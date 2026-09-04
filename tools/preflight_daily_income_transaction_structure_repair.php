<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

use Core\DbPdo;

const TARGET_TRANSACTION_ID = '2d315f38-bfa7-4ca6-8d6d-fb9bbaa50b7c';
const TARGET_EVIDENCE_ID = '0f07686f-fb23-4939-8a67-6b7860f192f3';
const TARGET_EVIDENCE_LINK_ID = '9f7d8c47-585b-4a02-b07d-1e2f363fe686';

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$one = static function (string $sql, array $params = []) use ($db): ?array {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
};
$rows = static function (string $sql, array $params = []) use ($db): array {
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};
$canonical = static function (mixed $value) use (&$canonical): mixed {
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map($canonical, $value);
    }
    ksort($value);
    foreach ($value as $key => $item) {
        $value[$key] = $canonical($item);
    }
    return $value;
};
$hash = static function (mixed $value) use ($canonical): string {
    return hash('sha256', json_encode($canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};

$transaction = $one('SELECT * FROM ledger_transactions WHERE id=:id', [':id' => TARGET_TRANSACTION_ID]);
$items = $rows('SELECT * FROM ledger_transaction_items WHERE transaction_id=:id ORDER BY sort_no,id', [':id' => TARGET_TRANSACTION_ID]);
$settlements = $rows('SELECT * FROM ledger_transaction_settlements WHERE transaction_id=:id ORDER BY sort_no,id', [':id' => TARGET_TRANSACTION_ID]);
$evidenceLink = $one('SELECT * FROM ledger_evidence_links WHERE id=:id AND target_type=\'TRANSACTION\' AND target_id=:transaction_id AND deleted_at IS NULL', [
        ':id' => TARGET_EVIDENCE_LINK_ID,
        ':transaction_id' => TARGET_TRANSACTION_ID,
    ]);
    $evidence = $one('SELECT * FROM ledger_evidence_daily_employment_income WHERE id=:id', [':id' => TARGET_EVIDENCE_ID]);
    $registry = $one("SELECT * FROM institution_daily_employment_income_accounting_links WHERE transaction_id=:id AND artifact_role='WORKER_PAYMENT'", [':id' => TARGET_TRANSACTION_ID]);
    $closure = $registry ? $one('SELECT * FROM institution_daily_employment_income_closures WHERE id=:id', [':id' => $registry['closure_id']]) : null;
    $revision = $evidence ? $one('SELECT * FROM institution_daily_employment_income_calculation_revisions WHERE id=:id', [':id' => $evidence['calculation_revision_id']]) : null;
    $snapshot = $evidence ? json_decode((string) $evidence['snapshot_json'], true, 512, JSON_THROW_ON_ERROR) : null;

    $snapshotLines = is_array($snapshot['lines'] ?? null) ? $snapshot['lines'] : [];
    $employmentInsuranceLines = array_values(array_filter($snapshotLines, static fn(array $line): bool =>
        strtoupper((string) ($line['line_type_code'] ?? '')) === 'DEDUCTION'
        && strtoupper((string) ($line['line_code'] ?? '')) === 'EMPLOYMENT_INSURANCE'
        && strtoupper((string) ($line['application_status_code'] ?? '')) === 'APPLICABLE'
    ));
    $otherEmployeeDeductions = array_values(array_filter($snapshotLines, static fn(array $line): bool =>
        strtoupper((string) ($line['line_type_code'] ?? '')) === 'DEDUCTION'
        && strtoupper((string) ($line['line_code'] ?? '')) !== 'EMPLOYMENT_INSURANCE'
        && round((float) ($line['final_amount'] ?? 0), 2) !== 0.0
    ));

    $settlementCodes = $rows("SELECT id,code,code_name,is_active,sort_no FROM system_codes WHERE code_group='SETTLEMENT_TYPE' ORDER BY sort_no,id");
    $employmentInsuranceSettlementCodes = array_values(array_filter(
        $settlementCodes,
        static fn(array $code): bool => (string) ($code['code'] ?? '') === 'EMPLOYMENT_INSURANCE'
    ));
    $operationCode = $one("SELECT id,code,code_name,is_active FROM system_codes WHERE code_group='OPERATION_TYPE' AND code='DAILY_WORKER' LIMIT 1");

    $transactionReferenceTables = $rows(
        "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() "
        . "AND COLUMN_NAME IN('transaction_id','source_transaction_id','target_transaction_id','ledger_transaction_id') "
        . "AND TABLE_NAME NOT IN('ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_links','institution_daily_employment_income_accounting_links') "
        . "ORDER BY TABLE_NAME,COLUMN_NAME"
    );
    $downstream = [];
    foreach ($transactionReferenceTables as $reference) {
        $table = (string) $reference['TABLE_NAME'];
        $column = (string) $reference['COLUMN_NAME'];
        $count = $one("SELECT COUNT(*) row_count FROM `{$table}` WHERE `{$column}`=:id", [':id' => TARGET_TRANSACTION_ID]);
        if ((int) ($count['row_count'] ?? 0) > 0) {
            $downstream[$table . '.' . $column] = (int) $count['row_count'];
        }
    }

    $auditCandidates = $rows(
        "SELECT TABLE_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) columns_present "
        . "FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() "
        . "AND (TABLE_NAME LIKE '%transaction%audit%' OR TABLE_NAME LIKE '%repair%' OR TABLE_NAME LIKE '%correction%audit%') "
        . "GROUP BY TABLE_NAME ORDER BY TABLE_NAME"
    );

    $beforeHashPayload = ['transaction' => $transaction, 'items' => $items, 'settlements' => $settlements];
    $expectedSettlement = count($employmentInsuranceLines) === 1 ? [
        'settlement_type' => 'EMPLOYMENT_INSURANCE',
        'amount_sign' => 'MINUS',
        'amount' => round((float) ($employmentInsuranceLines[0]['final_amount'] ?? 0), 2),
        'currency' => 'KRW',
        'settlement_description' => (string) ($employmentInsuranceLines[0]['line_name_snapshot'] ?? '고용보험 근로자부담'),
        'source_line_id' => (string) ($employmentInsuranceLines[0]['id'] ?? ''),
        'source_document_id' => (string) ($snapshot['source_document_id'] ?? ''),
        'source_group_id' => (string) ($snapshot['source_group_id'] ?? ''),
        'source_item_id' => (string) ($snapshot['source_item_id'] ?? ''),
        'calculation_revision_id' => (string) ($snapshot['calculation_revision_id'] ?? ''),
        'source_hash' => (string) ($snapshot['source_hash'] ?? ''),
        'attribution_month' => (string) ($snapshot['income_year_month'] ?? ''),
    ] : null;
    $requestKey = hash('sha256', implode('|', [
        'DAILY_INCOME_TRANSACTION_STRUCTURE_CORRECTION',
        TARGET_TRANSACTION_ID,
        (string) ($revision['source_hash'] ?? ''),
        (string) ($expectedSettlement['source_line_id'] ?? ''),
    ]));

    $checks = [
        'database_is_sukhyang' => (string) $db->query('SELECT DATABASE()')->fetchColumn() === 'sukhyang',
        'transaction_id' => (string) ($transaction['id'] ?? '') === TARGET_TRANSACTION_ID,
        'transaction_item_grain' => count($items) === 1,
        'evidence_id' => (string) ($evidence['id'] ?? '') === TARGET_EVIDENCE_ID,
        'evidence_link_id' => (string) ($evidenceLink['id'] ?? '') === TARGET_EVIDENCE_LINK_ID,
        'registry_grain' => (string) ($registry['daily_employment_income_id'] ?? '') === (string) ($snapshot['source_document_id'] ?? '')
            && (string) ($registry['daily_employment_income_group_id'] ?? '') === (string) ($snapshot['source_group_id'] ?? '')
            && (string) ($registry['daily_employment_income_item_id'] ?? '') === (string) ($snapshot['source_item_id'] ?? '')
            && (string) ($registry['worker_client_id'] ?? '') === (string) ($snapshot['worker_client_id'] ?? ''),
        'approval_snapshot_link' => (string) ($evidence['approval_request_id'] ?? '') === (string) ($snapshot['approval_request_id'] ?? '')
            && (string) ($closure['approval_request_id'] ?? '') === (string) ($snapshot['approval_request_id'] ?? ''),
        'source_hash' => (string) ($evidence['source_hash'] ?? '') !== ''
            && hash_equals((string) ($evidence['source_hash'] ?? ''), (string) ($revision['source_hash'] ?? ''))
            && hash_equals((string) ($evidence['source_hash'] ?? ''), (string) ($snapshot['source_hash'] ?? '')),
        'operation_type_payroll' => (string) ($transaction['operation_type'] ?? '') === 'PAYROLL',
        'header_amounts' => round((float) ($transaction['transaction_supply_amount'] ?? 0), 2) === 450000.0
            && round((float) ($transaction['transaction_settlement_amount'] ?? 0), 2) === 0.0
            && round((float) ($transaction['transaction_final_amount'] ?? 0), 2) === 450000.0,
        'item_amount' => count($items) === 1 && round((float) ($items[0]['item_supply_amount'] ?? 0), 2) === 450000.0,
        'settlement_absent' => $settlements === [],
        'evidence_link_count' => (int) ($one("SELECT COUNT(*) row_count FROM ledger_evidence_links WHERE target_type='TRANSACTION' AND target_id=:id AND deleted_at IS NULL", [':id' => TARGET_TRANSACTION_ID])['row_count'] ?? 0) === 1,
        'approved_amounts' => round((float) ($snapshot['total_gross_amount'] ?? 0), 2) === 452940.0
            && round((float) ($snapshot['total_deduction_amount'] ?? 0), 2) === 2940.0
            && round((float) ($snapshot['total_net_payment_amount'] ?? 0), 2) === 450000.0
            && round((float) ($snapshot['total_employer_burden_amount'] ?? 0), 2) === 20820.0,
        'employment_insurance_line' => count($employmentInsuranceLines) === 1
            && round((float) ($employmentInsuranceLines[0]['final_amount'] ?? 0), 2) === 2940.0,
        'other_employee_deductions_zero' => $otherEmployeeDeductions === [],
        'settlement_code_active' => count($employmentInsuranceSettlementCodes) === 1
            && (int) $employmentInsuranceSettlementCodes[0]['is_active'] === 1,
        'daily_worker_code_active' => (int) ($operationCode['is_active'] ?? 0) === 1,
        'downstream_absent' => $downstream === [],
        'repair_audit_ssot_available' => $auditCandidates !== [],
    ];

    echo json_encode([
        'mode' => 'READ_ONLY_DRY_RUN',
        'database' => (string) $db->query('SELECT DATABASE()')->fetchColumn(),
        'checks' => $checks,
        'can_execute_business_repair' => !in_array(false, $checks, true),
        'blocked_by' => array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed)),
        'identity' => [
            'transaction_id' => TARGET_TRANSACTION_ID,
            'actual_transaction_item_ids' => array_column($items, 'id'),
            'evidence_id' => TARGET_EVIDENCE_ID,
            'evidence_link_id' => TARGET_EVIDENCE_LINK_ID,
            'approval_request_id' => $snapshot['approval_request_id'] ?? null,
            'daily_document_id' => $snapshot['source_document_id'] ?? null,
            'daily_group_id' => $snapshot['source_group_id'] ?? null,
            'daily_item_id' => $snapshot['source_item_id'] ?? null,
            'worker_client_id' => $snapshot['worker_client_id'] ?? null,
        ],
        'before' => [
            'operation_type' => $transaction['operation_type'] ?? null,
            'header_supply_amount' => $transaction['transaction_supply_amount'] ?? null,
            'header_settlement_amount' => $transaction['transaction_settlement_amount'] ?? null,
            'header_final_amount' => $transaction['transaction_final_amount'] ?? null,
            'item_supply_amount' => $items[0]['item_supply_amount'] ?? null,
            'settlement_count' => count($settlements),
            'transaction_hash' => $hash($beforeHashPayload),
        ],
        'approved_snapshot' => [
            'gross_amount' => $snapshot['total_gross_amount'] ?? null,
            'deduction_amount' => $snapshot['total_deduction_amount'] ?? null,
            'net_amount' => $snapshot['total_net_payment_amount'] ?? null,
            'employer_burden_amount' => $snapshot['total_employer_burden_amount'] ?? null,
            'source_hash' => $snapshot['source_hash'] ?? null,
            'snapshot_hash' => $hash($snapshot),
        ],
        'expected' => [
            'operation_type' => 'DAILY_WORKER',
            'header_supply_amount' => $snapshot['total_gross_amount'] ?? null,
            'header_settlement_amount' => isset($snapshot['total_deduction_amount']) ? -round((float) $snapshot['total_deduction_amount'], 2) : null,
            'header_final_amount' => $snapshot['total_net_payment_amount'] ?? null,
            'item_supply_amount' => $snapshot['total_gross_amount'] ?? null,
            'settlement' => $expectedSettlement,
        ],
        'code_ssot' => [
            'operation_type' => $operationCode,
            'settlement_type_expected' => $employmentInsuranceSettlementCodes,
            'settlement_type_all' => $settlementCodes,
        ],
        'downstream_links' => $downstream,
        'repair_audit_candidates' => $auditCandidates,
        'request_key' => $requestKey,
        'stale_preflight_code' => count($items) === 1 ? null : 'STALE_PREFLIGHT',
        'expected_operating_dml' => [
            'ledger_transactions_update' => 1,
            'ledger_transaction_items_update' => 1,
            'ledger_transaction_settlements_insert' => 1,
            'repair_audit_insert' => 1,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
