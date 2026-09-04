<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\Ledger\EvidenceWorkflowPolicyService;

$policy = new EvidenceWorkflowPolicyService();
$checks = [
    'correction_source_trace' => $policy->canLink('CORRECTION_REQUIRED', 'SOURCE_TRACE'),
    'correction_not_accounting_ready' => !$policy->canLink('CORRECTION_REQUIRED', 'ACCOUNTING_READY'),
    'completed_source_trace' => $policy->canLink('COMPLETED', 'SOURCE_TRACE'),
    'completed_accounting_ready' => $policy->canLink('COMPLETED', 'ACCOUNTING_READY'),
    'voucher_gate' => !$policy->canBecomeVoucherCandidate('CORRECTION_REQUIRED') && $policy->canBecomeVoucherCandidate('COMPLETED'),
    'correction_complete_transition' => $policy->transitionAllowed('CORRECTION_REQUIRED', 'COMPLETED'),
];
$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
