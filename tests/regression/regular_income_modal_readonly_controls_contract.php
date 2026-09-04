<?php

declare(strict_types=1);

$script = file_get_contents(__DIR__ . '/../../public/assets/js/pages/institution/regular-employment-income/index.js');

$checks = [
    'all_modal_close_buttons_remain_enabled' => str_contains($script, 'querySelectorAll(\'[data-bs-dismiss="modal"], [data-ui-modal-card-collapse]\')'),
    'system_info_toggle_remains_enabled' => str_contains($script, '[data-ui-modal-card-collapse]')
        && str_contains($script, 'control.disabled=false'),
    'editing_controls_still_follow_document_status' => str_contains($script, 'setReadonly(!EDITABLE.has(documentStatus))'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(
    ['success' => $failed === [], 'checks' => $checks, 'failed' => $failed],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
) . PHP_EOL;
exit($failed === [] ? 0 : 1);
