<?php
declare(strict_types=1);

define('APP_NAME', 'Clinic Adjustment Scheduler');

define('BASE_URL', '/clinic-fullstrack/clinic-staff');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'clinic_scheduler');
define('DB_USER', 'root');
define('DB_PASS', '');

date_default_timezone_set('Asia/Manila');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
