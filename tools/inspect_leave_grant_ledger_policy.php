<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

$pdo = Core\DbPdo::conn();
foreach (['institution_leave_types', 'institution_leave_grants', 'institution_leave_ledger_entries'] as $table) {
    echo $table . ' rows=' . $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() . PHP_EOL;
    $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    echo ($row[1] ?? '') . PHP_EOL;
}

$typePolicies = $pdo->query('SELECT type_code,minimum_hourly_minutes,accrual_mode_code,carryover_policy_code,carryover_limit_minutes FROM institution_leave_types ORDER BY sort_no')->fetchAll(PDO::FETCH_ASSOC) ?: [];
$grantForeignKey = $pdo->query("SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_leave_ledger_grant'")->fetch(PDO::FETCH_ASSOC) ?: null;
echo json_encode(['leave_type_policies' => $typePolicies, 'grant_foreign_key' => $grantForeignKey], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
