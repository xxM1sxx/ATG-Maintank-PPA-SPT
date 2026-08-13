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

    // Skip the rewrite entirely if the schedule hasn't actually changed since
    // last time (this runs on every home.php page load via sync_reboot_schedule_from_db,
    // so an unconditional rewrite here would race with cron reading the file
    // right at the scheduled minute and could cause a missed reboot).
    if (is_readable($cronTarget) && file_get_contents($cronTarget) === $cronContent) {
        return;
    }

    file_put_contents($cronFile, $cronContent);

    // Install atomically: write to a temp file in the same directory as the
    // target, then rename over it. A plain `cp` can leave the target briefly
    // empty/truncated mid-copy, which cron can catch as an invalid/empty file.
    $cronTmp = $cronTarget . '.tmp';
    shell_exec("echo 1234 | sudo -S /bin/cp " . escapeshellarg($cronFile) . ' ' . escapeshellarg($cronTmp));
    shell_exec("echo 1234 | sudo -S /bin/chmod 644 " . escapeshellarg($cronTmp));
    shell_exec("echo 1234 | sudo -S /bin/mv -f " . escapeshellarg($cronTmp) . ' ' . escapeshellarg($cronTarget));
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
