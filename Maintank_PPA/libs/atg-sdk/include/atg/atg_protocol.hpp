#pragma once

#include "types.hpp"
#include "gpio.hpp"
#include <vector>
#include <functional>
#include <cstddef>
#include <cstdint>

namespace atg {
class SerialPort;
}

namespace atg {

class AtgProtocol {
public:
    static constexpr uint16_t kSp400InputRegStart = 0x0000;
    static constexpr uint16_t kSp400InputRegCount = 0x0010;  // 16 regs = 8 floats

    explicit AtgProtocol(SerialPort& port, uint8_t slave_id = 1);

    void set_response_timeout(milliseconds ms);
    void set_de_pin(GpioPin* pin);

    TankReading read_volume(int tank_number);
    TankReading read_water(int tank_number);
    TankReading read_tera_volume(const std::string& tera_table, double position_mm);
    std::vector<TankReading> read_all();

    using VolumeConverter = std::function<double(const std::string& table, double h)>;
    void set_volume_converter(VolumeConverter fn);

private:
    bool read_input_registers(uint16_t start, uint16_t count,
                              uint8_t* dst, size_t dst_cap, size_t* out_bytes);

    TankReading parse_sp400_payload(const uint8_t* data, size_t len) const;

    SerialPort& port_;
    uint8_t slave_id_;
    milliseconds timeout_ = milliseconds(3000);
    GpioPin* de_pin_ = nullptr;
    VolumeConverter converter_;
};

} // namespace atg
