#include "atg/atg_protocol.hpp"
#include "atg/serial_port.hpp"
#include "atg/gpio.hpp"
#include "atg/modbus.hpp"
#include "atg/debug_log.hpp"
#include <cstring>
#include <cmath>
#include <cstdio>
#include <cstdlib>
#include <thread>

namespace atg {

namespace {

enum class FloatFormat { Holykell, ModbusLe };

FloatFormat float_format() {
    const char* fmt = std::getenv("ATG_FLOAT_FORMAT");
    if (fmt && fmt[0] != '\0' && std::strcmp(fmt, "modbus_le") == 0)
        return FloatFormat::ModbusLe;
    return FloatFormat::Holykell;
}

bool rts_de_enabled() {
    const char* v = std::getenv("ATG_RTS_DE");
    return v && v[0] != '\0' && v[0] != '0';
}

int rs485_delay_ms() {
    const char* v = std::getenv("ATG_RS485_DELAY_MS");
    if (!v || v[0] == '\0') return 10;
    return std::atoi(v);
}

void rs485_turnaround_delay() {
    const int ms = rs485_delay_ms();
    if (ms > 0)
        std::this_thread::sleep_for(std::chrono::milliseconds(ms));
}

const char* modbus_exception_text(uint8_t code) {
    switch (code) {
        case 0x01: return "illegal function";
        case 0x02: return "illegal data address (check Input Registers 0-15, FC04)";
        case 0x03: return "illegal data value";
        case 0x04: return "slave device failure";
        default:   return "unknown";
    }
}

size_t modbus_response_len(const uint8_t* resp) {
    if (resp[1] & 0x80)
        return 5;
    return 3 + static_cast<size_t>(resp[2]) + 2;
}

double parse_float4(const uint8_t* bytes) {
    if (!bytes) return 0.0;
    float v = 0.0f;
    if (float_format() == FloatFormat::ModbusLe) {
        std::memcpy(&v, bytes, sizeof(v));
    } else {
        uint8_t le[4] = {bytes[2], bytes[3], bytes[0], bytes[1]};
        std::memcpy(&v, le, sizeof(v));
    }
    return static_cast<double>(v);
}

} // namespace

double parse_ieee_float_10007(const uint8_t* bytes) {
    return parse_float4(bytes);
}

AtgProtocol::AtgProtocol(SerialPort& port, uint8_t slave_id)
    : port_(port), slave_id_(slave_id) {}

void AtgProtocol::set_response_timeout(milliseconds ms) {
    timeout_ = ms;
}

void AtgProtocol::set_de_pin(GpioPin* pin) {
    de_pin_ = pin;
}

void AtgProtocol::set_volume_converter(VolumeConverter fn) {
    converter_ = std::move(fn);
}

bool AtgProtocol::read_input_registers(uint16_t start, uint16_t count,
                                       uint8_t* dst, size_t dst_cap, size_t* out_bytes) {
    if (!dst || dst_cap == 0) return false;

    uint8_t req[8];
    size_t req_len = modbus::build_request(req, sizeof(req), slave_id_,
                                           ModbusFunc::ReadInputRegisters,
                                           start, count);
    if (req_len == 0) return false;

    if (atg_debug_enabled())
        atg_debug_hex("modbus TX", req, req_len);

    if (de_pin_) de_pin_->set(true);
    if (rts_de_enabled()) port_.set_rts(true);

    if (!modbus::send_request(port_, req, req_len)) {
        if (rts_de_enabled()) port_.set_rts(false);
        if (de_pin_) de_pin_->set(false);
        return false;
    }

    if (de_pin_) de_pin_->set(false);
    if (rts_de_enabled()) port_.set_rts(false);

    rs485_turnaround_delay();

    uint8_t resp[256];
    if (!modbus::read_response(port_, resp, sizeof(resp), timeout_))
        return false;

    const size_t rx_len = modbus_response_len(resp);
    if (atg_debug_enabled())
        atg_debug_hex("modbus RX", resp, rx_len);

    if (resp[1] & 0x80) {
        if (atg_debug_enabled()) {
            std::fprintf(stderr,
                "atg-debug: modbus exception fc=%02X code=%02X (%s)\n",
                resp[1], resp[2], modbus_exception_text(resp[2]));
        }
        return false;
    }

    const size_t byte_count = resp[2];
    if (byte_count == 0 || 3 + byte_count > sizeof(resp))
        return false;

    if (atg_debug_enabled() && byte_count < 32)
        std::fprintf(stderr, "atg-debug: warning: expected 32 data bytes, got %zu\n", byte_count);

    const size_t copy = (byte_count < dst_cap) ? byte_count : dst_cap;
    std::memcpy(dst, resp + 3, copy);
    if (out_bytes) *out_bytes = copy;
    return copy > 0;
}

TankReading AtgProtocol::parse_sp400_payload(const uint8_t* data, size_t len) const {
    TankReading r;
    r.tank_number = 1;

    if (len >= 4)  r.fuel_level_mm = parse_float4(data + 0);
    if (len >= 8)  r.water_level_mm = parse_float4(data + 4);
    if (len >= 12) r.fuel_temp_c = parse_float4(data + 8);
    if (len >= 16) r.temp_a_c = parse_float4(data + 12);
    if (len >= 20) r.temp_b_c = parse_float4(data + 16);
    if (len >= 24) r.temp_c_c = parse_float4(data + 20);
    if (len >= 28) r.temp_d_c = parse_float4(data + 24);
    if (len >= 32) r.temp_e_c = parse_float4(data + 28);

    if (atg_debug_enabled()) {
        std::fprintf(stderr,
            "atg-debug: parsed fuel=%.1f water=%.1f temp=%.1f (format=%s, len=%zu)\n",
            r.fuel_level_mm, r.water_level_mm, r.fuel_temp_c,
            float_format() == FloatFormat::ModbusLe ? "modbus_le" : "holykell",
            len);
    }

    return r;
}

TankReading AtgProtocol::read_volume(int /*tank_number*/) {
    auto all = read_all();
    if (all.empty()) return TankReading{};
    return all.front();
}

TankReading AtgProtocol::read_water(int /*tank_number*/) {
    return read_volume(1);
}

TankReading AtgProtocol::read_tera_volume(const std::string& tera_table, double position_mm) {
    TankReading r;
    r.fuel_level_mm = position_mm;
    if (converter_) {
        r.volume = converter_(tera_table, position_mm);
    }
    return r;
}

std::vector<TankReading> AtgProtocol::read_all() {
    std::vector<TankReading> results;
    uint8_t data[32];
    size_t nbytes = 0;

    if (!read_input_registers(kSp400InputRegStart, kSp400InputRegCount,
                              data, sizeof(data), &nbytes))
        return results;

    if (atg_debug_enabled())
        atg_debug_hex("sp400 data", data, nbytes);

    TankReading r = parse_sp400_payload(data, nbytes);
    results.push_back(r);
    return results;
}

} // namespace atg
