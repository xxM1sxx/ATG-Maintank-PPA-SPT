<?php
session_start();

if (empty($_SESSION['username'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'conn.php';
require_once 'init_atg_table.php';
ensure_atg_tables($conn);

header('Content-Type: application/json');

$query = "SELECT s.id, s.label, s.enabled, s.device, s.slave_id, s.baudrate, s.polling_interval_ms,
                 s.offset_mm,
                 r.position_mm, r.volume, r.water_mm, r.fuel_temp_c, r.offset AS reading_offset,
                 r.ok, r.error_msg, r.updated_at
          FROM tbl_atg_sensor s
          LEFT JOIN tbl_atg_reading r ON r.sensor_id = s.id
          WHERE s.id BETWEEN 1 AND 6
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

    $offset_mm = isset($row['offset_mm']) ? floatval($row['offset_mm']) : 0.0;
    $row['offset_mm'] = $offset_mm;

    if ($row['position_mm'] !== null) {
        $probe_mm = floatval($row['position_mm']);
        $row['probe_mm'] = $probe_mm;
        $row['sounding_mm'] = $probe_mm + $offset_mm;
    } else {
        $row['probe_mm'] = null;
        $row['sounding_mm'] = null;
    }

    if ($row['volume'] !== null) $row['volume'] = floatval($row['volume']);
    if ($row['water_mm'] !== null) $row['water_mm'] = floatval($row['water_mm']);
    if ($row['fuel_temp_c'] !== null) $row['fuel_temp_c'] = floatval($row['fuel_temp_c']);
    if ($row['reading_offset'] !== null) $row['reading_offset'] = floatval($row['reading_offset']);
}
unset($row);

echo json_encode($rows);
