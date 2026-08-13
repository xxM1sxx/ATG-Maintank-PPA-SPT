<?php
/**
 * Import calibration CSV into tbl_atg_volume_point.
 * Format: sounding_mm,volume_l (header optional)
 */

function import_calibration_csv($conn, $sensor_id, $csv_path) {
    $sensor_id = intval($sensor_id);
    if ($sensor_id < 1 || $sensor_id > 8) {
        throw new InvalidArgumentException('sensor_id must be 1..8');
    }
    if (!is_readable($csv_path)) {
        throw new RuntimeException('Cannot read CSV: ' . $csv_path);
    }

    $fh = fopen($csv_path, 'r');
    if ($fh === false) {
        throw new RuntimeException('Cannot open CSV: ' . $csv_path);
    }

    $rows = array();
    $line_no = 0;
    while (($line = fgets($fh)) !== false) {
        $line_no++;
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if ($line_no === 1 && stripos($line, 'sounding') !== false) {
            continue;
        }
        $parts = str_getcsv($line);
        if (count($parts) < 2) {
            continue;
        }
        $sounding = intval($parts[0]);
        $volume = floatval(str_replace(',', '', $parts[1]));
        if ($sounding < 0 || $volume < 0) {
            continue;
        }
        $rows[] = array($sensor_id, $sounding, $volume);
    }
    fclose($fh);

    if (empty($rows)) {
        throw new RuntimeException('No valid rows in CSV');
    }

    $conn->beginTransaction();
    try {
        $del = $conn->prepare('DELETE FROM tbl_atg_volume_point WHERE sensor_id = ?');
        $del->execute(array($sensor_id));

        $ins = $conn->prepare(
            'INSERT INTO tbl_atg_volume_point (sensor_id, sounding_mm, volume_l) VALUES (?, ?, ?)'
        );
        foreach ($rows as $row) {
            $ins->execute($row);
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

    return count($rows);
}

if (php_sapi_name() === 'cli') {
    if ($argc < 3) {
        fwrite(STDERR, "Usage: php import_calibration.php <sensor_id 1-8> <csv_path>\n");
        exit(1);
    }
    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/init_atg_table.php';
    ensure_atg_tables($conn);
    $count = import_calibration_csv($conn, intval($argv[1]), $argv[2]);
    echo "Imported $count points for sensor {$argv[1]}\n";
}
