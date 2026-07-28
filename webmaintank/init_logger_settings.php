<?php
function ensure_logger_settings_columns($conn) {
    foreach (array(
        'schedule_reboot_enable INTEGER NOT NULL DEFAULT 0',
        'syslog_rotate_count INTEGER NOT NULL DEFAULT 7',
        'syslog_rotate_frequency TEXT NOT NULL DEFAULT \'daily\'',
        'syslog_max_size TEXT NOT NULL DEFAULT \'\'',
        'syslog_compress INTEGER NOT NULL DEFAULT 1',
        'syslog_delaycompress INTEGER NOT NULL DEFAULT 1',
        'syslog_missingok INTEGER NOT NULL DEFAULT 1',
        'syslog_notifempty INTEGER NOT NULL DEFAULT 1',
    ) as $col) {
        try {
            $conn->exec("ALTER TABLE logger_settings ADD COLUMN $col");
        } catch (PDOException $e) {
            // column already exists
        }
    }
}
