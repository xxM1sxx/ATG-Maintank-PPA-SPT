#pragma once

#include <cstddef>
#include <cstdint>
#include <cstdlib>
#include <cstdio>

namespace atg {

inline bool atg_debug_enabled() {
    const char* v = std::getenv("ATG_DEBUG");
    // Default ON (Modbus hex, parsed values, status logs always visible).
    // Set ATG_DEBUG=0 to silence.
    return !(v && v[0] == '0' && v[1] == '\0');
}

inline void atg_debug_hex(const char* label, const uint8_t* data, size_t len) {
    if (!atg_debug_enabled() || !data) return;
    std::fprintf(stderr, "atg-debug: %s (%zu bytes):", label, len);
    for (size_t i = 0; i < len; ++i)
        std::fprintf(stderr, " %02X", data[i]);
    std::fputc('\n', stderr);
}

} // namespace atg
