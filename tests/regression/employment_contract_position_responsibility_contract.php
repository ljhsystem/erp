<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/institution/employment-contract/index.php');
$service = file_get_contents($root . '/app/Services/Institution/EmploymentContractService.php');
$contractModel = file_get_contents($root . '/app/Models/Institution/EmploymentContractModel.php');
$modal = file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js');
$incomeModel = file_get_contents($root . '/app/Models/Institution/RegularEmploymentIncomeModel.php');
$applyService = file_get_contents($root . '/app/Services/Institution/PersonnelActionApplyService.php');

$checks = [
    'contract_snapshot_is_reference_select' => str_contains($view, 'name="job_title_snapshot" data-preserve-raw-code-value="true" required'),
    'position_options_use_existing_ssot' => str_contains($service, "\$this->positions->getAll(['is_active' => 1])"),
    'selected_snapshot_is_validated' => str_contains($service, "'job_title_snapshot' => \$this->positionSnapshot"),
    'server_does_not_force_employee_current_position' => !str_contains($service, "'job_title_snapshot' => \$employee['position_name']")
        && !str_contains($contractModel, 'pos.position_name'),
    'employee_current_value_is_default_only' => str_contains($modal, 'event.params?.data?.position_name')
        && str_contains($modal, 'AdminPicker.setSelect2Value(form.elements.job_title_snapshot'),
    'draft_can_choose_another_position' => !str_contains($view, 'name="job_title_snapshot" readonly'),
    'approved_revision_preserves_snapshot' => str_contains($service, "'project_id', 'work_location_detail', 'job_title_snapshot', 'job_description'"),
    'personnel_action_owns_current_position' => str_contains($applyService, "updateEmployee(\$employeeId, ['position_id'=>\$change['after_position_id']])"),
    'historical_income_prefers_contract_snapshot' => str_contains($incomeModel, "COALESCE(NULLIF(c.job_title_snapshot,''),p.position_name) position_name"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
