<?php
// backend/php/admin/data_counts.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$out = [
  'success' => true,
  'counts' => [
    'stops' => 0,
    'buses' => 0,
    'trains' => 0,
    'airports' => 0,
    'hotels' => 0,
    'synthetic_stops' => 0
  ],
  'missing_table' => []
];
try {
  $pdo = get_pdo();
  $tables = [
    'bus_stations' => 'stops',
    'buses' => 'buses',
    'railway_stations' => 'trains',
    'airports' => 'airports',
    'hotels' => 'hotels'
  ];
  foreach ($tables as $table => $key) {
    try {
      $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `$table`");
      $row = $stmt ? $stmt->fetch() : null;
      $out['counts'][$key] = isset($row['c']) ? (int)$row['c'] : 0;
    } catch (Throwable $te) {
      $out['counts'][$key] = 0;
      $out['missing_table'][] = $table;
    }
  }
  // synthetic_stops is not a table; if you track elsewhere, compute here. Keep 0 as placeholder.
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Server error']);
  exit;
}

echo json_encode($out);
