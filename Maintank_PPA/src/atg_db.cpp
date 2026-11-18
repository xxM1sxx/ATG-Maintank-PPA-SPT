#include "atg_db.hpp"
#include <atg/debug_log.hpp>
#include <cstdio>
#include <cstdlib>
#include <chrono>
#include <ctime>
#include <stdexcept>
#include <string>

namespace atg_app {

namespace {

std::string iso_timestamp() {
    auto now = std::chrono::system_clock::now();
    std::time_t t = std::chrono::system_clock::to_time_t(now);
    char buf[32];
    std::strftime(buf, sizeof(buf), "%Y-%m-%dT%H:%M:%S", std::localtime(&t));
    return buf;
}

} // namespace

AtgDatabase::AtgDatabase(const char* path) {
    if (sqlite3_open(path, &db_) != SQLITE_OK) {
        std::string err = db_ ? sqlite3_errmsg(db_) : "unknown error";
        if (db_) sqlite3_close(db_);
        db_ = nullptr;
        throw std::runtime_error("AtgDatabase: cannot open DB: " + err);
    }
    // Same file is written concurrently by the web (PHP, which sets WAL mode
    // + its own busy_timeout in conn.php). Set a busy_timeout here too so a
    // brief write from the web side doesn't cause an immediate SQLITE_BUSY
    // failure on our side - wait/retry for a bit instead.
    sqlite3_busy_timeout(db_, 5000);
}

AtgDatabase::~AtgDatabase() {
    if (db_) sqlite3_close(db_);
}

std::vector<atg::SensorConfig> AtgDatabase::load_enabled_sensors() {
    std::vector<atg::SensorConfig> configs;
    if (!db_) return configs;

    const char* sql =
        "SELECT id, enabled, device, slave_id, baudrate, polling_interval_ms, de_gpio_pin, read_timeout_ms "
        "FROM tbl_atg_sensor WHERE enabled=1 ORDER BY id";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK)
        return configs;

    while (sqlite3_step(stmt) == SQLITE_ROW) {
        atg::SensorConfig cfg;
        cfg.id = sqlite3_column_int(stmt, 0);
        cfg.enabled = sqlite3_column_int(stmt, 1) != 0;
        const char* device = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 2));
        cfg.device = device ? device : "";
        cfg.slave_id = static_cast<uint8_t>(sqlite3_column_int(stmt, 3));
        cfg.baudrate = atg::bps_from_int(sqlite3_column_int(stmt, 4));
        cfg.polling_interval = atg::milliseconds(sqlite3_column_int(stmt, 5));
        cfg.de_gpio_pin = sqlite3_column_int(stmt, 6);
        cfg.timeout = atg::milliseconds(sqlite3_column_int(stmt, 7));
        if (!cfg.device.empty()) configs.push_back(cfg);
    }
    sqlite3_finalize(stmt);
    return configs;
}

double AtgDatabase::load_offset_mm(int sensor_id) {
    if (!db_) return 0;

    const char* sql = "SELECT offset_mm FROM tbl_atg_sensor WHERE id=?";
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK)
        return 0;

    sqlite3_bind_int(stmt, 1, sensor_id);
    double offset = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW)
        offset = sqlite3_column_double(stmt, 0);
    sqlite3_finalize(stmt);
    return offset;
}

double AtgDatabase::load_offset_mm_water(int sensor_id) {
    if (!db_) return 0;

    const char* sql = "SELECT offset_mm_water FROM tbl_atg_sensor WHERE id=?";
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK)
        return 0;

    sqlite3_bind_int(stmt, 1, sensor_id);
    double offset = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW)
        offset = sqlite3_column_double(stmt, 0);
    sqlite3_finalize(stmt);
    return offset;
}

std::vector<VolumePoint> AtgDatabase::load_volume_points(int sensor_id) {
    std::vector<VolumePoint> points;
    if (!db_) return points;

    const char* sql =
        "SELECT sounding_mm, volume_l FROM tbl_atg_volume_point "
        "WHERE sensor_id=? ORDER BY sounding_mm";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK)
        return points;

    sqlite3_bind_int(stmt, 1, sensor_id);
    while (sqlite3_step(stmt) == SQLITE_ROW) {
        VolumePoint p;
        p.sounding_mm = sqlite3_column_int(stmt, 0);
        p.volume_l = sqlite3_column_double(stmt, 1);
        points.push_back(p);
    }
    sqlite3_finalize(stmt);
    return points;
}

