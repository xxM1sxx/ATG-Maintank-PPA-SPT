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
        "INSERT INTO tbl_atg_reading (sensor_id, position_mm, volume, water_mm, fuel_temp_c, offset, ok, error_msg, updated_at) "
        "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) "
        "ON CONFLICT(sensor_id) DO UPDATE SET "
        "position_mm=excluded.position_mm, volume=excluded.volume, "
        "water_mm=excluded.water_mm, fuel_temp_c=excluded.fuel_temp_c, "
        "offset=excluded.offset, "
        "ok=excluded.ok, error_msg=excluded.error_msg, updated_at=excluded.updated_at";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(db_, sql, -1, &stmt, nullptr) != SQLITE_OK) return;

    double probe_mm = 0.0, volume = 0.0, water_mm = 0.0, fuel_temp_c = 0.0;
    double offset_mm = 0.0;
    bool volume_valid = false;
    bool has_probe = false;
    if (r.ok && !r.readings.empty()) {
        const atg::TankReading& t = r.readings[0];
        probe_mm = t.fuel_level_mm;
        water_mm = t.water_level_mm;
        fuel_temp_c = t.fuel_temp_c;
        has_probe = true;
        offset_mm = load_offset_mm(sensor_id);
        const auto points = load_volume_points(sensor_id);
        // SUCOFINDO table: volume from sounding (probe + offset) only, not water
        const double vol = lookup_volume_liters(probe_mm, offset_mm, points);
        if (vol >= 0) {
            volume = vol;
            volume_valid = true;
        }
        atg::atg_debug_log("atg-debug",
            "volume lookup sensor=%d probe=%.1fmm offset=%.1fmm sounding=%.1fmm points=%zu -> %.1f L",
            sensor_id, probe_mm, offset_mm, probe_mm + offset_mm, points.size(),
            volume_valid ? volume : -1.0);
    }

    sqlite3_bind_int(stmt, 1, sensor_id);
    sqlite3_bind_double(stmt, 2, probe_mm);
    if (volume_valid)
        sqlite3_bind_double(stmt, 3, volume);
    else
        sqlite3_bind_null(stmt, 3);
    if (has_probe) {
        sqlite3_bind_double(stmt, 4, water_mm);
        sqlite3_bind_double(stmt, 5, fuel_temp_c);
        sqlite3_bind_double(stmt, 6, offset_mm);
    } else {
        sqlite3_bind_null(stmt, 4);
        sqlite3_bind_null(stmt, 5);
        sqlite3_bind_null(stmt, 6);
    }
    sqlite3_bind_int(stmt, 7, r.ok ? 1 : 0);
    if (r.ok)
        sqlite3_bind_null(stmt, 8);
    else
        sqlite3_bind_text(stmt, 8, r.error.c_str(), -1, SQLITE_TRANSIENT);
    std::string ts = iso_timestamp();
    sqlite3_bind_text(stmt, 9, ts.c_str(), -1, SQLITE_TRANSIENT);

    sqlite3_step(stmt);
    sqlite3_finalize(stmt);
}

} // namespace atg_app
