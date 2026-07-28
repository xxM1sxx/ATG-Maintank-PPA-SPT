#pragma once

#include "types.hpp"
#include <string>
#include <chrono>

namespace atg {

struct AtgConfig {
    std::string device = "/dev/ttyUSB0";
    Bps baudrate = Bps::Rate9600;
    Parity parity = Parity::None;
    DataBits data_bits = DataBits::D8;
    StopBits stop_bits = StopBits::One;
    milliseconds read_timeout = milliseconds(3000);

    uint8_t slave_id = 1;
    uint16_t start_register = 0x0000;
    uint16_t register_count = 12;

    int de_gpio_pin = -1;
    int re_gpio_pin = -1;

    int polling_interval_ms = 5000;

    static AtgConfig from_args(int argc, char* argv[]);
    static AtgConfig from_file(const char* path);
    static AtgConfig defaults();
};

} // namespace atg
