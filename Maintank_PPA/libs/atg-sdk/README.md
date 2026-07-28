# atg-sdk

C++17 library untuk membaca sensor ATG tank gauge via USB-to-RS485 (Modbus RTU) di BeagleBone Linux.

## Requirements

- CMake ≥ 3.10
- C++17 compiler (g++ ≥ 8)
- Linux dengan `termios.h`, `poll.h`, sysfs GPIO
- Tidak perlu library eksternal (pure POSIX + stdlib)

## Build

```bash
cd atg-sdk
mkdir build && cd build
cmake ..
make
sudo make install          # opsional: ke /usr/local
```

Build dengan contoh & tes:

```bash
cmake .. -DATG_BUILD_EXAMPLES=ON -DATG_BUILD_TESTS=ON
make
./tests/test_modbus        # jalanin tes
./examples/read_sensor     # jalanin contoh
```

## Architecture

```
SensorManager (multi-thread)
  ├── Sensor 0 ── SerialPort ── AtgProtocol ── modbus ── /dev/ttyUSB0
  ├── Sensor 1 ── SerialPort ── AtgProtocol ── modbus ── /dev/ttyUSB1
  ├── ...
  └── Sensor 5 ── SerialPort ── AtgProtocol ── modbus ── /dev/ttyUSB5
                       │
                  GpioPin (opsional: RS-485 DE/RE)
```

Setiap sensor punya USB-to-RS485 sendiri → thread sendiri → `SerialPort` + `AtgProtocol` sendiri.

---

## API Reference

## `atg::SerialPort` — serial port RS-485

```cpp
#include <atg/serial_port.hpp>
```

| Method | Deskripsi |
|--------|-----------|
| `SerialPort(device)` | Buka port `/dev/ttyUSB0` dll |
| `configure(baud, parity, data, stop, timeout)` | Set parameter komunikasi |
| `open()` | Buka file descriptor |
| `close()` | Tutup port |
| `is_open()` | Cek apakah terbuka |
| `read(buf, n)` | Baca bytes, return jumlah terbaca |
| `write(buf, n)` | Tulis bytes |
| `set_rts(bool)` | Set RTS pin (untuk RS-485 direction) |

**Contoh:**

```cpp
atg::SerialPort port("/dev/ttyUSB0");
port.configure(atg::Bps::Rate9600, atg::Parity::None,
               atg::DataBits::D8, atg::StopBits::One,
               std::chrono::milliseconds(3000));
port.open();
port.write(txbuf, len);
ssize_t n = port.read(rxbuf, sizeof(rxbuf));
```

---

## `atg::GpioPin` — sysfs GPIO (RS-485 DE/RE)

```cpp
#include <atg/gpio.hpp>
```

| Method | Deskripsi |
|--------|-----------|
| `GpioPin(gpio_number)` | Export & open GPIO |
| `set(high)` | Set value HIGH/LOW |
| `get()` | Baca value |
| `export_pin(n)` | static: export GPIO ke sysfs |
| `unexport_pin(n)` | static: unexport GPIO |

**Contoh:**

```cpp
atg::GpioPin de(48);          // GPIO48 = DE pin
de.set(true);                  // enable RS-485 driver
// ... kirim data ...
de.set(false);                 // disable, listener mode
```

Biarkan `de_gpio_pin = -1` di config kalau USB-to-485 auto-manage DE/RE (FTDI, CH340).

---

## `atg::modbus` — Modbus RTU protocol

```cpp
#include <atg/modbus.hpp>
```

### Fungsi

| Fungsi | Deskripsi |
|--------|-----------|
| `crc16(data, len)` | Hitung CRC16 Modbus |
| `build_request(dst, cap, slave, func, reg, count)` | Buat frame request |
| `find_frame(buf, n)` | Cari frame valid di buffer |
| `frame_length(func)` | Prediksi panjang response |
| `send_request(port, req, len)` | Kirim request ke serial |
| `read_response(port, buf, cap, timeout)` | Baca & validasi response |

