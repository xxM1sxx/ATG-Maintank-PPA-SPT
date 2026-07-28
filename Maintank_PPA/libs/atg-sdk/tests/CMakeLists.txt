get_filename_component(PPA_ROOT "${CMAKE_CURRENT_SOURCE_DIR}/../../.." ABSOLUTE)

add_executable(test_modbus test_modbus.cpp)
target_link_libraries(test_modbus PRIVATE atg-sdk)

if(EXISTS "${PPA_ROOT}/src/volume_lookup.cpp")
    target_sources(test_modbus PRIVATE ${PPA_ROOT}/src/volume_lookup.cpp)
    target_include_directories(test_modbus PRIVATE ${PPA_ROOT}/include)
endif()

add_test(NAME modbus COMMAND test_modbus)
