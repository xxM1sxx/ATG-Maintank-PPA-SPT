<?php
function ensure_atg_tables($conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS tbl_atg_sensor (
        id INTEGER PRIMARY KEY CHECK (id BETWEEN 1 AND 8),
        label TEXT NOT NULL DEFAULT '',
        enabled INTEGER NOT NULL DEFAULT 0,
        device TEXT NOT NULL DEFAULT '',
        slave_id INTEGER NOT NULL DEFAULT 1,
        baudrate INTEGER NOT NULL DEFAULT 9600,
        polling_interval_ms INTEGER NOT NULL DEFAULT 5000,
        de_gpio_pin INTEGER NOT NULL DEFAULT -1,
        read_timeout_ms INTEGER NOT NULL DEFAULT 3000,
        zero_offset_mm REAL NOT NULL DEFAULT 0,
        offset_mm REAL NOT NULL DEFAULT 0
    )");

    foreach (array(
        'zero_offset_mm REAL NOT NULL DEFAULT 0',
        'offset_mm REAL NOT NULL DEFAULT 0',
    ) as $col) {
        try {
            $conn->exec("ALTER TABLE tbl_atg_sensor ADD COLUMN $col");
        } catch (PDOException $e) {
            // column already exists
        }
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS tbl_atg_volume_point (
        sensor_id INTEGER NOT NULL,
        sounding_mm INTEGER NOT NULL,
        volume_l REAL NOT NULL,
        PRIMARY KEY (sensor_id, sounding_mm),
        FOREIGN KEY (sensor_id) REFERENCES tbl_atg_sensor(id)
    )");

    $conn->exec("CREATE INDEX IF NOT EXISTS idx_volume_point_sensor
        ON tbl_atg_volume_point(sensor_id, sounding_mm)");

    $conn->exec("CREATE TABLE IF NOT EXISTS tbl_atg_reading (
        sensor_id INTEGER PRIMARY KEY,
        position_mm REAL,
        volume REAL,
        water_mm REAL,
        fuel_temp_c REAL,
        offset REAL,
        ok INTEGER NOT NULL DEFAULT 0,
        error_msg TEXT,
        updated_at TEXT,
        FOREIGN KEY (sensor_id) REFERENCES tbl_atg_sensor(id)
    )");

    foreach (array('water_mm REAL', 'fuel_temp_c REAL') as $col) {
        try {
            $conn->exec("ALTER TABLE tbl_atg_reading ADD COLUMN $col");
        } catch (PDOException $e) {
            // column already exists
        }
    }

    // Clean up stray rows beyond 8-channel schema
    try {
        $conn->exec("DELETE FROM tbl_atg_reading WHERE sensor_id > 8");
        $conn->exec("DELETE FROM tbl_atg_volume_point WHERE sensor_id > 8");
        $conn->exec("DELETE FROM tbl_atg_sensor WHERE id > 8");
    } catch (PDOException $e) {
        // ignore
    }

    // Seed sensors 7-8 into existing tables that still have old CHECK(1..6) constraint
    try {
        $conn->exec("PRAGMA ignore_check_constraints = ON");
        $conn->exec("INSERT OR IGNORE INTO tbl_atg_sensor (id, label, device) VALUES (7, 'TANKI-7', '/dev/ttyACM6'), (8, 'TANKI-8', '/dev/ttyACM7')");
    } catch (PDOException $e) {
        // ignore
    } finally {
        try { $conn->exec("PRAGMA ignore_check_constraints = OFF"); } catch (PDOException $e) {}
    }

    $stmt = $conn->query("SELECT COUNT(*) FROM tbl_atg_sensor");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        for ($i = 1; $i <= 8; $i++) {
            $label = "TANKI-$i";
            $device = "/dev/ttyACM" . ($i - 1);
            $insert = $conn->prepare(
                "INSERT INTO tbl_atg_sensor (id, label, enabled, device) VALUES (:id, :label, 0, :device)"
            );
            $insert->bindParam(':id', $i, PDO::PARAM_INT);
            $insert->bindParam(':label', $label);
            $insert->bindParam(':device', $device);
            $insert->execute();
        }
    }
}
