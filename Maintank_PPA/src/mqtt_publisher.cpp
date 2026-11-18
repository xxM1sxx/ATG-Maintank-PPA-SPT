#include "mqtt_publisher.hpp"
#include "atg_db.hpp"
#include <atg/debug_log.hpp>
#include <mosquitto.h>
#include <cstdio>
#include <sstream>
#include <iomanip>
#include <ctime>
#include <chrono>
#include <thread>
#include <atomic>
#include <cstdint>

namespace {

// Application-level ack for the cloud path is expected within this window. Longer
// than the old broker-PUBACK-based timeout since it now depends on a real round
// trip through a downstream controller to the actual cloud endpoint.
constexpr int kCloudAckTimeoutSec = 10;

void on_connect(struct mosquitto* mosq, void* userdata, int reason_code) {
    (void)mosq;
    static_cast<MqttPublisher*>(userdata)->on_connect_impl(reason_code);
}

void on_disconnect(struct mosquitto* mosq, void* userdata, int reason_code) {
    (void)mosq; (void)userdata;
    if (reason_code == 0) {
        atg::atg_log(atg::LogLevel::Info, "MQTT", "Disconnected (clean shutdown)");
    } else {
        atg::atg_log(atg::LogLevel::Warn, "MQTT", "Disconnected unexpectedly (rc=%d)", reason_code);
    }
}

void on_publish(struct mosquitto* mosq, void* userdata, int mid) {
    (void)mosq;
    static_cast<MqttPublisher*>(userdata)->on_publish_impl(mid);
}

void on_message(struct mosquitto* mosq, void* userdata, const struct mosquitto_message* msg) {
    (void)mosq;
    if (!msg || !msg->topic) return;

    std::string topic(msg->topic);
    std::string payload;
    if (msg->payload && msg->payloadlen > 0) {
        payload.assign(static_cast<const char*>(msg->payload), static_cast<size_t>(msg->payloadlen));
    }
    static_cast<MqttPublisher*>(userdata)->on_message_impl(topic, payload);
}

// Minimal extractor for a single top-level string field, e.g. {"command":"get_pending"}
// or {"message_id":"...","status":"success"}. Not a general JSON parser - this
// codebase hand-rolls JSON everywhere (see build_json() etc.) rather than pulling
// in a parsing library, and every message here has a small, fixed set of fields.
// Returns "" if the field isn't found or isn't a string.
std::string extract_json_string_field(const std::string& json, const std::string& key) {
    std::string needle = "\"" + key + "\"";
    size_t key_pos = json.find(needle);
    if (key_pos == std::string::npos) return "";

    size_t colon_pos = json.find(':', key_pos + needle.size());
    if (colon_pos == std::string::npos) return "";

    size_t quote_start = json.find('"', colon_pos + 1);
    if (quote_start == std::string::npos) return "";

    size_t quote_end = json.find('"', quote_start + 1);
    if (quote_end == std::string::npos) return "";

    return json.substr(quote_start + 1, quote_end - quote_start - 1);
}

std::string generate_message_id() {
    static std::atomic<uint64_t> counter{0};
    auto now_ms = std::chrono::duration_cast<std::chrono::milliseconds>(
        std::chrono::system_clock::now().time_since_epoch()).count();
    uint64_t seq = counter.fetch_add(1, std::memory_order_relaxed);
    std::ostringstream oss;
    oss << "atg-" << now_ms << "-" << seq;
    return oss.str();
}

std::string get_iso_timestamp() {
    auto now = std::chrono::system_clock::now();
    auto time_t = std::chrono::system_clock::to_time_t(now);
    std::stringstream ss;
    ss << std::put_time(std::gmtime(&time_t), "%Y-%m-%dT%H:%M:%SZ");
    return ss.str();
}

} // namespace

