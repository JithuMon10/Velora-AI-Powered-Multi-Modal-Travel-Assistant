<?php
header('Content-Type: application/json');
echo json_encode(['ok'=>true,'ts'=>date('c')]);
