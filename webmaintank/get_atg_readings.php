<?php
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'conn.php';

// No ensure_atg_tables() here on purpose - this endpoint is polled every 5s by
// the Status page's live-update JS, and the schema is already guaranteed to
// exist by the time this runs (home.php, which embeds the JS that polls this,
// ensures it on its own load). Re-running the ~20-statement migration check
// every 5 seconds for the lifetime of an open browser tab is pure waste.
header('Content-Type: application/json');

$query = "SELECT s.id, s.label, s.enabled, s.device, s.slave_id, s.baudrate, s.polling_interval_ms,
                 s.offset_mm, s.offset_mm_water,
                 r.position_mm, r.volume, r.water_mm, r.fuel_temp_c, r.offset AS reading_offset,
                 r.volume_water_l,
                 r.ok, r.error_msg, r.updated_at
          FROM tbl_atg_sensor s
          LEFT JOIN tbl_atg_reading r ON r.sensor_id = s.id
          WHERE s.id BETWEEN 1 AND 8
          ORDER BY s.id";

$stmt = $conn->query($query);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$row) {
    $row['id'] = intval($row['id']);
    $row['enabled'] = intval($row['enabled']);
    $row['slave_id'] = intval($row['slave_id']);
    $row['baudrate'] = intval($row['baudrate']);
    $row['polling_interval_ms'] = intval($row['polling_interval_ms']);
    $row['ok'] = isset($row['ok']) ? intval($row['ok']) : 0;

    $offset_fuel_mm = isset($row['offset_mm']) ? floatval($row['offset_mm']) : 0.0;
    $offset_water_mm = isset($row['offset_mm_water']) ? floatval($row['offset_mm_water']) : 0.0;
    $row['offset_fuel_mm'] = $offset_fuel_mm;
    $row['offset_water_mm'] = $offset_water_mm;
    unset($row['offset_mm'], $row['offset_mm_water']);

    if ($row['position_mm'] !== null) {
        $fuel_mm = floatval($row['position_mm']);
        $row['fuel_mm'] = $fuel_mm;
        $row['sounding_fuel_mm'] = $fuel_mm + $offset_fuel_mm;
    } else {
        $row['fuel_mm'] = null;
        $row['sounding_fuel_mm'] = null;
    }
    unset($row['position_mm']);

    if ($row['water_mm'] !== null) {
        $water_mm = floatval($row['water_mm']);
        $row['water_mm'] = $water_mm;
        $row['sounding_water_mm'] = $water_mm + $offset_water_mm;
    } else {
        $row['sounding_water_mm'] = null;
    }

    if ($row['volume'] !== null) $row['volume'] = floatval($row['volume']);
    if ($row['fuel_temp_c'] !== null) $row['fuel_temp_c'] = floatval($row['fuel_temp_c']);
    if ($row['reading_offset'] !== null) $row['reading_offset'] = floatval($row['reading_offset']);
    if ($row['volume_water_l'] !== null) $row['volume_water_l'] = floatval($row['volume_water_l']);
}
unset($row);

echo json_encode($rows);
