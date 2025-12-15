<?php
// backend/php/helpers/routing_helpers.php
require_once __DIR__ . '/tomtom_helpers.php';

// Cache for operators per state loaded from CSV
function load_bus_operators_csv(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = ['_list'=>[]];
    $csv = __DIR__ . '/../data/bus_data.csv';
    if (is_file($csv)) {
        try {
            if (($fh = fopen($csv, 'r')) !== false) {
                $header = fgetcsv($fh);
                while (($row = fgetcsv($fh)) !== false) {
                    $state = trim((string)($row[0] ?? ''));
                    $op    = trim((string)($row[1] ?? ''));
                    if ($state !== '' && $op !== '') {
                        $key = strtolower($state);
                        if (!isset($cache[$key])) $cache[$key] = [];
                        $cache[$key][] = $op;
                        $cache['_list'][] = $op;
                    }
                }
                fclose($fh);
            }
        } catch (Throwable $e) { /* ignore */ }
    }
    return $cache;
}

function detect_state_from_name(?string $name): ?string {
    if (!$name) return null;
    $t = strtolower($name);
    $map = [
        'kerala'=>'Kerala','karnataka'=>'Karnataka','tamil nadu'=>'Tamil Nadu','maharashtra'=>'Maharashtra','delhi'=>'Delhi',
        'rajasthan'=>'Rajasthan','uttar pradesh'=>'Uttar Pradesh','andhra pradesh'=>'Andhra Pradesh','telangana'=>'Telangana',
        'gujarat'=>'Gujarat','west bengal'=>'West Bengal','odisha'=>'Odisha','bihar'=>'Bihar','punjab'=>'Punjab','haryana'=>'Haryana'
    ];
    foreach ($map as $k=>$v) if (strpos($t,$k)!==false) return $v; return null;
}

function pick_operator_for_leg(string $fromState=null, string $toState=null): string {
    $ops = load_bus_operators_csv();
    $f = strtolower($fromState ?? ''); $t = strtolower($toState ?? '');
    // Explicit state rules
    $rule = function($s){
        $ls = strtolower($s ?? '');
        if ($ls === 'kerala') return 'KSRTC';
        if ($ls === 'tamil nadu') return 'TNSTC';
        if ($ls === 'karnataka') return 'KSRTC-K';
        return null;
    };
    if ($fromState && $toState && $f === $t) {
        $r = $rule($fromState); if ($r) return $r;
        if (isset($ops[$f]) && count($ops[$f])) return $ops[$f][array_rand($ops[$f])];
    }
    // Interstate: use operator from origin state side for the leg
    if ($fromState) { $r = $rule($fromState); if ($r) return $r; }
    if ($fromState && isset($ops[$f]) && count($ops[$f])) return $ops[$f][array_rand($ops[$f])];
    if (!empty($ops['_list'])) return $ops['_list'][array_rand($ops['_list'])];
    return 'Interstate Bus';
}

if (!function_exists('sample_polyline_km')) {
function sample_polyline_km(array $poly, float $stepKm = 40.0): array {
    if (count($poly) < 2) return $poly;
    $out = [$poly[0]];
    $accKm = 0.0; $targetKm = max(5.0, $stepKm);
    for ($i=1; $i<count($poly); $i++){
        $a = $poly[$i-1]; $b = $poly[$i];
        $segKm = haversine_km($a[0],$a[1],$b[0],$b[1]);
        if ($segKm <= 0) continue;
        if ($accKm + $segKm >= $targetKm){
            $need = $targetKm - $accKm; $t = max(0.0, min(1.0, $need / $segKm));
            $lat = $a[0] + ($b[0]-$a[0])*$t; $lon = $a[1] + ($b[1]-$a[1])*$t;
            $out[] = [$lat,$lon];
            $accKm = 0.0; $poly[$i-1] = [$lat,$lon]; $i--; continue;
        } else { $accKm += $segKm; }
    }
    $out[] = $poly[count($poly)-1];
    return $out;
}
}

if (!function_exists('haversine_km')) {
function haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371.0; $dLat = deg2rad($lat2 - $lat1); $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2; return 2*$R*asin(min(1,sqrt($a)));
}
}

