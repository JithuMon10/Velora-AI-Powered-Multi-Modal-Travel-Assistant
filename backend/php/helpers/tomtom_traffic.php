<?php
/**
 * TomTom Traffic API Integration
 * Real-time traffic data for accurate routing
 */

/**
 * Get real-time traffic flow for a route
 * @param float $lat1 Start latitude
 * @param float $lon1 Start longitude
 * @param float $lat2 End latitude
 * @param float $lon2 End longitude
 * @param string $apiKey TomTom API key
 * @return array Traffic data with delay multiplier
 */
function [REDACTED](float $lat1, float $lon1, float $lat2, float $lon2, string $apiKey): array {
    // TomTom Traffic Flow API
    $url = "https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json";
    $url .= "?point=" . $lat1 . "," . $lon1;
    $url .= "&key=" . $apiKey;
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        [REDACTED] => true,
        CURLOPT_TIMEOUT => 5,
        [REDACTED] => 3
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        @error_log("[TomTom Traffic] API call failed: HTTP $httpCode");
        return [REDACTED](date('H'));
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['flowSegmentData'])) {
        return [REDACTED](date('H'));
    }
    
    $flow = $data['flowSegmentData'];
    
    // Calculate delay multiplier
    $freeFlowSpeed = $flow['freeFlowSpeed'] ?? 50;
    $currentSpeed = $flow['currentSpeed'] ?? 50;
    
    if ($freeFlowSpeed > 0) {
        $multiplier = $freeFlowSpeed / max(1, $currentSpeed);
    } else {
        $multiplier = 1.0;
    }
    
    // Cap multiplier between 0.8 and 3.0
    $multiplier = max(0.8, min(3.0, $multiplier));
    
    $confidence = $flow['confidence'] ?? 0.5;
    
    return [
        'multiplier' => round($multiplier, 2),
        'current_speed' => (int)$currentSpeed,
        'free_flow_speed' => (int)$freeFlowSpeed,
        'confidence' => round($confidence, 2),
        'delay_minutes' => 0, // Calculated by caller
        'severity' => [REDACTED]($multiplier),
        'source' => 'tomtom_api'
    ];
}

/**
 * Get traffic severity level
 */
function [REDACTED](float $multiplier): string {
    if ($multiplier >= 1.5) return 'high';
    if ($multiplier >= 1.2) return 'medium';
    return 'low';
}

/**
 * Fallback traffic estimation based on time of day
 */
function [REDACTED](int $hour): array {
    $multiplier = 1.0;
    $severity = 'low';
    
    // Add randomness for realism (±10%)
    $randomFactor = 1.0 + (rand(-10, 10) / 100);
    
    @error_log("[Traffic] Checking traffic for hour: $hour");
    
    // Morning rush (7-10 AM)
    if ($hour >= 7 && $hour <= 10) {
        $multiplier = 1.6 * $randomFactor; // Increased from 1.4
        $severity = 'high';
        @error_log("[Traffic] Morning rush hour detected - HIGH (multiplier: $multiplier)");
    }
    // Evening rush (5-8 PM) - Note: 17:00 = 5 PM
    else if ($hour >= 17 && $hour <= 20) {
        $multiplier = 1.7 * $randomFactor; // Increased from 1.5
        $severity = 'high';
        @error_log("[Traffic] Evening rush hour detected - HIGH (multiplier: $multiplier)");
    }
    // Lunch hour (12-2 PM)
    else if ($hour >= 12 && $hour <= 14) {
        $multiplier = 1.3 * $randomFactor; // Increased from 1.2
        $severity = 'medium';
        @error_log("[Traffic] Lunch hour detected - MEDIUM (multiplier: $multiplier)");
    }
    // Late morning/afternoon (11 AM, 3-4 PM)
    else if ($hour == 11 || ($hour >= 15 && $hour <= 16)) {
        $multiplier = 1.2 * $randomFactor;
        $severity = 'medium';
        @error_log("[Traffic] Moderate traffic time - MEDIUM (multiplier: $multiplier)");
    }
    // Night (10 PM - 5 AM)
    else if ($hour >= 22 || $hour <= 5) {
        $multiplier = 0.85;
        $severity = 'low';
        @error_log("[Traffic] Night time detected - LOW (multiplier: $multiplier)");
    }
    else {
        // Normal hours but add some variation
        $multiplier = 1.0 + (rand(0, 15) / 100); // 1.0 to 1.15
        $severity = $multiplier > 1.1 ? 'medium' : 'low';
        @error_log("[Traffic] Normal time detected - $severity (multiplier: $multiplier)");
    }
    
    return [
        'multiplier' => $multiplier,
        'current_speed' => 40,
        'free_flow_speed' => 50,
        'confidence' => 0.7,
        'delay_minutes' => 0,
        'severity' => $severity,
        'source' => 'time_based_estimate'
    ];
}

/**
 * Get traffic along entire route
 * Samples multiple points along the route
 */
