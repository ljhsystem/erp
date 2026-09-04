<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('PROJECT_ROOT', $root);
require $root . '/vendor/autoload.php';
require $root . '/core/Database.php';
require $root . '/core/DbPdo.php';

use App\Models\Institution\EmploymentContractModel;
use Core\DbPdo;

$model = (string) file_get_contents($root . '/app/Models/Institution/EmploymentContractModel.php');
$table = (string) file_get_contents($root . '/public/assets/js/pages/institution/employment-contract/table.js');

$expected = [
    'contract_type_name', 'contract_period_type_name', 'employment_category_name',
    'working_time_type_name', 'fixed_term_reason_code_name', 'work_location_type_name',
    'work_schedule_type_name', 'salary_type_name', 'payment_timing_name',
    'termination_reason_name', 'contract_status_name',
];
foreach ($expected as $field) {
    if (!str_contains($model, $field)) {
        throw new RuntimeException("근로계약 코드명 Projection 누락: {$field}");
    }
}
if (!str_contains($table, 'row?.[`${data}_name`] || getCodeNameByGroup')) {
    throw new RuntimeException('근로계약 DataTable이 API 코드명 Projection을 우선하지 않습니다.');
}

$page = (new EmploymentContractModel(DbPdo::conn()))->page(['start' => 0, 'length' => 50]);
foreach ($page['rows'] as $row) {
    foreach (['employment_category', 'working_time_type'] as $field) {
        if (trim((string) ($row[$field] ?? '')) !== ''
            && trim((string) ($row[$field . '_name'] ?? '')) === '') {
            throw new RuntimeException("실제 근로계약 코드명 조회 실패: {$field}");
        }
    }
}

echo json_encode([
    'success' => true,
    'rows' => count($page['rows']),
    'employment_category' => array_values(array_unique(array_column($page['rows'], 'employment_category_name'))),
    'working_time_type' => array_values(array_unique(array_column($page['rows'], 'working_time_type_name'))),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
