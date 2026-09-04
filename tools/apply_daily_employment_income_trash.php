<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$columns = static function () use ($db): array {
    $statement = $db->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS"
        . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_incomes'"
        . " AND COLUMN_NAME IN ('deleted_at','deleted_by') ORDER BY COLUMN_NAME"
    );
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []);
};

$before = $columns();
if ($before === []) {
    $db->exec((string) file_get_contents(
        PROJECT_ROOT . '/app/migrations/20260827_05_add_daily_employment_income_trash.up.sql'
    ));
} elseif ($before !== ['deleted_at', 'deleted_by']) {
    throw new RuntimeException('일용근로소득 휴지통 컬럼이 일부만 존재합니다. 수동 점검이 필요합니다.');
}

$after = $columns();
if ($after !== ['deleted_at', 'deleted_by']) {
    throw new RuntimeException('일용근로소득 휴지통 Migration 검증에 실패했습니다.');
}
$indexExists = (int) $db->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_daily_employment_incomes'"
    . " AND INDEX_NAME='idx_daily_income_deleted_at' AND COLUMN_NAME='deleted_at'"
)->fetchColumn() === 1;
if (!$indexExists) {
    throw new RuntimeException('일용근로소득 휴지통 인덱스를 확인할 수 없습니다.');
}

$model = new App\Models\Institution\DailyEmploymentIncomeModel($db);
$page = $model->page(['start' => 0, 'length' => 1]);
$trash = $model->trash();

echo json_encode([
    'success' => true,
    'before' => $before,
    'after' => $after,
    'index' => 'idx_daily_income_deleted_at',
    'list_total' => $page['total'],
    'trash_total' => count($trash),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
