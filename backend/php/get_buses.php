<?php
// get_buses.php
// Returns buses from velora_db.buses with optional filters: state, operator

header('Content-Type: application/json');
// Simple CORS for local testing
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header([REDACTED]: ' . $_SERVER['HTTP_ORIGIN']);
    header([REDACTED]: true');
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header([REDACTED]: GET, OPTIONS');
    header([REDACTED]: Content-Type');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed. Use GET.', 'data'=>[], 'count'=>0]);
        exit;
    }

    $pdo = get_pdo();

    $state = isset($_GET['state']) ? trim($_GET['state']) : '';
    $operator = isset($_GET['operator']) ? trim($_GET['operator']) : '';

    $where = [];
    $params = [];
    if ($state !== '') {
        $where[] = 'state = :state';
        $params[':state'] = $state;
    }
    if ($operator !== '') {
        $where[] = 'operator = :operator';
        $params[':operator'] = $operator;
    }

    // Ensure buses table exists (MySQL)
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS `buses` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `state` VARCHAR(100) NOT NULL,
          `operator` VARCHAR(150) NOT NULL,
          `category` VARCHAR(150) NOT NULL,
          `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_state` (`state`),
          KEY `idx_operator` (`operator`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) { /* ignore */ }

    // Ensure operator_cache exists for caching AI/operator lookups
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

    // Seed/refresh buses from CSV if present and table empty (cached)
    $csv = __DIR__ . DIRECTORY_SEPARATOR . 'bus_data.csv';
    try {
        static $CSV_LOADED = false;
        $count = (int)($pdo->query('SELECT COUNT(*) AS c FROM buses')->fetch()['c'] ?? 0);
        if (!$CSV_LOADED && $count === 0 && file_exists($csv)) {
            $fh = fopen($csv, 'r');
            if ($fh) {
                // skip header
                $header = fgetcsv($fh);
                $ins = $pdo->prepare('INSERT INTO buses(state, operator, category) VALUES (?, ?, ?)');
                while (($row = fgetcsv($fh)) !== false) {
                    $st = trim((string)($row[0] ?? ''));
                    $op = trim((string)($row[1] ?? ''));
                    $cat= trim((string)($row[2] ?? 'State Transport'));
                    if ($st !== '' && $op !== '') { $ins->execute([$st, $op, $cat]); }
                }
                fclose($fh);
                $CSV_LOADED = true;
            }
        }
    } catch (Throwable $e) { /* ignore CSV seed errors */ }

    // Cache operators per state into operator_cache (mode=bus) if not present
    try {
        $rowsOps = $pdo->query('SELECT DISTINCT state, operator FROM buses')->fetchAll();
        $insOp = $pdo->prepare('INSERT INTO operator_cache(state, mode, operator_name) VALUES (?,?,?)');
        foreach ($rowsOps as $r){
            $s = (string)$r['state']; $o = (string)$r['operator'];
            if ($s !== '' && $o !== '') { $insOp->execute([$s, 'bus', $o]); }
        }
    } catch (Throwable $e) { /* ignore cache fill errors */ }

    $sql = 'SELECT state, operator, category, created_at FROM buses';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable $qe) {
        echo json_encode(['success'=>false, 'error'=>'Query failed', 'data'=>[], 'count'=>0]);
        exit;
    }

    // Build operators_by_state mapping
    $opsByState = [];
    try {
        $rowsOps = $pdo->query('SELECT state, operator FROM buses')->fetchAll();
        foreach ($rowsOps as $r){
            $s = (string)$r['state']; $o = (string)$r['operator'];
            if ($s!=='' && $o!==''){ if (!isset($opsByState[$s])) $opsByState[$s]=[]; if (!in_array($o,$opsByState[$s],true)) $opsByState[$s][]=$o; }
        }
    } catch (Throwable $e) { /* ignore */ }

    echo json_encode(['success' => true, 'error'=>null, 'count' => count($rows), 'data' => $rows, 'operators_by_state'=>$opsByState]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error', 'data'=>[], 'count'=>0]);
}
