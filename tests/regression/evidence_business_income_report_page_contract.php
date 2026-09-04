<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$policy = $read('app/Services/Ledger/EvidenceTypePolicyService.php');
$storage = $read('app/Models/Ledger/EvidenceBodyStorageModel.php');
$bodyRead = $read('app/Services/Ledger/EvidenceBodyReadService.php');
$generation = $read('app/Services/Ledger/EvidenceGenerationService.php');
$businessRead = $read('app/Models/Ledger/BusinessDataEvidenceReadModel.php');
$table = $read('public/assets/js/pages/ledger/evidence-list/table.js');
$modal = $read('public/assets/js/pages/ledger/evidence-list/modal.js');
$save = $read('app/Controllers/Ledger/EvidenceSaveController.php');
$lifecycle = $read('app/Controllers/Ledger/EvidenceLifecycleController.php');
$status = $read('app/Controllers/Ledger/EvidenceStatusController.php');
$upload = $read('app/Controllers/Ledger/EvidenceUploadController.php');

$checks = [
    'physical_meta_domain' => str_contains($policy, "'meta_domain' => 'evidence-business-income-report'"),
    'immutable_policy' => str_contains($policy, "'BUSINESS_INCOME_REPORT' => [")
        && str_contains($policy, "'read_only' => true")
        && str_contains($policy, "'transaction_workflow_required' => false")
        && str_contains($policy, "'excel_manager_mode' => 'none'"),
    'detail_preset' => str_contains($policy, "'modal_preset' => 'business_income_report'"),
    'canonical_storage' => str_contains($storage, "'BUSINESS_INCOME_REPORT' => 'ledger_evidence_business_income'"),
    'body_reader_ssot' => str_contains($bodyRead, "'BUSINESS_INCOME_REPORT'")
        && str_contains($generation, "'BUSINESS_INCOME_REPORT'"),
    'detail_children' => str_contains($businessRead, 'ledger_evidence_business_income_work_lines')
        && str_contains($businessRead, 'ledger_evidence_business_income_raw_lines')
        && str_contains($modal, '외주 작업내역 원본')
        && str_contains($modal, '원천징수 계산 원천자료'),
    'read_only_buttons' => str_contains($table, "? 'd-none'")
        && str_contains($table, "readOnly ? 'd-none'"),
    'server_guards' => str_contains($save, 'rejectReadOnlyEvidenceMutation')
        && str_contains($lifecycle, 'rejectReadOnlyEvidenceMutation')
        && str_contains($status, 'rejectReadOnlyEvidenceMutation')
        && str_contains($upload, 'isReadOnlyStatusViewType'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
