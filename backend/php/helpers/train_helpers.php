<?php
/**
 * Train routing helpers for Velora
 * Implements realistic multi-hop train journeys across India
 */

/**
 * Build realistic train route with intermediate stations
 * Uses major railway junctions for long-distance travel
 */
function build_train_route(PDO $pdo, array $origin, array $destination, string $departHHMM): array {
    $oLat = (float)$origin['lat']; $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat']; $dLon = (float)$destination['lon'];
    
    // Find nearest train stations
    $originStation = nearest_station($pdo, $oLat, $oLon, 'train');
    $destStation = nearest_station($pdo, $dLat, $dLon, 'train');
    
    if (!$originStation || !$destStation) {
        return ['legs' => [], 'intermediate_stops' => [], 'error' => 'No train stations found'];
    }
    
    $legs = [];
    $cursor = $departHHMM;
    
    // Walk to origin station
    $distToOriginStation = haversine_km($oLat, $oLon, (float)$originStation['lat'], (float)$originStation['lon']);
    $walkMin = max(5, (int)round(($distToOriginStation / 4.5) * 60));
    $legs[] = [
        'mode' => 'walk',
        'operator_name' => 'Walk',
        'from' => $origin['name'] ?? 'Origin',
        'to' => $originStation['name'],
        'from_lat' => $oLat, 'from_lon' => $oLon,
        'to_lat' => (float)$originStation['lat'], 'to_lon' => (float)$originStation['lon'],
        'fare' => 0,
        'duration_s' => $walkMin * 60,
        'departure' => $cursor,
        'arrival' => [REDACTED]($cursor, $walkMin),
        'distance_km' => round($distToOriginStation, 1),
        'instructions' => [
            ['step_id'=>'tw1','mode'=>'walk','text'=>'Walk to '.$originStation['name'],'lat'=>$oLat,'lon'=>$oLon,'notify_when_m'=>50]
        ]
    ];
    $cursor = [REDACTED]($cursor, $walkMin + 10); // 10 min wait
    
    // Determine intermediate stations for long journeys
    $directDist = haversine_km((float)$originStation['lat'], (float)$originStation['lon'], 
                               (float)$destStation['lat'], (float)$destStation['lon']);
    
    $trainStops = [$originStation];
    
    // Add intermediate junctions for distances > 300km
    if ($directDist > 300) {
        $intermediates = [REDACTED]($pdo, $originStation, $destStation, $directDist);
        $trainStops = array_merge($trainStops, $intermediates);
    }
    
    $trainStops[] = $destStation;
    
    // Build train legs between stations
    for ($i = 0; $i < count($trainStops) - 1; $i++) {
        $from = $trainStops[$i];
        $to = $trainStops[$i + 1];
        
        $segDist = haversine_km((float)$from['lat'], (float)$from['lon'], 
                                (float)$to['lat'], (float)$to['lon']);
        
        // Train speed: 60-80 km/h average
        $travelMin = (int)round(($segDist / 70.0) * 60);
        $fare = (int)round($segDist * 0.5); // ₹0.50/km baseline
        
        $arr = [REDACTED]($cursor, $travelMin);
        
        $legs[] = [
            'mode' => 'train',
            'operator_name' => 'Indian Railways (IRCTC)',
            'from' => $from['name'],
            'to' => $to['name'],
            'from_lat' => (float)$from['lat'], 'from_lon' => (float)$from['lon'],
            'to_lat' => (float)$to['lat'], 'to_lon' => (float)$to['lon'],
            'fare' => $fare,
            'duration_s' => $travelMin * 60,
            'departure' => $cursor,
            'arrival' => $arr,
            'distance_km' => round($segDist, 1),
            'instructions' => [
                ['step_id'=>'tr'.($i+1).'b','mode'=>'train','text'=>'Board train to '.$to['name'],'lat'=>(float)$from['lat'],'lon'=>(float)$from['lon'],'notify_when_m'=>200],
                ['step_id'=>'tr'.($i+1).'r','mode'=>'train','text'=>'Riding to '.$to['name'],'lat'=>(float)$to['lat'],'lon'=>(float)$to['lon'],'distance_m'=>(int)($segDist*1000),'duration_s'=>$travelMin*60],
                ['step_id'=>'tr'.($i+1).'a','mode'=>'alight','text'=>'Alight at '.$to['name'],'lat'=>(float)$to['lat'],'lon'=>(float)$to['lon'],'notify_when_m'=>100]
            ]
        ];
        
        $cursor = [REDACTED]($arr, 15); // 15 min layover if intermediate
    }
    
    // Walk from destination station to final destination
    $distFromDestStation = haversine_km((float)$destStation['lat'], (float)$destStation['lon'], $dLat, $dLon);
    $walkMinEnd = max(5, (int)round(($distFromDestStation / 4.5) * 60));
    $legs[] = [
        'mode' => 'walk',
        'operator_name' => 'Walk',
        'from' => $destStation['name'],
        'to' => $destination['name'] ?? 'Destination',
        'from_lat' => (float)$destStation['lat'], 'from_lon' => (float)$destStation['lon'],
        'to_lat' => $dLat, 'to_lon' => $dLon,
        'fare' => 0,
        'duration_s' => $walkMinEnd * 60,
        'departure' => $cursor,
        'arrival' => [REDACTED]($cursor, $walkMinEnd),
        'distance_km' => round($distFromDestStation, 1),
        'instructions' => [
            ['step_id'=>'tw2','mode'=>'walk','text'=>'Walk to destination','lat'=>$dLat,'lon'=>$dLon,'notify_when_m'=>50]
        ]
    ];
    
    $intermediateStops = array_map(function($s){ 
        return ['name'=>$s['name'],'lat'=>$s['lat'],'lon'=>$s['lon'],'type'=>'train']; 
    }, array_slice($trainStops, 1, -1));
    
    return [
        'legs' => $legs,
        'intermediate_stops' => $intermediateStops,
        'origin_station' => $originStation['name'],
        'dest_station' => $destStation['name']
    ];
}

