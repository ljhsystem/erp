<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = $pageTitle ?? '거래입력';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.css')
    . AssetHelper::css('/assets/css/pages/dashboard/settings/system/code.css')
    . AssetHelper::css('/assets/css/pages/dashboard/settings/client.css')
    . AssetHelper::css('/assets/css/pages/ledger/transaction.css');
$pageScripts = AssetHelper::js('https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.js')
    . AssetHelper::module('/assets/js/pages/ledger/transaction.js');
?>

<main class="transaction-page" id="transaction-main">
    <div class="container-fluid py-4 transaction-shell">
        <div class="page-header">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-receipt-cutoff me-2"></i>거래입력
            </h5>
            <span id="transactionCount" class="text-primary transaction-count page-count"></span>
        </div>

        <div class="content-area">
            <?php
            $searchId = 'transaction';

            $dateOptions = '
                <option value="transaction_date">거래일자</option>
                <option value="updated_at">수정일시</option>
            ';

            $searchFieldOptions = '
                <option value="">선택</option>
                <option value="sort_no">순서</option>
                <option value="transaction_date">거래일자</option>
                <option value="business_unit">사업구분</option>
                <option value="transaction_type">거래유형</option>
                <option value="client_name">거래처</option>
                <option value="project_name">프로젝트</option>
                <option value="description">적요</option>
                <option value="currency">통화</option>
                <option value="exchange_rate">환율</option>
                <option value="tax_type">과세구분</option>
                <option value="supply_amount">공급가</option>
                <option value="vat_amount">부가세</option>
                <option value="total_amount">총금액</option>
                <option value="status">전표상태</option>
                <option value="match_status">전표연결</option>
                <option value="note">비고</option>
                <option value="memo">메모</option>
                <option value="created_at">생성일시</option>
                <option value="created_by">생성자</option>
                <option value="updated_at">수정일시</option>
                <option value="updated_by">수정자</option>
                <option value="deleted_at">삭제일시</option>
                <option value="deleted_by">삭제자</option>
            ';

            $periodGuideTitle = '거래 기간 조건 안내';
            $periodGuideItems = [
                '거래일자 또는 수정일시 기준으로 조회 기간을 지정합니다.',
                '빠른 선택 버튼으로 자주 쓰는 기간을 바로 입력할 수 있습니다.',
            ];

            $searchGuideTitle = '거래 검색 조건 안내';
            $searchGuideItems = [
                '거래처, 프로젝트, 적요, 금액, 전표연결 상태 등을 조건으로 검색합니다.',
                '여러 조건을 추가하면 조건에 맞는 거래만 조회합니다.',
            ];

            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            ?>

            <?php
            $tableId = 'transaction-table';
            $tableClass = 'table table-bordered align-middle table-cross-highlight transaction-table';
            $ajaxUrl = '/api/ledger/transaction/list';
            $columnsType = 'transaction';

            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = true;

            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/transaction_modal.php'; ?>
<?php include __DIR__ . '/partials/voucher_select_modal.php'; ?>
<?php include PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php'; ?>
<?php include PROJECT_ROOT . '/app/views/dashboard/settings/base-info/partials/client_modal.php'; ?>

<?php
$modalId = 'transactionTrashModal';
$type = 'transaction';
$modalTitle = '거래 휴지통';
$tableId = 'transaction-trash-table';
$checkAllId = 'transactionTrashCheckAll';
$tableHead = '
    <th>거래일자</th>
    <th>거래처</th>
    <th>적요</th>
    <th>총금액</th>
    <th>전표연결</th>
    <th>삭제일시</th>
    <th>삭제자</th>
    <th width="150">관리</th>
';
$emptyMessage = '휴지통 거래를 선택하면 상세 정보가 표시됩니다.';
$listUrl = '/api/ledger/transaction/trash';
$restoreUrl = '/api/ledger/transaction/restore';
$deleteUrl = '/api/ledger/transaction/purge';
$deleteAllUrl = '/api/ledger/transaction/purge-all';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>
