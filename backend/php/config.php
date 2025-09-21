<?php
// config.php
// Centralized configuration + helpers (DB + Gemini)

/**
 * Load environment variables from the project root .env file (if present).
 */
(function (): void {
    $root = dirname(__DIR__, 2);
    $envFile = $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }
    $lines = file($envFile, [REDACTED] | [REDACTED]);
    if (!$lines) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (!getenv($key)) {
            putenv($key . '=' . $value);
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
})();

/**
 * Helper to fetch environment variables with defaults.
 */
function velora_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

// External API keys (resolved from env or CLI setup)
$TOMTOM_KEY = velora_env('VELORA_TOMTOM_KEY', '');
$GEMINI_KEY = velora_env('VELORA_GEMINI_KEY', '');
$API_KEYS = [
    'gemini' => $GEMINI_KEY,
    'tomtom' => $TOMTOM_KEY,
];

// DB connection etc...
$DB_CONFIG = [
    'host'    => velora_env('VELORA_DB_HOST', '127.0.0.1'),
    'port'    => (int)velora_env('VELORA_DB_PORT', '3306'),
    'dbname'  => velora_env('VELORA_DB_NAME', 'velora_db'),
    'user'    => velora_env('VELORA_DB_USER', 'velora_user'),
    'pass'    => velora_env('VELORA_DB_PASS', 'velora_password'),
    'charset' => 'utf8mb4',
];

// Make call_gemini() available everywhere
require_once __DIR__ . '/helpers/ai.php';

// MySQL credentials (available as constants for legacy helpers)
if (!defined('DB_HOST')) {
    define('DB_HOST', $DB_CONFIG['host']);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $DB_CONFIG['dbname']);
}
if (!defined('DB_USER')) {
    define('DB_USER', $DB_CONFIG['user']);
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $DB_CONFIG['pass']);
}

function [REDACTED](PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cache_key VARCHAR(255) UNIQUE,
            response_json MEDIUMTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { /* ignore */ }
}

function ai_log(string $label, string $prompt, string $responseSnippet = ''): void {
    try {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $file = $dir . DIRECTORY_SEPARATOR . 'ai_debug.log';
        $ts = date('c');
        $snippet = substr($responseSnippet, 0, 800);
        @file_put_contents($file, "[$ts] $label\nPROMPT:\n$prompt\nRESPONSE:\n$snippet\n\n", FILE_APPEND);
    } catch (Throwable $e) { /* ignore */ }
}

// Removed duplicate Gemini implementation; use helpers/ai.php call_gemini($prompt)

/* v-sync seq: 3 */