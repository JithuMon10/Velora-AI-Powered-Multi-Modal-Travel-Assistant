<?php
// get_stops.php
// Returns stops list as JSON
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/db.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

function sendError($message, $details = null) {
    $response = ['success' => false, 'error' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    echo json_encode($response);
    exit;
}

try {
    try {
        $pdo = get_pdo();
    } catch (PDOException $e) {
        sendError('Database connection failed', $e->getMessage());
    }

    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $rows = [];
    try {
        if ($q !== '') {
            $like = "%" . $q . "%";
            $sql = "SELECT id, stop_name, operator_name, city, latitude, longitude
                    FROM stops
                    WHERE stop_name LIKE :q OR city LIKE :q
                    LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':q' => $like]);
        } else {
            $sql = "SELECT id, stop_name, operator_name, city, latitude, longitude FROM stops";
            $stmt = $pdo->query($sql);
        }
        $raw = $stmt ? $stmt->fetchAll() : [];
        // Map to include aliases expected by testers: name, lat, lng
        foreach ($raw as $r) {
            $rows[] = [
                'id' => $r['id'],
                'stop_name' => $r['stop_name'],
                'name' => $r['stop_name'],
                'operator_name' => $r['operator_name'],
                'city' => $r['city'],
                'longitude' => $r['longitude'],
                'lat' => $r['latitude'],
                'lng' => $r['longitude'],
            ];
        }
    } catch (Throwable $e) {
        error_log('Stops query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
    }

    // Check if we have any data
    if (empty($rows)) {
        // Try to check if table exists
        try {
            // MySQL: SHOW TABLES LIKE 'stops'; SQLite alternative handled by simple select
            $tableCheck = $pdo->query("SELECT 1 FROM stops LIMIT 1");
            if ($tableCheck === false) {
                sendError('Stops table not accessible', 'Unable to query the "stops" table.');
            }
        } catch (Throwable $e) {
            // Report a clearer error if table truly missing
            sendError('Stops table does not exist', $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($rows),
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    error_log('Server error in get_stops.php: ' . $e->getMessage());
    sendError('Server error', $e->getMessage());
}

/* v-sync seq: 57 */