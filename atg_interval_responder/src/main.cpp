// atg-interval-responder: penerima protokol request interval dari atg_mqtt_sub.
//
// Alur: subscribe maintank/atg/interval/request -> ambil data.log_interval ->
// UPDATE tbl_atg_mqtt_setting (baris id=1) -> baca balik -> publish
// {"status":"ok"} ke maintank/atg/interval/response.
//
// Nilai yang ditulis langsung dipakai atg-reader dalam <=5 detik (hot reload di
// main.cpp), jadi tidak perlu restart service apa pun.
#include <atg/debug_log.hpp>
#include <mosquitto.h>
#include <sqlite3.h>
#include <cstdlib>
#include <cstring>
#include <string>
#include <vector>

namespace {

constexpr const char* kRequestTopic  = "maintank/atg/interval/request";
constexpr const char* kResponseTopic = "maintank/atg/interval/response";
constexpr const char* kAckPayload    = "{\"status\":\"ok\"}";
constexpr const char* kCommand       = "set_log_interval";

// Batas wajar: di bawah 1 bikin atg-reader publish tiap tick, di atas 1 jam
// tidak masuk akal untuk interval log. Nilai di luar ini ditolak (tidak
// di-ack) supaya pengirim tahu requestnya tidak diterapkan.
constexpr int kMinIntervalSec = 1;
constexpr int kMaxIntervalSec = 3600;

struct Options {
    std::string db     = "/home/user/Apps/qims_logger.db";
    std::string host   = "127.0.0.1";
    int         port   = 1883;
    std::string user   = "admin";
    std::string pass   = "?#Qimtronics#?";
    std::string target = "cloud";  // cloud | local | both
};

Options       g_opt;
sqlite3*      g_db   = nullptr;
mosquitto*    g_mosq = nullptr;

bool get_json_string(const std::string& json, const char* key, std::string& out) {
    const std::string needle = std::string("\"") + key + "\"";
    size_t pos = json.find(needle);
    if (pos == std::string::npos) return false;
    pos = json.find(':', pos + needle.size());
    if (pos == std::string::npos) return false;
    pos = json.find('"', pos + 1);
    if (pos == std::string::npos) return false;
    const size_t end = json.find('"', pos + 1);
    if (end == std::string::npos) return false;
    out = json.substr(pos + 1, end - pos - 1);
    return true;
}

// Sama seperti pola di mqtt_publisher.cpp: JSON di sini kecil dan bentuknya
// tetap, jadi tidak perlu parser umum.
bool get_json_int(const std::string& json, const char* key, int& out) {
    const std::string needle = std::string("\"") + key + "\"";
    size_t pos = json.find(needle);
    if (pos == std::string::npos) return false;
    pos = json.find(':', pos + needle.size());
    if (pos == std::string::npos) return false;

    const char* p = json.c_str() + pos + 1;
    while (*p == ' ' || *p == '"') ++p;
    char* end = nullptr;
    const long v = std::strtol(p, &end, 10);
    if (end == p) return false;
    out = static_cast<int>(v);
    return true;
}

bool apply_column(const char* column, int seconds) {
    const std::string sql =
        std::string("UPDATE tbl_atg_mqtt_setting SET ") + column + "=? WHERE id=1";

    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(g_db, sql.c_str(), -1, &stmt, nullptr) != SQLITE_OK) {
        atg::atg_log(atg::LogLevel::Error, "INTERVAL", "prepare failed (%s): %s",
                     column, sqlite3_errmsg(g_db));
        return false;
    }
    sqlite3_bind_int(stmt, 1, seconds);
    const int rc = sqlite3_step(stmt);
    sqlite3_finalize(stmt);

    // 0 rows = baris id=1 belum ada (DB belum pernah dibuka di web).
    if (rc != SQLITE_DONE || sqlite3_changes(g_db) != 1) {
        atg::atg_log(atg::LogLevel::Error, "INTERVAL", "update %s failed: rc=%d changes=%d",
                     column, rc, sqlite3_changes(g_db));
        return false;
    }
    return true;
}

void log_current_intervals() {
    sqlite3_stmt* stmt = nullptr;
    if (sqlite3_prepare_v2(g_db,
            "SELECT publish_interval_sec, cloud_publish_interval_sec "
            "FROM tbl_atg_mqtt_setting WHERE id=1", -1, &stmt, nullptr) != SQLITE_OK) {
        return;
    }
    if (sqlite3_step(stmt) == SQLITE_ROW) {
        atg::atg_log(atg::LogLevel::Info, "INTERVAL", "db now: local=%ds cloud=%ds",
                     sqlite3_column_int(stmt, 0), sqlite3_column_int(stmt, 1));
    }
    sqlite3_finalize(stmt);
}

void publish_ack() {
    const int rc = mosquitto_publish(g_mosq, nullptr, kResponseTopic,
                                     static_cast<int>(std::strlen(kAckPayload)),
                                     kAckPayload, 1, false);
    if (rc != MOSQ_ERR_SUCCESS) {
        atg::atg_log(atg::LogLevel::Error, "INTERVAL", "publish ack failed: %s",
                     mosquitto_strerror(rc));
    }
}

