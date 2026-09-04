<?php

declare(strict_types=1);

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$calculation = new RegularEmploymentIncomeCalculationService($db);
$service = new RegularEmploymentIncomeService($db);
$employeeIds = [
    'ce50c61c-8b08-4f58-b8bc-e11f1dbafb84',
    '6e8fb7ef-ea70-4d37-9aed-74f33b355127',
];
$preview = $calculation->preview('2013-08', '2013-09-11', array_map(
    static fn(string $employeeId): array => ['employee_id' => $employeeId, 'dependent_count_snapshot' => 1],
    $employeeIds
), 'SYSTEM:REGRESSION');
$dependentTwoPreview = $calculation->preview('2013-08', '2013-09-11', [[
    'employee_id' => $employeeIds[0],
    'dependent_count_snapshot' => 2,
]], 'SYSTEM:REGRESSION')['results'][0];

$line = static function (array $item, string $code): array {
    foreach ($item['line_items'] ?? [] as $row) {
        if (($row['item_code'] ?? '') === $code) {
            return $row;
        }
    }
    throw new RuntimeException('검증할 공제항목이 없습니다: ' . $code);
};
$checks = [];
$dependentTwoTax = $line($dependentTwoPreview, 'EMPLOYMENT_INCOME_TAX');
$checks['dependent_two_recalculates'] = (int) $dependentTwoPreview['dependent_count_snapshot'] === 2
    && (int) $dependentTwoTax['dependent_count'] === 2
    && (float) $dependentTwoTax['calculated_amount'] === 0.0;
$supportedDependentCounts = array_map('intval', $dependentTwoPreview['supported_dependent_counts'] ?? []);
$checks['dependent_range_comes_from_effective_tax_table'] = $supportedDependentCounts !== []
    && min($supportedDependentCounts) === 1
    && max($supportedDependentCounts) === 11
    && !in_array(12, $supportedDependentCounts, true);
foreach ($preview['results'] as $item) {
    $prefix = (string) $item['employee_id'];
    $checks[$prefix . '_pension_basis'] = (float) $item['national_pension_basis_snapshot'] === 988000.0;
    $checks[$prefix . '_health_basis'] = (float) $item['health_insurance_basis_snapshot'] === 988890.0;
    $employmentLine = $line($item, 'EMPLOYMENT_INSURANCE');
    $employmentExcluded = ($employmentLine['application_status_code'] ?? null) === 'EXCLUDED';
    $checks[$prefix . '_employment_basis'] = $employmentExcluded
        ? !array_key_exists('employment_insurance_basis_snapshot', $item)
        : (float) ($item['employment_insurance_basis_snapshot'] ?? 0) === 988890.0;
    $checks[$prefix . '_pension'] = (float) $line($item, 'NATIONAL_PENSION')['calculated_amount'] === 44460.0;
    $checks[$prefix . '_health'] = (float) $line($item, 'HEALTH_INSURANCE')['calculated_amount'] === 29120.0;
    $checks[$prefix . '_care'] = (float) $line($item, 'LONG_TERM_CARE')['calculated_amount'] === 1900.0;
    $checks[$prefix . '_employment'] = $employmentExcluded
        ? $employmentLine['calculated_amount'] === null && (float) $employmentLine['final_amount'] === 0.0
        : (float) $employmentLine['calculated_amount'] === 6420.0;
    $tax = $line($item, 'EMPLOYMENT_INCOME_TAX');
    $checks[$prefix . '_tax'] = (float) $tax['calculated_amount'] === 0.0
        && (int) $tax['dependent_count'] === 1
        && (float) $tax['tax_table_salary_from'] === 985000.0
        && (float) $tax['tax_table_salary_to'] === 990000.0;
    $checks[$prefix . '_local_tax'] = (float) $line($item, 'LOCAL_INCOME_TAX')['calculated_amount'] === 0.0;
}

