# atg-interval-responder

Penerima protokol "Request Interval Log ATG" (salinan spec: `PROTOCOL.md`).
Subscribe `maintank/atg/interval/request`, ambil
`data.log_interval`, tulis ke `tbl_atg_mqtt_setting` baris `id=1`, baca balik,
lalu balas `{"status":"ok"}` ke `maintank/atg/interval/response`.

Tidak mengubah apa pun di `atg-reader`: interval yang ditulis langsung dipakai
dalam <=5 detik karena main loop `atg-reader` membaca ulang dua kolom interval
tiap 5 detik (hot reload).

## Build (di BeagleBone)

```bash
sudo apt-get install -y build-essential cmake libsqlite3-dev libmosquitto-dev
cd ~/Projects/ATG-Maintank-PPA-SPT/atg_interval_responder
mkdir -p build && cd build
cmake .. && cmake --build . -j2
sudo cp atg-interval-responder /home/user/Apps/bin/
```

## Service

```bash
sudo cp ../atg-interval-responder.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now atg-interval-responder
sudo journalctl -u atg-interval-responder -f
```

Catatan: kalau folder ini di-copy dari Windows (bukan `git clone` di board),
ubah line ending dulu — `sudo dos2unix /etc/systemd/system/atg-interval-responder.service`.
Unit file ber-CRLF bisa gagal `status=203/EXEC`.

## Target kolom

| `--target` | Kolom yang ditulis | Efek |
|---|---|---|
| `cloud` (default) | `cloud_publish_interval_sec` | interval kirim ke cloud |
| `local` | `publish_interval_sec` | interval topik `maintank/atg/value` |
| `both` | keduanya | hati-hati: topik lokal default 30s ikut jadi 5s |

## Uji manual

```bash
mosquitto_pub -h 127.0.0.1 -u admin -P '?#Qimtronics#?' \
  -t maintank/atg/interval/request \
  -m '{"command":"set_log_interval","timestamp":"2026-11-18T10:23:15Z","data":{"log_interval":5}}'

mosquitto_sub -h 127.0.0.1 -u admin -P '?#Qimtronics#?' \
  -t maintank/atg/interval/response -v -C 1

sqlite3 /home/user/Apps/qims_logger.db \
  "SELECT publish_interval_sec, cloud_publish_interval_sec FROM tbl_atg_mqtt_setting WHERE id=1;"
```

Request tanpa balasan akan diulang device tiap 10 detik (maks 5x); setiap
request valid dibalas, termasuk saat nilainya sama dengan yang sudah ada.

## Yang ditolak (tidak di-ack, hanya log WARN)

- `command` ada tapi bukan `set_log_interval`
- `log_interval` tidak ada / bukan angka
- `log_interval` di luar 1..3600
- baris `tbl_atg_mqtt_setting` `id=1` tidak ada (DB belum pernah dibuka di web)
- UPDATE gagal (mis. `database is locked` setelah 5s)