void handle_request(const std::string& payload) {
    std::string command;
    if (get_json_string(payload, "command", command) && command != kCommand) {
        atg::atg_log(atg::LogLevel::Warn, "INTERVAL",
                     "unrecognized command=%s, ignored (no ack)", command.c_str());
        return;
    }

    int seconds = 0;
    if (!get_json_int(payload, "log_interval", seconds)) {
        atg::atg_log(atg::LogLevel::Warn, "INTERVAL", "request without log_interval, ignored");
        return;
    }
    if (seconds < kMinIntervalSec || seconds > kMaxIntervalSec) {
        atg::atg_log(atg::LogLevel::Warn, "INTERVAL",
                     "log_interval=%d outside %d..%d, ignored (no ack)",
                     seconds, kMinIntervalSec, kMaxIntervalSec);
        return;
    }

    std::vector<const char*> columns;
    if (g_opt.target == "cloud" || g_opt.target == "both")
        columns.push_back("cloud_publish_interval_sec");
    if (g_opt.target == "local" || g_opt.target == "both")
        columns.push_back("publish_interval_sec");

    bool ok = true;
    for (const char* column : columns) {
        if (!apply_column(column, seconds)) ok = false;
    }
    if (!ok) return;  // jangan ack kalau tidak benar-benar tersimpan

    atg::atg_log(atg::LogLevel::Info, "INTERVAL", "applied log_interval=%ds to %s",
                 seconds, g_opt.target.c_str());
    log_current_intervals();

    // Retry dari device berarti ack sebelumnya hilang - balas lagi walau nilai
    // tidak berubah (UPDATE tetap idempoten).
    publish_ack();
}

void on_connect(struct mosquitto*, void*, int reason_code) {
    if (reason_code != 0) {
        atg::atg_log(atg::LogLevel::Error, "INTERVAL", "connect failed: %s",
                     mosquitto_connack_string(reason_code));
        return;
    }
    atg::atg_log(atg::LogLevel::Info, "INTERVAL", "connected to %s:%d, subscribing %s",
                 g_opt.host.c_str(), g_opt.port, kRequestTopic);
    // Subscribe di sini (bukan sekali di main) karena loop_forever reconnect
    // sendiri dan langganan hilang setiap kali tersambung ulang.
    const int rc = mosquitto_subscribe(g_mosq, nullptr, kRequestTopic, 1);
    if (rc != MOSQ_ERR_SUCCESS) {
        atg::atg_log(atg::LogLevel::Error, "INTERVAL", "subscribe failed: %s",
                     mosquitto_strerror(rc));
    }
}

void on_message(struct mosquitto*, void*, const mosquitto_message* msg) {
    if (!msg || !msg->payload || msg->payloadlen <= 0) return;
    handle_request(std::string(static_cast<const char*>(msg->payload),
                               static_cast<size_t>(msg->payloadlen)));
}

void usage(const char* argv0) {
    std::fprintf(stderr,
        "usage: %s [--db PATH] [--host H] [--port N] [--user U] [--pass P]\n"
        "          [--target cloud|local|both]\n"
        "  --target: kolom interval yang ditulis (default cloud = cloud_publish_interval_sec)\n",
        argv0);
}

bool parse_args(int argc, char* argv[]) {
    for (int i = 1; i < argc; ++i) {
        const std::string arg = argv[i];
        const bool has_value = (i + 1 < argc);
        if (arg == "--db" && has_value)          g_opt.db = argv[++i];
        else if (arg == "--host" && has_value)   g_opt.host = argv[++i];
        else if (arg == "--port" && has_value)   g_opt.port = std::atoi(argv[++i]);
        else if (arg == "--user" && has_value)   g_opt.user = argv[++i];
        else if (arg == "--pass" && has_value)   g_opt.pass = argv[++i];
        else if (arg == "--target" && has_value) g_opt.target = argv[++i];
        else { usage(argv[0]); return false; }
    }
    if (g_opt.target != "cloud" && g_opt.target != "local" && g_opt.target != "both") {
        std::fprintf(stderr, "invalid --target: %s\n", g_opt.target.c_str());
        return false;
    }
    if (g_opt.port < 1 || g_opt.port > 65535) {
        std::fprintf(stderr, "invalid --port: %d\n", g_opt.port);
        return false;
    }
    return true;
}

} // namespace

int main(int argc, char* argv[]) {
    if (!parse_args(argc, argv)) return 1;

    if (sqlite3_open_v2(g_opt.db.c_str(), &g_db, SQLITE_OPEN_READWRITE, nullptr) != SQLITE_OK) {
        std::fprintf(stderr, "cannot open db %s: %s\n", g_opt.db.c_str(),
                     g_db ? sqlite3_errmsg(g_db) : "unknown");
        return 1;
    }
    // Sama seperti atg-reader: file ini juga ditulis PHP dan daemon pembaca.
    sqlite3_busy_timeout(g_db, 5000);

    mosquitto_lib_init();
    g_mosq = mosquitto_new(nullptr, true, nullptr);
    if (!g_mosq) {
        std::fprintf(stderr, "cannot create mosquitto instance\n");
        return 1;
    }
    mosquitto_connect_callback_set(g_mosq, on_connect);
    mosquitto_message_callback_set(g_mosq, on_message);
    if (!g_opt.user.empty()) {
        mosquitto_username_pw_set(g_mosq, g_opt.user.c_str(), g_opt.pass.c_str());
    }

    atg::atg_log(atg::LogLevel::Info, "INTERVAL",
                 "starting: broker=%s:%d db=%s target=%s", g_opt.host.c_str(),
                 g_opt.port, g_opt.db.c_str(), g_opt.target.c_str());

    if (mosquitto_connect(g_mosq, g_opt.host.c_str(), g_opt.port, 60) != MOSQ_ERR_SUCCESS) {
        atg::atg_log(atg::LogLevel::Warn, "INTERVAL",
                     "initial connect failed, retrying in background");
    }

    const int rc = mosquitto_loop_forever(g_mosq, -1, 1);
    atg::atg_log(atg::LogLevel::Error, "INTERVAL", "loop stopped: %s", mosquitto_strerror(rc));

    mosquitto_destroy(g_mosq);
    mosquitto_lib_cleanup();
    sqlite3_close(g_db);
    return 1;
}
