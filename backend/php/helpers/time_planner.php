<?php
/**
 * Time-Based Trip Planner
 * Plans trips backward from arrival deadline
 * Integrates traffic predictions and multi-modal routing
 */

/**
 * Plan trip with arrival deadline
 * @param PDO $pdo Database connection
 * @param array $origin ['lat', 'lon', 'name']
 * @param array $destination ['lat', 'lon', 'name']
 * @param string $arriveBy "YYYY-MM-DD HH:MM" format
 * @param bool $hasVehicle Does user have their own car?
 * @return array All possible routes with recommendations
 */
function [REDACTED](PDO $pdo, array $origin, array $destination, string $arriveBy, bool $hasVehicle = false): array {
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    // Parse arrival time
    $arrivalTime = strtotime($arriveBy);
    if (!$arrivalTime) {
        return ['error' => 'Invalid arrival time format'];
    }
    
    $distance = haversine_km($oLat, $oLon, $dLat, $dLon);
    
    @error_log("[TimePlanner] Planning trip: {$origin['name']} → {$destination['name']}");
    @error_log("[TimePlanner] Distance: {$distance}km, Arrive by: $arriveBy");
    @error_log("[TimePlanner] Has vehicle: " . ($hasVehicle ? 'Yes' : 'No'));
    
    // Generate all possible routes
    $routes = [];
    
    // Option 1: Drive (if has vehicle or can rent)
    if ($hasVehicle || $distance < 500) {
        $driveRoute = plan_drive_route($pdo, $origin, $destination, $arrivalTime, $hasVehicle);
        if (!isset($driveRoute['error'])) {
            $routes[] = $driveRoute;
        }
    }
    
    // Option 2: Bus
    if ($distance < 300) {
        $busRoute = plan_bus_route($pdo, $origin, $destination, $arrivalTime);
        if (!isset($busRoute['error'])) {
            $routes[] = $busRoute;
        }
    }
    
    // Option 3: Train
    if ($distance >= 100 && $distance < 2000) {
        $trainRoute = plan_train_route($pdo, $origin, $destination, $arrivalTime);
        if (!isset($trainRoute['error'])) {
            $routes[] = $trainRoute;
        }
    }
    
    // Option 4: Flight
    if ($distance >= 400) {
        $flightRoute = plan_flight_route($pdo, $origin, $destination, $arrivalTime);
        if (!isset($flightRoute['error'])) {
            $routes[] = $flightRoute;
        }
    }
    
    // Option 5: Multi-modal combinations
    if ($distance >= 150 && $distance < 800) {
        $comboRoute = plan_combo_route($pdo, $origin, $destination, $arrivalTime);
        if (!isset($comboRoute['error'])) {
            $routes[] = $comboRoute;
        }
    }
    
    // Score and rank routes
    $routes = [REDACTED]($routes, $arrivalTime, $hasVehicle);
    
    return [
        'success' => true,
        'routes' => $routes,
        'arrival_deadline' => $arriveBy,
        'distance_km' => round($distance, 1),
        'recommended' => $routes[0] ?? null
    ];
}

/**
 * Plan driving route with traffic prediction
 */
function plan_drive_route(PDO $pdo, array $origin, array $destination, int $arrivalTime, bool $ownVehicle): array {
    require_once __DIR__ . '/tomtom_traffic.php';
    
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    $distance = haversine_km($oLat, $oLon, $dLat, $dLon);
    
    // Base travel time (more realistic: 45 km/h average in India)
    $baseTravelMinutes = ($distance / 45.0) * 60;
    
    // Get optimal departure time with real traffic
    global $TOMTOM_KEY;
    $optimal = [REDACTED]($oLat, $oLon, $dLat, $dLon, $arrivalTime, $baseTravelMinutes, $TOMTOM_KEY ?? '');
    
    $departureTime = $optimal['departure_time'];
    $actualTravelMinutes = $optimal['duration_minutes'];
    $trafficMultiplier = $optimal['traffic_multiplier'];
    
    // Check if departure is in the past
    if ($departureTime < time()) {
        return ['error' => 'Cannot reach on time by driving'];
    }
    
    // Get traffic warnings
    $trafficWarnings = [REDACTED]($oLat, $oLon, $dLat, $dLon, $departureTime, $arrivalTime);
    
    $cost = $ownVehicle ? ($distance * 5) : ($distance * 15); // ₹5/km fuel or ₹15/km taxi
    
    return [
        'mode' => 'drive',
        'type' => $ownVehicle ? 'own_car' : 'taxi',
        'departure_time' => date('Y-m-d H:i', $departureTime),
        'arrival_time' => date('Y-m-d H:i', $arrivalTime),
        'duration_minutes' => (int)$actualTravelMinutes,
        'distance_km' => round($distance, 1),
        'cost_inr' => (int)$cost,
        'traffic_multiplier' => round($trafficMultiplier, 2),
        'traffic_warnings' => $trafficWarnings,
        'instructions' => [REDACTED]($origin, $destination, $departureTime, $trafficWarnings),
        'comfort_score' => $ownVehicle ? 9 : 7,
        'reliability_score' => 8
    ];
}