void AtgDatabase::upsert_reading(int sensor_id, const atg::SensorResult& r) {
    if (!db_) return;

    const char* sql =
        "INSERT INTO tbl_atg_reading (sensor_id, position_mm, volume, water_mm, fuel_temp_c, offset, offset_water, ok, error_msg, updated_at, volume_water_l) "
        "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) "
        "ON CONFLICT(sensor_id) DO UPDATE SET "
        "position_mm=excluded.position_mm, volume=excluded.volume, "
        "water_mm=excluded.water_mm, fuel_temp_c=excluded.fuel_temp_c, "
        "offset=excluded.offset, offset_water=excluded.offset_water, "
        "ok=excluded.ok, error_msg=excluded.error_msg, updated_at=excluded.updated_at, "
        "volume_water_l=excluded.volume_water_l";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) return;

    double probe_mm = 0.0, volume_fuel = 0.0, water_mm = 0.0, fuel_temp_c = 0.0;
    double offset_mm = 0.0, offset_mm_water = 0.0, volume_water = 0.0;
    bool volume_valid = false;
    bool has_probe = false;
    if (r.ok && !r.readings.empty()) {
        const atg::TankReading& t = r.readings[0];
        probe_mm = t.fuel_level_mm;
        water_mm = t.water_level_mm;
        fuel_temp_c = t.fuel_temp_c;
        has_probe = true;
        offset_mm = load_offset_mm(sensor_id);
        offset_mm_water = load_offset_mm_water(sensor_id);
        const auto points = load_volume_points(sensor_id);

        // Fuel and water are each looked up independently against the same
        // strapping/tera table (height -> volume for the tank shape), each with
        // its own calibration offset - no total-sounding subtraction anymore.
        const double vf = lookup_volume_liters(probe_mm, offset_mm, points);
        const double vw = lookup_volume_liters(water_mm, offset_mm_water, points);
        if (vf >= 0 && vw >= 0) {
            volume_fuel = vf;
            volume_water = vw;
            volume_valid = true;
        }
        atg::atg_log(atg::LogLevel::Info, "ATG",
            "volume lookup sensor=%d probe=%.1fmm offset=%.1fmm water=%.1fmm offset_water=%.1fmm points=%zu -> fuel=%.1fL water=%.1fL",
            sensor_id, probe_mm, offset_mm, water_mm, offset_mm_water, points.size(),
            volume_valid ? volume_fuel : -1.0, volume_valid ? volume_water : -1.0);
    }

    sqlite3_bind_int(stmt, 1, sensor_id);
    sqlite3_bind_double(stmt, 2, probe_mm);
    if (volume_valid)
        sqlite3_bind_double(stmt, 3, volume_fuel);
    else
        sqlite3_bind_null(stmt, 3);
    if (has_probe) {
        sqlite3_bind_double(stmt, 4, water_mm);
        sqlite3_bind_double(stmt, 5, fuel_temp_c);
        sqlite3_bind_double(stmt, 6, offset_mm);
        sqlite3_bind_double(stmt, 7, offset_mm_water);
    } else {
        sqlite3_bind_null(stmt, 4);
        sqlite3_bind_null(stmt, 5);
        sqlite3_bind_null(stmt, 6);
        sqlite3_bind_null(stmt, 7);
    }
    sqlite3_bind_int(stmt, 8, r.ok ? 1 : 0);
    if (r.ok)
        sqlite3_bind_null(stmt, 9);
    else
        sqlite3_bind_text(stmt, 9, r.error.c_str(), -1, SQLITE_TRANSIENT);
    std::string ts = iso_timestamp();
    sqlite3_bind_text(stmt, 10, ts.c_str(), -1, SQLITE_TRANSIENT);
    if (volume_valid)
        sqlite3_bind_double(stmt, 11, volume_water);
    else
        sqlite3_bind_null(stmt, 11);

    sqlite3_step(stmt);
    sqlite3_finalize(stmt);
}

