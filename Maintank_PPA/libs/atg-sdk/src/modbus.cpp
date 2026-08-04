#include "atg/modbus.hpp"
#include "atg/serial_port.hpp"
#include "atg/debug_log.hpp"
#include <cstring>
#include <cstdio>
#ifndef _WIN32
#include <poll.h>
#endif

namespace atg::modbus {

static constexpr uint16_t CRC_POLY = 0xA001;

uint16_t crc16(const uint8_t* data, size_t len) {
    uint16_t crc = 0xFFFF;
    for (size_t i = 0; i < len; ++i) {
        crc ^= data[i];
        for (int j = 0; j < 8; ++j) {
            if (crc & 0x0001)
                crc = (crc >> 1) ^ CRC_POLY;
            else
                crc >>= 1;
        }
    }
    return crc;
}

size_t build_request(uint8_t* dst, size_t dst_cap,
                     uint8_t slave_id, ModbusFunc func,
                     uint16_t start_reg, uint16_t count) {
    if (dst_cap < 8) return 0;

    dst[0] = slave_id;
    dst[1] = static_cast<uint8_t>(func);
    dst[2] = static_cast<uint8_t>((start_reg >> 8) & 0xFF);
    dst[3] = static_cast<uint8_t>(start_reg & 0xFF);
    dst[4] = static_cast<uint8_t>((count >> 8) & 0xFF);
    dst[5] = static_cast<uint8_t>(count & 0xFF);

    uint16_t crc = crc16(dst, 6);
    dst[6] = static_cast<uint8_t>(crc & 0xFF);
    dst[7] = static_cast<uint8_t>((crc >> 8) & 0xFF);

    return 8;
}

int frame_length(uint8_t func_code) {
    switch (func_code) {
        case 0x01: case 0x02: return 6;  // bit coils - 1 byte data + addr + func + crc
        case 0x03: case 0x04: return 5;  // regs - data byte count + addr + func + crc
        case 0x05: case 0x06: return 8;  // single write
        case 0x0F: case 0x10: return 8;  // multi write
        default: return 5;
    }
}

static int frame_len_at(const uint8_t* buf, size_t n) {
    if (n < MIN_FRAME_LEN) return -1;

    size_t expected = 3;
    uint8_t func = buf[1];

    if (func & 0x80) {
        expected = 5;
    } else {
        switch (func) {
            case 0x01: case 0x02:
            case 0x03: case 0x04:
                // addr + func + byte_count + data[byte_count] + crc(2)
                expected = 3 + buf[2] + 2;
                break;
            case 0x05: case 0x06:
                expected = 8;
                break;
            case 0x0F: case 0x10:
                expected = 8;
                break;
            default:
                expected = frame_length(func);
                break;
        }
    }

    if (n < expected) return -1;

    uint16_t crc = crc16(buf, expected - 2);
    uint16_t rx_crc = static_cast<uint16_t>(buf[expected - 2]) |
                      (static_cast<uint16_t>(buf[expected - 1]) << 8);

    if (crc != rx_crc) return -1;

    return static_cast<int>(expected);
}

int find_frame(const uint8_t* buf, size_t n, size_t* frame_off) {
    for (size_t i = 0; i + MIN_FRAME_LEN <= n; ++i) {
        int flen = frame_len_at(buf + i, n - i);
        if (flen > 0) {
            if (frame_off) *frame_off = i;
            return flen;
        }
    }
    return -1;
}

#ifndef _WIN32

bool send_request(SerialPort& port, const uint8_t* req, size_t len) {
    port.flush_input();
    return port.write(req, len) == static_cast<ssize_t>(len);
}

bool read_response(SerialPort& port, uint8_t* buf, size_t bufsize,
                   milliseconds timeout) {
    int ms = timeout.count();
    uint8_t* raw = buf;
    size_t pos = 0;

    while (pos < bufsize) {
        struct pollfd pfd;
        pfd.fd = port.native_handle();
        pfd.events = POLLIN;

        int ret = poll(&pfd, 1, ms);
        if (ret <= 0) break;

        ssize_t r = port.read(raw + pos, bufsize - pos);
        if (r <= 0) break;
        pos += r;

        size_t off = 0;
        int flen = find_frame(raw, pos, &off);
        if (flen > 0) {
            if (off > 0)
                memmove(raw, raw + off, static_cast<size_t>(flen));
            return true;
        }

        if (pos >= bufsize) break;
    }

    if (pos > 0)
        atg_debug_hex("read_response failed", raw, pos);
    else
        atg_debug_log("atg-debug", "read_response failed, got 0 bytes");
    return false;
}

#else

bool send_request(SerialPort& port, const uint8_t* req, size_t len) {
    (void)port;
    (void)req;
    (void)len;
    return false;
}

bool read_response(SerialPort& port, uint8_t* buf, size_t bufsize,
                   milliseconds timeout) {
    (void)port;
    (void)buf;
    (void)bufsize;
    (void)timeout;
    return false;
}

#endif

} // namespace atg::modbus
