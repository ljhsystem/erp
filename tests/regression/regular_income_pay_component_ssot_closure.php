<?php

declare(strict_types=1);

use App\Services\Institution\PayComponentService;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomePayLineService;
use Core\DbPdo;
use Core\Helpers\AssetHelper;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$components = new PayComponentService($db);
$policy = new RegularEmploymentIncomePayLineService($components);
$options = $components->optionsForDate('2013-09-11');
$base = current(array_filter($options, static fn(array $option): bool => ($option['meta']['component_code'] ?? '') === 'BASE_SALARY'));
$meal = current(array_filter($options, static fn(array $option): bool => ($option['meta']['component_code'] ?? '') === 'MEAL_ALLOWANCE'));
$other = current(array_filter($options, static fn(array $option): bool => ($option['meta']['component_code'] ?? '') === 'OTHER_PAY'));
if (!$base || !$meal || !$other) throw new RuntimeException('검증할 급여항목 마스터가 없습니다.');

$manual = static fn(array $option, string $effect, string $token, array $forged = []): array => $forged + [
    'item_type_code' => 'PAY',
    'pay_effect_code' => $effect,
    'item_code' => 'FORGED_CODE',
    'item_name_snapshot' => '변조 항목명',
    'taxable_flag' => $option['meta']['taxable_flag'] ? 0 : 1,
    'final_amount' => 10000,
    'business_source_code' => 'MANUAL',
    'source_reference_id' => $option['value'],
    'source_key' => 'FORGED|' . $token,
    'business_reason' => '회귀검증',
];

$increaseOne = $policy->normalizeManualLine($manual($base, 'INCREASE', '11111111-1111-4111-8111-111111111111'), 'SYSTEM:REGRESSION', '2013-09-11');
$increaseTwo = $policy->normalizeManualLine($manual($base, 'INCREASE', '22222222-2222-4222-8222-222222222222'), 'SYSTEM:REGRESSION', '2013-09-11');
$decrease = $policy->normalizeManualLine($manual($base, 'DECREASE', '33333333-3333-4333-8333-333333333333'), 'SYSTEM:REGRESSION', '2013-09-11');
$mealIncrease = $policy->normalizeManualLine($manual($meal, 'INCREASE', '44444444-4444-4444-8444-444444444444'), 'SYSTEM:REGRESSION', '2013-09-11');
$otherIncreaseOne = $policy->normalizeManualLine($manual($other, 'INCREASE', '66666666-6666-4666-8666-666666666666'), 'SYSTEM:REGRESSION', '2013-09-11');
$otherIncreaseTwo = $policy->normalizeManualLine($manual($other, 'INCREASE', '77777777-7777-4777-8777-777777777777'), 'SYSTEM:REGRESSION', '2013-09-11');
$otherDecrease = $policy->normalizeManualLine($manual($other, 'DECREASE', '88888888-8888-4888-8888-888888888888'), 'SYSTEM:REGRESSION', '2013-09-11');
$contract = $policy->contractLine([
    'item_type_code' => 'PAY', 'item_code' => 'BASE_SALARY', 'item_name_snapshot' => '기본급',
    'taxable_flag' => 1, 'calculated_amount' => 100000, 'adjustment_amount' => 0, 'final_amount' => 100000,
], 'contract-id');
$calculator = new RegularEmploymentIncomeCalculationService($db);
$employeeId = 'ce50c61c-8b08-4f58-b8bc-e11f1dbafb84';
$baseline = $calculator->preview('2013-08', '2013-09-11', [['employee_id' => $employeeId, 'dependent_count_snapshot' => 1]], 'SYSTEM:REGRESSION')['results'][0];
$adjusted = $calculator->preview('2013-08', '2013-09-11', [[
    'employee_id' => $employeeId,
    'dependent_count_snapshot' => 1,
    'pay_line_items' => [$otherIncreaseOne],
]], 'SYSTEM:REGRESSION')['results'][0];

$invalidBlocked = false;
try {
    $policy->normalizeManualLine($manual($base, 'INCREASE', '55555555-5555-4555-8555-555555555555', [
        'source_reference_id' => '00000000-0000-4000-8000-000000000000',
    ]), 'SYSTEM:REGRESSION', '2013-09-11');
} catch (InvalidArgumentException) {
    $invalidBlocked = true;
}

$overDecreaseBlocked = false;
try {
    $tooLarge = $decrease;
    $tooLarge['final_amount'] = 200000;
    $tooLarge['calculated_amount'] = 200000;
    $policy->finalPayComposition([$contract, $tooLarge]);
} catch (RuntimeException) {
    $overDecreaseBlocked = true;
}