MqttPublisher::MqttPublisher(const MqttConfig& config, const CloudMqttConfig& cloud_config,
                             const std::string& company, const std::string& plant_id,
                             atg_app::AtgDatabase* db)
    : config_(config), cloud_config_(cloud_config), company_(company), plant_id_(plant_id),
      db_(db), mosq_(nullptr), connected_(false) {
}

MqttPublisher::~MqttPublisher() {
    close();
}

bool MqttPublisher::init() {
    if (!config_.enabled && !cloud_config_.enabled) {
        atg::atg_log(atg::LogLevel::Info, "MQTT", "MQTT publishing disabled");
        return false;
    }

    if (config_.broker_host.empty()) {
        atg::atg_log(atg::LogLevel::Error, "MQTT", "Broker host not configured");
        return false;
    }

    mosquitto_lib_init();

    mosq_ = mosquitto_new(nullptr, true, this);
    if (mosq_ == nullptr) {
        atg::atg_log(atg::LogLevel::Error, "MQTT", "Failed to create mosquitto instance");
        mosquitto_lib_cleanup();
        return false;
    }

    mosquitto_connect_callback_set(mosq_, on_connect);
    mosquitto_disconnect_callback_set(mosq_, on_disconnect);
    mosquitto_publish_callback_set(mosq_, on_publish);
    mosquitto_message_callback_set(mosq_, on_message);

    if (!config_.username.empty() && !config_.password.empty()) {
        int auth_result = mosquitto_username_pw_set(mosq_, config_.username.c_str(), config_.password.c_str());
        if (auth_result != MOSQ_ERR_SUCCESS) {
            atg::atg_log(atg::LogLevel::Error, "MQTT", "Failed to set auth: %s", mosquitto_strerror(auth_result));
            mosquitto_destroy(mosq_);
            mosq_ = nullptr;
            mosquitto_lib_cleanup();
            return false;
        }
    }

    int connect_result = mosquitto_connect(mosq_, config_.broker_host.c_str(), config_.broker_port, 60);
    if (connect_result != MOSQ_ERR_SUCCESS) {
        atg::atg_log(atg::LogLevel::Warn, "MQTT", "Initial connect failed: %s (will retry in background)",
                     mosquitto_strerror(connect_result));
    }

    int loop_result = mosquitto_loop_start(mosq_);
    if (loop_result != MOSQ_ERR_SUCCESS) {
        atg::atg_log(atg::LogLevel::Error, "MQTT", "loop_start failed: %s", mosquitto_strerror(loop_result));
        mosquitto_destroy(mosq_);
        mosq_ = nullptr;
        mosquitto_lib_cleanup();
        return false;
    }

    if (cloud_config_.enabled) {
        // Subscribe to the data topic too - the downstream controller that actually
        // pushes to the cloud publishes its success/failure ack back on this same
        // topic, so we must be a subscriber to receive it (not just a publisher).
        if (!cloud_config_.data_topic.empty()) {
            int sub_result = mosquitto_subscribe(mosq_, nullptr, cloud_config_.data_topic.c_str(), 1);
            if (sub_result != MOSQ_ERR_SUCCESS) {
                atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD", "Subscribe to data topic=%s failed: %s",
                             cloud_config_.data_topic.c_str(), mosquitto_strerror(sub_result));
            } else {
                atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD", "Subscribed to data topic=%s (for ack replies)",
                             cloud_config_.data_topic.c_str());
            }
        }
        if (!cloud_config_.request_topic.empty()) {
            int sub_result = mosquitto_subscribe(mosq_, nullptr, cloud_config_.request_topic.c_str(), 1);
            if (sub_result != MOSQ_ERR_SUCCESS) {
                atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD", "Subscribe to request topic=%s failed: %s",
                             cloud_config_.request_topic.c_str(), mosquitto_strerror(sub_result));
            } else {
                atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD", "Subscribed to request topic=%s",
                             cloud_config_.request_topic.c_str());
            }
        }
    }

    atg::atg_log(atg::LogLevel::Info, "MQTT", "Initialized: %s:%d local_topic=%s local_enabled=%d",
                       config_.broker_host.c_str(), config_.broker_port, config_.topic_path.c_str(),
                       config_.enabled ? 1 : 0);
    if (cloud_config_.enabled) {
        atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD",
            "Cloud enabled: data_topic=%s request_topic=%s interval=%ds ack_timeout=%ds store_on_fail=%d",
            cloud_config_.data_topic.c_str(), cloud_config_.request_topic.c_str(),
            cloud_config_.publish_interval_sec, kCloudAckTimeoutSec, cloud_config_.store_on_fail ? 1 : 0);
    }
    last_publish_ = std::chrono::steady_clock::now();
    last_cloud_publish_ = std::chrono::steady_clock::now();
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

