<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/files.js');
$events = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/events.js');
$modal = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/modal.js');
$index = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/index.js');

$checks = [
    'single_flight_promise' => str_contains($files, 'ctx.modalControlsInitializationPromise'),
    'complete_flag_after_control_initialization' => strpos($files, 'ctx.modalControlsInitialized = true;')
        > strpos($files, 'await initCodeSelectControls(ctx.modalEl);'),
    'detail_waits_for_controls' => str_contains($modal, 'ctx.initModalControls(),'),
    'boot_has_no_picker_reinitialization' => !str_contains($events, 'ctx.initClientSelect?.();')
        && !str_contains($events, 'ctx.initProjectSelect?.();')
        && !str_contains($events, 'ctx.initBankAccountSelect?.();'),
    'runtime_cache_busted' => str_contains($index, "import('./files.js?v=20260826-modal-controls-single-flight-1')")
        && str_contains($index, "import('./events.js?v=20260826-transaction-modal-viewport-1')"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: 새로고침 직후 상세 모달이 Picker 초기화 완료 후 저장값을 주입합니다.\n");