function concat_polylines(array $legs): array {
    $out = []; $first = true; foreach ($legs as $leg){
        $pl = $leg['polyline'] ?? [];
        if (!$pl) continue;
        if ($first) { $out = $pl; $first=false; }
        else {
            // avoid duplicate connecting point
            if (!empty($out) && !empty($pl)) {
                $last = $out[count($out)-1]; $firstPt = $pl[0];
                if (abs($last[0]-$firstPt[0])<1e-6 && abs($last[1]-$firstPt[1])<1e-6) { array_shift($pl); }
            }
            $out = array_merge($out, $pl);
        }
    }
    return $out;
}

function severity_from_delay(int $minutes): string {
    if ($minutes <= 0) return 'none';
    if ($minutes <= 10) return 'low';
    if ($minutes <= 45) return 'medium';
    return 'high';
}

function compute_leg_traffic(array $poly, int $base_duration_s, string $key): array {
    if (count($poly) < 2) return ['delay_min'=>0,'severity'=>'none','raw'=>['reason'=>'no_poly'],'sub_segments'=>[['polyline'=>$poly,'severity'=>'none']]];
    // Split polyline into ~3km chunks and score each chunk (tradeoff: fewer calls, sufficient granularity)
    $sub = []; $chunk = []; $acc = 0.0; $last = $poly[0]; $chunk[] = $last;
    $delays = []; $sevLevels = [];
    $calls = 0; $maxSamples = 20; // hard cap to keep responses snappy
    for ($i=1; $i<count($poly); $i++){
        $pt = $poly[$i]; $d = haversine_km($last[0],$last[1],$pt[0],$pt[1]);
        $acc += $d; $chunk[] = $pt; $last = $pt;
        $thresholdKm = 3.0;
        if ($acc >= $thresholdKm || $i === count($poly)-1) {
            // sample mid-point of chunk
            $mid = $chunk[(int)floor(count($chunk)/2)];
            $r = null;
            if ($calls < $maxSamples) { $r = tomtom_traffic_point($mid[0], $mid[1], $key); $calls++; }
            $sev = 'none'; $ratio = 1.0; $raw = null; $url = null;
            if ($r && $r['success'] && isset($r['data']['currentSpeed'],$r['data']['freeFlowSpeed'])) {
                $curr = max(1.0, (float)$r['data']['currentSpeed']);
                $free = max(1.0, (float)$r['data']['freeFlowSpeed']);
                $ratio = min(1.0, $curr/$free);
                $delayChunkMin = max(0, (int)round((1.0 - $ratio) * max(1,(int)round(($acc/45.0)*60))));
                $delays[] = $delayChunkMin;
                $sv = severity_from_delay($delayChunkMin);
                $sev = $sv; $raw = $r['debug']['raw'] ?? null; $url = $r['debug']['url'] ?? null;
            } else { $delays[] = 0; }
            $sub[] = ['polyline'=>$chunk, 'severity'=>$sev];
            $sevLevels[] = $sev;
            $chunk = [$pt]; $acc = 0.0;
            if ($calls >= $maxSamples) {
                // append the remaining geometry as a single neutral segment
                if ($i < count($poly)-1) {
                    $rest = array_slice($poly, $i+1);
                    if (!empty($rest)) { $sub[] = ['polyline'=>array_merge([$pt], $rest), 'severity'=>'none']; }
                }
                break;
            }
        }
    }
    $delayMin = min(180, array_sum($delays));
    // Derive overall severity as worst among chunks
    $rank = ['none'=>0,'low'=>1,'medium'=>2,'high'=>3]; $worst = 'none'; $bestR = 0;
    foreach ($sevLevels as $s) { $rnk = $rank[$s] ?? 0; if ($rnk > $bestR) { $bestR = $rnk; $worst = $s; } }
    return ['delay_min'=>$delayMin,'severity'=>$worst,'raw'=>['chunks'=>count($sub)],'sub_segments'=>$sub];
}