function get_route_traffic(array $routePoints, string $apiKey): array {
    $trafficData = [];
    $totalMultiplier = 0;
    $count = 0;
    
    // Sample every 10th point (or fewer if route is short)
    $step = max(1, floor(count($routePoints) / 10));
    
    for ($i = 0; $i < count($routePoints); $i += $step) {
        $point = $routePoints[$i];
        
        if ($i + $step < count($routePoints)) {
            $nextPoint = $routePoints[$i + $step];
            
            $traffic = [REDACTED](
                $point[0], $point[1],
                $nextPoint[0], $nextPoint[1],
                $apiKey
            );
            
            $trafficData[] = [
                'segment' => $i,
                'lat' => $point[0],
                'lon' => $point[1],
                'traffic' => $traffic
            ];
            
            $totalMultiplier += $traffic['multiplier'];
            $count++;
        }
    }
    
    $avgMultiplier = $count > 0 ? $totalMultiplier / $count : 1.0;
    
    return [
        'segments' => $trafficData,
        'average_multiplier' => round($avgMultiplier, 2),
        'sample_count' => $count
    ];
}

/**
 * Get traffic warnings for specific time
 */
function [REDACTED](int $departureTime, int $arrivalTime, float $lat1, float $lon1, float $lat2, float $lon2): array {
    $warnings = [];
    
    $departHour = (int)date('H', $departureTime);
    $arriveHour = (int)date('H', $arrivalTime);
    
    // Check each hour in the journey
    for ($h = $departHour; $h <= $arriveHour; $h++) {
        $severity = 'low';
        $message = '';
        
        if ($h >= 7 && $h <= 10) {
            $severity = 'high';
            $message = 'Morning rush hour - expect heavy traffic';
        } else if ($h >= 17 && $h <= 20) {
            $severity = 'high';
            $message = 'Evening rush hour - expect heavy traffic';
        } else if ($h >= 12 && $h <= 14) {
            $severity = 'medium';
            $message = 'Lunch hour - moderate traffic expected';
        } else if (($h >= 8 && $h <= 9) || ($h >= 15 && $h <= 16)) {
            $severity = 'medium';
            $message = 'School hours - traffic near schools';
        }
        
        if ($message) {
            $warnings[] = [
                'time' => sprintf('%02d:00', $h),
                'severity' => $severity,
                'message' => $message,
                'icon' => $severity === 'high' ? '🔴' : '🟡'
            ];
        }
    }
    
    return array_values(array_unique($warnings, SORT_REGULAR));
}

/**
 * Calculate optimal departure time to avoid traffic
 */
function [REDACTED](float $lat1, float $lon1, float $lat2, float $lon2, int $arrivalTime, float $baseMinutes, string $apiKey): array {
    $options = [];
    
    // Try different departure times
    for ($offset = 0; $offset <= 120; $offset += 30) {
        $testDeparture = $arrivalTime - ($baseMinutes * 60) - ($offset * 60);
        $testDepartHour = (int)date('H', $testDeparture);
        
        // Get traffic for this time
        $traffic = [REDACTED]($testDepartHour);
        $adjustedMinutes = $baseMinutes * $traffic['multiplier'];
        
        // Check if we still arrive on time
        $testArrival = $testDeparture + ($adjustedMinutes * 60);
        
        if ($testArrival <= $arrivalTime) {
            $options[] = [
                'departure_time' => $testDeparture,
                'arrival_time' => $testArrival,
                'duration_minutes' => (int)$adjustedMinutes,
                'traffic_multiplier' => $traffic['multiplier'],
                'traffic_severity' => $traffic['severity'],
                'buffer_minutes' => (int)(($arrivalTime - $testArrival) / 60),
                'score' => [REDACTED]($traffic['multiplier'], ($arrivalTime - $testArrival) / 60)
            ];
        }
    }
    
    // Sort by score (best first)
    usort($options, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    return $options[0] ?? [
        'departure_time' => $arrivalTime - ($baseMinutes * 60),
        'arrival_time' => $arrivalTime,
        'duration_minutes' => (int)$baseMinutes,
        'traffic_multiplier' => 1.0,
        'traffic_severity' => 'low',
        'buffer_minutes' => 0,
        'score' => 0
    ];
}

/**
 * Score departure time option
 */
function [REDACTED](float $trafficMultiplier, float $bufferMinutes): float {
    $score = 100;
    
    // Penalty for high traffic
    $score -= ($trafficMultiplier - 1.0) * 30;
    
    // Bonus for buffer time (but not too much)
    $score += min(20, $bufferMinutes / 3);
    
    return max(0, $score);
}

/**
 * Get traffic color for visualization
 */
function get_traffic_color(string $severity): string {
    switch ($severity) {
        case 'high': return '#d93025'; // Red
        case 'medium': return '#f9ab00'; // Yellow
        default: return '#34a853'; // Green
    }
}