**Contoh:**

```cpp
uint8_t req[8];
size_t n = atg::modbus::build_request(req, sizeof(req), 1,
    atg::ModbusFunc::ReadHoldingRegisters, 0x0000, 12);
atg::modbus::send_request(port, req, n);

uint8_t resp[256];
if (atg::modbus::read_response(port, resp, sizeof(resp), timeout)) {
    // resp[0] = slave, resp[1] = func, resp[2] = byte count, ...
}
```

### Enum `ModbusFunc`

| Value | Fungsi |
|-------|--------|
| `ReadHoldingRegisters` | 0x03 |
| `ReadInputRegisters` | 0x04 |
| `WriteSingleRegister` | 0x06 |
| `WriteMultipleRegisters` | 0x10 |

---

## `atg::AtgProtocol` — Holykell SP400 probe

Target sensor: **Holykell SP400** Modbus RTU (FC **0x04** Input Registers, 9600 8N1).

| Method | Deskripsi |
|--------|-----------|
| `read_all()` | Baca fuel mm, water mm, fuel temp, temp A–E (1 probe) |
| `read_volume()` | Alias: baca fuel level mm |
| `read_water()` | Baca water level mm |
| `set_response_timeout(ms)` | Min **3000 ms** recommended (probe needs ~1000 ms) |

Query: slave `04 00 00 00 10` — 16 input registers from 0x0000.

**Contoh:**

```cpp
atg::AtgProtocol atg(port, 1);
atg.set_response_timeout(std::chrono::milliseconds(3000));
auto readings = atg.read_all();
if (!readings.empty()) {
    const auto& r = readings.front();
    printf("Fuel=%.1f mm  Water=%.1f mm  Temp=%.1f C\n",
           r.fuel_level_mm, r.water_level_mm, r.fuel_temp_c);
}
```

### Struct `TankReading`

| Field | Deskripsi |
|-------|-----------|
| `fuel_level_mm` | Fuel level (mm) |
| `water_level_mm` | Water level (mm) |
| `fuel_temp_c` | Average fuel temperature (°C) |
| `temp_a_c` … `temp_e_c` | Point temperatures (°C) |
| `position_mm()` | Alias → `fuel_level_mm` |

Float format: Modbus type **10007** (`parse_ieee_float_10007`).

---

## `atg::AtgConfig` — konfigurasi terpusat

```cpp
#include <atg/config.hpp>
```

| Method | Deskripsi |
|--------|-----------|
| `defaults()` | Default config |
| `from_args(argc, argv)` | Parse CLI arguments |
| `from_file(path)` | Parse file `key=value` |

### Field

| Field | Default | CLI | File |
|-------|---------|-----|------|
| `device` | `"/dev/ttyUSB0"` | `--device` | `device` |
| `baudrate` | `Bps::Rate9600` | `--baud` | `baudrate` |
| `slave_id` | `1` | `--slave-id` | `slave_id` |
| `de_gpio_pin` | `-1` | `--de-gpio` | `de_gpio` |
| `timeout` | `3000ms` | `--timeout` | `timeout_ms` |
| `interval` | `5000` | `--interval` | `interval_ms` |
| `start_register` | `0x0000` | — | `start_register` |
| `register_count` | `12` | — | `register_count` |

**Contoh file config:**

```ini
device = /dev/ttyUSB2
baudrate = 9600
slave_id = 1
timeout_ms = 3000
interval_ms = 5000
```

---

## `atg::SensorManager` (multi-sensor, multi-thread)

```cpp
#include <atg/sensor_manager.hpp>
```

| Method | Deskripsi |
|--------|-----------|
| `SensorManager(configs)` | Daftar konfigurasi sensor |
| `start()` | Jalankan thread (satu per sensor) |
| `stop()` | Hentikan semua thread |
| `read_all()` | Baca semua sensor (blocking, paralel) |
| `latest()` | Ambil hasil terakhir (non-blocking) |
| `set_callback(cb)` | Callback real-time tiap kali sensor selesai baca |