$detail = $service->detail('4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6')['data'];
foreach ($detail['items'] as $item) {
    $name = (string) $item['employee_name_snapshot'];
    $checks[$name . '_reopen_snapshots'] = (int) $item['dependent_count_snapshot'] === 1
        && (float) $item['national_pension_basis_snapshot'] === 988000.0
        && (float) $item['health_insurance_basis_snapshot'] === 988890.0
        && (float) $item['employment_insurance_basis_snapshot'] === 988890.0;
    foreach (['NATIONAL_PENSION' => 44460.0, 'HEALTH_INSURANCE' => 29120.0, 'LONG_TERM_CARE' => 1900.0] as $code => $amount) {
        $stored = $line($item, $code);
        $checks[$name . '_' . $code . '_normalized'] = (float) $stored['calculated_amount'] === $amount
            && (float) $stored['adjustment_amount'] === 0.0
            && (float) $stored['final_amount'] === $amount
            && $stored['adjustment_reason'] === null;
    }
    foreach (['EMPLOYMENT_INCOME_TAX','LOCAL_INCOME_TAX'] as $code) {
        $stored = $line($item, $code);
        $checks[$name . '_' . $code . '_zero'] = (float) $stored['calculated_amount'] === 0.0
            && (float) $stored['adjustment_amount'] === 0.0
            && (float) $stored['final_amount'] === 0.0;
    }
}
$detailDeduction = array_sum(array_map(static fn(array $item): float => (float) $item['deduction_amount'], $detail['items']));
$detailNet = array_sum(array_map(static fn(array $item): float => (float) $item['net_payment_amount'], $detail['items']));
$checks['header_totals'] = (float) $detail['header']['deduction_amount'] === $detailDeduction
    && (float) $detail['header']['net_payment_amount'] === $detailNet;

$javascript = (string) file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$checks['dependent_card_input'] = str_contains($javascript, "const isDependent=key==='dependent_count_snapshot'")
    && str_contains($javascript, "input.type=isDependent?'number':'text'")
    && str_contains($javascript, "input.placeholder=isDependent?String(dependentMin):'자동산출값 사용'")
    && str_contains($javascript, 'scheduleRecalculation(item)')
    && str_contains($javascript, '간이세액표 지원범위 ${dependentMin}~${dependentMax}명 · 변경 시 자동계산');
$checks['dependent_count_uses_width_style_stepper'] = str_contains($javascript, "decrease.textContent='◀'")
    && str_contains($javascript, "increase.textContent='▶'")
    && str_contains($javascript, "decrease.setAttribute('aria-label','공제대상 가족수 감소')");
$checks['latest_basis_recalculation_wins'] = str_contains($javascript, 'const recalculationVersions=new Map()')
    && str_contains($javascript, 'const version=nextRecalculationVersion(item.employee_id)')
    && str_contains($javascript, 'if(recalculationVersions.get(employeeId)!==version)return false');
$checks['dependent_override_survives_recalculation_render'] = str_contains($javascript, 'const dependentCountOverrides = new Map()')
    && str_contains($javascript, 'dependentCountOverrides.set(employeeKey,item[key])')
    && str_contains($javascript, 'dependentCountOverrides.has(String(item.employee_id))?dependentCountOverrides.get(String(item.employee_id))')
    && str_contains($javascript, 'const dependentCount=dependentCountOverrides.has(employeeKey)?dependentCountOverrides.get(employeeKey)');
