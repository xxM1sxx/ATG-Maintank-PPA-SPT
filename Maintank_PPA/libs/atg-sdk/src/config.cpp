#include "atg/config.hpp"
#include <cstring>
#include <cstdlib>
#include <cstdio>
#include <fstream>
#include <sstream>

namespace atg {

AtgConfig AtgConfig::defaults() {
    return AtgConfig{};
}

AtgConfig AtgConfig::from_args(int argc, char* argv[]) {
    AtgConfig cfg;
    for (int i = 1; i < argc; ++i) {
        if (strcmp(argv[i], "--device") == 0 && i + 1 < argc)
            cfg.device = argv[++i];
        else if (strcmp(argv[i], "--baud") == 0 && i + 1 < argc)
            cfg.baudrate = static_cast<Bps>(std::atoi(argv[++i]));
        else if (strcmp(argv[i], "--slave-id") == 0 && i + 1 < argc)
            cfg.slave_id = static_cast<uint8_t>(std::atoi(argv[++i]));
        else if (strcmp(argv[i], "--de-gpio") == 0 && i + 1 < argc)
            cfg.de_gpio_pin = std::atoi(argv[++i]);
        else if (strcmp(argv[i], "--timeout") == 0 && i + 1 < argc)
            cfg.read_timeout = milliseconds(std::atoi(argv[++i]));
        else if (strcmp(argv[i], "--interval") == 0 && i + 1 < argc)
            cfg.polling_interval_ms = std::atoi(argv[++i]);
    }
    return cfg;
}

AtgConfig AtgConfig::from_file(const char* path) {
    AtgConfig cfg;
    std::ifstream f(path);
    if (!f) return cfg;

    std::string line;
    while (std::getline(f, line)) {
        if (line.empty() || line[0] == '#') continue;

        auto eq = line.find('=');
        if (eq == std::string::npos) continue;

        std::string key = line.substr(0, eq);
        std::string val = line.substr(eq + 1);

        auto trim = [](std::string& s) {
            s.erase(0, s.find_first_not_of(" \t\r"));
            s.erase(s.find_last_not_of(" \t\r") + 1);
        };
        trim(key);
        trim(val);

        if (key == "device") cfg.device = val;
        else if (key == "baudrate") cfg.baudrate = static_cast<Bps>(std::stoi(val));
        else if (key == "slave_id") cfg.slave_id = static_cast<uint8_t>(std::stoi(val));
        else if (key == "de_gpio") cfg.de_gpio_pin = std::stoi(val);
        else if (key == "timeout_ms") cfg.read_timeout = milliseconds(std::stoi(val));
        else if (key == "interval_ms") cfg.polling_interval_ms = std::stoi(val);
        else if (key == "start_register") cfg.start_register = static_cast<uint16_t>(std::stoi(val));
        else if (key == "register_count") cfg.register_count = static_cast<uint16_t>(std::stoi(val));
    }
    return cfg;
}

} // namespace atg
