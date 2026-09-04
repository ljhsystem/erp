<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($direction, ['up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_other_pay_component.php [up|verify]');
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$lockName = 'erp:pay-component:other-pay';
$locked = (int) $db->query('SELECT GET_LOCK(' . $db->quote($lockName) . ', 10)')->fetchColumn() === 1;
if (!$locked) {
    throw new RuntimeException('기타 급여항목 Migration 잠금을 획득하지 못했습니다.');
}

try {
    if ($direction === 'up') {
        $db->beginTransaction();
        try {
            $conflict = $db->query(
                "SELECT id, component_code, component_name, component_type
                 FROM institution_employment_contracts_pay_components
                 WHERE id='0d852729-d6cb-4d4d-9b09-758b2ec3f110'
                    OR component_code='OTHER_PAY'
                    OR (component_name='기타' AND component_type='OTHER_WAGE')
                 FOR UPDATE"
            )->fetchAll(PDO::FETCH_ASSOC);
            if ($conflict !== [] && !(
                count($conflict) === 1
                && $conflict[0]['id'] === '0d852729-d6cb-4d4d-9b09-758b2ec3f110'
                && $conflict[0]['component_code'] === 'OTHER_PAY'
            )) {
                throw new RuntimeException('동일 ID·코드·의미의 기존 급여항목이 충돌하여 Migration을 중단합니다.');
            }
            $db->exec((string) file_get_contents(
                PROJECT_ROOT . '/app/migrations/20260824_03_add_other_pay_component.up.sql'
            ));
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    $row = $db->query(
        "SELECT id, sort_no, component_code, component_name, component_type,
                default_calculation_type, default_tax_type, tax_policy_code,
                ordinary_wage_treatment, average_wage_treatment, minimum_wage_treatment,
                is_active, effective_from, effective_to, deleted_at, created_by
         FROM institution_employment_contracts_pay_components
         WHERE id='0d852729-d6cb-4d4d-9b09-758b2ec3f110' AND component_code='OTHER_PAY'"
    )->fetch(PDO::FETCH_ASSOC);
    $duplicateCode = (int) $db->query(
        "SELECT COUNT(*) FROM institution_employment_contracts_pay_components WHERE component_code='OTHER_PAY'"
    )->fetchColumn();
    $duplicateSort = $row ? (int) $db->query(
        'SELECT COUNT(*) FROM institution_employment_contracts_pay_components WHERE sort_no=' . (int) $row['sort_no']
    )->fetchColumn() : 0;
    $references = $row ? (int) $db->query(
        "SELECT
          (SELECT COUNT(*) FROM institution_employment_contracts_components WHERE pay_component_id='0d852729-d6cb-4d4d-9b09-758b2ec3f110') +
          (SELECT COUNT(*) FROM institution_regular_employment_income_line_items WHERE source_reference_id='0d852729-d6cb-4d4d-9b09-758b2ec3f110')"
    )->fetchColumn() : 0;
    $checks = [
        'row_exists' => is_array($row),
        'identity' => ($row['id'] ?? '') === '0d852729-d6cb-4d4d-9b09-758b2ec3f110'
            && ($row['component_code'] ?? '') === 'OTHER_PAY'
            && ($row['component_name'] ?? '') === '기타',
        'policy' => ($row['component_type'] ?? '') === 'OTHER_WAGE'
            && ($row['default_calculation_type'] ?? '') === 'FIXED_AMOUNT'
            && ($row['default_tax_type'] ?? '') === 'TAXABLE'
            && ($row['ordinary_wage_treatment'] ?? '') === 'REVIEW_REQUIRED'
            && ($row['average_wage_treatment'] ?? '') === 'INCLUDED'
            && ($row['minimum_wage_treatment'] ?? '') === 'REVIEW_REQUIRED',
        'effective_and_active' => ($row['effective_from'] ?? '') === '2013-01-01'
            && ($row['effective_to'] ?? null) === null
            && (int) ($row['is_active'] ?? 0) === 1
            && ($row['deleted_at'] ?? null) === null,
        'unique_code' => $duplicateCode === 1,
        'unique_sort_no' => $duplicateSort === 1,
        'actor' => ($row['created_by'] ?? '') === 'SYSTEM:MIGRATION',
    ];
    if (in_array(false, $checks, true)) {
        throw new RuntimeException('기타 급여항목 Migration 검증에 실패했습니다.');
    }
    echo json_encode(['success'=>true,'direction'=>$direction,'row'=>$row,'references'=>$references,'checks'=>$checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    $db->query('SELECT RELEASE_LOCK(' . $db->quote($lockName) . ')');
}
