<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$js = file_get_contents($root . '/public/assets/js/common/table/data-table.js');
$css = file_get_contents($root . '/public/assets/css/components/data-table.css');
$dictionary = file_get_contents($root . '/docs/architecture/CommonDictionary.md');

$checks = [
    'header_uses_full_title_metadata' => str_contains($js, 'data-dt-full-title')
        && str_contains($js, 'resolvePlainColumnTitle'),
    'header_uses_stable_column_key' => str_contains($js, 'data-dt-column-key')
        && str_contains($js, 'resolveHeaderColumnIndex')
        && str_contains($js, 'resolveSettingsColumnKey(columns[index])'),
    'header_has_no_visible_index_array_fallback' => !str_contains($js, 'Number.isInteger(dataIndex) ? dataIndex : visibleIndex'),
    'virtual_selection_has_stable_metadata' => str_contains($js, "tooltipText: '\\uC120\\uD0DD'")
        && str_contains($js, "settingsTitle: '\\uC120\\uD0DD'"),
    'header_hover_is_not_truncation_gated' => str_contains($js, "cell.dataset.dtFullTitle")
        && !preg_match('/dataset\.dtFullTitle[^}]{0,300}isCellTextTruncated/s', $js),
    'header_uses_clip' => preg_match('/thead th[^}]*text-overflow:\s*clip/s', $css) === 1,
    'body_ellipsis_is_class_scoped' => str_contains($css, 'td.dt-text-truncate')
        && !preg_match('/table\.dataTable tbody td\s*\{[^}]*text-overflow:\s*ellipsis/s', $css),
    'body_tooltip_requires_real_overflow' => str_contains($js, 'cell.scrollWidth')
        && str_contains($js, 'cell.clientWidth + 1'),
    'empty_placeholder_has_no_tooltip' => str_contains($js, "text === '-'"),
    'complex_cells_are_excluded' => str_contains($js, "'.badge'")
        && str_contains($js, "'button'")
        && str_contains($js, "'[role=\"progressbar\"]'"),
    'draw_and_column_changes_resync' => str_contains($js, 'column-visibility.dt.dtTruncatedTooltip')
        && str_contains($js, 'column-reorder.dt.dtTruncatedTooltip')
        && str_contains($js, 'responsive-resize.dt.dtTruncatedTooltip'),
    'common_dictionary_updated' => str_contains($dictionary, 'Header는 안정적인 column key')
        && str_contains($dictionary, '복제 Header'),
];

$legacyFiles = [
    '/public/assets/js/pages/main/settings/base/bank-account/table.js',
    '/public/assets/js/pages/main/settings/base/card/table.js',
    '/public/assets/js/pages/ledger/transaction/evidence-selection-table.js',
    '/public/assets/js/pages/ledger/voucher/evidence-selection-table.js',
    '/public/assets/js/pages/ledger/voucher/table.js',
];
$checks['page_text_tooltip_legacy_removed'] = array_reduce(
    $legacyFiles,
    static fn (bool $carry, string $file): bool => $carry
        && !preg_match('/text-truncate[^>]*title=|note-text[^>]*title=|<span title="\$\{escapeHtml\(text\)\}/', file_get_contents($root . $file)),
    true
);

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
