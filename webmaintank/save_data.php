<?php
session_start();

if (empty($_SESSION['username'])) {
	header('Location: index.php');
	exit;
}

require_once 'conn.php';
require_once 'reboot_schedule_helper.php';
require_once 'syslog_logrotate_helper.php';
require_once 'conn_pss.php';
require_once 'init_atg_table.php';
require_once 'init_logger_settings.php';

function save_redirect($params = '') {
	header('Location: home.php' . ($params !== '' ? '?' . $params : ''));
	exit;
}

try {
	ensure_atg_tables($conn);
	ensure_logger_settings_columns($conn);
} catch (Exception $e) {
	error_log('save_data bootstrap: ' . $e->getMessage());
}

if (isset($_POST['save_general_settings'])) {
	try {
		$data_unitname = $_POST['data_unitname'];
		$data_ip_address_logger = $_POST['data_ip_address_logger'];
		$data_subnetmask_logger = $_POST['data_subnetmask_logger'];
		$data_gateway_logger = $_POST['data_gateway_logger'];

		$query = "UPDATE logger_settings SET unitname = :data_unitname,
											 ip_address_logger = :data_ip_address_logger,
											 subnetmask_logger = :data_subnetmask_logger,
											 gateway_logger = :data_gateway_logger";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_unitname', $data_unitname);
		$stmt->bindParam(':data_ip_address_logger', $data_ip_address_logger);
		$stmt->bindParam(':data_subnetmask_logger', $data_subnetmask_logger);
		$stmt->bindParam(':data_gateway_logger', $data_gateway_logger);
		$stmt->execute();

		$etchosts = "127.0.0.1   localhost\n127.0.1.1   $data_unitname\n";
		file_put_contents("/var/www/html/etchosts", $etchosts);
		shell_exec("sudo chmod 666 /var/www/html/etchosts");
		shell_exec("sudo chown user:user /var/www/html/etchosts");
		shell_exec("sudo cp /var/www/html/etchosts /etc/hosts");

		$eth0_network = "[Match]\nName=eth0\nType=ether\n\n[Link]\nRequiredForOnline=yes\n\n[Network]\nDHCP=no\nGateway=$data_gateway_logger\nDNS=8.8.8.8\n\n[Address]\nAddress=$data_ip_address_logger/24\n\n[Address]\nAddress=192.168.50.2/30\n\n";
		file_put_contents("/var/www/html/eth0.network", $eth0_network);
		shell_exec("sudo chmod 666 /var/www/html/eth0.network");
		shell_exec("sudo chown user:user /var/www/html/eth0.network");
		shell_exec("sudo cp /var/www/html/eth0.network /etc/systemd/network/eth0.network");

		$text_isian_parameter = "source /etc/network/interfaces.d/*\n\nauto lo\niface lo inet loopback\n\nauto eth0\niface eth0 inet static\n		address $data_ip_address_logger\n		netmask $data_subnetmask_logger\n		gateway $data_gateway_logger\n		dns-nameserver 192.168.1.1\n		dns-nameserver 8.8.8.8\n#IP Alias\nauto eth0:1\niface eth0:1 inet static\n		address 192.168.50.2\n		netmask 255.255.255.252\n\n\nauto usb0\niface usb0 inet static\n		address 192.168.7.2\n		netmask 255.255.255.252\n		gateway 192.168.7.1\n";
		shell_exec("echo 1234 | sudo -S chown user:user /home/user/Apps/interfaces");
		shell_exec("echo 1234 | sudo -S chmod 666 /home/user/Apps/interfaces");
		file_put_contents("/home/user/Apps/interfaces", $text_isian_parameter);
		shell_exec("echo 1234 | sudo -S cp /home/user/Apps/interfaces /etc/network/interfaces");
		shell_exec("echo 1234 | sudo -S rm /home/user/Apps/interfaces");

		save_redirect('saved=general');
	} catch (Exception $e) {
		error_log('save_general_settings: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_syslog_settings'])) {
	try {
		$data_syslog_rotate_count = max(1, intval($_POST['data_syslog_rotate_count']));
		$data_syslog_rotate_frequency = $_POST['data_syslog_rotate_frequency'];
		$data_syslog_max_size = trim($_POST['data_syslog_max_size']);
		$data_syslog_compress = isset($_POST['data_syslog_compress']) ? 1 : 0;
		$data_syslog_delaycompress = isset($_POST['data_syslog_delaycompress']) ? 1 : 0;
		$data_syslog_missingok = isset($_POST['data_syslog_missingok']) ? 1 : 0;
		$data_syslog_notifempty = isset($_POST['data_syslog_notifempty']) ? 1 : 0;

		if (!in_array($data_syslog_rotate_frequency, array('daily', 'weekly', 'monthly'), true)) {
			$data_syslog_rotate_frequency = 'daily';
		}
		if ($data_syslog_max_size !== '' && !preg_match('/^\d+[kKmMgG]?$/', $data_syslog_max_size)) {
			$data_syslog_max_size = '';
		}

		$query = "UPDATE logger_settings SET
					syslog_rotate_count = :data_syslog_rotate_count,
					syslog_rotate_frequency = :data_syslog_rotate_frequency,
					syslog_max_size = :data_syslog_max_size,
					syslog_compress = :data_syslog_compress,
					syslog_delaycompress = :data_syslog_delaycompress,
					syslog_missingok = :data_syslog_missingok,
					syslog_notifempty = :data_syslog_notifempty";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_syslog_rotate_count', $data_syslog_rotate_count);
		$stmt->bindParam(':data_syslog_rotate_frequency', $data_syslog_rotate_frequency);
		$stmt->bindParam(':data_syslog_max_size', $data_syslog_max_size);
		$stmt->bindParam(':data_syslog_compress', $data_syslog_compress);
		$stmt->bindParam(':data_syslog_delaycompress', $data_syslog_delaycompress);
		$stmt->bindParam(':data_syslog_missingok', $data_syslog_missingok);
		$stmt->bindParam(':data_syslog_notifempty', $data_syslog_notifempty);
		$stmt->execute();

		update_rsyslog_logrotate_config(array(
			'syslog_rotate_count' => $data_syslog_rotate_count,
			'syslog_rotate_frequency' => $data_syslog_rotate_frequency,
			'syslog_max_size' => $data_syslog_max_size,
			'syslog_compress' => $data_syslog_compress,
			'syslog_delaycompress' => $data_syslog_delaycompress,
			'syslog_missingok' => $data_syslog_missingok,
			'syslog_notifempty' => $data_syslog_notifempty,
		));

		save_redirect('saved=syslog');
	} catch (Exception $e) {
		error_log('save_syslog_settings: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_reboot_schedule'])) {
	try {
		$data_reboot_schedule_enable = isset($_POST['data_reboot_schedule_enable']) ? 1 : 0;
		$data_reboot_time = $_POST['data_reboot_time'];

		$query = "UPDATE logger_settings
				  SET reboot_time = :data_reboot_time,
				      schedule_reboot_enable = :data_reboot_schedule_enable";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_reboot_time', $data_reboot_time);
		$stmt->bindParam(':data_reboot_schedule_enable', $data_reboot_schedule_enable, PDO::PARAM_INT);
		$stmt->execute();

		update_reboot_schedule_cron_config($data_reboot_schedule_enable === 1, $data_reboot_time);

		save_redirect('saved=reboot');
	} catch (Exception $e) {
		error_log('save_reboot_schedule: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_string_variable'])) {
	try {
		$data_str_DI0 = $_POST['data_str_DI0'];
		$data_str_DI1 = $_POST['data_str_DI1'];
		$data_str_DI2 = $_POST['data_str_DI2'];
		$data_str_DI3 = $_POST['data_str_DI3'];
		$data_str_DI4 = $_POST['data_str_DI4'];
		$data_str_DI5 = $_POST['data_str_DI5'];
		$data_str_DO0 = $_POST['data_str_DO0'];
		$data_str_DO1 = $_POST['data_str_DO1'];
		$data_str_AI0 = $_POST['data_str_AI0'];
		$data_str_AI1 = $_POST['data_str_AI1'];
		$data_str_PARAMKEY0 = $_POST['data_str_PARAMKEY0'];
		$data_str_PARAMKEY1 = $_POST['data_str_PARAMKEY1'];
		$data_str_PARAMKEY2 = $_POST['data_str_PARAMKEY2'];
		$data_str_PARAMKEY3 = $_POST['data_str_PARAMKEY3'];
		$data_str_PARAMKEY4 = $_POST['data_str_PARAMKEY4'];
		$data_str_PARAMKEY5 = $_POST['data_str_PARAMKEY5'];
		$data_str_PARAMKEY6 = $_POST['data_str_PARAMKEY6'];
		$data_str_PARAMKEY7 = $_POST['data_str_PARAMKEY7'];

		$query = "UPDATE TBL_ZETHA_PARAMKEY SET DI0 = :data_str_DI0,
												DI1 = :data_str_DI1,
												DI2 = :data_str_DI2,
												DI3 = :data_str_DI3,
												DI4 = :data_str_DI4,
												DI5 = :data_str_DI5,
												DO0 = :data_str_DO0,
												DO1 = :data_str_DO1,
												AIN0 = :data_str_AI0,
												AIN1 = :data_str_AI1,
												PARAMKEY0 = :data_str_PARAMKEY0,
												PARAMKEY1 = :data_str_PARAMKEY1,
												PARAMKEY2 = :data_str_PARAMKEY2,
												PARAMKEY3 = :data_str_PARAMKEY3,
												PARAMKEY4 = :data_str_PARAMKEY4,
												PARAMKEY5 = :data_str_PARAMKEY5,
												PARAMKEY6 = :data_str_PARAMKEY6,
												PARAMKEY7 = :data_str_PARAMKEY7";

		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_str_DI0', $data_str_DI0);
		$stmt->bindParam(':data_str_DI1', $data_str_DI1);
		$stmt->bindParam(':data_str_DI2', $data_str_DI2);
		$stmt->bindParam(':data_str_DI3', $data_str_DI3);
		$stmt->bindParam(':data_str_DI4', $data_str_DI4);
		$stmt->bindParam(':data_str_DI5', $data_str_DI5);
		$stmt->bindParam(':data_str_DO0', $data_str_DO0);
		$stmt->bindParam(':data_str_DO1', $data_str_DO1);
		$stmt->bindParam(':data_str_AI0', $data_str_AI0);
		$stmt->bindParam(':data_str_AI1', $data_str_AI1);
		$stmt->bindParam(':data_str_PARAMKEY0', $data_str_PARAMKEY0);
		$stmt->bindParam(':data_str_PARAMKEY1', $data_str_PARAMKEY1);
		$stmt->bindParam(':data_str_PARAMKEY2', $data_str_PARAMKEY2);
		$stmt->bindParam(':data_str_PARAMKEY3', $data_str_PARAMKEY3);
		$stmt->bindParam(':data_str_PARAMKEY4', $data_str_PARAMKEY4);
		$stmt->bindParam(':data_str_PARAMKEY5', $data_str_PARAMKEY5);
		$stmt->bindParam(':data_str_PARAMKEY6', $data_str_PARAMKEY6);
		$stmt->bindParam(':data_str_PARAMKEY7', $data_str_PARAMKEY7);
		$stmt->execute();

		save_redirect('saved=string');
	} catch (Exception $e) {
		error_log('save_string_variable: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_parameter_settings'])) {
	try {
		$data_ZETHA_OFFSET0 = $_POST['data_ZETHA_OFFSET0'];
		$data_ZETHA_OFFSET1 = $_POST['data_ZETHA_OFFSET1'];
		$data_ZETHA_GAIN0 = $_POST['data_ZETHA_GAIN0'];
		$data_ZETHA_GAIN1 = $_POST['data_ZETHA_GAIN1'];

		$query = "UPDATE TBL_ZETHA_IO_SETTING SET OFFSET0 = :data_ZETHA_OFFSET0, GAIN0 = :data_ZETHA_GAIN0, OFFSET1 = :data_ZETHA_OFFSET1, GAIN1 = :data_ZETHA_GAIN1";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_ZETHA_OFFSET0', $data_ZETHA_OFFSET0);
		$stmt->bindParam(':data_ZETHA_OFFSET1', $data_ZETHA_OFFSET1);
		$stmt->bindParam(':data_ZETHA_GAIN0', $data_ZETHA_GAIN0);
		$stmt->bindParam(':data_ZETHA_GAIN1', $data_ZETHA_GAIN1);
		$stmt->execute();

		save_redirect('saved=parameter');
	} catch (Exception $e) {
		error_log('save_parameter_settings: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_ip_server_settings'])) {
	try {
		$data_ip_address_server = $_POST['data_ip_address_server'];
		$query = "UPDATE logger_settings SET ip_address_server = :data_ip_address_server";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_ip_address_server', $data_ip_address_server);
		$stmt->execute();
		save_redirect('saved=ipserver');
	} catch (Exception $e) {
		error_log('save_ip_server_settings: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_ip_vhms_settings'])) {
	try {
		$data_ip_address_vhms = $_POST['data_ip_address_vhms'];
		$query = "UPDATE logger_settings SET ip_vhms = :data_ip_address_vhms";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_ip_address_vhms', $data_ip_address_vhms);
		$stmt->execute();
		save_redirect('saved=vhms');
	} catch (Exception $e) {
		error_log('save_ip_vhms_settings: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_digital_output_settings'])) {
	try {
		$data_str_DO0 = $_POST['data_str_DO0'] == 'ON' ? 1 : 0;
		$data_str_DO1 = $_POST['data_str_DO1'] == 'ON' ? 1 : 0;

		$fileDO0 = fopen("/home/user/Apps/log/DO0.txt", "w");
		if ($fileDO0) {
			fwrite($fileDO0, $data_str_DO0);
			fclose($fileDO0);
		}

		$fileDO1 = fopen("/home/user/Apps/log/DO1.txt", "w");
		if ($fileDO1) {
			fwrite($fileDO1, $data_str_DO1);
			fclose($fileDO1);
		}

		$query = "UPDATE TBL_ZETHA_REALTIME SET DO0 = :data_str_DO0, DO1 = :data_str_DO1";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_str_DO0', $data_str_DO0);
		$stmt->bindParam(':data_str_DO1', $data_str_DO1);
		$stmt->execute();

		save_redirect('saved=do');
	} catch (Exception $e) {
		error_log('save_digital_output_settings: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['set_date_time'])) {
	try {
		$data_timezone = $_POST['data_timezone'];
		$query = "UPDATE logger_settings SET timezone = :data_timezone";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':data_timezone', $data_timezone);
		$stmt->execute();

		$set_current_date = $_POST['current_date'];
		$set_current_time = $_POST['current_time'];
		$newDate = date("d M Y", strtotime($set_current_date));
		$newDateTime = $newDate . ' ' . $set_current_time;
		shell_exec("echo 1234 | sudo -S date -s '$newDateTime'");
		shell_exec("echo 1234 | sudo -S hwclock -w -f /dev/rtc0");
		shell_exec("echo 1234 | sudo -S hwclock -w -f /dev/rtc1");

		save_redirect('saved=datetime');
	} catch (Exception $e) {
		error_log('set_date_time: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['sync_ntp'])) {
	try {
		$ntp_server = $_POST['ntp_server'] ?? 'pool.ntp.org';
		$timezone_input = trim($_POST['timezone_offset'] ?? '7');

		// Validate timezone format (user can input: 7, -5, +7, 5:30, etc.)
		if (!preg_match('/^[+-]?\d{1,2}(:\d{2})?$/', $timezone_input)) {
			throw new Exception('Invalid timezone format. Use 7, -5, 5:30, etc.');
		}

		// Save timezone to database as-is
		$query = "UPDATE logger_settings SET timezone = :timezone";
		$stmt = $conn->prepare($query);
		$stmt->bindParam(':timezone', $timezone_input);
		$stmt->execute();

		// Prepare timezone for NTP: add + prefix if not already present
		$tz_for_ntp = $timezone_input;
		if (!preg_match('/^[+-]/', $tz_for_ntp)) {
			$tz_for_ntp = '+' . $tz_for_ntp;
		}

		// Sync time with NTP server
		// Using ntpdate if available, otherwise timedatectl
		$ntp_cmd = "echo 1234 | sudo -S ntpdate -s " . escapeshellarg($ntp_server) . " 2>&1";
		$ntp_result = shell_exec($ntp_cmd);

		// Also try timedatectl as fallback
		if (strpos($ntp_result, 'not found') !== false || strpos($ntp_result, 'command not found') !== false) {
			$ntp_cmd = "echo 1234 | sudo -S timedatectl set-ntp true 2>&1";
			shell_exec($ntp_cmd);
		}

		// Set system timezone
		$tz_cmd = "echo 1234 | sudo -S timedatectl set-timezone 'UTC' 2>&1";
		shell_exec($tz_cmd);

		// Write to hardware clock
		shell_exec("echo 1234 | sudo -S hwclock -w -f /dev/rtc0 2>&1");
		shell_exec("echo 1234 | sudo -S hwclock -w -f /dev/rtc1 2>&1");

		save_redirect('saved=ntp');
	} catch (Exception $e) {
		error_log('sync_ntp: ' . $e->getMessage());
		save_redirect('error=' . urlencode($e->getMessage()));
	}
}

if (isset($_POST['save_atg_sensors'])) {
	try {
		for ($i = 1; $i <= 8; $i++) {
			$enabled = isset($_POST["atg_enabled_$i"]) ? 1 : 0;
			$label = isset($_POST["atg_label_$i"]) ? $_POST["atg_label_$i"] : "Sensor $i";
			$device = isset($_POST["atg_device_$i"]) ? $_POST["atg_device_$i"] : '';
			$slave_id = intval(isset($_POST["atg_slave_id_$i"]) ? $_POST["atg_slave_id_$i"] : 1);
			$baudrate = intval(isset($_POST["atg_baudrate_$i"]) ? $_POST["atg_baudrate_$i"] : 9600);
			$polling = intval(isset($_POST["atg_polling_$i"]) ? $_POST["atg_polling_$i"] : 5000);
			$de_gpio = intval(isset($_POST["atg_de_gpio_$i"]) ? $_POST["atg_de_gpio_$i"] : -1);
			$timeout = intval(isset($_POST["atg_timeout_$i"]) ? $_POST["atg_timeout_$i"] : 3000);
			$offset_mm = floatval(isset($_POST["atg_offset_mm_$i"]) ? $_POST["atg_offset_mm_$i"] : 0);

			if ($polling < 500) $polling = 500;
			if ($timeout < 500) $timeout = 500;

			$query = "UPDATE tbl_atg_sensor SET
						label = :label,
						enabled = :enabled,
						device = :device,
						slave_id = :slave_id,
						baudrate = :baudrate,
						polling_interval_ms = :polling,
						de_gpio_pin = :de_gpio,
						read_timeout_ms = :timeout,
						offset_mm = :offset_mm,
						zero_offset_mm = 0
					  WHERE id = :id";
			$stmt = $conn->prepare($query);
			$stmt->bindValue(':label', $label);
			$stmt->bindValue(':enabled', $enabled, PDO::PARAM_INT);
			$stmt->bindValue(':device', $device);
			$stmt->bindValue(':slave_id', $slave_id, PDO::PARAM_INT);
			$stmt->bindValue(':baudrate', $baudrate, PDO::PARAM_INT);
			$stmt->bindValue(':polling', $polling, PDO::PARAM_INT);
			$stmt->bindValue(':de_gpio', $de_gpio, PDO::PARAM_INT);
			$stmt->bindValue(':timeout', $timeout, PDO::PARAM_INT);
			$stmt->bindValue(':offset_mm', $offset_mm);
			$stmt->bindValue(':id', $i, PDO::PARAM_INT);
			$stmt->execute();
		}

		save_redirect('saved=atg');
	} catch (Exception $e) {
		error_log('save_atg_sensors: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

if (isset($_POST['save_calibration_table'])) {
	try {
		require_once 'import_calibration.php';
		$sensor_id = intval(isset($_POST['calibration_sensor_id']) ? $_POST['calibration_sensor_id'] : 0);
		if ($sensor_id < 1 || $sensor_id > 8) {
			throw new InvalidArgumentException('Invalid sensor');
		}
		$soundings = isset($_POST['sounding_mm']) ? $_POST['sounding_mm'] : array();
		$volumes = isset($_POST['volume_l']) ? $_POST['volume_l'] : array();
		if (count($soundings) !== count($volumes)) {
			throw new InvalidArgumentException('Row count mismatch');
		}

		$upsert = $conn->prepare(
			'INSERT INTO tbl_atg_volume_point (sensor_id, sounding_mm, volume_l) VALUES (?, ?, ?)
			 ON CONFLICT(sensor_id, sounding_mm) DO UPDATE SET volume_l = excluded.volume_l'
		);
		for ($i = 0; $i < count($soundings); $i++) {
			$sounding = intval($soundings[$i]);
			$volume = floatval(str_replace(',', '', $volumes[$i]));
			if ($sounding < 0 || $volume < 0) {
				continue;
			}
			$upsert->execute(array($sensor_id, $sounding, $volume));
		}

		header('Location: atg_calibration.php?sensor=' . $sensor_id . '&saved=1');
		exit;
	} catch (Exception $e) {
		error_log('save_calibration_table: ' . $e->getMessage());
		$sensor_id = intval(isset($_POST['calibration_sensor_id']) ? $_POST['calibration_sensor_id'] : 1);
		header('Location: atg_calibration.php?sensor=' . $sensor_id . '&error=' . urlencode($e->getMessage()));
		exit;
	}
}

if (isset($_POST['import_calibration_csv'])) {
	try {
		require_once 'import_calibration.php';
		$sensor_id = intval(isset($_POST['calibration_sensor_id']) ? $_POST['calibration_sensor_id'] : 0);
		if ($sensor_id < 1 || $sensor_id > 8) {
			throw new InvalidArgumentException('Invalid sensor');
		}
		if (!isset($_FILES['calibration_csv']) || $_FILES['calibration_csv']['error'] !== UPLOAD_ERR_OK) {
			throw new RuntimeException('Upload failed');
		}
		$tmp = $_FILES['calibration_csv']['tmp_name'];
		$count = import_calibration_csv($conn, $sensor_id, $tmp);
		header('Location: atg_calibration.php?sensor=' . $sensor_id . '&imported=' . $count);
		exit;
	} catch (Exception $e) {
		error_log('import_calibration_csv: ' . $e->getMessage());
		$sensor_id = intval(isset($_POST['calibration_sensor_id']) ? $_POST['calibration_sensor_id'] : 1);
		header('Location: atg_calibration.php?sensor=' . $sensor_id . '&error=' . urlencode($e->getMessage()));
		exit;
	}
}

if (isset($_POST['save_pss_setting'])) {
	try {
		$mqtt_host = isset($_POST['pss_mqtt_host']) ? $_POST['pss_mqtt_host'] : '';
		$tenant = isset($_POST['tenant']) ? $_POST['tenant'] : '';
		$sub_tenant = isset($_POST['sub_tenant']) ? $_POST['sub_tenant'] : null;

		$query = "UPDATE TBL_CLIENT_SETTING
				SET mqttbroker = :mqtt_host, tenant = :tenant, subtenant = :subtenant
				WHERE ID = (SELECT ID FROM TBL_CLIENT_SETTING LIMIT 1)";

		$stmt = $conn_pss->prepare($query);
		$stmt->bindParam(':mqtt_host', $mqtt_host);
		$stmt->bindParam(':tenant', $tenant);
		$stmt->bindParam(':subtenant', $sub_tenant);
		$stmt->execute();

		shell_exec('sudo /bin/systemctl restart pssclient 2>&1');
		save_redirect('saved=pss');
	} catch (Exception $e) {
		error_log('save_pss_setting: ' . $e->getMessage());
		save_redirect('error=1');
	}
}

	if(ISSET($_POST['save_mqtt_settings']))
	{
		try {
			$broker_host = $_POST['mqtt_broker_host'] ?? '';
			$broker_port = intval($_POST['mqtt_broker_port'] ?? 1883);
			$username = $_POST['mqtt_username'] ?? '';
			$password = $_POST['mqtt_password'] ?? '';
			$topic_path = $_POST['mqtt_topic_path'] ?? 'maintank/atg/value';
			$publish_interval = intval($_POST['mqtt_interval'] ?? 30);
			$company_name = $_POST['mqtt_company'] ?? 'PT. Putra Perkasa Abadi';
			$plant_id = $_POST['mqtt_plant_id'] ?? 'PPA-BA';

			// Validation
			if (empty($broker_host) || empty($username) || empty($password) || empty($topic_path)) {
				throw new Exception('Broker host, username, password, and topic path are required');
			}
			if ($broker_port < 1 || $broker_port > 65535) {
				throw new Exception('Invalid broker port');
			}
			if ($publish_interval < 1 || $publish_interval > 300) {
				throw new Exception('Publish interval must be between 1 and 300 seconds');
			}

			$query = "UPDATE tbl_atg_mqtt_setting SET
						broker_host = :broker_host,
						broker_port = :broker_port,
						username = :username,
						password = :password,
						topic_path = :topic_path,
						publish_interval_sec = :publish_interval,
						company_name = :company_name,
						plant_id = :plant_id
					  WHERE id = 1";
			$stmt = $conn->prepare($query);
			$stmt->bindParam(':broker_host', $broker_host);
			$stmt->bindParam(':broker_port', $broker_port, PDO::PARAM_INT);
			$stmt->bindParam(':username', $username);
			$stmt->bindParam(':password', $password);
			$stmt->bindParam(':topic_path', $topic_path);
			$stmt->bindParam(':publish_interval', $publish_interval, PDO::PARAM_INT);
			$stmt->bindParam(':company_name', $company_name);
			$stmt->bindParam(':plant_id', $plant_id);
			$stmt->execute();

			header('location: home.php');
			$conn = null;
		} catch (Exception $e) {
			error_log('save_mqtt_settings: ' . $e->getMessage());
			header('location: home.php?error=' . urlencode($e->getMessage()));
			$conn = null;
		}
	}

	if(ISSET($_POST['save_sensor_metadata']))
	{
		try {
			for ($i = 1; $i <= 8; $i++) {
				$slocn = $_POST["sensor_slocn_$i"] ?? "FS-0$i";
				$sensor_number = $_POST["sensor_number_$i"] ?? sprintf('%02d', $i);
				$capacity = floatval($_POST["sensor_capacity_$i"] ?? 40000.0);

				$query = "UPDATE tbl_atg_sensor SET
							slocn = :slocn,
							sensor_number = :sensor_number,
							capacity_liters = :capacity
						  WHERE id = :id";
				$stmt = $conn->prepare($query);
				$stmt->bindParam(':slocn', $slocn);
				$stmt->bindParam(':sensor_number', $sensor_number);
				$stmt->bindParam(':capacity', $capacity);
				$stmt->bindParam(':id', $i, PDO::PARAM_INT);
				$stmt->execute();
			}

			header('location: home.php');
			$conn = null;
		} catch (Exception $e) {
			error_log('save_sensor_metadata: ' . $e->getMessage());
			header('location: home.php?error=' . urlencode($e->getMessage()));
			$conn = null;
		}
	}

save_redirect();