MqttConfig AtgDatabase::load_mqtt_config() {
    MqttConfig config;

    if (!db_) {
        return config;  // Return defaults if DB not open
    }

    const char* sql = "SELECT broker_host, broker_port, username, password, topic_path, "
                      "publish_interval_sec, enabled FROM tbl_atg_mqtt_setting WHERE id=1";

    sqlite3_stmt* stmt = nullptr;
    int rc = sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr);

    if (rc != SQLITE_OK) {
        return config;  // Return defaults if query fails
    }

    if (sqlite3_step(stmt) == SQLITE_ROW) {
        const char* host = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 0));
        config.broker_host = host ? host : "";
        config.broker_port = sqlite3_column_int(stmt, 1);

        const char* user = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 2));
        config.username = user ? user : "";

        const char* pass = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 3));
        config.password = pass ? pass : "";

        const char* topic = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 4));
        config.topic_path = topic ? topic : "maintank/atg/value";

        config.publish_interval_sec = sqlite3_column_int(stmt, 5);
        config.enabled = sqlite3_column_int(stmt, 6) != 0;
    }

    sqlite3_finalize(stmt);
    return config;
}

std::vector<SensorData> AtgDatabase::load_sensor_data_for_mqtt() {
    std::vector<SensorData> sensors;
    if (!db_) return sensors;

    // Every enabled sensor is always published, even with no reading yet or a
    // failed Modbus read (r.ok=0/NULL) - status=0 marks those, with all values
    // zeroed, instead of being silently omitted from the payload. Disabled
    // sensors (unchecked in ATG Sensor Configuration) are excluded entirely -
    // they don't exist as far as MQTT consumers are concerned.
    //
    // MQTT uses r.volume (the fuel-only volume, water already subtracted) so the
    // published volume_l matches Fuel Vol on the web UI, not Total Vol.
    //
    // fuel_level_mm reported here = position_mm + offset (the actual "sounding"
    // height), not the raw probe reading.
    const char* sql =
        "SELECT s.id, s.slocn, s.sensor_number, s.capacity_liters, "
        "       r.position_mm, r.volume, r.fuel_temp_c, r.offset, r.ok "
        "FROM tbl_atg_sensor s "
        "LEFT JOIN tbl_atg_reading r ON s.id = r.sensor_id "
        "WHERE s.enabled=1 ORDER BY s.id";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK)
        return sensors;

    while (sqlite3_step(stmt) == SQLITE_ROW) {
        SensorData data;

        const char* slocn = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 1));
        data.slocn = slocn ? slocn : "";

        const char* number = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 2));
        data.number = number ? number : "";

        data.capacity_l = sqlite3_column_double(stmt, 3);

        const bool ok = sqlite3_column_type(stmt, 8) != SQLITE_NULL && sqlite3_column_int(stmt, 8) != 0;
        data.status = ok ? 1 : 0;

        if (ok) {
            double position_mm = sqlite3_column_double(stmt, 4);
            double offset_mm = sqlite3_column_double(stmt, 7);
            data.fuel_level_mm = position_mm + offset_mm;
            data.volume_l = sqlite3_column_double(stmt, 5);
            data.temperature_c = sqlite3_column_double(stmt, 6);
        } else {
            data.fuel_level_mm = 0.0;
            data.volume_l = 0.0;
            data.temperature_c = 0.0;
        }

        sensors.push_back(data);
    }

    sqlite3_finalize(stmt);
    return sensors;
}

