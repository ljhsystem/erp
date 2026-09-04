<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$requestId = 'e7f37bc9-82d7-4113-bb64-c5b01cf9e0f1';
$documentId = '4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6';
$fetchAll = static function (string $sql, array $params = []) use ($pdo): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};
$fetchOne = static function (string $sql, array $params = []) use ($fetchAll): ?array {
    return $fetchAll($sql, $params)[0] ?? null;
};
$scalar = static function (string $sql, array $params = []) use ($pdo): mixed {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
};
$request = $fetchOne('SELECT * FROM user_approval_requests WHERE id=:id', [':id' => $requestId]);
$steps = $fetchAll('SELECT id,sort_no,step_type,status,approver_id,acted_by,action_at,comment FROM user_approval_request_steps WHERE request_id=:id ORDER BY sort_no,id', [':id' => $requestId]);
$document = $fetchOne('SELECT * FROM institution_regular_employment_incomes WHERE id=:id', [':id' => $documentId]);
$evidences = $fetchAll('SELECT * FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:id ORDER BY created_at,id', [':id' => $documentId]);
$registries = $fetchAll('SELECT * FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=:id ORDER BY generation_role,aggregation_key,id', [':id' => $documentId]);
$evidenceId = (string) ($evidences[0]['id'] ?? '');
$transactionIds = array_values(array_filter(array_map(static fn(array $row): string => (string) ($row['transaction_id'] ?? ''), $registries)));
$transactions = [];
if ($transactionIds !== []) {
    $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
    $transactions = $fetchAll("SELECT * FROM ledger_transactions WHERE id IN ({$placeholders}) ORDER BY id", $transactionIds);
}
$links = $evidenceId === '' ? [] : $fetchAll("SELECT * FROM ledger_evidence_links WHERE evidence_type='PAYROLL_REPORT' AND evidence_id=:id AND deleted_at IS NULL ORDER BY target_type,target_id,id", [':id' => $evidenceId]);
$audits = $fetchAll("SELECT * FROM institution_regular_employment_income_audits WHERE regular_employment_income_id=:id AND action_code='ACCOUNTING_MATERIALIZE' ORDER BY id", [':id' => $documentId]);
$roles = array_count_values(array_map(static fn(array $row): string => (string) $row['generation_role'], $registries));
$transactionById = [];
foreach ($transactions as $transaction) $transactionById[(string) $transaction['id']] = $transaction;
$institutionAmounts = [];
foreach ($registries as $registry) {
    if (($registry['generation_role'] ?? '') !== 'INSTITUTION_LIABILITY') continue;
    $parts = explode('|', (string) $registry['aggregation_key']);
    $code = $parts[1] ?? (string) $registry['aggregation_key'];
    $transaction = $transactionById[(string) $registry['transaction_id']] ?? [];
    $institutionAmounts[$code] = round((float) ($transaction['transaction_final_amount'] ?? 0), 2);
}
$registryRequestKeyDuplicates = (int) $scalar('SELECT COUNT(*) FROM (SELECT request_key FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=:id GROUP BY request_key HAVING COUNT(*)>1) duplicate_rows', [':id' => $documentId]);
$registryRoleDuplicates = (int) $scalar('SELECT COUNT(*) FROM (SELECT generation_role,aggregation_key FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=:id GROUP BY generation_role,aggregation_key HAVING COUNT(*)>1) duplicate_rows', [':id' => $documentId]);
$linkDuplicates = $evidenceId === '' ? 0 : (int) $scalar("SELECT COUNT(*) FROM (SELECT evidence_type,evidence_id,target_type,target_id,link_type FROM ledger_evidence_links WHERE evidence_type='PAYROLL_REPORT' AND evidence_id=:id AND deleted_at IS NULL GROUP BY evidence_type,evidence_id,target_type,target_id,link_type HAVING COUNT(*)>1) duplicate_rows", [':id' => $evidenceId]);
$result = [
    'request' => $request,
    'steps' => $steps,
    'document' => $document,
    'evidence_ids' => array_column($evidences, 'id'),
    'evidence_count' => count($evidences),
    'evidence_statuses' => array_values(array_unique(array_column($evidences, 'evidence_status'))),
    'employee_transaction_count' => $roles['EMPLOYEE_PAYROLL'] ?? 0,
    'institution_transaction_count' => $roles['INSTITUTION_LIABILITY'] ?? 0,
    'transaction_ids' => $transactionIds,
    'evidence_links' => $links,
    'evidence_link_count' => count($links),
    'registry_ids' => array_column($registries, 'id'),
    'registry_count' => count($registries),
    'closure_audit_ids' => array_column($audits, 'id'),
    'closure_audit_count' => count($audits),
    'institution_amounts' => $institutionAmounts,
    'institution_total' => round(array_sum($institutionAmounts), 2),
    'duplicates' => ['registry_request_key' => $registryRequestKeyDuplicates, 'registry_role_aggregation' => $registryRoleDuplicates, 'evidence_link' => $linkDuplicates, 'transaction_id' => count($transactionIds) - count(array_unique($transactionIds))],
    'forbidden_counts' => [
        'payment_schedules' => (int) $scalar('SELECT COUNT(*) FROM ledger_payment_schedules'),
        'payment_schedule_histories' => (int) $scalar('SELECT COUNT(*) FROM ledger_payment_schedule_histories'),
        'voucher_headers' => (int) $scalar('SELECT COUNT(*) FROM ledger_vouchers'),
        'voucher_lines' => (int) $scalar('SELECT COUNT(*) FROM ledger_voucher_lines'),
    ],
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
