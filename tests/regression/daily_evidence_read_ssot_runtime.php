<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Models\Ledger\DailyEmploymentIncomeEvidenceReadModel;
use App\Models\Ledger\EvidenceSchemaModel;
use Core\DbPdo;

$pdo = DbPdo::conn();
$model = new DailyEmploymentIncomeEvidenceReadModel($pdo, new EvidenceSchemaModel($pdo));
$rows = $model->findList();
$row = $rows[0] ?? [];
$required = [
    'import_type','worker_client_name','project_name','work_team_name','business_unit_name',
    'approval_request_name','calculation_revision_name','transaction_id',
    'gross_payment_amount','worker_deduction_amount','net_payment_amount','employer_burden_amount',
];
$checks = [
    'row_available' => $rows !== [],
    'canonical_type' => ($row['import_type'] ?? null) === 'DAILY_EMPLOYMENT_INCOME',
    'required_projection' => array_diff($required, array_keys($row)) === [],
    'count_matches' => $model->findCount() === count($rows),
    'hash_not_exposed' => !array_key_exists('source_hash', $row) && !array_key_exists('business_key_hash', $row),
    'snapshot_not_exposed' => !array_key_exists('snapshot_json', $row),
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
