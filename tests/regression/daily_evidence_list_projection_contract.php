<?php

declare(strict_types=1);

use App\Services\Ledger\EvidenceGenerationService;
use App\Services\System\DataTableColumnMetaService;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\PayrollEvidenceReadModel;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$normalizeType = static function (string $type): string {
    $normalized = strtoupper(trim($type));
    return match ($normalized) {
        'DAILY_WORKER', 'DAILY_WORK_REPORT', 'DAILY_EMPLOYMENT_INCOME' => 'PAYROLL_WITHHOLDING',
        default => $normalized,
    };
};

$service = new EvidenceGenerationService(
    DbPdo::conn(),
    static function (): void {},
    static function (): void {},
    static fn(string $type): bool => $normalizeType($type) === 'PAYROLL_WITHHOLDING',
    $normalizeType,
    static fn(string $type): string => strtoupper(trim($type)),
    static fn(string $type): array => [$normalizeType($type)],
    static fn(string $type): array => [$normalizeType($type)],
    static fn(string $type): string => 'APPROVAL',
    static fn(string $type): string => $type === 'APPROVAL' ? '승인문서' : $type,
    static fn(string $type): string => $normalizeType($type) === 'PAYROLL_WITHHOLDING' ? '일용직(신고)' : $type,
    static fn(string $table): bool => true,
    static fn(string $formatId): array => [],
    static fn(array $payload): array => $payload,
    static fn(array $payload): array => $payload,
    static function (array $row, array &$payload): void {},
    static fn(string $value): bool => preg_match('/^[0-9a-f-]{36}$/i', $value) === 1,
    static fn(string $type, string $id): ?string => null,
    static function (array &$row): void {
        $row['readiness_status'] = (string) ($row['process_status'] ?? 'READY');
    },
    static fn(array $row, string $key): int => (int) ($row[$key] ?? 0),
    static fn(string $message, array $row = [], int $rowNo = 0): string => $message
);

$request = [
    'import_type' => 'PAYROLL_WITHHOLDING',
    'draw' => 1,
    'start' => 0,
    'length' => 20,
    'search' => ['value' => '정순옥'],
    'columns' => [
        ['data' => 'transaction_date', 'name' => 'transaction_date'],
    ],
    'order' => [
        ['column' => 0, 'dir' => 'desc'],
    ],
];

$payload = $service->seedRows($request)['payload'] ?? [];
$rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$row = $rows[0] ?? [];
$columnMetaService = new DataTableColumnMetaService(DbPdo::conn());
$dailyMeta = $columnMetaService->columnsForDomain('evidence-daily-employment-income');
$payrollMeta = $columnMetaService->columnsForDomain('evidence-payroll-report');
$dailyMetaKeys = array_column($dailyMeta, 'key');
$payrollMetaKeys = array_column($payrollMeta, 'key');
$tableSource = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/ledger/evidence-list/table.js') ?: '';
$modalSource = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/ledger/evidence-list/modal.js') ?: '';
$policySource = file_get_contents(PROJECT_ROOT . '/app/Services/Ledger/EvidenceTypePolicyService.php') ?: '';
$payrollReadModel = new PayrollEvidenceReadModel(
    DbPdo::conn(),
    new EvidenceSchemaModel(DbPdo::conn()),
    new EvidenceBodyStatusProjectionModel()
);
$payrollRows = $payrollReadModel->findList('PAYROLL');
$payrollRow = $payrollRows[0] ?? [];

