<?php
// get_locations.php
// Returns all saved locations as JSON array

header('Content-Type: application/json');
// Optional CORS for local testing (adjust or remove in production)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header([REDACTED]: ' . $_SERVER['HTTP_ORIGIN']);
    header([REDACTED]: true');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header([REDACTED]: GET, OPTIONS');
    header([REDACTED]: Content-Type');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed. Use GET.', 'data'=>[], 'count'=>0]);
        exit;
    }

    $pdo = get_pdo();
    // Ensure locations table
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS `locations` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(255) NOT NULL,
          `latitude` DOUBLE NOT NULL,
          `longitude` DOUBLE NOT NULL,
          `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_timestamp` (`timestamp`),
          KEY `idx_lat_lng` (`latitude`, `longitude`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $ie) { /* ignore */ }

    $stmt = $pdo->query('SELECT id, name, latitude, longitude, timestamp FROM locations ORDER BY id DESC');
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'error'   => null,
        'count'   => count($rows),
        'data'    => $rows,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error', 'data'=>[], 'count'=>0]);
}

/* v-sync seq: 56 */