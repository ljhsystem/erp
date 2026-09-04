<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($mode, ['preflight', 'up', 'verify', 'fixture', 'report-2013'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_regular_income_historical_snapshot_migration.php [preflight|up|verify|fixture|report-2013]');
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$counts = static fn(PDO $pdo): array => [
    'items' => (int) $pdo->query('SELECT COUNT(*) FROM institution_regular_employment_income_items')->fetchColumn(),
    'lines' => (int) $pdo->query('SELECT COUNT(*) FROM institution_regular_employment_income_line_items')->fetchColumn(),
];
$before = $counts($db);
$violations = (int) $db->query("SELECT COUNT(*) FROM institution_regular_employment_income_line_items WHERE final_amount<>calculated_amount+adjustment_amount OR (adjustment_amount<>0 AND (adjustment_reason IS NULL OR CHAR_LENGTH(TRIM(adjustment_reason))=0))")->fetchColumn();
$orphans = (int) $db->query('SELECT COUNT(*) FROM institution_regular_employment_income_line_items l LEFT JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id WHERE i.id IS NULL')->fetchColumn();

if ($mode === 'up') {
    if ($violations !== 0 || $orphans !== 0) throw new RuntimeException('기존 Line Item 무결성 위반 또는 orphan이 있어 Migration을 중단합니다.');
    $sql = file_get_contents(PROJECT_ROOT . '/app/migrations/20260822_12_add_regular_income_insurance_basis_snapshots.up.sql');
    if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('Migration 파일을 읽을 수 없습니다.');
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $db->exec($sql);
}

$columns = $db->query("SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='institution_regular_employment_income_items' AND COLUMN_NAME IN ('national_pension_basis_snapshot','health_insurance_basis_snapshot','employment_insurance_basis_snapshot')) OR (TABLE_NAME='institution_regular_employment_income_line_items' AND COLUMN_NAME IN ('calculated_amount','adjustment_amount'))) ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$checks = $db->query("SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME IN ('chk_regular_income_pension_basis_snapshot','chk_regular_income_health_basis_snapshot','chk_regular_income_employment_basis_snapshot','chk_regular_income_line_final','chk_regular_income_line_adjustment') ORDER BY CONSTRAINT_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$after = $counts($db);
if ($mode === 'up' && $before !== $after) throw new RuntimeException('Migration 전후 기존 데이터 건수가 달라졌습니다.');

$fixture = null;
if ($mode === 'fixture') {
    $parentId = $db->query('SELECT id FROM institution_regular_employment_income_items ORDER BY created_at,id LIMIT 1')->fetchColumn();
    if (!$parentId) throw new RuntimeException('DB CHECK Fixture에 사용할 직원별 급여행이 없습니다.');
    $insert = $db->prepare("INSERT INTO institution_regular_employment_income_line_items (id,regular_employment_income_item_id,sort_no,item_type_code,item_code,item_name_snapshot,taxable_flag,calculated_amount,adjustment_amount,final_amount,adjustment_reason,calculation_source_code,created_by) VALUES (UUID(),:parent,:sort_no,'DEDUCTION',:code,:name,NULL,:calculated,:adjustment,:final,:reason,'HISTORICAL_IMPORT','SYSTEM:FIXTURE')");
    $run = static function (array $row, bool $expected) use ($db, $insert, $parentId): bool {
        try {
            $insert->execute([':parent'=>$parentId,':sort_no'=>$row['sort_no'],':code'=>$row['code'],':name'=>$row['code'],':calculated'=>$row['calculated'],':adjustment'=>$row['adjustment'],':final'=>$row['final'],':reason'=>$row['reason']]);
            return $expected;
        } catch (PDOException) {
            return !$expected;
        }
    };
    $db->beginTransaction();
    try {
        $fixture = [
            'scenario_a' => $run(['sort_no'=>900001,'code'=>'FIXTURE_A','calculated'=>100,'adjustment'=>10,'final'=>110,'reason'=>'차이 확인'], true),
            'scenario_b' => $run(['sort_no'=>900002,'code'=>'FIXTURE_B','calculated'=>100,'adjustment'=>null,'final'=>100,'reason'=>null], true),
            'scenario_c' => $run(['sort_no'=>900003,'code'=>'FIXTURE_C','calculated'=>null,'adjustment'=>null,'final'=>75480,'reason'=>null], true),
            'scenario_d' => $run(['sort_no'=>900004,'code'=>'FIXTURE_D','calculated'=>null,'adjustment'=>10,'final'=>110,'reason'=>'잘못된 조정'], false),
            'scenario_e' => $run(['sort_no'=>900005,'code'=>'FIXTURE_E','calculated'=>100,'adjustment'=>10,'final'=>110,'reason'=>null], false),
        ];
    } finally {
        $db->rollBack();
    }
    if (in_array(false, $fixture, true)) throw new RuntimeException('DB CHECK Runtime Fixture가 실패했습니다.');
}

$historicalRows = null;
if ($mode === 'report-2013') {
    $stmt = $db->query("SELECT h.id,h.income_year_month,h.calculation_source_code,i.id item_id,i.employee_name_snapshot,i.national_pension_basis_snapshot,i.health_insurance_basis_snapshot,i.employment_insurance_basis_snapshot,i.gross_amount,i.deduction_amount,i.net_payment_amount,i.calculation_status_code,i.calculation_message FROM institution_regular_employment_incomes h JOIN institution_regular_employment_income_items i ON i.regular_employment_income_id=h.id WHERE h.income_year_month='2013-08' AND h.deleted_at IS NULL AND i.deleted_at IS NULL ORDER BY i.sort_no");
    $historicalRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $lineStmt = $db->prepare('SELECT item_type_code,item_code,item_name_snapshot,calculated_amount,adjustment_amount,final_amount,adjustment_reason,calculation_source_code FROM institution_regular_employment_income_line_items WHERE regular_employment_income_item_id=? ORDER BY sort_no');
    foreach ($historicalRows as &$row) {
        $lineStmt->execute([$row['item_id']]);
        $row['lines'] = $lineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    unset($row);
}

echo json_encode(['success'=>true,'mode'=>$mode,'before'=>$before,'after'=>$after,'preflight_violations'=>$violations,'orphans'=>$orphans,'columns'=>$columns,'checks'=>$checks,'fixture'=>$fixture,'historical_rows'=>$historicalRows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
