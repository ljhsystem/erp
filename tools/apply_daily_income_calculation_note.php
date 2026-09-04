<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';

use Core\Database;

$db = Database::getInstance()->getConnection();
$table = 'institution_daily_employment_income_workdays';
$migration = PROJECT_ROOT . '/app/migrations/20260828_01_add_daily_income_calculation_note.up.sql';
$expected = <<<'SQL'
ALTER TABLE institution_daily_employment_income_workdays
    ADD COLUMN calculation_note VARCHAR(500) NULL
        COMMENT '지급액 산정 특이사항, 선택 입력'
        AFTER non_taxable_amount,
    ADD CONSTRAINT ck_daily_workday_calculation_note
        CHECK (
            calculation_note IS NULL
            OR (
                CHAR_LENGTH(calculation_note) BETWEEN 1 AND 500
                AND OCTET_LENGTH(calculation_note)
                    = OCTET_LENGTH(TRIM(calculation_note))
            )
        );
SQL;
$sql = trim((string) file_get_contents($migration));
if ($sql !== trim($expected)) throw new RuntimeException('Migration 내용이 승인 DDL과 일치하지 않습니다.');

$rowCount = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
$beforeRows = $db->query(
    "SELECT id,daily_rate_amount,allowance_amount,actual_work_minutes FROM {$table} ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);
$columns = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
$similar = array_values(array_filter($columns, static fn(string $name): bool => preg_match('/(note|description|evidence|proof|basis|support|attachment)/i', $name) === 1));
$checkCount = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND CONSTRAINT_NAME='ck_daily_workday_calculation_note'")->fetchColumn();
if ($rowCount !== 5 || in_array('calculation_note', $columns, true) || $similar !== [] || $checkCount !== 0) {
    throw new RuntimeException(json_encode(['row_count' => $rowCount, 'calculation_note_exists' => in_array('calculation_note', $columns, true), 'similar_columns' => $similar, 'check_count' => $checkCount], JSON_UNESCAPED_UNICODE));
}

$indexesBefore = $db->query("SHOW INDEX FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
$db->exec($sql);
$column = $db->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,ORDINAL_POSITION,COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='calculation_note'")->fetch(PDO::FETCH_ASSOC);
$check = $db->query("SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_daily_workday_calculation_note'")->fetch(PDO::FETCH_ASSOC);
$indexesAfter = $db->query("SHOW INDEX FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
$nullCount = (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE calculation_note IS NULL")->fetchColumn();
$fixtureId = (string) ($beforeRows[0]['id'] ?? '');
$blankBlocked = false;
$normalAccepted = false;
if ($fixtureId !== '') {
    $db->beginTransaction();
    try {
        try {
            $statement = $db->prepare("UPDATE {$table} SET calculation_note='   ' WHERE id=:id");
            $statement->execute([':id' => $fixtureId]);
        } catch (PDOException) {
            $blankBlocked = true;
        }
        $statement = $db->prepare("UPDATE {$table} SET calculation_note=:note WHERE id=:id");
        $statement->execute([':note' => '정상 산정내역', ':id' => $fixtureId]);
        $normalAccepted = true;
    } finally {
        $db->rollBack();
    }
}
$afterRows = $db->query(
    "SELECT id,daily_rate_amount,allowance_amount,actual_work_minutes FROM {$table} ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);
$gross = (float) $db->query('SELECT total_gross_amount FROM institution_daily_employment_incomes WHERE status_code=\'DRAFT\' AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 1')->fetchColumn();
echo json_encode([
    'precheck' => ['row_count' => $rowCount, 'similar_columns' => $similar, 'check_count' => $checkCount, 'migration_exact' => true],
    'column' => $column,
    'check' => $check,
    'row_count_after' => (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(),
    'null_count_after' => $nullCount,
    'existing_values_unchanged' => $beforeRows === $afterRows,
    'draft_total_gross_amount' => $gross,
    'blank_blocked' => $blankBlocked,
    'normal_value_accepted' => $normalAccepted,
    'fixture_residue_count' => (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE calculation_note IS NOT NULL")->fetchColumn(),
    'indexes_unchanged' => $indexesBefore === $indexesAfter,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
