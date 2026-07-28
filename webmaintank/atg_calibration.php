<?php
session_start();
if (empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

require_once 'conn.php';
require_once 'init_atg_table.php';
require_once 'import_calibration.php';

ensure_atg_tables($conn);

$sensor_id = isset($_GET['sensor']) ? intval($_GET['sensor']) : 1;
if ($sensor_id < 1 || $sensor_id > 6) {
    $sensor_id = 1;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=calibration_T' . str_pad($sensor_id, 2, '0', STR_PAD_LEFT) . '.csv');
    echo "sounding_mm,volume_l\n";
    $stmt = $conn->prepare(
        'SELECT sounding_mm, volume_l FROM tbl_atg_volume_point WHERE sensor_id = ? ORDER BY sounding_mm'
    );
    $stmt->execute(array($sensor_id));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo intval($row['sounding_mm']) . ',' . floatval($row['volume_l']) . "\n";
    }
    exit;
}

$label = "Sensor $sensor_id";
$stmt = $conn->prepare('SELECT label FROM tbl_atg_sensor WHERE id = ?');
$stmt->execute(array($sensor_id));
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && !empty($row['label'])) {
    $label = $row['label'];
}

$count_stmt = $conn->prepare('SELECT COUNT(*) FROM tbl_atg_volume_point WHERE sensor_id = ?');
$count_stmt->execute(array($sensor_id));
$point_count = intval($count_stmt->fetchColumn());

$min_stmt = $conn->prepare('SELECT MIN(sounding_mm) FROM tbl_atg_volume_point WHERE sensor_id = ?');
$min_stmt->execute(array($sensor_id));
$min_sounding = intval($min_stmt->fetchColumn());

$max_stmt = $conn->prepare('SELECT MAX(sounding_mm) FROM tbl_atg_volume_point WHERE sensor_id = ?');
$max_stmt->execute(array($sensor_id));
$max_sounding = intval($max_stmt->fetchColumn());

$from_raw = isset($_GET['from']) ? trim($_GET['from']) : '';
$to_raw = isset($_GET['to']) ? trim($_GET['to']) : '';
$filter_active = ($from_raw !== '' || $to_raw !== '');

if ($filter_active) {
    $range_from = ($from_raw !== '') ? max(0, intval($from_raw)) : $min_sounding;
    $range_to = ($to_raw !== '') ? max($range_from, intval($to_raw)) : $max_sounding;
} else {
    $range_from = $min_sounding;
    $range_to = $max_sounding;
}