void MqttPublisher::set_intervals(int local_interval_sec, int cloud_interval_sec) {
    // Dipanggil dari main thread, sama seperti publish_*() yang membacanya -
    // jadi tidak perlu publish_mutex_ (hanya dipakai saat publish/subscribe).
    if (local_interval_sec != config_.publish_interval_sec) {
        atg::atg_log(atg::LogLevel::Info, "MQTT", "local publish interval %d -> %ds (live)",
                     config_.publish_interval_sec, local_interval_sec);
        config_.publish_interval_sec = local_interval_sec;
    }
    if (cloud_interval_sec != cloud_config_.publish_interval_sec) {
        atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD", "cloud publish interval %d -> %ds (live)",
                     cloud_config_.publish_interval_sec, cloud_interval_sec);
        cloud_config_.publish_interval_sec = cloud_interval_sec;
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
    int mid = 0;

    {
        std::lock_guard<std::mutex> lock(publish_mutex_);
        int result = mosquitto_publish(mosq_, &mid, config_.topic_path.c_str(),
                                       static_cast<int>(json.size()), json.c_str(), 1, false);
        if (result != MOSQ_ERR_SUCCESS) {
            atg::atg_log(atg::LogLevel::Error, "MQTT",
                "Publish failed topic=%s sensors=%zu: %s",
                config_.topic_path.c_str(), sensors.size(), mosquitto_strerror(result));
        } else {
            atg::atg_log(atg::LogLevel::Info, "MQTT",
                "Publish queued mid=%d topic=%s sensors=%zu bytes=%zu",
                mid, config_.topic_path.c_str(), sensors.size(), json.size());
        }
    }

    last_publish_ = now;
}

void MqttPublisher::publish_cloud_telemetry(const std::vector<SensorData>& sensors) {
    if (!cloud_config_.enabled || !mosq_) {
        return;
    }

    auto now = std::chrono::steady_clock::now();
    auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(now - last_cloud_publish_).count();
    if (elapsed < cloud_config_.publish_interval_sec) {
        return;
    }
    last_cloud_publish_ = now;

    std::string message_id = generate_message_id();
    std::string json = build_cloud_envelope(sensors, message_id);
    do_cloud_publish(message_id, json, {}, cloud_config_.data_topic);
}

bool MqttPublisher::do_cloud_publish(const std::string& message_id, const std::string& json,
                                     const std::vector<int64_t>& row_ids, const std::string& topic) {
    const bool is_batch = !row_ids.empty();
    int mid = 0;
    std::lock_guard<std::mutex> lock(publish_mutex_);
    int result = mosquitto_publish(mosq_, &mid, topic.c_str(),
                                   static_cast<int>(json.size()), json.c_str(), 1, false);
    if (result != MOSQ_ERR_SUCCESS) {
        atg::atg_log(atg::LogLevel::Error, "MQTT-CLOUD",
            "%s publish call failed message_id=%s topic=%s: %s",
            is_batch ? "Replay batch" : "Telemetry", message_id.c_str(),
            topic.c_str(), mosquitto_strerror(result));
        if (!is_batch && db_ && cloud_config_.store_on_fail) {
            db_->insert_pending_cloud_row(json);
            atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD", "Stored to pending queue (publish call failed)");
        }
        return false;
    }

    atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD",
        "%s sent message_id=%s topic=%s mid=%d bytes=%zu, awaiting cloud ack (up to %ds)",
        is_batch ? "Replay batch" : "Telemetry", message_id.c_str(),
        topic.c_str(), mid, json.size(), kCloudAckTimeoutSec);

    PendingAck pa;
    pa.sent_at = std::chrono::steady_clock::now();
    pa.json_payload = json;
    pa.row_ids = row_ids;
    pa.resolved = false;
    pa.success = false;
    pending_acks_[message_id] = pa;
    return true;
}