$checks = [
    'list_count_matches_counter' => (int) ($payload['recordsFiltered'] ?? 0) === 1 && count($rows) === 1,
    'worker' => (string) ($row['worker_name'] ?? '') === '정순옥',
    'income_year_month' => (string) ($row['income_year_month'] ?? '') === '2013-08',
    'transaction_date_from_workday' => preg_match('/^2013-08-\d{2}$/', (string) ($row['transaction_date'] ?? '')) === 1,
    'workdays' => (int) ($row['total_work_days'] ?? 0) === 5,
    'work_minutes' => (int) ($row['total_work_minutes'] ?? 0) === 2400,
    'gross_amount' => (int) ($row['total_gross_amount'] ?? 0) === 452940,
    'deduction_amount' => (int) ($row['total_deduction_amount'] ?? 0) === 2940,
    'net_amount' => (int) ($row['total_net_payment_amount'] ?? 0) === 450000,
    'employer_burden' => (int) ($row['total_employer_burden_amount'] ?? 0) === 20820,
    'completed_original_visible' => (string) ($row['evidence_status'] ?? '') === 'COMPLETED',
    'transaction_linked' => (string) ($row['transaction_link_status'] ?? '') === 'LINKED',
    'snapshot_hidden' => !array_key_exists('snapshot_json', $row),
    'daily_table_settings_uses_physical_meta' => $dailyMeta !== []
        && count(array_filter($dailyMeta, static fn(array $meta): bool => ($meta['table'] ?? '') !== 'ledger_evidence_daily_employment_income')) === 0,
    'payroll_table_settings_uses_physical_meta' => $payrollMeta !== []
        && count(array_filter($payrollMeta, static fn(array $meta): bool => ($meta['table'] ?? '') !== 'ledger_evidence_salary_report')) === 0,
    'daily_required_physical_columns' => count(array_diff([
        'source_daily_employment_income_id',
        'daily_employment_income_item_id',
        'approval_request_id',
        'worker_client_id',
        'income_year_month',
        'total_gross_amount',
        'total_deduction_amount',
        'total_net_payment_amount',
        'total_employer_burden_amount',
        'evidence_status_code',
    ], $dailyMetaKeys)) === 0,
    'payroll_required_physical_columns' => count(array_diff([
        'source_regular_employment_income_id',
        'approval_request_id',
        'regular_employment_income_item_id',
        'employee_id',
        'raw_income_year_month',
        'raw_gross_amount',
        'raw_deduction_amount',
        'raw_net_payment_amount',
        'raw_employer_burden_amount',
        'evidence_status',
    ], $payrollMetaKeys)) === 0,
    'payroll_runtime_uses_salary_report_body' => $payrollRows !== []
        && isset($payrollRow['source_regular_employment_income_id'])
        && isset($payrollRow['regular_employment_income_item_id'])
        && isset($payrollRow['raw_gross_amount'])
        && isset($payrollRow['raw_net_payment_amount']),
    'daily_non_physical_columns_removed' => !str_contains($tableSource, 'function dailyEmploymentIncomeColumns')
        && !str_contains($tableSource, "textColumn('document_title'")
        && !str_contains($tableSource, "textColumn('approval_request_name'"),
    'daily_custom_modal_removed' => !str_contains($modalSource, 'function renderDailyEmploymentIncomeModalFields')
        && !str_contains($modalSource, "isModalPreset(row, 'daily_employment_income')"),
    'daily_uses_default_modal_contract' => preg_match(
        "/'DAILY_EMPLOYMENT_INCOME'\\s*=>\\s*\\[.*?'modal_preset'\\s*=>\\s*'default'/s",
        $policySource
    ) === 1,
];

foreach (['정순옥', '2013-08', '본사', 'COMPLETED'] as $keyword) {
    $searchRequest = $request;
    $searchRequest['search']['value'] = $keyword;
    $searchPayload = $service->seedRows($searchRequest)['payload'] ?? [];
    $checks['search_' . $keyword] = (int) ($searchPayload['recordsFiltered'] ?? 0) === 1
        && count(is_array($searchPayload['data'] ?? null) ? $searchPayload['data'] : []) === 1;
}

$emptyRequest = $request;
$emptyRequest['search']['value'] = '존재하지 않는 증빙';
$emptyPayload = $service->seedRows($emptyRequest)['payload'] ?? [];
$checks['search_no_match'] = (int) ($emptyPayload['recordsFiltered'] ?? -1) === 0
    && count(is_array($emptyPayload['data'] ?? null) ? $emptyPayload['data'] : []) === 0;

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode([
    'success' => $failed === [],
    'checks' => $checks,
    'failed' => $failed,
    'records_total' => (int) ($payload['recordsTotal'] ?? 0),
    'records_filtered' => (int) ($payload['recordsFiltered'] ?? 0),
    'evidence_id' => (string) ($row['id'] ?? ''),
    'daily_physical_column_count' => count($dailyMeta),
    'payroll_physical_column_count' => count($payrollMeta),
    'payroll_row_count' => count($payrollRows),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

exit($failed === [] ? 0 : 1);
