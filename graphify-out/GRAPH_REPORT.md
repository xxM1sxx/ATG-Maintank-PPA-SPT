# Graph Report - .  (2026-07-27)

## Corpus Check
- Corpus is ~10,985 words - fits in a single context window. You may not need a graph.

## Summary
- 185 nodes · 243 edges · 25 communities (23 shown, 2 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 16 edges (avg confidence: 0.9)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_ATG SDK Build Docs|ATG SDK Build Docs]]
- [[_COMMUNITY_Serial Port API|Serial Port API]]
- [[_COMMUNITY_ATG Configuration|ATG Configuration]]
- [[_COMMUNITY_ATG Protocol API|ATG Protocol API]]
- [[_COMMUNITY_Tank Reading Data|Tank Reading Data]]
- [[_COMMUNITY_GPIO Pin API|GPIO Pin API]]
- [[_COMMUNITY_Serial Port Impl|Serial Port Impl]]
- [[_COMMUNITY_Modbus Protocol|Modbus Protocol]]
- [[_COMMUNITY_ATG SDK Headers|ATG SDK Headers]]
- [[_COMMUNITY_GPIO Implementation|GPIO Implementation]]
- [[_COMMUNITY_Modbus Unit Tests|Modbus Unit Tests]]
- [[_COMMUNITY_Syslog Logrotate|Syslog Logrotate]]
- [[_COMMUNITY_Reboot Scheduling|Reboot Scheduling]]

## God Nodes (most connected - your core abstractions)
1. `AtgConfig` - 25 edges
2. `SerialPort` - 23 edges
3. `AtgProtocol` - 17 edges
4. `GpioPin` - 15 edges
5. `TankReading` - 11 edges
6. `atg-sdk Library Target` - 10 edges
7. `atg-sdk Library` - 10 edges
8. `atg::AtgProtocol` - 8 edges
9. `configure()` - 6 edges
10. `atg::SerialPort` - 6 edges

## Surprising Connections (you probably didn't know these)
- `set_de_pin()` --references--> `GpioPin`  [EXTRACTED]
  Maintak_PPA/libs/atg-sdk/src/atg_protocol.cpp → Maintak_PPA/libs/atg-sdk/include/atg/gpio.hpp
- `atg::SensorManager` --conceptually_related_to--> `atg-sdk Library Target`  [AMBIGUOUS]
  Maintak_PPA/libs/atg-sdk/README.md → Maintak_PPA/libs/atg-sdk/CMakeLists.txt
- `ATG_BUILD_TESTS Option` --references--> `test_modbus Executable`  [INFERRED]
  Maintak_PPA/libs/atg-sdk/CMakeLists.txt → Maintak_PPA/libs/atg-sdk/tests/CMakeLists.txt
- `serial_port.cpp` --implements--> `atg::SerialPort`  [INFERRED]
  Maintak_PPA/libs/atg-sdk/CMakeLists.txt → Maintak_PPA/libs/atg-sdk/README.md
- `modbus.cpp` --implements--> `atg::modbus`  [INFERRED]
  Maintak_PPA/libs/atg-sdk/CMakeLists.txt → Maintak_PPA/libs/atg-sdk/README.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **atg-sdk Library Source Modules** — atg_sdk_cmakelists_atg_sdk, atg_sdk_cmakelists_serial_port_cpp, atg_sdk_cmakelists_gpio_cpp, atg_sdk_cmakelists_modbus_cpp, atg_sdk_cmakelists_atg_protocol_cpp, atg_sdk_cmakelists_config_cpp [EXTRACTED 1.00]
- **Per-Sensor Communication Stack** — atg_sdk_readme_sensormanager, atg_sdk_readme_serialport, atg_sdk_readme_atgprotocol, atg_sdk_readme_modbus, atg_sdk_readme_gpiopin [EXTRACTED 1.00]
- **CMake Build Targets and Tests** — atg_sdk_cmakelists_atg_sdk, examples_cmakelists_read_sensor, tests_cmakelists_test_modbus, tests_cmakelists_modbus_test [EXTRACTED 1.00]