void MqttPublisher::check_ack_timeouts() {
    if (!cloud_config_.enabled) return;

    auto now = std::chrono::steady_clock::now();
    std::vector<PendingAck> silently_timed_out;
    {
        std::lock_guard<std::mutex> lock(publish_mutex_);
        for (auto it = pending_acks_.begin(); it != pending_acks_.end(); ) {
            // Replay batches are actively polled (and erased) by wait_for_ack() on
            // the main thread, including once resolved - leave those alone here.
            if (!it->second.row_ids.empty()) {
                ++it;
                continue;
            }

            if (it->second.resolved) {
                // Explicit success already handled at the point of the ack (see
                // on_message_impl); nothing more to do, just drop the bookkeeping.
                it = pending_acks_.erase(it);
                continue;
            }

            auto age = std::chrono::duration_cast<std::chrono::seconds>(now - it->second.sent_at).count();
            if (age >= kCloudAckTimeoutSec) {
                silently_timed_out.push_back(std::move(it->second));
                it = pending_acks_.erase(it);
            } else {
                ++it;
            }
        }
    }

    for (auto& pa : silently_timed_out) {
        // No ack at all (success or failure) arrived within the window.
        if (cloud_config_.store_on_fail) {
            atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD",
                "Telemetry ack timeout (no reply within %ds) -> stored to pending queue (bytes=%zu)",
                kCloudAckTimeoutSec, pa.json_payload.size());
            if (db_) db_->insert_pending_cloud_row(pa.json_payload);
        } else {
            atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD",
                "Telemetry ack timeout (no reply within %ds) -> dropped (store-on-fail disabled)",
                kCloudAckTimeoutSec);
        }
    }
}

bool MqttPublisher::wait_for_ack(const std::string& message_id, std::chrono::seconds timeout) {
    auto deadline = std::chrono::steady_clock::now() + timeout;
    while (std::chrono::steady_clock::now() < deadline) {
        {
            std::lock_guard<std::mutex> lock(publish_mutex_);
            auto it = pending_acks_.find(message_id);
            if (it == pending_acks_.end()) {
                return false;  // shouldn't normally happen; treat as failure
            }
            if (it->second.resolved) {
                bool ok = it->second.success;
                pending_acks_.erase(it);
                return ok;
            }
        }
        std::this_thread::sleep_for(std::chrono::milliseconds(50));
    }
    // No explicit ack at all within the window - clean up, treat as failure.
    std::lock_guard<std::mutex> lock(publish_mutex_);
    pending_acks_.erase(message_id);
    return false;
}

void MqttPublisher::poll_replay_request() {
    if (!cloud_config_.enabled || !db_) return;
    if (replay_requested_.exchange(false)) {
        run_replay_drain();
    }
}

