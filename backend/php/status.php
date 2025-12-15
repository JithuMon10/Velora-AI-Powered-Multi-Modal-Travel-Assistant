<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'online',
    'time' => date('Y-m-d H:i:s'),
    'endpoints' => [
        'test' => 'http://localhost:9000/test_trip.php',
        'stops' => 'http://localhost:9000/get_stops.php',
        'plan' => 'http://localhost:9000/plan_trip.php?mode=drive&origin_lat=9.9312&origin_lon=76.2673&dest_lat=9.5916&dest_lon=76.5222&origin_name=Kochi&dest_name=Mallappally&depart_time=14:30&vehicle=no'
    ]
]);
