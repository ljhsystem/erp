<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$count = static fn(string $sql): int => (int) $db->query($sql)->fetchColumn();
$columnExists = static fn(): bool => $count(
    "SELECT COUNT(*) FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA = DATABASE()"
    . " AND TABLE_NAME = 'institution_daily_employment_income_groups'"
    . " AND COLUMN_NAME = 'default_daily_rate'"
) === 1;
$constraintExists = static fn(): bool => $count(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS"
    . " WHERE CONSTRAINT_SCHEMA = DATABASE()"
    . " AND TABLE_NAME = 'institution_daily_employment_income_groups'"
    . " AND CONSTRAINT_NAME = 'ck_daily_income_group_rate'"
) === 1;

$before = [
    'groups' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_groups'),
    'items' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_items'),
    'workdays' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_workdays'),
    'column_exists' => $columnExists(),
    'constraint_exists' => $constraintExists(),
];

if (!$before['column_exists'] && !$before['constraint_exists']) {
    echo json_encode([
        'migration' => '20260827_11_remove_daily_employment_income_group_default_rate',
        'status' => 'already_applied',
        'before' => $before,
        'after' => $before,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

if (!$before['column_exists'] || !$before['constraint_exists']) {
    throw new RuntimeException('컬럼과 제약조건의 적용 상태가 서로 일치하지 않습니다.');
}

$sql = (string) file_get_contents(
    PROJECT_ROOT . '/app/migrations/20260827_11_remove_daily_employment_income_group_default_rate.up.sql'
);
$db->exec($sql);

$after = [
    'groups' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_groups'),
    'items' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_items'),
    'workdays' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_workdays'),
    'column_exists' => $columnExists(),
    'constraint_exists' => $constraintExists(),
];

foreach (['groups', 'items', 'workdays'] as $key) {
    if ($before[$key] !== $after[$key]) {
        throw new RuntimeException('Migration 전후 원천 건수가 일치하지 않습니다: ' . $key);
    }
}
if ($after['column_exists'] || $after['constraint_exists']) {
    throw new RuntimeException('기본단가 컬럼 또는 제약조건이 제거되지 않았습니다.');
}

echo json_encode([
    'migration' => '20260827_11_remove_daily_employment_income_group_default_rate',
    'status' => 'applied',
    'before' => $before,
    'after' => $after,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
