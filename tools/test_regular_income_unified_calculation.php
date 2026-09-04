<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

$db = DbPdo::conn();
$income = new RegularEmploymentIncomeService($db);
$calculation = new RegularEmploymentIncomeCalculationService($db);
$scenarios = [];
$headerColumn = $db->query("SELECT IS_NULLABLE,COLUMN_DEFAULT,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_incomes' AND COLUMN_NAME='calculation_source_code'")->fetch(PDO::FETCH_ASSOC) ?: null;
$headerValues = $db->query("SELECT calculation_source_code,COUNT(*) row_count FROM institution_regular_employment_incomes GROUP BY calculation_source_code")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$modeCodes = $db->query("SELECT code_group,code,code_name FROM system_codes WHERE code IN ('CURRENT','HISTORICAL_IMPORT') OR code_name IN ('현재자동계산','현재 자동계산','과거 실제자료복원','과거 실제자료 복원') ORDER BY code_group,code")->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ([['2013-08', '2013-09-11'], ['2026-08', '2026-09-11']] as [$month, $paymentDate]) {
    $selection = $income->eligibleEmployees($month)['data'];
    $candidate = $selection['candidates'][0] ?? null;
    if (!$candidate) {
        $scenarios[$month] = ['skipped' => true, 'reason' => '유효 계약 대상직원이 없습니다.'];
        continue;
    }
    $employeeId = (string) $candidate['employee_id'];
    $coverage = $db->prepare("SELECT COUNT(*) FROM institution_social_insurance_coverages WHERE employee_id=? AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?)");
    $coverage->execute([$employeeId, $month . '-31', $month . '-01']);
    $result = $calculation->preview($month, $paymentDate, [[
        'employee_id' => $employeeId,
        'dependent_count_snapshot' => 1,
    ]], 'SYSTEM:FIXTURE')['results'][0];
    $basis = $result['basis_resolutions'];
    $coverageError = str_contains((string) $result['calculation_message'], 'Coverage/Basis가 필요')
        || str_contains((string) $result['calculation_message'], '적용이력이 등록되어 있지 않습니다');
    $autoBasis = true;
    foreach (['national_pension_basis_snapshot', 'health_insurance_basis_snapshot', 'employment_insurance_basis_snapshot'] as $field) {
        $autoBasis = $autoBasis && ($basis[$field]['source_code'] ?? '') === 'PAY_ITEM_FINAL_AMOUNT';
    }
    $coverageCount = (int) $coverage->fetchColumn();
    $scenarios[$month] = [
        'employee_id' => $employeeId,
        'coverage_count' => $coverageCount,
        'coverage_error' => $coverageError,
        'auto_basis_from_pay_items' => $autoBasis,
        'status' => $result['calculation_status_code'],
        'message' => $result['calculation_message'],
        'notice' => $result['calculation_notice'],
        'basis_resolutions' => $basis,
    ];
}

$failed = array_filter($scenarios, static fn (array $row): bool => empty($row['skipped']) && ($row['coverage_error'] || ($row['coverage_count'] === 0 && !$row['auto_basis_from_pay_items'])));
echo json_encode(['success' => $failed === [], 'header_column' => $headerColumn, 'header_values' => $headerValues, 'mode_codes' => $modeCodes, 'scenarios' => $scenarios], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
