<?php

$root = dirname(__DIR__, 2);
$tool = (string) file_get_contents($root . '/tools/apply_evidence_status_baseline_transition.php');
$types = [
    'BANK_TRANSACTION', 'TAX_INVOICE', 'TAX_INVOICE_MANUAL', 'CASH_RECEIPT',
    'CARD_HOMETAX', 'CARD_STATEMENT', 'EMPLOYEE_EXPENSE_PERSONAL', 'PAYROLL_REPORT',
];

$checks = [
    'official_types' => array_reduce($types, static fn(bool $passed, string $type): bool => $passed && str_contains($tool, "'{$type}'"), true),
    'real_transaction_target' => str_contains($tool, 'INNER JOIN ledger_transactions')
        && str_contains($tool, 'transaction_row.deleted_at IS NULL'),
    'real_voucher_target' => str_contains($tool, 'INNER JOIN ledger_vouchers')
        && str_contains($tool, 'voucher.deleted_at IS NULL')
        && str_contains($tool, 'COALESCE(voucher.is_reversal, 0) = 0'),
    'link_lifecycle' => str_contains($tool, "link.deleted_at IS NULL"),
    'deleted_evidence_excluded' => str_contains($tool, '!$deleted && !$linked'),
    'only_correction_target' => str_contains($tool, "evidence_status='CORRECTION_REQUIRED'")
        && str_contains($tool, "evidence_status <> 'CORRECTION_REQUIRED'"),
    'system_actor' => str_contains($tool, "ActorHelper::system(EVIDENCE_STATUS_BASELINE_ACTOR_CONTEXT)"),
    'snapshot_digest' => str_contains($tool, 'evidenceStatusBaselineDigest')
        && str_contains($tool, 'Dry-run Snapshot과 현재 적용대상이 일치하지 않습니다.'),
    'transaction_and_invariants' => str_contains($tool, '$pdo->beginTransaction()')
        && str_contains($tool, 'evidenceStatusBaselineInvariants'),
    'idempotency_assertion' => str_contains($tool, "change_target_count'] !== 0"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
