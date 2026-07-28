#pragma once

#include "volume_lookup.hpp"
#include <atg/sensor_manager.hpp>
#include <sqlite3.h>
#include <vector>

namespace atg_app {

class AtgDatabase {
public:
    explicit AtgDatabase(const char* path);
    ~AtgDatabase();

    AtgDatabase(const AtgDatabase&) = delete;
    AtgDatabase& operator=(const AtgDatabase&) = delete;

    std::vector<atg::SensorConfig> load_enabled_sensors();
    void upsert_reading(int sensor_id, const atg::SensorResult& result);
    bool is_open() const { return db_ != nullptr; }

private:
    double load_offset_mm(int sensor_id);
    std::vector<VolumePoint> load_volume_points(int sensor_id);

    sqlite3* db_ = nullptr;
};

} // namespace atg_app
