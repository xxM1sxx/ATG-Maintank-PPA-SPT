#include <atg/modbus.hpp>
#include <atg/types.hpp>
#include <atg/config.hpp>
#include <cassert>
#include <cstdio>
#include <cstring>
#include <vector>

#if __has_include("volume_lookup.hpp")
#include "volume_lookup.hpp"
#define ATG_HAS_VOLUME_LOOKUP 1
#endif

void test_crc16() {
    uint8_t data[] = { 0x01, 0x03, 0x00, 0x00, 0x00, 0x0C };
    uint16_t crc = atg::modbus::crc16(data, 6);
    uint8_t lo = crc & 0xFF;
    uint8_t hi = (crc >> 8) & 0xFF;
    printf("CRC16 test: %02X %02X\n", lo, hi);
    assert(crc != 0 && "crc should not be zero");
}

void test_build_request() {
    uint8_t buf[8];
    size_t len = atg::modbus::build_request(buf, sizeof(buf), 1,
                                            atg::ModbusFunc::ReadHoldingRegisters,
                                            0x0000, 12);
    assert(len == 8);
    assert(buf[0] == 1);           // slave
    assert(buf[1] == 0x03);        // func
    assert(buf[2] == 0x00);        // start hi
    assert(buf[3] == 0x00);        // start lo
    assert(buf[4] == 0x00);        // count hi
    assert(buf[5] == 0x0C);        // count lo (12)
    printf("build_request OK (len=%zu)\n", len);
}

void test_find_frame() {
    uint8_t frame[] = { 0x01, 0x03, 0x04, 0x00, 0x01, 0x00, 0x02, 0x2A, 0x32 };
    int flen = atg::modbus::find_frame(frame, sizeof(frame));
    printf("find_frame FC03 returned %d (expected 9)\n", flen);
    assert(flen == 9);
}

void test_fc04_response_frame() {
    uint8_t frame[37];
    frame[0] = 0x01;
    frame[1] = 0x04;
    frame[2] = 0x20;
    std::memset(frame + 3, 0, 32);
    frame[3] = 0x70;
    frame[4] = 0x44;
    frame[5] = 0x00;
    frame[6] = 0xC0;
    uint16_t crc = atg::modbus::crc16(frame, 35);
    frame[35] = static_cast<uint8_t>(crc & 0xFF);
    frame[36] = static_cast<uint8_t>((crc >> 8) & 0xFF);
    int flen = atg::modbus::find_frame(frame, sizeof(frame));
    printf("find_frame FC04 returned %d (expected 37)\n", flen);
    assert(flen == 37);
}

void test_config_defaults() {
    auto cfg = atg::AtgConfig::defaults();
    assert(cfg.device == "/dev/ttyUSB0");
    assert(cfg.baudrate == atg::Bps::Rate9600);
    assert(cfg.slave_id == 1);
    printf("Config defaults OK\n");
}

void test_sp400_float_parse() {
    // Holykell SP400 datasheet examples (type 10007 byte order)
    const uint8_t fuel[] = {0x0E, 0x45, 0xB2, 0x49};
    const uint8_t water[] = {0xA5, 0x44, 0x95, 0x1C};
    const uint8_t temp[] = {0x91, 0x41, 0x00, 0x80};

    double f = atg::parse_ieee_float_10007(fuel);
    double w = atg::parse_ieee_float_10007(water);
    double t = atg::parse_ieee_float_10007(temp);

    printf("SP400 parse: fuel=%.1f water=%.1f temp=%.1f\n", f, w, t);
    assert(f > 2276.0 && f < 2277.0);
    assert(w > 1320.0 && w < 1321.0);
    assert(t > 18.0 && t < 19.0);
    printf("SP400 float parse OK\n");
}

