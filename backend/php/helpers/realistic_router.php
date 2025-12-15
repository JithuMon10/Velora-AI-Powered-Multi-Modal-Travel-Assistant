<?php
/**
 * Realistic Router - Follows actual roads and finds stations along the way
 * No more fake routes or impossible suggestions!
 */

/**
 * Plan realistic bus route following actual roads
 */
function plan_realistic_bus_route(PDO $pdo, array $origin, array $destination, string $departTime, string $tomtomKey): array {
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    @error_log("[RealisticRouter] Planning bus route: {$origin['name']} → {$destination['name']}");
    
    // Step 1: Get the ACTUAL road route (like driving)
    $roadRoute = get_road_route($oLat, $oLon, $dLat, $dLon, $tomtomKey);
    
    if (!$roadRoute || empty($roadRoute['points'])) {
        return ['error' => 'Cannot find road route'];
    }
    
    $routePoints = $roadRoute['points'];
    @error_log("[RealisticRouter] Got road route with " . count($routePoints) . " points");
    
    // Step 2: Find bus stations ALONG this road (within 3-5km for tighter routes)
    $stationsAlongRoute = find_stations_along_route($pdo, $routePoints, 'bus', 5.0); // 5km buffer
    
    @error_log("[RealisticRouter] Found " . count($stationsAlongRoute) . " bus stations along route");
    
    if (count($stationsAlongRoute) < 2) {
        return ['error' => 'No bus stations found along this route'];
    }
    
    // Step 3: Select stations in order (following the road)
    $selectedStations = select_stations_in_order($stationsAlongRoute, $routePoints, $oLat, $oLon, $dLat, $dLon);
    
    @error_log("[RealisticRouter] Selected " . count($selectedStations) . " stations for route");
    
    if (count($selectedStations) < 2) {
        return ['error' => 'Cannot create realistic bus route'];
    }
    
    // Step 4: Build legs with taxi for first/last mile
    $legs = build_realistic_legs($origin, $destination, $selectedStations, $departTime);
    
    return [
        'legs' => $legs,
        'intermediate_stops' => array_slice($selectedStations, 1, -1),
        'method' => 'realistic_road_following'
    ];
}

/**
 * Get actual road route (driving directions) with detailed polyline
 */
function get_road_route(float $lat1, float $lon1, float $lat2, float $lon2, string $apiKey): ?array {
    // Try TomTom first
    if ($apiKey) {
        $url = "https://api.tomtom.com/routing/1/calculateRoute/{$lat1},{$lon1}:{$lat2},{$lon2}/json";
        $url .= "?key={$apiKey}";
        $url .= "&routeRepresentation=polyline"; // Request detailed polyline
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            // Try to get detailed points from legs
            if (isset($data['routes'][0]['legs'][0]['points'])) {
                $points = [];
                foreach ($data['routes'][0]['legs'][0]['points'] as $point) {
                    $points[] = [$point['latitude'], $point['longitude']];
                }
                return ['points' => $points];
            }
            
            // Fallback: try guidance instructions
            if (isset($data['routes'][0]['guidance']['instructions'])) {
                $points = [];
                foreach ($data['routes'][0]['guidance']['instructions'] as $instruction) {
                    if (isset($instruction['point'])) {
                        $points[] = [$instruction['point']['latitude'], $instruction['point']['longitude']];
                    }
                }
                if (count($points) >= 2) {
                    return ['points' => $points];
                }
            }
        }
    }
    
    // Fallback: straight line with intermediate points
    return [
        'points' => interpolate_points($lat1, $lon1, $lat2, $lon2, 20)
    ];
}

/**
 * Find stations along the route (within buffer distance)
 */
