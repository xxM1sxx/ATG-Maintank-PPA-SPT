# Graph Report - .  (2026-08-05)

## Corpus Check
- Corpus is ~18,167 words - fits in a single context window. You may not need a graph.

## Summary
- 363 nodes · 458 edges · 37 communities (32 shown, 5 thin omitted)
- Extraction: 89% EXTRACTED · 11% INFERRED · 0% AMBIGUOUS · INFERRED: 50 edges (avg confidence: 0.82)
- Token cost: 43,984 input · 0 output

## Community Hubs (Navigation)
- atomic, sensor_manager....
- atg_db.hpp, AtgDatabase...
- FloatFormat, parse_sp400_pay...
- AtgConfig, baudrate...
- close, platform_stub_w...
- Bps, DataBits...
- read_sensor.cpp, main()...
- atg_protocol.cp, AtgProtocol (Ho...
- GpioPin, dir_path_...
- serial_port.cpp, Bps...
- converter_, read_volume...
- atg_db.cpp (SQL, atg-reader (Mod...
- stop, sensor_manager....
- modbus.cpp, build_request()...
- test_modbus.cpp, main()...
- atg.hpp, atg_protocol.hp...
- extract_calibra, extract_pairs()...
- syslog_logrotat, build_rsyslog_l...
- reboot_schedule, sync_reboot_sch...
- AtgConfig (centraliz & config.cpp (configur
- build-bbb.sh & build-bbb.sh script
- platform_stub_win.cpp (Windows stub impl

## God Nodes (most connected - your core abstractions)
1. `AtgConfig` - 25 edges
2. `SerialPort` - 24 edges
3. `AtgProtocol` - 21 edges
4. `SensorManager` - 20 edges
5. `TankReading` - 20 edges
6. `GpioPin` - 17 edges
7. `SensorConfig` - 16 edges
8. `SensorResult` - 11 edges
9. `main()` - 10 edges
10. `AtgDatabase` - 9 edges

## Surprising Connections (you probably didn't know these)
- `AtgDatabase::upsert_reading()` --calls--> `load_offset_mm`  [INFERRED]
  Maintank_PPA/src/atg_db.cpp → Maintank_PPA/include/atg_db.hpp
- `AtgDatabase::upsert_reading()` --calls--> `load_volume_points`  [INFERRED]
  Maintank_PPA/src/atg_db.cpp → Maintank_PPA/include/atg_db.hpp
- `main()` --calls--> `get`  [INFERRED]
  Maintank_PPA/libs/atg-sdk/examples/read_sensor.cpp → Maintank_PPA/libs/atg-sdk/include/atg/gpio.hpp
- `AtgProtocol::read_all()` --calls--> `read_input_registers`  [INFERRED]
  Maintank_PPA/libs/atg-sdk/src/atg_protocol.cpp → Maintank_PPA/libs/atg-sdk/include/atg/atg_protocol.hpp
- `AtgProtocol::read_all()` --calls--> `parse_sp400_payload`  [INFERRED]
  Maintank_PPA/libs/atg-sdk/src/atg_protocol.cpp → Maintank_PPA/libs/atg-sdk/include/atg/atg_protocol.hpp

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Multi-sensor polling architecture** — sensormanager_class, serialport_class, atgprotocol_class, modbus_protocol, tankreading_struct [INFERRED 0.85]
- **Platform-specific abstraction layer** — serial_port_cpp, gpio_cpp, sensor_manager_cpp, platform_stub_win_cpp, beaglebone_linux_platform [INFERRED 0.75]
- **ATG Reader deployment pipeline** — maintank_ppa_build_bbb_script, atg_reader_executable, atg_reader_service, beaglebone_linux_platform [INFERRED 0.80]

## Communities (37 total, 5 thin omitted)

### Community 0 - "atomic, sensor_manager...."
Cohesion: 0.07
Nodes (34): atomic, Bps, Callback, milliseconds, string, SensorConfig, baudrate, de_gpio_pin (+26 more)

### Community 1 - "atg_db.hpp, AtgDatabase..."
Cohesion: 0.09
Nodes (20): AtgDatabase, db_, load_enabled_sensors, load_offset_mm, load_volume_points, upsert_reading, VolumePoint, sounding_mm (+12 more)

### Community 2 - "FloatFormat, parse_sp400_pay..."
Cohesion: 0.13
Nodes (25): FloatFormat, parse_sp400_payload, read_input_registers, atg_debug_enabled(), atg_debug_hex(), atg_debug_log(), atg_debug_timestamp(), AtgProtocol::AtgProtocol() (+17 more)

### Community 3 - "AtgConfig, baudrate..."
Cohesion: 0.09
Nodes (24): AtgConfig, baudrate, data_bits, de_gpio_pin, defaults, device, from_args, from_file (+16 more)

### Community 4 - "close, platform_stub_w..."
Cohesion: 0.08
Nodes (11): close, Bps, DataBits, milliseconds, Parity, SerialPort, StopBits, string (+3 more)

### Community 5 - "Bps, DataBits..."
Cohesion: 0.09
Nodes (21): Bps, DataBits, milliseconds, Parity, StopBits, string, SerialPort, baudrate_ (+13 more)

### Community 6 - "read_sensor.cpp, main()..."
Cohesion: 0.12
Nodes (20): main(), AtgProtocol, de_pin_, kSp400InputRegCount, kSp400InputRegStart, read_all, read_tera_volume, read_water (+12 more)

### Community 7 - "atg_protocol.cp, AtgProtocol (Ho..."
Cohesion: 0.12
Nodes (20): atg_protocol.cpp (Holykell probe protocol), AtgProtocol (Holykell SP400 Modbus probe interface), Modbus type 10007 (Holykell IEEE float encoding), gpio.cpp (Linux sysfs GPIO), GpioPin (sysfs GPIO DE/RE control), Holykell SP400 (ATG tank gauge probe), main.cpp (atg-reader entry point), modbus.cpp (Modbus protocol implementation) (+12 more)

### Community 8 - "GpioPin, dir_path_..."
Cohesion: 0.11
Nodes (11): GpioPin, dir_path_, export_pin, fd_value_, gpio_, is_exported, set, unexport_pin (+3 more)

### Community 9 - "serial_port.cpp, Bps..."
Cohesion: 0.12
Nodes (13): Bps, DataBits, milliseconds, Parity, SerialPort, StopBits, string, SerialPort::configure() (+5 more)

### Community 10 - "converter_, read_volume..."
Cohesion: 0.11
Nodes (18): converter_, read_volume, string, TankReading, fuel_level_mm, fuel_temp_c, tank_number, temp_a_c (+10 more)

### Community 11 - "atg_db.cpp (SQL, atg-reader (Mod..."
Cohesion: 0.15
Nodes (13): atg_db.cpp (SQLite database operations), atg-reader (Modbus daemon), atg-reader.service (systemd unit), atg-sdk (C++17 RS485 sensor library), BeagleBone Linux (target platform), Maintak_PPA (root project), BUILD-BBB.md (deployment documentation), build-bbb.sh (BeagleBone build and deploy) (+5 more)

### Community 12 - "stop, sensor_manager...."
Cohesion: 0.18
Nodes (8): stop, bps_from_int(), Bps, Callback, vector, SensorManager::latest(), SensorManager::SensorManager(), SensorManager::set_callback()

### Community 13 - "modbus.cpp, build_request()..."
Cohesion: 0.29
Nodes (10): build_request(), milliseconds, SerialPort, crc16(), find_frame(), frame_len_at(), frame_length(), read_response() (+2 more)

### Community 14 - "test_modbus.cpp, main()..."
Cohesion: 0.35
Nodes (10): main(), test_build_request(), test_config_defaults(), test_crc16(), test_fc04_response_frame(), test_find_frame(), test_sp400_float_parse(), test_sp400_fuel_volume_only() (+2 more)

### Community 17 - "extract_calibra, extract_pairs()..."
Cohesion: 0.83
Nodes (3): extract_pairs(), main(), parse_num()

### Community 18 - "syslog_logrotat, build_rsyslog_l..."
Cohesion: 0.83
Nodes (3): build_rsyslog_logrotate_content(), sync_rsyslog_logrotate_from_db(), update_rsyslog_logrotate_config()

## Knowledge Gaps
- **100 isolated node(s):** `load_enabled_sensors`, `upsert_reading`, `db_`, `sounding_mm`, `volume_l` (+95 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **5 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `SerialPort` connect `Bps, DataBits...` to `close, platform_stub_w...`, `atg.hpp, atg_protocol.hp...`?**
  _High betweenness centrality (0.162) - this node is a cross-community bridge._
- **Why does `GpioPin` connect `GpioPin, dir_path_...` to `close, platform_stub_w...`, `read_sensor.cpp, main()...`, `atg.hpp, atg_protocol.hp...`?**
  _High betweenness centrality (0.148) - this node is a cross-community bridge._
- **Why does `AtgConfig` connect `AtgConfig, baudrate...` to `atg.hpp, atg_protocol.hp...`?**
  _High betweenness centrality (0.101) - this node is a cross-community bridge._
- **What connects `load_enabled_sensors`, `upsert_reading`, `db_` to the rest of the system?**
  _100 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `atomic, sensor_manager....` be split into smaller, more focused modules?**
  _Cohesion score 0.06685633001422475 - nodes in this community are weakly interconnected._
- **Should `atg_db.hpp, AtgDatabase...` be split into smaller, more focused modules?**
  _Cohesion score 0.08866995073891626 - nodes in this community are weakly interconnected._
- **Should `FloatFormat, parse_sp400_pay...` be split into smaller, more focused modules?**
  _Cohesion score 0.1282051282051282 - nodes in this community are weakly interconnected._