$duplicateIncreaseBlocked = false;
try {
    $policy->finalPayComposition([$contract, $increaseOne]);
} catch (InvalidArgumentException) {
    $duplicateIncreaseBlocked = true;
}

$checks = [
    'options_sorted_and_active_for_date' => count($options) >= 2
        && array_values($options) === array_values(array_filter($options, static fn(array $option): bool => !empty($option['value']))),
    'server_overwrites_forged_code_name_tax' => $increaseOne['item_code'] !== 'FORGED_CODE'
        && $increaseOne['item_name_snapshot'] === '기본급'
        && $increaseOne['taxable_flag'] === 1
        && $increaseOne['source_reference_id'] === $base['value'],
    'policy_tax_snapshot_applied' => $mealIncrease['item_name_snapshot'] === '식대'
        && $mealIncrease['taxable_flag'] === (int) $meal['meta']['taxable_flag'],
    'other_master_policy' => $other['label'] === '기타'
        && $other['meta']['taxable_flag'] === 1
        && $other['meta']['tax_label'] === '과세',
    'other_multiple_increases_allowed' => count($policy->finalPayComposition([$contract, $otherIncreaseOne, $otherIncreaseTwo])) === 3,
    'other_decrease_uses_taxable_balance' => array_sum(array_column($policy->finalPayComposition([$contract, $otherDecrease]), 'amount')) === 90000.0,
    'multiple_adjustments_have_distinct_identity' => $increaseOne['item_code'] !== $increaseTwo['item_code']
        && $increaseOne['source_key'] !== $increaseTwo['source_key'],
    'targeted_decrease_totals' => $policy->totals([$contract, $decrease])['gross_amount'] === 90000.0
        && array_sum(array_column($policy->finalPayComposition([$contract, $decrease]), 'amount')) === 90000.0,
    'calculation_consumers_receive_ssot_adjustment' => $adjusted['gross_amount'] === $baseline['gross_amount'] + 10000.0
        && $adjusted['taxable_amount'] === $baseline['taxable_amount'] + 10000.0
        && $adjusted['basis_resolutions']['taxable_monthly_salary_amount']['amount'] === $adjusted['taxable_amount'],
    'invalid_master_blocked' => $invalidBlocked,
    'target_component_over_decrease_blocked' => $overDecreaseBlocked,
    'duplicate_existing_component_increase_blocked' => $duplicateIncreaseBlocked,
];

$javascript = (string) file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$entryScriptPath = PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js';
ob_start();
require PROJECT_ROOT . '/app/views/institution/regular-employment-income/index.php';
$renderedView = (string) ob_get_clean();
$checks['rendered_view_has_stable_adjustment_action_host'] = substr_count($renderedView, 'id="regularIncomeLineItems"') === 1
    && str_contains($javascript, "document.getElementById('regularIncomeLineItems')");
$expectedEntryScript = AssetHelper::module('/assets/js/pages/institution/regular-employment-income/index.js');
$checks['rendered_view_loads_current_versioned_entry_script'] = ($pageScripts ?? '') === $expectedEntryScript
    && str_contains((string) $pageScripts, '?v=' . filemtime($entryScriptPath));
$service = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomePayLineService.php');
$employmentContract = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/EmploymentContractService.php');
$routes = (string) file_get_contents(PROJECT_ROOT . '/routes/api/institution.php');
$model = (string) file_get_contents(PROJECT_ROOT . '/app/Models/Institution/PayComponentModel.php');
$checks['ui_uses_ssot_select_and_readonly_tax'] = str_contains($javascript, "component.setAttribute('aria-label','급여항목 선택')")
    && str_contains($javascript, 'AdminPicker.select2(component')
    && str_contains($javascript, "taxable.textContent=draft.tax_label||'미선택'")
    && str_contains($javascript, "const taxable=document.createElement('span')")
    && !str_contains($javascript, "const taxable=document.createElement('input')")
    && !str_contains($javascript, "name.placeholder='항목'")
    && !str_contains($javascript, "taxable.innerHTML='<option");
$checks['draft_card_precedes_option_request'] = str_contains($javascript, 'const createPayAdjustmentDraft=()=>({client_key:adjustmentToken()')
    && str_contains($javascript, 'item.pay_adjustment_drafts.push(draft);if(!showDetail(employeeId))')
    && str_contains($javascript, 'item.pay_adjustment_drafts.forEach(renderDraft)')
    && str_contains($javascript, "fields.dataset.clientKey=draft.client_key");
$checks['delegated_add_action_matches_rendered_button'] = str_contains($javascript, "open.dataset.action='pay-adjustment-add'")
    && str_contains($javascript, "open.dataset.employeeId=String(item.employee_id)")
    && str_contains($javascript, "linesHost.addEventListener('click',event=>")
    && str_contains($javascript, "closest('button[data-action=\"pay-adjustment-add\"]')")
    && str_contains($javascript, "linesHost.dataset.payAdjustmentActionBound='true'");
