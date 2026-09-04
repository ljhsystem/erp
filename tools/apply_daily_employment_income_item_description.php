<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$count = static fn(string $sql): int => (int) $db->query($sql)->fetchColumn();
$columnExists = static fn(string $table, string $column): bool => $count(
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()"
    . " AND TABLE_NAME=" . $db->quote($table) . " AND COLUMN_NAME=" . $db->quote($column)
) === 1;
$constraintExists = static fn(string $table, string $constraint): bool => $count(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()"
    . " AND TABLE_NAME=" . $db->quote($table) . " AND CONSTRAINT_NAME=" . $db->quote($constraint)
) === 1;
$indexExists = static fn(string $table, string $index): bool => $count(
    "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()"
    . " AND TABLE_NAME=" . $db->quote($table) . " AND INDEX_NAME=" . $db->quote($index)
) > 0;

$before = [
    'headers' => $count('SELECT COUNT(*) FROM institution_daily_employment_incomes'),
    'groups' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_groups'),
    'items' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_items'),
    'header_document_number' => $columnExists('institution_daily_employment_incomes', 'document_number'),
    'group_work_description' => $columnExists('institution_daily_employment_income_groups', 'work_description'),
    'item_work_description' => $columnExists('institution_daily_employment_income_items', 'work_description'),
    'item_work_type_code' => $columnExists('institution_daily_employment_income_items', 'work_type_code'),
];

if (!$before['header_document_number'] && !$before['group_work_description']
    && $before['item_work_description'] && $before['item_work_type_code']) {
    echo json_encode(['migration'=>'20260827_12_move_daily_income_work_description_to_item','status'=>'already_applied','before'=>$before], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}
if (!$before['header_document_number'] || !$before['group_work_description']
    || $before['item_work_description'] || $before['item_work_type_code']) {
    throw new RuntimeException('일용근로소득 작업내용·문서번호 스키마가 예상한 적용 전 상태와 다릅니다.');
}
if ($before['headers'] !== 0 || $before['groups'] !== 0 || $before['items'] !== 0) {
    throw new RuntimeException('운영 자료가 존재하여 무손실 자동 전환 조건을 충족하지 않습니다.');
}

try {
    $db->exec((string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260827_12_move_daily_income_work_description_to_item.up.sql'));
} catch (Throwable $exception) {
    if (!$columnExists('institution_daily_employment_incomes', 'document_number')) {
        $db->exec('ALTER TABLE institution_daily_employment_incomes ADD COLUMN document_number VARCHAR(50) NOT NULL AFTER payment_date');
    }
    if (!$indexExists('institution_daily_employment_incomes', 'uq_daily_income_document_number')) {
        $db->exec('ALTER TABLE institution_daily_employment_incomes ADD UNIQUE KEY uq_daily_income_document_number (company_id,document_number)');
    }
    if (!$columnExists('institution_daily_employment_income_groups', 'work_description')) {
        $db->exec("ALTER TABLE institution_daily_employment_income_groups ADD COLUMN work_description VARCHAR(500) NOT NULL DEFAULT '기존 자료 이관' AFTER work_team_id");
        $db->exec('ALTER TABLE institution_daily_employment_income_groups ALTER COLUMN work_description DROP DEFAULT');
    }
    if (!$constraintExists('institution_daily_employment_income_groups', 'ck_daily_income_group_description')) {
        $db->exec('ALTER TABLE institution_daily_employment_income_groups ADD CONSTRAINT ck_daily_income_group_description CHECK (CHAR_LENGTH(TRIM(work_description)) > 0)');
    }
    if ($constraintExists('institution_daily_employment_income_items', 'ck_daily_income_item_description')) {
        $db->exec('ALTER TABLE institution_daily_employment_income_items DROP CONSTRAINT ck_daily_income_item_description');
    }
    if ($indexExists('institution_daily_employment_income_items', 'idx_daily_income_item_work_type')) {
        $db->exec('ALTER TABLE institution_daily_employment_income_items DROP INDEX idx_daily_income_item_work_type');
    }
    if ($columnExists('institution_daily_employment_income_items', 'work_description')) {
        $db->exec('ALTER TABLE institution_daily_employment_income_items DROP COLUMN work_description');
    }
    if ($columnExists('institution_daily_employment_income_items', 'work_type_code')) {
        $db->exec('ALTER TABLE institution_daily_employment_income_items DROP COLUMN work_type_code');
    }
    throw new RuntimeException('Migration 적용에 실패하여 적용 전 스키마로 복구했습니다.', 0, $exception);
}

$after = [
    'headers' => $count('SELECT COUNT(*) FROM institution_daily_employment_incomes'),
    'groups' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_groups'),
    'items' => $count('SELECT COUNT(*) FROM institution_daily_employment_income_items'),
    'header_document_number' => $columnExists('institution_daily_employment_incomes', 'document_number'),
    'group_work_description' => $columnExists('institution_daily_employment_income_groups', 'work_description'),
    'item_work_description' => $columnExists('institution_daily_employment_income_items', 'work_description'),
    'item_work_type_code' => $columnExists('institution_daily_employment_income_items', 'work_type_code'),
];
if ($before['headers'] !== $after['headers'] || $before['groups'] !== $after['groups'] || $before['items'] !== $after['items']) {
    throw new RuntimeException('Migration 전후 원천 건수가 일치하지 않습니다.');
}
if ($after['header_document_number'] || $after['group_work_description']
    || !$after['item_work_description'] || !$after['item_work_type_code']) {
    throw new RuntimeException('Migration 적용 후 컬럼 구조가 확정 계약과 다릅니다.');
}
echo json_encode(['migration'=>'20260827_12_move_daily_income_work_description_to_item','status'=>'applied','before'=>$before,'after'=>$after], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), PHP_EOL;
