<?php
// get_trains.php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    $pdo = get_pdo();
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `trains` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `train_name` VARCHAR(100) NOT NULL,
          `operator_name` VARCHAR(50) NOT NULL,
          `origin_city` VARCHAR(100) NOT NULL,
          `dest_city` VARCHAR(100) NOT NULL,
          `departure_time` TIME NOT NULL,
          `arrival_time` TIME NOT NULL,
          `base_fare` DECIMAL(10,2) NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $ie) { /* ignore */ }

    $stmt = $pdo->query('SELECT id, train_name, operator_name, origin_city, dest_city, departure_time, arrival_time, base_fare FROM trains ORDER BY id DESC');
    $rows = $stmt->fetchAll();
    echo json_encode(['success'=>true, 'error'=>null, 'count'=>count($rows), 'data'=>$rows]);
} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'error'=>'Server error', 'data'=>[], 'count'=>0]);
}
