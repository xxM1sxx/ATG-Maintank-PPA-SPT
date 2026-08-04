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
    std::snprintf(buf, buf_len, "%02d:%02d:%02d.%03d",
                  tm.tm_hour, tm.tm_min, tm.tm_sec, static_cast<int>(ms));
}

// Centralized, timestamped debug line: "[HH:MM:SS.mmm] <tag>: <message>".
// No-op when ATG_DEBUG=0. `tag` distinguishes SDK-internal frames ("atg-debug")
// from app-level status ("atg-reader") without duplicating the getenv check
// at every call site.
inline void atg_debug_log(const char* tag, const char* fmt, ...) {
    if (!atg_debug_enabled()) return;
    char ts[16];
    atg_debug_timestamp(ts, sizeof(ts));
    std::fprintf(stderr, "[%s] %s: ", ts, tag);
    va_list args;
    va_start(args, fmt);
    std::vfprintf(stderr, fmt, args);
    va_end(args);
    std::fputc('\n', stderr);
}

inline void atg_debug_hex(const char* label, const uint8_t* data, size_t len) {
    if (!atg_debug_enabled() || !data) return;
    char ts[16];
    atg_debug_timestamp(ts, sizeof(ts));
    std::fprintf(stderr, "[%s] atg-debug: %s (%zu bytes):", ts, label, len);
    for (size_t i = 0; i < len; ++i)
        std::fprintf(stderr, " %02X", data[i]);
    std::fputc('\n', stderr);
}

} // namespace atg
