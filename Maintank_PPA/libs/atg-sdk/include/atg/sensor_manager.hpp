#pragma once

#include "types.hpp"
#include <atomic>
#include <functional>
#include <map>
#include <mutex>
#include <string>
#include <thread>
#include <vector>

namespace atg {

struct SensorConfig {
    int id = 0;
    bool enabled = false;
    std::string device;
    uint8_t slave_id = 1;
    int de_gpio_pin = -1;
    Bps baudrate = Bps::Rate9600;
    milliseconds timeout = milliseconds(3000);
    milliseconds polling_interval = milliseconds(5000);
};

struct SensorResult {
    int id = 0;
    std::vector<TankReading> readings;
    bool ok = false;
    std::string error;
};

class SensorManager {
public:
    using Callback = std::function<void(int id, const SensorResult&)>;

    explicit SensorManager(std::vector<SensorConfig> configs);
    ~SensorManager();

    SensorManager(const SensorManager&) = delete;
    SensorManager& operator=(const SensorManager&) = delete;

    void set_callback(Callback cb);
    void start();
    void stop();
    SensorResult latest(int id) const;

private:
    void worker(SensorConfig cfg);

    std::vector<SensorConfig> configs_;
    Callback callback_;
    std::atomic<bool> running_{false};
    std::vector<std::thread> threads_;
    mutable std::mutex latest_mutex_;
    std::map<int, SensorResult> latest_;
};

Bps bps_from_int(int baudrate);

} // namespace atg
