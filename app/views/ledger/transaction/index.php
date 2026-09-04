<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = $pageTitle ?? 'Transaction Input';
$pageAssetProfile = 'data-list-light';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/main/settings/system/code.css')
    . AssetHelper::css('/assets/css/pages/main/settings/client.css')
    . AssetHelper::css('/assets/css/pages/ledger/data-status.css')
    . AssetHelper::css('/assets/css/pages/ledger/transaction/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/transaction/index.js');
?>

<main class="transaction-page" id="transaction-main">
    <div class="container-fluid py-4 transaction-shell dt-page-shell">
        <div class="page-header">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-receipt-cutoff me-2"></i>거래 관리
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
                <option value="">전체</option>
                <option value="sort_no">순번</option>
                <option value="transaction_date">거래일자</option>
                <option value="business_unit">사업구분</option>
                <option value="client_name">거래처명</option>
                <option value="project_name">프로젝트명</option>
                <option value="description">적요</option>
                <option value="currency">통화</option>
                <option value="exchange_rate">환율</option>
                <option value="supply_amount">공급가액</option>
                <option value="foreign_amount">외화금액</option>
                <option value="settlement_amount">정산금액</option>
                <option value="final_amount">최종금액</option>
                <option value="status">상태</option>
                <option value="note">비고</option>
                <option value="memo">메모</option>
                <option value="created_at">생성일시</option>
                <option value="created_by">생성자</option>
                <option value="updated_at">수정일시</option>
                <option value="updated_by">수정자</option>
                <option value="deleted_at">삭제일시</option>
                <option value="deleted_by">삭제자</option>
            ';

            $periodGuideTitle = '거래 조회 기간 안내';
            $periodGuideItems = [
                '거래일자와 수정일시를 기준으로 원하는 기간의 거래를 조회할 수 있습니다.',
                '기간 조건과 검색 조건을 함께 사용하면 원하는 데이터를 더 빠르게 찾을 수 있습니다.',
            ];

            $searchGuideTitle = '거래 검색 안내';
            $searchGuideItems = [
                '거래처명, 프로젝트명, 적요, 메모, 상태, 매칭상태 등 다양한 조건으로 검색할 수 있습니다.',
                '검색어와 기간 조건을 함께 사용하면 목록을 더 정확하게 좁힐 수 있습니다.',
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
            $enableReorder = false;

            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/partials/transaction_modal.php'; ?>
<?php include PROJECT_ROOT . '/app/views/main/settings/system/partials/code_modal.php'; ?>
<?php include PROJECT_ROOT . '/app/views/main/settings/base-info/partials/client_modal.php'; ?>
<?php
$modalId = 'transactionTrashModal';
$type = 'transaction';
$modalTitle = 'Transaction Trash';
$tableId = 'transaction-trash-table';
$checkAllId = 'transactionTrashCheckAll';
$tableHead = '
    <th>거래일자</th>
    <th>거래처</th>
    <th>사업구분</th>
    <th>최종금액</th>
    <th>삭제일시</th>
    <th>삭제자</th>
    <th width="150">작업</th>
';
$emptyMessage = '휴지통에 거래 데이터가 없습니다.';
$listUrl = '/api/ledger/transaction/trash';
$restoreUrl = '/api/ledger/transaction/restore';
$deleteUrl = '/api/ledger/transaction/purge';
$deleteAllUrl = '/api/ledger/transaction/purge-all';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>
<?php
$evidenceTypePoliciesJson = json_encode(
    $evidenceTypePolicies ?? [],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<script type="application/json" id="ledgerEvidenceTypePolicies"><?= $evidenceTypePoliciesJson ?></script>