CloudMqttConfig AtgDatabase::load_cloud_mqtt_config() {
    CloudMqttConfig config;
    config.publish_interval_sec = 120;
    config.enabled = false;
    config.store_on_fail = true;

    if (!db_) {
        return config;
    }

    const char* sql = "SELECT cloud_topic_path, cloud_request_topic, "
                      "cloud_publish_interval_sec, cloud_enabled, cloud_store_on_fail "
                      "FROM tbl_atg_mqtt_setting WHERE id=1";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) {
        return config;
    }

    if (sqlite3_step(stmt) == SQLITE_ROW) {
        const char* topic = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 0));
        config.data_topic = topic ? topic : "";

        const char* req_topic = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 1));
        config.request_topic = req_topic ? req_topic : "";

        config.publish_interval_sec = sqlite3_column_int(stmt, 2);
        config.enabled = sqlite3_column_int(stmt, 3) != 0;
        config.store_on_fail = sqlite3_column_int(stmt, 4) != 0;
    }

    sqlite3_finalize(stmt);
    return config;
}

void AtgDatabase::insert_pending_cloud_row(const std::string& payload) {
    if (!db_) return;

    const char* sql = "INSERT INTO tbl_atg_cloud_pending (payload) VALUES (?)";
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) {
        return;
    }
    sqlite3_bind_text(stmt, 1, payload.c_str(), -1, SQLITE_TRANSIENT);
    sqlite3_step(stmt);
    sqlite3_finalize(stmt);
}

std::vector<PendingCloudRow> AtgDatabase::select_pending_cloud_batch(int limit) {
    std::vector<PendingCloudRow> rows;
    if (!db_) return rows;

    const char* sql = "SELECT id, payload FROM tbl_atg_cloud_pending ORDER BY id ASC LIMIT ?";
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) {
        return rows;
    }
    sqlite3_bind_int(stmt, 1, limit);

    while (sqlite3_step(stmt) == SQLITE_ROW) {
        PendingCloudRow row;
        row.id = sqlite3_column_int64(stmt, 0);
        const char* payload = reinterpret_cast<const char*>(sqlite3_column_text(stmt, 1));
        row.payload = payload ? payload : "";
        rows.push_back(row);
    }

    sqlite3_finalize(stmt);
    return rows;
}

void AtgDatabase::delete_pending_cloud_rows(const std::vector<int64_t>& ids) {
    if (!db_ || ids.empty()) return;

    std::string sql = "DELETE FROM tbl_atg_cloud_pending WHERE id IN (";
    for (size_t i = 0; i < ids.size(); ++i) {
        if (i > 0) sql += ",";
        sql += "?";
    }
    sql += ")";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql.c_str(), -1, &stmt, nullptr) != SQLITE_OK) {
        return;
    }
    for (size_t i = 0; i < ids.size(); ++i) {
        sqlite3_bind_int64(stmt, static_cast<int>(i) + 1, ids[i]);
    }
    sqlite3_step(stmt);
    sqlite3_finalize(stmt);
}

int AtgDatabase::count_pending_cloud_rows() {
    if (!db_) return 0;

    const char* sql = "SELECT COUNT(*) FROM tbl_atg_cloud_pending";
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) {
        return 0;
    }

    int count = 0;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        count = sqlite3_column_int(stmt, 0);
    }

    sqlite3_finalize(stmt);
    return count;
}

void AtgDatabase::trim_pending_cloud_rows(int cap) {
    if (!db_) return;

    const char* sql = "DELETE FROM tbl_atg_cloud_pending WHERE id NOT IN "
                      "(SELECT id FROM tbl_atg_cloud_pending ORDER BY id DESC LIMIT ?)";
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) {
        return;
    }
    sqlite3_bind_int(stmt, 1, cap);
    sqlite3_step(stmt);
    sqlite3_finalize(stmt);
}

bool AtgDatabase::load_publish_intervals(int& local_sec, int& cloud_sec) {
    if (!db_) return false;

    const char* sql = "SELECT publish_interval_sec, cloud_publish_interval_sec "
                      "FROM tbl_atg_mqtt_setting WHERE id=1";
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) {
        return false;
    }

    bool found = false;
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        local_sec = sqlite3_column_int(stmt, 0);
        cloud_sec = sqlite3_column_int(stmt, 1);
        if (local_sec < 1) local_sec = 1;
        if (cloud_sec < 1) cloud_sec = 1;
        found = true;
    }

    sqlite3_finalize(stmt);
    return found;
}

} // namespace atg_app