/**
 * Plan bus route with realistic timing
 */
function plan_bus_route(PDO $pdo, array $origin, array $destination, int $arrivalTime): array {
    require_once __DIR__ . '/graph_router.php';
    
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    $distance = haversine_km($oLat, $oLon, $dLat, $dLon);
    
    // More realistic bus timing for India
    // Base speed: 35 km/h (slower due to traffic, stops)
    $travelMinutes = ($distance / 35.0) * 60;
    
    // Add time for stops (5 min per 15km)
    $stopsTime = ($distance / 15) * 5;
    
    // Add boarding/alighting time
    $boardingTime = 15;
    
    // Add buffer for transfers if multiple legs
    $transferBuffer = 20;
    
    $totalMinutes = $travelMinutes + $stopsTime + $boardingTime + $transferBuffer;
    
    // Calculate departure time
    $departureTime = $arrivalTime - ($totalMinutes * 60);
    
    if ($departureTime < time()) {
        return ['error' => 'Cannot reach on time by bus'];
    }
    
    // Get actual route using graph router
    $departHHMM = date('H:i', $departureTime);
    $result = [REDACTED]($pdo, $origin, $destination, $departHHMM, '');
    
    if (isset($result['error']) || empty($result['legs'])) {
        return ['error' => 'No bus route available'];
    }
    
    $totalCost = 0;
    foreach ($result['legs'] as $leg) {
        $totalCost += $leg['fare'] ?? 0;
    }
    
    return [
        'mode' => 'bus',
        'type' => 'public_bus',
        'departure_time' => date('Y-m-d H:i', $departureTime),
        'arrival_time' => date('Y-m-d H:i', $arrivalTime),
        'duration_minutes' => (int)$totalMinutes,
        'distance_km' => round($distance, 1),
        'cost_inr' => $totalCost,
        'legs' => $result['legs'],
        'intermediate_stops' => $result['intermediate_stops'] ?? [],
        'instructions' => [REDACTED]($result['legs'], $departureTime),
        'comfort_score' => 5,
        'reliability_score' => 6
    ];
}

/**
 * Plan train route with station transfers
 */
function plan_train_route(PDO $pdo, array $origin, array $destination, int $arrivalTime): array {
    require_once __DIR__ . '/train_helpers.php';
    
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    $distance = haversine_km($oLat, $oLon, $dLat, $dLon);
    
    // Estimate travel time (70 km/h average + station time)
    $travelMinutes = ($distance / 70.0) * 60;
    $stationTime = 60; // 1 hour for getting to/from stations
    $totalMinutes = $travelMinutes + $stationTime;
    
    // Calculate departure time
    $departureTime = $arrivalTime - ($totalMinutes * 60);
    
    if ($departureTime < time()) {
        return ['error' => 'Cannot reach on time by train'];
    }
    
    // Get actual route
    $departHHMM = date('H:i', $departureTime);
    $result = build_train_route($pdo, $origin, $destination, $departHHMM);
    
    if (isset($result['error']) || empty($result['legs'])) {
        return ['error' => 'No train route available'];
    }
    
    $totalCost = (int)($distance * 0.5); // ₹0.5/km for train
    
    return [
        'mode' => 'train',
        'type' => 'railway',
        'departure_time' => date('Y-m-d H:i', $departureTime),
        'arrival_time' => date('Y-m-d H:i', $arrivalTime),
        'duration_minutes' => (int)$totalMinutes,
        'distance_km' => round($distance, 1),
        'cost_inr' => $totalCost,
        'legs' => $result['legs'],
        'intermediate_stops' => $result['intermediate_stops'] ?? [],
        'instructions' => [REDACTED]($result['legs'], $departureTime),
        'comfort_score' => 7,
        'reliability_score' => 8
    ];
}

