#include "sys_stats.hpp"
#include <fstream>
#include <string>
#include <cstdlib>

namespace atg_app {

double read_used_memory_percent() {
    std::ifstream file("/proc/meminfo");
    if (!file.is_open()) {
        return -1.0;
    }

    long mem_total_kb = -1;
    long mem_available_kb = -1;
    std::string line;

    while (std::getline(file, line)) {
        if (line.rfind("MemTotal:", 0) == 0) {
            mem_total_kb = std::strtol(line.c_str() + 9, nullptr, 10);
        } else if (line.rfind("MemAvailable:", 0) == 0) {
            mem_available_kb = std::strtol(line.c_str() + 13, nullptr, 10);
        }
        if (mem_total_kb >= 0 && mem_available_kb >= 0) {
            break;
        }
    }

    if (mem_total_kb <= 0 || mem_available_kb < 0) {
        return -1.0;
    }

    return 100.0 * static_cast<double>(mem_total_kb - mem_available_kb) / static_cast<double>(mem_total_kb);
}

} // namespace atg_app
