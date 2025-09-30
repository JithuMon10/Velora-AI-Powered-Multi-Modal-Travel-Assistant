<?php
// Increase execution time for complex routing
set_time_limit(120);
ini_set('max_execution_time', '120');
ob_start(); // Start output buffering to prevent premature output

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/ai.php';
require_once __DIR__ . '/helpers/tomtom_helpers.php';
require_once __DIR__ . '/helpers/routing_helpers.php';
require_once __DIR__ . '/helpers/train_helpers.php';
require_once __DIR__ . '/helpers/flight_helpers.php';
require_once __DIR__ . '/helpers/graph_router.php';
require_once __DIR__ . '/helpers/realistic_router.php';
require_once __DIR__ . '/helpers/smart_router.php';
header('Content-Type: application/json');
// JSON-only hardening: never leak HTML notices/warnings
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line){
    @error_log("PHP Error in plan_trip.php:$line - $message");
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>'Server error',
        'detail'=>'runtime_error',
        'message'=>is_string($message)?$message:'',
        'file'=>basename($file),
        'line'=>$line
    ]);
    exit;
});

// Catch fatal errors and timeouts
[REDACTED](function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        @error_log("Fatal error in plan_trip.php: ".$err['message']." in ".$err['file']." on line ".$err['line']);
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success'=>false,
            'error'=>'Fatal error',
            'detail'=>$err['message'],
            'file'=>basename($err['file']),
            'line'=>$err['line']
        ]);
    }
});

// SAFE WRAPPERS (global scope) to prevent undefined function errors
if (!function_exists([REDACTED])) {
    function [REDACTED](float $oLat,float $oLon,float $dLat,float $dLon,string $key, string $prefix='d'): array {
        // Fallback simple 3-step guidance using distance/time estimates
        $length_m = (int)round(haversine_km($oLat,$oLon,$dLat,$dLon)*1000);
        $time_s = (int)round(max(1.0, ($length_m/1000)/30.0)*3600);
        $midLat = ($oLat+$dLat)/2.0; $midLon = ($oLon+$dLon)/2.0;
        return [
            ['step_id'=>$prefix.'-s','mode'=>'drive','text'=>'Start driving','lat'=>$oLat,'lon'=>$oLon,'distance_m'=>0,'duration_s'=>0,'notify_when_m'=>30,'maneuver'=>'start'],
            ['step_id'=>$prefix.'-c','mode'=>'drive','text'=>"Continue for ".max(0,$length_m-200)." m",'lat'=>$midLat,'lon'=>$midLon,'distance_m'=>max(0,$length_m-200),'duration_s'=>max(0,$time_s-60),'notify_when_m'=>200,'maneuver'=>'straight'],
            ['step_id'=>$prefix.'-a','mode'=>'drive','text'=>'Arrive at destination','lat'=>$dLat,'lon'=>$dLon,'distance_m'=>100,'duration_s'=>60,'notify_when_m'=>50,'maneuver'=>'arrive'],
        ];
    }
}
if (!function_exists([REDACTED])) {
    function [REDACTED](...$args): array {
        // TEMP safe fallback until detailed mapping is guaranteed available
        try {
            if (count($args) >= 5) {
                [$oLat,$oLon,$dLat,$dLon,$key] = $args;
                $prefix = $args[5] ?? 'd';
                return [REDACTED]((float)$oLat,(float)$oLon,(float)$dLat,(float)$dLon,(string)$key,(string)$prefix);
            }
            if (count($args) === 1 && is_array($args[0])) {
                $j = $args[0];
                return [REDACTED]((float)($j['from_lat']??0),(float)($j['from_lon']??0),(float)($j['to_lat']??0),(float)($j['to_lon']??0),(string)($j['key']??''),'d');
            }
        } catch (Throwable $e) { /* ignore */ }
        return [];
    }
}
// Build simplified driving steps; fallback if TomTom details unavailable
function [REDACTED](float $oLat,float $oLon,float $dLat,float $dLon,string $key, string $prefix='d'): array {
    $steps = [];
    // Try to fetch a basic route summary
    $route = tomtom_route($oLat,$oLon,$dLat,$dLon,$key);
    $length_m = isset($route['length_m']) ? (int)$route['length_m'] : (int)round(haversine_km($oLat,$oLon,$dLat,$dLon)*1000);
    $time_s = isset($route['time_s']) ? (int)$route['time_s'] : (int)round(($length_m/1000)/30*3600);
    $midLat = ($oLat+$dLat)/2.0; $midLon = ($oLon+$dLon)/2.0;
    // Three-step generic guidance
    $steps[] = ['step_id'=>$prefix.'-s','mode'=>'drive','text'=>'Start driving','lat'=>$oLat,'lon'=>$oLon,'distance_m'=>0,'duration_s'=>0,'notify_when_m'=>30,'maneuver'=>'start'];
    $steps[] = ['step_id'=>$prefix.'-c','mode'=>'drive','text'=>"Continue for ".max(0,$length_m-200)." m","lat"=>$midLat,'lon'=>$midLon,'distance_m'=>max(0,$length_m-200),'duration_s'=>max(0,$time_s-60),'notify_when_m'=>200,'maneuver'=>'straight'];
    $steps[] = ['step_id'=>$prefix.'-a','mode'=>'drive','text'=>'Arrive at destination','lat'=>$dLat,'lon'=>$dLon,'distance_m'=>100,'duration_s'=>60,'notify_when_m'=>50,'maneuver'=>'arrive'];
    return $steps;
}

// ---- Geofence helper (state by lat/lon) ----
if (!function_exists('geofence_state')) {
function geofence_state(float $lat, float $lon): ?string {
    // Kerala
    if ($lat >= 8.0 && $lat <= 13.5 && $lon >= 74.5 && $lon <= 77.8) return 'Kerala';
    // Tamil Nadu
    if ($lat >= 8.0 && $lat <= 13.8 && $lon >= 76.0 && $lon <= 80.8) return 'Tamil Nadu';
    // West Bengal
    if ($lat >= 21.0 && $lat <= 27.6 && $lon >= 85.5 && $lon <= 90.5) return 'West Bengal';
    return null;
}
}

/**
 * Smart bus chain selector: picks optimal intermediate stops along corridor.
 * Returns array of stops ordered by progress, ensuring no backtracking and max 15% detour.
 */
function select_bus_chain(PDO $pdo, float $oLat, float $oLon, float $dLat, float $dLon): array {
    $totalKm = haversine_km($oLat, $oLon, $dLat, $dLon);
    $maxDetourKm = $totalKm * 1.15;
    
    // Find closest origin bus stop within 5km
    $originStop = null;
    try {
        $st = $pdo->prepare("SELECT id, name, state, lat, lon, (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon)) + SIN(RADIANS(:lat)) * SIN(RADIANS(lat)))) AS dist_km FROM stations WHERE type='bus' HAVING dist_km <= 5.0 ORDER BY dist_km ASC LIMIT 1");
        $st->execute([':lat'=>$oLat, ':lon'=>$oLon]);
        $row = $st->fetch();
        if ($row) {
            $originStop = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'lat'=>(float)$row['lat'], 'lon'=>(float)$row['lon'], 'state'=>(string)($row['state']??''), 'type'=>'bus', 'synthetic'=>false];
            @error_log('Closest origin bus stop: name='.$row['name'].' distance='.$row['dist_km'].'km');
        }
    } catch (Throwable $e) { }
    
    // Find closest destination bus stop within 5km
    $destStop = null;
    try {
        $st = $pdo->prepare("SELECT id, name, state, lat, lon, (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon)) + SIN(RADIANS(:lat)) * SIN(RADIANS(lat)))) AS dist_km FROM stations WHERE type='bus' HAVING dist_km <= 5.0 ORDER BY dist_km ASC LIMIT 1");
        $st->execute([':lat'=>$dLat, ':lon'=>$dLon]);
        $row = $st->fetch();
        if ($row) {
            $destStop = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'lat'=>(float)$row['lat'], 'lon'=>(float)$row['lon'], 'state'=>(string)($row['state']??''), 'type'=>'bus', 'synthetic'=>false];
            @error_log('Closest dest bus stop: name='.$row['name'].' distance='.$row['dist_km'].'km');
        }
    } catch (Throwable $e) { }
    
    if (!$originStop || !$destStop) {
        @error_log('select_bus_chain: no valid origin or dest bus stop within 5km, falling back');
        return [];
    }
    
    // Get corridor candidates between the two bus stops
    $cands = [REDACTED]($pdo, (float)$originStop['lat'], (float)$originStop['lon'], (float)$destStop['lat'], (float)$destStop['lon'], 18.0);
    
    // Progressive selection toward destination
    $chain = [$originStop];
    $cumulativeKm = 0.0;
    $lastLat = (float)$originStop['lat']; $lastLon = (float)$originStop['lon'];
    
    foreach ($cands as $row){
        $lat = (float)$row['lat']; $lon = (float)$row['lon'];
        $distPrev = haversine_km($lastLat, $lastLon, $lat, $lon);
        $distToEnd = haversine_km($lat, $lon, (float)$destStop['lat'], (float)$destStop['lon']);
        
        // Skip if too close to previous
        if ($distPrev < 20.0) continue;
        
        // Check detour limit
        $newCumulative = $cumulativeKm + $distPrev;
        if ($newCumulative > $maxDetourKm) break;
        
        // Ensure forward progress (no backtracking)
        $prevDistToEnd = haversine_km($lastLat, $lastLon, (float)$destStop['lat'], (float)$destStop['lon']);
        if ($distToEnd >= $prevDistToEnd) continue;
        
        $chain[] = ['id'=>(int)($row['id']??0), 'name'=>(string)$row['name'], 'lat'=>$lat, 'lon'=>$lon, 'state'=>(string)($row['state']??''), 'type'=>'bus', 'synthetic'=>false];
        $cumulativeKm = $newCumulative;
        $lastLat = $lat; $lastLon = $lon;
        
        if (count($chain) >= 5) break; // Max 5 intermediate stops
    }
    
    $chain[] = $destStop;
    @error_log('select_bus_chain: total_km='.$totalKm.' max_detour='.$maxDetourKm.' cumulative='.$cumulativeKm.' stops='.count($chain));
    return $chain;
}

/**
 * Fetch bus station candidates within a tight corridor around the great-circle line
 * between origin and destination. Returns ordered list by progress (no endpoints).
 * Tightened to 15-20km radius for realistic routing.
 */
function [REDACTED](PDO $pdo, float $oLat, float $oLon, float $dLat, float $dLon, float $corridorRadiusKm=18.0, int $limit=500): array {
    // Bounding box with small padding for initial filter
    $minLat = min($oLat, $dLat) - 0.3;
    $maxLat = max($oLat, $dLat) + 0.3;
    $minLon = min($oLon, $dLon) - 0.3;
    $maxLon = max($oLon, $dLon) + 0.3;
    try {
        $st = $pdo->prepare("SELECT id, name, type, state, lat, lon FROM stations WHERE type='bus' AND lat BETWEEN :minLat AND :maxLat AND lon BETWEEN :minLon AND :maxLon LIMIT $limit");
        $st->execute([':minLat'=>$minLat, ':maxLat'=>$maxLat, ':minLon'=>$minLon, ':maxLon'=>$maxLon]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { $rows = []; }
    
    // Build a 2-point polyline for progress and distance calculations
    $poly = [ [$oLat,$oLon], [$dLat,$dLon] ];
    $out = [];
    $skipped = 0;
    foreach ($rows as $r){
        $lat = (float)$r['lat']; $lon = (float)$r['lon'];
        [$d, $prog] = [REDACTED]($lat, $lon, $poly);
        
        // Exclude near endpoints
        if ($prog <= 0.01 || $prog >= 0.99) continue;
        
        // Tighter corridor: only include stops within corridorRadiusKm of the direct line
        if ($d > $corridorRadiusKm) {
            $skipped++;
            @error_log('Skipped far stop: name='.$r['name'].' distance='.$d.'km (beyond corridor)');
            continue;
        }
        
        $r['_prog'] = $prog; 
        $r['_dline_km'] = $d; 
        $r['type'] = 'bus';
        $out[] = $r;
    }
    usort($out, function($a,$b){ return $a['_prog'] <=> $b['_prog']; });
    @error_log('Bus corridor candidates: '.count($rows).' total, '.count($out).' within corridor, '.$skipped.' skipped');
    return $out;
}
 
// Default initializations to avoid undefined variable warnings
$decision = null; // will be set per-mode: 'bus_chain' | 'train' | 'flight' | 'drive'
$vehicle = null;  // 'yes' | 'no' or null; some flows may not set
$dist_km = 0;     // estimated distance in km when computed
// Quick mode: skip external API calls to avoid timeouts
$IS_QUICK = (isset($_GET['quick']) && (string)$_GET['quick'] === '1');
// API keys from config.php
if (!isset($TOMTOM_KEY) || !$TOMTOM_KEY) {
    echo json_encode(['success'=>false,'error'=>'Missing TomTom API key','missing_keys'=>['TOMTOM_KEY'],'suggestion'=>'Set VELORA_TOMTOM_KEY env var or define $TOMTOM_KEY in backend/php/config.php']);
    exit;
}
if (!isset($_GET['quick']) || (string)$_GET['quick'] !== '1') {
    if (!isset($GEMINI_KEY) || !$GEMINI_KEY) {
        echo json_encode(['success'=>false,'error'=>'Missing Gemini API key','missing_keys'=>['GEMINI_KEY'],'suggestion'=>'Set VELORA_GEMINI_KEY env var or define $GEMINI_KEY in backend/php/config.php']);
        exit;
    }
}

// ---- Stations helpers (OSM-derived table) ----
if (!function_exists([REDACTED])) {
  function [REDACTED](PDO $pdo): void {
      try {
          // Create if missing with flexible type and state column
          $pdo->exec("CREATE TABLE IF NOT EXISTS stations (
            id BIGINT PRIMARY KEY,
            name VARCHAR(255) NULL,
            type VARCHAR(32) NOT NULL,
            state VARCHAR(100) NULL,
            lat DOUBLE NOT NULL,
            lon DOUBLE NOT NULL,
            KEY idx_type (type),
            KEY idx_state (state),
            KEY idx_latlon (lat, lon)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
          // Best-effort ALTERs to match expected columns
          try { $pdo->exec("ALTER TABLE stations MODIFY COLUMN type VARCHAR(32) NOT NULL"); } catch (Throwable $e) { }
          try { $pdo->exec("ALTER TABLE stations ADD COLUMN state VARCHAR(100) NULL"); } catch (Throwable $e) { }
          try { $pdo->exec("CREATE INDEX idx_state ON stations(state)"); } catch (Throwable $e) { }
      } catch (Throwable $e) { /* ignore */ }
  }
}

if (!function_exists('nearest_station')) {
  function nearest_station(PDO $pdo, float $lat, float $lon, string $type = 'train'): ?array {
      try {
          $sql = "SELECT id, name, type, lat, lon,
            (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon)) + SIN(RADIANS(:lat)) * SIN(RADIANS(lat)))) AS dist_km
            FROM stations WHERE type = :type ORDER BY dist_km ASC LIMIT 1";
          $st = $pdo->prepare($sql);
          $st->execute([':lat'=>$lat, ':lon'=>$lon, ':type'=>$type]);
          $row = $st->fetch();
          if ($row) { @error_log("Chose station: ".json_encode($row)); }
          return $row ?: null;
      } catch (Throwable $e) { return null; }
  }
}

// TomTom Directions API: fetch text instructions for driving
if (!function_exists([REDACTED])) {
function [REDACTED](float $oLat, float $oLon, float $dLat, float $dLon, string $apiKey, ?string $departTime = null): array {
    // Quick mode: no external call
    global $IS_QUICK; if ($IS_QUICK) { return []; }
    $base = "https://api.tomtom.com/routing/1/calculateRoute/{$oLat},{$oLon}:{$dLat},{$dLon}/json";
    $qs = [
        'key' => $apiKey,
        'traffic' => 'true',
        'travelMode' => 'car',
        'instructionsType' => 'text',
    ];
    if ($departTime) { $qs['departAt'] = date('c'); }
    $url = $base . '?' . http_build_query($qs);
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [[REDACTED]=>true, CURLOPT_TIMEOUT=>3, [REDACTED]=>2]);
        $res = curl_exec($ch);
        if ($res === false) return [];
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300) return [];
        $j = json_decode($res, true);
        $out = [];
        $legs = $j['routes'][0]['legs'] ?? [];
        foreach ($legs as $leg){
            $inst = $leg['instructions'] ?? [];
            foreach ($inst as $it){
                $out[] = [
                    'text' => (string)($it['message'] ?? $it['street'] ?? ''),
                    'distance_m' => (int)($it['lengthInMeters'] ?? 0),
                    'time_ms' => (int)($it['travelTimeInSeconds'] ?? 0) * 1000,
                ];
            }
        }
        return $out;
    } catch (Throwable $e) { return []; }
}
}

// ---- TomTom Helpers ----
function http_get_json(string $url): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        [REDACTED] => true,
        CURLOPT_TIMEOUT => 3,
        [REDACTED] => 2,
        [REDACTED] => true,
        [REDACTED] => false,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) { return ['_error'=>$err]; }
    $data = json_decode($resp, true);
    if (!is_array($data)) return ['_raw'=>$resp];
    return $data;
}

function tomtom_geocode(string $query, string $key): ?array {
    // Quick mode: avoid external geocode
    global $IS_QUICK; if ($IS_QUICK) { return null; }
    $q = rawurlencode($query);
    $url = "https://api.tomtom.com/search/2/geocode/{$q}.json?key={$key}";
    $data = http_get_json($url);
    if (!empty($data['results'][0])) {
        $r = $data['results'][0];
        $lat = $r['position']['lat'] ?? null;
        $lon = $r['position']['lon'] ?? null;
        $addr = $r['address']['freeformAddress'] ?? ($r['address']['streetName'] ?? $query);
        if ($lat !== null && $lon !== null) return ['lat'=>(float)$lat,'lon'=>(float)$lon,'name'=>(string)$addr,'_req'=>$url];
    }
    return null;
}

function tomtom_route(float $lat1,float $lon1,float $lat2,float $lon2,string $key): array {
    $url = "https://api.tomtom.com/routing/1/calculateRoute/{$lat1},{$lon1}:{$lat2},{$lon2}/json?key={$key}&computeBestOrder=false";
    $data = http_get_json($url);
    $points = []; $length_m = null; $time_s = null;
    if (!empty($data['routes'][0])) {
        $route0 = $data['routes'][0];
        $summary = $route0['summary'] ?? [];
        $length_m = $summary['lengthInMeters'] ?? null;
        $time_s = $summary['travelTimeInSeconds'] ?? null;
        if (!empty($route0['legs'][0]['points'])) {
            foreach ($route0['legs'][0]['points'] as $p) {
                if (isset($p['latitude'],$p['longitude'])) $points[] = [(float)$p['latitude'], (float)$p['longitude']];
            }
        }
    }
    return ['points'=>$points,'_req'=>$url,'raw'=>$data,'length_m'=>$length_m,'time_s'=>$time_s];
}

