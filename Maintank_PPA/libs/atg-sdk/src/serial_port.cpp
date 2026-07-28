#include "atg/serial_port.hpp"
#include <cstring>
#include <cerrno>
#include <system_error>
#include <unistd.h>
#include <fcntl.h>
#include <termios.h>
#include <sys/ioctl.h>
#include <poll.h>

namespace atg {
namespace {

speed_t to_posix_baud(Bps b) {
    switch (b) {
        case Bps::Rate0:      return B0;
        case Bps::Rate50:     return B50;
        case Bps::Rate75:     return B75;
        case Bps::Rate110:    return B110;
        case Bps::Rate134:    return B134;
        case Bps::Rate150:    return B150;
        case Bps::Rate200:    return B200;
        case Bps::Rate300:    return B300;
        case Bps::Rate600:    return B600;
        case Bps::Rate1200:   return B1200;
        case Bps::Rate1800:   return B1800;
        case Bps::Rate2400:   return B2400;
        case Bps::Rate4800:   return B4800;
        case Bps::Rate9600:   return B9600;
        case Bps::Rate19200:  return B19200;
        case Bps::Rate38400:  return B38400;
        case Bps::Rate57600:  return B57600;
        case Bps::Rate115200: return B115200;
        case Bps::Rate230400: return B230400;
    }
    return B9600;
}

int to_posix_parity(Parity p) {
    switch (p) {
        case Parity::None: return 0;
        case Parity::Even: return 1;
        case Parity::Odd:  return 2;
    }
    return 0;
}

} // anonymous namespace

SerialPort::SerialPort(const std::string& device) : device_(device) {}
SerialPort::~SerialPort() { close(); }

SerialPort::SerialPort(SerialPort&& other) noexcept
    : device_(std::move(other.device_)), fd_(other.fd_),
      baudrate_(other.baudrate_), parity_(other.parity_),
      data_bits_(other.data_bits_), stop_bits_(other.stop_bits_),
      read_timeout_(other.read_timeout_) {
    other.fd_ = -1;
}

SerialPort& SerialPort::operator=(SerialPort&& other) noexcept {
    if (this != &other) {
        close();
        device_ = std::move(other.device_);
        fd_ = other.fd_;
        baudrate_ = other.baudrate_;
        parity_ = other.parity_;
        data_bits_ = other.data_bits_;
        stop_bits_ = other.stop_bits_;
        read_timeout_ = other.read_timeout_;
        other.fd_ = -1;
    }
    return *this;
}

void SerialPort::configure(Bps baudrate, Parity parity, DataBits data_bits,
                           StopBits stop_bits, milliseconds read_timeout) {
    baudrate_ = baudrate;
    parity_ = parity;
    data_bits_ = data_bits;
    stop_bits_ = stop_bits;
    read_timeout_ = read_timeout;
}

void SerialPort::open() {
    if (fd_ >= 0) return;

    fd_ = ::open(device_.c_str(), O_RDWR | O_NOCTTY | O_NDELAY);
    if (fd_ < 0) {
        throw std::system_error(errno, std::generic_category(),
                                "SerialPort: failed to open " + device_);
    }

    struct termios tio;
    memset(&tio, 0, sizeof(tio));

    speed_t spd = to_posix_baud(baudrate_);
    cfsetospeed(&tio, spd);
    cfsetispeed(&tio, spd);

    tio.c_cflag |= CREAD | CLOCAL;

    switch (data_bits_) {
        case DataBits::D5: tio.c_cflag |= CS5; break;
        case DataBits::D6: tio.c_cflag |= CS6; break;
        case DataBits::D7: tio.c_cflag |= CS7; break;
        case DataBits::D8: tio.c_cflag |= CS8; break;
    }

    switch (parity_) {
        case Parity::None: tio.c_cflag &= ~PARENB; break;
        case Parity::Even: tio.c_cflag |= PARENB; tio.c_cflag &= ~PARODD; break;
        case Parity::Odd:  tio.c_cflag |= PARENB | PARODD; break;
    }

    switch (stop_bits_) {
        case StopBits::One: tio.c_cflag &= ~CSTOPB; break;
        case StopBits::Two: tio.c_cflag |= CSTOPB; break;
    }

    tio.c_iflag = IGNPAR;
    tio.c_oflag = 0;
    tio.c_lflag = 0;

    tio.c_cc[VMIN] = 0;
    tio.c_cc[VTIME] = 0;

    tcflush(fd_, TCIFLUSH);
    tcsetattr(fd_, TCSANOW, &tio);

    int flags = fcntl(fd_, F_GETFL, 0);
    fcntl(fd_, F_SETFL, flags & ~O_NDELAY);
}

void SerialPort::close() {
    if (fd_ >= 0) {
        ::close(fd_);
        fd_ = -1;
    }
}

bool SerialPort::is_open() const noexcept {
    return fd_ >= 0;
}

void SerialPort::flush_input() {
    if (fd_ >= 0)
        tcflush(fd_, TCIFLUSH);
}

ssize_t SerialPort::read(uint8_t* buf, size_t n) {
    if (fd_ < 0) return -1;

    int ms = read_timeout_.count();
    struct pollfd pfd;
    pfd.fd = fd_;
    pfd.events = POLLIN;

    ssize_t total = 0;
    while (total == 0) {
        int ret = poll(&pfd, 1, ms);
        if (ret < 0) return -1;
        if (ret == 0) break;

        ssize_t r = ::read(fd_, buf + total, n - total);
        if (r < 0) return -1;
        total += r;
    }
    if (total <= 0) return total;

    // ponytail: drain kernel buffer so multi-byte Modbus frames arrive in one poll cycle
    while (static_cast<size_t>(total) < n) {
        ssize_t r = ::read(fd_, buf + total, n - total);
        if (r <= 0) break;
        total += r;
    }
    return total;
}

ssize_t SerialPort::write(const uint8_t* buf, size_t n) {
    if (fd_ < 0) return -1;
    size_t total = 0;
    while (total < n) {
        ssize_t w = ::write(fd_, buf + total, n - total);
        if (w < 0) return -1;
        total += w;
    }
    tcdrain(fd_);
    return total;
}

void SerialPort::set_rts(bool on) {
    if (fd_ < 0) return;
    unsigned int flags = TIOCM_RTS;
    if (on)
        ioctl(fd_, TIOCMBIS, &flags);
    else
        ioctl(fd_, TIOCMBIC, &flags);
}

} // namespace atg
