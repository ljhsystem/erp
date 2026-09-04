<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$agents = file_get_contents($root . '/AGENTS.md');
$employeeModel = file_get_contents($root . '/app/Models/User/EmployeeModel.php');
$employeeService = file_get_contents($root . '/app/Services/System/EmployeeService.php');
$projectModel = file_get_contents($root . '/app/Models/System/ProjectModel.php');
$projectService = file_get_contents($root . '/app/Services/System/ProjectService.php');
$payComponentModel = file_get_contents($root . '/app/Models/Institution/PayComponentModel.php');

$checks = [
    'common_rule_registered' => str_contains($agents, '### 16-5. 참조 목록 정렬 규칙'),
    'employee_query_orders_by_sort_no' => str_contains($employeeModel, 'p.sort_no ASC,')
        && !str_contains($employeeModel, 'WHEN p.employee_name LIKE :prefixKeyword'),
    'employee_picker_preserves_sort_no' => str_contains($employeeService, "'sort_no' => (int) (\$row['sort_no'] ?? 0)"),
    'project_query_orders_by_sort_no' => str_contains($projectModel, "ORDER BY\n                sort_no ASC,")
        && !str_contains($projectModel, 'WHEN project_name LIKE :prefix'),
    'project_picker_preserves_sort_no' => str_contains($projectService, "'sort_no' => (int) (\$row['sort_no'] ?? 0)"),
    'pay_component_order_preserved' => str_contains($payComponentModel, 'ORDER BY sort_no ASC, component_name ASC, id ASC'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