$checks['calculation_response_returns_applied_dependent_count'] = str_contains(
    (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php'),
    "'dependent_count_snapshot' => \$dependentCounts[\$employeeId] ?? null"
);
$checks['calculation_response_returns_supported_dependent_counts'] = str_contains(
    (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php'),
    "'supported_dependent_counts' => \$supportedDependentCounts"
);
$checks['dependent_count_respects_tax_table_range'] = str_contains($javascript, 'supportedDependentCounts')
    && str_contains($javascript, 'supported_dependent_counts')
    && str_contains($javascript, "input.addEventListener('change',syncBasis)")
    && str_contains($javascript, 'supportedDependentCounts.includes(dependentValue)');
$checks['calculation_tooltips_disposed_before_grid_render'] = str_contains($javascript, 'function disposeCalculationMessageTooltips()')
    && str_contains($javascript, 'disposeCalculationMessageTooltips();grid.setState(')
    && str_contains($javascript, 'version!==calculationMessageTooltipVersion');
$checks['automatic_amount_tracks_recalculation'] = str_contains($javascript, 'const wasAutomatic=')
    && str_contains($javascript, 'const finalAmount=wasAutomatic?calculatedAmount:appliedFinal');
$checks['manual_pay_recalculates_on_add'] = str_contains($javascript, 'function appendPayEffectEditor(card,item)')
    && str_contains($javascript, 'await recalculateEmployee(owner)')
    && str_contains($javascript, 'pay_line_items:')
    && str_contains($javascript, "addCard.innerHTML='<strong>+</strong><span>")
    && str_contains($javascript, "String(candidate.item_code)!==String(pendingLine.item_code)")
    && str_contains($javascript, 'candidate!==removed');
$checks['common_amount_input_format'] = str_contains($javascript, 'bindNumberInput(amount,{integerOnly:true')
    && str_contains($javascript, "bindNumberInput(input,{integerOnly:true,onInput:()=>{sync();if(line.item_type_code==='PAY')scheduleRecalculation(item);},onBlur:sync})")
    && str_contains($javascript, "input.type=isDependent?'number':'text'")
    && str_contains($javascript, 'bindNumberInput(input,{integerOnly:true,onInput:syncBasis,onBlur:syncBasis})');
$checks['pay_cards_share_section'] = str_contains($javascript, "payTitle.textContent='지급항목'")
    && str_contains($javascript, 'const payEffectDisplayOrder={CONTRACT_BASE:0,INCREASE:1,DECREASE:2}');
$checks['direct_pay_cards_auto_recalculation'] = !str_contains($javascript, 'function createPayCard(')
    && str_contains($javascript, "payCards.className='regular-income-card-grid regular-income-pay-grid'")
    && str_contains($javascript, "payLines.forEach(line=>payCards.append(createLineCard(line,item)))")
    && str_contains($javascript, 'appendPayEffectEditor(payCards,item)')
    && !str_contains($javascript, "apply.textContent='변경사항 재계산'");
$stylesheet = (string) file_get_contents(PROJECT_ROOT . '/public/assets/css/pages/institution/regular-employment-income.css');
$view = (string) file_get_contents(PROJECT_ROOT . '/app/views/institution/regular-employment-income/index.php');
$checks['dependent_count_stepper_style'] = str_contains($stylesheet, '.regular-income-dependent-stepper { grid-template-columns: 28px 56px auto 28px;')
    && str_contains($stylesheet, '.regular-income-dependent-stepper input { width: 56px;')
    && str_contains($stylesheet, '.regular-income-dependent-step:hover:not(:disabled)');
$checks['date_pickers_are_button_only_and_compact'] = !str_contains($javascript, "yearMonthDisplay.addEventListener('click',openYearMonthPicker)")
    && !str_contains($javascript, "paymentDateInput.addEventListener('click',openDatePicker)")
    && str_contains($javascript, "bindDateIcon('regularIncomeYearMonthButton',openYearMonthPicker)")
    && str_contains($javascript, "bindDateIcon('regularIncomePaymentDateButton',openDatePicker)")
    && substr_count($view, '<div class="date-input-wrap">') >= 2
    && substr_count($view, '<i class="bi bi-calendar3" aria-hidden="true"></i>') === 2
    && str_contains($stylesheet, '.regular-income-month-field { width: 160px; }')
    && str_contains($stylesheet, '.regular-income-payment-date-field { width: 185px; }');
$checks['modal_amount_and_text_alignment'] = str_contains($stylesheet, '#regularIncomeModal .form-control,')
    && str_contains($stylesheet, "#regularIncomeModal .select2-container .select2-selection--single .select2-selection__rendered { text-align: left !important; }")
    && str_contains($stylesheet, '#regularIncomeModal .number-input { text-align: right !important; font-variant-numeric: tabular-nums; }')
    && substr_count($javascript, "className='form-control form-control-sm number-input'") >= 2
    && str_contains($javascript, 'number-input regular-income-applied-amount-input');
$checks['compact_card_inputs'] = str_contains($stylesheet, '#regularIncomeEmployeeDetail .form-control-sm,')
    && str_contains($stylesheet, 'min-height: 26px !important; height: 26px !important;')
    && str_contains($stylesheet, '#regularIncomeEmployeeDetail .regular-income-manual-pay .btn-sm,')
    && str_contains($stylesheet, '#regularIncomeEmployeeDetail .regular-income-settlement-form .btn-sm { display: inline-flex;')
    && str_contains($stylesheet, '.regular-income-manual-pay .number-input,')
    && str_contains($stylesheet, 'text-align: right;');
$checks['settlement_form_fits_card'] = str_contains($stylesheet, '.regular-income-settlement-form { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);')
    && str_contains($stylesheet, '.regular-income-settlement-field.is-wide { grid-column: 1 / -1; }')
    && str_contains($stylesheet, '.regular-income-settlement-form > button { grid-column: 1 / -1;')
    && str_contains($stylesheet, '.regular-income-settlement-toggle { display: inline-flex;')
    && str_contains($stylesheet, 'height: 20px !important; min-height: 20px !important;')
    && str_contains($stylesheet, 'padding: 0 .25rem !important;');
$checks['status_badges_are_uniform_and_localized'] = str_contains($javascript, "DRAFT:['미상신','text-bg-secondary']")
    && str_contains($javascript, "APPROVED:['승인 완료','text-bg-success']")
    && str_contains($javascript, "regular-income-table-status")
    && str_contains($stylesheet, 'min-width: 72px; height: 24px; min-height: 24px;')
    && str_contains($stylesheet, 'white-space: nowrap;');
$checks['main_table_excludes_unregistered_accounting_columns'] = !str_contains($javascript, "{data:'evidence_id',title:'증빙'")
    && !str_contains($javascript, "{data:'transaction_id',title:'거래'")
    && !str_contains($javascript, "{data:'voucher_id',title:'전표'");
$checks['main_table_uses_document_status_without_virtual_approval_status'] = str_contains($javascript, "{data:'document_status',title:'문서상태'")
    && !str_contains($javascript, "{data:'approval_status',title:'현재 결재상태'")
    && !str_contains($javascript, 'approvalStatusBadge');
$checks['detail_uses_saved_line_snapshot_until_explicit_recalculation'] = !str_contains(
    $javascript,
    "items=data.items;if(items.length&&data.header.payment_date){items=await calculateItems"
);
$checks['employee_grid_action_buttons_stay_horizontal'] = str_contains($stylesheet, '.regular-income-grid-actions { display: inline-flex; flex-wrap: nowrap;')
    && str_contains($stylesheet, '#regularIncomeItemsGrid .regular-income-grid-actions .btn.btn-sm { display: inline-flex;')
    && str_contains($stylesheet, 'height: 26px; min-height: 26px !important;')
    && str_contains($stylesheet, 'padding: 0 4px !important;')
    && str_contains($stylesheet, 'word-break: keep-all;');
$checks['employee_grid_has_no_reserved_right_gutter'] = str_contains($stylesheet, '.regular-income-grid-host { max-width: 100%; overflow-x: auto; scrollbar-gutter: auto; }');
$checks['deduction_card_display_order'] = str_contains($javascript, "const deductionDisplayOrder=['EMPLOYMENT_INCOME_TAX','LOCAL_INCOME_TAX','NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','LONG_TERM_CARE_INSURANCE','EMPLOYMENT_INSURANCE','OTHER_DEDUCTION']");
$checks['employer_burden_embedded_in_deduction'] = str_contains($javascript, 'renderIncomeCalculationCards(institutionCards,institutionDto')
    && !str_contains($javascript, "['EMPLOYER_BURDEN','회사부담']");
$checks['employer_burden_is_readonly_automatic'] = str_contains($javascript, "employerLine.calculated_amount===null?'계산기준 미확정'")
    && str_contains($javascript, "employerHint.textContent='동일 소득·보수월액 기준 자동계산'")
    && !str_contains($javascript, 'createLineCard(employerLine,item)');
$checks['card_local_deduction_settlement'] = str_contains($javascript, "toggle.textContent='+ 정산 추가'")
    && str_contains($javascript, 'deduction_line_items:')
    && str_contains($javascript, "type.innerHTML='<option value=\"ADDITIONAL_COLLECTION\">추가징수</option><option value=\"REFUND\">환급</option>'");

$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($failed === [] ? 0 : 1);
