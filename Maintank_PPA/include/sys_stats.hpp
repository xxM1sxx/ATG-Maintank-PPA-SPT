#pragma once

namespace atg_app {

// Reads /proc/meminfo (MemTotal, MemAvailable) and returns used-memory
// percentage in [0, 100]. Returns -1.0 if the file can't be read/parsed.
double read_used_memory_percent();

} // namespace atg_app
