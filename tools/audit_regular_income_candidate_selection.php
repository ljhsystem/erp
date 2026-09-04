<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeService;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use Core\DbPdo;

$month = trim((string) ($argv[1] ?? ''));
$documentId = trim((string) ($argv[2] ?? ''));
if (!preg_match('/^\d{4}-\d{2}$/', $month) || $documentId === '') {
    throw new InvalidArgumentException('귀속연월과 문서 ID를 입력해 주세요.');
}

$db = DbPdo::conn();
$service = new RegularEmploymentIncomeService($db);
$selection = $service->eligibleEmployees($month)['data'];
$detail = $service->detail($documentId)['data'];
$calculationInputs = array_map(static fn (array $item): array => [
    'employee_id' => $item['employee_id'],
    'dependent_count_snapshot' => $item['dependent_count_snapshot'] ?? null,
    'national_pension_basis_snapshot' => $item['national_pension_basis_snapshot'] ?? null,
    'health_insurance_basis_snapshot' => $item['health_insurance_basis_snapshot'] ?? null,
    'employment_insurance_basis_snapshot' => $item['employment_insurance_basis_snapshot'] ?? null,
    'pay_line_items' => array_values(array_filter($item['line_items'] ?? [], static fn (array $line): bool =>
        ($line['item_type_code'] ?? '') === 'PAY'
        && in_array($line['pay_effect_code'] ?? '', ['INCREASE', 'DECREASE'], true)
    )),
    'deduction_line_items' => array_values(array_filter($item['line_items'] ?? [], static fn (array $line): bool =>
        ($line['item_type_code'] ?? '') === 'DEDUCTION'
        && str_starts_with((string) ($line['source_key'] ?? ''), 'SETTLEMENT|')
    )),
], $detail['items'] ?? []);
$calculator = new RegularEmploymentIncomeCalculationService($db);
$previewOne = $calculator->preview($month, (string) $detail['header']['payment_date'], $calculationInputs, 'SYSTEM:AUDIT');
$previewTwo = $calculator->preview($month, (string) $detail['header']['payment_date'], $calculationInputs, 'SYSTEM:AUDIT');
$fingerprint = static function (array $item): string {
    $scalarKeys = ['employment_contract_id', 'dependent_count_snapshot', 'national_pension_basis_snapshot', 'health_insurance_basis_snapshot', 'employment_insurance_basis_snapshot'];
    $scalars = [];
    foreach ($scalarKeys as $key) $scalars[$key] = (string) ($item[$key] ?? '');
    $lines = array_map(static fn (array $line): array => [
        'item_type_code' => (string) ($line['item_type_code'] ?? ''),
        'item_code' => (string) ($line['item_code'] ?? ''),
        'pay_effect_code' => (string) ($line['pay_effect_code'] ?? ''),
        'source_reference_id' => (string) ($line['source_reference_id'] ?? ''),
        'source_key' => (string) ($line['source_key'] ?? ''),
        'taxable_flag' => (string) ($line['taxable_flag'] ?? ''),
        'calculated_amount' => round((float) ($line['calculated_amount'] ?? 0), 2),
    ], $item['line_items'] ?? []);
    usort($lines, static fn (array $left, array $right): int => json_encode($left) <=> json_encode($right));
    $bases = array_map(static fn (array $basis): array => [
        'basis_type_code' => (string) ($basis['basis_type_code'] ?? ''),
        'source_table' => (string) ($basis['source_table'] ?? ''),
        'source_id' => (string) ($basis['source_id'] ?? ''),
        'source_revision' => (string) ($basis['source_revision'] ?? ''),
        'effective_from' => (string) ($basis['effective_from'] ?? ''),
        'effective_to' => (string) ($basis['effective_to'] ?? ''),
        'basis_code' => (string) ($basis['basis_code'] ?? ''),
    ], $item['calculation_bases'] ?? []);
    usort($bases, static fn (array $left, array $right): int => json_encode($left) <=> json_encode($right));
    return json_encode(compact('scalars', 'lines', 'bases'));
};
$currentByEmployee = [];
foreach ($detail['items'] ?? [] as $item) $currentByEmployee[(string) $item['employee_id']] = $fingerprint($item);
$previewChanged = [];
foreach ($previewOne['results'] ?? [] as $result) {
    $employeeId = (string) $result['employee_id'];
    $current = current(array_filter($detail['items'] ?? [], static fn (array $item): bool => (string) $item['employee_id'] === $employeeId)) ?: [];
    $merged = array_merge($current, $result);
    $merged['line_items'] = $result['line_items'] ?? [];
    $merged['calculation_bases'] = $result['calculation_bases'] ?? [];
    if (($currentByEmployee[$employeeId] ?? '') !== $fingerprint($merged)) $previewChanged[] = $employeeId;
}
$periodFrom = $month . '-01';
$periodTo = date('Y-m-t', strtotime($periodFrom));
$employeeStmt = $db->prepare(
    "SELECT id employee_id,employee_name,employment_status,
            COALESCE(real_hire_date,doc_hire_date) hire_date,
            COALESCE(real_retire_date,doc_retire_date) retire_date
       FROM user_employees
      ORDER BY employee_name,id"
);
$employeeStmt->execute();
$candidateIds = array_fill_keys(array_map(
    static fn (array $row): string => (string) $row['employee_id'],
    $selection['candidates'] ?? []
), true);
$allEmployees = array_map(static function (array $row) use ($candidateIds, $periodFrom, $periodTo): array {
    $reason = null;
    if (!isset($candidateIds[(string) $row['employee_id']])) {
        if (($row['hire_date'] ?? null) !== null && $row['hire_date'] > $periodTo) {
            $reason = '귀속월 이후 입사';
        } elseif (($row['retire_date'] ?? null) !== null && $row['retire_date'] < $periodFrom) {
            $reason = '귀속월 이전 퇴사';
        } else {
            $reason = '재직기간·재직상태·유효 근로계약 조건 불충족';
        }
    }
    return $row + ['candidate' => isset($candidateIds[(string) $row['employee_id']]), 'filtered_reason' => $reason];
}, $employeeStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

echo json_encode([
    'month' => $month,
    'document_id' => $documentId,
    'summary' => $selection['summary'] ?? [],
    'repeat_preview_identical' => $previewOne == $previewTwo,
    'current_snapshot_changed_employee_ids' => $previewChanged,
    'all_employees' => $allEmployees,
    'candidates' => array_map(static fn (array $row): array => [
        'employee_id' => $row['employee_id'] ?? null,
        'employee_name' => $row['employee_name'] ?? null,
        'employment_contract_id' => $row['employment_contract_id'] ?? null,
        'department_name' => $row['department_name'] ?? null,
        'position_name' => $row['position_name'] ?? null,
        'nominal_payment_date' => $row['nominal_payment_date'] ?? null,
        'payment_terms_status' => $row['payment_terms_status'] ?? null,
    ], $selection['candidates'] ?? []),
    'excluded' => array_map(static fn (array $row): array => [
        'employee_id' => $row['employee_id'] ?? null,
        'employee_name' => $row['employee_name'] ?? null,
        'reason_code' => $row['reason_code'] ?? null,
        'reason' => $row['reason'] ?? null,
        'period_from' => $selection['summary']['period_from'] ?? null,
        'period_to' => $selection['summary']['period_to'] ?? null,
    ], $selection['excluded'] ?? []),
    'document_items' => array_map(static fn (array $row): array => [
        'id' => $row['id'] ?? null,
        'employee_id' => $row['employee_id'] ?? null,
        'employee_name' => $row['employee_name_snapshot'] ?? null,
        'employment_contract_id' => $row['employment_contract_id'] ?? null,
        'dependent_count_snapshot' => $row['dependent_count_snapshot'] ?? null,
        'national_pension_basis_snapshot' => $row['national_pension_basis_snapshot'] ?? null,
        'health_insurance_basis_snapshot' => $row['health_insurance_basis_snapshot'] ?? null,
        'employment_insurance_basis_snapshot' => $row['employment_insurance_basis_snapshot'] ?? null,
    ], $detail['items'] ?? []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
