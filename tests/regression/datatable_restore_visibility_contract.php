<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$settings = (string) file_get_contents($root . '/public/assets/js/common/datatable/dataTableSettings.js');
$modal = (string) file_get_contents($root . '/public/assets/js/common/datatable/dataTableColumnSettings.js');
$evidenceTable = (string) file_get_contents($root . '/public/assets/js/pages/ledger/evidence-list/table.js');
$meta = (string) file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');

$restoreOwners = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/public/assets/js', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    if (str_contains($source, 'data-dt-settings-restore')
        || str_contains($source, 'data-dt-view-settings-reset')) {
        $restoreOwners[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }
}

$checks = [
    'restore_provider_receives_current_entries' => str_contains(
        $modal,
        'state.restoreDefaults?.('
    ) && str_contains($modal, 'state.entries.map((entry) => ({ ...entry }))'),
    'column_restore_preserves_visibility' => substr_count(
        $settings,
        'visible: currentVisibility.has(entry.key)'
    ) === 2,
    'view_restore_does_not_change_visibility' => !preg_match(
        '/bind\(resetViewButton.*?visible\s*:/s',
        $modal
    ),
    'evidence_has_no_page_visibility_default' => !str_contains(
        $evidenceTable,
        'defaultVisibleColumns:'
    ),
    'daily_evidence_has_no_page_hidden_list' => !str_contains(
        $meta,
        "if (\$resolvedDomain === 'evidence-daily-employment-income')"
    ),
    'restore_buttons_have_single_common_owner' => $restoreOwners === [
        'public/assets/js/common/datatable/dataTableColumnSettings.js',
    ],
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode([
    'success' => $failed === [],
    'checks' => $checks,
    'failed' => $failed,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failed === [] ? 0 : 1);
