# MQTT Documentation — atg-reader

`atg-reader` publishes fuel-tank telemetry over MQTT using a single broker
connection (libmosquitto), split across three topics with different purposes.
All three share the same broker host/port/username/password, configured once
in the web UI (Settings → MQTT Settings).

## Topics overview

| # | Topic (web field) | Direction | Purpose |
|---|---|---|---|
| 1 | **Local Topic** — `mqtt_topic_path` | Publish only | Routine telemetry for local/on-prem consumers |
| 2 | **Cloud Data Topic** — `cloud_topic_path` | **Publish + Subscribe** | Periodic (every `cloud_interval`) telemetry to a cloud endpoint, with its ack read back on this same topic |
| 3 | **Cloud Request/Replay Topic** — `cloud_request_topic` | **Publish + Subscribe** | Receives `{"command":"get_pending"}` triggers; replay batches are published back here too, and their ack is read back on this same topic |

Configured in the web UI under Settings → **MQTT Settings** (broker/topic #1)
and **Cloud MQTT** (topics #2 and #3, publish interval, enable toggle, and
whether ack failures get queued to the database).

## Architecture — who confirms delivery, and how

`atg-reader` does **not** talk to the cloud directly. The real flow is:

```
atg-reader --publish--> Broker --forward--> Downstream Controller --push--> Cloud
                                                     |
atg-reader <--ack (same topic it was sent on)-- Broker <--publish---+
```

`atg-reader` publishes to either the Cloud Data Topic (periodic telemetry) or
the Cloud Request/Replay Topic (replay batches) - see the table above. A
separate **downstream controller** (not part of this repo) is subscribed to
both, is actually responsible for pushing the data to the cloud (however it
does that — HTTP API, its own MQTT bridge, etc.), and then publishes an
**application-level ack back on that same topic** it received the message on,
reporting whether the push to the cloud succeeded or failed.

This means: MQTT's own QoS 1 PUBACK (the broker saying "I received your
publish") is **not** treated as delivery confirmation for the cloud path —
it only proves the message reached the broker, not that it reached the
cloud. `atg-reader` subscribes to both topics itself so it can receive the
downstream controller's acks, and correlates each ack to the original message
via a `message_id` field it generates and embeds in every outgoing cloud
payload. **The ack always comes back on the same topic the message was sent
on** — telemetry acks on the Data Topic, replay batch acks on the
Request/Replay Topic.

## 1. Local Topic — routine telemetry

- **Interval**: `mqtt_interval` (default 30s, range 1–300s)
- **Reliability**: none — `mosquitto_publish()` is called and the result is
  logged, but no delivery confirmation of any kind is tracked. If the message
  never arrives anywhere downstream, nothing here would know.
- **Payload**: one JSON object per publish, all enabled+successfully-read
  sensors. No `message_id` field (not needed — nothing acks this topic).

### Example payload

```json
{
  "company": "PT. Putra Perkasa Abadi",
  "plant_id": "PPA-BA",
  "data": [
    {
      "slocn": "FS-01",
      "number": "01",
      "capacity_l": 40000,
      "fuel_level_mm": 2500,
      "volume_l": 30000,
      "temperature_c": 26,
      "timestamp": "2026-08-18T04:15:00Z"
    }
  ]
}
```

A sensor is **only** included if its last Modbus read succeeded (`ok=1` in
`tbl_atg_reading`). A failed read is never sent as if it were a real 0 mm /
0 L reading — it's just omitted from `data[]` until the next successful read.
This filtering applies to the Cloud Data Topic too (same underlying query).

**`volume_l` is total liquid volume** (`TeraTable(fuel_level_mm)` looked up
directly, no water subtraction) — this is the original/legacy MQTT field
shape and is deliberately kept as-is. The SP400 probe's fuel float rides on
top of everything, so `fuel_level_mm` is actually the *total* liquid
sounding (fuel + water combined), not fuel alone.

**`fuel_level_mm` includes the sensor's calibration offset** (raw probe
reading + `offset_mm`, i.e. the true "sounding" height) — not the raw,
unadjusted probe value — because that's the exact height `volume_l` was
computed from. Reporting the raw value here while `volume_l` was computed
from the offset-adjusted height would make the two fields inconsistent with
each other.

A separate fuel/water breakdown (fuel-only volume, water-layer volume, and
this same total) **is** computed and stored per reading
(`tbl_atg_reading.volume` / `volume_water_l` / `volume_total_l`), but is only
surfaced in the **web UI** (Status → Fuel Level Sensors: "Fuel Vol (L)" /
"Water Vol (L)" / "Total Vol (L)" columns) — it is not sent over MQTT.

### Log lines

```
[INFO] [MQTT] Publish queued mid=13 topic=maintank/atg/value sensors=2 bytes=210
[INFO] [MQTT] Broker received publish mid=13 (protocol-level only)
```

