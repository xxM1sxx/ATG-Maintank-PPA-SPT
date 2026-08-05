#ifndef MQTT_PUBLISHER_HPP
#define MQTT_PUBLISHER_HPP

#include <string>
#include <vector>
#include <memory>
#include <mutex>
#include <chrono>

struct mosquitto;  // Forward declare

struct MqttConfig {
    std::string broker_host;
    int broker_port;
    std::string username;
    std::string password;
    std::string topic_path;
    int publish_interval_sec;
    bool enabled;
};

struct SensorData {
    std::string slocn;
    std::string number;
    double capacity_l;
    double fuel_level_mm;
    double volume_l;
    double temperature_c;
};

class MqttPublisher {
public:
    MqttPublisher(const MqttConfig& config, const std::string& company, const std::string& plant_id);
    ~MqttPublisher();

    bool init();
    void update();
    void publish_telemetry(const std::vector<SensorData>& sensors);
    bool is_connected() const;
    void close();

private:
    MqttConfig config_;
    std::string company_;
    std::string plant_id_;
    mosquitto* mosq_;
    bool connected_;
    std::mutex publish_mutex_;
    std::chrono::steady_clock::time_point last_publish_;

    std::string build_json(const std::vector<SensorData>& sensors);
};

#endif // MQTT_PUBLISHER_HPP
