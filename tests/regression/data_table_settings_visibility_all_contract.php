<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = (string) file_get_contents($root . '/public/assets/js/common/datatable/dataTableColumnSettings.js');
$css = (string) file_get_contents($root . '/public/assets/css/components/data-table.css');

$checks = [
    'single_shared_modal' => str_contains($source, "const MODAL_ID = 'dtColumnSettingsModal'")
        && str_contains($source, 'document.getElementById(MODAL_ID)'),
    'blank_visibility_header' => str_contains($source, "{ key: 'visible', label: '', width: 64"),
    'visibility_all_switch' => str_contains($source, 'data-dt-settings-visible-all')
        && str_contains($source, 'role="switch"'),
    'tri_state_sync' => str_contains($source, 'syncVisibilityAllToggle')
        && str_contains($source, 'toggle.indeterminate'),
    'hideable_guard' => str_contains($source, 'entry.hideable === false')
        && str_contains($source, 'entry.hideable !== false'),
    'global_width_and_sort_sync' => str_contains($source, 'columnWidths,')
        && str_contains($source, 'sortSettings: visibilityAll.checked ? state.viewSettings.sortSettings : []'),
    'header_body_toggle_same_size' => str_contains($css, '[data-dt-settings-visible-all],')
        && str_contains($css, '[data-dt-settings-visible]')
        && str_contains($css, '--dt-settings-visibility-toggle-width: 32px')
        && str_contains($css, '--dt-settings-visibility-toggle-height: 16px'),
    'mixed_state_visual' => str_contains($source, "partiallyVisible ? ' is-mixed' : ''")
        && str_contains($source, "toggle.classList.toggle('is-mixed', toggle.indeterminate)")
        && str_contains($css, '[data-dt-settings-visible-all].is-mixed')
        && str_contains($css, 'radial-gradient(circle at center'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
