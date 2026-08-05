#pragma once

#include <cstddef>
#include <cstdint>
#include <cstdlib>
#include <cstdio>
#include <cstdarg>
#include <chrono>
#include <ctime>

namespace atg {

inline bool atg_debug_enabled() {
    const char* v = std::getenv("ATG_DEBUG");
    // Default ON (Modbus hex, parsed values, status logs always visible).
    // Set ATG_DEBUG=0 to silence.
    return !(v && v[0] == '0' && v[1] == '\0');
}

// system_clock is set from the hardware RTC at boot (hwclock -s), so this
// wall-clock stamp reflects RTC time even with no network/NTP available.
inline void atg_debug_timestamp(char* buf, size_t buf_len) {
    using namespace std::chrono;
    auto now = system_clock::now();
    auto ms = duration_cast<milliseconds>(now.time_since_epoch()).count() % 1000;
    std::time_t t = system_clock::to_time_t(now);
    std::tm tm{};
#ifdef _WIN32
    localtime_s(&tm, &t);
#else
    localtime_r(&t, &tm);
#endif
    std::snprintf(buf, buf_len, "%04d-%02d-%02d %02d:%02d:%02d.%03d",
                  tm.tm_year + 1900, tm.tm_mon + 1, tm.tm_mday,
                  tm.tm_hour, tm.tm_min, tm.tm_sec, static_cast<int>(ms));
}

enum class LogLevel { Info, Warn, Error };

inline const char* atg_level_str(LogLevel level) {
    switch (level) {
        case LogLevel::Info:  return "INFO";
        case LogLevel::Warn:  return "WARN";
        case LogLevel::Error: return "ERROR";
    }
    return "INFO";
}

// Centralized, timestamped log line: "[YYYY-MM-DD HH:MM:SS.mmm] [LEVEL] [TAG] message".
// Info is routine/verbose and silenced by ATG_DEBUG=0; Warn/Error are real
// problems (comm failures, publish errors) and always print regardless of
// ATG_DEBUG, so silencing debug output never hides an actual fault.
inline void atg_log(LogLevel level, const char* tag, const char* fmt, ...) {
    if (level == LogLevel::Info && !atg_debug_enabled()) return;
    char ts[32];
    atg_debug_timestamp(ts, sizeof(ts));
    std::fprintf(stderr, "[%s] [%s] [%s] ", ts, atg_level_str(level), tag);
    va_list args;
    va_start(args, fmt);
    std::vfprintf(stderr, fmt, args);
    va_end(args);
    std::fputc('\n', stderr);
}

inline void atg_debug_hex(const char* label, const uint8_t* data, size_t len) {
    if (!atg_debug_enabled() || !data) return;
    char ts[32];
    atg_debug_timestamp(ts, sizeof(ts));
    std::fprintf(stderr, "[%s] [INFO] [ATG] %s (%zu bytes):", ts, label, len);
    for (size_t i = 0; i < len; ++i)
        std::fprintf(stderr, " %02X", data[i]);
    std::fputc('\n', stderr);
}

} // namespace atg
