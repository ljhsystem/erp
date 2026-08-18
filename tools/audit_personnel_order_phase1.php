<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$mode = $argv[1] ?? '--tables';

if ($mode === '--tables') {
    $identity = $pdo->query(
        'SELECT DATABASE() AS db_name, @@hostname AS hostname, @@port AS port, VERSION() AS version'
    )->fetch(PDO::FETCH_ASSOC);
    $tables = $pdo->query(
        "SELECT TABLE_NAME, TABLE_COMMENT
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND (TABLE_NAME LIKE 'user\\_%' ESCAPE '\\\\'
             OR TABLE_NAME LIKE 'institution\\_%' ESCAPE '\\\\'
             OR TABLE_NAME LIKE 'system\\_%' ESCAPE '\\\\'
             OR TABLE_NAME LIKE '%approval%'
             OR TABLE_NAME LIKE '%payroll%'
             OR TABLE_NAME LIKE '%attendance%'
             OR TABLE_NAME LIKE '%leave%'
             OR TABLE_NAME LIKE '%history%'
             OR TABLE_NAME LIKE '%assignment%')
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['identity' => $identity, 'tables' => $tables], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

if ($mode === '--schema') {
    $requested = array_slice($argv, 2);
    $result = [];
    foreach ($requested as $table) {
        $exists = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $exists->execute([':table' => $table]);
        if ((int) $exists->fetchColumn() !== 1) {
            $result[$table] = ['exists' => false];
            continue;
        }
        $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM);
        $result[$table] = ['exists' => true, 'create' => $create[1] ?? ''];
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

if ($mode === '--codes') {
    $rows = $pdo->query(
        "SELECT code_group, group_name, code, code_name, sort_no, is_active
         FROM system_codes
         WHERE code_group LIKE '%EMPLOY%'
            OR code_group LIKE '%PERSONNEL%'
            OR code_group LIKE '%ORDER%'
            OR code_group LIKE '%JOB%'
            OR code_group LIKE '%POSITION%'
            OR code_group LIKE '%DEPARTMENT%'
            OR code_group LIKE '%WORK_LOCATION%'
            OR group_name LIKE '%인사%'
            OR group_name LIKE '%발령%'
            OR group_name LIKE '%재직%'
            OR group_name LIKE '%직무%'
            OR group_name LIKE '%근무지%'
         ORDER BY code_group, sort_no"
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

if ($mode === '--employees') {
    $rows = $pdo->query(
        "SELECT e.id, e.employee_name, e.doc_hire_date, e.real_hire_date,
                e.doc_retire_date, e.real_retire_date,
                u.approved, u.is_active
         FROM user_employees e
         JOIN auth_users u ON u.id = e.user_id
         ORDER BY e.sort_no, e.id"
    )->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}

fwrite(STDERR, "사용법: php tools/audit_personnel_order_phase1.php --tables|--schema [table ...]|--codes|--employees\n");
exit(1);
