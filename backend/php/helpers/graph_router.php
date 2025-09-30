<?php
/**
 * Simple Graph-Based Router for Velora
 * Uses weighted graph traversal (simplified Dijkstra) for bus/train chains
 */

/**
 * Build station graph with weighted edges
 * Returns array of nodes and edges for pathfinding
 */
function build_station_graph(PDO $pdo, float $oLat, float $oLon, float $dLat, float $dLon, string $mode = 'bus'): array {
    // Calculate corridor bounding box (balanced for performance + coverage)
    $tripDist = haversine_km($oLat, $oLon, $dLat, $dLon);
    // Dynamic buffer: smaller for short trips, larger for long trips
    $buffer = min(0.3, max(0.2, $tripDist / 300)); // 0.2-0.3 degrees (~20-30km)
    $minLat = min($oLat, $dLat) - $buffer;
    $maxLat = max($oLat, $dLat) + $buffer;
    $minLon = min($oLon, $dLon) - $buffer;
    $maxLon = max($oLon, $dLon) + $buffer;
    
    // Get stations in corridor, prioritizing those near origin/destination
    $sql = "SELECT id, name, lat, lon, state, type,
            LEAST(
                (6371 * ACOS(COS(RADIANS(:oLat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:oLon)) + SIN(RADIANS(:oLat2)) * SIN(RADIANS(lat)))),
                (6371 * ACOS(COS(RADIANS(:dLat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:dLon)) + SIN(RADIANS(:dLat2)) * SIN(RADIANS(lat))))
            ) as nearest_dist
            FROM stations 
            WHERE type = :type 
              AND lat BETWEEN :minLat AND :maxLat 
              AND lon BETWEEN :minLon AND :maxLon
            ORDER BY nearest_dist ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':type' => $mode,
        ':minLat' => $minLat,
        ':maxLat' => $maxLat,
        ':minLon' => $minLon,
        ':maxLon' => $maxLon,
        ':oLat' => $oLat,
        ':oLon' => $oLon,
        ':oLat2' => $oLat,
        ':dLat' => $dLat,
        ':dLon' => $dLon,
        ':dLat2' => $dLat
    ]);
    
    $nodes = [];
    $maxNodes = 200; // Hard limit to prevent memory exhaustion
    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nodes[$row['id']] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'lat' => (float)$row['lat'],
            'lon' => (float)$row['lon'],
            'state' => $row['state'] ?? '',
            'type' => $row['type']
        ];
        $count++;
        if ($count >= $maxNodes) {
            break; // Stop to prevent memory issues
        }
    }
    
    // Build edges between nearby stations
    $edges = [];
    $maxEdgeKm = 50; // Max distance for direct connection (realistic bus range)
    
    foreach ($nodes as $fromId => $fromNode) {
        foreach ($nodes as $toId => $toNode) {
            if ($fromId >= $toId) continue; // Avoid duplicates and self-loops
            
            $dist = haversine_km($fromNode['lat'], $fromNode['lon'], 
                                $toNode['lat'], $toNode['lon']);
            
            if ($dist <= $maxEdgeKm) {
                // Calculate weight (time in minutes)
                $speedKmh = ($mode === 'train') ? 70 : 40; // Train faster than bus
                $timeMin = ($dist / $speedKmh) * 60;
                
                $edges[] = [
                    'from' => $fromId,
                    'to' => $toId,
                    'dist_km' => $dist,
                    'time_min' => $timeMin,
                    'mode' => $mode
                ];
                
                // Add reverse edge
                $edges[] = [
                    'from' => $toId,
                    'to' => $fromId,
                    'dist_km' => $dist,
                    'time_min' => $timeMin,
                    'mode' => $mode
                ];
            }
        }
    }
    
    return ['nodes' => $nodes, 'edges' => $edges];
}

/**
 * Find shortest path using simplified Dijkstra
 * Returns array of node IDs representing the path
 */