void MqttPublisher::run_replay_drain() {
    // One request = one batch of up to 50 (oldest first). No auto-continue - if
    // more than 50 rows are pending, the downstream controller is expected to
    // send another {"command":"get_pending"} request to get the next batch.
    int pending_before = db_->count_pending_cloud_rows();
    atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD",
        "Replay request: %d rows pending", pending_before);

    auto batch = db_->select_pending_cloud_batch(50);
    if (batch.empty()) {
        atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD", "Replay request: no pending rows, nothing to send");
        return;
    }

    std::vector<std::string> payloads;
    std::vector<int64_t> ids;
    for (auto& row : batch) {
        payloads.push_back(row.payload);
        ids.push_back(row.id);
    }

    atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD",
        "Replay batch: sending %zu rows (ids %lld..%lld)",
        ids.size(), (long long)ids.front(), (long long)ids.back());

    std::string message_id = generate_message_id();
    std::string batch_json = build_batch_json(payloads, message_id);
    if (!do_cloud_publish(message_id, batch_json, ids, cloud_config_.request_topic)) {
        atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD", "Replay batch: publish call failed, rows stay queued");
        return;
    }

    bool acked = wait_for_ack(message_id, std::chrono::seconds(kCloudAckTimeoutSec));
    if (!acked) {
        atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD",
            "Replay batch: ack FAILED or timed out, %zu rows stay queued (not deleted)", ids.size());
        return;
    }

    db_->delete_pending_cloud_rows(ids);
    atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD",
        "Replay batch: ack OK, %zu rows delivered and removed from queue (%d rows still pending)",
        ids.size(), pending_before - static_cast<int>(ids.size()));
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
    atg::atg_log(atg::LogLevel::Info, "MQTT", "Closed");
}

void MqttPublisher::on_connect_impl(int reason_code) {
    if (reason_code == 0) {
        connected_ = true;
        atg::atg_log(atg::LogLevel::Info, "MQTT", "Connected to broker");
    } else {
        connected_ = false;
        atg::atg_log(atg::LogLevel::Error, "MQTT", "Connection failed: %s", mosquitto_connack_string(reason_code));
    }
}

void MqttPublisher::on_publish_impl(int mid) {
    // This is only the broker's protocol-level PUBACK (QoS 1) - it confirms the
    // message reached the broker, NOT that it reached the cloud. For the cloud
    // path, the actual delivery confirmation is the application-level ack handled
    // in on_message_impl() below.
    atg::atg_log(atg::LogLevel::Info, "MQTT", "Broker received publish mid=%d (protocol-level only)", mid);
}

void MqttPublisher::on_message_impl(const std::string& topic, const std::string& payload) {
    // Replay batches are now published (and acked) on request_topic, not data_topic
    // - so request_topic can carry either a {"command":...} trigger or a
    // {"message_id":...,"status":...} ack for a batch we sent there. Try command
    // first; anything without a recognized command falls through to ack handling.
    if (topic == cloud_config_.request_topic && !cloud_config_.request_topic.empty()) {
        std::string command = extract_json_string_field(payload, "command");
        if (!command.empty()) {
            if (command != "get_pending") {
                atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD",
                    "Request received with unrecognized command=%s, ignoring", command.c_str());
                return;
            }
            atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD", "Replay request received (command=get_pending)");
            replay_requested_.store(true);
            return;
        }
        handle_ack_payload(payload);
        return;
    }

    if (topic == cloud_config_.data_topic && !cloud_config_.data_topic.empty()) {
        handle_ack_payload(payload);
        return;
    }
}

