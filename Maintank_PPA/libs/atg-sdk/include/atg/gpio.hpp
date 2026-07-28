#pragma once

#include <string>

namespace atg {

class GpioPin {
public:
    explicit GpioPin(int gpio_number);
    ~GpioPin();

    GpioPin(const GpioPin&) = delete;
    GpioPin& operator=(const GpioPin&) = delete;
    GpioPin(GpioPin&& other) noexcept;
    GpioPin& operator=(GpioPin&& other) noexcept;

    void set(bool high);
    bool get() const;

    int gpio_number() const noexcept { return gpio_; }
    bool is_valid() const noexcept { return gpio_ >= 0; }

    static bool is_exported(int gpio_number);
    static void export_pin(int gpio_number);
    static void unexport_pin(int gpio_number);

private:
    int gpio_ = -1;
    int fd_value_ = -1;
    std::string dir_path_;
};

} // namespace atg