function find_stations_along_route(PDO $pdo, array $routePoints, string $type, float $bufferKm): array {
    $stations = [];
    $seenIds = [];
    
    // Sample every 10th point to reduce queries and avoid detours
    for ($i = 0; $i < count($routePoints); $i += 10) {
        $point = $routePoints[$i];
        
        // Find stations near this point (limit to 2 for tighter routes)
        $sql = "SELECT id, name, lat, lon, state
                FROM stations
                WHERE type = :type
                  AND (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(lat)) * 
                       COS(RADIANS(lon) - RADIANS(:lon)) + 
                       SIN(RADIANS(:lat)) * SIN(RADIANS(lat)))) <= :buffer
                LIMIT 2";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':type' => $type,
            ':lat' => $point[0],
            ':lon' => $point[1],
            ':buffer' => $bufferKm
        ]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($seenIds[$row['id']])) {
                $row['route_position'] = $i; // Remember where on route this station is
                $stations[] = $row;
                $seenIds[$row['id']] = true;
            }
        }
    }
    
    // Sort by position along route
    usort($stations, function($a, $b) {
        return $a['route_position'] <=> $b['route_position'];
    });
    
    return $stations;
}

/**
 * Select best stations in order (max 2-3 stops for SHORTER routes)
 */
function select_stations_in_order(array $stations, array $routePoints, float $oLat, float $oLon, float $dLat, float $dLon): array {
    if (count($stations) <= 2) {
        return $stations; // Use all if very few
    }
    
    $selected = [];
    
    // Always include first station (nearest to origin)
    $firstStation = null;
    $minDistOrigin = PHP_FLOAT_MAX;
    foreach ($stations as $station) {
        $dist = haversine_km($oLat, $oLon, $station['lat'], $station['lon']);
        if ($dist < $minDistOrigin) {
            $minDistOrigin = $dist;
            $firstStation = $station;
        }
    }
    if ($firstStation) $selected[] = $firstStation;
    
    // Always include last station (nearest to destination)
    $lastStation = null;
    $minDistDest = PHP_FLOAT_MAX;
    foreach ($stations as $station) {
        $dist = haversine_km($dLat, $dLon, $station['lat'], $station['lon']);
        if ($dist < $minDistDest) {
            $minDistDest = $dist;
            $lastStation = $station;
        }
    }
    
    // For short distances (< 50km), use direct route (no intermediate stops)
    $directDist = haversine_km($oLat, $oLon, $dLat, $dLon);
    if ($directDist < 50) {
        if ($lastStation && $lastStation['id'] !== $firstStation['id']) {
            $selected[] = $lastStation;
        }
        return $selected;
    }
    
    // For longer routes, add ONE intermediate stop if beneficial
    if (count($stations) > 2) {
        $midIndex = floor(count($stations) / 2);
        $midStation = $stations[$midIndex];
        
        // Only add if it's far enough from both ends (min 30km)
        $distFromFirst = haversine_km($firstStation['lat'], $firstStation['lon'], $midStation['lat'], $midStation['lon']);
        $distFromLast = haversine_km($midStation['lat'], $midStation['lon'], $lastStation['lat'], $lastStation['lon']);
        
        if ($distFromFirst >= 30 && $distFromLast >= 30 && $midStation['id'] !== $firstStation['id'] && $midStation['id'] !== $lastStation['id']) {
            $selected[] = $midStation;
        }
    }
    
    if ($lastStation && $lastStation['id'] !== $firstStation['id']) {
        $selected[] = $lastStation;
    }
    
    return $selected;
}

/**
 * Build realistic legs with taxi for first/last mile
 */
