#include "atg_db.hpp"
#include "mqtt_publisher.hpp"
#include "sys_stats.hpp"
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
    std::cerr << "==================== atg-reader starting ====================\n"
              << "atg-reader: db=" << db_path
              << " sensors=" << configs.size()
              << " debug=" << (debug ? "on" : "silent (ATG_DEBUG=0)") << "\n";
    for (const auto& cfg : configs) {
        std::cerr << "atg-reader: sensor[" << cfg.id << "] device=" << cfg.device
                  << " slave=" << static_cast<unsigned>(cfg.slave_id)
                  << " de_pin=" << cfg.de_gpio_pin
                  << " timeout=" << cfg.timeout.count() << "ms"
                  << " interval=" << cfg.polling_interval.count() << "ms\n";
    }
    std::cerr << "===============================================================\n";
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

        MqttConfig mqtt_config = db.load_mqtt_config();
        CloudMqttConfig cloud_config = db.load_cloud_mqtt_config();
        MqttPublisher publisher(mqtt_config, cloud_config, "PT. Putra Perkasa Abadi", "PPA-BA", &db);
        if (mqtt_config.enabled || cloud_config.enabled) {
            publisher.init();
        }

        atg::SensorManager mgr(std::move(configs));
        mgr.set_callback([&db](int id, const atg::SensorResult& r) {
            db.upsert_reading(id, r);
        });
        mgr.start();

        auto last_publish = std::chrono::steady_clock::now();
        auto last_mem_check = std::chrono::steady_clock::now();

        while (g_running) {
            std::this_thread::sleep_for(std::chrono::seconds(1));

            if (mqtt_config.enabled) {
                auto now = std::chrono::steady_clock::now();
                auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(now - last_publish).count();
                if (elapsed >= mqtt_config.publish_interval_sec) {
                    auto sensors = db.load_sensor_data_for_mqtt();
                    publisher.publish_telemetry(sensors);
                    last_publish = now;
                }
            }

            if (cloud_config.enabled) {
                auto sensors = db.load_sensor_data_for_mqtt();
                publisher.publish_cloud_telemetry(sensors);  // own internal interval gate
                publisher.check_ack_timeouts();
                publisher.poll_replay_request();

                auto now = std::chrono::steady_clock::now();
                auto mem_elapsed = std::chrono::duration_cast<std::chrono::seconds>(now - last_mem_check).count();
                if (mem_elapsed >= 60) {
                    last_mem_check = now;
                    double used_pct = atg_app::read_used_memory_percent();
                    if (used_pct >= 80.0) {
                        atg::atg_log(atg::LogLevel::Warn, "MQTT",
                            "Memory usage %.1f%% >= 80%%, trimming cloud pending queue to 500 rows", used_pct);
                        db.trim_pending_cloud_rows(500);
                    }
                }
            }
        }

        mgr.stop();
        publisher.close();
    } catch (const std::exception& ex) {
        std::cerr << "atg-reader: " << ex.what() << "\n";
        return 1;
    }

    return 0;
}
