#include "atg/sensor_manager.hpp"
#include "atg/atg_protocol.hpp"
#include "atg/gpio.hpp"
#include "atg/serial_port.hpp"
#include "atg/debug_log.hpp"
#include <chrono>
#include <cstdio>
#include <memory>
#include <stdexcept>
#include <thread>

namespace atg {

Bps bps_from_int(int baudrate) {
    switch (baudrate) {
        case 9600:   return Bps::Rate9600;
        case 19200:  return Bps::Rate19200;
        case 38400:  return Bps::Rate38400;
        case 57600:  return Bps::Rate57600;
        case 115200: return Bps::Rate115200;
        default:     return Bps::Rate9600;
    }
}

SensorManager::SensorManager(std::vector<SensorConfig> configs)
    : configs_(std::move(configs)) {}

SensorManager::~SensorManager() {
    stop();
}

void SensorManager::set_callback(Callback cb) {
    callback_ = std::move(cb);
}

void SensorManager::start() {
    if (running_.exchange(true)) return;

    for (const auto& cfg : configs_) {
        if (!cfg.enabled || cfg.device.empty()) continue;
        threads_.emplace_back(&SensorManager::worker, this, cfg);
    }
}

void SensorManager::stop() {
    if (!running_.exchange(false)) return;
    for (auto& t : threads_) {
        if (t.joinable()) t.join();
    }
    threads_.clear();
}

SensorResult SensorManager::latest(int id) const {
    std::lock_guard<std::mutex> lock(latest_mutex_);
    auto it = latest_.find(id);
    if (it == latest_.end()) return SensorResult{};
    return it->second;
}

void SensorManager::worker(SensorConfig cfg) {
    while (running_) {
        try {
            SerialPort port(cfg.device);
            port.configure(cfg.baudrate, Parity::None, DataBits::D8, StopBits::One, cfg.timeout);
            port.open();

            std::unique_ptr<GpioPin> de_pin;
            if (cfg.de_gpio_pin >= 0)
                de_pin = std::make_unique<GpioPin>(cfg.de_gpio_pin);

            AtgProtocol atg(port, cfg.slave_id);
            atg.set_response_timeout(cfg.timeout);
            if (de_pin) atg.set_de_pin(de_pin.get());

            while (running_) {
                SensorResult result;
                result.id = cfg.id;

                try {
                    auto tanks = atg.read_all();
                    if (tanks.empty()) {
                        result.ok = false;
                        result.error = "Modbus read failed";
                    } else {
                        result.ok = true;
                        result.readings = std::move(tanks);
                    }
                } catch (const std::exception& ex) {
                    result.ok = false;
                    result.error = ex.what();
                }

                {
                    std::lock_guard<std::mutex> lock(latest_mutex_);
                    latest_[cfg.id] = result;
                }

                if (callback_) callback_(cfg.id, result);

                if (atg_debug_enabled()) {
                    if (result.ok && !result.readings.empty()) {
                        const auto& t = result.readings.front();
                        std::fprintf(stderr,
                            "atg-reader: sensor=%d device=%s slave=%u ok=1 fuel=%.1f water=%.1f temp=%.1f\n",
                            cfg.id, cfg.device.c_str(), static_cast<unsigned>(cfg.slave_id),
                            t.fuel_level_mm, t.water_level_mm, t.fuel_temp_c);
                    } else {
                        std::fprintf(stderr,
                            "atg-reader: sensor=%d device=%s slave=%u ok=0 error=%s\n",
                            cfg.id, cfg.device.c_str(), static_cast<unsigned>(cfg.slave_id),
                            result.error.c_str());
                    }
                }

                const auto step = std::chrono::milliseconds(100);
                auto remaining = cfg.polling_interval;
                while (running_ && remaining.count() > 0) {
                    auto sleep_for = remaining > step ? step : remaining;
                    std::this_thread::sleep_for(sleep_for);
                    remaining -= sleep_for;
                }
            }
            return;
        } catch (const std::exception& ex) {
            SensorResult result;
            result.id = cfg.id;
            result.ok = false;
            result.error = ex.what();

            {
                std::lock_guard<std::mutex> lock(latest_mutex_);
                latest_[cfg.id] = result;
            }
            if (callback_) callback_(cfg.id, result);

            if (atg_debug_enabled()) {
                std::fprintf(stderr,
                    "atg-reader: sensor=%d device=%s open failed: %s (retry 5s)\n",
                    cfg.id, cfg.device.c_str(), ex.what());
            }

            for (int i = 0; i < 50 && running_; ++i)
                std::this_thread::sleep_for(std::chrono::milliseconds(100));
        }
    }
}

} // namespace atg
