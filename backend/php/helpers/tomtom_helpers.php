<?php
// backend/php/helpers/tomtom_helpers.php
// TomTom API helper wrappers with retry and structured responses

function [REDACTED](string $url): array {
    $isTraffic = (strpos($url, '/traffic/') !== false);
    $attemptLimit = $isTraffic ? 1 : 2;
    $timeoutSec = $isTraffic ? 6 : 20;
    $attempts = 0; $lastErr = null; $respData = null; $raw = null;
    while ($attempts < $attemptLimit) {
        $attempts++;
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                [REDACTED] => true,
                CURLOPT_TIMEOUT => $timeoutSec,
                [REDACTED] => true,
                [REDACTED] => false,
            ]);
            $resp = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, [REDACTED]);
            curl_close($ch);
            if ($resp === false) { $lastErr = $err ?: 'curl_error'; continue; }
            $raw = $resp;
            $data = json_decode($resp, true);
            if (!is_array($data)) { $lastErr = 'invalid_json'; continue; }
            if ($code < 200 || $code >= 300) { $lastErr = 'http_'.$code; continue; }
            $respData = $data; break;
        } catch (Throwable $e) { $lastErr = $e->getMessage(); }
    }
    if ($respData === null) return ['success'=>false,'error'=>$lastErr ?: 'unknown_error','data'=>null,'_raw'=>$raw];
    return ['success'=>true,'error'=>null,'data'=>$respData,'_raw'=>$raw];
}

function tomtom_geocode_q(string $query, string $key): array {
    $q = rawurlencode($query);
    $url = "https://api.tomtom.com/search/2/geocode/{$q}.json?key={$key}";
    $r = [REDACTED]($url);
    if (!$r['success']) return ['success'=>false,'error'=>$r['error'],'data'=>null,'debug'=>['url'=>$url]];
    $data = $r['data'];
    if (!empty($data['results'][0])) {
        $res = $data['results'][0];
        $lat = $res['position']['lat'] ?? null;
        $lon = $res['position']['lon'] ?? null;
        $name = $res['address']['freeformAddress'] ?? ($res['address']['streetName'] ?? $query);
        if ($lat !== null && $lon !== null) return ['success'=>true,'error'=>null,'data'=>['lat'=>(float)$lat,'lon'=>(float)$lon,'name'=>(string)$name],'debug'=>['url'=>$url]];
    }
    return ['success'=>false,'error'=>'no_results','data'=>null,'debug'=>['url'=>$url]];
}

function [REDACTED](float $lat, float $lon, string $key): array {
    $url = "https://api.tomtom.com/search/2/reverseGeocode/{$lat},{$lon}.json?key={$key}";
    $r = [REDACTED]($url);
    if (!$r['success']) return ['success'=>false,'error'=>$r['error'],'data'=>null,'debug'=>['url'=>$url]];
    $data = $r['data'];
    if (!empty($data['addresses'][0])) {
        $res = $data['addresses'][0];
        $name = $res['address']['municipality'] ?? $res['address']['localName'] ?? $res['address']['freeformAddress'] ?? 'Unknown';
        return ['success'=>true,'error'=>null,'data'=>['name'=>(string)$name],'debug'=>['url'=>$url]];
    }
    return ['success'=>false,'error'=>'no_results','data'=>null,'debug'=>['url'=>$url]];
}

function tomtom_route_points(float $lat1,float $lon1,float $lat2,float $lon2,string $key, array $options = []): array {
    $compute = $options['computeBestOrder'] ?? 'false';
    $url = "https://api.tomtom.com/routing/1/calculateRoute/{$lat1},{$lon1}:{$lat2},{$lon2}/json?key={$key}&computeBestOrder={$compute}&routeRepresentation=polyline&traffic=true";
    $r = [REDACTED]($url);
    if (!$r['success']) return ['success'=>false,'error'=>$r['error'],'data'=>null,'debug'=>['url'=>$url]];
    $data = $r['data'];
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
    return ['success'=>true,'error'=>null,'data'=>['points'=>$points,'length_m'=>$length_m,'time_s'=>$time_s],'debug'=>['url'=>$url]];
}

function [REDACTED](float $lat, float $lon, string $key): array {
    $url = "https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json?point={$lat},{$lon}&key={$key}";
    $r = [REDACTED]($url);
    if (!$r['success']) return ['success'=>false,'error'=>$r['error'],'data'=>null,'debug'=>['url'=>$url]];
    $data = $r['data'];
    $curr = null; $free = null;
    if (!empty($data['flowSegmentData'])) {
        $f = $data['flowSegmentData'];
        $curr = max(1.0, (float)($f['currentSpeed'] ?? 0));
        $free = max(1.0, (float)($f['freeFlowSpeed'] ?? 0));
    }
    return ['success'=>true,'error'=>null,'data'=>['currentSpeed'=>$curr,'freeFlowSpeed'=>$free],'debug'=>['url'=>$url,'raw'=>$data]];
}


/* v-sync seq: 67 */