function nearest_db_bus_stop(PDO $pdo, float $lat, float $lon, float $radius_km, float $forwardMinProg = -INF, array $routePoly = []): ?array {
    try {
        $st = $pdo->prepare("SELECT id, name, lat, lon,
          (6371 * ACOS(COS(RADIANS(:lat1)) * COS(RADIANS(lat)) * COS(RADIANS(lon) - RADIANS(:lon1)) + SIN(RADIANS(:lat2)) * SIN(RADIANS(lat)))) AS dist_km
          FROM bus_stations WHERE lat IS NOT NULL AND lon IS NOT NULL HAVING dist_km <= :r ORDER BY dist_km ASC LIMIT 15");
        $st->execute([':lat1'=>$lat, ':lat2'=>$lat, ':lon1'=>$lon, ':r'=>$radius_km]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { $rows = []; }
    if (!$rows) return null;
    $best = null; $bestProg = -INF;
    foreach ($rows as $r){
        $p = [$r['lat'], $r['lon']];
        [$d,$prog] = point_to_polyline_progress((float)$p[0],(float)$p[1],$routePoly);
        if ($prog > $forwardMinProg + 1e-4 && $prog > $bestProg) { $best = $r; $bestProg = $prog; }
    }
    if ($best){
        $best['state'] = detect_state_from_name($best['name']) ?? '';
        $best['synthetic'] = false;
        return $best;
    }
    return null;
}

if (!function_exists('point_to_polyline_progress')) {
function point_to_polyline_progress(float $plat, float $plon, array $poly): array {
    if (count($poly) < 2) return [INF, 0.0];
    $bestD = INF; $bestProg = 0.0; $acc = 0.0;
    for ($i=0; $i < count($poly)-1; $i++) {
        [$aLat,$aLon] = $poly[$i]; [$bLat,$bLon] = $poly[$i+1];
        $dA = haversine_km($plat,$plon,$aLat,$aLon);
        $dB = haversine_km($plat,$plon,$bLat,$bLon);
        $dAB= haversine_km($aLat,$aLon,$bLat,$bLon);
        if ($dAB <= 1e-6) { if ($dA < $bestD) { $bestD=$dA; $bestProg=$i; } continue; }
        $t = max(0.0, min(1.0, ( ($dAB*$dAB + $dA*$dA - $dB*$dB) / (2*$dAB*$dAB) )));
        $px = $aLat + ($bLat-$aLat)*$t; $py = $aLon + ($bLon-$aLon)*$t;
        $d = haversine_km($plat,$plon,$px,$py);
        if ($d < $bestD) { $bestD=$d; $bestProg=$acc + $t; }
        $acc += 1.0;
    }
    return [$bestD, $bestProg];
}
}

function chain_bus_strict(PDO $pdo, array $origin, array $dest, array $tt_poly, string $departHHMM, string $key, array &$debug = []): array {
    $stops = [];
    // A: snap origin
    $snapO = nearest_db_bus_stop($pdo, $origin['lat'], $origin['lon'], 12.0, -INF, $tt_poly);
    if ($snapO) { $stops[] = ['name'=>$snapO['name'],'lat'=>(float)$snapO['lat'],'lon'=>(float)$snapO['lon'],'state'=>$snapO['state'],'synthetic'=>false]; }
    else {
        // reverse geocode for pretty synthetic name
        $rev = tomtom_reverse_geocode($origin['lat'],$origin['lon'],$key);
        $town = ($rev['success'] && isset($rev['data']['name'])) ? $rev['data']['name'] : ($origin['name'] ?? 'Origin');
        $stops[] = ['name'=>'Near '.$town,'lat'=>$origin['lat'],'lon'=>$origin['lon'],'state'=>detect_state_from_name($town) ?? '', 'synthetic'=>true];
    }
    // B: walk along polyline, every ~20km try to pick forward stop within 10km
    $samples = sample_polyline_km($tt_poly, 20.0);
    $lastProg = -INF;
    foreach ($samples as $pt){
        [$d, $prog] = point_to_polyline_progress($pt[0],$pt[1],$tt_poly);
        if ($prog <= $lastProg + 0.25) continue;
        $cand = nearest_db_bus_stop($pdo, $pt[0], $pt[1], 10.0, $lastProg, $tt_poly);
        if ($cand) {
            $name = (string)$cand['name'];
            $state = detect_state_from_name($name) ?? '';
            // skip if too close to previous stop (<15km)
            $prev = $stops[count($stops)-1];
            $gap = haversine_km($prev['lat'],$prev['lon'], (float)$cand['lat'], (float)$cand['lon']);
            if ($gap < 15) { $lastProg = $prog; continue; }
            $stops[] = ['name'=>$name,'lat'=>(float)$cand['lat'],'lon'=>(float)$cand['lon'],'state'=>$state,'synthetic'=>false];
            $lastProg = $prog;
        }
    }
    // C: snap near dest or synthetic
    $snapD = nearest_db_bus_stop($pdo, $dest['lat'], $dest['lon'], 12.0, $lastProg, $tt_poly);
    if ($snapD) { $stops[] = ['name'=>$snapD['name'],'lat'=>(float)$snapD['lat'],'lon'=>(float)$snapD['lon'],'state'=>$snapD['state'] ?? detect_state_from_name($snapD['name']), 'synthetic'=>false]; }
    else {
        $rev = tomtom_reverse_geocode($dest['lat'],$dest['lon'],$key);
        $town = ($rev['success'] && isset($rev['data']['name'])) ? $rev['data']['name'] : ($dest['name'] ?? 'Destination');
        $stops[] = ['name'=>'Near '.$town,'lat'=>$dest['lat'],'lon'=>$dest['lon'],'state'=>detect_state_from_name($town) ?? '', 'synthetic'=>true];
    }
    // Guarantee >=3 stops
    if (count($stops) < 3) {
        $mid = $samples[(int)floor(count($samples)/2)] ?? $tt_poly[(int)floor(count($tt_poly)/2)] ?? [$origin['lat'],$origin['lon']];
        $rev = tomtom_reverse_geocode($mid[0],$mid[1],$key);
        $town = ($rev['success'] && isset($rev['data']['name'])) ? $rev['data']['name'] : 'Waypoint';
        $stops = [ $stops[0], ['name'=>'Near '.$town,'lat'=>$mid[0],'lon'=>$mid[1],'state'=>detect_state_from_name($town) ?? '', 'synthetic'=>true], $stops[count($stops)-1] ];
    }
    // Build legs with layovers ~10-15 min at each intermediate stop
    $legs = []; $cursor = $departHHMM; $urls = [];
    for ($i=0; $i<count($stops)-1; $i++){
        $a = $stops[$i]; $b = $stops[$i+1];
        $rr = tomtom_route_points($a['lat'],$a['lon'],$b['lat'],$b['lon'],$key);
        $poly = []; $len_m = null; $time_s = null; $url = null;
        if ($rr['success']) { $poly = $rr['data']['points']; $len_m = (float)($rr['data']['length_m'] ?? 0); $time_s = (int)($rr['data']['time_s'] ?? 0); $url = $rr['debug']['url'] ?? null; }
        else { // fallback geometry
            $poly = [[$a['lat'],$a['lon']],[$b['lat'],$b['lon']]]; $len_m = haversine_km($a['lat'],$a['lon'],$b['lat'],$b['lon'])*1000; $time_s = (int)round(($len_m/1000)/45*3600);
        }
        $urls[] = $url;
        // traffic over poly (sub-segmented)
        $traffic = compute_leg_traffic($poly, $time_s, $key);
        $dep = $cursor; $arr = fmt_time_add_minutes($cursor, (int)round($time_s/60) + $traffic['delay_min']);
        $op = pick_operator_for_leg($a['state'] ?? null, $b['state'] ?? null);
        $fare = (int)round(($len_m/1000.0) * 1.2 * (0.9 + (mt_rand(0,20)/100.0))); // +/-10%
        $layoverMin = ($i < count($stops)-2) ? (10 + (mt_rand(0,5))) : 0; // 10-15 min at intermediate stops
        $legs[] = [
            'mode'=>'bus','operator_name'=>$op,
            'from'=>$a['name'],'to'=>$b['name'],
            'from_lat'=>$a['lat'],'from_lon'=>$a['lon'],'to_lat'=>$b['lat'],'to_lon'=>$b['lon'],
            'distance_km'=>round($len_m/1000.0,1),'duration_s'=>$time_s,
            'departure'=>$dep,'arrival'=>$arr,'fare'=>$fare,
            'polyline'=>$poly,
            'traffic_delay'=>$traffic['delay_min'],'traffic_severity'=>$traffic['severity'],'traffic_raw'=>$traffic['raw'],
            'sub_segments'=>$traffic['sub_segments'] ?? [],
            'layover_s'=> $layoverMin * 60,
            'synthetic'=>(bool)($a['synthetic'] || $b['synthetic'])
        ];
        // apply layover to cursor
        $cursor = fmt_time_add_minutes($arr, $layoverMin);
    }
    $debug['tomtom_route_urls'] = $urls;
    $debug['bus_chaining_stops'] = $stops;
    return ['legs'=>$legs,'intermediate_stops'=>$stops];
}

if (!function_exists('fmt_time_add_minutes')) {
function fmt_time_add_minutes(string $hhmm, int $delta): string {
    $parts = explode(':', $hhmm);
    $h = (int)($parts[0] ?? 0); $m = (int)($parts[1] ?? 0);
    $t = $h*60 + $m + $delta; if ($t < 0) $t = 0; $h2 = intdiv($t,60)%24; $m2 = $t%60;
    return sprintf('%02d:%02d', $h2, $m2);
}
}
