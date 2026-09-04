<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$table = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/table.js');
$model = file_get_contents($root . '/app/Models/Ledger/TransactionModel.php');

$checks = [
    'line_status_column_removed' => !str_contains($table, 'transaction_line_status'),
    'line_status_projection_removed' => !str_contains($model, 'transaction_line_status')
        && !str_contains($model, 'hydrateLineStatuses')
        && !str_contains($model, 'lineStatusJoinSql'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
