#pragma once

#include <vector>

namespace atg_app {

struct VolumePoint {
    int sounding_mm = 0;
    double volume_l = 0;
};

// sounding_mm = probe_mm + offset_mm; returns liters; -1 if no points loaded.
double lookup_volume_liters(double probe_mm, double offset_mm,
                            const std::vector<VolumePoint>& points);

} // namespace atg_app
