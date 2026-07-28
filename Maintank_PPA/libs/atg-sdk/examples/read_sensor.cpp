#include <atg/atg.hpp>
#include <iostream>
#include <memory>
#include <cstdlib>
#include <unistd.h>

int main(int argc, char* argv[]) {
    auto cfg = atg::AtgConfig::from_args(argc, argv);

    std::cout << "ATG Sensor Reader (Holykell SP400)\n"
              << "  Device:  " << cfg.device << "\n"
              << "  Slave:   " << static_cast<int>(cfg.slave_id) << "\n"
              << "  GPIO DE: " << (cfg.de_gpio_pin >= 0 ? std::to_string(cfg.de_gpio_pin) : "none") << "\n\n";

    atg::SerialPort port(cfg.device);
    port.configure(cfg.baudrate, cfg.parity, cfg.data_bits, cfg.stop_bits, cfg.read_timeout);
    port.open();

    std::unique_ptr<atg::GpioPin> de_pin;
    if (cfg.de_gpio_pin >= 0)
        de_pin = std::make_unique<atg::GpioPin>(cfg.de_gpio_pin);

    atg::AtgProtocol atg(port, cfg.slave_id);
    atg.set_response_timeout(cfg.read_timeout);
    if (de_pin) atg.set_de_pin(de_pin.get());

    while (true) {
        auto readings = atg.read_all();
        if (readings.empty()) {
            std::cerr << "No readings from ATG\n";
        } else {
            const auto& r = readings.front();
            std::cout << "Fuel=" << r.fuel_level_mm << " mm"
                      << "  Water=" << r.water_level_mm << " mm"
                      << "  Temp=" << r.fuel_temp_c << " C\n";
        }

        sleep(cfg.polling_interval_ms / 1000);
    }

    return 0;
}