$checks['add_action_verifies_draft_dom_and_reports_errors'] = str_contains($javascript, "item.pay_adjustment_drafts.push(draft)")
    && str_contains($javascript, ".regular-income-adjustment-draft[data-client-key=\"")
    && str_contains($javascript, "notify('error',error.message||'증감 카드를 생성하지 못했습니다.')")
    && str_contains($javascript, "throw new Error('지급항목 영역을 찾을 수 없습니다.')");
$checks['draft_fields_do_not_require_ssot_before_selection'] = str_contains($javascript, 'source_reference_id:null,item_code:null,item_name_snapshot:null,default_tax_type:null,tax_policy_code:null,taxable_flag:null')
    && str_contains($javascript, "taxable.textContent=draft.tax_label||'미선택'")
    && str_contains($javascript, 'draft.source_reference_id=option?.value||null')
    && str_contains($javascript, 'draft.item_name_snapshot=option?.meta?.component_name||option?.label||null')
    && str_contains($javascript, 'draft.taxable_flag=option?Number(option.meta?.taxable_flag||0):null');
$checks['option_failure_keeps_card_and_retries'] = str_contains($javascript, "status.textContent=error.message||'급여항목을 불러오지 못했습니다.'")
    && str_contains($javascript, "retry.classList.remove('is-hidden')")
    && str_contains($javascript, "retry.addEventListener('click',()=>{void hydrate(true);})")
    && str_contains($javascript, 'if(!fields.isConnected)return;');
$checks['draft_delete_is_employee_local'] = str_contains($javascript, 'String(candidate.client_key)!==String(draft.client_key)')
    && str_contains($javascript, 'section.dataset.employeeId=String(item.employee_id)')
    && str_contains($javascript, "remove.addEventListener('click',event=>")
    && str_contains($javascript, 'button[data-action="pay-adjustment-add"][data-employee-id="');
$checks['unfinished_draft_is_validated_on_save'] = str_contains($javascript, 'const draftOwner=items.find(item=>(item.pay_adjustment_drafts||[]).length)')
    && str_contains($javascript, '직원의 증감 카드에서 지급항목, 금액, 사유를 입력하고 완료해 주세요.');
$checks['option_requests_are_deduplicated'] = str_contains($javascript, 'const payComponentOptionRequests = new Map();')
    && str_contains($javascript, 'if(payComponentOptionRequests.has(date))return payComponentOptionRequests.get(date)')
    && str_contains($javascript, 'payComponentOptionRequests.set(date,pending)');
$checks['increase_and_decrease_candidates_are_distinct'] = str_contains($javascript, "if(draft.pay_effect_code==='DECREASE')return allOptions.filter")
    && str_contains($javascript, "optionCode(option)==='OTHER_PAY'||availableAmount(draft,option)>0")
    && str_contains($javascript, "optionCode(option)==='OTHER_PAY'||(!existing.has(optionCode(option))&&!pending.has(optionCode(option)))");
$checks['candidate_refresh_events_are_employee_local'] = str_contains($javascript, "effect.addEventListener('change',()=>{")
    && str_contains($javascript, 'select2:select.regularIncomeTaxPolicy select2:clear.regularIncomeTaxPolicy change.regularIncomeTaxPolicy')
    && str_contains($javascript, "onBlur:()=>{draft.final_amount=normalizedAmount();")
    && str_contains($javascript, 'const removeDraft=draft=>')
    && str_contains($javascript, 'showDetail(item.employee_id);');
$checks['server_requires_master_and_effective_date'] = str_contains($service, 'requireActiveForDate($componentId, $effectiveDate)')
    && str_contains($service, "'item_name_snapshot' => (string) \$component['component_name']")
    && str_contains($service, "'taxable_flag' => \$this->payComponents->taxableFlag(\$component)");
$checks['employment_contract_reuses_shared_service'] = str_contains($employmentContract, 'private PayComponentService $payComponents;')
    && str_contains($employmentContract, "'pay_components' => \$this->payComponents->optionsForDate(date('Y-m-d'))");
$checks['shared_options_route_is_unique'] = substr_count($routes, '/api/institution/human-resources/pay-component/options') === 1
    && substr_count($routes, 'api.institution.human_resources.pay_component.options') === 1;
$checks['inactive_and_expired_components_are_filtered'] = str_contains($model, 'WHERE id = :id AND is_active = 1 AND deleted_at IS NULL')
    && str_contains($model, 'effective_from IS NULL OR effective_from <= :date_from')
    && str_contains($model, 'effective_to IS NULL OR effective_to >= :date_to');

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