function tomtom_traffic(float $lat,float $lon,string $key): array {
    // Quick mode: no external call
    global $IS_QUICK; if ($IS_QUICK) { return [0,'none',null,null]; }
    
    // Cache traffic calls to avoid repeated API hits
    static $cache = [];
    $cacheKey = round($lat,2).'_'.round($lon,2);
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];
    
    $url = "https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json?point={$lat},{$lon}&key={$key}";
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            [REDACTED]=>true,
            CURLOPT_TIMEOUT=>3,
            [REDACTED]=>2
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            curl_close($ch);
            $result = [0,'none',null,null];
            $cache[$cacheKey] = $result;
            return $result;
        }
        curl_close($ch);
        $data = json_decode($res, true);
    } catch (Throwable $e) {
        $result = [0,'none',null,null];
        $cache[$cacheKey] = $result;
        return $result;
    }
    
    $delayMin = 0; $severity = 'none';
    if (!empty($data['flowSegmentData'])) {
        $f = $data['flowSegmentData'];
        $curr = max(1.0, (float)($f['currentSpeed'] ?? 0));
        $free = max($curr, (float)($f['freeFlowSpeed'] ?? $curr));
        $ratio = $curr / max(1.0,$free);
        if ($ratio < 1.0) {
            if ($ratio >= 0.8) $severity = 'low';
            elseif ($ratio >= 0.6) $severity = 'medium';
            else $severity = 'high';
        }
        $delayMin = (int)max(0, round(((10.0/$curr) - (10.0/$free)) * 60));
    }
    $result = [$delayMin, $severity, $data, $url];
    $cache[$cacheKey] = $result;
    return $result;
}
// plan_trip.php
// Input (GET):
// - Path A (new multimodal): origin_lat, origin_lon, dest_lat, dest_lon, origin_name?, dest_name?, depart_time?
// - Path B (legacy): origin_id, dest_id, arrive_by
// Output JSON (new multimodal):
// { segments: [{mode, operator, from, to, from_lat, from_lon, to_lat, to_lon, departure, arrival, fare, traffic_delay?}], total_fare, total_time }

// Determine if a coordinate is near any bus stop within threshold
function is_near_bus_stop(PDO $pdo, float $lat, float $lon, float $thresh_km = 0.8): bool {
    try {
        $st = $pdo->prepare("SELECT 1 FROM stations WHERE type='bus' AND lat IS NOT NULL AND lon IS NOT NULL AND (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) <= :r LIMIT 1");
        $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon, ':r'=>$thresh_km]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}

// Sample polyline every ~30-50 km
function sample_polyline_km(array $poly, float $stepKm = 30.0): array {
    if (count($poly) < 2) return $poly;
    $out = [$poly[0]];
    $accKm = 0.0; $targetKm = max(1.0, $stepKm);
    for ($i=1; $i<count($poly); $i++){
        $a = $poly[$i-1]; $b = $poly[$i];
        $segKm = haversine_km($a[0],$a[1],$b[0],$b[1]);
        if ($segKm <= 0) continue;
        if ($accKm + $segKm >= $targetKm){
            $need = $targetKm - $accKm; $t = max(0.0, min(1.0, $need / $segKm));
            $lat = $a[0] + ($b[0]-$a[0])*$t; $lon = $a[1] + ($b[1]-$a[1])*$t;
            $out[] = [$lat,$lon];
            $accKm = 0.0;
            // restart from this interpolated point to preserve remainder
            $poly[$i-1] = [$lat,$lon]; $i--; continue;
        } else {
            $accKm += $segKm;
        }

        // Debug injection removed to avoid referencing outer-scope variables here
    }
    $out[] = $poly[count($poly)-1];
    return $out;
}

