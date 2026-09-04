<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$events = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/events.js');
$modal = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/modal.js');
$index = (string) file_get_contents($root . '/public/assets/js/pages/ledger/transaction/index.js');

$shownStart = strpos($events, "ctx.modalEl.addEventListener('shown.bs.modal'");
$shownEnd = $shownStart === false ? false : strpos($events, "ctx.modalEl.addEventListener('esc:modal-before-close'", $shownStart);
$shownBlock = $shownStart === false || $shownEnd === false ? '' : substr($events, $shownStart, $shownEnd - $shownStart);

$checks = [
    'shown_event_does_not_focus_grid' => !str_contains($shownBlock, 'focusInitialLineGridCell'),
    'modal_restores_top_viewport' => str_contains($modal, 'function resetTransactionModalViewport()')
        && str_contains($modal, 'ctx.modalBodyEl.scrollTop = 0;'),
    'create_and_detail_restore_viewport' => substr_count($modal, 'resetTransactionModalViewport();') === 2,
    'runtime_cache_busted' => str_contains($index, "import('./modal.js?v=20260826-transaction-modal-viewport-1')")
        && str_contains($index, "import('./events.js?v=20260826-transaction-modal-viewport-1')"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: 거래입력 모달은 업무분류정보 카드 상단에서 열립니다.\n");
