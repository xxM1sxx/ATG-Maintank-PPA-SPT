cmake_minimum_required(VERSION 3.10)
project(Maintak_PPA LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

# GCC 7+ emits noisy -Wpsabi notes for std::vector with non-trivial members (e.g. std::string in SensorConfig)
if(CMAKE_CXX_COMPILER_ID STREQUAL "GNU")
    add_compile_options(-Wno-psabi)
endif()

add_subdirectory(libs/atg-sdk)

option(ATG_BUILD_READER "Build atg-reader daemon" ON)
if(ATG_BUILD_READER)
    if(CMAKE_VERSION VERSION_GREATER_EQUAL 3.14)
        find_package(SQLite3 REQUIRED)
        set(ATG_SQLITE_LIBS SQLite::SQLite3)
    else()
        find_library(SQLITE3_LIBRARY NAMES sqlite3 REQUIRED)
        find_path(SQLITE3_INCLUDE_DIR NAMES sqlite3.h REQUIRED)
        set(ATG_SQLITE_LIBS ${SQLITE3_LIBRARY})
    endif()

    add_executable(atg-reader
        src/main.cpp
        src/atg_db.cpp
        src/volume_lookup.cpp
    )
    target_include_directories(atg-reader PRIVATE include)
    if(SQLITE3_INCLUDE_DIR)
        target_include_directories(atg-reader PRIVATE ${SQLITE3_INCLUDE_DIR})
    endif()
    target_link_libraries(atg-reader PRIVATE atg-sdk ${ATG_SQLITE_LIBS} pthread)
    target_compile_options(atg-reader PRIVATE -Wall -Wextra)

    install(TARGETS atg-reader RUNTIME DESTINATION bin)
endif()
