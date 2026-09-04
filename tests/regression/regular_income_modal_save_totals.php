<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use Core\DbPdo;

$db = DbPdo::conn();
$user = $db->query("SELECT id, username FROM auth_users WHERE is_active = 1 ORDER BY created_at, id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$user) throw new RuntimeException('회귀검사 Actor를 찾을 수 없습니다.');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];
$_SESSION['auth_state'] = ['user_id' => $user['id'], 'status' => 'NORMAL'];
$service = new RegularEmploymentIncomeService($db);
$calculation = new RegularEmploymentIncomeCalculationService($db);
$before = $service->detail('4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6')['data'];
$employees = array_map(static fn(array $item): array => [
    'employee_id' => $item['employee_id'],
    'dependent_count_snapshot' => $item['dependent_count_snapshot'],
    'national_pension_basis_snapshot' => $item['national_pension_basis_snapshot'],
    'health_insurance_basis_snapshot' => $item['health_insurance_basis_snapshot'],
    'employment_insurance_basis_snapshot' => $item['employment_insurance_basis_snapshot'],
    'pay_line_items' => array_values(array_filter(
        $item['line_items'],
        static fn(array $line): bool => ($line['item_type_code'] ?? '') === 'PAY'
            && in_array($line['pay_effect_code'] ?? '', ['INCREASE', 'DECREASE'], true)
    )),
    'deduction_line_items' => [],
    'insurance_override_line_items' => array_values(array_filter(
        $item['line_items'],
        static fn(array $line): bool => ($line['item_type_code'] ?? '') === 'DEDUCTION'
            && in_array($line['item_code'] ?? '', ['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'], true)
            && (abs((float) ($line['adjustment_amount'] ?? 0)) >= 0.01 || str_starts_with((string) ($line['source_key'] ?? ''), 'INSURANCE_OVERRIDE'))
    )),
], $before['items']);
$preview = $calculation->preview(
    '2013-09',
    '2013-10-11',
    $employees,
    'SYSTEM:REGRESSION'
);

$javascript = (string) file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js');

$db->beginTransaction();
$exitCode = 1;
try {
    $created = $service->savePayEffect([
        'income_year_month' => '2013-09',
        'payment_date' => '2013-10-11',
        'title' => '모달 합계 롤백 Fixture',
        'description' => '회귀검사 후 전체 롤백',
        'items' => $preview['results'],
    ]);
    $documentId = (string) $created['data']['id'];
    $preflight = new ReflectionMethod(RegularEmploymentIncomeService::class, 'assertSubmittable');
    $preflightPassed = true;
    try {
        $preflight->invoke($service, $documentId);
    } catch (Throwable $exception) {
        $preflightPassed = false;
    }
    $service->savePayEffect([
        'id' => $documentId,
        'income_year_month' => '2013-09',
        'payment_date' => '2013-10-11',
        'title' => '모달 합계 롤백 Fixture',
        'description' => '회귀검사 후 전체 롤백',
        'items' => $preview['results'],
    ]);
    $after = $service->detail($documentId)['data'];
    $page = $service->page([
        'start' => 0,
        'length' => 100,
        'filters' => json_encode([['field' => 'income_year_month', 'value' => '2013-09']], JSON_UNESCAPED_UNICODE),
    ]);
    $listRow = array_values(array_filter(
        $page['data'],
        static fn(array $row): bool => ($row['id'] ?? '') === $documentId
    ))[0] ?? [];
    $itemDeduction = array_sum(array_map(
        static fn(array $item): float => (float) $item['deduction_amount'],
        $after['items']
    ));
    $itemNet = array_sum(array_map(
        static fn(array $item): float => (float) $item['net_payment_amount'],
        $after['items']
    ));
    $checks = [
        'ui_recalculates_immediately_before_save' => str_contains(
            $javascript,
            'items=await calculateItems(form.elements.income_year_month.value,paymentDateInput.value,items)'
        ),
        'ui_cancels_scheduled_recalculations_before_save' => str_contains($javascript, 'recalculationTimers.forEach(timer=>clearTimeout(timer))')
            && str_contains($javascript, 'recalculationTimers.clear()'),
        'ui_projects_line_deductions_for_save_compatibility' => str_contains($javascript, "employment_insurance_amount:deductionAmount('EMPLOYMENT_INSURANCE')")
            && str_contains($javascript, "income_tax_amount:deductionAmount('EMPLOYMENT_INCOME_TAX')"),
        'ui_closes_modal_after_save' => str_contains($javascript, 'modal.hide();table.ajax.reload(null,false)'),
        'preview_deduction_157380' => array_sum(array_column($preview['results'], 'deduction_amount')) === 157380.0,
        'preview_projects_applied_employment_insurance' => array_sum(array_column($preview['results'], 'employment_insurance_amount')) === 6420.0,
        'approval_preflight_accepts_complete_industrial_rounding_policy' => $preflightPassed,
        'saved_header_matches_item_deduction' => (float) $after['header']['deduction_amount'] === $itemDeduction,
        'saved_header_matches_item_net' => (float) $after['header']['net_payment_amount'] === $itemNet,
        'saved_header_deduction_157380' => (float) $after['header']['deduction_amount'] === 157380.0,
        'saved_header_net_2020400' => (float) $after['header']['net_payment_amount'] === 2020400.0,
        'list_uses_saved_header_deduction' => (float) ($listRow['deduction_amount'] ?? -1) === 157380.0,
        'list_uses_saved_header_net' => (float) ($listRow['net_payment_amount'] ?? -1) === 2020400.0,
    ];
    $failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
    echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
    $exitCode = $failed === [] ? 0 : 1;
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
exit($exitCode);