void test_sp400_modbus_request() {
    uint8_t buf[8];
    size_t len = atg::modbus::build_request(buf, sizeof(buf), 2,
                                            atg::ModbusFunc::ReadInputRegisters,
                                            0x0000, 0x0010);
    assert(len == 8);
    assert(buf[0] == 2);
    assert(buf[1] == 0x04);
    assert(buf[2] == 0x00 && buf[3] == 0x00);
    assert(buf[4] == 0x00 && buf[5] == 0x10);
    printf("SP400 modbus request OK\n");
}

void test_sp400_modbus_slave_regs() {
    // Modbus Slave sim (Holykell encoding): fuel 963mm, water 120mm
    // Reg pairs: (28740,192) and (61506,0) -> wire 70 44 00 C0 / F0 42 00 00
    const uint8_t payload[] = {
        0x70, 0x44, 0x00, 0xC0,
        0xF0, 0x42, 0x00, 0x00,
        0x00, 0x00, 0x00, 0x00,
    };
    double fuel = atg::parse_ieee_float_10007(payload + 0);
    double water = atg::parse_ieee_float_10007(payload + 4);
    printf("SP400 sim regs: fuel=%.1f water=%.1f\n", fuel, water);
    assert(fuel > 962.0 && fuel < 964.0);
    assert(water > 119.0 && water < 121.0);
    printf("SP400 modbus slave reg parse OK\n");
}

#if defined(ATG_HAS_VOLUME_LOOKUP)

void test_sp400_fuel_volume_only() {
    // Same wire layout as sp400_modbus_sim.py / parse_sp400_payload (Holykell type 10007)
    uint8_t payload[32] = {};
    // fuel 963 mm
    payload[0] = 0x70;
    payload[1] = 0x44;
    payload[2] = 0x00;
    payload[3] = 0xC0;
    // water 120 mm
    payload[4] = 0xF0;
    payload[5] = 0x42;
    payload[6] = 0x00;
    payload[7] = 0x00;
    // temp 25 C
    payload[8] = 0xC8;
    payload[9] = 0x41;
    payload[10] = 0x00;
    payload[11] = 0x00;

    const double fuel = atg::parse_ieee_float_10007(payload + 0);
    const double water = atg::parse_ieee_float_10007(payload + 4);
    const double temp = atg::parse_ieee_float_10007(payload + 8);
    assert(fuel > 962.0 && fuel < 964.0);
    assert(water > 119.0 && water < 121.0);
    assert(temp > 24.0 && temp < 26.0);

    const std::vector<atg_app::VolumePoint> pts{{900, 10000.0}, {1000, 15000.0}};
    const double vol = atg_app::lookup_volume_liters(fuel, 0.0, pts);
    const double expected = 10000.0 + (fuel - 900.0) / 100.0 * 5000.0;
    assert(vol > expected - 1.0 && vol < expected + 1.0);

    // Water change must not affect volume (lookup uses fuel sounding only)
    payload[4] = 0x79;
    payload[5] = 0x44;
    payload[6] = 0x00;
    payload[7] = 0xC0;  // water 999 mm
    const double fuel2 = atg::parse_ieee_float_10007(payload + 0);
    const double water2 = atg::parse_ieee_float_10007(payload + 4);
    assert(fuel2 > 962.0 && fuel2 < 964.0);
    assert(water2 > 998.0 && water2 < 1000.0);
    const double vol2 = atg_app::lookup_volume_liters(fuel2, 0.0, pts);
    assert(vol2 > expected - 1.0 && vol2 < expected + 1.0);

    printf("SP400 fuel-only volume lookup OK (fuel=%.1f vol=%.1f L, water ignored)\n",
           fuel, vol);
}

#endif

int main() {
    test_crc16();
    test_build_request();
    test_find_frame();
    test_fc04_response_frame();
    test_config_defaults();
    test_sp400_float_parse();
    test_sp400_modbus_request();
    test_sp400_modbus_slave_regs();
#if defined(ATG_HAS_VOLUME_LOOKUP)
    test_sp400_fuel_volume_only();
#endif
    printf("\nAll tests passed.\n");
    return 0;
}
