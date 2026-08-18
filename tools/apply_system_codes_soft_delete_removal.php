<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$apply = in_array('--apply', $argv, true);
$db = Core\DbPdo::conn();

$columnStatement = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_codes'"
    . " AND COLUMN_NAME IN ('deleted_at','deleted_by') ORDER BY ORDINAL_POSITION"
);
$columns = $columnStatement->fetchAll(PDO::FETCH_COLUMN) ?: [];
$deletedRows = in_array('deleted_at', $columns, true)
    ? (int) $db->query('SELECT COUNT(*) FROM system_codes WHERE deleted_at IS NOT NULL')->fetchColumn()
    : 0;

if ($apply && $columns !== []) {
    if (!in_array('deleted_at', $columns, true) || !in_array('deleted_by', $columns, true)) {
        throw new RuntimeException('system_codes soft-delete 컬럼 구성이 불완전합니다.');
    }

    $sql = file_get_contents(
        PROJECT_ROOT . '/app/migrations/20260812_02_remove_system_codes_soft_delete.up.sql'
    );
    if ($sql === false) {
        throw new RuntimeException('코드 soft-delete 제거 Migration을 읽을 수 없습니다.');
    }
    $db->exec($sql);
}

$remainingStatement = $db->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_codes'"
    . " AND COLUMN_NAME IN ('deleted_at','deleted_by')"
);

echo json_encode([
    'success' => true,
    'mode' => $apply ? 'apply' : 'audit',
    'removed_soft_deleted_rows' => $apply ? $deletedRows : 0,
    'soft_deleted_rows' => $deletedRows,
    'remaining_soft_delete_columns' => (int) $remainingStatement->fetchColumn(),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
