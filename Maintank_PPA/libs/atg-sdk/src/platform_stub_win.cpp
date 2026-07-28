// ponytail: WIN32 stubs so unit tests build without termios/sysfs; BBB uses real sources.
#include "atg/serial_port.hpp"
#include "atg/gpio.hpp"
#include <utility>

namespace atg {

SerialPort::SerialPort(const std::string& device) : device_(device) {}
SerialPort::~SerialPort() { close(); }
SerialPort::SerialPort(SerialPort&& other) noexcept
    : device_(std::move(other.device_)), fd_(other.fd_) {
    other.fd_ = -1;
}
SerialPort& SerialPort::operator=(SerialPort&& other) noexcept {
    if (this != &other) {
        close();
        device_ = std::move(other.device_);
        fd_ = other.fd_;
        other.fd_ = -1;
    }
    return *this;
}
void SerialPort::configure(Bps, Parity, DataBits, StopBits, milliseconds) {}
void SerialPort::open() {}
void SerialPort::close() { fd_ = -1; }
bool SerialPort::is_open() const noexcept { return false; }
ssize_t SerialPort::read(uint8_t*, size_t) { return -1; }
ssize_t SerialPort::write(const uint8_t*, size_t len) { return static_cast<ssize_t>(len); }
void SerialPort::flush_input() {}
void SerialPort::set_rts(bool) {}

GpioPin::GpioPin(int gpio_number) : gpio_(gpio_number) {}
GpioPin::~GpioPin() = default;
GpioPin::GpioPin(GpioPin&& other) noexcept : gpio_(other.gpio_) { other.gpio_ = -1; }
GpioPin& GpioPin::operator=(GpioPin&& other) noexcept {
    gpio_ = other.gpio_;
    other.gpio_ = -1;
    return *this;
}
void GpioPin::set(bool) {}
bool GpioPin::get() const { return false; }
bool GpioPin::is_exported(int) { return false; }
void GpioPin::export_pin(int) {}
void GpioPin::unexport_pin(int) {}

} // namespace atg
