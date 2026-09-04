<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

$db = DbPdo::conn();
$employees = $db->query("SELECT id, employee_name FROM user_employees WHERE employee_name IN ('이정호', '박한호')")
    ->fetchAll(PDO::FETCH_ASSOC);
$names = array_column($employees, 'employee_name', 'id');
$preview = (new RegularEmploymentIncomeCalculationService($db))->preview(
    '2013-08',
    '2013-09-11',
    array_map(
        static fn(array $employee): array => [
            'employee_id' => $employee['id'],
            'dependent_count_snapshot' => 1,
        ],
        $employees
    ),
    'SYSTEM:REGRESSION'
);

$lines = [];
foreach ($preview['results'] as $item) {
    foreach ($item['line_items'] as $line) {
        $lines[$names[$item['employee_id']]][$line['item_type_code'] . ':' . $line['item_code']] = $line;
    }
}

$industrial = $lines['박한호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE'] ?? [];
$leeEmploymentProjection = $lines['이정호']['DEDUCTION:EMPLOYMENT_INSURANCE']['eligibility_projection'] ?? [];
$parkEmploymentProjection = $lines['박한호']['DEDUCTION:EMPLOYMENT_INSURANCE']['eligibility_projection'] ?? [];
$parkPensionProjection = $lines['박한호']['DEDUCTION:NATIONAL_PENSION']['eligibility_projection'] ?? [];
$parkCareProjection = $lines['박한호']['DEDUCTION:LONG_TERM_CARE']['eligibility_projection'] ?? [];
$leeIndustrialProjection = $lines['이정호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['eligibility_projection'] ?? [];
$parkIndustrialProjection = $lines['박한호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['eligibility_projection'] ?? [];
$parkVocationalProjection = $lines['박한호']['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL']['eligibility_projection'] ?? [];
$documentId = (string) $db->query("SELECT id FROM institution_regular_employment_incomes WHERE income_year_month='2013-08' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
$storedDetail = (new RegularEmploymentIncomeService($db))->detail($documentId)['data'];
$storedLines = [];
foreach ($storedDetail['items'] as $item) {
    foreach ($item['line_items'] as $line) {
        $storedLines[$item['employee_name_snapshot']][$line['item_type_code'] . ':' . $line['item_code']] = $line;
    }
}
$checks = [
    '이정호_고용보험_제외사유' => ($lines['이정호']['DEDUCTION:EMPLOYMENT_INSURANCE']['application_status_code'] ?? null) === 'EXCLUDED'
        && ($lines['이정호']['DEDUCTION:EMPLOYMENT_INSURANCE']['calculation_message'] ?? null) === '대표자라서 고용가입안됨',
    '이정호_고용안정_제외사유' => ($lines['이정호']['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL']['application_status_code'] ?? null) === 'EXCLUDED'
        && ($lines['이정호']['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL']['calculation_message'] ?? null) === '대표자라서 고용가입안됨',
    '이정호_산재_제외사유' => ($lines['이정호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['application_status_code'] ?? null) === 'EXCLUDED'
        && ($lines['이정호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['calculation_message'] ?? null) === '대표자라서 산재가입안됨',
    '박한호_고용보험_법정기준' => (float) ($lines['박한호']['DEDUCTION:EMPLOYMENT_INSURANCE']['calculation_rate'] ?? 0) === 0.0065
        && (float) ($lines['박한호']['DEDUCTION:EMPLOYMENT_INSURANCE']['calculated_amount'] ?? 0) === 6420.0,
    '박한호_고용안정_법정기준' => (float) ($lines['박한호']['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL']['calculation_rate'] ?? 0) === 0.0025
        && (float) ($lines['박한호']['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL']['calculated_amount'] ?? 0) === 2470.0,
    '박한호_산재_법정기준' => ($industrial['application_status_code'] ?? null) === 'APPLICABLE'
        && ($industrial['calculation_status_code'] ?? null) === 'CALCULATED'
        && (float) ($industrial['calculation_basis_amount'] ?? 0) === 988890.0
        && (float) ($industrial['calculation_rate'] ?? 0) === 0.037
        && (float) ($industrial['calculation_before_rounding'] ?? 0) === 36588.93
        && ($industrial['rounding_method_code'] ?? null) === 'TRUNCATE'
        && (int) ($industrial['rounding_unit'] ?? 0) === 10
        && (float) ($industrial['calculated_amount'] ?? 0) === 36580.0
        && (float) ($industrial['final_amount'] ?? 0) === 36580.0,
    '이정호_고용보험_근로계약_Projection' => ($leeEmploymentProjection['decision_source_code'] ?? null) === 'EMPLOYMENT_CONTRACT_SETTING'
        && ($leeEmploymentProjection['reason_name'] ?? null) === '대표자라서 고용가입안됨'
        && ($leeEmploymentProjection['application_status_code'] ?? null) === 'EXCLUDED',
    '박한호_고용보험_사업구분_Projection' => ($parkEmploymentProjection['decision_source_code'] ?? null) === 'BUSINESS_DIVISION_POLICY'
        && ($parkEmploymentProjection['application_status_code'] ?? null) === 'APPLICABLE'
        && ($parkEmploymentProjection['company_burden_name'] ?? null) === '우리 회사 부담'
        && trim((string) ($parkEmploymentProjection['decision_basis_name'] ?? '')) !== '',
    '박한호_국민연금_계산Snapshot_Projection' => ($parkPensionProjection['decision_source_code'] ?? null) === 'CALCULATION_SNAPSHOT'
        && trim((string) ($parkPensionProjection['decision_basis_name'] ?? '')) !== '',
    '박한호_장기요양_건강보험종속_Projection' => ($parkCareProjection['decision_source_code'] ?? null) === 'DEPENDENT_INSURANCE_RESULT'
        && trim((string) ($parkCareProjection['decision_basis_name'] ?? '')) !== '',
    '이정호_산재_근로계약_Projection' => ($leeIndustrialProjection['decision_source_code'] ?? null) === 'EMPLOYMENT_CONTRACT_SETTING'
        && ($leeIndustrialProjection['reason_name'] ?? null) === '대표자라서 산재가입안됨',
    '박한호_산재_사업구분_Projection' => ($parkIndustrialProjection['decision_source_code'] ?? null) === 'BUSINESS_DIVISION_POLICY'
        && ($parkIndustrialProjection['company_burden_name'] ?? null) === '우리 회사 부담'
        && trim((string) ($parkIndustrialProjection['decision_basis_name'] ?? '')) !== '',
    '박한호_고용안정_사업구분_Projection' => ($parkVocationalProjection['decision_source_code'] ?? null) === 'BUSINESS_DIVISION_POLICY'
        && ($parkVocationalProjection['company_burden_name'] ?? null) === '우리 회사 부담'
        && trim((string) ($parkVocationalProjection['decision_basis_name'] ?? '')) !== '',
    '승인문서_이정호_고용안정_미적용사유' => ($storedLines['이정호']['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL']['application_status_code'] ?? null) === 'EXCLUDED'
        && ($storedLines['이정호']['EMPLOYER_BURDEN:EMPLOYMENT_INSURANCE_VOCATIONAL']['calculation_message'] ?? null) === '대표자라서 고용가입안됨',
    '승인문서_이정호_산재_미적용사유' => ($storedLines['이정호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['application_status_code'] ?? null) === 'EXCLUDED'
        && ($storedLines['이정호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['calculation_message'] ?? null) === '대표자라서 산재가입안됨',
    '승인문서_박한호_산재_저장값' => ($storedLines['박한호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['application_status_code'] ?? null) === 'APPLICABLE'
        && (float) ($storedLines['박한호']['EMPLOYER_BURDEN:INDUSTRIAL_ACCIDENT_INSURANCE']['final_amount'] ?? 0) === 36580.0,
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(
    [
        'success' => $failed === [],
        'checks' => $checks,
        'failed' => $failed,
        'projection_samples' => [
            '이정호_고용보험' => $leeEmploymentProjection,
            '박한호_고용보험' => $parkEmploymentProjection,
            '박한호_국민연금' => $parkPensionProjection,
            '박한호_장기요양' => $parkCareProjection,
            '이정호_산재보험' => $leeIndustrialProjection,
            '박한호_산재보험' => $parkIndustrialProjection,
            '박한호_고용안정' => $parkVocationalProjection,
        ],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
), PHP_EOL;
exit($failed === [] ? 0 : 1);
