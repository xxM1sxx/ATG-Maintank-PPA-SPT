project(atg-sdk VERSION 1.0.0 LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_POSITION_INDEPENDENT_CODE ON)

add_library(atg-sdk
    src/modbus.cpp
    src/atg_protocol.cpp
    src/config.cpp
)

if(WIN32)
    target_sources(atg-sdk PRIVATE src/platform_stub_win.cpp)
else()
    target_sources(atg-sdk PRIVATE
        src/serial_port.cpp
        src/gpio.cpp
        src/sensor_manager.cpp
    )
endif()

target_include_directories(atg-sdk PUBLIC include)

option(ATG_BUILD_TESTS "Build tests" OFF)
option(ATG_BUILD_EXAMPLES "Build examples" OFF)

if(ATG_BUILD_TESTS)
    enable_testing()
    add_subdirectory(tests)
endif()

if(ATG_BUILD_EXAMPLES)
    add_subdirectory(examples)
endif()
