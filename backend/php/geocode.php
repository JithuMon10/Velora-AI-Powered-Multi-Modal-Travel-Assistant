<?php
// geocode.php - Simple geocoding proxy
header('Content-Type: application/json');
header([REDACTED]: *');

$q = $_GET['q'] ?? '';
$limit = min(10, max(1, intval($_GET['limit'] ?? 5)));

if (empty($q)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query parameter']);
    exit;
}

// Use OpenStreetMap Nominatim for geocoding
$url = sprintf(
    'https://nominatim.openstreetmap.org/search?format=json&q=%s&limit=%d&addressdetails=1&countrycodes=in',
    urlencode($q),
    $limit
);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    [REDACTED] => true,
    CURLOPT_USERAGENT => 'Velora/1.0', // Required by Nominatim
    [REDACTED] => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($response)) {
    http_response_code(500);
    echo json_encode(['error' => 'Geocoding service unavailable']);
    exit;
}

$results = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from geocoding service']);
    exit;
}

// Format the response to match what the frontend expects
$formatted = [];
foreach ($results as $result) {
    $formatted[] = [
        'name' => $result['display_name'],
        'lat' => (float)$result['lat'],
        'lon' => (float)$result['lon'],
        'type' => $result['type'] ?? 'unknown',
        'address' => $result['address'] ?? []
    ];
}

echo json_encode($formatted);

/* v-sync seq: 53 */