/**
 * Find intermediate train junctions for long-distance routes
 */
function [REDACTED](PDO $pdo, array $origin, array $dest, float $totalDist): array {
    // Major Indian railway junctions by region
    $majorJunctions = [
        ['name'=>'New Delhi Junction','lat'=>28.6414,'lon'=>77.2191,'state'=>'Delhi'],
        ['name'=>'Mumbai Central','lat'=>18.9681,'lon'=>72.8196,'state'=>'Maharashtra'],
        ['name'=>'Chennai Central','lat'=>13.0827,'lon'=>80.2707,'state'=>'Tamil Nadu'],
        ['name'=>'Howrah Junction','lat'=>22.5833,'lon'=>88.3417,'state'=>'West Bengal'],
        ['name'=>'Bengaluru City','lat'=>12.9776,'lon'=>77.5718,'state'=>'Karnataka'],
        ['name'=>'Secunderabad Junction','lat'=>17.4326,'lon'=>78.5013,'state'=>'Telangana'],
        ['name'=>'Pune Junction','lat'=>18.5286,'lon'=>73.8742,'state'=>'Maharashtra'],
        ['name'=>'Ahmedabad Junction','lat'=>23.0258,'lon'=>72.5873,'state'=>'Gujarat'],
        ['name'=>'Jaipur Junction','lat'=>26.9180,'lon'=>75.7870,'state'=>'Rajasthan'],
        ['name'=>'Lucknow Junction','lat'=>26.8467,'lon'=>80.9462,'state'=>'Uttar Pradesh'],
        ['name'=>'Patna Junction','lat'=>25.5941,'lon'=>85.1376,'state'=>'Bihar'],
        ['name'=>'Bhopal Junction','lat'=>23.2599,'lon'=>77.4126,'state'=>'Madhya Pradesh'],
        ['name'=>'Nagpur Junction','lat'=>21.1458,'lon'=>79.0882,'state'=>'Maharashtra'],
        ['name'=>'Vijayawada Junction','lat'=>16.5193,'lon'=>80.6305,'state'=>'Andhra Pradesh'],
        ['name'=>'Ernakulam Junction','lat'=>9.9816,'lon'=>76.2999,'state'=>'Kerala']
    ];
    
    // Calculate bearing from origin to destination
    $targetBearing = bearing_deg((float)$origin['lat'], (float)$origin['lon'], 
                                  (float)$dest['lat'], (float)$dest['lon']);
    
    $candidates = [];
    foreach ($majorJunctions as $junc) {
        // Skip if too close to origin or destination
        $distFromOrigin = haversine_km((float)$origin['lat'], (float)$origin['lon'], $junc['lat'], $junc['lon']);
        $distToDest = haversine_km($junc['lat'], $junc['lon'], (float)$dest['lat'], (float)$dest['lon']);
        
        if ($distFromOrigin < 50 || $distToDest < 50) continue;
        
        // Check if junction is roughly on the path (bearing difference < 30°)
        $bearingToJunc = bearing_deg((float)$origin['lat'], (float)$origin['lon'], $junc['lat'], $junc['lon']);
        $bearingDiff = abs(angular_difference($bearingToJunc, $targetBearing));
        
        if ($bearingDiff < 30) {
            $candidates[] = [
                'junction' => $junc,
                'dist_from_origin' => $distFromOrigin,
                'bearing_diff' => $bearingDiff,
                'score' => $distFromOrigin + ($bearingDiff * 2) // Prefer closer, better-aligned junctions
            ];
        }
    }
    
    // Sort by score and pick best 1-2 junctions
    usort($candidates, function($a, $b){ return $a['score'] <=> $b['score']; });
    
    $selected = [];
    $maxJunctions = ($totalDist > 800) ? 2 : 1;
    foreach (array_slice($candidates, 0, $maxJunctions) as $c) {
        $selected[] = $c['junction'];
    }
    
    return $selected;
}

/**
 * Calculate initial bearing between two points
 */
function bearing_deg(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
    $dlam = deg2rad($lon2 - $lon1);
    $y = sin($dlam) * cos($phi2);
    $x = cos($phi1)*sin($phi2) - sin($phi1)*cos($phi2)*cos($dlam);
    $br = atan2($y,$x);
    $deg = rad2deg($br);
    return fmod(($deg + 360.0), 360.0);
}

/**
 * Calculate smallest angular difference between two bearings
 */
function angular_difference(float $a, float $b): float {
    $d = fmod(($a - $b + 540.0), 360.0) - 180.0;
    return abs($d);
}
