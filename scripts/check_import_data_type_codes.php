<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

require PROJECT_ROOT . '/vendor/autoload.php';

$pdo = \Core\DbPdo::conn();
$stmt = $pdo->prepare("
    SELECT code_group, code, code_name, is_active
    FROM system_codes
    WHERE code_group IN ('IMPORT_DATA_TYPE', 'TRANSACTION_TYPE')
    ORDER BY code_group, sort_no
");
$stmt->execute();

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['code_group'] . '|' . $row['code'] . '|' . $row['code_name'] . '|' . $row['is_active'] . PHP_EOL;
}