## 2. Cloud Data Topic — reliable telemetry with a real delivery ack

- **Interval**: `cloud_interval` (default 120s, range 1–300s), independent
  timer from the Local Topic.
- **Ack window**: **10 seconds**. If the downstream controller's ack for a
  given `message_id` doesn't arrive within that window, the publish is
  treated as failed.
- **On failure** — three distinct cases, all with the same outcome (queued or
  dropped, depending on `cloud_store_on_fail`):
  1. `mosquitto_publish()` itself fails (broker unreachable) — queued
     immediately, no wait.
  2. Downstream controller explicitly replies `"status":"failed"` — queued
     immediately (doesn't wait out the rest of the 10s window).
  3. No ack at all arrives within 10s — queued once the window expires.
- **On explicit success** (`"status":"success"` ack received) — nothing
  further happens; the payload was genuinely delivered to the cloud.

### Outgoing payload — telemetry

Same sensor fields as the Local Topic, but wrapped with `message_id` and
`type` so the ack can be correlated back to it:

```json
{
  "message_id": "atg-1755500142123-42",
  "type": "telemetry",
  "company": "PT. Putra Perkasa Abadi",
  "plant_id": "PPA-BA",
  "data": [
    {
      "slocn": "FS-01",
      "number": "01",
      "capacity_l": 40000,
      "fuel_level_mm": 2500,
      "volume_l": 30000,
      "temperature_c": 26,
      "timestamp": "2026-08-18T04:15:00Z"
    }
  ]
}
```

`message_id` format: `atg-<unix epoch ms>-<sequence counter>`. Guaranteed
unique per process run (monotonic counter), not persisted across restarts.

### Expected ack payload (published by the downstream controller, same topic)

```json
{ "message_id": "atg-1755500142123-42", "status": "success" }
```
```json
{ "message_id": "atg-1755500142123-42", "status": "failed" }
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `message_id` | string | yes | Must exactly match the `message_id` from the payload being acked |
| `status` | string | yes | Either `"success"` or `"failed"` — anything else is treated as failed for logging purposes, but the row/queue action still fires |

Both fields are parsed with the same small hand-rolled single-field
extractor used for the request-topic `command` field (see below) — not a
general JSON parser, so keep ack payloads to just these two fields.

**Note on echoed messages**: because `atg-reader` subscribes to the same
topic it publishes on, the broker will also deliver its own outgoing
telemetry/replay-batch messages back to it. Those are harmlessly ignored —
they have a `message_id` but no `status` field, and anything missing either
field is skipped.

### Log lines — success

```
[INFO] [MQTT-CLOUD] Telemetry sent message_id=atg-1755500142123-42 topic=maintank/atg/cloud/value mid=26 bytes=210, awaiting cloud ack (up to 10s)
[INFO] [MQTT-CLOUD] Ack SUCCESS message_id=atg-1755500142123-42 (telemetry) rtt=340ms
```

`rtt` here is the real round trip: broker → downstream controller → cloud →
downstream controller → broker → back to `atg-reader`. Expect this to be
noticeably slower than a raw broker PUBACK (which only measures broker
round trip) — hundreds of ms to a couple of seconds is normal depending on
the cloud endpoint's latency.

### Log lines — explicit failure ack

```
[WARN] [MQTT-CLOUD] Ack FAILED message_id=atg-1755500142123-42 status=failed (telemetry) rtt=512ms
[WARN] [MQTT-CLOUD] Stored to pending queue (explicit failure ack)
```

### Log lines — silent timeout (no ack at all)

```
[WARN] [MQTT-CLOUD] Telemetry ack timeout (no reply within 10s) -> stored to pending queue (bytes=210)
```

### Verifying the timeout path independently

Point `cloud_topic_path` at a broker/port that's unreachable (or don't run a
downstream controller at all) and watch for the timeout log line above, then
confirm the row landed in the database:

```bash
sqlite3 /home/user/Apps/qims_logger.db \
  "SELECT id, created_at FROM tbl_atg_cloud_pending ORDER BY id DESC LIMIT 1;"
```

## 3. Cloud Request/Replay Topic — draining the pending queue

`atg-reader` **publishes AND subscribes** to this topic — it's bidirectional,
unlike the name might suggest. Incoming messages here are checked for a
`command` field first (the trigger); a valid `{"command":"get_pending"}`
starts draining `tbl_atg_cloud_pending`. Anything without a recognized
`command` field is then checked as a possible ack (see below) instead.
Malformed/unrecognized `command` values are logged and ignored — they do
**not** start a replay.

### Request payload schema

```json
{ "command": "get_pending" }
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `command` | string | yes | Must be exactly `"get_pending"`. Any other value is ignored. |

### Triggering a replay

```bash
mosquitto_pub -h <broker> -u <user> -P <pass> \
  -t "maintank/atg/cloud/request" -m '{"command":"get_pending"}'
```

### Batch behavior

**One request = one batch. There is no auto-continue.** Each valid
`{"command":"get_pending"}` triggers exactly one attempt to send up to 50
rows; it never loops to send more on its own, even if hundreds of rows are
still pending afterward. To drain more, the downstream controller sends
another `{"command":"get_pending"}` request — as many times as needed.

- Reads up to **50 oldest** pending rows (oldest = lowest `id` first, FIFO —
  so a steady trickle of new failures never starves older queued data).
- Wraps them into one outgoing envelope (its own `message_id`, `type` =
  `"replay_batch"`, and a `timestamp` marking when the batch was sent — separate
  from each record's own original `timestamp` nested inside `records[]`) and
  publishes it back to **this same Request/Replay Topic** — batches do NOT go
  to the Cloud Data Topic (that's telemetry-only).
- Waits up to **10s** for that batch's ack, expected back on this same
  Request/Replay Topic (same success/failed/timeout rules as telemetry above,
  just on a different topic).
  - **Ack succeeds** → those rows are deleted from the table.
  - **Ack fails or times out** → rows are **not deleted**, stay queued exactly
    as they were. Nothing is resent automatically — wait for the next
    `get_pending` request, which will offer the same oldest-50 again.
- If fewer than 50 rows were pending, that smaller batch is sent as-is (no
  padding/waiting for more to accumulate).

### Example batch payload (2 queued records)

```json
{
  "message_id": "atg-1755500300111-77",
  "type": "replay_batch",
  "timestamp": "2026-08-18T06:30:00Z",
  "count": 2,
  "records": [
    {
      "message_id": "atg-1755500142123-42",
      "type": "telemetry",
      "company": "PT. Putra Perkasa Abadi",
      "plant_id": "PPA-BA",
      "data": [{"slocn":"FS-01","number":"01","capacity_l":40000,"fuel_level_mm":2500,"volume_l":30000,"temperature_c":26,"timestamp":"2026-08-18T04:10:00Z"}]
    },
    {
      "message_id": "atg-1755500144876-43",
      "type": "telemetry",
      "company": "PT. Putra Perkasa Abadi",
      "plant_id": "PPA-BA",
      "data": [{"slocn":"FS-01","number":"01","capacity_l":40000,"fuel_level_mm":2510,"volume_l":30100,"temperature_c":26,"timestamp":"2026-08-18T04:12:00Z"}]
    }
  ]
}
```

Each entry in `records[]` is the exact original envelope that failed —
including its own original (now-irrelevant) `message_id`. The ack that
matters for this whole batch is the **outer** `message_id`
(`atg-1755500300111-77`), not the ones nested inside `records[]`.

### Expected batch ack (same shape as telemetry ack)

```json
{ "message_id": "atg-1755500300111-77", "status": "success" }
```

### Log lines — one request, 63 rows pending (only 50 get sent)

```
[INFO] [MQTT-CLOUD] Replay request received (command=get_pending)
[INFO] [MQTT-CLOUD] Replay request: 63 rows pending
[INFO] [MQTT-CLOUD] Replay batch: sending 50 rows (ids 1..50)
[INFO] [MQTT-CLOUD] Replay batch: sent message_id=atg-...-77 topic=... mid=31 bytes=8420, awaiting cloud ack (up to 10s)
[INFO] [MQTT-CLOUD] Ack SUCCESS message_id=atg-...-77 (replay batch) rtt=610ms
[INFO] [MQTT-CLOUD] Replay batch: ack OK, 50 rows delivered and removed from queue (13 rows still pending)
```

The remaining 13 rows are **not** sent automatically. A second
`{"command":"get_pending"}` request is needed to get them:

```
[INFO] [MQTT-CLOUD] Replay request received (command=get_pending)
[INFO] [MQTT-CLOUD] Replay request: 13 rows pending
[INFO] [MQTT-CLOUD] Replay batch: sending 13 rows (ids 51..63)
[INFO] [MQTT-CLOUD] Ack SUCCESS message_id=atg-...-78 (replay batch) rtt=455ms
[INFO] [MQTT-CLOUD] Replay batch: ack OK, 13 rows delivered and removed from queue (0 rows still pending)
```

### Log lines — ack fails

```
[INFO] [MQTT-CLOUD] Replay batch: sending 50 rows (ids 51..100)
[WARN] [MQTT-CLOUD] Ack FAILED message_id=atg-...-79 status=failed (replay batch) rtt=890ms
[WARN] [MQTT-CLOUD] Replay batch: ack FAILED or timed out, 50 rows stay queued (not deleted)
```

Those 50 rows remain untouched — the next `get_pending` request will offer
the exact same oldest-50 again (nothing was resent automatically in between).

## Pending/failed-queue table — `tbl_atg_cloud_pending`

```sql
CREATE TABLE tbl_atg_cloud_pending (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payload TEXT NOT NULL,               -- the exact JSON envelope that failed
    created_at TEXT NOT NULL DEFAULT (datetime('now'))  -- UTC, auto-filled
);
```

| Column | Meaning |
|---|---|
| `id` | Insert order = replay order (oldest first) |
| `payload` | The full `{"message_id":...,"type":"telemetry",...}` envelope as it was sent (or attempted) |
| `created_at` | UTC timestamp of when it was queued |

Rows leave this table in exactly two ways:
1. **Delivered via replay** — deleted right after that batch's ack succeeds.
2. **Memory-pressure trim** — if BeagleBone RAM usage reaches **80%** (checked
   once a minute), the oldest rows are deleted down to a cap of **500** rows,
   regardless of replay activity. This is a safety valve independent of the
   replay mechanism, in case the cloud never sends a replay request.

### Inspecting the queue directly

```bash
# how many rows are pending
sqlite3 /home/user/Apps/qims_logger.db "SELECT COUNT(*) FROM tbl_atg_cloud_pending;"

# oldest 10, with a payload preview
sqlite3 /home/user/Apps/qims_logger.db \
  "SELECT id, substr(payload,1,80), created_at FROM tbl_atg_cloud_pending ORDER BY id ASC LIMIT 10;"
```

Or via the web UI: Settings → **Download** tab shows the same data with a
"Download CSV" export of the full table.

## Web configuration reference

| Field | Table column | Default | Notes |
|---|---|---|---|
| Broker Host/Port/Username/Password | `tbl_atg_mqtt_setting.broker_host` etc. | — | Shared by all 3 topics |
| Local Topic Path | `topic_path` | `maintank/atg/value` | Topic #1 |
| Local Publish Interval | `publish_interval_sec` | 30s | Topic #1 |
| Cloud Data Topic | `cloud_topic_path` | `maintank/atg/cloud/value` | Topic #2 (published + subscribed; telemetry only) |
| Cloud Request/Replay Topic | `cloud_request_topic` | `maintank/atg/cloud/request` | Topic #3 (published + subscribed; commands in, replay batches + their acks both ways) |
| Cloud Publish Interval | `cloud_publish_interval_sec` | 120s | Topic #2 |
| Enable Cloud MQTT | `cloud_enabled` | off | Gates topics #2 and #3 entirely |
| Save to database when ACK fails | `cloud_store_on_fail` | on | If off, failed cloud publishes are dropped, not queued |

Ack timeout (10s) and replay batch size (50) are currently fixed in firmware,
not web-configurable.

The two publish intervals (**Local Publish Interval**, **Cloud Publish
Interval**) are re-read from `tbl_atg_mqtt_setting` every 5 seconds by the main
loop, so changing them in the web UI takes effect within ~5s with **no
restart** — logged when applied:

```
[INFO] [MQTT-CLOUD] cloud publish interval 120 -> 5s (live)
```

Everything else (broker host/port, credentials, topic paths, `enabled` /
`cloud_enabled`, `cloud_store_on_fail`) is still loaded once at startup and
requires a restart, because it needs a reconnect/resubscribe:

```bash
sudo systemctl restart atg-reader
```

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| No `[MQTT-CLOUD]` log lines at all | `cloud_enabled` is off, or broker host is empty |
| `Subscribe to data topic failed` / `Subscribe to request topic failed` | Broker rejected the subscribe — check ACL/permissions for that topic |
| `Ack received for unknown/expired message_id` | The downstream controller replied after `atg-reader` already gave up waiting (>10s), or `message_id` doesn't match exactly (typo, wrong quoting) |
| Telemetry always times out, never gets an explicit ack | No downstream controller is actually subscribed to the Cloud Data Topic and replying — check its logs/connection, not `atg-reader`'s |
| Replay batch always times out, never gets an explicit ack | Downstream controller must reply on the **Request/Replay Topic**, not the Data Topic — replying on the wrong topic looks identical to "no reply" from `atg-reader`'s side |
| `Request received with unrecognized command=..., ignoring` | Payload's `command` field isn't `"get_pending"` exactly |
| Pending queue keeps growing, never drains | Nobody is publishing `{"command":"get_pending"}` to the request topic, or the downstream controller keeps replying `"status":"failed"` on it |
| `tbl_atg_cloud_pending` count stuck at 500 | Memory-pressure trim is active — RAM usage is ≥80%, check with `free -h` |
| Local topic works but cloud doesn't | Check `cloud_enabled` and that `cloud_topic_path`/`cloud_request_topic` aren't empty (both required when cloud is enabled) |
