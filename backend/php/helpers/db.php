<?php
// helpers/db.php
// Central DB helper and schema ensure functions

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'velora_db');
if (!defined('DB_USER')) define('DB_USER', 'velora_user');
if (!defined('DB_PASS')) define('DB_PASS', 'pX8uS2mD9qL4-Zr7@Vb1Yc6N');

if (!function_exists('get_pdo')) {
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    // Try MySQL first
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::[REDACTED] => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        // Fallback to SQLite for local debug so the app remains functional
        try {
            $path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'velora.sqlite';
            $path = realpath($path) ?: (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'velora.sqlite');
            $pdo = new PDO('sqlite:'.$path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Ensure minimal stations schema
            $pdo->exec("CREATE TABLE IF NOT EXISTS stations (
                id INTEGER PRIMARY KEY,
                name TEXT,
                type TEXT,
                state TEXT,
                lat REAL,
                lon REAL
            );");
            // If empty, seed two Kerala stations for Mallappally→Kochi quick debug
            $count = (int)$pdo->query("SELECT COUNT(*) FROM stations")->fetchColumn();
            if ($count === 0) {
                // Try to read top-level stations_bus.csv if present
                $rootCsv = realpath(__DIR__ . '/../../../stations_bus.csv');
                $inserted = 0;
                if ($rootCsv && is_file($rootCsv)) {
                    if (($fh = @fopen($rootCsv,'r'))){
                        while(($row = fgetcsv($fh))!==false){
                            // Expect columns include name,state,lat,lon loosely; be defensive
                            $line = implode(',', $row);
                            if (stripos($line,'mallappally')!==false || stripos($line,'ernakulam')!==false || stripos($line,'ksrtc')!==false) {
                                // Heuristic parse: find first 4 floats/strings
                                $name = $row[0] ?? 'Bus Stop';
                                $state = $row[1] ?? 'Kerala';
                                $lat = isset($row[2]) ? (float)$row[2] : null;
                                $lon = isset($row[3]) ? (float)$row[3] : null;
                                if ($lat && $lon) {
                                    $st = $pdo->prepare('INSERT INTO stations(name,type,state,lat,lon) VALUES (?,?,?,?,?)');
                                    $st->execute([$name,'bus',$state,$lat,$lon]);
                                    $inserted++;
                                    if ($inserted >= 8) break;
                                }
                            }
                        }
                        fclose($fh);
                    }
                }
                if ($inserted === 0) {
                    // Static seed fallback near Mallappally and Ernakulam KSRTC
                    $pdo->exec("INSERT INTO stations(name,type,state,lat,lon) VALUES
                        ('Mallappally Bus Stand','bus','Kerala',9.3865,76.6765),
                        ('Ernakulam KSRTC Bus Stand','bus','Kerala',9.9816,76.2826)
                    ");
                }
            }
            return $pdo;
        } catch (Throwable $se) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false, 'error'=>'Database connection failed (both MySQL and SQLite)', 'data'=>[], 'count'=>0]);
            exit;
        }
    }
}
}

function [REDACTED](PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
{{ ... }}
            response_json MEDIUMTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { /* ignore */ }
}

function [REDACTED](PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hotels_cache (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          cache_key VARCHAR(200) NOT NULL,
          response_json JSON NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_hcache (cache_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }
}

function [REDACTED](PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS traffic_cache_ext (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          origin VARCHAR(100) NOT NULL,
          dest VARCHAR(100) NOT NULL,
          hour_bucket VARCHAR(10) NOT NULL,
          response_json JSON NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_tcache (origin, dest, hour_bucket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }
}

function [REDACTED](PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_stop_cache (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          origin_hash CHAR(64) NOT NULL,
          dest_hash   CHAR(64) NOT NULL,
          date_bucket DATE NOT NULL,
          response_json JSON NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_ai_stop_cache (origin_hash, dest_hash, date_bucket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }
}