## Communities (25 total, 2 thin omitted)

### Community 0 - "ATG SDK Build Docs"
Cohesion: 0.11
Nodes (29): ATG_BUILD_EXAMPLES Option, ATG_BUILD_TESTS Option, atg_protocol.cpp, atg-sdk Library Target, config.cpp, C++17 Standard Requirement, gpio.cpp, modbus.cpp (+21 more)

### Community 1 - "Serial Port API"
Cohesion: 0.09
Nodes (22): SerialPort, baudrate_, close, configure, data_bits_, device_, fd_, is_open (+14 more)

### Community 2 - "ATG Configuration"
Cohesion: 0.11
Nodes (19): AtgConfig, baudrate, data_bits, de_gpio_pin, defaults, device, from_args, from_file (+11 more)

### Community 3 - "ATG Protocol API"
Cohesion: 0.13
Nodes (15): AtgProtocol, de_pin_, read_all, read_offset, read_registers, read_tera_volume, read_volume, set_de_pin (+7 more)

### Community 4 - "Tank Reading Data"
Cohesion: 0.19
Nodes (14): converter_, TankReading, offset, position_mm, tank_number, timestamp, volume, read_all() (+6 more)

### Community 5 - "GPIO Pin API"
Cohesion: 0.17
Nodes (9): GpioPin, dir_path_, export_pin, fd_value_, get, gpio_, is_exported, set (+1 more)

### Community 6 - "Serial Port Impl"
Cohesion: 0.22
Nodes (6): Bps, speed_t, close(), open(), SerialPort(), to_posix_baud()

### Community 7 - "Modbus Protocol"
Cohesion: 0.31
Nodes (9): ModbusFunc, SerialPort, AtgProtocol(), build_request(), crc16(), find_frame(), frame_length(), read_response() (+1 more)

### Community 9 - "GPIO Implementation"
Cohesion: 0.38
Nodes (3): export_pin(), GpioPin(), is_exported()

### Community 11 - "Modbus Unit Tests"
Cohesion: 0.60
Nodes (5): main(), test_build_request(), test_config_defaults(), test_crc16(), test_find_frame()

### Community 12 - "Syslog Logrotate"
Cohesion: 0.83
Nodes (3): build_rsyslog_logrotate_content(), sync_rsyslog_logrotate_from_db(), update_rsyslog_logrotate_config()

## Ambiguous Edges - Review These
- `atg-sdk Library Target` → `atg::SensorManager`  [AMBIGUOUS]
  Maintak_PPA/libs/atg-sdk/README.md · relation: conceptually_related_to

## Knowledge Gaps
- **59 isolated node(s):** `set_response_timeout`, `set_de_pin`, `read_volume`, `read_offset`, `read_tera_volume` (+54 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **2 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `atg-sdk Library Target` and `atg::SensorManager`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `AtgConfig` connect `ATG Configuration` to `ATG SDK Headers`, `Serial Port API`, `Serial Port Impl`?**
  _High betweenness centrality (0.133) - this node is a cross-community bridge._
- **Why does `SerialPort` connect `Serial Port API` to `ATG SDK Headers`, `Serial Port Impl`?**
  _High betweenness centrality (0.116) - this node is a cross-community bridge._
- **Why does `AtgProtocol` connect `ATG Protocol API` to `ATG SDK Headers`, `Serial Port API`, `Tank Reading Data`, `GPIO Pin API`?**
  _High betweenness centrality (0.098) - this node is a cross-community bridge._
- **What connects `set_response_timeout`, `set_de_pin`, `read_volume` to the rest of the system?**
  _61 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `ATG SDK Build Docs` be split into smaller, more focused modules?**
  _Cohesion score 0.11330049261083744 - nodes in this community are weakly interconnected._
- **Should `Serial Port API` be split into smaller, more focused modules?**
  _Cohesion score 0.09420289855072464 - nodes in this community are weakly interconnected._