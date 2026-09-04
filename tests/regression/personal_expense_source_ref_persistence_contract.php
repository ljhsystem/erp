<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'source_service' => $root . '/app/Services/Ledger/VoucherLineSourceRefService.php',
    'voucher_service' => $root . '/app/Services/Ledger/VoucherService.php',
    'query_service' => $root . '/app/Services/Ledger/VoucherQueryService.php',
    'grid_bridge' => $root . '/public/assets/js/pages/ledger/voucher/voucher-grid-bridge.js',
];
foreach ($files as $name => $path) {
    if (!is_file($path)) throw new RuntimeException("{$name} 파일을 찾을 수 없습니다.");
    $files[$name] = (string) file_get_contents($path);
}
$contracts = [
    'save' => str_contains($files['voucher_service'], 'persistVoucherLines($companyId, $voucherId, $savedLines)'),
    'allocation_validation' => str_contains($files['voucher_service'], 'validateVoucher($companyId, $voucherId, $savedLines)'),
    'reload' => str_contains($files['query_service'], 'hydrateVoucherLines(') && str_contains($files['query_service'], 'voucherLineSourceRefService'),
    'reversal' => str_contains($files['voucher_service'], 'createReversals(') && str_contains($files['source_service'], "'reference_action_code' => 'REVERSAL'")
        && str_contains($files['source_service'], "'original_source_ref_id' => (string) \$original['id']"),
    'client_round_trip' => substr_count($files['grid_bridge'], 'source_refs') >= 2,
    'server_evidence_validation' => str_contains($files['source_service'], 'evidenceRepository->find('),
    'rule_revision_validation' => str_contains($files['source_service'], 'ruleRevisionExists('),
    'revision_fk_column' => str_contains((string) file_get_contents($root . '/app/Models/Ledger/VoucherLineSourceRefModel.php'), 'rv.rule_id=r.id'),
];
$failed = array_keys(array_filter($contracts, static fn(bool $passed): bool => !$passed));
if ($failed !== []) throw new RuntimeException('Source Ref 영속 계약 누락: ' . implode(', ', $failed));

echo "personal expense source ref persistence contract: OK\n";
