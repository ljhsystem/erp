<?php

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$service = new DataTableColumnMetaService($pdo);
$expected = [
    'leave-status' => [
        'tables' => ['institution_leave_requests','institution_leave_request_items','institution_leave_types','user_employees','auth_users','user_approval_requests','user_approval_request_steps'],
        'types' => ['employee_name'=>'join','leave_type_names'=>'projection','leave_from'=>'projection','leave_to'=>'projection','approval_status'=>'join','current_step_name'=>'join','completed_at'=>'join','__actions'=>'virtual'],
    ],
    'leave-balance' => [
        'tables' => ['user_employees','institution_leave_types','institution_leave_ledger_entries'],
        'types' => ['employee_name'=>'join','type_name'=>'join','base_year'=>'projection','balance_minutes'=>'projection'],
    ],
    'leave-request' => [
        'tables' => ['institution_leave_requests','institution_leave_request_items','institution_leave_types','user_employees','auth_users','user_approval_requests','user_approval_request_steps'],
        'types' => ['leave_type_names'=>'projection','leave_period'=>'projection','request_unit_names'=>'projection','deductible_total_minutes'=>'projection','approval_status'=>'join','current_step_name'=>'join','completed_at'=>'join','__actions'=>'virtual'],
    ],
    'leave-type' => [
        'tables' => ['institution_leave_types'],
        'types' => [],
    ],
];

$report = [];
foreach ($expected as $domain => $contract) {
    if (!$service->hasDomain($domain)) {
        throw new RuntimeException($domain . ' metadata domain이 등록되지 않았습니다.');
    }
    $columns = $service->columnsForDomain($domain);
    $physical = array_values(array_filter($columns, static fn(array $column): bool => ($column['column_type'] ?? 'physical') === 'physical'));
    $byKey = [];
    foreach ($columns as $column) $byKey[(string) $column['key']] = $column;
    $tableCounts = [];
    foreach ($physical as $column) $tableCounts[(string) $column['table']] = ($tableCounts[(string) $column['table']] ?? 0) + 1;
    if (array_keys($tableCounts) !== $contract['tables']) {
        throw new RuntimeException($domain . ' 원본 테이블 등록 순서가 실제 SQL 계약과 다릅니다.');
    }
    foreach ($tableCounts as $table => $count) {
        $actual = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=" . $pdo->quote($table))->fetchColumn();
        if ($count !== $actual) throw new RuntimeException($domain . '의 ' . $table . ' 물리컬럼 수가 DB와 다릅니다.');
        foreach ($physical as $column) {
            if (($column['table'] ?? '') !== $table) continue;
            if ($domain !== 'leave-type' && ($column['key'] ?? '') !== $table . '.' . ($column['source_column'] ?? '')) throw new RuntimeException($domain . ' 물리컬럼 key가 table.column 형식이 아닙니다.');
        }
    }
    foreach ($contract['types'] as $key => $type) {
        if (($byKey[$key]['column_type'] ?? '') !== $type) throw new RuntimeException($domain . '의 ' . $key . ' 유형이 ' . $type . '이 아닙니다.');
        if ($type !== 'virtual' && empty($byKey[$key]['description'])) throw new RuntimeException($domain . '의 ' . $key . ' 설명이 없습니다.');
    }
    $report[$domain] = [
        'tables' => $tableCounts,
        'physical' => count($physical),
        'join' => count(array_filter($columns, static fn(array $column): bool => ($column['column_type'] ?? '') === 'join')),
        'projection' => count(array_filter($columns, static fn(array $column): bool => ($column['column_type'] ?? '') === 'projection')),
        'virtual' => count(array_filter($columns, static fn(array $column): bool => ($column['column_type'] ?? '') === 'virtual')),
        'total' => count($columns),
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo "leave TableSettings metadata audit passed\n";