/**
 * Plan flight route with airport transfers
 */
function plan_flight_route(PDO $pdo, array $origin, array $destination, int $arrivalTime): array {
    require_once __DIR__ . '/flight_helpers.php';
    
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    $distance = haversine_km($oLat, $oLon, $dLat, $dLon);
    
    // Estimate travel time (700 km/h + 3 hours for airport procedures)
    $flightMinutes = ($distance / 700.0) * 60;
    $airportTime = 180; // 3 hours total (check-in, security, transfers)
    $totalMinutes = $flightMinutes + $airportTime;
    
    // Calculate departure time
    $departureTime = $arrivalTime - ($totalMinutes * 60);
    
    if ($departureTime < time()) {
        return ['error' => 'Cannot reach on time by flight'];
    }
    
    // Get actual route
    $departHHMM = date('H:i', $departureTime);
    $result = build_flight_route($pdo, $origin, $destination, $departHHMM);
    
    if (isset($result['error']) || empty($result['legs'])) {
        return ['error' => 'No flight available'];
    }
    
    $totalCost = (int)($distance * 4.5); // ₹4.5/km for flight
    
    return [
        'mode' => 'flight',
        'type' => 'airplane',
        'departure_time' => date('Y-m-d H:i', $departureTime),
        'arrival_time' => date('Y-m-d H:i', $arrivalTime),
        'duration_minutes' => (int)$totalMinutes,
        'distance_km' => round($distance, 1),
        'cost_inr' => $totalCost,
        'legs' => $result['legs'],
        'instructions' => [REDACTED]($result['legs'], $departureTime),
        'comfort_score' => 9,
        'reliability_score' => 9
    ];
}

/**
 * Plan combination route (e.g., taxi + train)
 */
function plan_combo_route(PDO $pdo, array $origin, array $destination, int $arrivalTime): array {
    // Try: Taxi to station → Train → Taxi from station
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    // Find nearest train stations
    $originStation = [REDACTED]($pdo, $oLat, $oLon, 'train');
    $destStation = [REDACTED]($pdo, $dLat, $dLon, 'train');
    
    if (!$originStation || !$destStation) {
        return ['error' => 'No train stations nearby'];
    }
    
    $distance = haversine_km($oLat, $oLon, $dLat, $dLon);
    $trainDist = haversine_km($originStation['lat'], $originStation['lon'], 
                              $destStation['lat'], $destStation['lon']);
    
    // Calculate times
    $taxi1Time = (haversine_km($oLat, $oLon, $originStation['lat'], $originStation['lon']) / 30) * 60;
    $trainTime = ($trainDist / 70) * 60;
    $taxi2Time = (haversine_km($destStation['lat'], $destStation['lon'], $dLat, $dLon) / 30) * 60;
    $totalMinutes = $taxi1Time + $trainTime + $taxi2Time + 30; // +30 buffer
    
    $departureTime = $arrivalTime - ($totalMinutes * 60);
    
    if ($departureTime < time()) {
        return ['error' => 'Cannot reach on time via combo'];
    }
    
    $cost = (int)(
        (haversine_km($oLat, $oLon, $originStation['lat'], $originStation['lon']) * 15) + // Taxi 1
        ($trainDist * 0.5) + // Train
        (haversine_km($destStation['lat'], $destStation['lon'], $dLat, $dLon) * 15) // Taxi 2
    );
    
    return [
        'mode' => 'combo',
        'type' => 'taxi_train_taxi',
        'departure_time' => date('Y-m-d H:i', $departureTime),
        'arrival_time' => date('Y-m-d H:i', $arrivalTime),
        'duration_minutes' => (int)$totalMinutes,
        'distance_km' => round($distance, 1),
        'cost_inr' => $cost,
        'legs' => [
            [
                'mode' => 'taxi',
                'from' => $origin['name'],
                'to' => $originStation['name'],
                'duration_minutes' => (int)$taxi1Time
            ],
            [
                'mode' => 'train',
                'from' => $originStation['name'],
                'to' => $destStation['name'],
                'duration_minutes' => (int)$trainTime
            ],
            [
                'mode' => 'taxi',
                'from' => $destStation['name'],
                'to' => $destination['name'],
                'duration_minutes' => (int)$taxi2Time
            ]
        ],
        'instructions' => [REDACTED]($origin, $destination, $originStation, $destStation, $departureTime),
        'comfort_score' => 8,
        'reliability_score' => 7
    ];
}

