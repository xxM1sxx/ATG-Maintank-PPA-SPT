#pragma once

#include "volume_lookup.hpp"
#include "mqtt_publisher.hpp"
#include <atg/sensor_manager.hpp>
#include <sqlite3.h>
#include <vector>
#include <string>
#include <cstdint>

// Forward declare for atg_app namespace
struct MqttConfig;
struct CloudMqttConfig;
struct SensorData;

namespace atg_app {

struct PendingCloudRow {
    int64_t id;
    std::string payload;
};

class AtgDatabase {
public:
    explicit AtgDatabase(const char* path);
    ~AtgDatabase();

    AtgDatabase(const AtgDatabase&) = delete;
    AtgDatabase& operator=(const AtgDatabase&) = delete;

    std::vector<atg::SensorConfig> load_enabled_sensors();
    void upsert_reading(int sensor_id, const atg::SensorResult& result);
    MqttConfig load_mqtt_config();
    std::vector<SensorData> load_sensor_data_for_mqtt();
    bool is_open() const { return db_ != nullptr; }

    // Cloud MQTT: config + pending/failed-queue table CRUD.
    CloudMqttConfig load_cloud_mqtt_config();
    void insert_pending_cloud_row(const std::string& payload);
    std::vector<PendingCloudRow> select_pending_cloud_batch(int limit);
    void delete_pending_cloud_rows(const std::vector<int64_t>& ids);
    void trim_pending_cloud_rows(int cap);
    int count_pending_cloud_rows();

private:
    double load_offset_mm(int sensor_id);
    std::vector<VolumePoint> load_volume_points(int sensor_id);

    sqlite3* db_ = nullptr;
};

} // namespace atg_app