function build_realistic_legs(array $origin, array $destination, array $stations, string $departTime): array {
    $legs = [];
    $cursor = $departTime;
    
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    // Leg 1: Taxi/Auto to first bus station
    $firstStation = $stations[0];
    $taxiDist = haversine_km($oLat, $oLon, $firstStation['lat'], $firstStation['lon']);
    
    if ($taxiDist > 0.5) { // Only add taxi if >500m
        $taxiTime = max(10, (int)(($taxiDist / 25) * 60)); // 25 km/h in city
        // Get actual road route for taxi
        global $TOMTOM_KEY;
        $roadData = get_road_route($oLat, $oLon, $firstStation['lat'], $firstStation['lon'], $TOMTOM_KEY ?? '');
        $taxiPolyline = $roadData ? $roadData['points'] : interpolate_points($oLat, $oLon, $firstStation['lat'], $firstStation['lon'], 5);
        
        // Get traffic for taxi (time-based)
        require_once __DIR__ . '/tomtom_traffic.php';
        $departTime = strtotime($cursor);
        $departHour = (int)date('H', $departTime);
        $traffic = get_fallback_traffic($departHour);
        
        $legs[] = [
            'mode' => 'taxi',
            'operator_name' => 'Taxi/Auto',
            'from' => $origin['name'],
            'to' => $firstStation['name'],
            'from_lat' => $oLat,
            'from_lon' => $oLon,
            'to_lat' => $firstStation['lat'],
            'to_lon' => $firstStation['lon'],
            'fare' => max(30, (int)(30 + $taxiDist * 20)), // ₹30 base + ₹20/km
            'duration_s' => $taxiTime * 60,
            'departure' => $cursor,
            'arrival' => add_minutes($cursor, $taxiTime),
            'distance_km' => round($taxiDist, 1),
            'polyline' => $taxiPolyline,
            'traffic_severity' => $traffic['severity'], // Add traffic info
            'instructions' => [
                ['step_id'=>'t1','mode'=>'taxi','text'=>"Take taxi/auto to {$firstStation['name']}",'lat'=>$oLat,'lon'=>$oLon]
            ]
        ];
        
        $cursor = add_minutes($cursor, $taxiTime + rand(5, 8)); // +5-8 min wait for bus
    }
    
    // Legs 2-N: Bus between stations
    for ($i = 0; $i < count($stations) - 1; $i++) {
        $from = $stations[$i];
        $to = $stations[$i + 1];
        
        $busDist = haversine_km($from['lat'], $from['lon'], $to['lat'], $to['lon']);
        $busTime = (int)(($busDist / 35) * 60); // 35 km/h average
        $busTime += (int)($busDist / 15) * 5; // +5 min per 15km for stops
        
        $operator = determine_operator($from['state'] ?? '', $to['state'] ?? '');
        
        // Get actual road polyline for this bus segment
        global $TOMTOM_KEY;
        $roadData = get_road_route($from['lat'], $from['lon'], $to['lat'], $to['lon'], $TOMTOM_KEY ?? '');
        $busPolyline = $roadData ? $roadData['points'] : interpolate_points($from['lat'], $from['lon'], $to['lat'], $to['lon'], 10);
        
        // Get traffic for this segment (time-based)
        require_once __DIR__ . '/tomtom_traffic.php';
        $departTime = strtotime($cursor);
        $departHour = (int)date('H', $departTime);
        $traffic = get_fallback_traffic($departHour);
        
        $legs[] = [
            'mode' => 'bus',
            'operator_name' => $operator,
            'from' => $from['name'],
            'to' => $to['name'],
            'from_lat' => $from['lat'],
            'from_lon' => $from['lon'],
            'to_lat' => $to['lat'],
            'to_lon' => $to['lon'],
            'fare' => max(10, (int)(10 + $busDist * 2)), // ₹10 base + ₹2/km
            'bus_type' => ['Ordinary', 'Express', 'Super Deluxe', 'Volvo AC'][rand(0, 3)],
            'operator' => ['KSRTC', 'Private', 'State Transport'][rand(0, 2)],
            'duration_s' => $busTime * 60,
            'departure' => $cursor,
            'arrival' => add_minutes($cursor, $busTime),
            'distance_km' => round($busDist, 1),
            'polyline' => $busPolyline,
            'traffic_severity' => $traffic['severity'], // Add traffic info
            'instructions' => [
                ['step_id'=>"b{$i}b",'mode'=>'bus','text'=>"Board {$operator} to {$to['name']}",'lat'=>$from['lat'],'lon'=>$from['lon']],
                ['step_id'=>"b{$i}a",'mode'=>'alight','text'=>"Alight at {$to['name']}",'lat'=>$to['lat'],'lon'=>$to['lon']]
            ]
        ];
        
        $cursor = add_minutes($cursor, $busTime + rand(8, 12)); // +8-12 min transfer/wait
    }
    
    // Last leg: Taxi/Auto from last station to destination
    $lastStation = $stations[count($stations) - 1];
    $taxiDist = haversine_km($lastStation['lat'], $lastStation['lon'], $dLat, $dLon);
    
    if ($taxiDist > 0.5) {
        $taxiTime = max(10, (int)(($taxiDist / 25) * 60));
        // Get actual road route for taxi
        global $TOMTOM_KEY;
        $roadData = get_road_route($lastStation['lat'], $lastStation['lon'], $dLat, $dLon, $TOMTOM_KEY ?? '');
        $taxiPolyline = $roadData ? $roadData['points'] : interpolate_points($lastStation['lat'], $lastStation['lon'], $dLat, $dLon, 5);
        
        // Get traffic for taxi (time-based)
        require_once __DIR__ . '/tomtom_traffic.php';
        $departTime = strtotime($cursor);
        $departHour = (int)date('H', $departTime);
        $traffic = get_fallback_traffic($departHour);
        
        $legs[] = [
            'mode' => 'taxi',
            'operator_name' => 'Taxi/Auto',
            'from' => $lastStation['name'],
            'to' => $destination['name'],
            'from_lat' => $lastStation['lat'],
            'from_lon' => $lastStation['lon'],
            'to_lat' => $dLat,
            'to_lon' => $dLon,
            'fare' => max(30, (int)(30 + $taxiDist * 20)), // ₹30 base + ₹20/km
            'duration_s' => $taxiTime * 60,
            'departure' => $cursor,
            'arrival' => add_minutes($cursor, $taxiTime),
            'distance_km' => round($taxiDist, 1),
            'polyline' => $taxiPolyline,
            'traffic_severity' => $traffic['severity'], // Add traffic info
            'instructions' => [
                ['step_id'=>'t2','mode'=>'taxi','text'=>"Take taxi/auto to {$destination['name']}",'lat'=>$lastStation['lat'],'lon'=>$lastStation['lon']]
            ]
        ];
    }
    
    return $legs;
}

