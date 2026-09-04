<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$employeeService = file_get_contents($root . '/app/Services/System/EmployeeService.php');
$modalRuntime = file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js');
$view = file_get_contents($root . '/app/views/institution/employment-contract/index.php');

$checks = [
    'picker_exposes_position_name' => str_contains($employeeService, "'position_name' => trim((string) (\$row['position_name'] ?? ''))"),
    'position_is_contract_snapshot_select' => str_contains($view, 'name="job_title_snapshot" data-preserve-raw-code-value="true" required'),
    'employee_selection_proposes_snapshot' => str_contains($modalRuntime, 'event.params?.data?.position_name')
        && str_contains($modalRuntime, 'AdminPicker.setSelect2Value(form.elements.job_title_snapshot'),
    'employee_clear_resets_snapshot' => str_contains($modalRuntime, 'AdminPicker.clearSelect2(form.elements.job_title_snapshot)'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
