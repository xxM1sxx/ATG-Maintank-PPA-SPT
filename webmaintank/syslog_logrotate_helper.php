<?php
function build_rsyslog_logrotate_content(array $settings)
{
    $rotateCount = max(1, intval($settings['syslog_rotate_count']));
    $frequency = in_array($settings['syslog_rotate_frequency'], array('daily', 'weekly', 'monthly'), true)
        ? $settings['syslog_rotate_frequency'] : 'daily';
    $maxSize = isset($settings['syslog_max_size']) ? trim((string)$settings['syslog_max_size']) : '';
    $compress = !empty($settings['syslog_compress']);
    $delaycompress = !empty($settings['syslog_delaycompress']);
    $missingok = !empty($settings['syslog_missingok']);
    $notifempty = !empty($settings['syslog_notifempty']);

    $lines = array(
        '/var/log/syslog',
        '{',
        '        rotate ' . $rotateCount,
        '        ' . $frequency,
    );

    if ($maxSize !== '' && preg_match('/^(\d+)([kKmMgG]?)$/', $maxSize, $matches)) {
        $normalizedMaxSize = $matches[1];
        if ($matches[2] !== '') {
            $normalizedMaxSize .= strtoupper($matches[2]);
        }
        $lines[] = '        maxsize ' . $normalizedMaxSize;
    }

    if ($missingok) {
        $lines[] = '        missingok';
    }
    if ($notifempty) {
        $lines[] = '        notifempty';
    }
    if ($delaycompress) {
        $lines[] = '        delaycompress';
    }
    if ($compress) {
        $lines[] = '        compress';
    }

    $lines = array_merge($lines, array(
        '        postrotate',
        '                /usr/lib/rsyslog/rsyslog-rotate',
        '        endscript',
        '}',
        '',
        '/var/log/mail.info',
        '/var/log/mail.warn',
        '/var/log/mail.err',
        '/var/log/mail.log',
        '/var/log/daemon.log',
        '/var/log/kern.log',
        '/var/log/auth.log',
        '/var/log/user.log',
        '/var/log/lpr.log',
        '/var/log/cron.log',
        '/var/log/debug',
        '/var/log/messages',
        '{',
        '        rotate 4',
        '        weekly',
        '        missingok',
        '        notifempty',
        '        compress',
        '        delaycompress',
        '        sharedscripts',
        '        postrotate',
        '                /usr/lib/rsyslog/rsyslog-rotate',
        '        endscript',
        '}',
        '',
    ));

    return implode("\n", $lines);
}

function update_rsyslog_logrotate_config(array $settings)
{
    $tempFile = '/home/user/Apps/rsyslog-logrotate.conf';
    $targetFile = '/etc/logrotate.d/rsyslog';
    $content = build_rsyslog_logrotate_content($settings);

    file_put_contents($tempFile, $content);
    shell_exec("echo 1234 | sudo -S /bin/cp " . escapeshellarg($tempFile) . ' ' . escapeshellarg($targetFile));
    shell_exec("echo 1234 | sudo -S /bin/chmod 644 " . escapeshellarg($targetFile));
}

function sync_rsyslog_logrotate_from_db($pdo)
{
    try {
        $sql = "SELECT 
                    COALESCE(syslog_rotate_count, 7) AS syslog_rotate_count,
                    COALESCE(syslog_rotate_frequency, 'daily') AS syslog_rotate_frequency,
                    COALESCE(syslog_max_size, '') AS syslog_max_size,
                    COALESCE(syslog_compress, 1) AS syslog_compress,
                    COALESCE(syslog_delaycompress, 1) AS syslog_delaycompress,
                    COALESCE(syslog_missingok, 1) AS syslog_missingok,
                    COALESCE(syslog_notifempty, 1) AS syslog_notifempty
                FROM logger_settings
                LIMIT 1";
        $stmt = $pdo->query($sql);
        if (!$stmt) {
            return;
        }

        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return;
        }

        update_rsyslog_logrotate_config($settings);
    } catch (Exception $e) {
        error_log('sync_rsyslog_logrotate_from_db: ' . $e->getMessage());
    }
}
?>
