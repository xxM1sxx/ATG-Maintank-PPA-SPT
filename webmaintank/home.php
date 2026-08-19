<!DOCTYPE html>
<?php
	session_start();

	//empty does both of the checks you are doing at once
	//check if user is logged in first
	if(empty($_SESSION['username'])) {
		
		//give error and start redirection to login page
		//you may never see this `echo` because the redirect may happen too fast
		echo "Please log in first to see this page.";
		header('Location: index.php');

		//kill page because user is not logged in and is waiting for redirection
		die();
	}
	$data_username = $_SESSION['username'];

?>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Vimo Zetha</title>
    <style>
    @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");

    *,
    *:before,
    *:after {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        max-width: 100%;
        overflow-x: hidden;
    }

    body {
        font-family: 'Poppins', sans-serif;
        font-size: 16px;
        line-height: 1.0;
        color: #777777;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 32px;
        background: #f9f9f9;
        border-bottom: 1px solid #ddd;
        margin-bottom: 24px;
    }

    h1 {
        font-family: 'Poppins', sans-serif;
        font-size: 24px;
        margin: 0;
        color: #333;
        flex: 1;
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 24px;
    }

    .welcome-text {
        color: #666;
        font-size: 14px;
        margin: 0;
    }

    .logout-btn {
        padding: 10px 20px;
        background: #dc3545;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        font-family: 'Poppins', sans-serif;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        display: inline-block;
    }

    .logout-btn:hover {
        background: #c82333;
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }

    .container {
        width: 100%;
        margin: 0 auto;
        max-width: 1400px;
        padding-right: 16px;
        padding-left: 16px;
    }

    section {
        background: #ffffff;
        padding: 32px 0;
    }

    .tab__list {
        display: flex;
        margin-bottom: 24px;
        border-bottom: 2px solid #ddd;
        background: transparent;
        padding: 0;
    }

    .tab__item {
        overflow: hidden;
        cursor: pointer;
        padding: 16px 24px;
        margin-right: 0;
        text-shadow: none;
        letter-spacing: 0;
        transition: all 0.3s ease;
        font-size: 15px;
        font-weight: 500;
        position: relative;
        color: #666;
        border-bottom: 3px solid transparent;
    }

    .tab__item:before {
        display: none;
    }

    .tab__item:hover {
        color: #333;
        background: #f9f9f9;
    }

    .tab__item.is--active {
        color: #03a9f4;
        border-bottom-color: #03a9f4;
        position: relative;
    }

    .tab__item.is--active:before {
        display: none;
    }

    .tab__content {
        padding: 32px 0;
        background: transparent;
    }

    .tab__content-item {
        display: none;
    }

    .tab__content-item.is--active {
        display: block;
    }

    .tab__content-title {
        padding: 24px 24px 24px 0;
    }

    .tab .tab .tab__list {
        border-bottom: 1px solid #ddd;
        padding: 0;
    }

    .tab .tab .tab__item {
        font-size: 14px;
        font-weight: 400;
        padding: 12px 16px;
        border-bottom: 2px solid transparent;
    }

    .tab .tab .tab__item.is--active {
        border-bottom-color: #03a9f4;
    }

    .tab .tab .tab__content {
        padding: 0;
        background: transparent;
        box-shadow: none;
    }

    .logo_qims {
        position: absolute;
        bottom: 8px;
        left: 16px;
        width: 50px;
        height: 50px;
    }

    table {
        font-family: 'Poppins', sans-serif;
        border-collapse: collapse;
        width: 100%;
        background: white;
    }

    td, th {
        border: 1px solid #ddd;
        text-align: left;
        padding: 12px 16px;
        font-size: 14px;
    }

    th {
        background: #f5f5f5;
        font-weight: 600;
        color: #333;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    tr:hover {
        background-color: #f0f7ff;
    }

    label {
        display: inline-block;
        width: 250px;
        text-align: left;
    }

    .form-section {
        margin-bottom: 28px;
    }

    .form-section > strong,
    .section-title {
        display: block;
        font-size: 16px;
        margin-bottom: 16px;
        color: #333;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(380px, 100%), 1fr));
        gap: 20px;
        align-items: stretch;
    }

    .settings-grid .form-section {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
    }

    .settings-grid .form-section:not(.multi-form) form {
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .settings-grid .form-section:not(.multi-form) form > .form-group:has(input[type="submit"]):last-child {
        margin-top: auto;
        padding-top: 12px;
    }

    .settings-grid .form-section.multi-form form {
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f0f0f0;
    }

    .settings-grid .form-section.multi-form form:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .settings-subtitle {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 10px;
    }

    .checkbox-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 14px;
        padding: 12px 14px;
        background: #f9f9f9;
        border: 1px solid #eee;
        border-radius: 6px;
    }

    .checkbox-grid .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        color: #666;
        font-size: 14px;
    }

    .form-actions {
        margin-top: 16px;
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .form-group {
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .form-group label {
        margin: 0;
        width: auto;
        min-width: 200px;
    }

    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group input[type="password"],
    .form-group select {
        flex: 1 1 auto;
        min-width: 0;
        max-width: 100%;
    }

    input[type="text"],
    input[type="number"],
    input[type="password"],
    input[type="date"],
    input[type="time"],
    input[type="file"],
    select {
        padding: 6px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
    }

    .form-group label.checkbox-label,
    .form-group label:has(input[type="checkbox"]) {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .form-group input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #03a9f4;
    }

    input[type="submit"],
    input[type="button"],
    .btn {
        padding: 10px 20px;
        background: #03a9f4;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        font-family: 'Poppins', sans-serif;
    }

    input[type="submit"]:hover,
    input[type="button"]:hover,
    .btn:hover {
        background: #0288d1;
        box-shadow: 0 2px 8px rgba(3, 169, 244, 0.3);
    }

    input[type="submit"]:active,
    input[type="button"]:active {
        transform: translateY(1px);
    }

    input[type="submit"].btn-danger {
        background: #dc3545;
    }

    input[type="submit"].btn-danger:hover {
        background: #c82333;
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }

    .form-note {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
        margin-bottom: 12px;
    }

    .status-online {
        color: green;
        font-weight: bold;
    }

    .status-offline {
        color: red;
        font-weight: bold;
    }

    .atg-disabled {
        color: #999;
    }
    </style>
    <script>
    window.console = window.console || function(t) {};
    </script>
    <script>
    if (document.location.search.match(/type=embed/gi)) {
        window.parent.postMessage("resize", "*");
    }

    function formatAMPM() {
        var d = new Date(),
            minutes = d.getMinutes().toString().length == 1 ? '0' + d.getMinutes() : d.getMinutes(),
            hours = d.getHours().toString().length == 1 ? '0' + d.getHours() : d.getHours(),
            ampm = d.getHours() >= 12 ? 'pm' : 'am',
            months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear() + ' ' + hours + ':' + minutes;
    }
    var hasil = formatAMPM();
    document.cookie = "myJavascriptVar = " + hasil;
    //document.location = 'save_data.php?realtime='+hasil;
    </script>
    <style>
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
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (params.has('saved')) {
            showToast('✓ Changes saved successfully.', 'success');
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });
    </script>
</head>
<?php
		
		//Baca File sn.txt
		$file_sn = fopen("/var/www/html/sn.txt", "r");
		$read_serial_number = fgets($file_sn);
		//echo $read_serial_number;
		fclose($file_sn);
		
		
		//Bagian untuk membaca status logger / linux menggunakan command prompt
		$get_current_date = shell_exec('date +"%Y-%m-%d"');									//Get Current Date 
		$get_current_time = shell_exec('date +"%H:%M"');									//Get Current Time 
		$get_memory_free = shell_exec("grep '^MemFree' /proc/meminfo | sed 's/.*: //'");	//Get Memory Free
		$get_uptime = shell_exec('uptime -p');												//Get Uptime					
		$linux_version = shell_exec("lsb_release -d");
		$linux_version_parsed = explode(":", $linux_version);
//		$output = shell_exec("df -T -h /var | sed 's/...$//'");	
		$get_diskfree = shell_exec("df --output=avail -h / | sed '1d'");
		$get_cpuusage = shell_exec("top -b -n1 | grep 'Cpu(s)' | awk '{print $2}'");
		$file_on_folder_firmware = shell_exec("ls /var/www/html/upload/firmware/");
		$file_on_folder_license  = shell_exec("ls /var/www/html/upload/license/");
		 
		//Open Database
		require_once 'conn.php';
		require_once 'reboot_schedule_helper.php';
		require_once 'syslog_logrotate_helper.php';
		require_once 'init_atg_table.php';
		require_once 'init_logger_settings.php';
		try {
			ensure_atg_tables($conn);
			ensure_logger_settings_columns($conn);
		} catch (Exception $e) {
			error_log('ensure_atg_tables: ' . $e->getMessage());
		}
		$db = new SQLite3('/home/user/Apps/qims_logger.db');		
		try {
			sync_reboot_schedule_from_db($conn);
		} catch (Exception $e) {
			error_log('sync_reboot_schedule: ' . $e->getMessage());
		}
		try {
			sync_rsyslog_logrotate_from_db($conn);
		} catch (Exception $e) {
			error_log('sync_rsyslog_logrotate: ' . $e->getMessage());
		}
		//Save dulu hasil pembacaan file sn.txt
		$query_sn = "UPDATE logger_settings SET serial_number = :read_serial_number";
		$stmt_sn = $db->prepare($query_sn);
		$stmt_sn->bindParam(':read_serial_number', $read_serial_number);
		$stmt_sn->execute();  
		
		//Open Database User
		$result_dbuser = $db->query("SELECT * FROM logger_user WHERE username='$data_username' "); 
		$data_dbuser = $result_dbuser ? $result_dbuser->fetchArray() : false;
		$privilege_user = ($data_dbuser && isset($data_dbuser[3])) ? $data_dbuser[3] : 0;

		//Parsing Tabel Setting
		$result_setting = $db->query("SELECT * FROM logger_settings"); 
		$data_setting = $result_setting ? $result_setting->fetchArray() : false;
		$data_ip_address_logger= ($data_setting && isset($data_setting[1])) ? $data_setting[1] : ''; 
		$data_subnetmask_logger = ($data_setting && isset($data_setting[2])) ? $data_setting[2] : ''; 
		$data_gateway_logger = ($data_setting && isset($data_setting[3])) ? $data_setting[3] : ''; 
		$data_baudrate1= ($data_setting && isset($data_setting[4])) ? $data_setting[4] : ''; 
		$data_baudrate2= ($data_setting && isset($data_setting[5])) ? $data_setting[5] : ''; 
		$data_baudrate_modbus = ($data_setting && isset($data_setting[6])) ? $data_setting[6] : ''; 
		$data_comport1 = ($data_setting && isset($data_setting[7])) ? $data_setting[7] : ''; 
		$data_comport2 = ($data_setting && isset($data_setting[8])) ? $data_setting[8] : ''; 
		$data_comport_modbus  = ($data_setting && isset($data_setting[9])) ? $data_setting[9] : ''; 
		$data_serial_number  = ($data_setting && isset($data_setting[10])) ? $data_setting[10] : '';
		// skip index 11 (logfile_day_rotation - unused, removed feature)
		// skip index 12 (version_firmware - tidak dipakai)
		$data_unitname= ($data_setting && isset($data_setting[13])) ? $data_setting[13] : ''; 
		$data_ip_address_logger_alias= ($data_setting && isset($data_setting[14])) ? $data_setting[14] : ''; 
		$data_subnetmask_logger_alias= ($data_setting && isset($data_setting[15])) ? $data_setting[15] : ''; 
		$data_gateway_logger_alias= ($data_setting && isset($data_setting[16])) ? $data_setting[16] : ''; 
		$data_ip_address_ntp= ($data_setting && isset($data_setting[17])) ? $data_setting[17] : ''; 
		$data_reboot_time= ($data_setting && isset($data_setting[18])) ? $data_setting[18] : '03:00'; 
		$data_ip_address_server= ($data_setting && isset($data_setting[19])) ? $data_setting[19] : ''; 
		$data_timezone = ($data_setting && isset($data_setting[24])) ? $data_setting[24] : ''; 
		$data_jenis_apps = ($data_setting && isset($data_setting[25])) ? $data_setting[25] : ''; 
		$data_ip_address_vhms = ($data_setting && isset($data_setting[26])) ? $data_setting[26] : ''; 
		$data_reboot_schedule_enable = 0;
		$result_reboot_schedule = $db->query("SELECT reboot_time, COALESCE(schedule_reboot_enable, 0) AS schedule_reboot_enable FROM logger_settings LIMIT 1");
		if ($result_reboot_schedule) {
			$data_reboot_schedule = $result_reboot_schedule->fetchArray(SQLITE3_ASSOC);
			if ($data_reboot_schedule) {
				$data_reboot_time = isset($data_reboot_schedule['reboot_time']) ? $data_reboot_schedule['reboot_time'] : $data_reboot_time;
				$data_reboot_schedule_enable = isset($data_reboot_schedule['schedule_reboot_enable']) ? intval($data_reboot_schedule['schedule_reboot_enable']) : 0;
			}
		}
		$data_syslog_rotate_count = 7;
		$data_syslog_rotate_frequency = 'daily';
		$data_syslog_max_size = '';
		$data_syslog_compress = 1;
		$data_syslog_delaycompress = 1;
		$data_syslog_missingok = 1;
		$data_syslog_notifempty = 1;
		$result_syslog_settings = $db->query("SELECT 
			COALESCE(syslog_rotate_count, 7) AS syslog_rotate_count,
			COALESCE(syslog_rotate_frequency, 'daily') AS syslog_rotate_frequency,
			COALESCE(syslog_max_size, '') AS syslog_max_size,
			COALESCE(syslog_compress, 1) AS syslog_compress,
			COALESCE(syslog_delaycompress, 1) AS syslog_delaycompress,
			COALESCE(syslog_missingok, 1) AS syslog_missingok,
			COALESCE(syslog_notifempty, 1) AS syslog_notifempty
			FROM logger_settings LIMIT 1");
		if ($result_syslog_settings) {
			$data_syslog_settings = $result_syslog_settings->fetchArray(SQLITE3_ASSOC);
			if ($data_syslog_settings) {
				$data_syslog_rotate_count = intval($data_syslog_settings['syslog_rotate_count']);
				$data_syslog_rotate_frequency = $data_syslog_settings['syslog_rotate_frequency'];
				$data_syslog_max_size = $data_syslog_settings['syslog_max_size'];
				$data_syslog_compress = intval($data_syslog_settings['syslog_compress']);
				$data_syslog_delaycompress = intval($data_syslog_settings['syslog_delaycompress']);
				$data_syslog_missingok = intval($data_syslog_settings['syslog_missingok']);
				$data_syslog_notifempty = intval($data_syslog_settings['syslog_notifempty']);
			}
		}

        // Load ATG MQTT Settings
        $data_mqtt_broker_host = '192.168.1.10';
        $data_mqtt_broker_port = 1883;
        $data_mqtt_username = '';
        $data_mqtt_password = '';
        $data_mqtt_topic_path = 'maintank/atg/value';
        $data_mqtt_interval = 30;
        $data_mqtt_company = 'PT. Putra Perkasa Abadi';
        $data_mqtt_plant_id = 'PPA-BA';

        // Load Cloud MQTT Settings (shares the broker/credentials above; own topics+interval)
        $data_cloud_topic_path = 'maintank/atg/cloud/value';
        $data_cloud_request_topic = 'maintank/atg/cloud/request';
        $data_cloud_interval = 120;
        $data_cloud_enabled = false;
        $data_cloud_store_on_fail = true;

        $result_mqtt = $db->query("SELECT * FROM tbl_atg_mqtt_setting WHERE id=1");
        if ($result_mqtt) {
            $row_mqtt = $result_mqtt->fetchArray(SQLITE3_ASSOC);
            if ($row_mqtt) {
                $data_mqtt_broker_host = $row_mqtt['broker_host'] ?? '192.168.1.10';
                $data_mqtt_broker_port = intval($row_mqtt['broker_port'] ?? 1883);
                $data_mqtt_username = $row_mqtt['username'] ?? '';
                $data_mqtt_password = $row_mqtt['password'] ?? '';
                $data_mqtt_topic_path = $row_mqtt['topic_path'] ?? 'maintank/atg/value';
                $data_mqtt_interval = intval($row_mqtt['publish_interval_sec'] ?? 30);
                $data_mqtt_company = $row_mqtt['company_name'] ?? 'PT. Putra Perkasa Abadi';
                $data_mqtt_plant_id = $row_mqtt['plant_id'] ?? 'PPA-BA';

                if (!empty($row_mqtt['cloud_topic_path'])) {
                    $data_cloud_topic_path = $row_mqtt['cloud_topic_path'];
                }
                if (!empty($row_mqtt['cloud_request_topic'])) {
                    $data_cloud_request_topic = $row_mqtt['cloud_request_topic'];
                }
                $data_cloud_interval = intval($row_mqtt['cloud_publish_interval_sec'] ?? 120);
                $data_cloud_enabled = intval($row_mqtt['cloud_enabled'] ?? 0) === 1;
                $data_cloud_store_on_fail = intval($row_mqtt['cloud_store_on_fail'] ?? 1) === 1;
            }
        }

		$atg_sensors = array();
		$result_atg = $db->query("SELECT * FROM tbl_atg_sensor ORDER BY id");
		if ($result_atg) {
			while ($row = $result_atg->fetchArray(SQLITE3_ASSOC)) {
				$atg_sensors[intval($row['id'])] = $row;
			}
		}

		//Parsing Tabel Loger Basic Info
		$result_basic_info = $db->query("SELECT * FROM logger_basic_info"); 
		$data_basic_info = $result_basic_info ? $result_basic_info->fetchArray() : false;
		$data_cpu_load = ($data_basic_info && isset($data_basic_info[1])) ? $data_basic_info[1] : ''; 
		$data_cache_memory = ($data_basic_info && isset($data_basic_info[2])) ? $data_basic_info[2] : ''; 
		$data_temperature = ($data_basic_info && isset($data_basic_info[3])) ? $data_basic_info[3] : ''; 
		$data_free_memory = ($data_basic_info && isset($data_basic_info[4])) ? $data_basic_info[4] : ''; 
		$data_disk_space = ($data_basic_info && isset($data_basic_info[5])) ? $data_basic_info[5] : '';



        //Db pss
        $pss_mqtt_host = '';
        $pss_mqtt_port = 8883;
        $pss_tenant = '';
        $pss_sub_tenant = '';
        if (file_exists('/home/user/Apps/qimsPSS.db')) {
            $db_pss = new SQLite3('/home/user/Apps/qimsPSS.db');
            $pss_setting = $db_pss->query("SELECT * FROM TBL_CLIENT_SETTING");
            if ($pss_setting) {
                $pss_data = $pss_setting->fetchArray();
                if ($pss_data) {
                    $pss_mqtt_host = $pss_data[1];
                    $pss_tenant = $pss_data[2];
                    $pss_sub_tenant = $pss_data[3];
                }
            }
        }


	?>

<body>
    <div class="header">
        <h1>ATG Maintank</h1>
        <div class="header-right">
            <p class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <a href="action-logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    <section class="section">
        <div class="container">
            <div class="tab" id="tab-main">
                <div class="tab__list">
                    <div class="tab__item">
                        Status
                    </div>
                    <div class="tab__item">
                        Settings
                    </div>
                </div>
                <div class="tab__content">
                    <div class="tab__content-item tab" id="tab-status">
                        <div class="tab__list">
                            <div class="tab__item">
                                General
                            </div>

                            <!-- Download tab unused, kept for reference
                            <div class="tab__item">
                                Download
                            </div>
                            -->
                            <div class="tab__item">
                                Fuel Level
                            </div>
                        </div>
                        <div class="tab__content">
                            <div class="tab__content-item">
                                <table>
                                    <tr>
                                        <td width=50%>Unit Name </td>
                                        <td><?php echo $data_unitname;?></td>
                                    </tr>
                                    <tr>
                                        <td width=50%>IP address</td>
                                        <td><?php echo $data_ip_address_logger;?></td>
                                    </tr>
                                    <tr>
                                        <td width=50%>Subnetmask</td>
                                        <td><?php echo $data_subnetmask_logger;?></td>
                                    </tr>
                                    <tr>
                                        <td width=50%>Gateway</td>
                                        <td><?php echo $data_gateway_logger;?></td>
                                    </tr>

                                    <!-- <tr>
										<td>Serial Com</td>
										<td><?php echo $data_comport1;?></td>
									</tr>
									<tr>
										<td>Baudrate</td>
										<td><?php echo $data_baudrate1;?></td>
									</tr> -->
                                    <tr>
                                        <td>Logger ID</td>
                                        <td><?php echo $data_serial_number;?></td>
                                    </tr>
                                    <tr>
                                        <td>Disk Free</td>
                                        <td><?php echo $get_diskfree;?></td>
                                    </tr>
                                    <tr>
                                        <td>Uptime</td>
                                        <td><?php echo $get_uptime;?></td>
                                    </tr>
                                    <tr>
                                        <td>Date &amp; Time</td>
                                        <td id="live_datetime" data-server-time="<?php echo trim(shell_exec('date +"%Y-%m-%d %H:%M:%S"')); ?>"><?php echo trim(shell_exec('date +"%d %b %Y %H:%M:%S"')); ?></td>
                                    </tr>
                                    <tr>
										<td>PSS Status</td>
										<td id="pss_status" style="vertical-align: middle;">Checking...</td>
									</tr>
                                    <tr>
										<td>ATG Sensors</td>
										<td id="atg_status_summary" style="vertical-align: middle;">Checking...</td>
									</tr>


                                    <?php
                                    if ($privilege_user == "Superuser")
                                    {
                                    ?>
                                    <tr>
										<td>PSS Host</td>
										<td><?php echo $pss_mqtt_host;?></td>
									</tr>
									<tr>
										<td>Tenant</td>
										<td><?php echo $pss_tenant;?></td>
									</tr>
									<tr>
										<td>Sub-Tenant</td>
										<td><?php echo $pss_sub_tenant;?></td>
									</tr>
                                    <?php
                                    }
                                    ?>
                                    <!--									<tr>
										<td>CPU Usage</td>
										<td><?php echo $get_cpuusage;?> %</td>
									</tr>
									<tr>
										<td>Timezone </td>
										<td>GMT +<?php echo $data_timezone;?></td>
									</tr>
									<tr>
										<td>Memory Free</td>
										<td><?php echo $get_memory_free;?></td>
									</tr> -->
                                </table>
                            </div>

                            <!-- Download Files tab unused, kept for reference
                            <div class="tab__content-item">
                                <strong class="section-title">Download Files</strong>
                                <iframe src="file_manager.php" id="iFrame1" width="900" height="500">
                                </iframe>

                            </div>
                            -->

                            <div class="tab__content-item">
                                <strong class="section-title">Fuel Level Sensors</strong>
                                <table id="atg_readings_table">
                                    <tr>
                                        <th>#</th>
                                        <th>Label</th>
                                        <th>Device</th>
                                        <th>Status</th>
                                        <th>Offset (mm)</th>
                                        <th>Probe (mm)</th>
                                        <th>Sounding (mm)</th>
                                        <th>Fuel Vol (L)</th>
                                        <th>Water (mm)</th>
                                        <th>Water Vol (L)</th>
                                        <th>Total Vol (L)</th>
                                        <th>Temp (°C)</th>
                                        <th>Last Update</th>
                                    </tr>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <tr id="atg_row_<?php echo $i; ?>">
                                        <td><?php echo $i; ?></td>
                                        <td class="atg-label">—</td>
                                        <td class="atg-device">—</td>
                                        <td class="atg-status">—</td>
                                        <td class="atg-offset">—</td>
                                        <td class="atg-probe">—</td>
                                        <td class="atg-sounding">—</td>
                                        <td class="atg-volume">—</td>
                                        <td class="atg-water">—</td>
                                        <td class="atg-volume-water">—</td>
                                        <td class="atg-volume-total">—</td>
                                        <td class="atg-temp">—</td>
                                        <td class="atg-updated">—</td>
                                    </tr>
                                    <?php endfor; ?>
                                </table>
                            </div>

                            <div class="tab__content-item">
                                <strong class="section-title">Serial Information</strong>
                                <table>
                                    <tr>
                                        <td>Serial Port Radio</td>
                                        <td>COM<?php echo $data_comport1;?></td>
                                        <td>Baudrate</td>
                                        <td><?php echo $data_baudrate1;?> bps</td>
                                    </tr>
                                    <tr>
                                        <td>COM Port #2</td>
                                        <td>COM<?php echo $data_comport2;?></td>
                                        <td>Baudrate #2</td>
                                        <td><?php echo $data_baudrate2;?> bps</td>
                                    </tr>
                                    <tr>
                                        <td>COM Port Modbus</td>
                                        <td>COM<?php echo $data_comport_modbus;?></td>
                                        <td>Baudrate Modbus</td>
                                        <td><?php echo $data_baudrate_modbus;?> bps</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="tab__content-item tab" id="tab-settings">
                        <div class="tab__list">
                            <div class="tab__item">
                                General
                            </div>
                            <!-- <div class="tab__item">
								Parameter Analog Input 
							</div> -->
                            <div class="tab__item">
                                System
                            </div>
                            <div class="tab__item">
                                ATG Sensors
                            </div>
                            <div class="tab__item">
                                MQTT Settings
                            </div>
                            <div class="tab__item">
                                Cloud MQTT
                            </div>
                            <div class="tab__item">
                                Download
                            </div>
                            <div class="tab__item">
                                Sensor Metadata
                            </div>
                            <!-- <?php
							if ($privilege_user == "Superuser")
							{
							?>
                            <div class="tab__item">
                                String Variable
                            </div>
                            <?php
							}
							?> -->
                            <?php
							if ($privilege_user == "Superuser")
							{
							?>
                            <div class="tab__item" id="pss-tab">
								PSS
							</div>
                            <?php
							}
							?>
                        </div>
                        <div class="tab__content">
                            <div class="tab__content-item">
                                <div class="form-section">
                                    <strong>Unit Identity &amp; Ethernet Properties</strong>
                                    <form method="post" action="save_data.php">
                                        <div class="form-group">
                                            <label for="data_unitname">Unit Name:</label>
                                            <input type="text" id="data_unitname" name="data_unitname"
                                                value="<?php echo htmlspecialchars($data_unitname);?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="data_ip_address_logger">IP Address:</label>
                                            <input type="text" id="data_ip_address_logger" name="data_ip_address_logger"
                                                value="<?php echo htmlspecialchars($data_ip_address_logger);?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="data_subnetmask_logger">Subnetmask:</label>
                                            <input type="text" id="data_subnetmask_logger" name="data_subnetmask_logger"
                                                value="<?php echo htmlspecialchars($data_subnetmask_logger);?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="data_gateway_logger">Gateway:</label>
                                            <input type="text" id="data_gateway_logger" name="data_gateway_logger"
                                                value="<?php echo htmlspecialchars($data_gateway_logger);?>">
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="save_general_settings" value="Save Changes">
                                        </div>
                                    </form>
                                </div>
                            </div>


                            <div class="tab__content-item">
                                <div class="settings-grid">

                                <div class="form-section">
                                    <strong>Syslog Rotation</strong>
                                    <form method="post" action="save_data.php">
                                        <div class="form-group">
                                            <label for="data_syslog_rotate_count">Rotate Count:</label>
                                            <input type="text" id="data_syslog_rotate_count" name="data_syslog_rotate_count"
                                                value="<?php echo $data_syslog_rotate_count; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="data_syslog_rotate_frequency">Frequency:</label>
                                            <select id="data_syslog_rotate_frequency" name="data_syslog_rotate_frequency">
                                                <option value="daily" <?php echo ($data_syslog_rotate_frequency === 'daily') ? 'selected' : ''; ?>>daily</option>
                                                <option value="weekly" <?php echo ($data_syslog_rotate_frequency === 'weekly') ? 'selected' : ''; ?>>weekly</option>
                                                <option value="monthly" <?php echo ($data_syslog_rotate_frequency === 'monthly') ? 'selected' : ''; ?>>monthly</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="data_syslog_max_size">Max Size:</label>
                                            <input type="text" id="data_syslog_max_size" name="data_syslog_max_size"
                                                value="<?php echo htmlspecialchars($data_syslog_max_size); ?>">
                                        </div>
                                        <div class="checkbox-grid">
                                            <label class="checkbox-label"><input type="checkbox" name="data_syslog_compress" value="1"
                                                <?php echo ($data_syslog_compress === 1) ? 'checked' : ''; ?>> Compress</label>
                                            <label class="checkbox-label"><input type="checkbox" name="data_syslog_delaycompress" value="1"
                                                <?php echo ($data_syslog_delaycompress === 1) ? 'checked' : ''; ?>> Delay Compress</label>
                                            <label class="checkbox-label"><input type="checkbox" name="data_syslog_missingok" value="1"
                                                <?php echo ($data_syslog_missingok === 1) ? 'checked' : ''; ?>> Missing OK</label>
                                            <label class="checkbox-label"><input type="checkbox" name="data_syslog_notifempty" value="1"
                                                <?php echo ($data_syslog_notifempty === 1) ? 'checked' : ''; ?>> Not If Empty</label>
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="save_syslog_settings" value="Save Syslog Setting">
                                        </div>
                                    </form>
                                </div>

                                <div class="form-section multi-form">
                                    <strong>Time Settings</strong>

                                    <form method="post" action="save_data.php">
                                        <span class="settings-subtitle">Manual</span>
                                        <div class="form-group">
                                            <label for="data_timezone">Timezone (GMT):</label>
                                            <input type="text" id="data_timezone" name="data_timezone"
                                                value="<?php echo htmlspecialchars($data_timezone); ?>" placeholder="7">
                                        </div>
                                        <div class="form-group">
                                            <label for="current_date">Set Current Date:</label>
                                            <input type="date" id="current_date" name="current_date"
                                                value="<?php echo trim(shell_exec('date +"%Y-%m-%d"')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="current_time">Set Current Time:</label>
                                            <input type="time" id="current_time" name="current_time"
                                                value="<?php echo trim(shell_exec('date +"%H:%M"')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="set_date_time" value="Set Date &amp; Time">
                                        </div>
                                    </form>

                                    <form method="post" action="save_data.php">
                                        <span class="settings-subtitle">Sync with PC</span>
                                        <div class="form-group">
                                            <input type="submit" name="sync" value="Synchronize with PC Time">
                                        </div>
                                    </form>

                                    <form method="post" action="save_data.php">
                                        <span class="settings-subtitle">Sync with NTP</span>
                                        <input type="hidden" name="timezone_offset" value="<?php echo htmlspecialchars($data_timezone ?? '7'); ?>">
                                        <div class="form-group">
                                            <label for="ntp_server">NTP Server:</label>
                                            <input type="text" id="ntp_server" name="ntp_server"
                                                value="<?php echo htmlspecialchars($data_ip_address_ntp ?? 'pool.ntp.org'); ?>"
                                                placeholder="pool.ntp.org">
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="sync_ntp" value="Sync Time with NTP">
                                        </div>
                                    </form>
                                </div>

                                <div class="form-section multi-form">
                                    <strong>Schedule Reboot</strong>
                                    <form method="post" action="save_data.php">
                                        <div class="form-group">
                                            <label class="checkbox-label"><input type="checkbox" id="data_reboot_schedule_enable" name="data_reboot_schedule_enable" value="1"
                                                <?php echo ($data_reboot_schedule_enable == 1) ? 'checked' : ''; ?>> Enable Schedule</label>
                                        </div>
                                        <div class="form-group">
                                            <label for="data_reboot_time">Reboot Time:</label>
                                            <input type="time" id="data_reboot_time" name="data_reboot_time"
                                                value="<?php echo htmlspecialchars($data_reboot_time ? $data_reboot_time : '03:00'); ?>">
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="save_reboot_schedule" value="Save Schedule">
                                        </div>
                                    </form>
                                    <form method="post">
                                        <div class="form-group">
                                            <input type="submit" name="reboot" value="Reboot Now" class="btn-danger">
                                        </div>
                                    </form>
                                </div>
                                <?php
									if (isset($_POST['reboot']))
									{
										echo '<script language="javascript">';
										echo 'alert("Logger Akan melakukan Reboot Hardware.")';
										echo '</script>';

										shell_exec("echo 1234 | sudo -S reboot");
										header('refresh:5;url=index.php');
									}
								?>

                                </div>
                            </div>

                            <div class="tab__content-item">
                                <div class="form-section">
                                <strong>ATG Sensor Configuration</strong>
                                <form method="post" action="save_data.php">
                                    <table>
                                        <tr>
                                            <th>#</th>
                                            <th>Enabled</th>
                                            <th>Label</th>
                                            <th>Device</th>
                                            <th>Polling (ms)</th>
                                            <th>Slave ID</th>
                                            <th>Baudrate</th>
                                            <th>DE GPIO</th>
                                            <th>Timeout (ms)</th>
                                            <th>Offset (mm)</th>
                                            <th>Kalibrasi</th>
                                        </tr>
                                        <?php for ($i = 1; $i <= 8; $i++):
                                            $s = isset($atg_sensors[$i]) ? $atg_sensors[$i] : array();
                                            $enabled = !empty($s['enabled']);
                                            $label = htmlspecialchars(isset($s['label']) ? $s['label'] : "Sensor $i");
                                            $device = htmlspecialchars(isset($s['device']) ? $s['device'] : "/dev/ttyACM" . ($i - 1));
                                            $polling = intval(isset($s['polling_interval_ms']) ? $s['polling_interval_ms'] : 5000);
                                            $slave_id = intval(isset($s['slave_id']) ? $s['slave_id'] : 1);
                                            $baudrate = intval(isset($s['baudrate']) ? $s['baudrate'] : 9600);
                                            $de_gpio = intval(isset($s['de_gpio_pin']) ? $s['de_gpio_pin'] : -1);
                                            $timeout = intval(isset($s['read_timeout_ms']) ? $s['read_timeout_ms'] : 3000);
                                            $offset_mm = floatval(isset($s['offset_mm']) ? $s['offset_mm'] : 0);
                                        ?>
                                        <tr>
                                            <td><?php echo $i; ?></td>
                                            <td><input type="checkbox" name="atg_enabled_<?php echo $i; ?>" value="1" <?php echo $enabled ? 'checked' : ''; ?>></td>
                                            <td><input type="text" name="atg_label_<?php echo $i; ?>" value="<?php echo $label; ?>" size="12"></td>
                                            <td><input type="text" name="atg_device_<?php echo $i; ?>" value="<?php echo $device; ?>" size="16"></td>
                                            <td><input type="text" name="atg_polling_<?php echo $i; ?>" value="<?php echo $polling; ?>" size="6"></td>
                                            <td><input type="text" name="atg_slave_id_<?php echo $i; ?>" value="<?php echo $slave_id; ?>" size="4"></td>
                                            <td>
                                                <select name="atg_baudrate_<?php echo $i; ?>">
                                                    <?php foreach (array(9600, 19200, 57600, 115200) as $b): ?>
                                                    <option value="<?php echo $b; ?>" <?php echo ($baudrate == $b) ? 'selected' : ''; ?>><?php echo $b; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="text" name="atg_de_gpio_<?php echo $i; ?>" value="<?php echo $de_gpio; ?>" size="4"></td>
                                            <td><input type="text" name="atg_timeout_<?php echo $i; ?>" value="<?php echo $timeout; ?>" size="6"></td>
                                            <td><input type="text" name="atg_offset_mm_<?php echo $i; ?>" value="<?php echo $offset_mm; ?>" size="8"></td>
                                            <td><a href="atg_calibration.php?sensor=<?php echo $i; ?>">Edit tabel &rarr;</a></td>
                                        </tr>
                                        <?php endfor; ?>
                                    </table>
                                    <div class="form-actions">
                                        <input type="submit" name="save_atg_sensors" value="Save ATG Settings">
                                    </div>
                                </form>
                                </div>
                            </div>

                            <div class="tab__content-item">
                                <div class="form-section">
                                    <strong>MQTT Settings</strong>
                                    <form method="post" action="save_data.php">
                                        <div class="form-group">
                                            <label for="mqtt_broker_host">Broker Host (required):</label>
                                            <input type="text" id="mqtt_broker_host" name="mqtt_broker_host" value="<?php echo htmlspecialchars($data_mqtt_broker_host); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="mqtt_broker_port">Broker Port:</label>
                                            <input type="number" id="mqtt_broker_port" name="mqtt_broker_port" value="<?php echo $data_mqtt_broker_port; ?>" min="1" max="65535">
                                        </div>
                                        <div class="form-group">
                                            <label for="mqtt_username">Username (required):</label>
                                            <input type="text" id="mqtt_username" name="mqtt_username" value="<?php echo htmlspecialchars($data_mqtt_username); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="mqtt_password">Password (required):</label>
                                            <input type="password" id="mqtt_password" name="mqtt_password" value="<?php echo htmlspecialchars($data_mqtt_password); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="mqtt_topic_path">Topic Path:</label>
                                            <input type="text" id="mqtt_topic_path" name="mqtt_topic_path" value="<?php echo htmlspecialchars($data_mqtt_topic_path); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="mqtt_interval">Publish Interval (s):</label>
                                            <input type="number" id="mqtt_interval" name="mqtt_interval" value="<?php echo $data_mqtt_interval; ?>" min="1" max="300">
                                        </div>
                                        <div class="form-group">
                                            <label for="mqtt_company">Company Name:</label>
                                            <input type="text" id="mqtt_company" name="mqtt_company" value="<?php echo htmlspecialchars($data_mqtt_company); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="mqtt_plant_id">Plant ID:</label>
                                            <input type="text" id="mqtt_plant_id" name="mqtt_plant_id" value="<?php echo htmlspecialchars($data_mqtt_plant_id); ?>">
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="save_mqtt_settings" value="Save MQTT Settings">
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="tab__content-item">
                                <div class="form-section">
                                    <strong>Cloud MQTT Settings</strong>
                                    <form method="post" action="save_data.php">
                                        <div class="form-group">
                                            <label for="cloud_topic_path">Cloud Data Topic:</label>
                                            <input type="text" id="cloud_topic_path" name="cloud_topic_path" value="<?php echo htmlspecialchars($data_cloud_topic_path); ?>" placeholder="maintank/atg/cloud/value">
                                        </div>
                                        <div class="form-group">
                                            <label for="cloud_request_topic">Cloud Request:</label>
                                            <input type="text" id="cloud_request_topic" name="cloud_request_topic" value="<?php echo htmlspecialchars($data_cloud_request_topic); ?>" placeholder="maintank/atg/cloud/request">
                                        </div>
                                        <div class="form-group">
                                            <label for="cloud_interval">Publish Interval (s):</label>
                                            <input type="number" id="cloud_interval" name="cloud_interval" value="<?php echo $data_cloud_interval; ?>" min="1" max="300">
                                        </div>
                                        <div class="form-group">
                                            <label class="checkbox-label"><input type="checkbox" id="cloud_enabled" name="cloud_enabled" value="1" <?php echo $data_cloud_enabled ? 'checked' : ''; ?>> Enable Cloud MQTT</label>
                                        </div>
                                        <div class="form-group">
                                            <label class="checkbox-label"><input type="checkbox" id="cloud_store_on_fail" name="cloud_store_on_fail" value="1" <?php echo $data_cloud_store_on_fail ? 'checked' : ''; ?>> Save to database when ACK fails</label>
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="save_cloud_mqtt_settings" value="Save Cloud MQTT Settings">
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="tab__content-item">
                                <div class="form-section">
                                    <strong>Pending Cloud MQTT Data</strong>
                                    <?php
                                        $cloud_pending_count = 0;
                                        $cloud_pending_rows = array();
                                        $result_pending_count = $db->query("SELECT COUNT(*) AS c FROM tbl_atg_cloud_pending");
                                        if ($result_pending_count) {
                                            $row_count = $result_pending_count->fetchArray(SQLITE3_ASSOC);
                                            $cloud_pending_count = $row_count ? intval($row_count['c']) : 0;
                                        }
                                        $result_pending_rows = $db->query("SELECT id, payload, created_at FROM tbl_atg_cloud_pending ORDER BY id ASC LIMIT 200");
                                        if ($result_pending_rows) {
                                            while ($row_pending = $result_pending_rows->fetchArray(SQLITE3_ASSOC)) {
                                                $cloud_pending_rows[] = $row_pending;
                                            }
                                        }
                                    ?>
                                    <div class="form-actions">
                                        <span>Total pending: <strong><?php echo $cloud_pending_count; ?></strong></span>
                                        <a href="download_cloud_pending.php" class="btn">Download CSV</a>
                                    </div>
                                    <?php if (empty($cloud_pending_rows)): ?>
                                    <p class="form-note">No pending data.</p>
                                    <?php else: ?>
                                    <div class="table-scroll" style="max-height: 400px; overflow-y: auto; margin-top: 12px;">
                                        <table>
                                            <tr><th>#</th><th>ID</th><th>Created At (UTC)</th><th>Payload</th></tr>
                                            <?php $row_num = 0; foreach ($cloud_pending_rows as $row_pending): $row_num++; ?>
                                            <tr>
                                                <td><?php echo $row_num; ?></td>
                                                <td><?php echo intval($row_pending['id']); ?></td>
                                                <td><?php echo htmlspecialchars($row_pending['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($row_pending['payload'], 0, 120)); ?><?php echo (strlen($row_pending['payload']) > 120) ? '...' : ''; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                    <?php if ($cloud_pending_count > 200): ?>
                                    <p class="form-note">Showing the oldest 200 of <?php echo $cloud_pending_count; ?> rows. Use "Download CSV" for the full list.</p>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tab__content-item">
                                <div class="form-section">
                                <strong>ATG Sensor Metadata (for MQTT)</strong>
                                <form method="post" action="save_data.php">
                                    <table>
                                        <tr>
                                            <th>#</th>
                                            <th>Location (slocn)</th>
                                            <th>Sensor Number</th>
                                            <th>Capacity (liters)</th>
                                        </tr>
                                        <?php for ($i = 1; $i <= 8; $i++):
                                            $s = isset($atg_sensors[$i]) ? $atg_sensors[$i] : array();
                                            $slocn = htmlspecialchars(isset($s['slocn']) ? $s['slocn'] : "FS-0{$i}");
                                            $sensor_number = htmlspecialchars(isset($s['sensor_number']) ? $s['sensor_number'] : str_pad($i, 2, '0', STR_PAD_LEFT));
                                            $capacity_liters = floatval(isset($s['capacity_liters']) ? $s['capacity_liters'] : 40000);
                                        ?>
                                        <tr>
                                            <td><?php echo $i; ?></td>
                                            <td><input type="text" name="sensor_slocn_<?php echo $i; ?>" value="<?php echo $slocn; ?>" size="20"></td>
                                            <td><input type="text" name="sensor_number_<?php echo $i; ?>" value="<?php echo $sensor_number; ?>" size="10"></td>
                                            <td><input type="number" name="sensor_capacity_<?php echo $i; ?>" value="<?php echo $capacity_liters; ?>" step="0.1" size="10"></td>
                                        </tr>
                                        <?php endfor; ?>
                                    </table>
                                    <div class="form-actions">
                                        <input type="submit" name="save_sensor_metadata" value="Save Sensor Metadata">
                                    </div>
                                </form>
                                </div>
                            </div>

                            <!-- pss-->
                            <?php
								$show_pss_form = false;
								$wrong_password = false;

								// Cek apakah password sudah dimasukkan
								if (isset($_POST['check_pss_password'])) {
									$entered_password = $_POST['pss_password'];
									$correct_password = "adminqims123"; // Ganti sesuai keinginan

									if ($entered_password === $correct_password) {
										$show_pss_form = true;
									} else {
										$wrong_password = true;
									}
								}
							?>

                            <div class="tab__content-item">
                                <div class="form-section">
								<?php if (!$show_pss_form): ?>
                                    <strong>Protected Settings</strong>
									<form method="post">
										<?php if ($wrong_password): ?>
                                            <p class="form-note" style="color:#dc3545;">Incorrect password. Please try again.</p>
										<?php endif; ?>
                                        <div class="form-group">
                                            <label for="pss_password">Password:</label>
                                            <input type="password" id="pss_password" name="pss_password" required>
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="check_pss_password" value="Submit">
                                        </div>
									</form>
								<?php else: ?>
                                    <strong>PSS Settings</strong>
									<form method="post" action="save_data.php" onsubmit="return validatePSSForm();">
                                        <div class="form-group">
                                            <label for="pss_mqtt_host">MQTT Host:</label>
                                            <input type="text" id="pss_mqtt_host" name="pss_mqtt_host" value="<?php echo htmlspecialchars($pss_mqtt_host); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="tenant">Tenant:</label>
                                            <input type="text" id="tenant" name="tenant" value="<?php echo htmlspecialchars($pss_tenant); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="sub_tenant">Sub Tenant:</label>
                                            <input type="text" id="sub_tenant" name="sub_tenant" value="<?php echo htmlspecialchars($pss_sub_tenant); ?>">
                                        </div>
                                        <div class="form-group">
                                            <input type="submit" name="save_pss_setting" value="Save Changes">
                                        </div>
									</form>
								<?php endif; ?>
                                </div>
							</div>




                        </div>
                    </div>
                    <!--					<div class="tab__content-item tab">
						<div class="tab__list">
							<div class="tab__item">
								Time Settings
							</div>

							<div class="tab__item">
								Reboot
							</div>
						</div>
						<div class="tab__content">
							<div class="tab__content-item">
								<strong>Time Settings</strong><br>
								<form method="post" action="save_data.php" >
									<label for="current_date">Set Current Date </label>
									<input type="date" id="current_date" name="current_date" value=<?php echo $get_current_date;?>><br>
									<label for="current_time">Set Current Time </label>
									<input type="time" id="current_time" name="current_time" value=<?php echo $get_current_time;?>><br>
									<label for="data_timezone">Set Timezone (GMT +) </label>
									<input type="text" id="data_timezone" name="data_timezone" value=<?php echo $data_timezone;?>>
									
									<input type="submit" name="set_date_time"  value=" Set Date Time "><br><br><br> 
								</form>	
								<strong>Synchronize Time</strong><br>
								<form method="post" >
									<label for="comport1">Synchronize with PC Time  </label> 
									<input type="submit" name="sync"  value=" Synchronize Time "><br>
								</form>
	 							<?php
									if (isset($_POST['sync']))
									{
										$local_pc_datetime= $_COOKIE['myJavascriptVar'];
										shell_exec("echo 1234 | sudo -S date -s '$local_pc_datetime'");	
										header('location: home.php');
									}
								?> 
								<label for="comport1">Synchronize with NTP  </label><input type="text" id="lname" name="lname" value="<?php echo $data_ip_address_ntp;?>"><input type="submit" value="Synchronize Time"><br><br>
							</div>
							<div class="tab__content-item">
								<strong>Reboot / Reset</strong><br>
								<form method="post" >
									<label for="comport1">Reboot </label>
									<input type="submit" name="reboot"  value="Reboot"><br>
								</form>
								<?php
									if (isset($_POST['reboot']))
									{
										echo '<script language="javascript">';
										echo 'alert("Logger Akan melakukan Reboot Hardware.")';
										echo '</script>';

										shell_exec("echo 1234 | sudo -S reboot");	
										header('refresh:5;url=index.php');
									}
								?> 
								<?php
								if ($privilege_user == "Superuser")
								{
								?>
								<label for="comport1">Schedule Reboot </label><input type="time" id="current_date" name="current_date"><input type="submit"><br>
								<label for="comport1">Restore Factory Settings </label><input type="submit" value="Restore"><br>
															<?php
							}
							?>
							</div>
<!--							<div class="tab__content-item">
								<strong>Download Files</strong><br>
								<iframe src="file_manager.php"  id="iFrame1" width="900" height="500">
								</iframe>

							</div>	
							<?php
							if ($privilege_user == "Superuser")
							{
							?>						 	
<!--							<div class="tab__content-item">
								<form action="upload.php" method="post" enctype="multipart/form-data">
									<strong>Update Firmware</strong><br>
									<label for="update_firmware">Current Version </label><?php echo $file_on_folder_firmware;?><br>
									<label for="update_firmware">File Update </label>
									<input type="file" id="update_firmware" name="update_firmware">
									<input type="submit" value="Update"><br>
								</form>
									<br><br><br>
								<form action="upload_license.php" method="post" enctype="multipart/form-data">
									<strong>Add License</strong><br>
									<label for="update_license">Current License </label><?php echo $file_on_folder_license;?><br>
									<label for="comport1">License for Apps : </label>
									<select name="data_jenis_apps" id="data_jenis_apps" >
										<option value="vims_eth" <?php if($data_jenis_apps=="vims_eth") echo 'selected="selected"'; ?> >VIMS_ETH</option>
										<option value="tyre" <?php if($data_jenis_apps=="tyre") echo 'selected="selected"'; ?> >TYRE</option>
									</select><br>
									<label for="update_license">License File </label>
									<input type="file" id="update_license" name="update_license">
									<input type="submit"  name="upload_license" value="Add License"><br>
								</form>
								
							</div>
							<?php
							}
							?>
							<div class="tab__content-item"> 
								<strong>Guidance</strong>
								<br>
								 <embed type="application/pdf" src="assets/guidance.pdf" width="900" height="500"></embed>								
							</div>	-->
                </div>
            </div>
        </div>
        </div>
        </div>
    </section>
    <script
        src="https://cpwebassets.codepen.io/assets/common/stopExecutionOnTimeout-2c7831bb44f98c1391d6a4ffda0e1fd302503391ca806e7fcc7b9b87197aec26.js">
    </script>
    <script id="rendered-js">
    // reference from https://www.digitalocean.com/community/tutorials/js-tabs

    const tabs = document.querySelectorAll(".tab");

    function tabify(tab, tabKey) {
        const tabList = tab.querySelector(".tab__list");

        if (tabList) {
            const tabItems = [...tabList.children];
            const tabContent = tab.querySelector(".tab__content");
            const tabContentItems = [...tabContent.children];
            const storageKey = tabKey ? "tabState_" + tabKey : null;
            let tabIndex = 0;

            const savedIndex = storageKey ? parseInt(localStorage.getItem(storageKey), 10) : NaN;
            if (!isNaN(savedIndex) && savedIndex >= 0 && savedIndex < tabItems.length) {
                tabIndex = savedIndex;
            } else {
                tabIndex = tabItems.findIndex((item, index) => {
                    return [...item.classList].indexOf("is--active") > -1;
                });
                tabIndex > -1 ? tabIndex = tabIndex : tabIndex = 0;
            }

            function setTab(index) {
                tabItems.forEach((x, index) => x.classList.remove("is--active"));
                tabContentItems.forEach((x, index) => x.classList.remove("is--active"));

                tabItems[index].classList.add("is--active");
                tabContentItems[index].classList.add("is--active");

                if (storageKey) {
                    localStorage.setItem(storageKey, index);
                }
            }

            tabItems.forEach((x, index) =>
                x.addEventListener("click", () => setTab(index)));

            setTab(tabIndex);
        }
    }

    tabs.forEach(tab => tabify(tab, tab.id || null));
    //# sourceURL=pen.js
    </script>
    <script>
    (function() {
        const el = document.getElementById('live_datetime');
        if (!el) return;

        const parts = el.dataset.serverTime.match(/(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/);
        if (!parts) return;

        let serverEpochMs = Date.UTC(+parts[1], +parts[2] - 1, +parts[3], +parts[4], +parts[5], +parts[6]);
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        function pad(n) { return n.toString().padStart(2, '0'); }

        function render() {
            const d = new Date(serverEpochMs);
            el.textContent = pad(d.getUTCDate()) + ' ' + months[d.getUTCMonth()] + ' ' + d.getUTCFullYear() + ' ' +
                pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds());
        }

        setInterval(() => {
            serverEpochMs += 1000;
            render();
        }, 1000);
    })();
    </script>

    <script>
		const host = "<?php echo $pss_mqtt_host; ?>";
		const port = "<?php echo $pss_mqtt_port; ?>";

		function checkPSSStatus() {
			fetch(`check_pss.php?host=${host}&port=${port}`)
				.then(response => response.text())
				.then(status => {
					const statusCell = document.getElementById('pss_status');
					statusCell.innerText = status;
					statusCell.className = (status.trim() === 'Online') ? 'status-online' : 'status-offline';
				})
				.catch(error => {
					const statusCell = document.getElementById('pss_status');
					statusCell.innerText = 'Error';
					statusCell.className = 'status-offline';
				});
		}

		checkPSSStatus();
		setInterval(checkPSSStatus, 5000);
	</script>
    <script>
		function fetchAtgReadings() {
			fetch('get_atg_readings.php')
				.then(response => response.json())
				.then(sensors => {
					let enabled = 0, online = 0;
					sensors.forEach(s => {
						const row = document.getElementById('atg_row_' + s.id);
						if (!row) return;

						const isEnabled = s.enabled === 1;
						if (isEnabled) enabled++;
						if (isEnabled && s.ok === 1) online++;

						row.className = isEnabled ? '' : 'atg-disabled';
						row.querySelector('.atg-label').innerText = s.label || ('Sensor ' + s.id);
						row.querySelector('.atg-device').innerText = s.device || '—';

						const statusCell = row.querySelector('.atg-status');
						if (!isEnabled) {
							statusCell.innerText = 'Disabled';
							statusCell.className = 'atg-status atg-disabled';
						} else if (s.ok === 1) {
							statusCell.innerText = 'Online';
							statusCell.className = 'atg-status status-online';
						} else {
							statusCell.innerText = s.error_msg || 'Offline';
							statusCell.className = 'atg-status status-offline';
						}

						row.querySelector('.atg-offset').innerText =
							isEnabled ? s.offset_mm.toFixed(1) : '—';
						row.querySelector('.atg-probe').innerText =
							(isEnabled && s.probe_mm != null) ? s.probe_mm.toFixed(1) : '—';
						row.querySelector('.atg-sounding').innerText =
							(isEnabled && s.sounding_mm != null) ? s.sounding_mm.toFixed(1) : '—';
						const volCell = row.querySelector('.atg-volume');
						if (isEnabled && s.volume != null) {
							volCell.innerText = s.volume.toFixed(1);
						} else {
							volCell.innerText = '—';
						}
						row.querySelector('.atg-water').innerText =
							(isEnabled && s.water_mm != null) ? s.water_mm.toFixed(1) : '—';
						row.querySelector('.atg-volume-water').innerText =
							(isEnabled && s.volume_water_l != null) ? s.volume_water_l.toFixed(1) : '—';
						row.querySelector('.atg-volume-total').innerText =
							(isEnabled && s.volume_total_l != null) ? s.volume_total_l.toFixed(1) : '—';
						row.querySelector('.atg-temp').innerText =
							(isEnabled && s.fuel_temp_c != null) ? s.fuel_temp_c.toFixed(1) : '—';
						row.querySelector('.atg-updated').innerText = s.updated_at || '—';
					});

					const summary = document.getElementById('atg_status_summary');
					if (summary) {
						if (enabled === 0) {
							summary.innerText = 'No sensors enabled';
							summary.className = 'atg-disabled';
						} else {
							summary.innerText = online + '/' + enabled + ' online';
							summary.className = (online === enabled) ? 'status-online' : 'status-offline';
						}
					}
				})
				.catch(() => {
					const summary = document.getElementById('atg_status_summary');
					if (summary) {
						summary.innerText = 'Error';
						summary.className = 'status-offline';
					}
				});
		}

		fetchAtgReadings();
		setInterval(fetchAtgReadings, 5000);
	</script>
    <script>
    function validatePSSForm() {
        const tenant = document.getElementById('tenant').value;
        const subTenant = document.getElementById('sub_tenant').value;

        if (tenant.trim() === '') {
            alert('Tenant tidak boleh kosong!');
            return false;
        }
        if (/\s/.test(tenant)) {
            alert('Tenant tidak boleh mengandung spasi!');
            return false;
        }
        if (/\s/.test(subTenant)) {
            alert('Sub Tenant tidak boleh mengandung spasi!');
            return false;
        }
        return true;
    }
    </script>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script>
    // === CONFIGURATION ===
    const brokerUrl = "ws://192.168.7.2:8083"; // change to your broker
    const topic = "FMS/RAWDATAUNIT";

    // === CONNECT TO MQTT BROKER ===
    const clientId = "webClient_" + Math.random().toString(16).substr(2, 8);
    const client = mqtt.connect(brokerUrl, { clientId });

    const messageContainer = document.getElementById("mqttMessages");

    client.on("connect", function () {
        messageContainer.innerHTML = "Connected to MQTT broker\n";
        client.subscribe(topic, function (err) {
        if (!err) {
            messageContainer.innerHTML += `Subscribed to topic: ${topic}\n\n`;
        } else {
            messageContainer.innerHTML = "Subscribe error: " + err + "\n";
        }
        });
    });

    client.on("message", function (topic, message) {
        const now = new Date().toLocaleTimeString();
        // 🟢 Replace previous content, don't append
        messageContainer.innerHTML =
        `[${now}] ${topic}: ${message.toString()}`;
    });

    client.on("error", function (error) {
        messageContainer.innerHTML = "❌ Connection error: " + error + "\n";
    });

    client.on("reconnect", function () {
        messageContainer.innerHTML = "🔁 Reconnecting...\n";
    });
    </script>




    <center>&#169; Qimtronics</center>
</body>

</html>