function find_shortest_path(array $nodes, array $edges, int $startId, int $endId, float $oLat, float $oLon, float $dLat, float $dLon): ?array {
    // Build adjacency list
    $graph = [];
    foreach ($edges as $edge) {
        if (!isset($graph[$edge['from']])) {
            $graph[$edge['from']] = [];
        }
        $graph[$edge['from']][] = $edge;
    }
    
    // Calculate corridor bearing for directional filtering
    $corridorBearing = graph_bearing_deg($oLat, $oLon, $dLat, $dLon);
    
    // Dijkstra's algorithm with directional preference
    $dist = [];
    $prev = [];
    $visited = [];
    $queue = new SplPriorityQueue();
    
    $dist[$startId] = 0;
    $queue->insert($startId, 0);
    
    while (!$queue->isEmpty()) {
        $current = $queue->extract();
        
        if (isset($visited[$current])) continue;
        $visited[$current] = true;
        
        if ($current === $endId) {
            // Reconstruct path
            $path = [];
            $node = $endId;
            while (isset($prev[$node])) {
                array_unshift($path, $node);
                $node = $prev[$node];
            }
            array_unshift($path, $startId);
            return $path;
        }
        
        if (!isset($graph[$current])) continue;
        
        foreach ($graph[$current] as $edge) {
            $neighbor = $edge['to'];
            if (isset($visited[$neighbor])) continue;
            
            // Check if edge is in forward direction (bearing filter)
            $fromNode = $nodes[$current];
            $toNode = $nodes[$neighbor];
            $edgeBearing = graph_bearing_deg($fromNode['lat'], $fromNode['lon'], 
                                      $toNode['lat'], $toNode['lon']);
            $bearingDiff = [REDACTED]($edgeBearing, $corridorBearing);
            
            // Skip edges that go too far off-corridor (>30° deviation)
            if ($bearingDiff > 30) continue;
            
            // Calculate cost with bearing penalty
            $baseCost = $edge['time_min'];
            $bearingPenalty = $bearingDiff * 0.5; // 0.5 min per degree deviation
            $newDist = $dist[$current] + $baseCost + $bearingPenalty;
            
            if (!isset($dist[$neighbor]) || $newDist < $dist[$neighbor]) {
                $dist[$neighbor] = $newDist;
                $prev[$neighbor] = $current;
                // Priority = negative distance (SplPriorityQueue is max-heap)
                $queue->insert($neighbor, -$newDist);
            }
        }
    }
    
    return null; // No path found
}

/**
 * Calculate bearing between two points (0-360 degrees)
 */
function graph_bearing_deg(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dlam = deg2rad($lon2 - $lon1);
    
    $y = sin($dlam) * cos($phi2);
    $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($dlam);
    $br = atan2($y, $x);
    $deg = rad2deg($br);
    
    return fmod(($deg + 360.0), 360.0);
}

/**
 * Calculate angular difference between two bearings
 */
function [REDACTED](float $a, float $b): float {
    $d = fmod(($a - $b + 540.0), 360.0) - 180.0;
    return abs($d);
}

/**
 * Enhanced bus chaining using graph-based pathfinding
 */
