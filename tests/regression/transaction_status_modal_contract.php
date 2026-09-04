<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Controllers/Ledger/TransactionController.php');
$service = file_get_contents($root . '/app/Services/Ledger/TransactionCrudService.php');
$editors = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/editors.js');
$modal = file_get_contents($root . '/app/views/ledger/transaction/partials/transaction_modal.php');

$checks = [
    'status_in_business_card_header' => str_contains($modal, 'transaction-status-confirmation')
        && strpos($modal, 'transaction-status-confirmation') < strpos($modal, 'transaction-business-grid'),
    'draft_completed_are_selectable' => str_contains($modal, 'value="draft" checked')
        && str_contains($modal, 'value="completed"'),
    'terminal_states_are_readonly' => str_contains($modal, 'value="closed" data-status-terminal disabled')
        && str_contains($modal, 'value="cancelled" data-status-terminal disabled'),
    'radio_updates_stored_status' => str_contains($editors, 'transaction_status_choice')
        && str_contains($editors, 'statusInput.value = radio.value'),
    'system_info_excludes_status' => preg_match("/'memo',\\s*'status',/", $editors) === 1,
    'controller_accepts_user_states_only' => str_contains($controller, "['draft', 'completed']")
        && !str_contains($controller, "\$payload['status'] = 'draft';"),
    'completed_transaction_is_editable' => str_contains($service, "['draft', 'completed']")
        && str_contains($service, '마감되거나 취소된 거래는 수정할 수 없습니다.'),
];

$failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
