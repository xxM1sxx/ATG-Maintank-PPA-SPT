#pragma once

#include "types.hpp"
#include <string>
#include <chrono>

namespace atg {

class SerialPort {
public:
    explicit SerialPort(const std::string& device);
    ~SerialPort();

    SerialPort(const SerialPort&) = delete;
    SerialPort& operator=(const SerialPort&) = delete;
    SerialPort(SerialPort&& other) noexcept;
    SerialPort& operator=(SerialPort&& other) noexcept;

    void configure(Bps baudrate, Parity parity = Parity::None,
                   DataBits data_bits = DataBits::D8, StopBits stop_bits = StopBits::One,
                   milliseconds read_timeout = milliseconds(3000));
    void open();
    void close();
    bool is_open() const noexcept;

    ssize_t read(uint8_t* buf, size_t n);
    ssize_t write(const uint8_t* buf, size_t n);

    void flush_input();
    void set_rts(bool on);

    int native_handle() const noexcept { return fd_; }

private:
    std::string device_;
    int fd_ = -1;
    Bps baudrate_ = Bps::Rate9600;
    Parity parity_ = Parity::None;
    DataBits data_bits_ = DataBits::D8;
    StopBits stop_bits_ = StopBits::One;
    milliseconds read_timeout_ = milliseconds(3000);
};

} // namespace atg
