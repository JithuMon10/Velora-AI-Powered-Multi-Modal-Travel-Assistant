<?php
// helpers/ai.php
// Single source of truth for Gemini calls per spec.

// call_gemini($prompt):
// - Endpoint: v1beta/models/gemini-2.5-flash:generateContent
// - Header: X-Goog-Api-Key from config.php ($API_KEYS['gemini']) or env VELO_GEMINI_KEY
// - Body: {"contents":[{"parts":[{"text": $prompt}]}]}
// - Parse: return json_decode(text) or null
// - Always log raw API response to backend/logs/ai_debug.log

function ai_get_api_key_simple(): string {
    // 1) Environment variable takes precedence
    $k = getenv('VELO_GEMINI_KEY');
    if ($k && trim($k) !== '') return trim($k);

    // 2) If already present in globals (config loaded earlier), use it
    if (isset($GLOBALS['API_KEYS']['gemini']) && trim((string)$GLOBALS['API_KEYS']['gemini']) !== '') {
        return trim((string)$GLOBALS['API_KEYS']['gemini']);
    }

    // 3) Try loading config.php; note include inside function scopes variables locally
    $cfg = __DIR__ . '/../config.php';
    if (is_file($cfg)) { @require_once $cfg; }

    // 4) Re-check globals in case config populated it
    if (isset($GLOBALS['API_KEYS']['gemini']) && trim((string)$GLOBALS['API_KEYS']['gemini']) !== '') {
        return trim((string)$GLOBALS['API_KEYS']['gemini']);
    }
    // 5) Also check local variable created by include-from-function
    if (isset($API_KEYS) && isset($API_KEYS['gemini']) && trim((string)$API_KEYS['gemini']) !== '') {
        return trim((string)$API_KEYS['gemini']);
    }
    return '';
}

function ai_log_raw(string $prompt, string $raw): void {
    try {
        $dir = dirname(__DIR__) . '/../logs'; // backend/logs
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ai_debug.log';
        $line = '['.date('c')."] v1beta/gemini-2.5-flash\nPROMPT:\n".$prompt."\nRESPONSE:\n".$raw."\n\n";
        @file_put_contents($file, $line, FILE_APPEND);
    } catch (Throwable $e) { /* ignore */ }
}

function call_gemini(string $prompt) {
    $apiKey = ai_get_api_key_simple();
    if ($apiKey === '') return ['success'=>false,'error'=>'No API key','raw'=>''];
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    $instruction = "You are a JSON-only service. Output STRICT JSON only. No explanations, no markdown, no extra text. If you cannot answer, output {\"error\":\"unavailable\"}.";
    $wrapped = $instruction."\n\nPROMPT: ".$prompt;
    $payload = json_encode([
        'contents' => [[ 'parts' => [[ 'text' => $wrapped ]]]],
        'generationConfig' => [ 'responseMimeType' => 'application/json' ]
    ], JSON_UNESCAPED_SLASHES);
    try {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        if ($response === false) { $response = 'curl_error: '.curl_error($ch); }
        curl_close($ch);
        ai_log_raw($prompt, (string)$response);
        $data = json_decode($response, true);
        // If API returned an error object, surface it directly
        if (is_array($data) && isset($data['error'])) {
            return ['success'=>false,'error'=>'API error','raw'=>$response];
        }
        $text = is_array($data) ? ($data['candidates'][0]['content']['parts'][0]['text'] ?? '') : '';
        $parsed = null;
        if ($text) {
            $jsonStr = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', trim($text));
            // Strip UTF-8 BOM if present
            $jsonStr = preg_replace('/^\xEF\xBB\xBF/', '', $jsonStr);
            $parsed = json_decode($jsonStr, true);
        }
        if (is_array($parsed)) return $parsed;
        // One retry with explicit JSON-only instruction
        $retryPrompt = $instruction."\n\nPROMPT: ".$prompt."\nRespond ONLY with valid JSON. No markdown, no code fences, no commentary.";
        $retryPayload = json_encode([
            'contents' => [[ 'parts' => [[ 'text' => $retryPrompt ]]]],
            'generationConfig' => [ 'responseMimeType' => 'application/json' ]
        ], JSON_UNESCAPED_SLASHES);
        $ch2 = curl_init($url);
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $retryPayload,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response2 = curl_exec($ch2);
        if ($response2 === false) { $response2 = 'curl_error: '.curl_error($ch2); }
        curl_close($ch2);
        ai_log_raw($retryPrompt, (string)$response2);
        $data2 = json_decode($response2, true);
        if (is_array($data2) && isset($data2['error'])) {
            return ['success'=>false,'error'=>'API error','raw'=>$response2];
        }
        $text2 = is_array($data2) ? ($data2['candidates'][0]['content']['parts'][0]['text'] ?? '') : '';
        if ($text2) {
            $jsonStr2 = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', trim($text2));
            $jsonStr2 = preg_replace('/^\xEF\xBB\xBF/', '', $jsonStr2);
            $parsed2 = json_decode($jsonStr2, true);
            if (is_array($parsed2)) return $parsed2;
            // Parsing failed on retry -> structured error
            ai_log_raw($retryPrompt, 'parse_failed_raw: '.$text2);
            return ['success'=>false,'error'=>'AI parse failed','raw'=>$text2];
        }
        // No text -> structured error with full raw
        return ['success'=>false,'error'=>'AI parse failed','raw'=>$response2];
    } catch (Throwable $e) {
        ai_log_raw($prompt, 'exception: '.$e->getMessage());
        return ['success'=>false,'error'=>'AI exception','raw'=>$e->getMessage()];
    }
}
