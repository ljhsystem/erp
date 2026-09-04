<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adminPicker = file_get_contents($root . '/public/assets/js/common/picker/admin_picker.js');
$dayPicker = file_get_contents($root . '/public/assets/js/common/picker/picker.dayofmonth.js');
$bridge = file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/payment-day.js');
$view = file_get_contents($root . '/app/views/institution/employment-contract/index.php');
$service = file_get_contents($root . '/app/Services/Institution/EmploymentContractService.php');

$checks = [
    'common_picker_mode_registered' => str_contains($adminPicker, "case 'day-of-month':"),
    'picker_has_1_to_31_grid' => str_contains($dayPicker, 'day <= 31') && str_contains($dayPicker, 'day-of-month-grid'),
    'picker_is_hidden_before_open' => str_contains($dayPicker, "container.classList.add('picker', 'is-day-of-month')"),
    'picker_uses_domain_neutral_labels' => str_contains($dayPicker, "title: titleText = '일자 선택'")
        && str_contains($dayPicker, "ariaLabel = '월 내 일자 선택'")
        && !str_contains($dayPicker, '매월 지급일'),
    'manual_input_normalized' => str_contains($bridge, 'normalizeDayOfMonthInputValue(input.value)'),
    'picker_selection_updates_input' => str_contains($bridge, 'input.value = String(day)'),
    'employment_contract_uses_common_picker' => str_contains($view, 'data-employment-payment-day')
        && str_contains($view, 'employment-contract-payment-day-picker'),
    'native_number_spinner_removed' => !str_contains($view, 'type="number" name="payment_day"'),
    'server_contract_unchanged' => str_contains($service, "\$paymentDay = (int) (\$input['payment_day'] ?? 0)")
        && str_contains($service, '$paymentDay < 1 || $paymentDay > 31'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
