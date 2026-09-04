<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$js = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/evidence-selection-table.js');
$view = file_get_contents($root . '/app/views/ledger/transaction/partials/transaction_modal.php');
$css = file_get_contents($root . '/public/assets/css/pages/ledger/transaction/modal.css');
$meta = file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');
$projection = file_get_contents($root . '/app/Services/Ledger/TransactionEvidenceReferenceService.php');
$modal = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/modal.js');

$assertions = [
    '테이블 설정 활성화' => str_contains($js, "metaDomain: EVIDENCE_SELECTION_META_DOMAIN")
        && !str_contains($js, 'tableSettings: { enabled: false }'),
    '거래입력 전용 설정 저장키' => str_contains($js, 'datatable.settings.ledger.transaction.evidence-selection.v1'),
    '공용 복합 메타 도메인' => str_contains($meta, "'transaction-evidence-selection' => ['composite' => 'voucher-evidence-selection']"),
    '선택 및 관리 시스템컬럼' => str_contains($js, "'__select'")
        && str_contains($js, "settingsKey: '__actions'"),
    '전표입력과 동일한 참조 표시컬럼' => str_contains($js, "settingsKey: 'employee_name'")
        && str_contains($js, "settingsKey: 'project_name'")
        && str_contains($js, "settingsKey: 'bank_account_name'")
        && str_contains($js, "settingsKey: 'card_name'")
        && str_contains($js, "settingsKey: 'team_name'"),
    'API 표시 Projection' => str_contains($projection, "'employee_name' =>")
        && str_contains($projection, "'project_name' =>")
        && str_contains($projection, "'bank_account_name' =>")
        && str_contains($projection, "'created_at' =>"),
    '전체 컬럼 Header 구조' => str_contains($view, '<th>증빙 ID</th>')
        && str_contains($view, '<th>직원</th>')
        && str_contains($view, '<th>수정일시</th>')
        && !str_contains($view, 'id="transaction_evidence_search_table" class="table table-sm align-middle nowrap w-100"'),
    '전표입력형 너비 및 고정 Header' => str_contains($js, "widthScopeSelector: '.transaction-evidence-table-wrap'")
        && str_contains($js, 'fitColumnsToScope: true')
        && str_contains($css, 'top: var(--transaction-evidence-toolbar-height, 42px)')
        && str_contains($js, 'new ResizeObserver(syncStickyOffset)'),
    '첫 조회 완료 후 모달 표시' => preg_match(
        '/const table = await ctx\.ensureTransactionEvidenceSelectionTable\?\.\(\);.*table\.ajax\.reload\(\(\) => resolve\(\), false\);.*bootstrap\.Modal\.getOrCreateInstance\(ctx\.evidenceSearchModalEl\)\.show\(\);/s',
        $modal
    ) === 1,
    '중복 열기 차단 및 버튼 대기상태' => str_contains($modal, 'if (evidenceSearchOpening) return;')
        && str_contains($modal, "setAttribute('aria-busy', 'true')")
        && str_contains($modal, "removeAttribute('aria-busy')"),
    '중첩 테이블설정 모달 레이어' => str_contains($css, 'body:has(#transactionEvidenceSearchModal.show) #dtColumnSettingsModal.show')
        && str_contains($css, 'z-index: 30060 !important;')
        && str_contains($css, 'body:has(#transactionEvidenceSearchModal.show):has(#dtColumnSettingsModal.show) .modal-backdrop.show:last-of-type')
        && str_contains($css, 'z-index: 30055 !important;'),
    '증빙추가 단독 backdrop 상위노출 방지' => !str_contains(
        $css,
        'body:has(#transactionEvidenceSearchModal.show) .modal-backdrop.show:last-of-type'
    ),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, 'FAIL: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'PASS: transaction evidence selection DataTable settings contract' . PHP_EOL;