/**
 * Get traffic multiplier based on time of day
 */
function [REDACTED](int $hour): float {
    // Peak hours: 7-10 AM, 5-8 PM
    if (($hour >= 7 && $hour <= 10) || ($hour >= 17 && $hour <= 20)) {
        return 1.3; // 30% slower during peak
    } else if ($hour >= 22 || $hour <= 5) {
        return 0.9; // 10% faster at night
    }
    return 1.0; // Normal
}

/**
 * Get traffic warnings for route
 */
function [REDACTED](float $oLat, float $oLon, float $dLat, float $dLon, int $departTime, int $arriveTime): array {
    $warnings = [];
    
    $departHour = (int)date('H', $departTime);
    $arriveHour = (int)date('H', $arriveTime);
    
    // Check if passing through peak hours
    for ($h = $departHour; $h <= $arriveHour; $h++) {
        if ($h >= 7 && $h <= 10) {
            $warnings[] = [
                'time' => sprintf('%02d:00', $h),
                'severity' => 'high',
                'message' => 'Morning rush hour traffic expected'
            ];
        } else if ($h >= 17 && $h <= 20) {
            $warnings[] = [
                'time' => sprintf('%02d:00', $h),
                'severity' => 'high',
                'message' => 'Evening rush hour traffic expected'
            ];
        } else if ($h >= 12 && $h <= 14) {
            $warnings[] = [
                'time' => sprintf('%02d:00', $h),
                'severity' => 'medium',
                'message' => 'Moderate traffic due to lunch hour'
            ];
        }
    }
    
    return array_unique($warnings, SORT_REGULAR);
}

/**
 * Score and rank routes
 */
function [REDACTED](array $routes, int $arrivalTime, bool $hasVehicle): array {
    foreach ($routes as &$route) {
        $score = 0;
        
        // Time score (earlier departure = better)
        $departTime = strtotime($route['departure_time']);
        $bufferMinutes = ($arrivalTime - $departTime) / 60 - $route['duration_minutes'];
        $score += min(30, $bufferMinutes / 2); // Max 30 points for buffer
        
        // Cost score (cheaper = better)
        $costScore = max(0, 50 - ($route['cost_inr'] / 50));
        $score += $costScore;
        
        // Comfort score
        $score += $route['comfort_score'] * 2;
        
        // Reliability score
        $score += $route['reliability_score'] * 2;
        
        // Bonus for own vehicle
        if ($hasVehicle && $route['type'] === 'own_car') {
            $score += 20;
        }
        
        $route['velora_score'] = round($score, 1);
    }
    
    // Sort by score (highest first)
    usort($routes, function($a, $b) {
        return $b['velora_score'] <=> $a['velora_score'];
    });
    
    // Mark recommended
    if (count($routes) > 0) {
        $routes[0]['recommended'] = true;
    }
    
    return $routes;
}

/**
 * Generate driving instructions with traffic warnings
 */
function [REDACTED](array $origin, array $destination, int $departTime, array $trafficWarnings): array {
    $instructions = [];
    
    $instructions[] = [
        'time' => date('H:i', $departTime),
        'action' => 'start',
        'text' => "Start from {$origin['name']}",
        'icon' => '🚗'
    ];
    
    foreach ($trafficWarnings as $warning) {
        $instructions[] = [
            'time' => $warning['time'],
            'action' => 'warning',
            'text' => $warning['message'],
            'severity' => $warning['severity'],
            'icon' => '⚠️'
        ];
    }
    
    $instructions[] = [
        'time' => date('H:i', $departTime + 1800), // +30 min
        'action' => 'continue',
        'text' => "Continue on main route to {$destination['name']}",
        'icon' => '➡️'
    ];
    
    return $instructions;
}

