<?php
// save_location.php
// Accepts POST: name, latitude, longitude
// Inserts a row into velora_db.locations and returns standardized JSON

header('Content-Type: application/json');
// Optional CORS for local testing (adjust or remove in production)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header([REDACTED]: ' . $_SERVER['HTTP_ORIGIN']);
    header([REDACTED]: true');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header([REDACTED]: POST, OPTIONS');
    header([REDACTED]: Content-Type');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.', 'data'=>[], 'count'=>0]);
        exit;
    }

    // Accept JSON or form-encoded input
    $input = [];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
    } else {
        $input = $_POST;
    }

    $name = trim($input['name'] ?? '');
    $lat  = $input['latitude'] ?? null;
    $lng  = $input['longitude'] ?? null;

    // Basic validation
    if ($name === '' || $lat === null || $lng === null) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields: name, latitude, longitude', 'data'=>[], 'count'=>0]);
        exit;
    }

    if (!is_numeric($lat) || !is_numeric($lng)) {
        echo json_encode(['success' => false, 'error' => 'Latitude and longitude must be numeric', 'data'=>[], 'count'=>0]);
        exit;
    }

    $lat = (float)$lat;
    $lng = (float)$lng;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode(['success' => false, 'error' => 'Latitude must be between -90 and 90, longitude between -180 and 180', 'data'=>[], 'count'=>0]);
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

    $stmt = $pdo->prepare('INSERT INTO locations (name, latitude, longitude) VALUES (:name, :lat, :lng)');
    $stmt->execute([
        ':name' => $name,
        ':lat'  => $lat,
        ':lng'  => $lng,
    ]);

    $id = (int)$pdo->lastInsertId();
    $row = [ 'id'=>$id, 'name'=>$name, 'latitude'=>$lat, 'longitude'=>$lng, 'timestamp'=>date('c') ];

    echo json_encode(['success'=>true, 'error'=>null, 'data'=>[$row], 'count'=>1]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error', 'data'=>[], 'count'=>0]);
}
