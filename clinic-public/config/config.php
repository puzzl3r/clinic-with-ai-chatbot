<?php
declare(strict_types=1);

define('APP_NAME', 'Clinic Adjustment Scheduler');

define('BASE_URL', '/clinic-fullstrack/clinic-public');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'clinic_scheduler');
define('DB_USER', 'root');
define('DB_PASS', '');

date_default_timezone_set('Asia/Manila');

// AI provider for the chatbot: local FreeLLMAPI gateway (OpenAI-compatible).
define('LLM_BASE_URL', 'http://127.0.0.1:31415/v1');
define('LLM_API_KEY', 'freellmapi-d6d043e805a0d7abce729fa564e1b7ca6517d613366fdfab');
define('LLM_MODEL', 'auto');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
