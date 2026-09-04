<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$ui = (string) file_get_contents($root . '/public/assets/js/pages/institution/regular-employment-income/index.js');
$view = (string) file_get_contents($root . '/app/views/institution/regular-employment-income/index.php');

$checks = [
    'single_date_driven_service' => !str_contains($service, 'HISTORICAL_IMPORT')
        && str_contains($service, 'in_array($type, $insuranceTypes, true) ? $insuranceDate : $paymentDate'),
    'coverage_is_optional_reference' => str_contains($service, "'INSURANCE_ASSESSMENT_BASE' => '확정 Coverage/Basis 자동 제안'")
        && !str_contains($service, '확정 Coverage/Basis가 필요합니다.'),
    'pay_items_supply_missing_basis' => str_contains($service, '$sourceCode = \'PAY_ITEM_FINAL_AMOUNT\'')
        && str_contains($service, '지급항목 최종금액으로 계산기초를 자동 산출했습니다.'),
    'snapshot_override_is_supported' => str_contains($service, '$sourceCode = \'PAYROLL_SNAPSHOT\'')
        && str_contains($service, "'USER_CONFIRMED'"),
    'source_mode_ui_removed' => !str_contains($view, '자료구분')
        && !str_contains($view, 'regularIncomeSource')
        && !str_contains($ui, 'sourceInput')
        && !str_contains($ui, 'isHistorical'),
    'actual_value_adjustment_is_common' => str_contains($ui, "applied.innerHTML='<span>적용금액</span>'")
        && str_contains($ui, 'line.adjustment_amount=appliedAmount===null||calculated===null?null:appliedAmount-Math.trunc(calculated)')
        && str_contains($ui, "reasonWrap.classList.toggle('is-hidden',!changed)")
        && !str_contains($ui, '실제값 적용'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