/**
 * Generate bus instructions
 */
function [REDACTED](array $legs, int $departTime): array {
    $instructions = [];
    $currentTime = $departTime;
    
    foreach ($legs as $i => $leg) {
        if ($leg['mode'] === 'walk') {
            $instructions[] = [
                'time' => date('H:i', $currentTime),
                'action' => 'walk',
                'text' => "Walk to {$leg['to']}",
                'duration' => $leg['duration_s'] / 60,
                'icon' => '🚶'
            ];
        } else if ($leg['mode'] === 'bus') {
            $instructions[] = [
                'time' => date('H:i', $currentTime),
                'action' => 'board',
                'text' => "Board {$leg['operator_name']} bus to {$leg['to']}",
                'duration' => $leg['duration_s'] / 60,
                'icon' => '🚌'
            ];
        }
        $currentTime += $leg['duration_s'];
    }
    
    return $instructions;
}

/**
 * Generate train instructions
 */
function [REDACTED](array $legs, int $departTime): array {
    $instructions = [];
    $currentTime = $departTime;
    
    foreach ($legs as $leg) {
        if ($leg['mode'] === 'walk') {
            $instructions[] = [
                'time' => date('H:i', $currentTime),
                'action' => 'walk',
                'text' => "Walk to {$leg['to']}",
                'icon' => '🚶'
            ];
        } else if ($leg['mode'] === 'train') {
            $instructions[] = [
                'time' => date('H:i', $currentTime),
                'action' => 'board',
                'text' => "Board train to {$leg['to']}",
                'icon' => '🚂'
            ];
        } else if ($leg['mode'] === 'taxi') {
            $instructions[] = [
                'time' => date('H:i', $currentTime),
                'action' => 'taxi',
                'text' => "Take taxi to {$leg['to']}",
                'icon' => '🚕'
            ];
        }
        $currentTime += $leg['duration_s'];
    }
    
    return $instructions;
}

/**
 * Generate flight instructions
 */
function [REDACTED](array $legs, int $departTime): array {
    $instructions = [];
    $currentTime = $departTime;
    
    $instructions[] = [
        'time' => date('H:i', $currentTime),
        'action' => 'checkin',
        'text' => "Arrive at airport for check-in (2 hours before flight)",
        'icon' => '✈️'
    ];
    
    foreach ($legs as $leg) {
        if ($leg['mode'] === 'flight') {
            $instructions[] = [
                'time' => date('H:i', $currentTime + 7200), // +2 hours
                'action' => 'board',
                'text' => "Board flight to {$leg['to']}",
                'icon' => '✈️'
            ];
        }
        $currentTime += $leg['duration_s'];
    }
    
    return $instructions;
}

/**
 * Generate combo instructions
 */
function [REDACTED](array $origin, array $destination, array $originStation, array $destStation, int $departTime): array {
    $instructions = [];
    
    $instructions[] = [
        'time' => date('H:i', $departTime),
        'action' => 'taxi',
        'text' => "Take taxi from {$origin['name']} to {$originStation['name']}",
        'icon' => '🚕'
    ];
    
    $instructions[] = [
        'time' => date('H:i', $departTime + 1800),
        'action' => 'board',
        'text' => "Board train at {$originStation['name']}",
        'icon' => '🚂'
    ];
    
    $instructions[] = [
        'time' => date('H:i', $departTime + 7200),
        'action' => 'taxi',
        'text' => "Take taxi from {$destStation['name']} to {$destination['name']}",
        'icon' => '🚕'
    ];
    
    return $instructions;
}

/**
 * Find nearest station of given type
 */
function [REDACTED](PDO $pdo, float $lat, float $lon, string $type): ?array {
    $sql = "SELECT id, name, lat, lon, state
            FROM stations
            WHERE type = :type
            ORDER BY (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(lat)) * 
                     COS(RADIANS(lon) - RADIANS(:lon)) + 
                     SIN(RADIANS(:lat)) * SIN(RADIANS(lat)))) ASC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':type' => $type, ':lat' => $lat, ':lon' => $lon]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
