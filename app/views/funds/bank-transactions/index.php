<?php

use Core\Helpers\AssetHelper;

$pageTitle = $pageTitle ?? '계좌별거래내역';
$bankAccount = isset($bankAccount) && is_array($bankAccount) ? $bankAccount : [];
$bankAccountName = trim((string) ($bankAccount['account_name'] ?? ''));
$bankName = trim((string) ($bankAccount['bank_name'] ?? ''));
$accountNumber = trim((string) ($bankAccount['account_number'] ?? ''));
$bankAccountId = trim((string) ($bankAccount['id'] ?? ''));
$bankAccountQuery = $bankAccountId !== ''
    ? '?bank_account_id=' . rawurlencode($bankAccountId)
    : '';
$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => false,
    'wrapper' => 'single',
];
$pageStyles = AssetHelper::css('/assets/css/pages/funds/bank-transactions/index.css')
    . AssetHelper::css('/assets/css/pages/ledger/voucher/index.css')
    . AssetHelper::css('/assets/css/pages/ledger/account.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/journal.js')
    . AssetHelper::module('/assets/js/pages/funds/bank-transactions/index.js');
?>

<main class="funds-bank-transactions-page" id="fundsBankTransactionsPage">
    <div class="container-fluid py-3">
        <div class="page-header mb-3 d-flex justify-content-between align-items-start">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-bank me-2"></i>계좌별거래내역
                    <?php if ($bankAccountName !== ''): ?>
                        <span class="text-primary ms-1"><?= htmlspecialchars($bankAccountName, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <span id="fundsBankTransactionCount" class="text-primary page-count"></span>
                </h5>
                <div class="small text-muted mt-1">
                    <?php if ($bankAccountId !== ''): ?>
                        <?= htmlspecialchars(trim($bankName . ' ' . $accountNumber), ENT_QUOTES, 'UTF-8') ?>
                        계좌의 입출금 원본과 전표 연결상태를 조회합니다.
                    <?php else: ?>
                        전체 운영계좌의 입출금(은행) 증빙원본과 전표 연결상태를 조회합니다.
                    <?php endif; ?>
                </div>
            </div>
            <a href="/ledger/funds" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-left me-1"></i>자금현황
            </a>
        </div>

        <section class="funds-summary-grid mb-3" aria-label="계좌별거래내역 요약">
            <div class="funds-summary-card">
                <span>입금합계</span>
                <strong data-funds-summary="deposit_total">0</strong>
            </div>
            <div class="funds-summary-card">
                <span>출금합계</span>
                <strong data-funds-summary="withdraw_total">0</strong>
            </div>
            <div class="funds-summary-card">
                <span>잔액</span>
                <strong data-funds-summary="ending_balance">-</strong>
            </div>
            <div class="funds-summary-card">
                <span>전표연결</span>
                <strong data-funds-summary="voucher_linked_count">0건</strong>
            </div>
            <div class="funds-summary-card">
                <span>전표미연결</span>
                <strong data-funds-summary="unlinked_count">0건</strong>
            </div>
        </section>

        <div class="content-area">
            <?php
            $searchId = 'fundsBankTransactions';
            $dateOptions = '
                <option value="raw_transaction_datetime">거래일시</option>
                <option value="created_at">업로드일시</option>
            ';
            $searchFieldOptions = '
                <option value="raw_transaction_datetime">거래일시</option>
                <option value="raw_description">거래내용/메모</option>
                <option value="bank_account_id">계좌</option>
                <option value="account_name">계좌명</option>
                <option value="account_number">계좌번호</option>
                <option value="transaction_direction">입출구분</option>
                <option value="bank_name">은행명</option>
                <option value="raw_deposit_amount">입금액</option>
                <option value="raw_withdraw_amount">출금액</option>
                <option value="client_name">거래처명</option>
                <option value="raw_counterparty_name">상대계좌예금주명</option>
                <option value="raw_counterparty_account_number">상대계좌번호</option>
                <option value="raw_counterparty_bank_name">상대은행</option>
                <option value="raw_check_bill_amount">수표어음금액</option>
                <option value="raw_cms_code">CMS코드</option>
                <option value="voucher_link_status">전표연결상태</option>
                <option value="evidence_status">증빙상태</option>
                <option value="amount_min">금액 최소</option>
                <option value="amount_max">금액 최대</option>
                <option value="deleted_scope">삭제상태</option>
            ';
            $periodGuideTitle = '계좌별거래내역 기간 조건 안내';
            $periodGuideItems = [
                '거래일시 또는 업로드일시 기준으로 입출금 원본을 조회합니다.',
                '검색 조건은 현재 조회 요약과 목록에 함께 반영됩니다.',
            ];
            $searchGuideTitle = '계좌별거래내역 검색 조건 안내';
            $searchGuideItems = [
                '거래내용, 메모, 은행명, 거래처명, 상대계좌예금주명, 상태, 금액 조건을 조합해 검색할 수 있습니다.',
                '검색 조건은 최대 5개까지 번갈아 지정할 수 있습니다.',
            ];
            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            ?>

            <?php
            $tableId = 'fundsBankTransactionsTable';
            $ajaxUrl = '/api/funds/bank-transactions' . $bankAccountQuery;
            $columnsType = 'fundsBankTransactions';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = false;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>

<div class="modal fade" id="fundsBankSourceModal" tabindex="-1" aria-labelledby="fundsBankSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fundsBankSourceModalLabel">입출금 원본보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <dl class="row funds-source-detail" id="fundsBankSourceDetail"></dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary btn-sm" id="fundsBankSourceEditBtn">증빙수정</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<?php
$modalId = 'fundsBankTrashModal';
$type = 'fundsBankTransaction';
$modalTitle = '계좌별거래내역 휴지통';
$tableId = 'funds-bank-trash-table';
$checkAllId = 'fundsBankTrashCheckAll';
$tableHead = '
    <th>거래일시</th>
    <th>계좌</th>
    <th>거래내용</th>
    <th class="text-end">입금</th>
    <th class="text-end">출금</th>
    <th>삭제일시</th>
    <th width="120">관리</th>
';
$emptyMessage = '휴지통의 입출금 원본을 선택하면 상세 정보가 표시됩니다.';
$listUrl = '/api/funds/bank-transactions/trash' . $bankAccountQuery;
$restoreUrl = '/api/funds/bank-transactions/restore';
$deleteUrl = '';
$deleteAllUrl = '';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php include PROJECT_ROOT . '/app/views/ledger/journal/partials/journal_modal.php'; ?>
<?php include PROJECT_ROOT . '/app/views/main/settings/system/partials/code_modal.php'; ?>

<template id="journal-client-modal-template">
    <?php include PROJECT_ROOT . '/app/views/main/settings/base-info/partials/client_modal.php'; ?>
</template>

<template id="journal-account-modal-template">
    <?php include PROJECT_ROOT . '/app/views/ledger/account/partials/account_modal.php'; ?>
</template>

<template id="journal-project-modal-template">
    <?php include PROJECT_ROOT . '/app/views/main/settings/base-info/partials/project_modal.php'; ?>
</template>

<div class="picker-root">
    <div id="mini-picker" class="picker is-hidden"></div>
    <div id="base-picker" class="picker is-hidden"></div>
    <div id="datetime-picker" class="picker is-hidden"></div>
    <div id="today-picker" class="picker is-hidden"></div>
    <div id="time-list-picker" class="picker is-hidden"></div>
</div>

<div class="modal fade" id="fundsBankManageModal" tabindex="-1" aria-labelledby="fundsBankManageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fundsBankManageModalLabel">입출금 관리</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <div class="funds-manage-summary" id="fundsBankManageSummary"></div>
                <div class="funds-manage-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-funds-manage-action="source">
                        <i class="bi bi-file-earmark-text me-1"></i>원본보기
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-funds-manage-action="edit-evidence">
                        <i class="bi bi-pencil-square me-1"></i>증빙수정
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" data-funds-manage-action="voucher">
                        <i class="bi bi-journal-text me-1"></i>전표보기
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-funds-manage-action="delete">
                        <i class="bi bi-trash me-1"></i>삭제
                    </button>
                    <button type="button" class="btn btn-success btn-sm d-none" data-funds-manage-action="restore">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>복구
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>
