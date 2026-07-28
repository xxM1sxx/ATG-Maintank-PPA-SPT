#include "atg_db.hpp"
#include <atg/debug_log.hpp>
#include <atg/sensor_manager.hpp>
#include <atomic>
#include <chrono>
#include <csignal>
#include <iostream>
#include <thread>

namespace {

constexpr const char* kDefaultDb = "/home/user/Apps/qims_logger.db";
std::atomic<bool> g_running{true};

void on_signal(int) {
    g_running = false;
}

void print_startup_banner(const char* db_path,
                          const std::vector<atg::SensorConfig>& configs) {
    const bool debug = atg::atg_debug_enabled();
    std::cerr << "atg-reader: db=" << db_path
              << " sensors=" << configs.size()
              << " debug=" << (debug ? "on" : "off") << "\n";
    for (const auto& cfg : configs) {
        std::cerr << "atg-reader: sensor[" << cfg.id << "] device=" << cfg.device
                  << " slave=" << static_cast<unsigned>(cfg.slave_id)
                  << " interval=" << cfg.polling_interval.count() << "ms\n";
    }
    if (!debug) {
        std::cerr << "atg-reader: hint: set ATG_DEBUG=1 for Modbus hex logs\n";
    }
}

} // namespace

int main(int argc, char* argv[]) {
    const char* db_path = (argc > 1) ? argv[1] : kDefaultDb;

    std::signal(SIGINT, on_signal);
    std::signal(SIGTERM, on_signal);

    try {
        atg_app::AtgDatabase db(db_path);

        auto configs = db.load_enabled_sensors();
        if (configs.empty()) {
            std::cerr << "atg-reader: no enabled sensors in config (db=" << db_path << ")\n";
            return 1;
        }

        print_startup_banner(db_path, configs);

        atg::SensorManager mgr(std::move(configs));
        mgr.set_callback([&db](int id, const atg::SensorResult& r) {
            db.upsert_reading(id, r);
        });
        mgr.start();

        while (g_running) {
            std::this_thread::sleep_for(std::chrono::seconds(1));
        }

        mgr.stop();
    } catch (const std::exception& ex) {
        std::cerr << "atg-reader: " << ex.what() << "\n";
        return 1;
    }

    return 0;
}