void MqttPublisher::handle_ack_payload(const std::string& payload) {
    std::string message_id = extract_json_string_field(payload, "message_id");
    std::string status = extract_json_string_field(payload, "status");
    if (message_id.empty() || status.empty()) {
        // Not an ack we understand - most likely our own message echoed back by
        // the broker (we're subscribed to the same topics we publish to). Our own
        // outgoing envelopes have a message_id but no "status" field, so they're
        // silently ignored here.
        return;
    }

    std::lock_guard<std::mutex> lock(publish_mutex_);
    auto it = pending_acks_.find(message_id);
    if (it == pending_acks_.end()) {
        atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD",
            "Ack received for unknown/expired message_id=%s status=%s", message_id.c_str(), status.c_str());
        return;
    }

    const bool is_batch = !it->second.row_ids.empty();
    auto rtt_ms = std::chrono::duration_cast<std::chrono::milliseconds>(
        std::chrono::steady_clock::now() - it->second.sent_at).count();

    if (status == "success") {
        atg::atg_log(atg::LogLevel::Info, "MQTT-CLOUD",
            "Ack SUCCESS message_id=%s (%s) rtt=%lldms", message_id.c_str(),
            is_batch ? "replay batch" : "telemetry", (long long)rtt_ms);
        if (is_batch) {
            // wait_for_ack() (on the main thread, inside run_replay_drain) is
            // actively polling for this - leave it resolved for that to consume
            // and erase, so it can also trigger the row deletion.
            it->second.resolved = true;
            it->second.success = true;
        } else {
            // Nothing else needs this telemetry entry - drop it now.
            pending_acks_.erase(it);
        }
    } else {
        atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD",
            "Ack FAILED message_id=%s status=%s (%s) rtt=%lldms", message_id.c_str(), status.c_str(),
            is_batch ? "replay batch" : "telemetry", (long long)rtt_ms);
        if (is_batch) {
            // Let wait_for_ack() notice promptly instead of waiting out the full timeout.
            it->second.resolved = true;
            it->second.success = false;
        } else {
            if (cloud_config_.store_on_fail) {
                if (db_) db_->insert_pending_cloud_row(it->second.json_payload);
                atg::atg_log(atg::LogLevel::Warn, "MQTT-CLOUD", "Stored to pending queue (explicit failure ack)");
            }
            pending_acks_.erase(it);
        }
    }
}

std::string MqttPublisher::build_sensor_data_array(const std::vector<SensorData>& sensors) {
    std::stringstream ss;
    ss << "[";
    for (size_t i = 0; i < sensors.size(); ++i) {
        if (i > 0) ss << ",";

        const auto& sensor = sensors[i];
        ss << "{"
           << "\"slocn\":\"" << sensor.slocn << "\","
           << "\"number\":\"" << sensor.number << "\","
           << "\"status\":" << sensor.status << ","
           << "\"capacity_l\":" << static_cast<long>(sensor.capacity_l) << ","
           << "\"fuel_level_mm\":" << static_cast<long>(sensor.fuel_level_mm) << ","
           << "\"volume_l\":" << static_cast<long>(sensor.volume_l) << ","
           << "\"temperature_c\":" << static_cast<int>(sensor.temperature_c) << ","
           << "\"timestamp\":\"" << get_iso_timestamp() << "\""
           << "}";
    }
    ss << "]";
    return ss.str();
}

std::string MqttPublisher::build_json(const std::vector<SensorData>& sensors) {
    std::stringstream ss;
    ss << "{"
       << "\"company\":\"" << company_ << "\","
       << "\"plant_id\":\"" << plant_id_ << "\","
       << "\"data\":" << build_sensor_data_array(sensors)
       << "}";
    return ss.str();
}

std::string MqttPublisher::build_cloud_envelope(const std::vector<SensorData>& sensors, const std::string& message_id) {
    std::stringstream ss;
    ss << "{"
       << "\"message_id\":\"" << message_id << "\","
       << "\"type\":\"telemetry\","
       << "\"company\":\"" << company_ << "\","
       << "\"plant_id\":\"" << plant_id_ << "\","
       << "\"data\":" << build_sensor_data_array(sensors)
       << "}";
    return ss.str();
}

std::string MqttPublisher::build_batch_json(const std::vector<std::string>& payloads, const std::string& message_id) {
    std::stringstream ss;
    ss << "{"
       << "\"message_id\":\"" << message_id << "\","
       << "\"type\":\"replay_batch\","
       << "\"timestamp\":\"" << get_iso_timestamp() << "\","
       << "\"count\":" << payloads.size() << ","
       << "\"records\":[";
    for (size_t i = 0; i < payloads.size(); ++i) {
        if (i > 0) ss << ",";
        ss << payloads[i];  // each stored payload is already a complete JSON object
    }
    ss << "]}";
    return ss.str();
}
