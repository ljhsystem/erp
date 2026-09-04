<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$stmt = DbPdo::conn()->query("SELECT code, code_name, is_active
    FROM system_codes
    WHERE code_group='OPERATION_TYPE'
    ORDER BY sort_no, code");
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
if ($rows === []) {
    throw new RuntimeException('OPERATION_TYPE 코드가 없습니다.');
}
foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}
