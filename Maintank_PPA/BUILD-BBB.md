# Build on BeagleBone (QIMSDEV)

## 1. Sync from PC

Copy the whole `Maintak_PPA` folder to `~/Projects/Maintak_PPA` on the board (scp, rsync, USB, git).

## 2. One-shot build + deploy

```bash
cd ~/Projects/Maintak_PPA
chmod +x scripts/build-bbb.sh
./scripts/build-bbb.sh
```

The script will:

- Fail fast if old `Bps::B0` code is still present (sync not done)
- Enable NTP, install deps, clean cmake build
- Install binary to `/home/user/Apps/bin/atg-reader`
- Restart `atg-reader` if systemd unit exists (skip with `SKIP_RESTART=1 ./scripts/build-bbb.sh`)

Clean build without restarting the service:

```bash
sudo systemctl stop atg-reader
SKIP_RESTART=1 ./scripts/build-bbb.sh
```

## 3. Install systemd unit (first time only)

```bash
sudo cp deploy/atg-reader.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now atg-reader
```

## 4. Web / DB

Open web UI once so `init_atg_table.php` creates ATG tables. Import calibration seeds per sensor (ATG Settings → Edit tabel → Import default).

### PHP settings for full-table Save (calibration editor)

Inline Save on ~2600 rows needs enough POST variables (~5300). On the board, set in Apache PHP ini (path varies, e.g. `/etc/php/7.4/apache2/php.ini`):

```ini
max_input_vars = 10000
post_max_size = 16M
```

Then restart the web server:

```bash
sudo systemctl restart apache2
```

Without this, Save may silently truncate rows. CSV import/export always works for full-table updates.

## 5. Debug Modbus / atg-reader logs

Debug is enabled by default in [`deploy/atg-reader.service`](deploy/atg-reader.service) via `ATG_DEBUG=1`. Logs go to systemd journal (not an interactive terminal).

```bash
sudo cp deploy/atg-reader.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl restart atg-reader
sudo journalctl -u atg-reader -f
```

You should see lines like:

- `atg-debug: modbus TX: 01 04 00 00 00 10 ...`
- `atg-debug: modbus RX: ...` (32 data bytes for SP400)
- `atg-debug: parsed fuel=963.0 water=120.0 temp=0.0`
- `atg-reader: sensor=1 device=/dev/ttyUSB0 slave=1 ok=1 fuel=...`

To disable debug: remove `Environment=ATG_DEBUG=1` from the service file, or set `ATG_DEBUG=0`.

### Foreground test (no systemd)

Stop the service first so `/dev/ttyUSB0` is free:

```bash
sudo systemctl stop atg-reader
```

Running `./atg-reader` alone does **not** enable Modbus hex logs — `ATG_DEBUG` is only set in the systemd unit. You must export it for manual runs:

```bash
cd ~/Projects/Maintak_PPA/build
ATG_DEBUG=1 ATG_RTS_DE=1 ATG_RS485_DELAY_MS=15 ./atg-reader /home/user/Apps/qims_logger.db
```

Startup always prints a banner (db path, sensor list). Modbus TX/RX hex appears only when `ATG_DEBUG=1`.

Installed binary (same flags):

```bash
ATG_DEBUG=1 /home/user/Apps/bin/atg-reader /home/user/Apps/qims_logger.db
```

### Python simulator (Windows laptop)

Ganti Modbus Slave dengan script bawaan — encoding Holykell sama dengan probe SP400 asli dan firmware.

**Volume (L) di web = lookup tabel kalibrasi dari fuel mm saja.** Water mm hanya ditampilkan; tidak dipakai hitung volume.

| Wire offset | Register | Field | Dipakai untuk volume? |
|-------------|----------|-------|------------------------|
| 0-3 | 0-1 | Fuel level (mm) | Ya |
| 4-7 | 2-3 | Water level (mm) | Tidak (monitoring saja) |
| 8-11 | 4-5 | Fuel temp (°C) | Tidak |

```bash
pip install -r tools/requirements-sim.txt
python tools/sp400_modbus_sim.py --port COM15 --verbose
```

Default: **fuel / water / temp random** setiap poll BBB (fuel 100–2500 mm, water 0–300 mm, temp 15–45 °C).

Fixed values:

