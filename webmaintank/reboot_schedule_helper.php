<?php
function update_reboot_schedule_cron_config($enabled, $timeValue)
{
    $cronFile = '/home/user/Apps/qims-scheduled-reboot';
    $cronTarget = '/etc/cron.d/qims-scheduled-reboot';

    if (!$enabled) {
        $cronContent = "# Scheduled reboot disabled\n";
    } else {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $timeValue, $matches)) {
            throw new Exception('Format jam schedule reboot tidak valid. Gunakan HH:MM.');
        }

        $hour = $matches[1];
        $minute = $matches[2];
        $cronContent = "# Managed by WebQimsAdmin\n";
        $cronContent .= $minute . ' ' . $hour . " * * * root /sbin/reboot\n";
    }

    file_put_contents($cronFile, $cronContent);
    shell_exec("echo 1234 | sudo -S /bin/cp " . escapeshellarg($cronFile) . ' ' . escapeshellarg($cronTarget));
    shell_exec("echo 1234 | sudo -S /bin/chmod 644 " . escapeshellarg($cronTarget));
}

function sync_reboot_schedule_from_db($pdo)
{
    try {
        $stmt = $pdo->query("SELECT reboot_time, COALESCE(schedule_reboot_enable, 0) AS schedule_reboot_enable FROM logger_settings LIMIT 1");
        if (!$stmt) {
            return;
        }

        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return;
        }

        $timeValue = !empty($settings['reboot_time']) ? $settings['reboot_time'] : '03:00';
        $enabled = intval($settings['schedule_reboot_enable']) === 1;
        update_reboot_schedule_cron_config($enabled, $timeValue);
    } catch (Exception $e) {
        error_log('sync_reboot_schedule_from_db: ' . $e->getMessage());
    }
}
?>
