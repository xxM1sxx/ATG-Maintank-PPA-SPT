#include "mqtt_publisher.hpp"
#include <atg/debug_log.hpp>
#include <mosquitto.h>
#include <cstdio>
#include <sstream>
#include <iomanip>
#include <ctime>
#include <chrono>

namespace {

void on_connect(struct mosquitto* mosq, void* userdata, int reason_code) {
    (void)mosq; (void)userdata;
    if (reason_code == 0) {
        atg::atg_debug_log("mqtt", "Connected to broker");
    } else {
        std::fprintf(stderr, "[MQTT] Connection failed: %s\n", mosquitto_connack_string(reason_code));
    }
}

void on_disconnect(struct mosquitto* mosq, void* userdata, int reason_code) {
    (void)mosq; (void)userdata;
    if (reason_code == 0) {
        atg::atg_debug_log("mqtt", "Disconnected (clean shutdown)");
    } else {
        std::fprintf(stderr, "[MQTT] Disconnected (rc=%d)\n", reason_code);
    }
}

void on_publish(struct mosquitto* mosq, void* userdata, int mid) {
    (void)mosq; (void)userdata; (void)mid;
    // Silent success - no logging needed for every publish
}

std::string get_iso_timestamp() {
    auto now = std::chrono::system_clock::now();
    auto time_t = std::chrono::system_clock::to_time_t(now);
    std::stringstream ss;
    ss << std::put_time(std::gmtime(&time_t), "%Y-%m-%dT%H:%M:%SZ");
    return ss.str();
}

} // namespace

MqttPublisher::MqttPublisher(const MqttConfig& config, const std::string& company, const std::string& plant_id)
    : config_(config), company_(company), plant_id_(plant_id), mosq_(nullptr), connected_(false) {
}

MqttPublisher::~MqttPublisher() {
    close();
}

bool MqttPublisher::init() {
    if (!config_.enabled) {
        atg::atg_debug_log("mqtt", "MQTT publishing disabled");
        return false;
    }

    if (config_.broker_host.empty()) {
        std::fprintf(stderr, "[MQTT] Broker host not configured\n");
        return false;
    }

    mosquitto_lib_init();

    mosq_ = mosquitto_new(nullptr, true, nullptr);
    if (mosq_ == nullptr) {
        std::fprintf(stderr, "[MQTT] Failed to create mosquitto instance\n");
        mosquitto_lib_cleanup();
        return false;
    }

    mosquitto_connect_callback_set(mosq_, on_connect);
    mosquitto_disconnect_callback_set(mosq_, on_disconnect);
    mosquitto_publish_callback_set(mosq_, on_publish);

    if (!config_.username.empty() && !config_.password.empty()) {
        int auth_result = mosquitto_username_pw_set(mosq_, config_.username.c_str(), config_.password.c_str());
        if (auth_result != MOSQ_ERR_SUCCESS) {
            std::fprintf(stderr, "[MQTT] Failed to set auth: %s\n", mosquitto_strerror(auth_result));
            mosquitto_destroy(mosq_);
            mosq_ = nullptr;
            mosquitto_lib_cleanup();
            return false;
        }
    }

    int connect_result = mosquitto_connect(mosq_, config_.broker_host.c_str(), config_.broker_port, 60);
    if (connect_result != MOSQ_ERR_SUCCESS) {
        std::fprintf(stderr, "[MQTT] Initial connect failed: %s (will retry in background)\n",
                     mosquitto_strerror(connect_result));
    }

    int loop_result = mosquitto_loop_start(mosq_);
    if (loop_result != MOSQ_ERR_SUCCESS) {
        std::fprintf(stderr, "[MQTT] loop_start failed: %s\n", mosquitto_strerror(loop_result));
        mosquitto_destroy(mosq_);
        mosq_ = nullptr;
        mosquitto_lib_cleanup();
        return false;
    }

    atg::atg_debug_log("mqtt", "Initialized: %s:%d topic=%s", config_.broker_host.c_str(),
                       config_.broker_port, config_.topic_path.c_str());
    last_publish_ = std::chrono::steady_clock::now();
    return true;
}

void MqttPublisher::update() {
    if (!config_.enabled || !mosq_) {
        return;
    }

    auto now = std::chrono::steady_clock::now();
    auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(now - last_publish_).count();

    if (elapsed >= config_.publish_interval_sec) {
        // Publish heartbeat or signal that system is alive
        // This can be extended for actual sensor updates
        last_publish_ = now;
    }
}

void MqttPublisher::publish_telemetry(const std::vector<SensorData>& sensors) {
    if (!config_.enabled || !mosq_) {
        return;
    }

    auto now = std::chrono::steady_clock::now();
    auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(now - last_publish_).count();

    if (elapsed < config_.publish_interval_sec) {
        return;  // Not yet time to publish
    }

    std::string json = build_json(sensors);

    {
        std::lock_guard<std::mutex> lock(publish_mutex_);
        int result = mosquitto_publish(mosq_, nullptr, config_.topic_path.c_str(),
                                       static_cast<int>(json.size()), json.c_str(), 1, false);
        if (result != MOSQ_ERR_SUCCESS) {
            std::fprintf(stderr, "[MQTT] Publish failed: %s\n", mosquitto_strerror(result));
        }
    }

    last_publish_ = now;
}

bool MqttPublisher::is_connected() const {
    return mosq_ != nullptr && connected_;
}

void MqttPublisher::close() {
    if (!mosq_) {
        return;
    }

    mosquitto_loop_stop(mosq_, true);
    mosquitto_disconnect(mosq_);
    mosquitto_destroy(mosq_);
    mosq_ = nullptr;
    connected_ = false;

    mosquitto_lib_cleanup();
    atg::atg_debug_log("mqtt", "Closed");
}

std::string MqttPublisher::build_json(const std::vector<SensorData>& sensors) {
    std::stringstream ss;

    ss << "{"
       << "\"company\":\"" << company_ << "\","
       << "\"plant_id\":\"" << plant_id_ << "\","
       << "\"data\":[";

    for (size_t i = 0; i < sensors.size(); ++i) {
        if (i > 0) ss << ",";

        const auto& sensor = sensors[i];
        ss << "{"
           << "\"slocn\":\"" << sensor.slocn << "\","
           << "\"number\":\"" << sensor.number << "\","
           << "\"capacity_l\":" << static_cast<long>(sensor.capacity_l) << ","
           << "\"fuel_level_mm\":" << static_cast<long>(sensor.fuel_level_mm) << ","
           << "\"volume_l\":" << static_cast<long>(sensor.volume_l) << ","
           << "\"temperature_c\":" << static_cast<int>(sensor.temperature_c) << ","
           << "\"timestamp\":\"" << get_iso_timestamp() << "\""
           << "}";
    }

    ss << "]}";

    return ss.str();
}
