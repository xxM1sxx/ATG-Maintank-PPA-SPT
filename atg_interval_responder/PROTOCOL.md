# Protokol Request Interval Log ATG

Program: `atg_mqtt_sub` (device) — meminta pihak lain mengubah interval log
secara dinamis mengikuti gerakan flowmeter (EQEP).

## 1. Topik (broker lokal / mosquitto)

| Arah | Topic | QoS |
|---|---|---|
| device → server | `maintank/atg/interval/request` | 1 |
| server → device | `maintank/atg/interval/response` | 1 |

`atg_mqtt_sub` **subscribe** `maintank/atg/interval/response`.

## 2. Request (device → server)

```json
{
  "command": "set_log_interval",
  "timestamp": "2026-11-18T10:23:15Z",
  "data": {
    "log_interval": 5
  }
}
```

- `command` selalu `"set_log_interval"`.
- `timestamp` = UTC ISO8601 saat request dibuat.
- `data.log_interval` = **5** saat flowmeter bergerak, **120** saat diam >20 detik.

## 3. Response (server → device)

```json
{"status":"ok"}
```

- Balas `status` = `"ok"` (atau `"success"`) setelah request diterima/diterapkan.
- Device menunggu balasan; bila tidak ada balasan, request dikirim ulang tiap
  **10 detik, maksimal 5×**. Setelah di-ack (atau menyerah setelah 5×), device
  berhenti mengirim sampai `log_interval` target berubah.

## 4. Perilaku device

- Tiap 1 detik membaca pulsa EQEP `/sys/.../48304180.eqep/position`.
- Ada perubahan pulsa → target **5s** (grace 20s setelah gerakan terakhir).
- Diam > 20 detik → target **120s**.
- Request hanya dikirim saat target berubah (atau retry sampai di-ack).
- Device **TIDAK** mengubah interval sendiri — hanya memberi tahu; pihak
  penerima yang menetapkan interval.

## 5. Yang harus dilakukan pihak penerima

1. Subscribe `maintank/atg/interval/request`.
2. Parse JSON, ambil `data.log_interval`.
3. Terapkan interval log (mis. update `TBL_SPT_SETTING.interval_log_atg`).
4. Publish `{"status":"ok"}` ke `maintank/atg/interval/response`.

## 6. Contoh uji (di device)

```bash
# lihat request yang dikirim device
mosquitto_sub -h 127.0.0.1 -u admin -P '?#Qimtronics#?' \
  -t maintank/atg/interval/request -v

# balas ok secara manual
mosquitto_pub -h 127.0.0.1 -u admin -P '?#Qimtronics#?' \
  -t maintank/atg/interval/response -m '{"status":"ok"}'
```

## 7. Minimal responder (contoh Python paho-mqtt)

```python
import json
import paho.mqtt.client as mqtt

def on_connect(c, u, flags, rc):
    c.subscribe("maintank/atg/interval/request", qos=1)

def on_message(c, u, m):
    try:
        req = json.loads(m.payload)
    except Exception:
        return
    if req.get("command") == "set_log_interval":
        iv = req.get("data", {}).get("log_interval")
        # TODO: terapkan iv ke interval log di sisi Anda
        c.publish("maintank/atg/interval/response",
                  json.dumps({"status": "ok"}), qos=1)

c = mqtt.Client()
c.username_pw_set("admin", "?#Qimtronics#?")
c.on_connect = on_connect
c.on_message = on_message
c.connect("127.0.0.1", 1883, 60)
c.loop_forever()
```

## 8. Catatan

- Response **tidak** memuat `req_id`; device menganggap `status:"ok"` sebagai ack
  untuk request terakhir yang belum dijawab.
- Balas secepat mungkin. Delay > 10s memicu retry (maks 5×, lalu menyerah
  sampai target berubah).
- Protokol ini terpisah dari program `atg_interval` (yang menulis interval
  sendiri); tidak saling bergantung.
