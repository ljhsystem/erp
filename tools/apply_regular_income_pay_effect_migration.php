<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($mode, ['preflight', 'up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_regular_income_pay_effect_migration.php [preflight|up|verify]');
}
$db = DbPdo::conn();
$payCount = (int) $db->query("SELECT COUNT(*) FROM institution_regular_employment_income_line_items WHERE item_type_code='PAY'")->fetchColumn();
$orphanCount = (int) $db->query('SELECT COUNT(*) FROM institution_regular_employment_income_line_items l LEFT JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id WHERE i.id IS NULL')->fetchColumn();
if ($mode === 'up') {
    if ($payCount !== 0 || $orphanCount !== 0) {
        throw new RuntimeException('미분류 PAY 또는 orphan이 있어 Migration을 중단합니다.');
    }
    $sql = file_get_contents(PROJECT_ROOT . '/app/migrations/20260822_13_add_regular_income_pay_effect_contract.up.sql');
    if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('Migration 파일을 읽을 수 없습니다.');
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $db->exec($sql);
}
$columns = $db->query("SELECT COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_line_items' AND COLUMN_NAME IN ('pay_effect_code','business_source_code','source_reference_id','source_key','business_reason','processed_at','processed_by') ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$checks = $db->query("SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('chk_regular_income_pay_effect','chk_regular_income_pay_amount','chk_regular_income_pay_business','chk_regular_income_pay_source_key') ORDER BY CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$indexes = $db->query("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_joined,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_line_items' AND INDEX_NAME='uk_regular_income_line_source' GROUP BY INDEX_NAME,NON_UNIQUE")->fetchAll(PDO::FETCH_ASSOC) ?: [];
echo json_encode(['success'=>true,'mode'=>$mode,'pay_count'=>$payCount,'orphan_count'=>$orphanCount,'columns'=>$columns,'checks'=>$checks,'indexes'=>$indexes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
