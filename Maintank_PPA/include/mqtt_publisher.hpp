#ifndef MQTT_PUBLISHER_HPP
#define MQTT_PUBLISHER_HPP

#include <string>
#include <vector>
#include <memory>
#include <mutex>
#include <chrono>
#include <map>
#include <atomic>
#include <cstdint>

struct mosquitto;  // Forward declare

// atg_db.hpp includes this header, so only forward-declare here to avoid a cycle.
namespace atg_app { class AtgDatabase; }

struct MqttConfig {
    std::string broker_host;
    int broker_port;
    std::string username;
    std::string password;
    std::string topic_path;
    int publish_interval_sec;
    bool enabled;
};

// Second, reliability-focused publish path aimed at a cloud endpoint. Reuses the
// same broker connection/credentials as MqttConfig above - only topic(s) and
// interval differ.
//
// Delivery confirmation here is NOT MQTT's own QoS1 PUBACK (that only proves the
// broker received the message, not that it reached the cloud). Instead, a separate
// downstream controller subscribes to these topics, is responsible for actually
// pushing to the cloud, and publishes an application-level ack back:
// {"message_id":"...","status":"success"|"failed"}.
//
// - Periodic telemetry is published to data_topic, and its ack is expected back on
//   that same data_topic (we subscribe to it for that reason).
// - Replay batches (triggered by request_topic) are published to request_topic
//   instead, and their ack is also expected back on request_topic.
struct CloudMqttConfig {
    std::string data_topic;      // periodic cloud telemetry: published AND subscribed (for its ack)
    int publish_interval_sec;    // default 120
    std::string request_topic;   // subscribed for {"command":"get_pending"}; replay batches are
                                  // also published here and acked here
    bool enabled;
    bool store_on_fail;          // if false, a failed/unacked publish is just dropped, not queued
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
    MqttPublisher(const MqttConfig& config, const CloudMqttConfig& cloud_config,
                  const std::string& company, const std::string& plant_id,
                  atg_app::AtgDatabase* db);
    ~MqttPublisher();

    bool init();
    void update();
    void publish_telemetry(const std::vector<SensorData>& sensors);

    // Cloud path: gated by cloud_config_.publish_interval_sec, tags the payload with
    // a message_id, and waits for the downstream controller's application-level ack
    // (see CloudMqttConfig comment). On timeout/explicit failure, parks the payload
    // in the pending-queue table.
    void publish_cloud_telemetry(const std::vector<SensorData>& sensors);
    // Call once per main-loop tick: sweeps pending acks that got no reply at all
    // within the timeout window.
    void check_ack_timeouts();
    // Call once per main-loop tick: if the request topic received a valid
    // {"command":"get_pending"} message since the last call, drains the
    // pending-queue table in batches of 50.
    void poll_replay_request();

    bool is_connected() const;
    void close();

    // Called from the mosquitto network thread via free-function callbacks.
    void on_connect_impl(int reason_code);
    void on_publish_impl(int mid);
    void on_message_impl(const std::string& topic, const std::string& payload);

private:
    struct PendingAck {
        std::chrono::steady_clock::time_point sent_at;
        std::string json_payload;
        std::vector<int64_t> row_ids;  // empty = live telemetry; non-empty = replay batch
        bool resolved = false;         // an explicit ack (success or failure) arrived
        bool success = false;          // only meaningful when resolved
    };

    MqttConfig config_;
    CloudMqttConfig cloud_config_;
    std::string company_;
    std::string plant_id_;
    atg_app::AtgDatabase* db_;
    mosquitto* mosq_;
    bool connected_;
    std::mutex publish_mutex_;
    std::chrono::steady_clock::time_point last_publish_;
    std::chrono::steady_clock::time_point last_cloud_publish_;
    std::map<std::string, PendingAck> pending_acks_;  // keyed by our own message_id
    std::atomic<bool> replay_requested_{false};

    std::string build_sensor_data_array(const std::vector<SensorData>& sensors);
    std::string build_json(const std::vector<SensorData>& sensors);
    std::string build_cloud_envelope(const std::vector<SensorData>& sensors, const std::string& message_id);
    std::string build_batch_json(const std::vector<std::string>& payloads, const std::string& message_id);
    bool do_cloud_publish(const std::string& message_id, const std::string& json,
                          const std::vector<int64_t>& row_ids_on_success_delete,
                          const std::string& topic);
    bool wait_for_ack(const std::string& message_id, std::chrono::seconds timeout);
    void run_replay_drain();
    void handle_ack_payload(const std::string& payload);
};

#endif // MQTT_PUBLISHER_HPP