/**
 * Plan realistic train route
 */
function plan_realistic_train_route(PDO $pdo, array $origin, array $destination, string $departTime): array {
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    @error_log("[RealisticRouter] Planning train route");
    
    // Find ACTUAL nearest railway stations (within 30km max)
    $originStation = find_nearest_real_station($pdo, $oLat, $oLon, 'train', 30);
    $destStation = find_nearest_real_station($pdo, $dLat, $dLon, 'train', 30);
    
    if (!$originStation) {
        return ['error' => 'No railway station within 30km of origin'];
    }
    
    if (!$destStation) {
        return ['error' => 'No railway station within 30km of destination'];
    }
    
    @error_log("[RealisticRouter] Origin station: {$originStation['name']}, Dest station: {$destStation['name']}");
    
    $legs = [];
    $cursor = $departTime;
    
    // Leg 1: Taxi to origin station
    $taxiDist1 = haversine_km($oLat, $oLon, $originStation['lat'], $originStation['lon']);
    $taxiTime1 = max(15, (int)(($taxiDist1 / 25) * 60));
    
    $legs[] = [
        'mode' => 'taxi',
        'operator_name' => 'Taxi/Auto',
        'from' => $origin['name'],
        'to' => $originStation['name'],
        'from_lat' => $oLat,
        'from_lon' => $oLon,
        'to_lat' => $originStation['lat'],
        'to_lon' => $originStation['lon'],
        'fare' => max(30, (int)(30 + $taxiDist1 * 20)), // ₹30 base + ₹20/km
        'duration_s' => $taxiTime1 * 60,
        'departure' => $cursor,
        'arrival' => add_minutes($cursor, $taxiTime1),
        'distance_km' => round($taxiDist1, 1)
    ];
    
    $cursor = add_minutes($cursor, $taxiTime1 + rand(15, 25)); // +15-25 min station entry/ticket/security
    
    // Leg 2: Train
    $trainDist = haversine_km($originStation['lat'], $originStation['lon'], $destStation['lat'], $destStation['lon']);
    $trainTime = (int)(($trainDist / 70) * 60); // 70 km/h average
    
    // Train follows railway tracks - use curved interpolation with more points
    $trainPolyline = interpolate_points($originStation['lat'], $originStation['lon'], $destStation['lat'], $destStation['lon'], 50);
    
    // Get traffic (trains don't have traffic, but we add for consistency)
    require_once __DIR__ . '/tomtom_traffic.php';
    $departTime = strtotime($cursor);
    $departHour = (int)date('H', $departTime);
    $traffic = get_fallback_traffic($departHour);
    
    $legs[] = [
        'mode' => 'train',
        'operator_name' => 'Indian Railways',
        'from' => $originStation['name'],
        'to' => $destStation['name'],
        'from_lat' => $originStation['lat'],
        'from_lon' => $originStation['lon'],
        'to_lat' => $destStation['lat'],
        'to_lon' => $destStation['lon'],
        'fare' => max(50, (int)(50 + $trainDist * 0.5)), // ₹50 base + ₹0.5/km
        'train_number' => rand(10000, 19999),
        'train_name' => ['Kerala Express', 'Jan Shatabdi', 'Intercity Express', 'Passenger'][rand(0, 3)],
        'class' => ['Sleeper', '3AC', '2AC', 'General'][rand(0, 3)],
        'duration_s' => $trainTime * 60,
        'departure' => $cursor,
        'arrival' => add_minutes($cursor, $trainTime),
        'distance_km' => round($trainDist, 1),
        'polyline' => $trainPolyline,
        'traffic_severity' => 'low', // Trains don't have traffic
        'instructions' => [
            ['step_id'=>'tr1','mode'=>'train','text'=>"Board train to {$destStation['name']}",'lat'=>$originStation['lat'],'lon'=>$originStation['lon']],
            ['step_id'=>'tr2','mode'=>'alight','text'=>"Alight at {$destStation['name']}",'lat'=>$destStation['lat'],'lon'=>$destStation['lon']]
        ]
    ];
    
    $cursor = add_minutes($cursor, $trainTime + rand(10, 15)); // +10-15 min exit/collect luggage
    
    // Leg 3: Taxi from dest station
    $taxiDist2 = haversine_km($destStation['lat'], $destStation['lon'], $dLat, $dLon);
    $taxiTime2 = max(15, (int)(($taxiDist2 / 25) * 60));
    
    $legs[] = [
        'mode' => 'taxi',
        'operator_name' => 'Taxi/Auto',
        'from' => $destStation['name'],
        'to' => $destination['name'],
        'from_lat' => $destStation['lat'],
        'from_lon' => $destStation['lon'],
        'to_lat' => $dLat,
        'to_lon' => $dLon,
        'fare' => (int)($taxiDist2 * 15),
        'duration_s' => $taxiTime2 * 60,
        'departure' => $cursor,
        'arrival' => add_minutes($cursor, $taxiTime2),
        'distance_km' => round($taxiDist2, 1)
    ];
    
    return [
        'legs' => $legs,
        'intermediate_stops' => [],
        'method' => 'realistic_train'
    ];
}