### Struct `SensorConfig`

| Field | Tipe | Default |
|-------|------|---------|
| `device` | `std::string` | — |
| `slave_id` | `uint8_t` | `1` |
| `de_gpio_pin` | `int` | `-1` |
| `baudrate` | `Bps` | `Bps::Rate9600` |
| `timeout` | `milliseconds` | `3000ms` |

### Struct `SensorResult`

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| `id` | `int` | Index sensor |
| `readings` | `vector<TankReading>` | Hasil pembacaan tank |
| `ok` | `bool` | Sukses/gagal |
| `error` | `string` | Pesan error jika gagal |

**Contoh multi-sensor (6 thread):**

```cpp
auto mgr = atg::SensorManager(std::vector<atg::SensorConfig>{
    {"/dev/ttyUSB0", 1},   // sensor 1, slave 1
    {"/dev/ttyUSB1", 1},   // sensor 2, slave 1
    {"/dev/ttyUSB2", 1},   // sensor 3, slave 1
    {"/dev/ttyUSB3", 1},   // sensor 4, slave 1
    {"/dev/ttyUSB4", 1},   // sensor 5, slave 1
    {"/dev/ttyUSB5", 1},   // sensor 6, slave 1
});

mgr.set_callback([](int id, const atg::SensorResult& r) {
    if (!r.ok) {
        std::cerr << "Sensor " << id << " error: " << r.error << "\n";
        return;
    }
    for (auto& t : r.readings)
        std::cout << "Sensor" << id << " Tank" << t.tank_number
                  << ": " << t.volume << " L\n";
});

mgr.start();  // 6 thread berjalan paralel
// ... jalan terus ...
mgr.stop();
```

---

## Hardware — USB-to-RS485

### Wiring

```
USB-to-RS485            ATG Tank Gauge
┌──────────┐           ┌─────────────┐
│ A (+)    ├───────────┤ A (+)       │
│ B (-)    ├───────────┤ B (-)       │
│ GND      ├───────────┤ GND         │
│          │           │             │
│ DE/RE    ├─┬─┐       │ (optional)  │
│ (RTS)    │ │ │       │             │
└──────────┘ │ │       └─────────────┘
             │ │
         ┌──┘ └──┐
         │       │
(GPIO)  DE      RE
        ─────────── tie bersama untuk half-duplex
        (bisa pakai RTS dari USB jika didukung)
```

- **FTDI / CH340** — biasanya auto-manage DE/RE via RTS, tidak perlu GPIO
- **Manual** — set `de_gpio_pin` di config, library set DE high sebelum tx, low setelah tx

### Catatan

- Setiap USB-to-RS485 muncul sebagai `/dev/ttyUSB*` sendiri
- 6 sensor = 6 USB adapter = 6 port terpisah
- Baudrate default 9600 (standar ATG), bisa diubah via config
- Termination resistor 120Ω di ujung bus jika kabel panjang (>10m)

---

## Contoh lengkap `main.cpp`

```cpp
#include <atg/atg.hpp>
#include <iostream>

int main(int argc, char* argv[]) {
    auto cfg = atg::AtgConfig::from_args(argc, argv);

    atg::SerialPort port(cfg.device);
    port.configure(cfg.baudrate, cfg.parity, cfg.data_bits,
                   cfg.stop_bits, cfg.read_timeout);
    port.open();

    atg::AtgProtocol atg(port, cfg.slave_id);
    atg.set_response_timeout(cfg.read_timeout);

    auto tanks = atg.read_all();
    for (auto& t : tanks) {
        std::cout << "Tank " << t.tank_number
                  << ": " << t.volume << " L\n";
    }
    return 0;
}
```

Build:

```bash
g++ -std=c++17 main.cpp -latg-sdk -o read_atg
./read_atg --device /dev/ttyUSB0 --slave-id 1
```
