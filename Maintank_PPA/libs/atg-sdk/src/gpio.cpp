#include "atg/gpio.hpp"
#include <cstring>
#include <cerrno>
#include <fstream>
#include <sstream>
#include <system_error>
#include <unistd.h>
#include <fcntl.h>

namespace atg {

static const char* SYSFS_GPIO = "/sys/class/gpio";

GpioPin::GpioPin(int gpio_number) : gpio_(gpio_number) {
    if (gpio_ < 0) return;
    if (!is_exported(gpio_)) export_pin(gpio_);

    dir_path_ = std::string(SYSFS_GPIO) + "/gpio" + std::to_string(gpio_);

    std::string value_path = dir_path_ + "/value";
    fd_value_ = ::open(value_path.c_str(), O_RDWR);
    if (fd_value_ < 0) {
        throw std::system_error(errno, std::generic_category(),
                                "GpioPin: failed to open " + value_path);
    }
}

GpioPin::~GpioPin() {
    if (fd_value_ >= 0) ::close(fd_value_);
}

GpioPin::GpioPin(GpioPin&& other) noexcept
    : gpio_(other.gpio_), fd_value_(other.fd_value_),
      dir_path_(std::move(other.dir_path_)) {
    other.gpio_ = -1;
    other.fd_value_ = -1;
}

GpioPin& GpioPin::operator=(GpioPin&& other) noexcept {
    if (this != &other) {
        if (fd_value_ >= 0) ::close(fd_value_);
        gpio_ = other.gpio_;
        fd_value_ = other.fd_value_;
        dir_path_ = std::move(other.dir_path_);
        other.gpio_ = -1;
        other.fd_value_ = -1;
    }
    return *this;
}

void GpioPin::set(bool high) {
    if (fd_value_ < 0) return;
    const char* val = high ? "1" : "0";
    if (::write(fd_value_, val, 1) < 1) {
        throw std::system_error(errno, std::generic_category(),
                                "GpioPin: write failed");
    }
}

bool GpioPin::get() const {
    if (fd_value_ < 0) return false;
    char val = 0;
    if (::lseek(fd_value_, 0, SEEK_SET) < 0) return false;
    if (::read(fd_value_, &val, 1) < 1) return false;
    return val == '1';
}

bool GpioPin::is_exported(int gpio_number) {
    std::string path = std::string(SYSFS_GPIO) + "/gpio" + std::to_string(gpio_number);
    return access(path.c_str(), F_OK) == 0;
}

void GpioPin::export_pin(int gpio_number) {
    std::ofstream exp(std::string(SYSFS_GPIO) + "/export");
    if (!exp) {
        throw std::system_error(errno, std::generic_category(),
                                "GpioPin: export failed");
    }
    exp << gpio_number << "\n";

    std::string dir_path = std::string(SYSFS_GPIO) + "/gpio" + std::to_string(gpio_number) + "/direction";
    std::ofstream dir(dir_path);
    if (dir) dir << "out\n";
}

void GpioPin::unexport_pin(int gpio_number) {
    std::ofstream unexp(std::string(SYSFS_GPIO) + "/unexport");
    if (unexp) unexp << gpio_number << "\n";
}

} // namespace atg
