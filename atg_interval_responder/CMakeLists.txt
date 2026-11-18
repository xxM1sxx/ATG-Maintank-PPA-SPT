cmake_minimum_required(VERSION 3.10)
project(atg-interval-responder LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

if(CMAKE_VERSION VERSION_GREATER_EQUAL 3.14)
    find_package(SQLite3 REQUIRED)
    set(ATG_SQLITE_LIBS SQLite::SQLite3)
else()
    find_library(SQLITE3_LIBRARY NAMES sqlite3 REQUIRED)
    find_path(SQLITE3_INCLUDE_DIR NAMES sqlite3.h REQUIRED)
    set(ATG_SQLITE_LIBS ${SQLITE3_LIBRARY})
endif()

# Beda dari atg-reader: tanpa libmosquitto program ini tidak ada gunanya, jadi
# gagal di cmake, bukan gagal saat runtime.
find_library(MOSQUITTO_LIBRARY NAMES mosquitto)
if(NOT MOSQUITTO_LIBRARY)
    message(FATAL_ERROR "libmosquitto not found - sudo apt-get install libmosquitto-dev")
endif()

add_executable(atg-interval-responder src/main.cpp)
target_include_directories(atg-interval-responder PRIVATE
    ${CMAKE_CURRENT_SOURCE_DIR}/../Maintank_PPA/libs/atg-sdk/include)
if(SQLITE3_INCLUDE_DIR)
    target_include_directories(atg-interval-responder PRIVATE ${SQLITE3_INCLUDE_DIR})
endif()
target_link_libraries(atg-interval-responder PRIVATE
    ${ATG_SQLITE_LIBS} ${MOSQUITTO_LIBRARY} pthread)
target_compile_options(atg-interval-responder PRIVATE -Wall -Wextra)

install(TARGETS atg-interval-responder RUNTIME DESTINATION bin)
