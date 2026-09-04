<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

$db = DbPdo::conn();
$db->beginTransaction();
register_shutdown_function(static function () use ($db): void {
    if ($db->inTransaction()) $db->rollBack();
});
$fixtureEmployeeId = '6e8fb7ef-ea70-4d37-9aed-74f33b355127';
$fixtureLine = $db->prepare("UPDATE institution_regular_employment_income_line_items l
    JOIN institution_regular_employment_income_items i ON i.id=l.regular_employment_income_item_id
    JOIN institution_regular_employment_incomes h ON h.id=i.regular_employment_income_id
    SET l.final_amount=0,l.adjustment_amount=-6420,l.adjustment_reason='과거 실제 고용보험 미공제',
        l.business_source_code='MANUAL',l.source_reference_id=l.id,
        l.source_key='INSURANCE_OVERRIDE|EMPLOYMENT_INSURANCE|2013-08',
        l.processed_at='2026-08-28 15:00:00',l.processed_by='SYSTEM:REGRESSION'
    WHERE h.income_year_month='2013-08' AND i.employee_id=:employee_id
      AND l.item_type_code='DEDUCTION' AND l.item_code='EMPLOYMENT_INSURANCE'");
$fixtureLine->execute([':employee_id'=>$fixtureEmployeeId]);
if ($fixtureLine->rowCount() !== 1) throw new RuntimeException('보험 Override Fixture Line을 확정할 수 없습니다.');
$service = new RegularEmploymentIncomeService($db);
$calculator = new RegularEmploymentIncomeCalculationService($db);
$detail = $service->detail('4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6')['data'];
$inputs = array_map(static fn(array $item): array => [
    'employee_id' => $item['employee_id'],
    'dependent_count_snapshot' => $item['dependent_count_snapshot'],
    'national_pension_basis_snapshot' => $item['national_pension_basis_snapshot'],
    'health_insurance_basis_snapshot' => $item['health_insurance_basis_snapshot'],
    'employment_insurance_basis_snapshot' => $item['employment_insurance_basis_snapshot'],
], $detail['items']);

$inherited = $calculator->preview('2013-09', '2013-10-11', $inputs, 'SYSTEM:REGRESSION');
$lee = current(array_filter($inherited['results'], static fn(array $row): bool => $row['employee_id'] === '6e8fb7ef-ea70-4d37-9aed-74f33b355127'));
$line = current(array_filter($lee['line_items'] ?? [], static fn(array $row): bool => ($row['item_code'] ?? '') === 'EMPLOYMENT_INSURANCE' && ($row['item_type_code'] ?? '') === 'DEDUCTION'));

$repeatInputs = $inputs;
foreach ($repeatInputs as &$input) {
    if ($input['employee_id'] === $lee['employee_id']) $input['insurance_override_line_items'] = [$line];
}
unset($input);
$repeat = $calculator->preview('2013-09', '2013-10-11', $repeatInputs, 'SYSTEM:REGRESSION');
$repeatLee = current(array_filter($repeat['results'], static fn(array $row): bool => $row['employee_id'] === $lee['employee_id']));
$repeatLine = current(array_filter($repeatLee['line_items'] ?? [], static fn(array $row): bool => ($row['item_code'] ?? '') === 'EMPLOYMENT_INSURANCE' && ($row['item_type_code'] ?? '') === 'DEDUCTION'));
$normalize = new ReflectionMethod(RegularEmploymentIncomeService::class, 'normalizeInsuranceOverrideLines');
$missingReasonBlocked = false;
try {
    $normalize->invoke($service, [['id'=>'line','item_type_code'=>'DEDUCTION','item_code'=>'EMPLOYMENT_INSURANCE','calculated_amount'=>6420,'final_amount'=>0]], '2013-08', 'SYSTEM:REGRESSION');
} catch (Throwable) {
    $missingReasonBlocked = true;
}
$reset = $normalize->invoke($service, [['id'=>'line','item_type_code'=>'DEDUCTION','item_code'=>'EMPLOYMENT_INSURANCE','calculated_amount'=>6420,'final_amount'=>6420,'adjustment_reason'=>null,'source_key'=>'INSURANCE_OVERRIDE_RESET|EMPLOYMENT_INSURANCE|2014-01']], '2014-01', 'SYSTEM:REGRESSION')[0];
$javascript = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$formatJavascript = file_get_contents(PROJECT_ROOT . '/public/assets/js/common/format.js');
$view = file_get_contents(PROJECT_ROOT . '/app/views/institution/regular-employment-income/index.php');
$serviceSource = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeService.php');
$fieldPolicyService = file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeFieldPolicyService.php');

$checks = [
    'zero_is_preserved' => (float) ($line['final_amount'] ?? -1) === 0.0,
    'automatic_amount_is_preserved' => (float) ($line['calculated_amount'] ?? -1) > 0.0,
    'reason_is_inherited' => trim((string) ($line['adjustment_reason'] ?? '')) !== '',
    'origin_month_is_exposed' => ($line['override_origin_month'] ?? '') === '2013-08',
    'origin_line_is_referenced' => trim((string) ($line['source_reference_id'] ?? '')) !== '',
    'repeat_is_idempotent' => (float) ($repeatLine['final_amount'] ?? -1) === 0.0
        && (string) ($repeatLine['adjustment_reason'] ?? '') === (string) ($line['adjustment_reason'] ?? ''),
    'missing_reason_is_blocked' => $missingReasonBlocked,
    'automatic_reset_is_explicit' => str_starts_with((string) ($reset['source_key'] ?? ''), 'INSURANCE_OVERRIDE_RESET|') && ($reset['adjustment_reason'] ?? null) === null
        && ($reset['calculation_source_code'] ?? '') === 'CALCULATED',
    'ui_has_required_states_and_action' => str_contains($javascript, '자동계산 금액 적용')
        && str_contains($javascript, "reset.textContent='적용'")
        && str_contains($javascript, "'수동 적용'")
        && str_contains($javascript, "'계승 적용'")
        && str_contains($javascript, "'확인 필요'")
        && str_contains($javascript, "'계산 오류'"),
    'reload_sends_override_lines' => str_contains($javascript, 'insurance_override_line_items:'),
    'family_is_not_auto_excluded' => !str_contains($javascript, '대표자와 그 가족은 제외')
        && !str_contains($javascript, '대표자와 그가족은 제외'),
    'won_inputs_are_integer_only' => substr_count($javascript, 'integerOnly:true') >= 4
        && str_contains($formatJavascript, 'options.integerOnly === true')
        && str_contains($javascript, "input.inputMode='numeric'"),
    'system_info_is_read_only_collapsible_card' => str_contains($view, 'regularIncomeSystemInfoTemplate')
        && str_contains($view, 'data-ui-modal-card-collapse')
        && !str_contains(substr($view, strpos($view, '<template id="regularIncomeSystemInfoTemplate"')), '<input'),
    'system_info_covers_header_metadata' => str_contains($javascript, "{key:'calculation_version',label:'계산정책버전'}")
        && str_contains($javascript, "{key:'company_id',label:'회사 ID'}")
        && str_contains($javascript, "{key:'payroll_period_start_date',label:'급여 산정기간 시작일',type:'date'}")
        && str_contains($javascript, "{key:'payroll_period_end_date',label:'급여 산정기간 종료일',type:'date'}")
        && str_contains($javascript, "{key:'nominal_payment_date',label:'명목 지급일',type:'date'}")
        && str_contains($javascript, "{key:'proposed_payment_date',label:'자동 제안 지급일',type:'date'}")
        && str_contains($javascript, "{key:'payment_date_override_reason',label:'지급일 변경 사유'}")
        && str_contains($javascript, "{key:'memo',label:'메모'}")
        && str_contains($javascript, "{key:'gross_amount',label:'지급총액',type:'amount'}")
        && str_contains($javascript, "{key:'deduction_amount',label:'공제총액',type:'amount'}")
        && str_contains($javascript, "{key:'created_by_name',fallbackKey:'created_by',label:'생성자'}")
        && str_contains($javascript, 'renderSystemInfo(data.header)')
        && str_contains($javascript, 'resolveDataTableColumnDisplayName(column, state')
        && str_contains($javascript, 'resolveDataTableColumnRequirementPolicy(column, state)')
        && str_contains($javascript, 'column-policy-star is-${columnPolicy.policy}'),
    'modal_uses_table_settings_labels_and_markers' => str_contains($javascript, 'createDataTableFormSettings')
        && str_contains($javascript, 'applyFormSettings()')
        && str_contains($javascript, "event.detail?.storageKey === TABLE_SETTINGS_KEY"),
    'save_uses_table_settings_required_policy' => str_contains($serviceSource, '$this->fieldPolicy->validateRequiredFields($input)')
        && str_contains($fieldPolicyService, "'columnRequirementPolicy'")
        && str_contains($fieldPolicyService, "'columnDisplayName'"),
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($db->inTransaction()) $db->rollBack();
echo json_encode(['success'=>$failed===[],'checks'=>$checks,'failed'=>$failed], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed===[]?0:1);
