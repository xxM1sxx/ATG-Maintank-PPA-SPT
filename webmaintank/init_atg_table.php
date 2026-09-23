<?php
function ensure_atg_tables($conn) {
    // This function issues ~20 separate writes (CREATE/ALTER/INSERT). Left as
    // individual autocommit statements, each one is its own SQLite transaction
    // with its own fsync - on slow eMMC/SD storage that's ~20 disk syncs on
    // every single page load and every save, easily adding up to seconds. Wrap
    // it all in one transaction so it's a single fsync instead.
    $own_transaction = !$conn->inTransaction();
    if ($own_transaction) {
        $conn->beginTransaction();
    }
    try {
        ensure_atg_tables_inner($conn);
        if ($own_transaction) {
            $conn->commit();
        }
    } catch (Exception $e) {
        if ($own_transaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function ensure_atg_tables_inner($conn) {
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
        'offset_mm_water REAL NOT NULL DEFAULT 0',
        'slocn TEXT NOT NULL DEFAULT \'FS-01\'',
        'sensor_number TEXT NOT NULL DEFAULT \'01\'',
        'capacity_liters REAL NOT NULL DEFAULT 40000.0',
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

    foreach (array('water_mm REAL', 'fuel_temp_c REAL', 'volume_water_l REAL', 'volume_total_l REAL', 'offset_water REAL') as $col) {
        try {
            $conn->exec("ALTER TABLE tbl_atg_reading ADD COLUMN $col");
        } catch (PDOException $e) {
            // column already exists
        }
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS tbl_atg_mqtt_setting (
        id INTEGER PRIMARY KEY,
        broker_host TEXT NOT NULL DEFAULT '',
        broker_port INTEGER NOT NULL DEFAULT 1883,
        username TEXT NOT NULL DEFAULT '',
        password TEXT NOT NULL DEFAULT '',
        topic_path TEXT NOT NULL DEFAULT 'maintank/atg/value',
        publish_interval_sec INTEGER NOT NULL DEFAULT 30,
        company_name TEXT NOT NULL DEFAULT 'PT. Putra Perkasa Abadi',
        plant_id TEXT NOT NULL DEFAULT 'PPA-BA',
        enabled INTEGER NOT NULL DEFAULT 1
    )");

    // Ensure one row exists in mqtt settings
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM tbl_atg_mqtt_setting");
        $count = $stmt->fetchColumn();
        if ($count == 0) {
            $conn->exec("INSERT INTO tbl_atg_mqtt_setting (id) VALUES (1)");
        }
    } catch (PDOException $e) {
        // ignore
    }

    // Cloud MQTT: reuses the same broker/credentials as tbl_atg_mqtt_setting above,
    // just adds its own topic(s)/interval/enabled columns to that singleton row.
    foreach (array(
        "cloud_topic_path TEXT NOT NULL DEFAULT ''",
        "cloud_request_topic TEXT NOT NULL DEFAULT ''",
        "cloud_publish_interval_sec INTEGER NOT NULL DEFAULT 120",
        "cloud_enabled INTEGER NOT NULL DEFAULT 0",
        "cloud_store_on_fail INTEGER NOT NULL DEFAULT 1",
    ) as $col) {
        try {
            $conn->exec("ALTER TABLE tbl_atg_mqtt_setting ADD COLUMN $col");
        } catch (PDOException $e) {
            // column already exists
        }
    }

    // Pending/failed-queue for cloud publishes that didn't get a PUBACK, and the
    // batches replayed on request.
    $conn->exec("CREATE TABLE IF NOT EXISTS tbl_atg_cloud_pending (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payload TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Clean up stray rows beyond 8-channel schema
    try {
        $conn->exec("DELETE FROM tbl_atg_reading WHERE sensor_id > 8");
        $conn->exec("DELETE FROM tbl_atg_volume_point WHERE sensor_id > 8");
        $conn->exec("DELETE FROM tbl_atg_sensor WHERE id > 8");
    } catch (PDOException $e) {
        // ignore
    }

    // Seed any missing sensor rows 1-8 (fresh install: none exist yet; older
    // installs upgraded from a CHECK(1..6) schema: only 7-8 are missing).
    // INSERT OR IGNORE is idempotent - safe to run unconditionally every time,
    // unlike a COUNT(*)==0 gate which breaks if some rows already exist.
    try {
        $conn->exec("PRAGMA ignore_check_constraints = ON");
        for ($i = 1; $i <= 8; $i++) {
            $label = "TANKI-$i";
            $device = "/dev/ttyACM" . ($i - 1);
            $insert = $conn->prepare(
                "INSERT OR IGNORE INTO tbl_atg_sensor (id, label, enabled, device) VALUES (:id, :label, 0, :device)"
            );
            $insert->bindParam(':id', $i, PDO::PARAM_INT);
            $insert->bindParam(':label', $label);
            $insert->bindParam(':device', $device);
            $insert->execute();
        }
    } catch (PDOException $e) {
        // ignore
    } finally {
        try { $conn->exec("PRAGMA ignore_check_constraints = OFF"); } catch (PDOException $e) {}
    }
}
