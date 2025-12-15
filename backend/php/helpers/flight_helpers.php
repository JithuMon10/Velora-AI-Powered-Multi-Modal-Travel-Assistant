<?php
/**
 * Flight routing helpers for Velora
 * Implements realistic flight journeys with airport transfers
 */

/**
 * Build flight route with airport transfers
 */
function build_flight_route(PDO $pdo, array $origin, array $destination, string $departHHMM): array {
    $oLat = (float)$origin['lat']; $oLon = (float)$origin['lon'];
    $dLat = (float)$destination['lat']; $dLon = (float)$destination['lon'];
    
    // Find nearest airports
    $originAirport = nearest_station($pdo, $oLat, $oLon, 'airport');
    $destAirport = nearest_station($pdo, $dLat, $dLon, 'airport');
    
    if (!$originAirport || !$destAirport) {
        return ['legs' => [], 'intermediate_stops' => [], 'error' => 'No airports found'];
    }
    
    $legs = [];
    $cursor = $departHHMM;
    
    // Taxi/walk to origin airport
    $distToOriginAirport = haversine_km($oLat, $oLon, (float)$originAirport['lat'], (float)$originAirport['lon']);
    $transferMin = max(20, (int)round(($distToOriginAirport / 30.0) * 60)); // Assume taxi at 30 km/h
    $transferFare = max(30, (int)(30 + $distToOriginAirport * 20)); // ₹30 base + ₹20/km
    
    $legs[] = [
        'mode' => 'taxi',
        'operator_name' => 'Airport Transfer',
        'from' => $origin['name'] ?? 'Origin',
        'to' => $originAirport['name'],
        'from_lat' => $oLat, 'from_lon' => $oLon,
        'to_lat' => (float)$originAirport['lat'], 'to_lon' => (float)$originAirport['lon'],
        'fare' => $transferFare,
        'duration_s' => $transferMin * 60,
        'departure' => $cursor,
        'arrival' => fmt_time_add_minutes($cursor, $transferMin),
        'distance_km' => round($distToOriginAirport, 1),
        'instructions' => [
            ['step_id'=>'tx1','mode'=>'taxi','text'=>'Take taxi to '.$originAirport['name'],'lat'=>$oLat,'lon'=>$oLon,'notify_when_m'=>100]
        ]
    ];
    $cursor = fmt_time_add_minutes($cursor, $transferMin + rand(75, 105)); // 75-105 min check-in/security/boarding
    
    // Flight leg
    $flightDist = haversine_km((float)$originAirport['lat'], (float)$originAirport['lon'], 
                               (float)$destAirport['lat'], (float)$destAirport['lon']);
    
    // Flight speed: ~700 km/h cruise + 45 min overhead (takeoff/landing)
    $flightMin = (int)round(($flightDist / 700.0) * 60) + 45;
    $flightFare = (int)round($flightDist * 4.5); // ₹4.5/km baseline
    
    // Determine airline by region
    $airline = determine_airline($originAirport, $destAirport);
    
    $arr = fmt_time_add_minutes($cursor, $flightMin);
    
    $legs[] = [
        'mode' => 'flight',
        'operator_name' => $airline,
        'from' => $originAirport['name'],
        'to' => $destAirport['name'],
        'from_lat' => (float)$originAirport['lat'], 'from_lon' => (float)$originAirport['lon'],
        'to_lat' => (float)$destAirport['lat'], 'to_lon' => (float)$destAirport['lon'],
        'fare' => $flightFare,
        'duration_s' => $flightMin * 60,
        'departure' => $cursor,
        'arrival' => $arr,
        'distance_km' => round($flightDist, 1),
        'instructions' => [
            ['step_id'=>'fl1b','mode'=>'flight','text'=>'Board '.$airline.' flight to '.$destAirport['name'],'lat'=>(float)$originAirport['lat'],'lon'=>(float)$originAirport['lon'],'notify_when_m'=>500],
            ['step_id'=>'fl1f','mode'=>'flight','text'=>'Flying to '.$destAirport['name'],'lat'=>(float)$destAirport['lat'],'lon'=>(float)$destAirport['lon'],'distance_m'=>(int)($flightDist*1000),'duration_s'=>$flightMin*60],
            ['step_id'=>'fl1l','mode'=>'alight','text'=>'Landing at '.$destAirport['name'],'lat'=>(float)$destAirport['lat'],'lon'=>(float)$destAirport['lon'],'notify_when_m'=>200]
        ]
    ];
    
    $cursor = fmt_time_add_minutes($arr, rand(25, 40)); // 25-40 min baggage/exit/customs
    
    // Taxi from destination airport to final destination
    $distFromDestAirport = haversine_km((float)$destAirport['lat'], (float)$destAirport['lon'], $dLat, $dLon);
    $transferMinEnd = max(20, (int)round(($distFromDestAirport / 30.0) * 60));
    $transferFareEnd = max(30, (int)(30 + $distFromDestAirport * 20)); // ₹30 base + ₹20/km
    
    $legs[] = [
        'mode' => 'taxi',
        'operator_name' => 'Airport Transfer',
        'from' => $destAirport['name'],
        'to' => $destination['name'] ?? 'Destination',
        'from_lat' => (float)$destAirport['lat'], 'from_lon' => (float)$destAirport['lon'],
        'to_lat' => $dLat, 'to_lon' => $dLon,
        'fare' => $transferFareEnd,
        'duration_s' => $transferMinEnd * 60,
        'departure' => $cursor,
        'arrival' => fmt_time_add_minutes($cursor, $transferMinEnd),
        'distance_km' => round($distFromDestAirport, 1),
        'instructions' => [
            ['step_id'=>'tx2','mode'=>'taxi','text'=>'Take taxi to destination','lat'=>$dLat,'lon'=>$dLon,'notify_when_m'=>100]
        ]
    ];
    
    return [
        'legs' => $legs,
        'intermediate_stops' => [],
        'origin_airport' => $originAirport['name'],
        'dest_airport' => $destAirport['name']
    ];
}

/**
 * Determine airline based on route
 */
function determine_airline(array $originAirport, array $destAirport): string {
    $airlines = [
        'IndiGo', 'Air India', 'SpiceJet', 'Vistara', 'AirAsia India', 'GoFirst'
    ];
    
    // Use hash of airport names for consistent selection
    $hash = crc32($originAirport['name'] . $destAirport['name']);
    return $airlines[$hash % count($airlines)];
}
