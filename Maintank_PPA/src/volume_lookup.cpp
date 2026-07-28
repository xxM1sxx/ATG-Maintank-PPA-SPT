#include "volume_lookup.hpp"
#include <algorithm>
#include <cassert>
#include <cmath>

namespace atg_app {

double lookup_volume_liters(double probe_mm, double offset_mm,
                            const std::vector<VolumePoint>& points) {
    if (points.empty())
        return -1;

    const double h = probe_mm + offset_mm;
    if (h <= static_cast<double>(points.front().sounding_mm))
        return points.front().volume_l;

    if (h >= static_cast<double>(points.back().sounding_mm))
        return points.back().volume_l;

    const auto it = std::upper_bound(
        points.begin(), points.end(), h,
        [](double val, const VolumePoint& p) { return val < static_cast<double>(p.sounding_mm); });

    if (it == points.begin() || it == points.end())
        return -1;

    const VolumePoint& hi = *it;
    const VolumePoint& lo = *(it - 1);
    const double span = static_cast<double>(hi.sounding_mm - lo.sounding_mm);
    if (span <= 0)
        return lo.volume_l;

    const double t = (h - lo.sounding_mm) / span;
    return lo.volume_l + t * (hi.volume_l - lo.volume_l);
}

namespace {
struct VolumeLookupSelfCheck {
    VolumeLookupSelfCheck() {
        const std::vector<VolumePoint> pts{{0, 0}, {100, 500}, {200, 1000}};
        assert(lookup_volume_liters(50, 0, pts) == 250);
        assert(lookup_volume_liters(250, 0, pts) == 1000);
        assert(lookup_volume_liters(50, 10, pts) == 300);
    }
} volume_lookup_self_check;
} // namespace

} // namespace atg_app
