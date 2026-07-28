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
    @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap");

    *,
    *:before,
    *:after {
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Poppins', sans-serif;
        font-size: 16px;
        line-height: 1.0;
        color: #777777;
    }

    h1 {
        font-family: 'Poppins', sans-serif;
        font-size: 24px;
        text-align: center;
        line-height: 1.0;
        color: #777777;
    }

    .container {
        width: calc((100% - 200)/3);
        margin: 0 auto;
        max-width: 90%;
        padding-right: 16px;
        padding-left: 16px;
    }

    section {
        background: #ffffff;
        padding: 100px 0;
    }

    .tab__list {
        display: flex;
        margin-bottom: 24px;
    }

    .tab__item {
        overflow: hidden;
        cursor: pointer;
        padding: 12px 8px;
        margin-right: 24px;
        text-shadow: 0 0 0.5px currentColor;
        letter-spacing: 1px;
        transition: 0.2s;
        font-size: 18px;
        position: relative;
        color: #777777;
    }

    .tab__item:before {
        content: "";
        position: absolute;
        top: 95%;
        left: 0;
        height: 2px;
        width: 100%;
        background: #03a9f4;
        transition: 0.2s;
        visibility: hidden;
        opacity: 0;
    }

    .tab__item.is--active {
        color: #444;
        position: relative;
    }

    .tab__item.is--active:before {
        visibility: visible;
        opacity: 1;
    }

    .tab__content {
        padding: 32px;
        background: #f1f1f1;
        border-radius: 0 10px 10px;
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

    .tab .tab .tab__item {
        font-size: 16px;
    }

    .tab .tab .tab__content {
        padding: 0;
    }

    .logo_qims {
        position: absolute;
        bottom: 8px;
        left: 16px;
        width: 50px;
        height: 50px;
    }

    table {
        font-family: arial, sans-serif;
        border-collapse: collapse;
        width: 100%;
    }


    td,
    th {
        border: 1px solid #dddddd;
        text-align: left;
        padding: 8px;
    }

    tr:nth-child(even) {
        background-color: #dddddd;
    }

    label {
        display: inline-block;
        width: 250px;
        text-align: left;
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
		$data_logfile_day_rotation= ($data_setting && isset($data_setting[11])) ? $data_setting[11] : ''; 
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

        $data_mqtt_broker = '';
        $data_mqtt_port = '';
        $data_mqtt_username = '';
        $data_mqtt_password = '';
        $data_mqtt_topic = '';
        $result_mqtt_setting = $db->query("SELECT * FROM TBL_MQTT_SETTING"); 
		if ($result_mqtt_setting) {
			$data_mqtt_setting = $result_mqtt_setting->fetchArray();
			if ($data_mqtt_setting) {
				$data_mqtt_broker = $data_mqtt_setting[1]; 
				$data_mqtt_port = $data_mqtt_setting[2]; 
				$data_mqtt_username = $data_mqtt_setting[3]; 
				$data_mqtt_password = $data_mqtt_setting[4]; 
				$data_mqtt_topic = $data_mqtt_setting[5];
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
    <h1>ATG Maintank</h1>
    <?php if (isset($_GET['saved'])): ?>
    <p style="color: green; text-align: center;">Settings saved.</p>
    <?php elseif (isset($_GET['error'])): ?>
    <p style="color: red; text-align: center;">Save failed — check server log.</p>
    <?php endif; ?>
    <span style="float:right;">
        <h2>Welcome, <?php echo $_SESSION['username']; ?></h2>
        <h2><a href="action-logout.php">Logout</a></h2>
    </span>
    <section class="section">
        <div class="container">
            <div class="tab">
                <div class="tab__list">
                    <div class="tab__item">
                        Status
                    </div>
                    <div class="tab__item">
                        Settings
                    </div>
                </div>
                <div class="tab__content">
                    <div class="tab__content-item tab">
                        <div class="tab__list">
                            <div class="tab__item">
                                General
                            </div>

                            <div class="tab__item">
                                Download
                            </div>
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

                            <div class="tab__content-item">
                                <strong>Download Files</strong><br>
                                <iframe src="file_manager.php" id="iFrame1" width="900" height="500">
                                </iframe>

                            </div>

                            <div class="tab__content-item">
                                <strong>Fuel Level Sensors</strong><br><br>
                                <table id="atg_readings_table">
                                    <tr>
                                        <th>#</th>
                                        <th>Label</th>
                                        <th>Device</th>
                                        <th>Status</th>
                                        <th>Offset (mm)</th>
                                        <th>Probe (mm)</th>
                                        <th>Sounding (mm)</th>
                                        <th>Volume (L)</th>
                                        <th>Water (mm)</th>
                                        <th>Temp (°C)</th>
                                        <th>Last Update</th>
                                    </tr>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
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
                                        <td class="atg-temp">—</td>
                                        <td class="atg-updated">—</td>
                                    </tr>
                                    <?php endfor; ?>
                                </table>
                            </div>

                            <div class="tab__content-item">
                                <strong>Serial Information</strong><br><br>
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
                    <div class="tab__content-item tab">
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
                                <form method="post" action="save_data.php">
                                    <label for="unit_name"><strong>Unit Name </strong></label>
                                    <input type="text" id="data_unitname" name="data_unitname"
                                        value="<?php echo $data_unitname;?>"><br>



                                    <strong>Ethernet Properties</strong><br>
                                    <label for="data_ip_address_logger">IP Address</label>
                                    <input type="text" id="data_ip_address_logger" name="data_ip_address_logger"
                                        value="<?php echo $data_ip_address_logger;?>"><br>

                                    <label for="data_subnetmask_logger">Subnetmask</label>
                                    <input type="text" id="data_subnetmask_logger" name="data_subnetmask_logger"
                                        value="<?php echo $data_subnetmask_logger;?>"><br>

                                    <label for="data_gateway_logger">Gateway</label>
                                    <input type="text" id="data_gateway_logger" name="data_gateway_logger"
                                        value="<?php echo $data_gateway_logger;?>">
                                    <br><br>

                                    <br>
                                    <!-- <label for="data_comport1">Serial Com : </label>										
									<select name="data_comport1" id="data_comport1" >
										<option value="1" <?php if($data_comport1=="1") echo 'selected="selected"'; ?> >1</option>
										<option value="2" <?php if($data_comport1=="2") echo 'selected="selected"'; ?> >2</option>
										<option value="3" <?php if($data_comport1=="3") echo 'selected="selected"'; ?> >3</option>
										<option value="4" <?php if($data_comport1=="4") echo 'selected="selected"'; ?> >4</option>
									</select>
								<br>
								<label for="data_baudrate1">Baudrate : </label>	
									<select name="data_baudrate1" id="data_baudrate1" >
										<option value="9600" <?php if($data_baudrate1=="9600") echo 'selected="selected"'; ?> >9600</option>
										<option value="19200" <?php if($data_baudrate1=="19200") echo 'selected="selected"'; ?> >19200</option>
										<option value="57600" <?php if($data_baudrate1=="57600") echo 'selected="selected"'; ?> >57600</option>
										<option value="115200" <?php if($data_baudrate1=="115200") echo 'selected="selected"'; ?> >115200</option>
									</select>
								<br><br> -->
                                    <!-- <label for="SETTING_tyreINTERVALREGID">Interval Request ID</label>
                                    <input type="text" id="SETTING_tyreINTERVALREGID" name="SETTING_tyreINTERVALREGID"
                                        value="<?php echo $SETTING_tyreINTERVALREGID;?>"> [detik]
                                    <br> -->


                                    <!-- <label for="SETTING_tyreSATUANTEKANAN">Satuan Tekanan : </label>
                                    <select name="SETTING_tyreSATUANTEKANAN" id="SETTING_tyreSATUANTEKANAN">
                                        <option value="0"
                                            <?php if($SETTING_tyreSATUANTEKANAN=="0") echo 'selected="selected"'; ?>>PSI
                                        </option>
                                        <option value="1"
                                            <?php if($SETTING_tyreSATUANTEKANAN=="1") echo 'selected="selected"'; ?>>BAR
                                        </option>
                                    </select> -->
                                    <br><br>
                                    <!-- <label for="SETTING_radiusLoading">Radius Loading</label>
                                    <input type="text" id="SETTING_radiusLoading" name="SETTING_radiusLoading"
                                        value="<?php echo $SETTING_radiusLoading;?>"> [m]
                                    <br>
                                    <label for="SETTING_radiusWaiting">Radius Waiting</label>
                                    <input type="text" id="SETTING_radiusWaiting" name="SETTING_radiusWaiting"
                                        value="<?php echo $SETTING_radiusWaiting;?>"> [m]
                                    <br>
                                    <label for="SETTING_radiusHauling">Radius Hauling</label>
                                    <input type="text" id="SETTING_radiusHauling" name="SETTING_radiusHauling"
                                        value="<?php echo $SETTING_radiusHauling;?>"> [m]
                                    <br>
                                    <label for="SETTING_company">Company</label>
                                    <input type="text" id="SETTING_company" name="SETTING_company"
                                        value="<?php echo $SETTING_company;?>">
                                    <br>
                                    <label for="SETTING_plant">Plant</label>
                                    <input type="text" id="SETTING_plant" name="SETTING_plant"
                                        value="<?php echo $SETTING_plant;?>">
                                    <br>
                                    <label for="SETTING_plant_description">Plant Description</label>
                                    <input type="text" id="SETTING_plant_description" name="SETTING_plant_description"
                                        value="<?php echo $SETTING_plant_description;?>">
                                    <br>
                                    <br><br>
                                    <label for="SETTING_tyrePUSHHTTPPOST">Interval Push Http Post</label>
                                    <input type="text" id="SETTING_tyreINSETTING_tyrePUSHHTTPPOSTTERVALSAVELOG"
                                        name="SETTING_tyrePUSHHTTPPOST" value="<?php echo $SETTING_tyrePUSHHTTPPOST;?>">
                                    [detik] -->
                                    <!-- <br>
                                    <label for="SETTING_tyrePUSHMQTT">Interval Push MQTT</label>
                                    <input type="text" id="SETTING_tyrePUSHMQTT" name="SETTING_tyrePUSHMQTT"
                                        value="<?php echo $SETTING_tyrePUSHMQTT;?>"> [detik]
                                    <br> -->
                                    <br>
                                    <!-- <label for="SETTING_tyreURLAPI">URL API</label>
                                    <input type="text" id="SETTING_tyreURLAPI" name="SETTING_tyreURLAPI"
                                        value="<?php echo $SETTING_tyreURLAPI;?>">
                                    <br>

                                    <label for="SETTING_tyreIDSITE">ID SITE</label>
                                    <input type="text" id="SETTING_tyreIDSITE" name="SETTING_tyreIDSITE"
                                        value="<?php echo $SETTING_tyreIDSITE;?>">
                                    <br> -->

                                    <input type="submit" name="save_general_settings" onclick="save_data.php"
                                        value=" Save Changes "><br><br><br>
                                </form>
                            </div>
                            

                            <div class="tab__content-item">

                                <Strong>Schedule Rotation</strong><br>
                                <form method="post" action="save_data.php">
                                    <label for="data_logfile_day_rotation">Months : </label>
                                    <input type="text" id="data_logfile_day_rotation" name="data_logfile_day_rotation"
                                        value="<?php echo $data_logfile_day_rotation;?>">
                                    <input type="submit" name="save_rotation_settings" onclick="save_data.php"
                                        value=" Save Changes ">
                                </form><br>

                                <strong>Syslog Rotation</strong><br>
                                <form method="post" action="save_data.php">
                                    <label for="data_syslog_rotate_count">Rotate Count </label>
                                    <input type="text" id="data_syslog_rotate_count" name="data_syslog_rotate_count"
                                        value="<?php echo $data_syslog_rotate_count; ?>"><br>
                                    <label for="data_syslog_rotate_frequency">Frequency </label>
                                    <select id="data_syslog_rotate_frequency" name="data_syslog_rotate_frequency">
                                        <option value="daily" <?php echo ($data_syslog_rotate_frequency === 'daily') ? 'selected' : ''; ?>>daily</option>
                                        <option value="weekly" <?php echo ($data_syslog_rotate_frequency === 'weekly') ? 'selected' : ''; ?>>weekly</option>
                                        <option value="monthly" <?php echo ($data_syslog_rotate_frequency === 'monthly') ? 'selected' : ''; ?>>monthly</option>
                                    </select><br>
                                    <label for="data_syslog_max_size">Max Size</label>
                                    <input type="text" id="data_syslog_max_size" name="data_syslog_max_size"
                                        value="<?php echo htmlspecialchars($data_syslog_max_size); ?>"><br>
                                    <small>Contoh: 100M, 500k, kosongkan jika tidak dipakai</small><br>
                                    <label><input type="checkbox" name="data_syslog_compress" value="1"
                                        <?php echo ($data_syslog_compress === 1) ? 'checked' : ''; ?>> Compress</label><br>
                                    <label><input type="checkbox" name="data_syslog_delaycompress" value="1"
                                        <?php echo ($data_syslog_delaycompress === 1) ? 'checked' : ''; ?>> Delay Compress</label><br>
                                    <label><input type="checkbox" name="data_syslog_missingok" value="1"
                                        <?php echo ($data_syslog_missingok === 1) ? 'checked' : ''; ?>> Missing OK</label><br>
                                    <label><input type="checkbox" name="data_syslog_notifempty" value="1"
                                        <?php echo ($data_syslog_notifempty === 1) ? 'checked' : ''; ?>> Not If Empty</label><br>
                                    <input type="submit" name="save_syslog_settings" value=" Save Syslog Setting ">
                                </form><br>

                                <Strong>Synchronize</strong><br>
                                <form method="post">
                                    <label for="synctime">Synchronize with PC Time </label>
                                    <input type="submit" name="sync" value=" Synchronize Time "><br>
                                </form><br>
                                <?php
									if (isset($_POST['sync']))
									{
										$local_pc_datetime= $_COOKIE['myJavascriptVar'];
										shell_exec("echo 1234 | sudo -S date -s '$local_pc_datetime'");

										shell_exec("echo 1234 | sudo hwclock -w");
										header('location: home.php');
									}
								?>

                                <strong>Time Settings Manual</strong><br>
                                <form method="post" action="save_data.php">
                                    <label for="current_date">Set Current Date </label>
                                    <input type="date" id="current_date" name="current_date"
                                        value="<?php echo trim(shell_exec('date +"%Y-%m-%d"')); ?>"><br>
                                    <label for="current_time">Set Current Time </label>
                                    <input type="time" id="current_time" name="current_time"
                                        value="<?php echo trim(shell_exec('date +"%H:%M"')); ?>"><br />
                                    <label for="data_timezone">Set Timezone (GMT +) </label>
                                    <input type="text" id="data_timezone" name="data_timezone"
                                        value=<?php echo $data_timezone;?>>
                                    <input type="submit" name="set_date_time" value=" Set Date Time "><br><br>
                                </form>

                                <strong>Schedule Reboot</strong><br>
                                <form method="post" action="save_data.php">
                                    <label for="data_reboot_schedule_enable">Enable Schedule </label>
                                    <input type="checkbox" id="data_reboot_schedule_enable" name="data_reboot_schedule_enable" value="1"
                                        <?php echo ($data_reboot_schedule_enable == 1) ? 'checked' : ''; ?>><br>
                                    <label for="data_reboot_time">Reboot Time </label>
                                    <input type="time" id="data_reboot_time" name="data_reboot_time"
                                        value="<?php echo htmlspecialchars($data_reboot_time ? $data_reboot_time : '03:00'); ?>"><br>
                                    <input type="submit" name="save_reboot_schedule" value=" Save Schedule "><br><br>
                                </form>

                                <form method="post">
                                    <label for="Reboot">Reboot </label>
                                    <input type="submit" name="reboot" value="Reboot"><br>
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
                            </div>

                            <div class="tab__content-item">
                                <strong>ATG Sensor Configuration</strong> (6 channels)<br><br>
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
                                        <?php for ($i = 1; $i <= 6; $i++):
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
                                    <p><small>Sounding = Probe + Offset. Volume dihitung dari Sounding (mm).</small></p>
                                    <br>
                                    <input type="submit" name="save_atg_sensors" value=" Save ATG Settings ">
                                </form>
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
								<?php if (!$show_pss_form): ?>
									<form method="post">
										<p><strong>This setting is protected. Please insert password.</strong></p><br>
										<?php if ($wrong_password): ?>
											<p style="color:red;">Incorrect password. Please try again.</p>
										<?php endif; ?>
										<label for="pss_password">Password:</label>
										<input type="password" id="pss_password" name="pss_password" required>
										<input type="submit" name="check_pss_password" value="Submit">
									</form>
								<?php else: ?>
									<form method="post" action="save_data.php" onsubmit="return validatePSSForm();">
										<label for="pss_mqtt_host">MQTT Host</label>
										<input type="text" id="pss_mqtt_host" name="pss_mqtt_host" value="<?php echo $pss_mqtt_host; ?>"><br>

										<label for="tenant">Tenant</label>
										<input type="text" id="tenant" name="tenant" value="<?php echo $pss_tenant; ?>"><br>

										<label for="sub_tenant">Sub Tenant</label>
										<input type="text" id="sub_tenant" name="sub_tenant" value="<?php echo $pss_sub_tenant; ?>"><br><br><br>

										<input type="submit" name="save_pss_setting" value="Save Changes"><br><br><br>
									</form>
								<?php endif; ?>
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

    function tabify(tab) {
        const tabList = tab.querySelector(".tab__list");

        if (tabList) {
            const tabItems = [...tabList.children];
            const tabContent = tab.querySelector(".tab__content");
            const tabContentItems = [...tabContent.children];
            let tabIndex = 0;

            tabIndex = tabItems.findIndex((item, index) => {
                return [...item.classList].indexOf("is--active") > -1;
            });

            tabIndex > -1 ? tabIndex = tabIndex : tabIndex = 0;

            function setTab(index) {
                tabItems.forEach((x, index) => x.classList.remove("is--active"));
                tabContentItems.forEach((x, index) => x.classList.remove("is--active"));

                tabItems[index].classList.add("is--active");
                tabContentItems[index].classList.add("is--active");
            }

            tabItems.forEach((x, index) =>
                x.addEventListener("click", () => setTab(index)));

            setTab(tabIndex);
            tab.querySelectorAll(".tab").forEach(tabContent => tabify(tabContent));
        }
    }

    tabs.forEach(tabify);
    //# sourceURL=pen.js
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
