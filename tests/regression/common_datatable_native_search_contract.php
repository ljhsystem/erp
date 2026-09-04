<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../../public/assets/js/common/table/data-table.js');
$evidenceService = file_get_contents(__DIR__ . '/../../app/Services/Ledger/EvidenceGenerationService.php');
$evidenceRepository = file_get_contents(__DIR__ . '/../../app/Repositories/Ledger/EvidenceSourceRepository.php');

$checks = [
    'server-side native search enabled' => str_contains(
        $source,
        'const resolvedSearching = serverSide === true ? true : searching !== false;'
    ) && str_contains($source, 'searching: resolvedSearching'),
    'DataTables request preserved' => str_contains($source, '...request,')
        && str_contains($source, '...result,')
        && str_contains($source, "? { ...(request.search || {}), ...result.search }")
        && str_contains($source, ': request.search,'),
    'cell search fill opt-in' => str_contains($source, 'cellSearchFill = false,'),
    'evidence native search forwarded' => str_contains(
        $evidenceService,
        "'keyword' => trim((string) (\$query['search']['value'] ?? \$query['search'] ?? ''))"
    ),
    'evidence repository keyword filter' => str_contains($evidenceRepository, "\$criteria['keyword'] ?? ''")
        && str_contains($evidenceRepository, "LIKE {\$key}"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(
    ['success' => $failed === [], 'checks' => $checks, 'failed' => $failed],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) . PHP_EOL;

exit($failed === [] ? 0 : 1);