$points = array();
if ($point_count > 0) {
    if ($filter_active) {
        $pts = $conn->prepare(
            'SELECT sounding_mm, volume_l FROM tbl_atg_volume_point
             WHERE sensor_id = ? AND sounding_mm >= ? AND sounding_mm <= ?
             ORDER BY sounding_mm'
        );
        $pts->execute(array($sensor_id, $range_from, $range_to));
    } else {
        $pts = $conn->prepare(
            'SELECT sounding_mm, volume_l FROM tbl_atg_volume_point
             WHERE sensor_id = ?
             ORDER BY sounding_mm'
        );
        $pts->execute(array($sensor_id));
    }
    $points = $pts->fetchAll(PDO::FETCH_ASSOC);
}
$display_count = count($points);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ATG Calibration - <?php echo htmlspecialchars($label); ?></title>
    <style>
    body { font-family: sans-serif; margin: 24px; color: #444; }
    .table-scroll { max-height: 70vh; overflow-y: auto; margin-top: 12px; border: 1px solid #ccc; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 6px 10px; }
    th { background: #f5f5f5; position: sticky; top: 0; z-index: 1; }
    input[type=text] { width: 90px; }
    .msg { color: green; margin: 8px 0; }
    .err { color: red; margin: 8px 0; }
    .toolbar { margin: 16px 0; }
    .toolbar form { display: inline-block; margin-right: 12px; }
    </style>
</head>
<body>
    <p><a href="home.php">&larr; Back to Settings</a></p>
    <h2>Calibration Table — <?php echo htmlspecialchars($label); ?> (Sensor <?php echo $sensor_id; ?>)</h2>
    <?php if ($point_count > 0): ?>
    <p>
        Menampilkan <?php echo $display_count; ?> titik
        <?php if ($filter_active): ?>
            (filter mm <?php echo $range_from; ?>–<?php echo $range_to; ?> dari <?php echo $point_count; ?> total)
        <?php else: ?>
            (sounding <?php echo $min_sounding; ?> – <?php echo $max_sounding; ?> mm, <?php echo $point_count; ?> titik — sama dengan data seed)
        <?php endif; ?>.
        Offset (mm) di ATG Settings: Sounding = Probe + Offset. Volume lookup memakai Sounding.
    </p>
    <?php else: ?>
    <p>Belum ada data kalibrasi. Import seed atau CSV terlebih dahulu.</p>
    <?php endif; ?>

    <?php if (isset($_GET['saved'])): ?><p class="msg">Saved.</p><?php endif; ?>
    <?php if (isset($_GET['imported'])): ?><p class="msg">Imported <?php echo intval($_GET['imported']); ?> points.</p><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><p class="err">Error: <?php echo htmlspecialchars($_GET['error']); ?></p><?php endif; ?>

    <div class="toolbar">
        <form method="get">
            Sensor:
            <select name="sensor" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo ($i === $sensor_id) ? 'selected' : ''; ?>>TANKI-<?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <form method="get">
            <input type="hidden" name="sensor" value="<?php echo $sensor_id; ?>">
            Filter mm dari <input type="text" name="from" value="<?php echo $filter_active ? $range_from : ''; ?>" placeholder="<?php echo $min_sounding; ?>" size="6">
            sampai <input type="text" name="to" value="<?php echo $filter_active ? $range_to : ''; ?>" placeholder="<?php echo $max_sounding; ?>" size="6">
            <input type="submit" value="Filter">
        </form>
        <?php if ($filter_active): ?>
        <a href="?sensor=<?php echo $sensor_id; ?>">Tampilkan semua</a>
        <?php endif; ?>
        <a href="?sensor=<?php echo $sensor_id; ?>&export=csv">Export CSV</a>
        <form method="post" action="save_data.php" enctype="multipart/form-data" style="display:inline">
            <input type="hidden" name="calibration_sensor_id" value="<?php echo $sensor_id; ?>">
            <input type="file" name="calibration_csv" accept=".csv,text/csv">
            <input type="submit" name="import_calibration_csv" value="Import CSV">
        </form>
        <form method="post" action="save_data.php" style="display:inline">
            <input type="hidden" name="calibration_sensor_id" value="<?php echo $sensor_id; ?>">
            <input type="submit" name="import_calibration_seed" value="Import default (PDF seed)">
        </form>
    </div>

    <?php if ($point_count === 0): ?>
        <p>No calibration data. Use <strong>Import default (PDF seed)</strong> or upload a CSV.</p>
    <?php else: ?>
        <form method="post" action="save_data.php">
            <input type="hidden" name="calibration_sensor_id" value="<?php echo $sensor_id; ?>">
            <div class="table-scroll">
                <table>
                    <tr><th>Sounding (mm)</th><th>Volume (L)</th></tr>
                    <?php foreach ($points as $p): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="sounding_mm[]" value="<?php echo intval($p['sounding_mm']); ?>">
                            <?php echo intval($p['sounding_mm']); ?>
                        </td>
                        <td><input type="text" name="volume_l[]" value="<?php echo floatval($p['volume_l']); ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <p><input type="submit" name="save_calibration_table" value="Save"></p>
            <p><small>
                Edit volume inline lalu Save, atau gunakan Export/Import CSV untuk perubahan massal.
                Save penuh (~2600 baris) membutuhkan <code>max_input_vars &ge; 10000</code> di php.ini (lihat BUILD-BBB.md).
            </small></p>
        </form>
    <?php endif; ?>
</body>
</html>
