#pragma once

#include <cstdint>
#include <chrono>
#include <string>

namespace atg {

enum class Bps : int {
    Rate0 = 0,
    Rate50 = 50,
    Rate75 = 75,
    Rate110 = 110,
    Rate134 = 134,
    Rate150 = 150,
    Rate200 = 200,
    Rate300 = 300,
    Rate600 = 600,
    Rate1200 = 1200,
    Rate1800 = 1800,
    Rate2400 = 2400,
    Rate4800 = 4800,
    Rate9600 = 9600,
    Rate19200 = 19200,
    Rate38400 = 38400,
    Rate57600 = 57600,
    Rate115200 = 115200,
    Rate230400 = 230400,
};

enum class Parity { None, Even, Odd };

enum class DataBits { D5, D6, D7, D8 };

enum class StopBits { One, Two };

enum class ModbusFunc : uint8_t {
    ReadHoldingRegisters   = 0x03,
    ReadInputRegisters     = 0x04,
    WriteSingleRegister    = 0x06,
    WriteMultipleRegisters = 0x10,
};

using milliseconds = std::chrono::milliseconds;

// Holykell SP400 probe reading (1 probe = fuel + water + temperatures)
struct TankReading {
    int     tank_number = 1;
    double  fuel_level_mm{};
    double  water_level_mm{};
    double  fuel_temp_c{};
    double  temp_a_c{};
    double  temp_b_c{};
    double  temp_c_c{};
    double  temp_d_c{};
    double  temp_e_c{};
    double  volume{};
    std::string timestamp;

    // Backward compatibility: fuel level in mm
    double position_mm() const { return fuel_level_mm; }
};

// Modbus type 10007: swap 16-bit words (AB CD -> CD AB), then IEEE754 LE
double parse_ieee_float_10007(const uint8_t* bytes);

} // namespace atg
