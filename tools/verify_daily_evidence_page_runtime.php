<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Models\Ledger\DailyEmploymentIncomeEvidenceReadModel;
use App\Models\Ledger\EvidenceSchemaModel;
use App\Controllers\Ledger\EvidenceController;
use App\Controllers\Ledger\EvidenceListController;
use App\Services\Ledger\EvidenceTypePolicyService;
use App\Services\Ledger\EvidenceBodyReadService;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use Core\DbPdo;

$db = DbPdo::conn();
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'sukhyang') {
    throw new RuntimeException('OPERATING_SCHEMA_MISMATCH');
}
$bodyReadService = new EvidenceBodyReadService(
    $db,
    new EvidenceBodyStatusProjectionModel(),
    static fn(string $type): string => EvidenceTypePolicyService::normalizeLegacyDataType($type)
);
$directPersonalRows = $bodyReadService->rowsForTypes(['EMPLOYEE_EXPENSE_PERSONAL'], '', '');
if (in_array('--personal-api', $argv, true)) {
    $_GET = [];
    $_POST = ['import_type' => 'EMPLOYEE_EXPENSE_PERSONAL', 'draw' => 1, 'start' => 0, 'length' => 50];
    http_response_code(200);
    ob_start();
    (new EvidenceListController($db))->apiList();
    $personalPayload = json_decode((string) ob_get_clean(), true);
    $personalRows = is_array($personalPayload['data'] ?? null) ? $personalPayload['data'] : [];
    $personalFirst = $personalRows[0] ?? [];
    echo json_encode([
        'status' => http_response_code(),
        'count' => count($personalRows),
        'total_amount' => array_sum(array_column($personalRows, 'raw_total_amount')),
        'missing_common_columns' => array_values(array_diff([
            'id','sort_no','external_key','source_type','import_type','business_unit','transaction_direction',
            'operation_type','client_id','employee_id','project_id','bank_account_id','card_id','work_team_id',
            'evidence_status','created_at','created_by','updated_at','updated_by','deleted_at','deleted_by'
        ], array_keys($personalFirst))),
        'forbidden_fields' => array_values(array_intersect(
            ['source_hash','snapshot_json','business_key_hash'],
            array_keys($personalFirst)
        )),
        'legacy_team_id_returned' => array_key_exists('team_id', $personalFirst),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$policyService = new EvidenceTypePolicyService(
    static fn(string $type): string => EvidenceTypePolicyService::normalizeLegacyDataType($type),
    $db
);
$policies = $policyService->statusViewImportTypePolicies();
$dailyPolicies = array_values(array_filter(
    $policies,
    static fn(array $row): bool => ($row['code'] ?? '') === 'DAILY_EMPLOYMENT_INCOME'
));
$model = new DailyEmploymentIncomeEvidenceReadModel($db, new EvidenceSchemaModel($db));
$rows = $model->findList();
$detail = count($rows) === 1 ? $model->findById((string) $rows[0]['id']) : null;
$rawLines = is_array($detail['raw_lines'] ?? null) ? $detail['raw_lines'] : [];
$sum = static function (string $type) use ($rawLines): float {
    return array_sum(array_map(
        static fn(array $line): float => strtoupper((string) ($line['line_type_code'] ?? '')) === $type
            ? (float) ($line['raw_final_amount'] ?? 0)
            : 0.0,
        $rawLines
    ));
};
$forbidden = ['source_hash', 'snapshot_json', 'business_key_hash'];
$listKeys = $rows !== [] ? array_keys($rows[0]) : [];
$_SERVER['REQUEST_URI'] = '/ledger/data/daily-employment-incomes';
ob_start();
(new EvidenceController($db))->webTypePage();
$pageHtml = (string) ob_get_clean();
$_GET = [];
$_POST = ['import_type' => 'DAILY_EMPLOYMENT_INCOME', 'draw' => 1, 'start' => 0, 'length' => 25];
http_response_code(200);
ob_start();
(new EvidenceListController($db))->apiList();
$listApiPayload = json_decode((string) ob_get_clean(), true);
$listApiStatus = http_response_code();
$apiRows = is_array($listApiPayload['data'] ?? null) ? $listApiPayload['data'] : [];
$_GET = [];
$_POST = ['import_type' => 'DAILY_EMPLOYMENT_INCOME', 'id' => (string) ($rows[0]['id'] ?? '')];
http_response_code(200);
ob_start();
(new EvidenceListController($db))->apiList();
$detailApiPayload = json_decode((string) ob_get_clean(), true);
$detailApiStatus = http_response_code();
$detailApiRows = is_array($detailApiPayload['data'] ?? null) ? $detailApiPayload['data'] : [];
$aliasResults = [];
foreach (['DAILY_WORK_REPORT', 'PAYROLL_WITHHOLDING'] as $alias) {
    $_GET = [];
    $_POST = ['import_type' => $alias, 'start' => 0, 'length' => 25];
    http_response_code(200);
    ob_start();
    (new EvidenceListController($db))->apiList();
    $aliasPayload = json_decode((string) ob_get_clean(), true);
    $aliasRows = is_array($aliasPayload['data'] ?? null) ? $aliasPayload['data'] : [];
    $aliasResults[$alias] = [
        'status' => http_response_code(),
        'count' => count($aliasRows),
        'response_type' => $aliasRows[0]['import_type'] ?? null,
    ];
}
$commonColumns = ['id','sort_no','external_key','source_type','import_type','business_unit','transaction_direction',
    'operation_type','client_id','employee_id','project_id','bank_account_id','card_id','work_team_id',
    'evidence_status','created_at','created_by','updated_at','updated_by','deleted_at','deleted_by'];
$pageCases = [
    'PAYROLL' => ['/ledger/data/payroll', 2],
    'EMPLOYEE_EXPENSE_PERSONAL' => ['/ledger/data/employee-personal-expenses', 9],
    'DAILY_EMPLOYMENT_INCOME' => ['/ledger/data/daily-employment-incomes', 1],
];
$pageResults = [];
foreach ($pageCases as $type => [$path, $expectedCount]) {
    $_SERVER['REQUEST_URI'] = $path;
    ob_start();
    (new EvidenceController($db))->webTypePage();
    $html = (string) ob_get_clean();
    $_GET = [];
    $_POST = ['import_type' => $type, 'draw' => 1, 'start' => 0, 'length' => 50];
    http_response_code(200);
    ob_start();
    (new EvidenceListController($db))->apiList();
    $payload = json_decode((string) ob_get_clean(), true);
    $typeRows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $first = $typeRows[0] ?? [];
    $pageResults[$type] = [
        'url' => $path,
        'page_status' => $html !== '' ? 200 : 500,
        'page_ready' => str_contains($html, 'data-page-ready="1"'),
        'uses_common_table' => str_contains($html, 'id="evidenceStatusTable"'),
        'uses_common_search' => str_contains($html, 'searchForm') || str_contains($html, 'search-form'),
        'api_status' => http_response_code(),
        'count' => count($typeRows),
        'expected_count' => $expectedCount,
        'missing_common_columns' => array_values(array_diff($commonColumns, array_keys($first))),
        'forbidden_fields' => array_values(array_intersect($forbidden, array_keys($first))),
        'legacy_team_id_returned' => array_key_exists('team_id', $first),
    ];
    if ($type === 'PAYROLL') {
        $pageResults[$type]['gross_total'] = array_sum(array_column($typeRows, 'raw_gross_payment_amount'));
        $pageResults[$type]['deduction_total'] = array_sum(array_column($typeRows, 'raw_worker_deduction_amount'));
        $pageResults[$type]['net_total'] = array_sum(array_column($typeRows, 'raw_net_payment_amount'));
    } elseif ($type === 'EMPLOYEE_EXPENSE_PERSONAL') {
        $pageResults[$type]['total_amount'] = array_sum(array_column($typeRows, 'raw_total_amount'));
        $pageResults[$type]['raw_source_fields_present'] = count(array_diff(
            ['source_document_id','source_item_id','approval_request_id','raw_application_date','raw_project_id','raw_client_id'],
            array_keys($first)
        )) === 0;
    }
}

echo json_encode([
    'database' => 'sukhyang',
    'canonical_policy_count' => count($dailyPolicies),
    'page_rendered' => $pageHtml !== '',
    'page_ready' => str_contains($pageHtml, 'data-page-ready="1"'),
    'page_has_table' => str_contains($pageHtml, 'id="evidenceStatusTable"'),
    'list_api_status' => $listApiStatus,
    'list_api_count' => count($apiRows),
    'list_api_forbidden_fields' => $apiRows !== []
        ? array_values(array_intersect($forbidden, array_keys($apiRows[0])))
        : [],
    'detail_api_status' => $detailApiStatus,
    'detail_api_count' => count($detailApiRows),
    'detail_api_raw_line_count' => count($detailApiRows[0]['raw_lines'] ?? []),
    'aliases' => $dailyPolicies[0]['aliases'] ?? [],
    'alias_results' => $aliasResults,
    'list_count' => count($rows),
    'list_import_type' => $rows[0]['import_type'] ?? null,
    'list_forbidden_fields' => array_values(array_intersect($forbidden, $listKeys)),
    'detail_found' => $detail !== null,
    'raw_line_count' => count($rawLines),
    'pay_total' => $sum('PAY'),
    'deduction_total' => $sum('DEDUCTION'),
    'employer_burden_total' => $sum('EMPLOYER_BURDEN'),
    'transaction_operation_type' => $detail['operation_type'] ?? null,
    'transaction_settlement_amount' => isset($detail['transaction_settlement_amount'])
        ? (float) $detail['transaction_settlement_amount']
        : null,
    'transaction_final_amount' => isset($detail['transaction_final_amount'])
        ? (float) $detail['transaction_final_amount']
        : null,
    'internal_pages' => $pageResults,
    'direct_personal_count' => count($directPersonalRows),
    'direct_personal_missing_common_columns' => $directPersonalRows !== []
        ? array_values(array_diff($commonColumns, array_keys($directPersonalRows[0])))
        : $commonColumns,
    'direct_personal_forbidden_fields' => $directPersonalRows !== []
        ? array_values(array_intersect($forbidden, array_keys($directPersonalRows[0])))
        : [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