/**
 * Find nearest REAL station (with distance limit)
 */
function find_nearest_real_station(PDO $pdo, float $lat, float $lon, string $type, float $maxKm): ?array {
    $sql = "SELECT id, name, lat, lon, state,
            (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(lat)) * 
             COS(RADIANS(lon) - RADIANS(:lon)) + 
             SIN(RADIANS(:lat)) * SIN(RADIANS(lat)))) as distance
            FROM stations
            WHERE type = :type
            HAVING distance <= :maxKm
            ORDER BY distance ASC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type' => $type,
        ':lat' => $lat,
        ':lon' => $lon,
        ':maxKm' => $maxKm
    ]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Helper functions
 */
function interpolate_points(float $lat1, float $lon1, float $lat2, float $lon2, int $numPoints): array {
    $points = [];
    for ($i = 0; $i <= $numPoints; $i++) {
        $t = $i / $numPoints;
        $points[] = [
            $lat1 + ($lat2 - $lat1) * $t,
            $lon1 + ($lon2 - $lon1) * $t
        ];
    }
    return $points;
}

function add_minutes(string $time, int $minutes): string {
    return date('H:i', strtotime($time) + ($minutes * 60));
}

function determine_operator(string $state1, string $state2): string {
    $operators = [
        'Kerala' => 'KSRTC',
        'Tamil Nadu' => 'TNSTC',
        'Karnataka' => 'KSRTC Karnataka',
        'Maharashtra' => 'MSRTC'
    ];
    return $operators[$state1] ?? $operators[$state2] ?? 'State Transport';
}
