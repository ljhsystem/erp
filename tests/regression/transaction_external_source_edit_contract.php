<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/Ledger/TransactionCrudService.php');
$calculation = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/calculation.js');
$modal = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/modal.js');
$index = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/index.js');
$view = file_get_contents($root . '/app/views/ledger/transaction/partials/transaction_modal.php');
$editors = file_get_contents($root . '/public/assets/js/pages/ledger/transaction/editors.js');

if ($service === false || $calculation === false || $modal === false || $index === false
    || $view === false || $editors === false) {
    fwrite(STDERR, "거래 수정 계약 파일을 읽을 수 없습니다.\n");
    exit(1);
}

$traceFields = [
    'regular_employment_income_line_item_id',
    'statutory_standard_revision_id',
    'calculation_basis_id',
];

$checks = [
    'active_grid_edit_committed_before_collect' => str_contains($modal, 'ctx.lineGrid?.stopEditing?.(false);')
        && str_contains($modal, 'ctx.settlementGrid?.stopEditing?.(false);')
        && strpos($modal, 'ctx.lineGrid?.stopEditing?.(false);') < strpos($modal, 'const lines = collectLines();'),
    'successful_save_closes_modal_and_refreshes_list' => strpos($modal, 'ctx.modal?.hide();') !== false
        && strpos($modal, 'ctx.modal?.hide();') < strpos($modal, 'ctx.reloadTable();'),
    'latest_modal_contract_is_cache_busted' => str_contains($index, "import('./modal.js?v=20260826-transaction-modal-viewport-1')"),
    'client_is_not_statically_required' => !preg_match('/id="client_id"[\\s\\S]{0,160}required/', $view),
    'modal_required_validation_uses_table_settings' => str_contains($editors, "inputEl.required = requirementPolicy === 'required';")
        && str_contains($editors, "inputEl.setAttribute('aria-required', requirementPolicy === 'required' ? 'true' : 'false');"),
    'latest_requirement_policy_contract_is_cache_busted' => str_contains(
        $index,
        "import('./editors.js?v=20260826-transaction-requirement-policy-1')"
    ),
    '기존 Evidence 연결 식별자를 조회한다' => str_contains($service, 'getTransactionEvidences($transactionId)'),
    '기존 연결은 누락된 목적을 신규 연결 기본값으로 바꾸지 않는다' => str_contains(
        $service,
        'if (isset($existingEvidenceIdentities[$identity]))'
    ),
    '신규 연결에만 ACCOUNTING_READY 기본값을 적용한다' => str_contains(
        $service,
        '$linkPurpose = EvidenceWorkflowPolicyService::LINK_ACCOUNTING_READY;'
    ),
    'Evidence 상태·목적 검증은 중복 반복하지 않는다' => substr_count(
        $service,
        'foreach ($linkedEvidences as $linkedEvidence)'
    ) === 1,
    '화면은 기존 연결 목적을 가능한 경우 왕복한다' => str_contains($modal, "link_purpose: evidence.link_purpose || ''"),
    '저장 검증 오류는 처리되지 않은 Promise로 남기지 않는다' => str_contains(
        $modal,
        "ctx.notify('error', error?.message || '수정 중 오류가 발생했습니다.');"
    ),
];

foreach ($traceFields as $field) {
    $checks["{$field} 계산행 보존"] = substr_count($calculation, $field) >= 2;
    $checks["{$field} 저장요청 보존"] = substr_count($modal, $field) >= 2;
}

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

fwrite(STDOUT, "PASS: 외부 생성 거래 수정 시 원본 연결과 계산 추적정보를 보존합니다.\n");