```bash
python tools/sp400_modbus_sim.py --port COM15 --fuel 963 --water 120 --temp 25
python tools/sp400_modbus_sim.py --port COM15 --no-random --fuel 963 --water 120 --temp 25
```

1. Tutup Modbus Slave (satu program per COM port)
2. Laptop: jalankan sim di atas
3. BBB: `sudo systemctl stop atg-reader` lalu `ATG_DEBUG=1 ./atg-reader /home/user/Apps/qims_logger.db`
4. Expected RX: `01 04 20 ...` (32 data bytes), bukan `01 84 02`

Port laptop (COM15) harus paired dengan `/dev/ttyUSB0` di BBB.

Jika BBB log `Modbus read failed` tapi sim menerima request: **rebuild firmware** (fix flush RX + frame sync), naikkan `--reply-delay-ms 30` pada sim, pastikan hanya satu program pakai COM port.

```bash
# Laptop
python tools/sp400_modbus_sim.py --port COM15 --verbose --reply-delay-ms 30

# BBB — rebuild dulu setelah sync
cd ~/Projects/Maintak_PPA/build && cmake --build . && ATG_DEBUG=1 ./atg-reader /home/user/Apps/qims_logger.db
```

### Modbus Slave simulation checklist (alternatif)

| Setting | Value |
|---------|--------|
| Function | **04** Input Registers |
| Slave ID | Same as web ATG Settings (default **1**) |
| Baud | **9600-8-N-1** |
| Registers | At least **0–15** (16 registers) |
| Timeout (web) | **≥ 3000 ms** |

Example register values (fuel 963 mm, water 120 mm, temp 25 °C):

| Reg | 0 | 1 | 2 | 3 | 4 | 5 |
|-----|---|---|---|---|---|---|
| Val | **28740** | **192** | **61506** | **0** | **51265** | **0** |

(Each float uses 2 consecutive 16-bit registers — Holykell type 10007 encoding. Wire bytes for fuel: `70 44 00 C0`. Common mistake: `0, 11488` or `49152, 17520` parse to ~0 mm, which matches “Online but all 0.0”.)

**Modbus exception `fc=84 code=02`** in debug log means the slave received the request but rejected the register range. Fix in Modbus Slave: **Function 04**, **16 Input Registers (0–15)**, slave ID **1**. Expected RX after fix: `01 04 20 ...` (37 bytes), not `01 84 02` (5 bytes).
 — for unsigned values above 32767, enter `value - 65536`:

| Reg | Unsigned | Signed (Modbus Slave UI) |
|-----|----------|--------------------------|
| 0 | 28740 | -36796 |
| 2 | 61506 | -4030 |
| 4 | 51265 | -14271 |

If fuel/water stay 0 but RX hex shows non-zero data, try:

```bash
Environment=ATG_FLOAT_FORMAT=modbus_le
```

in the service file (simulator byte order). Default `holykell` matches the real SP400 probe datasheet.

### RS485 USB adapter (BBB ↔ laptop / probe)

Many USB-RS485 dongles need **RTS to toggle DE/RE**. Without it, BBB TX reaches the bus but **RX stays empty** (`Modbus read failed`, no `modbus RX` log).

Set on the board (or in [`deploy/atg-reader.service`](deploy/atg-reader.service)):

```bash
ATG_RTS_DE=1
ATG_RS485_DELAY_MS=15
```

Foreground test:

```bash
sudo systemctl stop atg-reader
ATG_DEBUG=1 ATG_RTS_DE=1 ATG_RS485_DELAY_MS=15 \
  ./atg-reader /home/user/Apps/qims_logger.db
```

If debug shows `read_response failed, got 0 bytes` → DE/wiring. If `got N bytes` with garbage → echo/noise or baud mismatch.

Web ATG Settings: `de_gpio_pin = -1` unless the board has a **GPIO DE line** wired separately. `read_timeout_ms` ≥ **3000**.

Hardware loopback (adapter A↔B shorted on BBB):

```bash
stty -F /dev/ttyUSB0 9600 raw -echo -icanon
echo -ne '\x01\x04\x00\x00\x00\x10\xF1\xC6' > /dev/ttyUSB0
timeout 1 hexdump -C /dev/ttyUSB0
```

Laptop sim with RS485: use `--reply-delay-ms 30` so the master can switch to RX before the response arrives.

