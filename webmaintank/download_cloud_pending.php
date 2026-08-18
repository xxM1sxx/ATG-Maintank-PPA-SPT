<?php
session_start();
if (empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

require_once 'conn.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=cloud_pending_' . date('Ymd_His') . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, array('id', 'payload', 'created_at_utc'));

$stmt = $conn->query('SELECT id, payload, created_at FROM tbl_atg_cloud_pending ORDER BY id ASC');
foreach ($stmt as $row) {
    fputcsv($out, array($row['id'], $row['payload'], $row['created_at']));
}

fclose($out);