function [REDACTED](PDO $pdo, array $origin, array $destination, string $departHHMM, string $tomtomKey): array {
    $oLat = (float)$origin['lat'];
    $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat'];
    $dLon = (float)$destination['lon'];
    
    $tripDist = haversine_km($oLat, $oLon, $dLat, $dLon);
    @error_log('[GraphRouter] Building station graph for bus chain, trip distance: ' . round($tripDist, 1) . 'km');
    
    // Reject very long distances for bus
    if ($tripDist > 300) {
        @error_log('[GraphRouter] Distance too long for bus (>300km), should use train');
        return ['legs' => [], 'intermediate_stops' => [], 'error' => 'Distance too long for bus, use train mode'];
    }
    
    // Build station graph
    $graph = build_station_graph($pdo, $oLat, $oLon, $dLat, $dLon, 'bus');
    $nodes = $graph['nodes'];
    $edges = $graph['edges'];
    
    @error_log('[GraphRouter] Graph built: ' . count($nodes) . ' nodes, ' . count($edges) . ' edges');
    
    if (count($nodes) < 2) {
        @error_log('[GraphRouter] Insufficient stations, using fallback');
        return ['legs' => [], 'intermediate_stops' => [], 'error' => 'Insufficient stations'];
    }
    
    // Find nearest start and end stations
    $startStation = null;
    $endStation = null;
    $minStartDist = PHP_FLOAT_MAX;
    $minEndDist = PHP_FLOAT_MAX;
    
    foreach ($nodes as $node) {
        $distFromOrigin = haversine_km($oLat, $oLon, $node['lat'], $node['lon']);
        $distToDest = haversine_km($dLat, $dLon, $node['lat'], $node['lon']);
        
        if ($distFromOrigin < $minStartDist) {
            $minStartDist = $distFromOrigin;
            $startStation = $node;
        }
        
        if ($distToDest < $minEndDist) {
            $minEndDist = $distToDest;
            $endStation = $node;
        }
    }
    
    if (!$startStation || !$endStation) {
        return ['legs' => [], 'intermediate_stops' => [], 'error' => 'No stations found'];
    }
    
    @error_log('[GraphRouter] Start: ' . $startStation['name'] . ', End: ' . $endStation['name']);
    
    // Find shortest path
    $path = find_shortest_path($nodes, $edges, $startStation['id'], $endStation['id'], 
                               $oLat, $oLon, $dLat, $dLon);
    
    if (!$path || count($path) < 2) {
        @error_log('[GraphRouter] No path found, using direct connection');
        $path = [$startStation['id'], $endStation['id']];
    }
    
    @error_log('[GraphRouter] Path found with ' . count($path) . ' stops');
    
    // Limit chain length to avoid unrealistic long chains
    $maxHops = 6;
    if (count($path) > $maxHops) {
        @error_log('[GraphRouter] Path too long (' . count($path) . ' stops), truncating to ' . $maxHops);
        $path = array_slice($path, 0, $maxHops);
    }
    
    // Build legs from path
    $legs = [];
    $cursor = $departHHMM;
    
    // Walk to first station
    $walkDist = haversine_km($oLat, $oLon, $startStation['lat'], $startStation['lon']);
    
    // Reject if walking distance is too far (>3km is unrealistic)
    if ($walkDist > 3.0) {
        @error_log('[GraphRouter] Walk distance too far: ' . round($walkDist, 1) . 'km from origin to ' . $startStation['name']);
        return ['legs' => [], 'intermediate_stops' => [], 'error' => 'No nearby bus station (nearest is ' . round($walkDist, 1) . 'km away)'];
    }
    
    $walkMin = max(5, (int)round(($walkDist / 4.5) * 60));
    
    $legs[] = [
        'mode' => 'walk',
        'operator_name' => 'Walk',
        'from' => $origin['name'] ?? 'Origin',
        'to' => $startStation['name'],
        'from_lat' => $oLat,
        'from_lon' => $oLon,
        'to_lat' => $startStation['lat'],
        'to_lon' => $startStation['lon'],
        'fare' => 0,
        'duration_s' => $walkMin * 60,
        'departure' => $cursor,
        'arrival' => [REDACTED]($cursor, $walkMin),
        'distance_km' => round($walkDist, 1),
        'instructions' => [
            ['step_id'=>'w1','mode'=>'walk','text'=>'Walk to '.$startStation['name'],'lat'=>$oLat,'lon'=>$oLon,'notify_when_m'=>50]
        ]
    ];
    
    $cursor = [REDACTED]($cursor, $walkMin + 5);
    
    // Bus legs between stations
    for ($i = 0; $i < count($path) - 1; $i++) {
        $fromNode = $nodes[$path[$i]];
        $toNode = $nodes[$path[$i + 1]];
        
        $segDist = haversine_km($fromNode['lat'], $fromNode['lon'], 
                                $toNode['lat'], $toNode['lon']);
        $travelMin = (int)round(($segDist / 40.0) * 60); // 40 km/h bus speed
        $fare = (int)round($segDist * 2.5); // ₹2.5/km
        
        $arr = [REDACTED]($cursor, $travelMin);
        
        // Determine operator
        $operator = [REDACTED]($fromNode['state'] ?? '', $toNode['state'] ?? '');
        
        $legs[] = [
            'mode' => 'bus',
            'operator_name' => $operator,
            'from' => $fromNode['name'],
            'to' => $toNode['name'],
            'from_lat' => $fromNode['lat'],
            'from_lon' => $fromNode['lon'],
            'to_lat' => $toNode['lat'],
            'to_lon' => $toNode['lon'],
            'fare' => $fare,
            'duration_s' => $travelMin * 60,
            'departure' => $cursor,
            'arrival' => $arr,
            'distance_km' => round($segDist, 1),
            'instructions' => [
                ['step_id'=>'b'.($i+1).'b','mode'=>'bus','text'=>'Board '.$operator.' to '.$toNode['name'],'lat'=>$fromNode['lat'],'lon'=>$fromNode['lon'],'notify_when_m'=>200],
                ['step_id'=>'b'.($i+1).'r','mode'=>'bus','text'=>'Riding to '.$toNode['name'],'lat'=>$toNode['lat'],'lon'=>$toNode['lon'],'distance_m'=>(int)($segDist*1000),'duration_s'=>$travelMin*60],
                ['step_id'=>'b'.($i+1).'a','mode'=>'alight','text'=>'Alight at '.$toNode['name'],'lat'=>$toNode['lat'],'lon'=>$toNode['lon'],'notify_when_m'=>100]
            ]
        ];
        
        $cursor = [REDACTED]($arr, ($i < count($path) - 2) ? 10 : 0);
    }
    
    // Walk to destination
    $walkDistEnd = haversine_km($endStation['lat'], $endStation['lon'], $dLat, $dLon);
    $walkMinEnd = max(5, (int)round(($walkDistEnd / 4.5) * 60));
    
    $legs[] = [
        'mode' => 'walk',
        'operator_name' => 'Walk',
        'from' => $endStation['name'],
        'to' => $destination['name'] ?? 'Destination',
        'from_lat' => $endStation['lat'],
        'from_lon' => $endStation['lon'],
        'to_lat' => $dLat,
        'to_lon' => $dLon,
        'fare' => 0,
        'duration_s' => $walkMinEnd * 60,
        'departure' => $cursor,
        'arrival' => [REDACTED]($cursor, $walkMinEnd),
        'distance_km' => round($walkDistEnd, 1),
        'instructions' => [
            ['step_id'=>'w2','mode'=>'walk','text'=>'Walk to destination','lat'=>$dLat,'lon'=>$dLon,'notify_when_m'=>50]
        ]
    ];
    
    // Build intermediate stops list
    $intermediateStops = [];
    for ($i = 1; $i < count($path) - 1; $i++) {
        $node = $nodes[$path[$i]];
        $intermediateStops[] = [
            'name' => $node['name'],
            'lat' => $node['lat'],
            'lon' => $node['lon'],
            'type' => 'bus'
        ];
    }
    
    return [
        'legs' => $legs,
        'intermediate_stops' => $intermediateStops,
        'path_nodes' => count($path),
        'method' => 'graph_dijkstra'
    ];
}

/**
 * Determine bus operator based on state
 */
function [REDACTED](string $state1, string $state2): string {
    static $operators = null;
    if ($operators === null) {
        $operators = [
            'Kerala' => 'KSRTC',
            'Tamil Nadu' => 'TNSTC',
            'Karnataka' => 'KSRTC Karnataka',
            'Maharashtra' => 'MSRTC',
            'Andhra Pradesh' => 'APSRTC',
            'Telangana' => 'TSRTC',
            'Gujarat' => 'GSRTC',
            'Rajasthan' => 'RSRTC'
        ];
    }
    
    $state = $state1 ?: $state2;
    return $operators[$state] ?? 'State Transport';
}
