<?php

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . DIRECTORY_SEPARATOR . $path);

$save = $read('app/Services/Ledger/EvidenceGenerationSaveService.php');
$batch = $read('app/Services/Ledger/EvidenceBatchSaveService.php');
$modal = $read('public/assets/js/pages/ledger/evidence-list/modal.js');
$settings = $read('public/assets/js/common/datatable/dataTableSettings.js');
$bank = $read('app/Models/Funds/BankTransactionReportModel.php');
$transaction = $read('app/Services/Ledger/TransactionCrudService.php');
$voucher = $read('app/Services/Ledger/VoucherService.php');
$helper = $read('app/Services/Ledger/EvidenceStatusHelperService.php');
$workflowPolicy = $read('app/Services/Ledger/EvidenceWorkflowPolicyService.php');

$checks = [
    'explicit_status_only' => str_contains($save, "['COMPLETED', 'CORRECTION_REQUIRED']")
        && str_contains($save, 'requestedEvidenceStatus'),
    'new_and_excel_default_correction' => str_contains($save, "requestedEvidenceStatus(\$parsed, 'CORRECTION_REQUIRED')")
        && str_contains($batch, ": 'CORRECTION_REQUIRED'"),
    'no_automatic_status_helper' => !str_contains($helper, 'evidenceStatusFrom')
        && !str_contains($helper, 'evidenceStatusForPayload'),
    'status_not_required_policy' => str_contains($settings, "normalizedKey === 'evidence_status'")
        && str_contains($settings, 'COLUMN_REQUIREMENT_POLICY.NONE'),
    'modal_switch_and_payload' => str_contains($modal, 'evidence-status-confirmation-switch')
        && str_contains($modal, "statusSwitch?.checked ? 'COMPLETED' : 'CORRECTION_REQUIRED'"),
    'linked_update_blocked' => str_contains($save, 'hasActiveLink')
        && str_contains($save, '활성 거래 또는 전표에 연결된 증빙'),
    'bank_lifecycle_does_not_write_status' => !preg_match("/evidence_status\\s*=\\s*'(?:ACTIVE|DELETED|INVALID)'/", $bank),
    'transaction_purpose_and_voucher_gate' => str_contains($transaction, 'canLink')
        && str_contains($workflowPolicy, 'LINK_SOURCE_TRACE')
        && str_contains($workflowPolicy, 'LINK_ACCOUNTING_READY')
        && str_contains($voucher, "!== 'COMPLETED'"),
    'legacy_status_not_written' => !preg_match("/evidence_status['\"]?\\s*(?:=>|=)\\s*['\"](?:READY|VERIFY_ONLY|NOT_READY|REVIEW_REQUIRED|ACTIVE|DELETED|INVALID)['\"]/", $save . $batch . $bank),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
