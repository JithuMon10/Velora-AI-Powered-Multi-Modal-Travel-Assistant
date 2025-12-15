<?php
// get_hotels.php — DB > OSM > Gemini layered fallback
header('Content-Type: application/json');
require_once __DIR__ . '/helpers/db.php';
require_once __DIR__ . '/helpers/ai.php';

try {
    $pdo = get_pdo();
    ensure_table_hotels_cache($pdo);

    $city = isset($_GET['city']) ? trim($_GET['city']) : '';
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
    $lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;
    $debug = isset($_GET['debug']) && ($_GET['debug']==='1' || strtolower($_GET['debug'])==='true');

    // Try DB legacy schema
    if ($lat!==null && $lon!==null) {
        try {
            $st = $pdo->prepare("SELECT city, hotel_name, price_per_night, latitude, longitude,
              (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(latitude)))) AS dist_km
              FROM hotels WHERE latitude IS NOT NULL AND longitude IS NOT NULL HAVING dist_km <= 30 ORDER BY dist_km ASC, price_per_night ASC LIMIT 5");
            $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
            $rows = $st->fetchAll();
            if ($rows && count($rows)) { echo json_encode(['success'=>true,'count'=>count($rows),'data'=>$rows,'source'=>'db']); exit; }
        } catch (Throwable $e) { /* continue */ }
        // Try OSM schema
        try {
            $st = $pdo->prepare("SELECT name AS hotel_name, city, price_per_night, lat AS latitude, lon AS longitude,
              (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) AS dist_km
              FROM hotels WHERE lat IS NOT NULL AND lon IS NOT NULL HAVING dist_km <= 30 ORDER BY dist_km ASC LIMIT 5");
            $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon]);
            $rows = $st->fetchAll();
            if ($rows && count($rows)) { echo json_encode(['success'=>true,'count'=>count($rows),'data'=>$rows,'source'=>'osm']); exit; }
        } catch (Throwable $e) { /* continue */ }
    }

    // Overpass (OSM) fallback: 30km radius around coordinate
    if ($lat!==null && $lon!==null) {
        try {
            $radius_m = 30000; // 30 km
            $q = '[out:json][timeout:20];(' .
                 sprintf('node["tourism"="hotel"](around:%d,%.6f,%.6f);', $radius_m, $lat, $lon) .
                 sprintf('node["amenity"="hotel"](around:%d,%.6f,%.6f);', $radius_m, $lat, $lon) .
                 ');out body 20;';
            $ch = curl_init('https://overpass-api.de/api/interpreter');
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>http_build_query(['data'=>$q]),
                CURLOPT_TIMEOUT=>20,
                CURLOPT_HTTPHEADER=>['User-Agent: Velora/1.0 (contact: student-project)']
            ]);
            $resp = curl_exec($ch); if ($resp===false) { $resp = 'curl_error: '.curl_error($ch);} curl_close($ch);
            $j = json_decode($resp,true);
            $items = [];
            if (isset($j['elements']) && is_array($j['elements'])){
                foreach ($j['elements'] as $el){
                    $name = $el['tags']['name'] ?? '';
                    $la = isset($el['lat']) ? (float)$el['lat'] : null;
                    $lo = isset($el['lon']) ? (float)$el['lon'] : null;
                    if ($name!=='' && $la!==null && $lo!==null){
                        $items[] = [
                            'hotel_name'=>$name,
                            'price_per_night'=>null,
                            'stars'=>null,
                            'latitude'=>$la,
                            'longitude'=>$lo
                        ];
                    }
                    if (count($items) >= 5) break;
                }
            }
            if (!empty($items)) {
                // cache
                try { $ins=$pdo->prepare('INSERT INTO hotels_cache(cache_key,response_json) VALUES (?,?)'); $ins->execute(['hotels:'.md5(sprintf('%.4f,%.4f',$lat,$lon)), json_encode($items)]); } catch (Throwable $e) {}
                echo json_encode(['success'=>true,'count'=>count($items),'data'=>$items,'source'=>'overpass']); exit;
            }
        } catch (Throwable $e) { /* continue to Gemini */ }
    }

    // Gemini fallback (hardened prompt)
    $where = $city !== '' ? ("city '".$city."'") : (sprintf('lat %.5f, lon %.5f', $lat, $lon));
    $prompt = "Return a JSON array with AT LEAST 5 realistic hotels within 30 km of $where in India.\n".
              "Respond ONLY with JSON array of objects: [{hotel_name:string, price_per_night:int, stars:int, latitude:float, longitude:float}]\n".
              "- Do not return an empty array. If unsure, provide plausible hotels near the coordinates.\n- price_per_night must be INR between 1500 and 5000\n- stars must be 1..5";
    $cacheKey = 'hotels:'.md5(($city?"city:$city":sprintf('%.4f,%.4f',$lat,$lon)));

    // Try hotels_cache (24h)
    try {
        $sel = $pdo->prepare('SELECT response_json FROM hotels_cache WHERE cache_key=? AND created_at >= (NOW() - INTERVAL 1 DAY) ORDER BY id DESC LIMIT 1');
        $sel->execute([$cacheKey]);
        if ($row = $sel->fetch()){
            $arr = json_decode($row['response_json'] ?? 'null', true);
            if (is_array($arr) && !empty($arr)) { echo json_encode(['success'=>true,'count'=>count($arr),'data'=>$arr,'source'=>'gemini-cache']); exit; }
        }
    } catch (Throwable $e) { /* ignore */ }

    $res = call_gemini($prompt);
    $arr = is_array($res ?? null) ? $res : null;
    if (!is_array($arr)) {
        // One retry with explicit instruction
        $res = call_gemini($prompt."\nReturn only valid JSON, no markdown, no commentary.");
        $arr = is_array($res ?? null) ? $res : null;
    }
    if (!is_array($arr)) {
        $out = ['success'=>false,'error'=>'AI parse failed','data'=>[],'count'=>0];
        if ($debug) $out['raw'] = 'see logs';
        echo json_encode($out); exit;
    }

    // Sanitize
    $out=[]; foreach ($arr as $it){
        $hn = (string)($it['hotel_name'] ?? '');
        $ppn = (int)($it['price_per_night'] ?? 0);
        $st  = (int)($it['stars'] ?? 0);
        $la  = isset($it['latitude']) ? (float)$it['latitude'] : null;
        $lo  = isset($it['longitude']) ? (float)$it['longitude'] : null;
        if ($hn!=='' && $ppn>=1000 && $ppn<=20000 && $st>=1 && $st<=5 && $la!==null && $lo!==null) {
            $out[] = [ 'hotel_name'=>$hn, 'price_per_night'=>$ppn, 'stars'=>$st, 'latitude'=>$la, 'longitude'=>$lo ];
        }
    }
    // Cache
    try { $ins=$pdo->prepare('INSERT INTO hotels_cache(cache_key,response_json) VALUES (?,?)'); $ins->execute([$cacheKey, json_encode($out)]); } catch (Throwable $e) { /* ignore */ }

    $resp = ['success'=>true,'error'=>null,'count'=>count($out),'data'=>$out,'source'=>'gemini'];
    if ($debug) $resp['raw'] = $res['raw'] ?? '';
    echo json_encode($resp);
} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'error'=>'Server error']);
}

