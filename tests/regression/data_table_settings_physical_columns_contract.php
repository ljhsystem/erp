<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$service = new DataTableColumnMetaService($pdo);
$domains = [
    'department' => ['user_departments'],
    'position' => ['user_positions'],
    'code' => ['system_codes'],
    'employee' => ['user_employees', 'auth_users'],
    'employment-contract' => [
        'institution_employment_contracts',
        'institution_employment_contracts_weekly_schedules',
        'institution_employment_contracts_work_schedule_policies',
        'institution_employment_contracts_components',
    ],
    'personnel-action' => ['institution_personnel_actions', 'institution_personnel_actions_targets'],
    'transaction' => ['ledger_transactions', 'ledger_transaction_items', 'ledger_transaction_settlements'],
    'voucher' => ['ledger_vouchers', 'ledger_voucher_lines'],
    'evidence-payroll-report' => ['ledger_evidence_salary_report'],
];

$result = [];
foreach ($domains as $domain => $tables) {
    $columns = $service->columnsForDomain($domain);
    $offset = 0;
    $tableCounts = [];
    foreach ($tables as $table) {
        $statement = $pdo->prepare('SELECT COLUMN_NAME, ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table ORDER BY ORDINAL_POSITION');
        $statement->execute([':table' => $table]);
        $physical = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($physical === []) {
            continue;
        }
        $slice = array_slice($columns, $offset, count($physical));
        if (count($slice) !== count($physical)) {
            throw new RuntimeException("{$domain}: {$table} 물리컬럼 수가 일치하지 않습니다.");
        }
        foreach ($physical as $index => $source) {
            $meta = $slice[$index] ?? [];
            if (($meta['table'] ?? '') !== $table
                || ($meta['source_column'] ?? '') !== ($source['COLUMN_NAME'] ?? '')
                || (int) ($meta['source_ordinal_position'] ?? 0) !== (int) ($source['ORDINAL_POSITION'] ?? 0)
            ) {
                throw new RuntimeException("{$domain}: {$table} 등록순서 또는 ORDINAL_POSITION 불일치");
            }
        }
        $tableCounts[$table] = count($physical);
        $offset += count($physical);
    }
    if ($offset !== count($columns)) {
        throw new RuntimeException("{$domain}: 등록 테이블 합계와 metadata 합계가 일치하지 않습니다.");
    }
    $result[$domain] = ['tables' => $tableCounts, 'total' => count($columns)];
}

echo json_encode(['success' => true, 'domains' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