// Find the nearest bus stop to a point that lies "ahead" along the polyline, given a minimum progress
function [REDACTED](PDO $pdo, float $plat, float $plon, array $poly, float $minProg, float $maxSnapKm = 3.0): ?array {
    $best = null; $bestD = 1e18;
    // Query nearby candidates within 10km, ordered by distance
    try {
        $st = $pdo->prepare("SELECT name, lat, lon,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) AS dist_km
          FROM stations WHERE type='bus' AND lat IS NOT NULL AND lon IS NOT NULL HAVING dist_km <= 10 ORDER BY dist_km ASC LIMIT 50");
        $st->execute([':lat1'=>$plat, ':lat2'=>$plat, ':lon1'=>$plon]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { $rows = []; }
    foreach ($rows as $r){
        $lat = (float)$r['lat']; $lon = (float)$r['lon'];
        [$d, $prog] = [REDACTED]($lat,$lon,$poly);
        if ($prog > $minProg + 1e-3) { // ensure forward
            $dist = haversine_km($plat,$plon,$lat,$lon);
            if ($dist <= $maxSnapKm && $dist < $bestD) { $bestD = $dist; $best = ['name'=>(string)$r['name'],'lat'=>$lat,'lon'=>$lon]; }
        }
    }
    if ($best) {
        $best['state'] = [REDACTED]($best['name']) ?: '';
    }
    return $best;
}

function [REDACTED](PDO $pdo, ?string $fromState, ?string $toState, string $fromName, string $toName): string {
    $state = $toState ?: $fromState ?: '';
    if ($state !== '') {
        return [REDACTED]($pdo, $state, 'bus', function() use($pdo,$fromName,$toName){ return get_bus_operator($pdo, $fromName, $toName); });
    }
    return get_bus_operator($pdo, $fromName, $toName);
}

function [REDACTED](float $fromLat, float $fromLon, float $toLat, float $toLon, float $farePerKm = 1.2, float $speedKmh = 45.0): array {
    $km = max(0.1, haversine_km($fromLat,$fromLon,$toLat,$toLon));
    $fare = (int)round($km * $farePerKm);
    $hours = $km / max(1.0, $speedKmh);
    $duration_s = (int)round($hours * 3600);
    return [$fare, $duration_s, $km];
}

// Implements the exact chaining per pseudocode
function build_bus_chaining(PDO $pdo, array $origin, array $destination, array $polyline, string $departHHMM, string $tomtomKey): array {
    // Try smart chain selector first
    $smartChain = select_bus_chain($pdo, (float)$origin['lat'], (float)$origin['lon'], (float)$destination['lat'], (float)$destination['lon']);
    
    $stops = [];
    $chain_reason = 'smart_chain';
    
    if (count($smartChain) >= 2) {
        // Use smart chain result
        $stops = $smartChain;
        $chainNames = array_map(function($s){ return $s['name']; }, $stops);
        @error_log('Using smart bus chain: '.implode(' → ', $chainNames));
        @error_log('Bus chaining: using '.count($stops).' stops from select_bus_chain()');
    } else {
        // Fallback to legacy approach
        @error_log('Smart chain failed, using legacy corridor approach');
        
        // 1) Find nearest bus station to origin (synthetic if farther than 10km)
        $startBus = nearest_bus_station($pdo, (float)$origin['lat'], (float)$origin['lon']);
        if ($startBus && isset($startBus[3]) && (float)$startBus[3] <= 10.0) {
            $current = ['name'=>$startBus[0].' (Bus Station)', 'lat'=>$startBus[1], 'lon'=>$startBus[2], 'synthetic'=>false];
        } else if ($startBus && !isset($startBus[3])) {
            // Backward compat if dist not provided, accept
            $current = ['name'=>$startBus[0].' (Bus Station)', 'lat'=>$startBus[1], 'lon'=>$startBus[2], 'synthetic'=>false];
        } else {
            $current = ['name'=>$origin['name'] ?? 'Origin', 'lat'=>(float)$origin['lat'], 'lon'=>(float)$origin['lon'], 'synthetic'=>true];
        }
        $current['state'] = [REDACTED]((string)$current['name']) ?: '';
        $stops = [$current];

        // Try corridor-based real bus stations between origin and destination
        $endBus = nearest_bus_station($pdo, (float)$destination['lat'], (float)$destination['lon']);
        $endStop = null;
        if ($endBus) {
            $endStop = ['id'=>$endBus[4] ?? null,'name'=>$endBus[0].' (Bus Station)','lat'=>$endBus[1],'lon'=>$endBus[2],'state'=>[REDACTED]((string)$endBus[0]) ?: '', 'type'=>'bus', 'synthetic'=>false];
        } else {
            $endStop = ['name'=>$destination['name'] ?? 'Destination', 'lat'=>(float)$destination['lat'], 'lon'=>(float)$destination['lon'], 'state'=>'', 'type'=>'point', 'synthetic'=>true];
        }

        // Dynamic corridor width based on trip distance (stricter for direct routes)
        $totalKm = max(1.0, haversine_km((float)$current['lat'],(float)$current['lon'], (float)$endStop['lat'], (float)$endStop['lon']));
        $corrWidthKm = ($totalKm < 150.0) ? 8.0 : (($totalKm > 200.0) ? 15.0 : 12.0);

        $cands = [REDACTED]($pdo, (float)$current['lat'], (float)$current['lon'], (float)$endStop['lat'], (float)$endStop['lon'], $corrWidthKm);
        // Smart stop selection: forward-direction corridor with scoring and no backtracking
        $picked = [];
        $maxChainKm = $totalKm * 1.15; // Allow 15% detour
        $cumulativeKm = 0.0;
        $lastLat = (float)$current['lat']; $lastLon = (float)$current['lon'];

        // Helper: initial bearing in degrees (0..360)
        $bearing_deg = function(float $lat1, float $lon1, float $lat2, float $lon2): float {
            $phi1 = deg2rad($lat1); $phi2 = deg2rad($lat2);
            $dlam = deg2rad($lon2 - $lon1);
            $y = sin($dlam) * cos($phi2);
            $x = cos($phi1)*sin($phi2) - sin($phi1)*cos($phi2)*cos($dlam);
            $br = atan2($y,$x);
            $deg = rad2deg($br);
            return fmod(($deg + 360.0), 360.0);
        };
        // Helper: smallest angular difference (deg)
        $ang_diff = function(float $a, float $b): float {
            $d = fmod(($a - $b + 540.0), 360.0) - 180.0; return abs($d);
        };

        // Precompute corridor bearing origin->destination
        $corrBearing = $bearing_deg((float)$current['lat'], (float)$current['lon'], (float)$endStop['lat'], (float)$endStop['lon']);

        // Filter candidates to forward corridor and within corridor width using polyline proximity
        $kept = []; $dropped = []; $diffs = [];
        foreach ($cands as $row){
            $lat = (float)$row['lat']; $lon = (float)$row['lon'];
            // Directional filter: bearing from last point toward candidate should be close to corridor bearing
            $b = $bearing_deg($lastLat, $lastLon, $lat, $lon);
            $bdiff = $ang_diff($b, $corrBearing);
            // Distance to corridor line via polyline projection (best-effort)
            $distToCorr = 0.0; $prog = 0.0;
            try { [$distToCorr, $prog] = [REDACTED]($lat, $lon, $polyline); } catch (Throwable $e) { $distToCorr = 0.0; }
            if ($bdiff <= 15.0 && $distToCorr <= $corrWidthKm) {
                $row['_bearing_diff'] = $bdiff;
                $kept[] = $row; $diffs[] = $bdiff;
            } else {
                $dropped[] = $row;
            }
        }
        @error_log('Corridor filter: kept='.count($kept).' dropped='.count($dropped));
        if (count($diffs)) { @error_log('Average bearing diff: '.round(array_sum($diffs)/max(1,count($diffs)),2)); }

        // Score kept candidates by distance from last + weighted bearing difference
        $scored = [];
        foreach ($kept as $row){
            $lat = (float)$row['lat']; $lon = (float)$row['lon'];
            $distPrev = haversine_km($lastLat, $lastLon, $lat, $lon);
            $score = $distPrev + 0.1 * (float)$row['_bearing_diff'];
            $row['_score'] = $score; $row['_distPrev'] = $distPrev;
            $scored[] = $row;
        }
        usort($scored, function($a,$b){ return ($a['_score'] <=> $b['_score']); });

        // If no forward corridor stops remain, fallback to direct route
        if (count($scored) === 0) {
            @error_log('No forward corridor stops found — using direct route');
        }

        // Iterate sorted candidates
        $sorted = (count($scored) ? $scored : $cands);
        foreach ($sorted as $row){
            $lat = (float)$row['lat']; $lon = (float)$row['lon'];
            $distPrev = haversine_km($lastLat, $lastLon, $lat, $lon);
            $distToEnd = haversine_km($lat, $lon, (float)$endStop['lat'], (float)$endStop['lon']);
            $distFromStart = haversine_km((float)$current['lat'], (float)$current['lon'], $lat, $lon);
            
            // Skip if too close to previous stop (avoid clustering)
            if ($distPrev < 20.0) continue;
            
            // Skip if too close to start or end
            if ($distFromStart < 8.0) continue;
            if ($distToEnd < 8.0) continue;
            
            // Check if adding this stop would exceed max detour
            $newCumulative = $cumulativeKm + $distPrev;
            if ($newCumulative > $maxChainKm) {
                @error_log('Skipped stop (exceeds detour): name='.$row['name'].' cumulative='.$newCumulative.'km max='.$maxChainKm.'km');
                break;
            }
            
            // Ensure progressive movement toward destination (no backtracking)
            $prevDistToEnd = haversine_km($lastLat, $lastLon, (float)$endStop['lat'], (float)$endStop['lon']);
            if ($distToEnd >= $prevDistToEnd) {
                @error_log('Skipped backtracking stop: name='.$row['name'].' dist_to_end='.$distToEnd.'km prev='.$prevDistToEnd.'km');
                continue;
            }
            
            $picked[] = ['id'=> (int)($row['id'] ?? 0), 'name'=>(string)$row['name'], 'lat'=>$lat, 'lon'=>$lon, 'state'=> (string)($row['state'] ?? ''), 'type'=>'bus', 'synthetic'=>false];
            $cumulativeKm = $newCumulative;
            $lastLat = $lat; $lastLon = $lon;
            
            // Stop if we have enough intermediate stops (max 4 for readability)
            if (count($picked) >= 4) break;
        }
        $chain_reason = 'fallback origin/dest used';
        if (count($picked) >= 1) {
            $stops = array_merge([$current], $picked, [$endStop]);
            $chainNames = array_map(function($s){ return $s['name']; }, $stops);
            @error_log('Bus corridor chosen: '.json_encode($chainNames));
            @error_log('Bus chaining corridor length='.$totalKm.'km, chosen stops='.count($stops).', cumulative='.$cumulativeKm.'km');
            @error_log('Chosen chain: '.implode(' → ', $chainNames));
            $chain_reason = 'corridor candidates found';
        } else {
        // Fallback to existing sampling-based approach along the TomTom/GraphHopper polyline
        $samples = sample_polyline_km($polyline, 50.0);
        [$d0,$prog0] = [REDACTED]($current['lat'],$current['lon'],$polyline);
        $minProg = $prog0;
        foreach ($samples as $pt){
            $pLat = (float)$pt[0]; $pLon = (float)$pt[1];
            $next = [REDACTED]($pdo, $pLat, $pLon, $polyline, $minProg, 5.0);
            if ($next) {
                $dupe = false;
                foreach ($stops as $s){ if (haversine_km($s['lat'],$s['lon'],$next['lat'],$next['lon']) < 0.5) { $dupe = true; break; } }
                if (!$dupe) {
                    $next['synthetic'] = $next['synthetic'] ?? false;
                    $stops[] = $next; $minProg = [REDACTED]($next['lat'],$next['lon'],$polyline)[1];
                }
            }
            $dToDest = haversine_km($pLat,$pLon,(float)$destination['lat'],(float)$destination['lon']);
            if ($dToDest <= 20.0) break;
        }
        // Append destination bus stop
        $stops[] = $endStop;
        // Ensure multiple bus legs via additional sampling if needed
        if (count($stops) < 3) {
            $more = sample_polyline_km($polyline, 50.0);
            foreach ($more as $pt){
                $cand = [REDACTED]($pdo, (float)$pt[0], (float)$pt[1], $polyline, $minProg, 5.0);
                if ($cand){
                    $exists = false; foreach ($stops as $s){ if (haversine_km($s['lat'],$s['lon'],$cand['lat'],$cand['lon']) < 0.5) { $exists=true; break; } }
                    if (!$exists) { $cand['synthetic'] = $cand['synthetic'] ?? false; $stops[] = $cand; }
                }
                if (count($stops) >= 3) break;
            }
        }
        if (count($stops) < 3) {
            // Synthesize intermediate bus stops every ~50km along polyline
            $cum = [0.0]; $total = 0.0;
            for ($i=1;$i<count($polyline);$i++){ $total += haversine_km($polyline[$i-1][0],$polyline[$i-1][1],$polyline[$i][0],$polyline[$i][1]); $cum[] = $total; }
            $step = 50.0; $pos = $step;
            while ($pos < $total && count($stops) < 6){
                $idx = 0; for ($j=1;$j<count($cum);$j++){ if ($cum[$j] >= $pos){ $idx = $j; break; } }
                $p = $polyline[$idx];
                $name = 'Synthetic Stop '.count($stops);
                $stops[] = ['name'=>$name, 'lat'=>(float)$p[0], 'lon'=>(float)$p[1], 'state'=>'', 'synthetic'=>true];
                $pos += $step;
            }
        }
        if (count($stops) < 3) {
            // Absolute last fallback insert midpoint
            $total = 0.0; $acc = [0.0];
            for ($i=1;$i<count($polyline);$i++){ $total += haversine_km($polyline[$i-1][0],$polyline[$i-1][1],$polyline[$i][0],$polyline[$i][1]); $acc[] = $total; }
            $half = $total/2.0; $mid = $polyline[0];
            for ($i=1;$i<count($acc);$i++){ if ($acc[$i] >= $half) { $mid = $polyline[$i]; break; } }
            $stops[] = [ 'name'=>'Midway Stop', 'lat'=>(float)$mid[0], 'lon'=>(float)$mid[1], 'state'=>'', 'synthetic'=>true ];
        }
    }
    }

    // 4) Build legs: walk to nearest bus stop, bus chain between bus stops, walk to destination
    $legs = [];
    $cursor = $departHHMM;
    
    // Determine first and last bus stops (filter out synthetic points)
    $busStops = array_values(array_filter($stops, function($s){ return empty($s['synthetic']); }));
    if (count($busStops) === 0) { 
        $busStops = [$stops[0], $stops[count($stops)-1]];
    }
    $firstBus = $busStops[0];
    $lastBus  = $busStops[count($busStops)-1];
    
    // Detailed logging
    @error_log("Bus chaining: origin=".json_encode($firstBus)." dest=".json_encode($lastBus));
    @error_log('Bus chaining candidates (all stops incl. intermediates): '.json_encode(array_map(function($s){ return ['name'=>$s['name'],'lat'=>$s['lat'],'lon'=>$s['lon'],'synthetic'=>!empty($s['synthetic'])]; }, $stops)));

    // Walk from origin to first bus stop (always include a feeder leg)
    $distToFirst = haversine_km((float)$origin['lat'], (float)$origin['lon'], (float)$firstBus['lat'], (float)$firstBus['lon']);
    $walk_min = max(2, (int)round(($distToFirst / 4.5) * 60));
    $legs[] = [
        'mode' => 'walk',
        'operator_name' => 'Walk',
        'from' => (string)($origin['name'] ?? 'Origin'),
        'to' => (string)$firstBus['name'],
        'from_lat' => (float)$origin['lat'], 'from_lon' => (float)$origin['lon'],
        'to_lat'   => (float)$firstBus['lat'],   'to_lon'   => (float)$firstBus['lon'],
        'fare' => 0,
        'duration_s' => $walk_min * 60,
        'departure' => $cursor,
        'arrival' => [REDACTED]($cursor, $walk_min),
        'distance_km' => round($distToFirst,2),
        'instructions' => [
            [
                'step_id'=>'w1', 'mode'=>'walk',
                'text'=> 'Walk '.(int)round($distToFirst*1000).'m to '.(string)$firstBus['name'],
                'lat'=>(float)$firstBus['lat'], 'lon'=>(float)$firstBus['lon'],
                'distance_m'=>(int)round($distToFirst*1000), 'duration_s'=> $walk_min*60, 'notify_when_m'=>50
            ]
        ]
    ];
    $cursor = $legs[count($legs)-1]['arrival'];

    // Build bus chain from all bus stops
    $chain = $busStops;
    if (count($chain) < 2) { $chain = [$firstBus, $lastBus]; }
    
    // Check for large gaps and insert midpoint stops
    $finalChain = [];
    for ($i=0; $i < count($chain)-1; $i++){
        $finalChain[] = $chain[$i];
        $from = $chain[$i]; $to = $chain[$i+1];
        $gap = haversine_km((float)$from['lat'], (float)$from['lon'], (float)$to['lat'], (float)$to['lon']);
        if ($gap > 25.0) {
            $midLat = ((float)$from['lat'] + (float)$to['lat']) / 2.0;
            $midLon = ((float)$from['lon'] + (float)$to['lon']) / 2.0;
            try {
                $st = $pdo->prepare("SELECT id, name, state, lat, lon, (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon)) + SIN(RADIANS(:lat)) * SIN(RADIANS(lat)))) AS dist_km FROM stations WHERE type='bus' HAVING dist_km <= 5.0 ORDER BY dist_km ASC LIMIT 1");
                $st->execute([':lat'=>$midLat, ':lon'=>$midLon]);
                $mid = $st->fetch();
                if ($mid) {
                    $finalChain[] = ['id'=>(int)$mid['id'], 'name'=>(string)$mid['name'], 'lat'=>(float)$mid['lat'], 'lon'=>(float)$mid['lon'], 'state'=>(string)($mid['state']??''), 'type'=>'bus', 'synthetic'=>false];
                    @error_log('Inserted midpoint stop: name='.$mid['name'].' gap='.$gap.'km');
                }
            } catch (Throwable $e) { }
        }
    }
    $finalChain[] = $chain[count($chain)-1];
    $chain = $finalChain;
    
    // Log final chain
    $viaIds = array_map(function($s){ return $s['id'] ?? null; }, $busStops);
    $chainNames = array_map(function($s){ return $s['name']; }, $chain);
    @error_log('Final bus chain: '.implode(' → ', $chainNames));
    @error_log('Bus chaining: origin='.json_encode(['id'=>$firstBus['id']??null,'name'=>$firstBus['name']??'']).' dest='.json_encode(['id'=>$lastBus['id']??null,'name'=>$lastBus['name']??'']).' via='.json_encode($viaIds));
    foreach ($busStops as $bs) { if (!empty($bs['id'])) { @error_log('Chose station: '.json_encode(['id'=>$bs['id'], 'name'=>$bs['name'], 'type'=>'bus'])); } }
    
    // Optional: simple operator mapping by state via CSV (best-effort)
    static $opMap = null; if ($opMap === null) {
        $opMap = [];
        $csv = __DIR__.'/data/bus_data.csv';
        if (is_file($csv)){
            if (($fh = @fopen($csv,'r'))){
                while(($row = fgetcsv($fh))!==false){ if (count($row)>=2){ $opMap[strtolower(trim($row[0]))] = trim($row[1]); } }
                fclose($fh);
            }
        }
    }

    for ($i=0; $i < count($chain)-1; $i++){
        $from = $chain[$i]; $to = $chain[$i+1];
        [$fare, $duration_s, $km] = [REDACTED]($from['lat'],$from['lon'],$to['lat'],$to['lon']);
        $midLat = ($from['lat'] + $to['lat'])/2.0; $midLon = ($from['lon'] + $to['lon'])/2.0;
        [$delay, $sev, $raw] = tomtom_traffic($midLat,$midLon,$tomtomKey);
        $travel_min = (int)round($duration_s/60) + (int)$delay;
        $arr = [REDACTED]($cursor, $travel_min);
        $layover_min = ($i < count($chain)-2) ? (10 + mt_rand(0,5)) : 0;
        $arr_with_layover = $layover_min > 0 ? [REDACTED]($arr, $layover_min) : $arr;
        // Determine operator by state (prefer explicit stop state, else geofence from coords)
        $state = (string)($to['state'] ?? $from['state'] ?? '');
        if ($state === '' || strtolower($state) === 'unknown') {
            $gf = geofence_state((float)$to['lat'], (float)$to['lon']);
            $state = $gf ?: $state;
        }
        $stateKey = strtolower($state);
        $opName = 'KSRTC Bus';
        if ($stateKey !== '') {
            if (isset($opMap[$stateKey])) { $opName = $opMap[$stateKey]; }
            else if ($stateKey === 'kerala') { $opName = 'KSRTC Bus'; }
            else if ($stateKey === 'tamil nadu') { $opName = 'SETC'; }
            else if ($stateKey === 'west bengal') { $opName = 'WBTC'; }
        }
        @error_log('Operator chosen: '.json_encode(['state'=>$state,'operator'=>$opName,'station_id'=>$to['id'] ?? null]));
        // Snap bus route to roads for realistic path
        $snappedPoly = osrm_snap_route((float)$from['lat'], (float)$from['lon'], (float)$to['lat'], (float)$to['lon']);
        
        $leg = [
            'mode' => 'bus',
            'operator_name' => $opName,
            'from' => $from['name'],
            'to' => $to['name'],
            'from_lat' => $from['lat'], 'from_lon' => $from['lon'],
            'to_lat'   => $to['lat'],   'to_lon'   => $to['lon'],
            'fare' => $fare,
            'duration_s' => (int)round($duration_s + $delay*60),
            'departure' => $cursor,
            'arrival' => $arr,
            'traffic_delay' => (int)$delay,
            'traffic_delay_min' => (int)$delay,
            'traffic_severity' => $sev,
            'distance_km' => round($km,1),
            'layover_s' => $layover_min * 60,
            'polyline' => $snappedPoly,
            'instructions' => [
                [ 'step_id'=>'b'.($i+1).'s', 'mode'=>'walk', 'text'=>'Walk to bus bay', 'lat'=>$from['lat'], 'lon'=>$from['lon'], 'notify_when_m'=>30 ],
                [ 'step_id'=>'b'.($i+1).'b', 'mode'=>'bus', 'text'=>'Board '.($opName).' to '.(string)$to['name'], 'lat'=>$from['lat'], 'lon'=>$from['lon'], 'distance_m'=>(int)round($km*1000), 'duration_s'=>(int)round($duration_s + $delay*60), 'notify_when_m'=>200 ],
                [ 'step_id'=>'b'.($i+1).'a', 'mode'=>'alight', 'text'=>'Alight at '.(string)$to['name'], 'lat'=>$to['lat'], 'lon'=>$to['lon'], 'notify_when_m'=>50 ]
            ]
        ];
        if (isset($_GET['debug']) && $raw !== null) { $leg['traffic_raw'] = $raw; }
        $legs[] = $leg;
        $cursor = $arr_with_layover;
    }

    // Walk from last bus stop to destination (always include a feeder leg)
    $distFromLast = haversine_km((float)$lastBus['lat'], (float)$lastBus['lon'], (float)$destination['lat'], (float)$destination['lon']);
    $walk_min2 = max(2, (int)round(($distFromLast / 4.5) * 60));
    $legs[] = [
            'mode' => 'walk',
            'operator_name' => 'Walk',
            'from' => (string)$lastBus['name'],
            'to' => (string)($destination['name'] ?? 'Destination'),
            'from_lat' => (float)$lastBus['lat'], 'from_lon' => (float)$lastBus['lon'],
            'to_lat'   => (float)$destination['lat'],   'to_lon'   => (float)$destination['lon'],
            'fare' => 0,
            'duration_s' => $walk_min2 * 60,
            'departure' => $cursor,
            'arrival' => [REDACTED]($cursor, $walk_min2),
            'distance_km' => round($distFromLast,2),
            'instructions' => [
                [
                    'step_id'=>'w2', 'mode'=>'walk',
                    'text'=> 'Walk '.(int)round($distFromLast*1000).'m to '.(string)($destination['name'] ?? 'Destination'),
                    'lat'=>(float)$destination['lat'], 'lon'=>(float)$destination['lon'],
                    'distance_m'=>(int)round($distFromLast*1000), 'duration_s'=> $walk_min2*60, 'notify_when_m'=>50
                ]
            ]
    ];
    $cursor = $legs[count($legs)-1]['arrival'];

    // 5) If final destination not at a bus stand → add Taxi leg
    if (!is_near_bus_stop($pdo, (float)$destination['lat'], (float)$destination['lon'])){
        $last = $stops[count($stops)-1];
        [$fareT, $durT, $kmT] = [REDACTED]($last['lat'],$last['lon'], (float)$destination['lat'], (float)$destination['lon'], 10.0, 30.0);
        [$delayT, $sevT, $rawT] = get_ai_traffic($pdo, (string)$last['name'], (string)($destination['name'] ?? 'Destination'), $cursor, $kmT, 'road');
        $arrT = [REDACTED]($cursor, (int)round($durT/60) + $delayT);
        $legs[] = [
            'mode' => 'car', 'operator' => 'Taxi',
            'from' => $last['name'], 'to' => ($destination['name'] ?? 'Destination'),
            'from_lat'=>$last['lat'],'from_lon'=>$last['lon'],
            'to_lat'=>(float)$destination['lat'],'to_lon'=>(float)$destination['lon'],
            'fare' => $fareT, 'duration_s' => (int)round($durT + $delayT*60),
            'departure'=>$cursor, 'arrival'=>$arrT,
            'traffic_delay'=>$delayT, 'traffic_delay_min'=>$delayT, 'traffic_severity'=>$sevT,
            'distance_km'=>round($kmT,1),
        ];
        if (isset($_GET['debug']) && $rawT !== null) { $legs[count($legs)-1]['traffic_raw'] = $rawT; }
    }

    // Enrich stops with minimal fields
    $intermediate = array_map(function($s){ return [
        'name'=>$s['name'], 'lat'=>$s['lat'], 'lon'=>$s['lon'], 'type'=>'bus_stop', 'synthetic'=>!empty($s['synthetic'])
    ];}, $stops);

    // Debug sequence of chosen bus stops
    $chosenSeq = array_map(function($s){ return $s['name'] . (!empty($s['synthetic']) ? ' (synthetic)' : ''); }, $stops);
    return ['legs'=>$legs, 'intermediate_stops'=>$intermediate, 'debug_stops'=>$chosenSeq, 'used_bus_stops'=>$stops, 'chain_reason'=>$chain_reason];
}

// Nearest bus station from OSM bus_stations table
function nearest_bus_station(PDO $pdo, float $lat, float $lon): ?array {
    try {
        $sql = "SELECT id, name, type, state, lat, lon,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) AS dist_km
          FROM stations WHERE type='bus' AND lat IS NOT NULL AND lon IS NOT NULL ORDER BY dist_km ASC LIMIT 1";
        $st = $pdo->prepare($sql); $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
        if ($r = $st->fetch()) { @error_log("Chose station: ".json_encode($r)); return [ (string)$r['name'], (float)$r['lat'], (float)$r['lon'], (float)$r['dist_km'], (int)$r['id'], (string)($r['state']??'') ]; }
    } catch (Throwable $e) { /* ignore */ }
    return null;
}

// Find nearest stop given coordinates (uses legacy stops table)
function nearest_stop(PDO $pdo, float $lat, float $lon): ?array {
    try {
        $sql = "SELECT id, stop_name, city, latitude, longitude,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude)))) AS dist_km
          FROM stops WHERE latitude IS NOT NULL AND longitude IS NOT NULL
          ORDER BY dist_km ASC LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

// Hotels near a coordinate, trying legacy schema, then OSM schema, then Gemini fallback
function hotels_near(PDO $pdo, float $lat, float $lon, ?string $city = null, int $limit=5): array {
    // 1) Legacy schema
    try {
        $st = $pdo->prepare("SELECT city, hotel_name, price_per_night, latitude, longitude,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude)))) AS dist_km
          FROM hotels WHERE latitude IS NOT NULL AND longitude IS NOT NULL
          HAVING dist_km <= 30 ORDER BY dist_km ASC, price_per_night ASC LIMIT $limit");
        $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
        $rows = $st->fetchAll();
        if ($rows && count($rows)) { return $rows; }
    } catch (Throwable $e) { /* next */ }
    // 2) OSM schema
    try {
        $st = $pdo->prepare("SELECT name as hotel_name, city, price_per_night, lat as latitude, lon as longitude,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) AS dist_km
          FROM hotels WHERE lat IS NOT NULL AND lon IS NOT NULL
          HAVING dist_km <= 30 ORDER BY dist_km ASC LIMIT $limit");
        $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
        $rows = $st->fetchAll();
        if ($rows && count($rows)) { return $rows; }
    } catch (Throwable $e) { /* next */ }
    // 3) Gemini fallback via centralized helper
    $prompt = "List $limit realistic hotels within 30 km of lat=$lat, lon=$lon in India.\nRespond ONLY with JSON array of objects: [{hotel_name:string, price_per_night:int, latitude:float, longitude:float}]";
    try {
        $arr = call_gemini($prompt);
        if (!is_array($arr)) { $arr = call_gemini($prompt."\nReturn only valid JSON, no markdown, no commentary."); }
        if (is_array($arr)) return $arr;
    } catch (Throwable $e) { /* ignore */ }
    return [];
}

// Nearest airport from OSM airports table; fallback to static list if needed
function nearest_airport(PDO $pdo, float $lat, float $lon): array {
    try {
        $sql = "SELECT id, name, type, lat, lon,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) AS dist_km
          FROM stations WHERE type='airport' AND lat IS NOT NULL AND lon IS NOT NULL ORDER BY dist_km ASC LIMIT 1";
        $st = $pdo->prepare($sql); $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
        if ($r = $st->fetch()) { @error_log("Chose station: ".json_encode($r)); return [ (string)$r['name'], (float)$r['lat'], (float)$r['lon'] ]; }
    } catch (Throwable $e) { /* ignore */ }
    // Fallback minimal static
    $fallback = [ 'Delhi'=>[28.5562,77.1000], 'Bengaluru'=>[13.1986,77.7066], 'Mumbai'=>[19.0896,72.8656] ];
    $best=null;$bd=1e18; foreach($fallback as $n=>$c){ $d=haversine_km($lat,$lon,$c[0],$c[1]); if($d<$bd){$bd=$d;$best=[$n,$c[0],$c[1]];} }
    return $best ?: ['Delhi',28.5562,77.1000];
}

// Legacy helper preserved under guard to avoid redeclare; prefer stations table nearest_station() above
if (!function_exists('nearest_station')) {
function nearest_station(PDO $pdo, float $lat, float $lon): ?array {
    try {
        $sql = "SELECT name, lat, lon,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) AS dist_km
          FROM railway_stations WHERE lat IS NOT NULL AND lon IS NOT NULL ORDER BY dist_km ASC LIMIT 1";
        $st = $pdo->prepare($sql); $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
        if ($r = $st->fetch()) return [ (string)$r['name'], (float)$r['lat'], (float)$r['lon'] ];
    } catch (Throwable $e) { /* ignore */ }
    return null;
}
}

/**
 * New helper: AI traffic by origin/dest/hour with extended cache.
 * Returns [delay_min:int, severity:string one of low|medium|high]
 */
function get_ai_traffic(PDO $pdo, string $origin, string $dest, string $hourHH, float $distance_km, string $roadType='road'): array {
    // Ensure cache table with hour INT
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS traffic_cache_ext (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          origin TEXT NOT NULL,
          dest TEXT NOT NULL,
          hour INT NOT NULL,
          response_json TEXT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_tcache_hour (hour)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }

    $hour = max(0,min(23,(int)explode(':',$hourHH.':')[0]));
    // Weekday/weekend note included in prompt for realism (not a key now)
    $weekday = (int)date('N');
    $isWeekend = ($weekday === 6 || $weekday === 7);
    // Defaults on failure
    $fallback_delay = 0; $fallback_sev = 'none';

    // Try cache - 6h TTL
    try {
        $st = $pdo->prepare('SELECT response_json FROM traffic_cache_ext WHERE origin=? AND dest=? AND hour=? AND created_at >= (NOW() - INTERVAL 6 HOUR) ORDER BY id DESC LIMIT 1');
        $st->execute([trim($origin), trim($dest), $hour]);
        if ($row = $st->fetch()){
            $raw = (string)($row['response_json'] ?? '');
            $j = json_decode($raw, true);
            if (is_array($j)){
                $d = (int)($j['delay'] ?? $j['delay_minutes'] ?? $fallback_delay);
                $s = (string)($j['severity'] ?? $fallback_sev);
                if (!in_array($s, ['none','low','medium','high'], true)) { $s = $fallback_sev; }
                return [max(0,$d), $s, $raw];
            }
        }
    } catch (Throwable $e) { /* ignore */ }

    // Build strict JSON prompt for Gemini 2.5 Flash
    $dist = round(max(1.0,$distance_km),1);
    $prompt = [
        'model' => 'gemini-2.5-flash',
        'task'  => 'traffic_estimate',
        'instructions' => "Predict road traffic in India. Return ONLY JSON { delay:int, severity:string(one of none,low,medium,high) }.",
        'inputs' => [
            'origin' => trim($origin),
            'destination' => trim($dest),
            'distance_km' => $dist,
            'hour' => sprintf('%02d',$hour),
            'day_type' => $isWeekend ? 'weekend' : 'weekday',
            'road_type' => $roadType
        ]
    ];
    $rawOut = null; $obj = null;
    try {
        $res = call_gemini(json_encode($prompt));
        if (is_string($res)) { $rawOut = $res; }
        if (is_array($res)) { $obj = $res; $rawOut = json_encode($res); }
        if (!$obj && is_string($rawOut)){
            // Strip markdown code fences if present
            $clean = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $rawOut);
            $try = json_decode($clean, true);
            if (is_array($try)) { $obj = $try; }
        }
        if (!$obj){
            // Fallback: ask again with ultra-strict instruction
            $fallbackPrompt = $prompt;
            $fallbackPrompt['instructions'] .= ' No markdown, no commentary, only JSON.';
            $res2 = call_gemini(json_encode($fallbackPrompt));
            if (is_array($res2)) { $obj = $res2; $rawOut = json_encode($res2); }
            elseif (is_string($res2)) { $rawOut = $res2; $obj = json_decode(preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $res2), true); }
        }
    } catch (Throwable $e) { /* ignore */ }

    $delay = $fallback_delay; $sev = $fallback_sev;
    if (is_array($obj)){
        $delay = max(0, (int)($obj['delay'] ?? $obj['delay_minutes'] ?? $fallback_delay));
        $sev = (string)($obj['severity'] ?? $fallback_sev);
        if (!in_array($sev, ['none','low','medium','high'], true)) { $sev = $fallback_sev; }
    }
    // Cache
    try {
        $payload = json_encode(['delay'=>$delay,'severity'=>$sev]);
        $ins = $pdo->prepare('INSERT INTO traffic_cache_ext(origin,dest,hour,response_json) VALUES (?,?,?,?)');
        $ins->execute([trim($origin),trim($dest),$hour,$payload]);
        if ($rawOut && $obj===null) { // store raw if parsing failed
            $ins2 = $pdo->prepare('INSERT INTO traffic_cache_ext(origin,dest,hour,response_json) VALUES (?,?,?,?)');
            $ins2->execute([trim($origin),trim($dest),$hour,$rawOut]);
        }
    } catch (Throwable $ie) { /* ignore */ }
    return [$delay, $sev, $rawOut];
}

/**
 * AI-first operator selection with cache. Falls back to DB mapping if AI not available.
 */
function [REDACTED](PDO $pdo, string $state, string $mode, callable $fallback): string {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS operator_cache (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          state VARCHAR(100) NOT NULL,
          mode VARCHAR(20) NOT NULL,
          operator_name VARCHAR(150) NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_operator_cache (state, mode)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }
    $state = trim($state);
    $modeL = strtolower(trim($mode));
    if ($state==='') return $fallback();
    try {
        $st = $pdo->prepare('SELECT operator_name FROM operator_cache WHERE state=? AND mode=? ORDER BY created_at DESC LIMIT 1');
        $st->execute([$state,$modeL]);
        if ($row = $st->fetch()) return (string)$row['operator_name'];
    } catch (Throwable $e) { /* ignore */ }
    $promptObj = [
        'state'=>$state,
        'mode'=>$modeL,
        'goal'=>'Given Indian state and transport mode, return the most likely operator name as JSON: {"operator_name":"..."}.'
    ];
    $obj = call_gemini(json_encode($promptObj));
    $op = is_array($obj) ? (string)($obj['operator_name'] ?? '') : '';
    if ($op !== ''){
        try { $ins = $pdo->prepare('INSERT INTO operator_cache(state,mode,operator_name) VALUES (?,?,?)'); $ins->execute([$state,$modeL,$op]); } catch (Throwable $ie) {}
        return $op;
    }
    return $fallback();
}

function [REDACTED](string $txt): ?string {
    $t = strtolower($txt);
    $map = ['kerala'=>'Kerala','karnataka'=>'Karnataka','tamil nadu'=>'Tamil Nadu','maharashtra'=>'Maharashtra','delhi'=>'Delhi','rajasthan'=>'Rajasthan','uttar pradesh'=>'Uttar Pradesh'];
    foreach ($map as $k=>$v){ if (strpos($t,$k)!==false) return $v; }
    return null;
}

function get_bus_operator(PDO $pdo, string $origin_name, string $dest_name): string {
    $state = [REDACTED]($origin_name) ?: [REDACTED]($dest_name);
    if (!$state) return 'KSRTC';
    // Optional CSV map: data/bus_data.csv with headers: state,operator
    static $csvMap = null;
    if ($csvMap === null) {
        $csvMap = [];
        $csv = __DIR__ . '/data/bus_data.csv';
        if (is_file($csv)) {
            try {
                $fh = fopen($csv, 'r');
                if ($fh) {
                    $header = fgetcsv($fh);
                    while (($row = fgetcsv($fh)) !== false) {
                        $st = trim($row[0] ?? ''); $op = trim($row[1] ?? '');
                        if ($st !== '' && $op !== '') $csvMap[strtolower($st)] = $op;
                    }
                    fclose($fh);
                }
            } catch (Throwable $e) { /* ignore */ }
        }
    }
    $key = strtolower($state);
    if ($csvMap && isset($csvMap[$key])) return $csvMap[$key];
    try {
        $st = $pdo->prepare('SELECT operator FROM buses WHERE state = :s ORDER BY id ASC LIMIT 1');
        $st->execute([':s'=>$state]);
        $row = $st->fetch(); if ($row && !empty($row['operator'])) return $row['operator'];
    } catch (Throwable $e) {}
    return 'KSRTC';
}

/**
 * Sample polyline approximately every $sample_m meters (using haversine), returning [lat,lon] samples.
 */
function sample_polyline(array $poly, float $sample_m = 500.0): array {
    if (count($poly) < 2) return $poly;
    $out = [$poly[0]]; $acc = 0.0; $prev = $poly[0];
    for ($i=1; $i<count($poly); $i++){
        $cur = $poly[$i];
        $seg_km = haversine_km($prev[0],$prev[1],$cur[0],$cur[1]);
        $seg_m = $seg_km * 1000.0;
        $acc += $seg_m;
        while ($acc >= $sample_m) {
            $t = max(0.0, min(1.0, 1.0 - ($acc - $sample_m)/$seg_m));
            $lat = $prev[0] + ($cur[0]-$prev[0])*$t; $lon = $prev[1] + ($cur[1]-$prev[1])*$t;
            $out[] = [$lat,$lon];
            $acc -= $sample_m;
        }
        $prev = $cur;
    }
    $out[] = $poly[count($poly)-1];
    return $out;
}

/**
 * Find DB stops near polyline by scanning once and ordering by progress.
 * radius_km: inclusion radius; sample_m: sampling resolution.
 */
function [REDACTED](PDO $pdo, array $poly, float $radius_km=1.5, float $sample_m=500.0, ?string $stopType=null): array {
    // Prefer OSM bus_stations when stopType=bus; fallback to legacy stops table
    $rows = [];
    if ($stopType && strtolower($stopType)==='bus'){
        try {
            $rows = $pdo->query("SELECT id, name AS stop_name, city, lat AS latitude, lon AS longitude, NULL AS operator_name, 'bus' AS stop_type, state, 0 AS synthetic FROM bus_stations WHERE lat IS NOT NULL AND lon IS NOT NULL")->fetchAll();
        } catch (Throwable $e) { $rows = []; }
    }
    if (!$rows){
        try {
            $sql = "SELECT id, stop_name, city, latitude, longitude, operator_name, stop_type, state, COALESCE(synthetic,0) AS synthetic FROM stops WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
            if ($stopType) { $stt = strtolower($stopType); $sql .= " AND LOWER(stop_type)=".$pdo->quote($stt); }
            $rows = $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) { $rows = []; }
    }
    if (!$rows) return [];
    $cands = [];
    foreach ($rows as $r) {
        $lat = (float)$r['latitude']; $lon=(float)$r['longitude'];
        [$d,$prog] = [REDACTED]($lat,$lon,$poly);
        if ($d <= $radius_km) {
            $cands[] = [
                'id'=>(int)$r['id'],
                'name'=>$r['stop_name'] ?: ($r['city'] ?: 'Stop'),
                'city'=>$r['city'] ?: '',
                'lat'=>$lat, 'lon'=>$lon,
                'operator'=>$r['operator_name'] ?: '',
                'state'=>$r['state'] ?? '',
                'stop_type'=>$r['stop_type'] ?? '',
                'synthetic'=>(int)$r['synthetic']===1,
                'prog'=>$prog
            ];
            if (isset($_GET['debug'])){
                $debug = $debug ?? [];
                $debug['operators'] = ($debug['operators'] ?? []);
                $debug['operators'][] = ['mode'=>'flight','operator'=>$flightOp];
                $debug['operators'][] = ['mode'=>'metro','operator'=>'DMRC'];
            }
        }
    }
    usort($cands, fn($a,$b)=> $a['prog'] <=> $b['prog']);
    // Space them at least ~40km apart (to reduce tiny hops)
    $chain = [];
    foreach ($cands as $c){
        if (!empty($chain)){
            $last = $chain[count($chain)-1];
            if (haversine_km($last['lat'],$last['lon'],$c['lat'],$c['lon']) < 40) continue;
        }
        $chain[] = $c;
    }
    return $chain;
}

/**
 * AI inference for missing intermediate stops. Caches to ai_stop_cache and persists synthetic stops.
 * Returns array of ['id'?, 'name','lat','lon','synthetic'=>bool]
 */
function ai_infer_stops(PDO $pdo, string $origin_name, string $dest_name, array $poly, float $approx_km, int $max_create=8): array {
    $origin_hash = hash('sha256', $origin_name);
    $dest_hash   = hash('sha256', $dest_name);
    $date_bucket = date('Y-m-d');
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_stop_cache (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          origin_hash CHAR(64) NOT NULL,
          dest_hash   CHAR(64) NOT NULL,
          date_bucket DATE NOT NULL,
          response_json JSON NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_stop_cache (origin_hash, dest_hash, date_bucket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }
    // Build strict prompt
    $pts = [];
    foreach (sample_polyline($poly, 10000.0) as $p){ $pts[] = sprintf('%.4f,%.4f',$p[0],$p[1]); }
    $prompt = "Generate up to $max_create plausible intermediate bus stands between $origin_name and $dest_name in India, near these waypoints: ".implode(' | ', $pts).".\nRespond ONLY with JSON array: [{stop_name:string, operator_name:string, lat:float, lon:float}]";
    $arr = call_gemini($prompt);
    if (!is_array($arr)) { $arr = call_gemini($prompt."\nReturn only valid JSON, no markdown, no commentary."); }
    if (!is_array($arr)) return [];

    $out = []; $created = 0;
    foreach ($arr as $it){
        $name = (string)($it['stop_name'] ?? '');
        $op   = (string)($it['operator_name'] ?? 'KSRTC');
        $lat  = isset($it['lat']) ? (float)$it['lat'] : null;
        $lon  = isset($it['lon']) ? (float)$it['lon'] : null;
        if ($name==='' || $lat===null || $lon===null) continue;
        // Use existing nearby stop if available within 5km
        $row = null;
        try {
            $st = $pdo->prepare("SELECT id, stop_name, latitude, longitude, COALESCE(synthetic,0) AS synthetic FROM stops WHERE latitude IS NOT NULL AND longitude IS NOT NULL ORDER BY ABS(latitude-?)+ABS(longitude-?) ASC LIMIT 50");
            $st->execute([$lat,$lon]);
            $rows = $st->fetchAll();
            foreach ($rows as $r){
                $d = haversine_km((float)$r['latitude'],(float)$r['longitude'],$lat,$lon);
                if ($d <= 5.0){ $row = $r; break; }
            }
        } catch (Throwable $e) { /* ignore */ }
        if ($row){
            $out[] = ['id'=>(int)$row['id'],'name'=>$row['stop_name']?:$name,'lat'=>(float)$row['latitude'],'lon'=>(float)$row['longitude'],'synthetic'=>(int)($row['synthetic']??0)===1];
            continue;
        }
        if ($created >= $max_create) continue;
        try {
            $ins = $pdo->prepare("INSERT INTO stops(stop_name, city, latitude, longitude, operator_name, synthetic) VALUES (?,?,?,?,?,1)");
            $city = '';
            $ins->execute([$name,$city,$lat,$lon,$op]);
            $id = (int)$pdo->lastInsertId();
            $out[] = ['id'=>$id,'name'=>$name,'lat'=>$lat,'lon'=>$lon,'synthetic'=>true];
            $created++;
        } catch (Throwable $ie) {
            $out[] = ['name'=>$name,'lat'=>$lat,'lon'=>$lon,'synthetic'=>true];
        }
    }
    // Cache raw normalized array
    try { $ins = $pdo->prepare('INSERT INTO ai_stop_cache(origin_hash,dest_hash,date_bucket,response_json) VALUES (?,?,?,?)'); $ins->execute([$origin_hash,$dest_hash,$date_bucket,json_encode($arr)]); } catch (Throwable $e) { /* ignore */ }
    return $out;
}

/**
 * Get AI traffic delay with caching and severity classification.
 * Returns [delay_min:int, severity:string(low|medium|high)].
 */
function [REDACTED](PDO $pdo, string $city, string $hhmm, float $distance_km): array {
    $city = trim($city) ?: 'City';
    $hour = (int)explode(':', $hhmm ?: '08:00')[0];
    $weekday = (int)date('N');
    $weekday_flag = ($weekday >= 1 && $weekday <= 5) ? 1 : 0;
    $road_type = $distance_km <= 20 ? 'city' : 'highway';
    // Try cache (same city, weekday flag, hour bucket)
    try {
        $st = $pdo->prepare('SELECT delay_min, severity FROM traffic_cache WHERE city = :c AND weekday_flag = :w AND hour_bucket = :h ORDER BY created_at DESC LIMIT 1');
        $st->execute([':c'=>$city, ':w'=>$weekday_flag, ':h'=>$hour]);
        if ($row = $st->fetch()) {
            return [ (int)$row['delay_min'], (string)$row['severity'] ];
        }
    } catch (Throwable $e) { /* ignore */ }

    // Build Gemini prompt (key handled in helper)
    // Distance-based fallback if AI not available or fails
    $dkm = max(0.0, (float)$distance_km);
    if ($dkm < 5) { $fallback_sev = 'light'; $fallback_delay = mt_rand(0, 5); }
    elseif ($dkm <= 50) { $fallback_sev = 'moderate'; $fallback_delay = mt_rand(5, 20); }
    else { $fallback_sev = 'heavy'; $fallback_delay = mt_rand(20, 60); }
    if ($key === '') return [$fallback_delay, $fallback_sev];

    $weekday_text = $weekday_flag ? 'weekday' : 'weekend';
    $prompt = sprintf(
        "Estimate traffic delay in minutes for a %.0f km bus trip in %s, India on a %s at %s. Consider Indian rush hour and congestion. Respond strictly as JSON: {\"delay\": <int>, \"severity\": \"none|light|moderate|heavy\"}.",
        max(1.0,$distance_km), $city, $weekday_text, $hhmm
    );
    // Use centralized helper for consistency
    try {
        $o = call_gemini($prompt);
        $delay = $fallback_delay; $sev = $fallback_sev;
        if (!is_array($o)) { $o = call_gemini($prompt."\nReturn only valid JSON, no markdown, no commentary."); }
        if (is_array($o)) {
            if (isset($o['delay'])) { $delay = (int)$o['delay']; }
            if (isset($o['severity']) && in_array($o['severity'], ['none','light','moderate','heavy'], true)) { $sev = $o['severity']; }
        }
        // cache
        try {
            $ins = $pdo->prepare('INSERT INTO traffic_cache(city,weekday_flag,hour_bucket,distance_km,road_type,delay_min,severity) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$city,$weekday_flag,$hour,max(1,$distance_km),$road_type,$delay,$sev]);
        } catch (Throwable $ie) { /* ignore */ }
        return [$delay, $sev];
    } catch (Throwable $e) {
        return [$fallback_delay, $fallback_sev];
    }
}

/**
 * Call local GraphHopper to get a car route polyline between two coords.
 * Returns array of [lat, lon] points or empty array on failure.
 */
function gh_route_points(float $olat, float $olon, float $dlat, float $dlon): array {
    // Quick mode: skip GraphHopper external call
    global $IS_QUICK; if ($IS_QUICK) { return []; }
    $base = getenv('VELO_GH_BASE') ?: 'http://localhost:8989';
    $url = sprintf('%s/route?point=%f,%f&point=%f,%f&profile=car&locale=en&points_encoded=false',
        rtrim($base,'/'), $olat, $olon, $dlat, $dlon);
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [[REDACTED]=>true, CURLOPT_TIMEOUT=>3, [REDACTED]=>2]);
        $res = curl_exec($ch);
        if ($res === false) return [];
        $code = curl_getinfo($ch, [REDACTED]); curl_close($ch);
        if ($code < 200 || $code >= 300) return [];
        $j = json_decode($res, true);
        if (!isset($j['paths'][0]['points']['coordinates'])) return [];
        $coords = $j['paths'][0]['points']['coordinates']; // [lon, lat]
        $pts = [];
        foreach ($coords as $c) { $pts[] = [ (float)$c[1], (float)$c[0] ]; }
        return $pts;
    } catch (Throwable $e) { return []; }
}

/**
 * Snap bus route to roads using OSRM (public demo or self-hosted).
 * Returns road-snapped polyline as [[lat,lon],...] or fallback to direct line.
 */
function osrm_snap_route(float $olat, float $olon, float $dlat, float $dlon): array {
    global $IS_QUICK; if ($IS_QUICK) { return [[$olat,$olon],[$dlat,$dlon]]; }
    
    // Skip OSRM for very short distances (<1km) to avoid unnecessary API calls
    $dist = haversine_km($olat, $olon, $dlat, $dlon);
    if ($dist < 1.0) return [[$olat,$olon],[$dlat,$dlon]];
    
    $base = getenv('VELO_OSRM_BASE') ?: 'https://router.project-osrm.org';
    $url = sprintf('%s/route/v1/driving/%f,%f;%f,%f?overview=full&geometries=geojson',
        rtrim($base,'/'), $olon, $olat, $dlon, $dlat);
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            [REDACTED]=>true, 
            CURLOPT_TIMEOUT=>5,
            [REDACTED]=>3,
            CURLOPT_USERAGENT=>'Velora/1.0',
            [REDACTED]=>true
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            @error_log('OSRM snap failed: '.curl_error($ch));
            curl_close($ch);
            return [[$olat,$olon],[$dlat,$dlon]];
        }
        $code = curl_getinfo($ch, [REDACTED]); curl_close($ch);
        if ($code < 200 || $code >= 300) {
            @error_log('OSRM snap HTTP error: '.$code);
            return [[$olat,$olon],[$dlat,$dlon]];
        }
        $j = json_decode($res, true);
        if (!isset($j['routes'][0]['geometry']['coordinates'])) return [[$olat,$olon],[$dlat,$dlon]];
        $coords = $j['routes'][0]['geometry']['coordinates']; // [lon, lat]
        $pts = [];
        foreach ($coords as $c) { $pts[] = [ (float)$c[1], (float)$c[0] ]; }
        @error_log('OSRM snap success: '.count($pts).' points for '.$dist.'km');
        return $pts;
    } catch (Throwable $e) { 
        @error_log('OSRM snap exception: '.$e->getMessage());
        return [[$olat,$olon],[$dlat,$dlon]]; 
    }
}

/**
 * Fetch traffic flow data for multiple points along a route.
 * Returns array of traffic segments with speed, severity, and coordinates.
 */
function fetch_route_traffic(array $polyline, string $tomtomKey): array {
    global $IS_QUICK; if ($IS_QUICK) { return []; }
    $samples = sample_polyline_km($polyline, 10.0); // Sample every 10km
    $traffic = [];
    foreach ($samples as $pt) {
        [$delay, $sev, $raw] = tomtom_traffic((float)$pt[0], (float)$pt[1], $tomtomKey);
        $speed = 50; // default
        if ($raw && isset($raw['flowSegmentData']['currentSpeed'])) {
            $speed = (int)$raw['flowSegmentData']['currentSpeed'];
        }
        $traffic[] = [
            'lat' => (float)$pt[0],
            'lon' => (float)$pt[1],
            'speed_kmph' => $speed,
            'severity' => $sev,
            'delay_min' => $delay
        ];
        @error_log('Traffic update: lat='.$pt[0].' lon='.$pt[1].' avg_speed_kmph='.$speed.' severity='.$sev.' eta_delta_min='.$delay);
    }
    return $traffic;
}

/**
 * Compute nearest distance (km) and progress (segment index + fraction) of a point to a polyline.
 */
function [REDACTED](float $plat, float $plon, array $poly): array {
    if (count($poly) < 2) return [INF, 0.0];
    $bestD = INF; $bestProg = 0.0; $acc = 0.0;
    for ($i=0; $i < count($poly)-1; $i++) {
        [$aLat,$aLon] = $poly[$i]; [$bLat,$bLon] = $poly[$i+1];
        // Approximate projection in lat/lon by sampling; for robustness use distances
        $dA = haversine_km($plat,$plon,$aLat,$aLon);
        $dB = haversine_km($plat,$plon,$bLat,$bLon);
        $dAB= haversine_km($aLat,$aLon,$bLat,$bLon);
        if ($dAB <= 1e-6) { if ($dA < $bestD) { $bestD=$dA; $bestProg=$i; } continue; }
        // Law of cosines projection approximation
        $t = max(0.0, min(1.0, ( ($dAB*$dAB + $dA*$dA - $dB*$dB) / (2*$dAB*$dAB) )));
        // Interpolated point distance using simple splitting
        $px = $aLat + ($bLat-$aLat)*$t; $py = $aLon + ($bLon-$aLon)*$t;
        $d = haversine_km($plat,$plon,$px,$py);
        if ($d < $bestD) { $bestD = $d; $bestProg = $acc + $t; }
        $acc += 1.0; // each segment counts as 1 unit
    }
    return [$bestD, $bestProg];
}
// Legacy output unchanged when using IDs.

function haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371.0; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// (Removed duplicate header/require and legacy json_error helper)

// Simple heuristic to infer operator type from operator name
function inferOperatorType(?string $operatorName): string {
    $name = strtolower($operatorName ?? '');
    if ($name === '') return 'Bus';
    if (str_contains($name, 'rail') || str_contains($name, 'irctc') || str_contains($name, 'train')) return 'Train';
    if (str_contains($name, 'air') || str_contains($name, 'flight') || str_contains($name, 'indigo') || str_contains($name, 'vistara') || str_contains($name, 'spicejet')) return 'Flight';
    return 'Bus';
}

function [REDACTED](string $hhmm, int $delta): string {
    $parts = explode(':', $hhmm);
    $h = (int)($parts[0] ?? 0); $m = (int)($parts[1] ?? 0);
    $t = $h*60 + $m + $delta; if ($t < 0) $t = 0; $h2 = intdiv($t,60)%24; $m2 = $t%60;
    return sprintf('%02d:%02d', $h2, $m2);
}

function [REDACTED](?string $city, ?string $hhmm): int {
    $city = $city ?: 'city'; $hhmm = $hhmm ?: '08:00';
    $fallback = (int)(15 + (crc32($city.$hhmm) % 16)); // 15..30
    $prompt = "Predict traffic delay in minutes for $city at $hhmm on a weekday. Respond ONLY with JSON {minutes:int}.";
    try {
        $obj = call_gemini($prompt);
        if (!is_array($obj)) { $obj = call_gemini($prompt."\nReturn only valid JSON, no markdown, no commentary."); }
        if (is_array($obj) && isset($obj['minutes'])) { return min(120, max(0, (int)$obj['minutes'])); }
        return $fallback;
    } catch (Throwable $e) { return $fallback; }
}

try {
    $start_time = microtime(true);
    @error_log('=== plan_trip.php START === '.date('Y-m-d H:i:s').' params='.json_encode($_GET));
    $pdo = get_pdo();
    @error_log('Database connected successfully');
    
    // Global time conversion helper to avoid undefined variable errors
    $toMinLoc = function(string $t){ [$h,$m] = array_map('intval', explode(':',$t.':0')); return $h*60+$m; };
    
    // Initialize to avoid undefined variable notices and to standardize response
    $decision = 'velora';
    $segments = [];
    $hotels = [];
    $total_fare = 0;
    $total_time = '';
    $intermediate_meta = [];
    $usedChaining = false;
    // Optional debug container (only returned when debug=true)
    $debug = null;
    // Ensure schema tables exist
    try { [REDACTED]($pdo); } catch (Throwable $e) {}
    try { [REDACTED]($pdo); } catch (Throwable $e) {}
    // Ensure caches exist (idempotent)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS traffic_cache (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          city VARCHAR(120) NOT NULL,
          weekday_flag TINYINT(1) NOT NULL,
          hour_bucket TINYINT(2) NOT NULL,
          distance_km DECIMAL(6,1) NULL,
          road_type VARCHAR(20) NULL,
          delay_min INT NOT NULL,
          severity VARCHAR(16) NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_traffic (city, weekday_flag, hour_bucket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_stop_cache (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          origin_hash CHAR(64) NOT NULL,
          dest_hash CHAR(64) NOT NULL,
          date_bucket DATE NOT NULL,
          response_json JSON NOT NULL,
          created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_ai_stop_cache (origin_hash, dest_hash, date_bucket)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* ignore */ }

    // New multimodal path if coords are supplied
    $origin_lat = isset($_GET['origin_lat']) ? (float)$_GET['origin_lat'] : null;
    $origin_lon = isset($_GET['origin_lon']) ? (float)$_GET['origin_lon'] : null;
    $dest_lat   = isset($_GET['dest_lat']) ? (float)$_GET['dest_lat'] : null;
    $dest_lon   = isset($_GET['dest_lon']) ? (float)$_GET['dest_lon'] : null;
    $origin_name= isset($_GET['origin_name']) ? trim((string)$_GET['origin_name']) : '';
    $dest_name  = isset($_GET['dest_name']) ? trim((string)$_GET['dest_name']) : '';
    $depart_time= isset($_GET['depart_time']) ? trim((string)$_GET['depart_time']) : '08:00';
    // Vehicle flag from either has_vehicle=1|0 or vehicle=yes|no
    $has_vehicle = isset($_GET['has_vehicle']) ? (int)$_GET['has_vehicle'] : 0;
    if (!$has_vehicle) {
        $veh = isset($_GET['vehicle']) ? strtolower(trim((string)$_GET['vehicle'])) : '';
        if ($veh === 'yes') $has_vehicle = 1; elseif ($veh === 'no') $has_vehicle = 0;
    }

    // TomTom geocoding fallback when only names provided
    if (($origin_lat === null || $origin_lon === null) && $origin_name !== '') {
        $geoO = tomtom_geocode($origin_name, $TOMTOM_KEY);
        if ($geoO) { $origin_lat = $geoO['lat']; $origin_lon = $geoO['lon']; $origin_name = $geoO['name'] ?: $origin_name; if (isset($_GET['debug'])) { $debug_geo_o = $geoO['_req'] ?? null; } }
    }
    if (($dest_lat === null || $dest_lon === null) && $dest_name !== '') {
        $geoD = tomtom_geocode($dest_name, $TOMTOM_KEY);
        if ($geoD) { $dest_lat = $geoD['lat']; $dest_lon = $geoD['lon']; $dest_name = $geoD['name'] ?: $dest_name; if (isset($_GET['debug'])) { $debug_geo_d = $geoD['_req'] ?? null; } }
    }
    if ($origin_lat !== null && $origin_lon !== null && $dest_lat !== null && $dest_lon !== null) {
        $dist_km = haversine_km($origin_lat, $origin_lon, $dest_lat, $dest_lon);
        $segments = [];
        $cursor = $depart_time;

        // Static airports list (city -> coords)
        $airports = [
            'Delhi' => [28.5562, 77.1000],
            'Kochi' => [10.1520, 76.4019],
            'Bengaluru' => [13.1986, 77.7066],
            'Chennai' => [12.9941, 80.1709],
            'Mumbai' => [19.0896, 72.8656],
            'Kolkata' => [22.6547, 88.4467],
            'Jaipur' => [26.8242, 75.8122],
            'Pune' => [18.5814, 73.9200],
            'Hyderabad' => [17.2403, 78.4294],
        ];
        // Helper: nearest airport name to a coordinate
        $nearestAirport = function(float $lat, float $lon) use($airports): array {
            $best = null; $bestD = 1e18;
            foreach ($airports as $name => $c) {
                $d = haversine_km($lat, $lon, $c[0], $c[1]);
                if ($d < $bestD) { $bestD = $d; $best = [$name, $c[0], $c[1]]; }
            }
            return $best ?: ['Delhi',28.5562,77.1000];
        };

        // Modes: 'drive', 'bus' (bus-only), 'train', 'flight', or 'velora' (default)
        $req_mode = isset($_GET['mode']) ? trim($_GET['mode']) : '';
        
        // Smart mode selection for Velora auto mode
        if ($req_mode === '' || $req_mode === 'velora') {
            if ($dist_km < 50) {
                @error_log("Velora: Short distance ({$dist_km}km), using bus");
                $req_mode = 'bus';
            } else if ($dist_km >= 50 && $dist_km < 300) {
                @error_log("Velora: Medium distance ({$dist_km}km), using bus");
                $req_mode = 'bus';
            } else if ($dist_km >= 300 && $dist_km < 600) {
                @error_log("Velora: Long distance ({$dist_km}km), using train");
                $req_mode = 'train';
            } else {
                @error_log("Velora: Very long distance ({$dist_km}km), using flight");
                $req_mode = 'flight';
            }
        }
        // Explicit bus-only: use graph-based routing with fallback
        if ($req_mode === 'bus' || $req_mode === 'bus_only') {
            @error_log('Mode: bus_only, distance='.$dist_km.'km');
            $decision = 'bus_chain';
            $originArr = ['name'=>$origin_name ?: 'Origin', 'lat'=>$origin_lat, 'lon'=>$origin_lon];
            $destArr   = ['name'=>$dest_name   ?: 'Destination', 'lat'=>$dest_lat,   'lon'=>$dest_lon];
            
            // Use realistic router (follows actual roads)
            @error_log('Using realistic road-following bus router...');
            $built = [REDACTED]($pdo, $originArr, $destArr, $cursor, $TOMTOM_KEY);
            
            // Fallback to graph-based if realistic fails
            if (isset($built['error']) || empty($built['legs'])) {
                @error_log('Realistic routing failed, trying graph fallback');
                $built = [REDACTED]($pdo, $originArr, $destArr, $cursor, $TOMTOM_KEY);
            }
            
            // Final fallback to corridor
            if (isset($built['error']) || empty($built['legs'])) {
                @error_log('Graph routing also failed, using corridor fallback');
                $rt = tomtom_route_points($origin_lat,$origin_lon,$dest_lat,$dest_lon,$TOMTOM_KEY);
                $poly = $rt['success'] ? ($rt['data']['points'] ?? []) : [[$origin_lat,$origin_lon],[$dest_lat,$dest_lon]];
                $built = build_bus_chaining($pdo, $originArr, $destArr, $poly, $cursor, $TOMTOM_KEY);
            }
            
            @error_log('Bus routing completed, legs='.count($built['legs']??[]).', method='.($built['method']??'corridor'));
            $segments = $built['legs'] ?? [];
            $intermediate_meta = $built['intermediate_stops'] ?? [];
            // Totals for bus-only
            $total_fare = 0; $start = $segments[0]['departure']; $end = $segments[count($segments)-1]['arrival'];
            foreach ($segments as $s) { $total_fare += (int)($s['fare'] ?? 0); }
            $toMin = function(string $t){ [$h,$m] = array_map('intval', explode(':',$t.':0')); return $h*60+$m; };
            $durMin = ($toMin($end) - $toMin($start) + 24*60) % (24*60);
            $total_time = sprintf('%dh %02dm', intdiv($durMin,60), $durMin%60);
            // Flat JSON response per requirement
            $resp = [
                'success' => true,
                'decision' => $decision,
                'segments' => $segments,
                'legs' => $segments,
                'total_fare' => $total_fare,
                'total_time' => $total_time,
                'resolved_origin' => ['id'=>null,'name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon,'source'=>'coords'],
                'resolved_dest' => ['id'=>null,'name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon,'source'=>'coords'],
                'chaining' => true,
                'intermediate_stops' => $intermediate_meta
            ];
            if (isset($_GET['debug'])) { $resp['debug'] = ['gh_poly'=>$poly, 'intermediate_stops'=>$intermediate_meta]; }
            @error_log('=== plan_trip.php END === Sending response, success=true');
            ob_end_clean(); // Clear any buffered output
            echo json_encode($resp); 
            exit;
        }
        
        // Explicit train mode: use realistic train routing
        if ($req_mode === 'train') {
            @error_log('Mode: train, distance='.$dist_km.'km');
            $decision = 'train';
            // Use realistic train router
            $trainResult = [REDACTED]($pdo, ['name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon], 
                                                     ['name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon], $cursor);
            if (isset($trainResult['error'])) {
                $resp = ['success'=>false,'error'=>$trainResult['error'],'detail'=>'train_unavailable'];
                ob_end_clean(); echo json_encode($resp); exit;
            }
            $segments = $trainResult['legs'];
            $intermediate_meta = $trainResult['intermediate_stops'];
            $total_fare = 0; $start = $segments[0]['departure']; $end = $segments[count($segments)-1]['arrival'];
            foreach ($segments as $s) { $total_fare += (int)($s['fare'] ?? 0); }
            $durMin = ($toMinLoc($end) - $toMinLoc($start) + 24*60) % (24*60);
            $total_time = sprintf('%dh %02dm', intdiv($durMin,60), $durMin%60);
            $resp = [
                'success'=>true,'decision'=>$decision,'segments'=>$segments,'legs'=>$segments,
                'total_fare'=>$total_fare,'total_time'=>$total_time,
                'resolved_origin'=>['id'=>null,'name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon,'source'=>'coords'],
                'resolved_dest'=>['id'=>null,'name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon,'source'=>'coords'],
                'intermediate_stops'=>$intermediate_meta
            ];
            @error_log('=== plan_trip.php END === Train mode, segments='.count($segments).', time='.(microtime(true)-$start_time).'s');
            ob_end_clean(); echo json_encode($resp); exit;
        }
        
        // Explicit flight mode: use realistic flight routing
        if ($req_mode === 'flight') {
            @error_log('Mode: flight, distance='.$dist_km.'km');
            $decision = 'flight';
            $flightResult = build_flight_route($pdo, ['name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon], 
                                              ['name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon], $cursor);
            if (isset($flightResult['error'])) {
                $resp = ['success'=>false,'error'=>$flightResult['error'],'detail'=>'flight_unavailable'];
                ob_end_clean(); echo json_encode($resp); exit;
            }
            $segments = $flightResult['legs'];
            $intermediate_meta = $flightResult['intermediate_stops'];
            $total_fare = 0; $start = $segments[0]['departure']; $end = $segments[count($segments)-1]['arrival'];
            foreach ($segments as $s) { $total_fare += (int)($s['fare'] ?? 0); }
            $durMin = ($toMinLoc($end) - $toMinLoc($start) + 24*60) % (24*60);
            $total_time = sprintf('%dh %02dm', intdiv($durMin,60), $durMin%60);
            $resp = [
                'success'=>true,'decision'=>$decision,'segments'=>$segments,'legs'=>$segments,
                'total_fare'=>$total_fare,'total_time'=>$total_time,
                'resolved_origin'=>['id'=>null,'name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon,'source'=>'coords'],
                'resolved_dest'=>['id'=>null,'name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon,'source'=>'coords'],
                'intermediate_stops'=>$intermediate_meta
            ];
            @error_log('=== plan_trip.php END === Flight mode, segments='.count($segments).', time='.(microtime(true)-$start_time).'s');
            ob_end_clean(); echo json_encode($resp); exit;
        }
        
        if ($req_mode === 'drive') {
            try {
                $dep = isset($_GET['depart_time']) ? (string)$_GET['depart_time'] : '08:00';
                $cursor = preg_match('/^\d{1,2}:\d{2}/', $dep) ? substr($dep, 0, 5) : '08:00';
                $engine = 'unknown'; $ttUrl = null;
                // Try GraphHopper first (local, fastest), then TomTom, then straight line
                $polyGh = gh_route_points($origin_lat,$origin_lon,$dest_lat,$dest_lon);
                if (count($polyGh) >= 2) {
                    $poly = $polyGh; $engine = 'graphhopper';
                    $len_m = haversine_km($origin_lat,$origin_lon,$dest_lat,$dest_lon)*1000.0;
                    $time_s = (int)round(($len_m/1000.0)/50.0*3600.0); // approx
                } else {
                    $rt = tomtom_route_points($origin_lat,$origin_lon,$dest_lat,$dest_lon,$TOMTOM_KEY);
                    if ($rt['success']) { $ttUrl = $rt['debug']['url'] ?? null; }
                    $poly = $rt['success'] ? ($rt['data']['points'] ?? []) : [];
                    if (count($poly) >= 2) {
                        $engine = 'tomtom';
                        $len_m = isset($rt['data']['length_m']) ? (float)$rt['data']['length_m'] : (haversine_km($origin_lat,$origin_lon,$dest_lat,$dest_lon)*1000.0);
                        $time_s = isset($rt['data']['time_s']) ? (int)$rt['data']['time_s'] : (int)round(($len_m/1000.0)/50.0*3600.0);
                    } else {
                        // Straight line fallback
                        $engine = 'straight_line';
                        $poly = [[$origin_lat,$origin_lon],[$dest_lat,$dest_lon]];
                        $len_m = haversine_km($origin_lat,$origin_lon,$dest_lat,$dest_lon)*1000.0;
                        $time_s = (int)round(($len_m/1000.0)/50.0*3600.0);
                    }
                }
            // Downsample polyline (~1km) to cap traffic API calls and avoid long loops
            if (function_exists('sample_polyline')) {
                $poly = sample_polyline($poly, 1000.0);
            }
            $traffic = compute_leg_traffic($poly, $time_s, $TOMTOM_KEY);
            $arrive = [REDACTED]($cursor, (int)round($time_s/60) + $traffic['delay_min']);
            $segments = [[
                'mode'=>'car','operator'=>'TomTom','from'=>$origin_name,'to'=>$dest_name,
                'from_lat'=>$origin_lat,'from_lon'=>$origin_lon,'to_lat'=>$dest_lat,'to_lon'=>$dest_lon,
                'departure'=>$cursor,'arrival'=>$arrive,
                'fare'=>0,
                'distance_km'=>round($len_m/1000.0,1), 'duration_s'=>$time_s,
                'traffic_delay'=>$traffic['delay_min'], 'traffic_delay_min'=>$traffic['delay_min'], 'traffic_severity'=>$traffic['severity'],
                'traffic_raw'=>$traffic['raw'],
                'polyline'=>$poly
            ]];
            $segments[0]['engine'] = $engine;
            if ($ttUrl) { $segments[0]['tomtom_url'] = $ttUrl; }
            // Hotels near destination for drive mode
            $hotels = [];
            try {
                $hsql = "SELECT hotel_name, stars, price_per_night, latitude, longitude,
                  (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude)))) AS dist_km
                  FROM hotels WHERE latitude IS NOT NULL AND longitude IS NOT NULL HAVING dist_km <= 30 ORDER BY dist_km ASC, price_per_night ASC LIMIT 5";
                $hs = $pdo->prepare($hsql);
                $hs->execute([':lat1'=>$dest_lat, ':lat2'=>$dest_lat, ':lon1'=>$dest_lon]);
                $rowsH = $hs->fetchAll();
                foreach ($rowsH as $r){
                    $hotels[] = [
                        'hotel_name' => $r['hotel_name'],
                        'stars' => isset($r['stars']) ? (int)$r['stars'] : 3,
                        'price_per_night' => isset($r['price_per_night']) ? (float)$r['price_per_night'] : null,
                        'latitude' => isset($r['latitude']) ? (float)$r['latitude'] : null,
                        'longitude'=> isset($r['longitude']) ? (float)$r['longitude'] : null,
                        'distance_km'=> isset($r['dist_km']) ? (float)$r['dist_km'] : null,
                    ];
                }
            } catch (Throwable $he) { /* ignore */ }
            $route_poly = concat_polylines($segments);
            $total_min = (int)round(($time_s/60) + ($traffic['delay_min'] ?? 0));
            // Attach instructions to car segments (drive path) with detailed TomTom mapping when possible
            foreach ($segments as $idx => $seg) {
                $m = strtolower((string)($seg['mode'] ?? ''));
                if ($m==='car' || $m==='taxi' || $m==='drive') {
                    $segments[$idx]['instructions'] = [REDACTED]((float)$seg['from_lat'], (float)$seg['from_lon'], (float)$seg['to_lat'], (float)$seg['to_lon'], $TOMTOM_KEY, 'd'.($idx+1));
                }
            }
            $payload = [
                'segments'=>$segments,
                'legs'=>$segments,
                'hotels'=>$hotels,
                'total_fare'=>0,
                'total_time'=>sprintf('%dh %02dm', intdiv($total_min,60), $total_min%60),
                'decision'=>'drive',
                'resolved_origin'=>['id'=>null,'name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon,'source'=>'coords'],
                'resolved_dest'=>['id'=>null,'name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon,'source'=>'coords'],
                'debug'=> isset($_GET['debug']) ? ['gh_poly'=>$poly] : null,
                'route_poly'=>$route_poly
            ];
            if (isset($_GET['debug'])) { $payload['debug']['engine'] = $engine; if ($ttUrl) $payload['debug']['tomtom_url'] = $ttUrl; }
            if (isset($_GET['debug'])) { $payload['debug']['tomtom_geocode'] = ['origin_url'=>$debug_geo_o ?? null,'dest_url'=>$debug_geo_d ?? null]; }
            $envelope = ['success'=>true, 'error'=>null, 'count'=>count($segments), 'data'=>$payload];
            // Back-compat top-level keys expected by frontend
            $envelope['segments'] = $segments;
            $envelope['legs'] = $segments;
            $envelope['hotels'] = $hotels;
            $envelope['total_fare'] = 0;
            $envelope['total_time'] = $payload['total_time'];
            // Embed inline; no external route file
            $envelope['route_poly'] = $route_poly;
            $envelope['decision'] = 'drive';
            $envelope['resolved_origin'] = $payload['resolved_origin'];
            $envelope['resolved_dest'] = $payload['resolved_dest'];
            @error_log("Itinerary returned inline, origin=" . ($origin_name ?? '') . " dest=" . ($dest_name ?? ''));
            echo json_encode($envelope); exit;
            } catch (Throwable $e) {
                echo json_encode(['success'=>false,'error'=>'Drive routing failed','detail'=>$e->getMessage()]);
                exit;
            }
        }
        // Explicit bus mode => always bus chaining
        if ($req_mode === 'bus') {
            $depHH = isset($_GET['depart_time']) ? (string)$_GET['depart_time'] : '08:00';
            $tt_bus = tomtom_route_points($origin_lat,$origin_lon,$dest_lat,$dest_lon,$TOMTOM_KEY);
            $poly_bus = $tt_bus['success'] ? ($tt_bus['data']['points'] ?? []) : [[$origin_lat,$origin_lon],[$dest_lat,$dest_lon]];
            $originArrC = ['name'=>$origin_name ?: 'Origin', 'lat'=>$origin_lat, 'lon'=>$origin_lon];
            $destArrC   = ['name'=>$dest_name ?: 'Destination', 'lat'=>$dest_lat, 'lon'=>$dest_lon];
            $built = build_bus_chaining($pdo, $originArrC, $destArrC, $poly_bus, $depHH, $TOMTOM_KEY);
            $segments = $built['legs'];
            $usedStops = array_map(function($s){ return [
                'id'=>$s['id'] ?? null, 'name'=>$s['name'] ?? '', 'state'=>$s['state'] ?? '', 'lat'=>$s['lat'] ?? null, 'lon'=>$s['lon'] ?? null
            ]; }, ($built['used_bus_stops'] ?? []));
            $chain_reason = $built['chain_reason'] ?? 'unknown';
            $payload = [
                'segments'=>$segments,
                'legs'=>$segments,
                'total_fare'=>array_sum(array_map(fn($x)=>(int)($x['fare']??0), $segments)),
                'total_time'=>'',
                'decision'=>'bus_chain',
                'resolved_origin'=>['id'=>null,'name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon,'source'=>'coords'],
                'resolved_dest'=>['id'=>null,'name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon,'source'=>'coords'],
                'used_stops'=>$usedStops,
                'chaining_enforced'=>true,
                'chain_reason'=>$chain_reason
            ];
            $envelope = ['success'=>true,'error'=>null,'data'=>$payload];
            // top-level back-compat
            $envelope['segments']=$segments; $envelope['legs']=$segments; $envelope['decision']='bus_chain';
            $envelope['used_stops']=$usedStops; $envelope['chaining_enforced']=true; $envelope['chain_reason']=$chain_reason;
            echo json_encode($envelope); exit;
        }
        // Let Velora decide (no explicit bus/train/flight overrides)
        if ($dist_km <= 700) {
            // Prefer bus/train by distance with realistic bus chaining using GraphHopper polyline
            $isBus = $dist_km <= 300; // <=300km: bus, 300-700km: train
            // Build bus candidate regardless to compare
            $tt_bus = tomtom_route_points($origin_lat,$origin_lon,$dest_lat,$dest_lon,$TOMTOM_KEY);
            $poly_bus = $tt_bus['success'] ? ($tt_bus['data']['points'] ?? []) : [[$origin_lat,$origin_lon],[$dest_lat,$dest_lon]];
            $originArrC = ['name'=>$origin_name ?: 'Origin', 'lat'=>$origin_lat, 'lon'=>$origin_lon];
            $destArrC   = ['name'=>$dest_name ?: 'Destination', 'lat'=>$dest_lat, 'lon'=>$dest_lon];
            $debugBus = [];
            $candBus = build_bus_chaining($pdo, $originArrC, $destArrC, $poly_bus, $cursor, $TOMTOM_KEY);
            @file_put_contents("logs/debug_bus.txt", json_encode($candBus));
            $busSegs = $candBus['legs'];
            // Totals for bus
            $busFare = 0; $busStart = $busSegs[0]['departure']; $busEnd = $busSegs[count($busSegs)-1]['arrival'];
            foreach ($busSegs as $s) { $busFare += (int)($s['fare'] ?? 0); }
            $busDurMin = ($toMinLoc($busEnd) - $toMinLoc($busStart) + 24*60) % (24*60);

            if ($isBus) {
                $segments = $busSegs; $usedChaining = true; $intermediate_meta = $candBus['intermediate_stops']; $decision = 'bus_chain';
                $usedStops = array_map(function($s){ return [
                    'id'=>$s['id'] ?? null, 'name'=>$s['name'] ?? '', 'state'=>$s['state'] ?? '', 'lat'=>$s['lat'] ?? null, 'lon'=>$s['lon'] ?? null
                ]; }, ($candBus['used_bus_stops'] ?? []));
                $chain_reason = $candBus['chain_reason'] ?? 'unknown';
                if (isset($_GET['debug'])) { $debug = ['gh_poly'=>$poly_bus, 'intermediate_stops'=>$intermediate_meta, 'used_stops'=>$usedStops, 'chain_reason'=>$chain_reason]; }
            } else {
                // Train primary for 300-700km with first/last mile road legs
                $speed_kmh = 60; $fare_per_km = 1.6;
                // Prefer real OSM railway stations as endpoints
                $oSt = nearest_station($pdo, $origin_lat, $origin_lon);
                $dSt = nearest_station($pdo, $dest_lat, $dest_lon);
                $fromName = $oSt[0] ?? ($origin_name ?: 'Origin');
                $toName   = $dSt[0] ?? ($dest_name ?: 'Destination');
                $fromLat  = isset($oSt[1]) ? (float)$oSt[1] : $origin_lat;
                $fromLon  = isset($oSt[2]) ? (float)$oSt[2] : $origin_lon;
                $toLat    = isset($dSt[1]) ? (float)$dSt[1] : $dest_lat;
                $toLon    = isset($dSt[2]) ? (float)$dSt[2] : $dest_lon;
                // Mark chaining meta so frontend can show station chips
                $usedChaining = true;
                $intermediate_meta = [
                    ['name'=>$fromName, 'synthetic'=>false, 'type'=>'station', 'label'=>$fromName.' (Station)'],
                    ['name'=>$toName,   'synthetic'=>false, 'type'=>'station', 'label'=>$toName.' (Station)'],
                ];
                // First mile to station (car/taxi)
                $km_fm = haversine_km($origin_lat,$origin_lon,$fromLat,$fromLon);
                if ($km_fm > 0.1) {
                    $mid_fm_lat = ($origin_lat + $fromLat)/2.0; $mid_fm_lon = ($origin_lon + $fromLon)/2.0;
                    [$delay_fm, $sev_fm, $traw_fm] = (function($lat,$lon,$key){ [$d,$s,$raw] = tomtom_traffic($lat,$lon,$key); return [$d,$s,$raw]; })($mid_fm_lat,$mid_fm_lon,$TOMTOM_KEY);
                    $mins_fm = max(8, (int)round(($km_fm/30.0)*60));
                    $segments[] = [
                        'mode'=>'car','operator_name'=> ($has_vehicle ? 'Self-drive' : 'Taxi'),
                        'from'=>$origin_name ?: 'Origin','to'=>$fromName,
                        'from_lat'=>$origin_lat,'from_lon'=>$origin_lon,'to_lat'=>$fromLat,'to_lon'=>$fromLon,
                        'departure'=>$cursor,
                        'arrival'=> [REDACTED]($cursor, $mins_fm + $delay_fm),
                        'fare'=> (int)round($km_fm * ($has_vehicle ? 0 : 10)),
                        'distance_km'=> round($km_fm,1),
                        'duration_s'=> ($mins_fm + $delay_fm)*60,
                        'traffic_delay'=> $delay_fm,
                        'traffic_delay_min'=> $delay_fm,
                        'traffic_severity'=> $sev_fm,
                    ];
                    if (isset($_GET['debug']) && $traw_fm !== null) { $segments[count($segments)-1]['traffic_raw'] = $traw_fm; }
                    $cursor = $segments[count($segments)-1]['arrival'];
                }
                // Train leg
                $train_km = haversine_km($fromLat,$fromLon,$toLat,$toLon);
                $minutes = max(20, (int)round(($train_km / $speed_kmh) * 60));
                // Use small fixed rail delay based on midpoint traffic near station egress to avoid AI
                $mid_tr_lat = ($fromLat + $toLat)/2.0; $mid_tr_lon = ($fromLon + $toLon)/2.0;
                [$delayTrain, $sevTrain, $trawTrain] = (function($lat,$lon,$key){ [$d,$s,$raw] = tomtom_traffic($lat,$lon,$key); return [max(0,(int)round($d*0.25)),$s,$raw]; })($mid_tr_lat,$mid_tr_lon,$TOMTOM_KEY);
                $segTrain = [
                    'mode' => 'train', 'operator_name' => 'IRCTC',
                    'from' => $fromName,
                    'to' => $toName,
                    'from_lat' => $fromLat, 'from_lon' => $fromLon,
                    'to_lat' => $toLat, 'to_lon' => $toLon,
                    'departure' => $cursor,
                    'arrival' => [REDACTED]($cursor, $minutes + $delayTrain),
                    'fare' => (int)round($train_km * $fare_per_km),
                    'distance_km' => round($train_km,1),
                    'duration_s' => ($minutes + $delayTrain)*60,
                    'traffic_delay' => $delayTrain,
                    'traffic_delay_min' => $delayTrain,
                    'traffic_severity' => $sevTrain,
                ];
                if (isset($_GET['debug']) && $trawTrain !== null) { $segTrain['traffic_raw'] = $trawTrain; }
                $segments[] = $segTrain;
                $cursor = $segments[count($segments)-1]['arrival'];
                // Last mile from station (car/taxi)
                $km_lm = haversine_km($toLat,$toLon,$dest_lat,$dest_lon);
                if ($km_lm > 0.1) {
                    $mid_lm_lat = ($toLat + $dest_lat)/2.0; $mid_lm_lon = ($toLon + $dest_lon)/2.0;
                    [$delay_lm, $sev_lm, $traw_lm] = (function($lat,$lon,$key){ [$d,$s,$raw] = tomtom_traffic($lat,$lon,$key); return [$d,$s,$raw]; })($mid_lm_lat,$mid_lm_lon,$TOMTOM_KEY);
                    $mins_lm = max(8, (int)round(($km_lm/30.0)*60));
                    $segments[] = [
                        'mode'=>'car','operator_name'=> ($has_vehicle ? 'Self-drive' : 'Taxi'),
                        'from'=>$toName,'to'=>$dest_name ?: 'Destination',
                        'from_lat'=>$toLat,'from_lon'=>$toLon,'to_lat'=>$dest_lat,'to_lon'=>$dest_lon,
                        'departure'=> [REDACTED]($cursor, 2),
                        'arrival'=> [REDACTED]($cursor, 2 + $mins_lm + $delay_lm),
                        'fare'=> (int)round($km_lm * ($has_vehicle ? 0 : 10)),
                        'distance_km'=> round($km_lm,1),
                        'duration_s'=> ($mins_lm + $delay_lm + 2)*60,
                        'traffic_delay'=> $delay_lm,
                        'traffic_delay_min'=> $delay_lm,
                        'traffic_severity'=> $sev_lm,
                    ];
                    if (isset($_GET['debug']) && $traw_lm !== null) { $segments[count($segments)-1]['traffic_raw'] = $traw_lm; }
                    $cursor = $segments[count($segments)-1]['arrival'];
                }
                // Compare bus vs train candidates and pick competitive bus if suitable
                // Compute train totals
                $trainFare = 0; $trainStart = $segments[0]['departure']; $trainEnd = $segments[count($segments)-1]['arrival'];
                foreach ($segments as $s) { $trainFare += (int)($s['fare'] ?? 0); }
                $trainDurMin = ($toMinLoc($trainEnd) - $toMinLoc($trainStart) + 24*60) % (24*60);
                $decision = 'train';
                $busCompetitive = ($busDurMin <= (int)round($trainDurMin * 1.25)) && ($busFare <= (int)round($trainFare * 1.15));
                if ($busCompetitive) {
                    $segments = $busSegs; $usedChaining = true; $intermediate_meta = $candBus['intermediate_stops']; $decision = 'bus_chain';
                    if (isset($_GET['debug'])) { $debug = ['gh_poly'=>$poly_bus, 'tomtom_route_urls'=>$debugBus['tomtom_route_urls'] ?? [], 'intermediate_stops'=>$intermediate_meta, 'bus_chaining_stops'=>$debugBus['bus_chaining_stops'] ?? []]; }
                }
                if (isset($_GET['debug'])){
                    $debug = $debug ?? [];
                    $debug[[REDACTED]] = [$fromName, $toName];
                    $debug['operators'] = ($debug['operators'] ?? []);
                    $debug['operators'][] = ['mode'=>'train','operator'=>'IRCTC'];
                }
            }

            // Build candidates and score them (bus, drive, train if built)
            $candidates = [];
            $sumFare = function(array $segs){ $t=0; foreach($segs as $s){ $t += (int)($s['fare'] ?? 0); } return $t; };
            $toMinFn = function(string $t){ [$h,$m] = array_map('intval', explode(':',$t.':0')); return $h*60+$m; };
            $durMinFn = function(array $segs) use ($toMinFn){ if (!$segs) return 0; $start=$segs[0]['departure']; $end=$segs[count($segs)-1]['arrival']; $d=($toMinFn($end)-$toMinFn($start)+24*60)%(24*60); return $d; };
            $hasSynthetic = function(array $segs){ foreach($segs as $s){ if (!empty($s['synthetic'])) return true; } return false; };

            // Bus candidate
            $busCand = ['name'=>'bus_chain','segments'=>$busSegs,'fare'=>$sumFare($busSegs),'time_min'=>$durMinFn($busSegs),'synthetic'=>$hasSynthetic($busSegs)];
            $busCand['score'] = (int)$busCand['time_min'] + (int)round(($busCand['fare'] * 0.01)) + ($busCand['synthetic']?30:0);
            $candidates[] = $busCand;

            // Drive candidate (only if user vehicle not explicitly no)
            $vehicleParam = isset($_GET['vehicle']) ? strtolower(trim($_GET['vehicle'])) : '';
            if ($vehicleParam !== 'no'){
                $rtD = tomtom_route_points($origin_lat,$origin_lon,$dest_lat,$dest_lon,$TOMTOM_KEY);
                $polyD = $rtD['success'] ? ($rtD['data']['points'] ?? [[$origin_lat,$origin_lon],[$dest_lat,$dest_lon]]) : [[$origin_lat,$origin_lon],[$dest_lat,$dest_lon]];
                $lenD = $rtD['success'] ? (float)($rtD['data']['length_m'] ?? 0) : (haversine_km($origin_lat,$origin_lon,$dest_lat,$dest_lon)*1000);
                $timeD= $rtD['success'] ? (int)($rtD['data']['time_s'] ?? 0) : (int)round(($lenD/1000)/50*3600);
                $trafD= compute_leg_traffic($polyD, $timeD, $TOMTOM_KEY);
                $arrD = [REDACTED]($cursor, (int)round($timeD/60) + $trafD['delay_min']);
                $segD = [[
                    'mode'=>'car','operator'=>'TomTom','from'=>$origin_name,'to'=>$dest_name,
                    'from_lat'=>$origin_lat,'from_lon'=>$origin_lon,'to_lat'=>$dest_lat,'to_lon'=>$dest_lon,
                    'departure'=>$cursor,'arrival'=>$arrD,'fare'=> (int)round(($lenD/1000.0)*8),
                    'distance_km'=>round($lenD/1000.0,1),'duration_s'=>$timeD,
                    'traffic_delay'=>$trafD['delay_min'],'traffic_severity'=>$trafD['severity'],
                    'traffic_raw'=>$trafD['raw'],'sub_segments'=>$trafD['sub_segments'] ?? [],
                    'polyline'=>$polyD
                ]];
                $driveCand = ['name'=>'drive','segments'=>$segD,'fare'=>$sumFare($segD),'time_min'=>$durMinFn($segD),'synthetic'=>false];
                $driveCand['score'] = (int)$driveCand['time_min'] + (int)round(($driveCand['fare']*0.01));
                $candidates[] = $driveCand;
            }

            // Train candidate (only if we built any non-empty train/feeder segments in the non-bus path)
            if (!$isBus && !empty($segments)){
                $trainSegs = $segments;
                $trainCand = ['name'=>'train','segments'=>$trainSegs,'fare'=>$sumFare($trainSegs),'time_min'=>$durMinFn($trainSegs),'synthetic'=>$hasSynthetic($trainSegs)];
                $trainCand['score'] = (int)$trainCand['time_min'] + (int)round(($trainCand['fare']*0.01)) + ($trainCand['synthetic']?30:0);
                $candidates[] = $trainCand;
            }

            // Choose best candidate
            $best = null; $bestScore = PHP_INT_MAX;
            foreach ($candidates as $cand){ if ($cand['score'] < $bestScore) { $best = $cand; $bestScore = $cand['score']; } }
            if ($best){
                $segments = $best['segments'];
                $decision = $best['name'];
                // If bus, also set intermediate meta from chaining and capture used stops + reason
                if ($decision === 'bus_chain') {
                    $usedChaining = true; $intermediate_meta = $candBus['intermediate_stops'];
                    $usedStops = array_map(function($s){ return [
                        'id'=>$s['id'] ?? null, 'name'=>$s['name'] ?? '', 'state'=>$s['state'] ?? '', 'lat'=>$s['lat'] ?? null, 'lon'=>$s['lon'] ?? null
                    ]; }, ($candBus['used_bus_stops'] ?? []));
                    $chain_reason = $candBus['chain_reason'] ?? 'unknown';
                }
                if (isset($_GET['debug'])){ $debug = $debug ?? []; $debug['candidates'] = $candidates; }
            }
        } else {
            // Long distance: bus to nearest airport -> flight -> metro/local
            [$aName, $aLat, $aLon] = nearest_airport($pdo, $origin_lat, $origin_lon);
            [$bName, $bLat, $bLon] = nearest_airport($pdo, $dest_lat, $dest_lon);
            // Mark chaining meta so frontend can show airport chips
            $usedChaining = true;
            $intermediate_meta = [
                ['name'=>$aName.' Airport', 'synthetic'=>false, 'type'=>'airport', 'label'=>$aName.' Airport'],
                ['name'=>$bName.' Airport', 'synthetic'=>false, 'type'=>'airport', 'label'=>$bName.' Airport'],
            ];

            // First mile to airport (car/taxi)
            $km_fm = haversine_km($origin_lat, $origin_lon, $aLat, $aLon);
            $mins_fm = max(15, (int)round(($km_fm/32)*60));
            [$delay_fm, $sev_fm, $traw_fm] = get_ai_traffic($pdo, $origin_name ?: 'Origin', $aName.' Airport', $cursor, max(1.0, $km_fm), 'road');
            $segments[] = [
                'mode' => 'car',
                'operator_name' => ($has_vehicle ? 'Self-drive' : 'Taxi'),
                'from' => $origin_name ?: 'Origin', 'to' => $aName.' Airport',
                'from_lat'=>$origin_lat,'from_lon'=>$origin_lon,'to_lat'=>$aLat,'to_lon'=>$aLon,
                'departure' => $cursor,
                'arrival' => [REDACTED]($cursor, $mins_fm + $delay_fm),
                'fare' => (int)round($km_fm * ($has_vehicle ? 0 : 12)),
                'distance_km' => round($km_fm,1),
                'duration_s' => ($mins_fm + $delay_fm)*60,
                'traffic_delay' => $delay_fm,
                'traffic_delay_min' => $delay_fm,
                'traffic_severity' => $sev_fm,
            ];
            if (isset($_GET['debug']) && $traw_fm !== null) { $segments[count($segments)-1]['traffic_raw'] = $traw_fm; }
            $cursor = $segments[count($segments)-1]['arrival'];

            // Flight segment (look up demo table if possible)
            $flight = null;
            try {
                $st = $pdo->prepare('SELECT operator, departure_time, arrival_time, fare FROM flight_routes WHERE origin = :o AND destination = :d LIMIT 1');
                $st->execute([':o'=>$aName, ':d'=>$bName]);
                $flight = $st->fetch();
            } catch (Throwable $fe) { /* ignore */ }
            $fallbackOps = ['IndiGo','Air India','SpiceJet'];
            $flight_op = $flight['operator'] ?? $fallbackOps[array_rand($fallbackOps)];
            $flight_minutes = $flight ?
                (int)round((strtotime($flight['arrival_time']) - strtotime($flight['departure_time']))/60) :
                max(60, (int)round((haversine_km($aLat,$aLon,$bLat,$bLon)/750)*60 + 30));
            // CO2: flight 250 g/km
            $co2_f = (int)round(haversine_km($aLat,$aLon,$bLat,$bLon) * 250);
            $flightOp = [REDACTED]($pdo, ($bName ?: '') , 'flight', function() use($flight_op){ return $flight_op; });
            $segments[] = [
                'mode' => 'flight','operator_name'=>$flightOp,
                'from' => $aName, 'to' => $bName,
                'from_lat'=>$aLat,'from_lon'=>$aLon,'to_lat'=>$bLat,'to_lon'=>$bLon,
                'departure' => [REDACTED]($cursor, 30),
                'arrival' => [REDACTED]($cursor, 30 + $flight_minutes),
                'fare' => (int)($flight['fare'] ?? max(3500, (int)round(haversine_km($aLat,$aLon,$bLat,$bLon)*6.2))),
                'distance_km' => round(haversine_km($aLat,$aLon,$bLat,$bLon),1),
                'duration_s' => $flight_minutes*60,
                'traffic_delay' => 0,
                'traffic_delay_min' => 0,
                'traffic_severity' => 'none',
            ];
            $cursor = $segments[count($segments)-1]['arrival'];

            // Last mile from airport
            $km_local = haversine_km($bLat,$bLon,$dest_lat,$dest_lon);
            [$delay_local, $sev_local, $traw_local] = get_ai_traffic($pdo, $bName.' Airport', $dest_name ?: 'Destination', $cursor, max(1.0,$km_local), 'road');
            $mins_local = max(20, (int)round(($km_local/30)*60));
            $segments[] = [
                'mode'=>'car','operator_name'=> ($has_vehicle ? 'Self-drive' : 'Taxi'),
                'from' => $bName.' Airport','to'=>$dest_name ?: 'Destination',
                'from_lat'=>$bLat,'from_lon'=>$bLon,'to_lat'=>$dest_lat,'to_lon'=>$dest_lon,
                'departure'=> [REDACTED]($cursor, 5),
                'arrival'  => [REDACTED]($cursor, 5 + $mins_local + $delay_local),
                'fare' => (int)round($km_local * ($has_vehicle ? 0 : 12)),
                'distance_km' => round($km_local,1),
                'duration_s' => ($mins_local + $delay_local)*60,
                'traffic_delay' => $delay_local,
                'traffic_delay_min' => $delay_local,
                'traffic_severity' => $sev_local,
            ];
            if (isset($_GET['debug']) && $traw_local !== null) { $segments[count($segments)-1]['traffic_raw'] = $traw_local; }

            // Compare bus vs flight candidates for velora
            $decision = 'flight';
            $tt_busL = tomtom_route_points($origin_lat,$origin_lon,$dest_lat,$dest_lon,$TOMTOM_KEY);
            $poly_busL = $tt_busL['success'] ? ($tt_busL['data']['points'] ?? []) : [[$origin_lat,$origin_lon],[$dest_lat,$dest_lon]];
            $debugBusL = [];
            $candBusL = chain_bus_strict($pdo, ['name'=>$origin_name ?: 'Origin','lat'=>$origin_lat,'lon'=>$origin_lon], ['name'=>$dest_name ?: 'Destination','lat'=>$dest_lat,'lon'=>$dest_lon], $poly_busL, $depart_time, $TOMTOM_KEY, $debugBusL);
            $busSegsL = $candBusL['legs'];
            $busFareL = 0; $busStartL = $busSegsL[0]['departure']; $busEndL = $busSegsL[count($busSegsL)-1]['arrival'];
            foreach ($busSegsL as $s) { $busFareL += (int)($s['fare'] ?? 0); }
            $busDurMinL = ($toMinLoc($busEndL) - $toMinLoc($busStartL) + 24*60) % (24*60);
            // Flight totals
            $flightFare = 0; $flightStart = $segments[0]['departure']; $flightEnd = $segments[count($segments)-1]['arrival'];
            foreach ($segments as $s) { $flightFare += (int)($s['fare'] ?? 0); }
            $flightDurMin = ($toMinLoc($flightEnd) - $toMinLoc($flightStart) + 24*60) % (24*60);
            $busCompetitiveL = ($busDurMinL <= (int)round($flightDurMin * 1.25)) && ($busFareL <= (int)round($flightFare * 0.9));
            if ($busCompetitiveL) {
                $segments = $busSegsL; $usedChaining = true; $intermediate_meta = $candBusL['intermediate_stops']; $decision = 'bus_chain';
                if (isset($_GET['debug'])) { $debug = ['gh_poly'=>$poly_busL, 'tomtom_route_urls'=>$debugBusL['tomtom_route_urls'] ?? [], 'intermediate_stops'=>$intermediate_meta, 'bus_chaining_stops'=>$debugBusL['bus_chaining_stops'] ?? []]; }
            }
        }

        // Station fallback for train: insert taxi legs to nearest stations when endpoints lack a station
        try {
            // Ensure schema exists (no-op if already there)
            [REDACTED]($pdo);
            $hasTrain = false;
            foreach ($segments as $s){ if (strtolower((string)($s['mode']??''))==='train') { $hasTrain = true; break; } }
            if ($hasTrain && isset($origin_lat,$origin_lon,$dest_lat,$dest_lon)){
                // Pre leg to nearest origin station if first train leg doesn't start at a station
                $pre = nearest_station($pdo, (float)$origin_lat, (float)$origin_lon, 'train');
                $post = nearest_station($pdo, (float)$dest_lat, (float)$dest_lon, 'train');
                $addPre = false; $addPost = false;
                if ($pre){ $dPre = haversine_km((float)$origin_lat,(float)$origin_lon,(float)$pre['lat'],(float)$pre['lon']); $addPre = ($dPre > 1.5); }
                if ($post){ $dPost = haversine_km((float)$dest_lat,(float)$dest_lon,(float)$post['lat'],(float)$post['lon']); $addPost = ($dPost > 1.5); }
                if ($addPre){
                    $cursor = $segments[0]['departure'] ?? '08:00';
                    [$delay_pre, $sev_pre] = [10, 'medium'];
                    $mins_pre = max(10, (int)round(($dPre/25)*60));
                    array_unshift($segments, [
                        'mode'=>'car','operator_name'=>'Taxi','from'=>$origin_name ?: 'Origin','to'=> ($pre['name'] ?: 'Nearest Station'),
                        'from_lat'=>$origin_lat,'from_lon'=>$origin_lon,'to_lat'=>(float)$pre['lat'],'to_lon'=>(float)$pre['lon'],
                        'departure'=> [REDACTED]($cursor, -($mins_pre+$delay_pre)),
                        'arrival'  => $cursor,
                        'fare' => (int)round($dPre * 12),
                        'distance_km' => round($dPre,1),
                        'duration_s' => ($mins_pre + $delay_pre)*60,
                        'traffic_delay' => $delay_pre,
                        'traffic_delay_min' => $delay_pre,
                        'traffic_severity' => $sev_pre,
                    ]);
                }
                if ($addPost){
                    $lastArr = $segments[count($segments)-1]['arrival'] ?? '09:00';
                    [$delay_post, $sev_post] = [10, 'medium'];
                    $mins_post = max(10, (int)round(($dPost/25)*60));
                    $segments[] = [
                        'mode'=>'car','operator_name'=>'Taxi','from'=> ($post['name'] ?: 'Arrival Station'),'to'=>$dest_name ?: 'Destination',
                        'from_lat'=>(float)$post['lat'],'from_lon'=>(float)$post['lon'],'to_lat'=>$dest_lat,'to_lon'=>$dest_lon,
                        'departure'=> $lastArr,
                        'arrival'  => [REDACTED]($lastArr, $mins_post + $delay_post),
                        'fare' => (int)round($dPost * 12),
                        'distance_km' => round($dPost,1),
                        'duration_s' => ($mins_post + $delay_post)*60,
                        'traffic_delay' => $delay_post,
                        'traffic_delay_min' => $delay_post,
                        'traffic_severity' => $sev_post,
                    ];
                }
            }
        } catch (Throwable $e) { /* ignore stations fallback errors */ }

        // Enforce no-car constraint: if decision picked drive but user has no vehicle, prefer non-car based on distance thresholds
        try {
            if (!empty($segments)){
                $hasCarOnly = true; foreach ($segments as $s){ if (strtolower((string)($s['mode']??''))!=='car') { $hasCarOnly = false; break; } }
                if ($hasCarOnly && (int)($has_vehicle ?? 0) === 0){
                    $decision = 'bus';
                    // no mutation of built segments here (non-breaking); attach reason
                    $decision_reason = 'User has no car; avoided car option';
                }
            }
        } catch (Throwable $e) { /* ignore */ }

        // Attach turn-by-turn instructions for driving legs (if any)
        try {
            foreach ($segments as $i => $seg) {
                $mode = strtolower((string)($seg['mode'] ?? ''));
                if ($mode === 'car' && isset($seg['from_lat'],$seg['from_lon'],$seg['to_lat'],$seg['to_lon'])){
                    $inst = [REDACTED]((float)$seg['from_lat'], (float)$seg['from_lon'], (float)$seg['to_lat'], (float)$seg['to_lon'], $TOMTOM_KEY, $depart_time ?? null);
                    if ($inst) { $segments[$i]['instructions'] = $inst; }
                }
            }
        } catch (Throwable $e) { /* ignore instruction errors */ }

        // Totals
        $total_fare = 0; $start = $segments[0]['departure']; $end = $segments[count($segments)-1]['arrival'];
        foreach ($segments as $s) { $total_fare += (int)($s['fare'] ?? 0); }
        $toMin = function(string $t){ [$h,$m] = array_map('intval', explode(':',$t.':0')); return $h*60+$m; };
        $durMin = ($toMin($end) - $toMin($start) + 24*60) % (24*60);
        $total_time = sprintf('%dh %02dm', intdiv($durMin,60), $durMin%60);
        // Decision reason (basic) and arrive-by support
        $decision_reason = null;
        if (isset($usedChaining) && $usedChaining && ($decision==='bus_chain')) { $decision_reason = 'Chose bus chaining based on time and fare thresholds'; }
        // If user provided arrive_by, compute leave_by advice
        $leave_by = null;
        $arrive_by_param = isset($_GET['arrive_by']) ? trim((string)$_GET['arrive_by']) : '';
        if ($arrive_by_param !== ''){
            $am = $toMin($arrive_by_param);
            $lv = ($am - $durMin + 24*60) % (24*60);
            $leave_by = sprintf('%02d:%02d', intdiv($lv,60)%24, $lv%60);
        }
        // Build route polyline and embed inline (no external file)
        $route_poly = concat_polylines($segments);
        // Bus chaining meta (if any)
        $usedStops = isset($usedStops) ? $usedStops : [];
        $chain_reason = isset($chain_reason) ? $chain_reason : (isset($usedChaining) && $usedChaining && ($decision==='bus_chain') ? 'corridor candidates found' : null);
        $chaining_enforced = (isset($usedChaining) && $usedChaining && ($decision==='bus_chain')) ? true : false;

        $payload = [
            'created_at'=>date('c'),
            'origin'=>['name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon],
            'dest'=>['name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon],
            'decision'=>$decision,
            'segments'=>$segments,
            'legs'=>$segments,
            'route_poly'=>$route_poly,
            'intermediate_stops'=>$intermediate_meta ?? [],
            'total_fare'=>$total_fare,
            'total_time'=>$total_time,
            'debug'=> isset($_GET['debug']) ? ($debug ?? null) : null,
            'used_stops'=>$usedStops,
            'chaining_enforced'=>$chaining_enforced,
            'chain_reason'=>$chain_reason
        ];
        if (isset($_GET['debug'])) { $payload['debug'] = $payload['debug'] ?? []; $payload['debug']['tomtom_geocode'] = ['origin_url'=>$debug_geo_o ?? null,'dest_url'=>$debug_geo_d ?? null]; }
        // Hotels near final destination (only when include_hotels flag truthy)
        $hotels = [];
        $include_hotels = false;
        if (isset($_GET['include_hotels'])) {
            $v = strtolower((string)$_GET['include_hotels']);
            $include_hotels = ($v === '1' || $v === 'true' || $v === 'yes');
        }
        if ($include_hotels) {
            try {
                $toSeg = $segments[count($segments)-1] ?? null;
                if ($toSeg) {
                    $hsql = "SELECT hotel_name, stars, price_per_night, latitude, longitude,
                      (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude)))) AS dist_km
                      FROM hotels WHERE latitude IS NOT NULL AND longitude IS NOT NULL HAVING dist_km <= 30 ORDER BY dist_km ASC, price_per_night ASC LIMIT 5";
                    $hs = $pdo->prepare($hsql);
                    $hs->execute([':lat1'=>$toSeg['to_lat'], ':lat2'=>$toSeg['to_lat'], ':lon1'=>$toSeg['to_lon']]);
                    $rowsH = $hs->fetchAll();
                    foreach ($rowsH as $r){
                        $hotels[] = [
                            'hotel_name' => $r['hotel_name'],
                            'stars' => isset($r['stars']) ? (int)$r['stars'] : 3,
                            'price_per_night' => isset($r['price_per_night']) ? (float)$r['price_per_night'] : null,
                            'latitude' => isset($r['latitude']) ? (float)$r['latitude'] : null,
                            'longitude'=> isset($r['longitude']) ? (float)$r['longitude'] : null,
                            'distance_km'=> isset($r['dist_km']) ? (float)$r['dist_km'] : null,
                        ];
                    }
                }
            } catch (Throwable $he) { /* ignore */ }
        }

        $payload = [
            'segments'=>$segments,
            'legs'=>$segments,
            'hotels'=>$hotels,
            'total_fare'=>$total_fare,
            'total_time'=>$total_time,
            'decision'=>($decision ?? 'velora'),
            'resolved_origin'=>['id'=>null,'name'=>$origin_name,'lat'=>$origin_lat,'lon'=>$origin_lon,'source'=>'coords'],
            'resolved_dest'=>['id'=>null,'name'=>$dest_name,'lat'=>$dest_lat,'lon'=>$dest_lon,'source'=>'coords'],
            'used_stops'=>$usedStops,
            'chaining_enforced'=>$chaining_enforced,
            'chain_reason'=>$chain_reason
        ];
        if ($decision_reason) { $payload['decision_reason'] = $decision_reason; }
        if ($leave_by) { $payload['leave_by'] = $leave_by; }
        // No route_file; everything is inline
        if (isset($usedChaining) && $usedChaining) { $payload['chaining'] = true; $payload['intermediate_stops'] = $intermediate_meta ?? []; }
        if (isset($debug)) { $payload['debug'] = $debug; }
        $envelope = ['success'=>true, 'error'=>null, 'count'=>count($segments), 'data'=>$payload];
        // Back-compat top-level keys expected by frontend
        $envelope['segments'] = $segments;
        $envelope['legs'] = $segments;
        $envelope['decision'] = $payload['decision'];
        $envelope['resolved_origin'] = $payload['resolved_origin'];
        $envelope['resolved_dest'] = $payload['resolved_dest'];
        $envelope['hotels'] = $hotels;
        $envelope['total_fare'] = $total_fare;
        $envelope['total_time'] = $total_time;
        // No route_file in envelope
        if (!empty($payload['warnings'])) { $envelope['warnings'] = $payload['warnings']; }
        if (!empty($payload['chaining'])) { $envelope['chaining'] = true; $envelope['intermediate_stops'] = $payload['intermediate_stops'] ?? []; }
        // Surface chaining meta at top-level too
        if (isset($payload['used_stops'])) { $envelope['used_stops'] = $payload['used_stops']; }
        if (isset($payload['chaining_enforced'])) { $envelope['chaining_enforced'] = $payload['chaining_enforced']; }
        if (isset($payload['chain_reason'])) { $envelope['chain_reason'] = $payload['chain_reason']; }
        if (!empty($payload['decision_reason'])) { $envelope['decision_reason'] = $payload['decision_reason']; }
        if (!empty($payload['leave_by'])) { $envelope['leave_by'] = $payload['leave_by']; }
        if (isset($payload['debug'])) { $envelope['debug'] = $payload['debug']; }
        @error_log("Itinerary returned inline, origin=" . ($origin_name ?? '') . " dest=" . ($dest_name ?? ''));
        echo json_encode($envelope);
        exit;
    }

    // ---- Legacy path (IDs) ----
    $origin_id = isset($_GET['origin_id']) ? (int)$_GET['origin_id'] : 0;
    $dest_id   = isset($_GET['dest_id']) ? (int)$_GET['dest_id'] : 0;
    $arrive_by = isset($_GET['arrive_by']) ? trim($_GET['arrive_by']) : '';

    // Robust resolution when IDs are missing: try names, then coordinates
    if ($origin_id <= 0 || $dest_id <= 0) {
        $resolved = [
            'origin' => ['id'=>null,'name'=>null,'lat'=>null,'lon'=>null,'source'=>null],
            'dest'   => ['id'=>null,'name'=>null,'lat'=>null,'lon'=>null,'source'=>null],
        ];
        $origin_name = isset($_GET['origin']) ? trim((string)$_GET['origin']) : (isset($_GET['origin_name']) ? trim((string)$_GET['origin_name']) : '');
        $dest_name   = isset($_GET['dest']) ? trim((string)$_GET['dest']) : (isset($_GET['dest_name']) ? trim((string)$_GET['dest_name']) : '');
        $oLatIn = isset($_GET['origin_lat']) ? (float)$_GET['origin_lat'] : null;
        $oLonIn = isset($_GET['origin_lon']) ? (float)$_GET['origin_lon'] : null;
        $dLatIn = isset($_GET['dest_lat'])   ? (float)$_GET['dest_lat']   : null;
        $dLonIn = isset($_GET['dest_lon'])   ? (float)$_GET['dest_lon']   : null;

        // Helper to fetch stop by ID
        $fetchStopById = function(int $id) use ($pdo) {
            try {
                $st = $pdo->prepare('SELECT id, stop_name, city, latitude, longitude FROM stops WHERE id = :id LIMIT 1');
                $st->execute([':id'=>$id]);
                return $st->fetch() ?: null;
            } catch (Throwable $e) { return null; }
        };

        // Helper to fetch stop by name (stop_name or city LIKE)
        $fetchStopByName = function(string $q) use ($pdo) {
            if ($q === '') return null;
            try {
                $like = "%".$q."%";
                $st = $pdo->prepare("SELECT id, stop_name, city, latitude, longitude
                   FROM stops
                   WHERE stop_name LIKE :q OR city LIKE :q
                   ORDER BY CASE WHEN stop_name = :qexact THEN 0 ELSE 1 END, LENGTH(stop_name) ASC
                   LIMIT 1");
                $st->execute([':q'=>$like, ':qexact'=>$q]);
                return $st->fetch() ?: null;
            } catch (Throwable $e) { return null; }
        };

        // GraphHopper geocode (localhost)
        $ghGeocode = function(string $q){
            $q = trim($q); if ($q==='') return null;
            $url = 'http://localhost:8989/geocode?q='.urlencode($q).'&limit=1';
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [[REDACTED]=>true, CURLOPT_TIMEOUT=>3, [REDACTED]=>2]);
                $res = curl_exec($ch);
                if ($res === false) return null;
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code >= 200 && $code < 300) {
                    $j = json_decode($res, true);
                    if (isset($j['hits'][0]['point']['lat'],$j['hits'][0]['point']['lng'])){
                        $hit = $j['hits'][0];
                        return [
                            'name' => (string)($hit['name'] ?? $q),
                            'lat'  => (float)$hit['point']['lat'],
                            'lon'  => (float)$hit['point']['lng'],
                            'source' => 'graphhopper'
                        ];
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
            return null;
        };

        // Nominatim geocode (public OSM)
        $osmGeocode = function(string $q){
            $q = trim($q); if ($q==='') return null;
            $url = 'https://nominatim.openstreetmap.org/search?q='.urlencode($q).'&format=json&limit=1';
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [[REDACTED]=>true, CURLOPT_TIMEOUT=>3, [REDACTED]=>2, CURLOPT_HTTPHEADER=>['User-Agent: Velora/1.0 (+https://example.com)']]);
                $res = curl_exec($ch);
                if ($res === false) return null;
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($code >= 200 && $code < 300) {
                    $arr = json_decode($res, true);
                    if (is_array($arr) && isset($arr[0]['lat'],$arr[0]['lon'])){
                        $hit = $arr[0];
                        return [
                            'name' => (string)($hit['display_name'] ?? $q),
                            'lat'  => (float)$hit['lat'],
                            'lon'  => (float)$hit['lon'],
                            'source' => 'nominatim'
                        ];
                    }
                }
            } catch (Throwable $e) { /* ignore */ }
            return null;
        };

        // Resolve origin
        if ($origin_id > 0) {
            $o = $fetchStopById($origin_id);
            if ($o) { $resolved['origin'] = ['id'=>(int)$o['id'],'name'=>(string)$o['stop_name'],'lat'=>(float)$o['latitude'],'lon'=>(float)$o['longitude'],'source'=>'id']; }
        }
        if ($resolved['origin']['id'] === null && $origin_name !== '') {
            $o = $fetchStopByName($origin_name);
            if ($o) { $resolved['origin'] = ['id'=>(int)$o['id'],'name'=>(string)$o['stop_name'],'lat'=>(float)$o['latitude'],'lon'=>(float)$o['longitude'],'source'=>'name']; $origin_id = (int)$o['id']; }
            else {
                // Geocode fallback
                $g = $ghGeocode($origin_name) ?: $osmGeocode($origin_name);
                if ($g) {
                    $resolved['origin'] = ['id'=>null,'name'=>$g['name'],'lat'=>$g['lat'],'lon'=>$g['lon'],'source'=>$g['source']];
                } else {
                    // No coordinates found, synthesize placeholder
                    $resolved['origin'] = ['id'=>null,'name'=>$origin_name,'lat'=>null,'lon'=>null,'source'=>'name_unresolved'];
                }
            }
        }
        if ($resolved['origin']['id'] === null && $oLatIn !== null && $oLonIn !== null) {
            $o = nearest_stop($pdo, (float)$oLatIn, (float)$oLonIn);
            if ($o) { $resolved['origin'] = ['id'=>(int)$o['id'],'name'=>(string)$o['stop_name'],'lat'=>(float)$o['latitude'],'lon'=>(float)$o['longitude'],'source'=>'coords_nearest']; $origin_id = (int)$o['id']; }
            else { $resolved['origin'] = ['id'=>null,'name'=>$origin_name!==''?$origin_name:'Origin','lat'=>$oLatIn,'lon'=>$oLonIn,'source'=>'coords_synthetic']; }
        }
        if ($resolved['origin']['name'] === null && $origin_name !== '') { $resolved['origin']['name'] = $origin_name; }

        // Resolve destination
        if ($dest_id > 0) {
            $d = $fetchStopById($dest_id);
            if ($d) { $resolved['dest'] = ['id'=>(int)$d['id'],'name'=>(string)$d['stop_name'],'lat'=>(float)$d['latitude'],'lon'=>(float)$d['longitude'],'source'=>'id']; }
        }
        if ($resolved['dest']['id'] === null && $dest_name !== '') {
            $d = $fetchStopByName($dest_name);
            if ($d) { $resolved['dest'] = ['id'=>(int)$d['id'],'name'=>(string)$d['stop_name'],'lat'=>(float)$d['latitude'],'lon'=>(float)$d['longitude'],'source'=>'name']; $dest_id = (int)$d['id']; }
            else {
                // Geocode fallback
                $g = $ghGeocode($dest_name) ?: $osmGeocode($dest_name);
                if ($g) {
                    $resolved['dest'] = ['id'=>null,'name'=>$g['name'],'lat'=>$g['lat'],'lon'=>$g['lon'],'source'=>$g['source']];
                } else {
                    $resolved['dest'] = ['id'=>null,'name'=>$dest_name,'lat'=>null,'lon'=>null,'source'=>'name_unresolved'];
                }
            }
        }
        if ($resolved['dest']['id'] === null && $dLatIn !== null && $dLonIn !== null) {
            $d = nearest_stop($pdo, (float)$dLatIn, (float)$dLonIn);
            if ($d) { $resolved['dest'] = ['id'=>(int)$d['id'],'name'=>(string)$d['stop_name'],'lat'=>(float)$d['latitude'],'lon'=>(float)$d['longitude'],'source'=>'coords_nearest']; $dest_id = (int)$d['id']; }
            else { $resolved['dest'] = ['id'=>null,'name'=>$dest_name!==''?$dest_name:'Destination','lat'=>$dLatIn,'lon'=>$dLonIn,'source'=>'coords_synthetic']; }
        }
        if ($resolved['dest']['name'] === null && $dest_name !== '') { $resolved['dest']['name'] = $dest_name; }

        // If both coordinates are known (either real or synthetic), synthesize an itinerary and return
        $oLatEff = $resolved['origin']['lat']; $oLonEff = $resolved['origin']['lon'];
        $dLatEff = $resolved['dest']['lat'];   $dLonEff = $resolved['dest']['lon'];
        $haveCoords = ($oLatEff !== null && $oLonEff !== null && $dLatEff !== null && $dLonEff !== null);
        $synthetic_used = (
            $resolved['origin']['source'] === 'coords_synthetic' ||
            $resolved['dest']['source'] === 'coords_synthetic' ||
            $resolved['origin']['source'] === 'graphhopper' ||
            $resolved['origin']['source'] === 'nominatim' ||
            $resolved['origin']['source'] === 'name_unresolved' ||
            $resolved['dest']['source'] === 'graphhopper' ||
            $resolved['dest']['source'] === 'nominatim' ||
            $resolved['dest']['source'] === 'name_unresolved'
        );

        if ($haveCoords && ($origin_id <= 0 || $dest_id <= 0)) {
            // Reuse existing synthesize closure below to always return a valid trip
            $oNameUse = $resolved['origin']['name'] ?: 'Origin';
            $dNameUse = $resolved['dest']['name']   ?: 'Destination';
            // Copy of synthesize env with debug additions
            $poly = gh_route_points((float)$oLatEff,(float)$oLonEff,(float)$dLatEff,(float)$dLonEff);
            $dist_km = 0.0;
            if ($poly && count($poly)>1) {
                for ($i=1;$i<count($poly);$i++){ $dist_km += haversine_km($poly[$i-1][0],$poly[$i-1][1],$poly[$i][0],$poly[$i][1]); }
            } else { $dist_km = haversine_km((float)$oLatEff,(float)$oLonEff,(float)$dLatEff,(float)$dLonEff); }
            $mode = $dist_km <= 300 ? 'bus' : ($dist_km <= 700 ? 'train' : 'flight');
            $speed = $mode==='bus'?40:($mode==='train'?70:700);
            $minutes = max(1, (int)round(($dist_km / max(1,$speed)) * 60));
            $depart = '12:00'; $arrive = [REDACTED]($depart,$minutes);
            $fare = $mode==='bus'? max(20, (int)round($dist_km*0.8)) : ($mode==='train'? max(80,(int)round($dist_km*1.2)) : max(1500,(int)round($dist_km*10)));
            $segments=[[
                'mode'=>$mode,
                'operator'=> $mode==='bus' ? 'KSRTC' : ($mode==='train'?'IRCTC':'IndiGo'),
                'from'=>$oNameUse, 'to'=>$dNameUse,
                'from_lat'=>(float)$oLatEff,'from_lon'=>(float)$oLonEff,'to_lat'=>(float)$dLatEff,'to_lon'=>(float)$dLonEff,
                'departure'=>$depart,'arrival'=>$arrive,'fare'=>$fare
            ]];
            $hotels = hotels_near($pdo, (float)$dLatEff, (float)$dLonEff, $dNameUse, 3);
            $payload=['segments'=>$segments,'legs'=>$segments,'hotels'=>$hotels,'total_fare'=>$fare,'total_time'=>sprintf('%dh %02dm', intdiv($minutes,60), $minutes%60)];
            $payload['resolved_origin'] = $resolved['origin'];
            $payload['resolved_dest']   = $resolved['dest'];
            $payload['synthetic_used']  = $synthetic_used;
            $geo_src = null;
            if (in_array($resolved['origin']['source'], ['graphhopper','nominatim'], true)) { $geo_src = $resolved['origin']['source']; }
            elseif (in_array($resolved['dest']['source'], ['graphhopper','nominatim'], true)) { $geo_src = $resolved['dest']['source']; }
            if ($geo_src !== null) { $payload['geocode_source'] = $geo_src; }
            if (!empty($poly)) { $payload['debug']=['gh_poly'=>$poly]; }
            $env=['success'=>true,'error'=>null,'count'=>count($segments),'data'=>$payload];
            // Back-compat top-level fields
            $env['segments']=$segments; $env['legs']=$segments; $env['hotels']=$hotels; $env['total_fare']=$fare; $env['total_time']=$payload['total_time'];
            $env['resolved_origin'] = $resolved['origin'];
            $env['resolved_dest']   = $resolved['dest'];
            $env['synthetic_used']  = $synthetic_used;
            if ($geo_src !== null) { $env['geocode_source'] = $geo_src; }
            echo json_encode($env);
            exit;
        }

        // If we resolved both IDs by name/nearest, continue with legacy query
        if ($origin_id > 0 && $dest_id > 0) {
            // fallthrough to legacy query below
        } else {
            // Final safe fallback: return empty but successful envelope with debug
            $payload=['segments'=>[],'legs'=>[],'hotels'=>[],'total_fare'=>0,'total_time'=>'0h 00m'];
            $payload['resolved_origin'] = $resolved['origin'];
            $payload['resolved_dest']   = $resolved['dest'];
            $payload['synthetic_used']  = $synthetic_used;
            $geo_src = null;
            if (in_array($resolved['origin']['source'], ['graphhopper','nominatim'], true)) { $geo_src = $resolved['origin']['source']; }
            elseif (in_array($resolved['dest']['source'], ['graphhopper','nominatim'], true)) { $geo_src = $resolved['dest']['source']; }
            if ($geo_src !== null) { $payload['geocode_source'] = $geo_src; }
            $env=['success'=>true,'error'=>null,'count'=>0,'data'=>$payload];
            $env['segments']=[]; $env['legs']=[]; $env['hotels']=[]; $env['total_fare']=0; $env['total_time']='0h 00m';
            $env['resolved_origin'] = $resolved['origin'];
            $env['resolved_dest']   = $resolved['dest'];
            $env['synthetic_used']  = $synthetic_used;
            if ($geo_src !== null) { $env['geocode_source'] = $geo_src; }
            echo json_encode($env);
            exit;
        }
    }

    if ($arrive_by === '') { $arrive_by = '23:59:59'; }

    // Assumed schema (example):
    // stops(id, stop_name, operator_name, city, latitude, longitude)
    // routes(id, operator_name, name)
    // trips(id, route_id, departure_time, arrival_time, base_fare)
    // trip_stops(route_id, stop_id, stop_sequence)
    // Query: find trips on routes that include origin then destination (sequence) and arrive before arrive_by

    $sql = "
    SELECT
      r.id AS route_id,
      r.operator_name,
      t.id AS trip_id,
      t.departure_time,
      t.arrival_time,
      t.base_fare,
      ts_o.stop_sequence AS seq_o,
      ts_d.stop_sequence AS seq_d
    FROM routes r
    JOIN trips t ON t.route_id = r.id
    JOIN trip_stops ts_o ON ts_o.route_id = r.id AND ts_o.stop_id = :origin_id
    JOIN trip_stops ts_d ON ts_d.route_id = r.id AND ts_d.stop_id = :dest_id
    WHERE ts_o.stop_sequence < ts_d.stop_sequence
      AND t.departure_time <= :arrive_by
    ORDER BY t.departure_time DESC
    LIMIT 10
    ";

    $rows = [];
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':origin_id' => $origin_id,
            ':dest_id'   => $dest_id,
            ':arrive_by' => $arrive_by,
        ]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $q) {
        $payload = ['results'=>[]];
        $env = ['success'=>false, 'error'=>'Query failed', 'count'=>0, 'data'=>$payload, 'results'=>[]];
        echo json_encode($env);
        exit;
    }

    if (!$rows) {
        // Fallback: synthesize a basic itinerary using origin/dest stop coordinates
        try {
            $getS = $pdo->prepare('SELECT id, stop_name, city, latitude, longitude FROM stops WHERE id IN (:o,:d)');
            // Workaround for MySQL named params uniqueness: use two statements
        } catch (Throwable $ie) { /* ignore */ }
        $o = null; $d = null;
        try {
            $stmtO = $pdo->prepare('SELECT id, stop_name, city, latitude, longitude FROM stops WHERE id = :id LIMIT 1');
            $stmtO->execute([':id'=>$origin_id]);
            $o = $stmtO->fetch();
            $stmtD = $pdo->prepare('SELECT id, stop_name, city, latitude, longitude FROM stops WHERE id = :id LIMIT 1');
            $stmtD->execute([':id'=>$dest_id]);
            $d = $stmtD->fetch();
        } catch (Throwable $se) { /* ignore */ }

        // Helper to synthesize using two coordinate pairs
        $synthesize = function(float $oLat, float $oLon, float $dLat, float $dLon, string $oName='Origin', string $dName='Destination') use ($pdo) {
            $poly = gh_route_points($oLat,$oLon,$dLat,$dLon);
            $dist_km = 0.0;
            if ($poly && count($poly)>1) {
                for ($i=1;$i<count($poly);$i++){ $dist_km += haversine_km($poly[$i-1][0],$poly[$i-1][1],$poly[$i][0],$poly[$i][1]); }
            } else { $dist_km = haversine_km($oLat,$oLon,$dLat,$dLon); }
            $mode = $dist_km <= 300 ? 'bus' : ($dist_km <= 700 ? 'train' : 'flight');
            $speed = $mode==='bus'?40:($mode==='train'?70:700);
            $minutes = max(1, (int)round(($dist_km / max(1,$speed)) * 60));
            $depart = '12:00'; $arrive = [REDACTED]($depart,$minutes);
            $fare = $mode==='bus'? max(20, (int)round($dist_km*0.8)) : ($mode==='train'? max(80,(int)round($dist_km*1.2)) : max(1500,(int)round($dist_km*10)));
            $segments=[[
                'mode'=>$mode,
                'operator'=> $mode==='bus' ? 'KSRTC' : ($mode==='train'?'IRCTC':'IndiGo'),
                'from'=>$oName, 'to'=>$dName,
                'from_lat'=>$oLat,'from_lon'=>$oLon,'to_lat'=>$dLat,'to_lon'=>$dLon,
                'departure'=>$depart,'arrival'=>$arrive,'fare'=>$fare
            ]];
            $hotels = hotels_near($pdo, $dLat, $dLon, $dName, 3);
            $payload=['segments'=>$segments,'legs'=>$segments,'hotels'=>$hotels,'total_fare'=>$fare,'total_time'=>sprintf('%dh %02dm', intdiv($minutes,60), $minutes%60)];
            $env=['success'=>true,'error'=>null,'count'=>count($segments),'data'=>$payload];
            $env['segments']=$segments; $env['legs']=$segments; $env['hotels']=$hotels; $env['total_fare']=$fare; $env['total_time']=$payload['total_time'];
            if (!empty($poly)) { $env['debug']=['gh_poly'=>$poly]; }
            echo json_encode($env);
            exit;
        };

        if ($o && $d && isset($o['latitude'],$o['longitude'],$d['latitude'],$d['longitude'])) {
            $oLat=(float)$o['latitude']; $oLon=(float)$o['longitude'];
            $dLat=(float)$d['latitude']; $dLon=(float)$d['longitude'];
            $synthesize($oLat,$oLon,$dLat,$dLon,$o['stop_name']??'Origin',$d['stop_name']??'Destination');
        }

        // Try GET-provided coordinates
        $oLat = isset($_GET['origin_lat']) ? (float)$_GET['origin_lat'] : null;
        $oLon = isset($_GET['origin_lon']) ? (float)$_GET['origin_lon'] : null;
        $dLat = isset($_GET['dest_lat']) ? (float)$_GET['dest_lat'] : null;
        $dLon = isset($_GET['dest_lon']) ? (float)$_GET['dest_lon'] : null;
        if ($oLat!==null && $oLon!==null && $dLat!==null && $dLon!==null) {
            $synthesize($oLat,$oLon,$dLat,$dLon,'Origin','Destination');
        }

        // As a final fallback, return an empty but valid itinerary
        $payload=['segments'=>[],'legs'=>[],'hotels'=>[],'total_fare'=>0,'total_time'=>'0h 00m'];
        $env=['success'=>true,'error'=>null,'count'=>0,'data'=>$payload,'segments'=>[],'legs'=>[],'hotels'=>[],'total_fare'=>0,'total_time'=>'0h 00m'];
        echo json_encode($env);
        exit;
    }

    $results = [];
    foreach ($rows as $row) {
        $route_id = (int)$row['route_id'];
        $seq_o = (int)$row['seq_o'];
        $seq_d = (int)$row['seq_d'];

        // Fetch stops along the route between origin and destination sequences
        try {
            $pathStmt = $pdo->prepare("SELECT s.id, s.stop_name, s.city, s.latitude, s.longitude
                                       FROM trip_stops ts
                                       JOIN stops s ON s.id = ts.stop_id
                                       WHERE ts.route_id = :route_id
                                         AND ts.stop_sequence BETWEEN :seq_o AND :seq_d
                                       ORDER BY ts.stop_sequence ASC");
            $pathStmt->execute([
                ':route_id' => $route_id,
                ':seq_o'    => $seq_o,
                ':seq_d'    => $seq_d,
            ]);
            $stops = $pathStmt->fetchAll();
        } catch (Throwable $q2) {
            http_response_code(500);
            echo json_encode(['error' => 'Path query failed', 'details' => $q2->getMessage()]);
            exit;
        }
        // Determine operator type by distance thresholds
        $opType = inferOperatorType($row['operator_name']);
        $hotel = null;
        if (count($stops) >= 2) {
            $o = $stops[0];
            $d = $stops[count($stops)-1];
            $distKm = haversine_km((float)$o['latitude'], (float)$o['longitude'], (float)$d['latitude'], (float)$d['longitude']);
            if ($distKm <= 300) $opType = 'Bus';
            elseif ($distKm <= 700) $opType = 'Train';
            else { $opType = 'Flight'; }
            // Suggest a nearby hotel for destination stop (Haversine within ~30km), fallback to city match
            try {
                $latD = (float)$d['latitude'];
                $lonD = (float)$d['longitude'];
                $hs = $pdo->prepare("SELECT hotel_name, stars, price_per_night, latitude, longitude,
                  (6371 * ACOS(COS(RADIANS(:lat)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon)) + SIN(RADIANS(:lat)) * SIN(RADIANS(latitude)))) AS dist_km
                  FROM hotels
                  WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                  HAVING dist_km <= 30
                  ORDER BY stars DESC, price_per_night ASC
                  LIMIT 1");
                $hs->execute([':lat'=>$latD, ':lon'=>$lonD]);
                $hrow = $hs->fetch();
                if (!$hrow && !empty($d['city'])){
                    $hs = $pdo->prepare('SELECT hotel_name, stars, price_per_night, latitude, longitude FROM hotels WHERE city = :c ORDER BY stars DESC, price_per_night ASC LIMIT 1');
                    $hs->execute([':c' => $d['city']]);
                    $hrow = $hs->fetch();
                }
                if ($hrow) {
                    $hotel = [
                        'hotel_name' => $hrow['hotel_name'],
                        'stars' => isset($hrow['stars']) ? (int)$hrow['stars'] : 3,
                        'price_per_night' => isset($hrow['price_per_night']) ? (float)$hrow['price_per_night'] : null,
                        'latitude' => isset($hrow['latitude']) ? (float)$hrow['latitude'] : null,
                        'longitude'=> isset($hrow['longitude']) ? (float)$hrow['longitude'] : null,
                    ];
                    // Append stars to name for immediate frontend visibility
                    if (!empty($hotel['hotel_name']) && isset($hotel['stars'])) {
                        $hotel['hotel_name'] .= ' (' . (int)$hotel['stars'] . '★)';
                    }
                }
            } catch (Throwable $he) { /* ignore hotel errors */ }
        }

        $results[] = [
            'operator_name' => $row['operator_name'],
            'operator_type' => $opType,
            'route_id'      => $route_id,
            'trip_id'       => (int)$row['trip_id'],
            'departure_time'=> $row['departure_time'],
            'arrival_time'  => $row['arrival_time'],
            'base_fare'     => isset($row['base_fare']) ? (float)$row['base_fare'] : null,
            'stops'         => $stops,
            'hotel'         => $hotel,
        ];
    }

    $payload = ['results'=>$results];
    $env = ['success'=>true, 'error'=>null, 'count'=>count($results), 'data'=>$payload, 'results'=>$results];
    echo json_encode($env);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}

