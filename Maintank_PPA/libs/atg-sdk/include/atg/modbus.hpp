#pragma once

#include "types.hpp"
#include <cstdint>
#include <cstddef>
#include <chrono>

namespace atg {
class SerialPort;
}

namespace atg::modbus {

static constexpr int MIN_FRAME_LEN = 5;
static constexpr int MAX_FRAME_LEN = 256;

uint16_t crc16(const uint8_t* data, size_t len);
size_t   build_request(uint8_t* dst, size_t dst_cap,
                       uint8_t slave_id, ModbusFunc func,
                       uint16_t start_reg, uint16_t count);
int      find_frame(const uint8_t* buf, size_t n, size_t* frame_off = nullptr);
int      frame_length(uint8_t func_code);

bool     read_response(SerialPort& port, uint8_t* buf, size_t bufsize,
                       milliseconds timeout = milliseconds(3000));
bool     send_request(SerialPort& port, const uint8_t* req, size_t len);

} // namespace atg::modbus
