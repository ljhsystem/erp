<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$view = file_get_contents($root . '/app/views/ledger/journal/partials/journal_modal.php');
$actions = file_get_contents($root . '/public/assets/js/pages/ledger/voucher/actions.js');
$state = file_get_contents($root . '/public/assets/js/pages/ledger/voucher/state.js');

if (!is_string($view) || !is_string($actions) || !is_string($state)) {
    throw new RuntimeException('증빙추가 모달 계약 소스를 읽을 수 없습니다.');
}
if (!preg_match('/<div class="modal"\s+id="journalEvidenceSearchModal"/', $view)) {
    throw new RuntimeException('증빙추가 모달의 불필요한 페이드 전환이 제거되지 않았습니다.');
}
$ensurePosition = strpos($actions, 'await ctx.ensureEvidenceSelectionTable?.()');
$reloadPosition = strpos($actions, 'table.ajax.reload(() => resolve(), false)');
$showPosition = strpos($actions, 'evidenceModal.show()', $ensurePosition === false ? 0 : $ensurePosition);
if ($ensurePosition === false || $reloadPosition === false || $showPosition === false
    || !($ensurePosition < $reloadPosition && $reloadPosition < $showPosition)) {
    throw new RuntimeException('증빙 목록 준비 완료 전에 모달이 표시됩니다.');
}
if (!str_contains($state, 'evidenceSearchOpening: false')) {
    throw new RuntimeException('증빙추가 모달 중복 열기 Guard가 없습니다.');
}
if (!str_contains($actions, "selectEvidenceBtn.setAttribute('aria-busy', 'true')")
    || !str_contains($actions, "selectEvidenceBtn.removeAttribute('aria-busy')")) {
    throw new RuntimeException('증빙추가 버튼의 준비 상태 접근성 계약이 없습니다.');
}

echo "voucher evidence modal opening contract: OK\n";
