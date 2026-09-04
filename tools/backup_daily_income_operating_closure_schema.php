<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$version = (string) $db->query('SELECT VERSION()')->fetchColumn();
if ($database !== 'sukhyang' || !str_starts_with($version, '10.11.11')) {
    throw new RuntimeException('승인된 운영 DB 또는 MariaDB 버전과 일치하지 않습니다.');
}

$tables = [
    'institution_daily_employment_incomes',
    'institution_daily_employment_income_groups',
    'institution_daily_employment_income_items',
    'institution_daily_employment_income_workdays',
    'institution_daily_employment_income_lines',
    'institution_daily_employment_income_calculation_revisions',
    'institution_daily_employment_income_calculation_results',
    'ledger_evidence_daily_employment_income',
    'ledger_transactions',
    'ledger_evidence_links',
    'user_approval_templates',
    'user_approval_template_steps',
    'user_approval_requests',
];

$capturedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));
$output = "-- Schema-only backup before approved daily employment income Closure migrations\n";
$output .= '-- database: ' . $database . "\n";
$output .= '-- captured_at: ' . $capturedAt->format(DATE_ATOM) . "\n";
$output .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$capturedTables = [];
foreach ($tables as $table) {
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table');
    $statement->execute([':table' => $table]);
    if ((int) $statement->fetchColumn() !== 1) {
        continue;
    }
    $row = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
    if (!is_array($row) || !isset($row[1])) {
        throw new RuntimeException($table . ' Schema를 백업할 수 없습니다.');
    }
    $output .= '-- table: ' . $table . "\n" . $row[1] . ";\n\n";
    $capturedTables[] = $table;
}
$output .= "SET FOREIGN_KEY_CHECKS=1;\n";

$directory = PROJECT_ROOT . '/storage/db_backup';
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Schema 백업 디렉터리를 만들 수 없습니다.');
}
$path = $directory . '/20260831_daily_income_closure_schema_before_' . $capturedAt->format('Ymd_His') . '.sql';
$bytes = file_put_contents($path, $output, LOCK_EX);
if ($bytes === false) {
    throw new RuntimeException('Schema 백업 파일을 저장할 수 없습니다.');
}

echo json_encode([
    'success' => true,
    'database' => $database,
    'mariadb_version' => $version,
    'captured_at' => $capturedAt->format(DATE_ATOM),
    'path' => str_replace('\\', '/', $path),
    'size_bytes' => $bytes,
    'sha256' => hash_file('sha256', $path),
    'captured_tables' => $capturedTables,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
