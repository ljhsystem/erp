<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$tables = [
    'institution_educations_courses',
    'institution_educations_employee_records',
    'institution_educations_audits',
    'user_employees',
];
$result = ['tables' => [], 'counts' => [], 'new_tables_absent' => true];

foreach ($tables as $table) {
    $row = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
    $result['tables'][$table] = $row[1] ?? null;
    $result['counts'][$table] = (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

foreach (['institution_educations_sessions', 'institution_educations_session_targets'] as $table) {
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    $statement->execute([':table' => $table]);
    if ((int) $statement->fetchColumn() > 0) {
        $result['new_tables_absent'] = false;
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
