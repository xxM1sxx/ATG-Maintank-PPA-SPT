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
if ($sensor_id < 1 || $sensor_id > 8) {
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
    @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

    body { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; color: #444; background: #f9f9f9; }

    .header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 16px 32px; background: #f9f9f9; border-bottom: 1px solid #ddd;
        margin-bottom: 24px;
    }

    h2 { margin: 0; color: #333; font-size: 20px; flex: 1; }

    .back-link { color: #03a9f4; text-decoration: none; font-size: 14px; }
    .back-link:hover { text-decoration: underline; }

    .toolbar {
        margin: 20px 32px; display: flex; gap: 16px; align-items: center;
        flex-wrap: wrap; background: #fff; padding: 16px; border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .toolbar form { display: flex; gap: 8px; align-items: center; }
    input[type=text], input[type=file], select {
        padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;
        font-family: 'Poppins', sans-serif;
    }

    input[type=submit], .btn {
        padding: 10px 20px; background: #03a9f4; color: white; text-decoration: none;
        border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;
        font-family: 'Poppins', sans-serif; transition: all 0.2s ease; display: inline-block;
    }

    input[type=submit]:hover, .btn:hover {
        background: #0288d1; box-shadow: 0 2px 8px rgba(3, 169, 244, 0.3);
    }

    input[type=submit]:active { transform: translateY(1px); }

    .toolbar a { padding: 10px 20px; background: #03a9f4; color: white; text-decoration: none;
        border-radius: 4px; font-size: 14px; font-weight: 500; transition: all 0.2s ease; }
    .toolbar a:hover { background: #0288d1; box-shadow: 0 2px 8px rgba(3, 169, 244, 0.3); }

    .content { padding: 0 32px 32px; }
    .info-text { color: #666; font-size: 14px; margin: 12px 0; }

    .table-scroll { max-height: 70vh; overflow-y: auto; margin-top: 12px; border: 1px solid #ccc; border-radius: 4px; }
    table { border-collapse: collapse; width: 100%; background: white; font-family: 'Poppins', sans-serif; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
    th { background: #f5f5f5; position: sticky; top: 0; z-index: 1; font-weight: 600; color: #333; }
    tr:nth-child(even) { background: #f9f9f9; }
    tr:hover { background-color: #f0f7ff; }

    .table-scroll input[type=text] { width: 100px; }

    .form-note { font-size: 13px; color: #666; margin: 12px 0; }

    .form-actions { margin-top: 16px; display: flex; gap: 12px; align-items: center; }

    .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .toast { background: #333; color: #fff; padding: 12px 20px; border-radius: 4px; margin-bottom: 10px;
             min-width: 250px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); animation: slideIn 0.3s ease; }
    .toast.success { background: #28a745; }
    .toast.error { background: #dc3545; }
    @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }
    </style>
    <script>
    function showToast(msg, type = 'info') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        let toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    window.addEventListener('load', function() {
        let params = new URLSearchParams(window.location.search);
        if (params.has('error')) {
            showToast('❌ Error: ' + decodeURIComponent(params.get('error')), 'error');
            window.history.replaceState({}, document.title, window.location.pathname + '?sensor=' + params.get('sensor'));
        } else if (params.has('saved')) {
            showToast('✓ Changes saved successfully.', 'success');
            window.history.replaceState({}, document.title, window.location.pathname + '?sensor=' + params.get('sensor'));
        } else if (params.has('imported')) {
            showToast('✓ Imported ' + decodeURIComponent(params.get('imported')) + ' calibration points.', 'success');
            window.history.replaceState({}, document.title, window.location.pathname + '?sensor=' + params.get('sensor'));
        }
    });
    </script>
</head>
<body>
    <div class="header">
        <h2>Calibration — <?php echo htmlspecialchars($label); ?> (Sensor <?php echo $sensor_id; ?>)</h2>
        <a href="home.php" class="back-link">← Back to Settings</a>
    </div>
    <div class="content">
        <?php if ($point_count > 0): ?>
        <p class="info-text">
            Showing <?php echo $display_count; ?> calibration points
            <?php if ($filter_active): ?>
                (filtered <?php echo $range_from; ?>–<?php echo $range_to; ?> mm of <?php echo $point_count; ?> total)
            <?php else: ?>
                (range <?php echo $min_sounding; ?>–<?php echo $max_sounding; ?> mm)
            <?php endif; ?>.
        </p>
        <?php else: ?>
        <p class="info-text">No calibration data yet. Upload a CSV file to get started.</p>
        <?php endif; ?>

        <div class="toolbar">
            <form method="get">
                <label style="margin: 0;">Sensor:</label>
                <select name="sensor" onchange="this.form.submit()">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($i === $sensor_id) ? 'selected' : ''; ?>>TANKI-<?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <form method="get">
                <input type="hidden" name="sensor" value="<?php echo $sensor_id; ?>">
                <label style="margin: 0;">Filter:</label>
                <input type="text" name="from" value="<?php echo $filter_active ? $range_from : ''; ?>" placeholder="from mm" size="8">
                <input type="text" name="to" value="<?php echo $filter_active ? $range_to : ''; ?>" placeholder="to mm" size="8">
                <input type="submit" value="Apply">
            </form>
            <?php if ($filter_active): ?>
            <a href="?sensor=<?php echo $sensor_id; ?>">Clear filter</a>
            <?php endif; ?>
            <a href="?sensor=<?php echo $sensor_id; ?>&export=csv">Export CSV</a>
            <form method="post" action="save_data.php" enctype="multipart/form-data">
                <input type="hidden" name="calibration_sensor_id" value="<?php echo $sensor_id; ?>">
                <input type="file" name="calibration_csv" accept=".csv,text/csv">
                <input type="submit" name="import_calibration_csv" value="Import CSV">
            </form>
        </div>

        <?php if ($point_count === 0): ?>
            <p class="info-text">No calibration data. Please upload a CSV file to import calibration points.</p>
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
                <div class="form-actions">
                    <input type="submit" name="save_calibration_table" value="Save Changes">
                </div>
                <p class="form-note">Edit volume inline then Save, or use Export/Import CSV for bulk changes.</p>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
