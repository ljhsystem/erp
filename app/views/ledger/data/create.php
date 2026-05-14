<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '생성센터';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => false,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.css')
    . AssetHelper::css('/assets/css/pages/ledger/data-create.css');
$pageScripts = AssetHelper::js('https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.js')
    . AssetHelper::module('/assets/js/pages/ledger/dataCreate.js');
?>

<main class="ledger-data-create-page" id="ledgerDataCreatePage">
    <div class="container-fluid py-3">
        <div class="page-header mb-2 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-database-check me-2"></i>생성센터
                </h5>
                <div class="small text-muted mt-1">
                    증빙원본에 정리된 기준정보와 기초정보를 바탕으로 거래와 전표 생성 상태를 관리합니다.
                </div>
            </div>
        </div>

        <section class="row g-3 mb-3" id="seedRowsStatusSummary">
            <div class="col-6 col-md-4 col-xl">
                <div class="border rounded p-3 h-100 bg-white">
                    <button type="button" class="seed-summary-filter seed-summary-total is-active" data-seed-status-filter="" data-seed-summary="total" data-seed-summary-label="전체">전체 0건</button>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="border rounded p-3 h-100 bg-white">
                    <button type="button" class="seed-summary-filter seed-summary-evidence" data-seed-status-filter="evidenceReady" data-seed-summary="evidenceReady" data-seed-summary-label="증빙준비">증빙준비 0건</button>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="border rounded p-3 h-100 bg-white">
                    <button type="button" class="seed-summary-filter seed-summary-transaction" data-seed-status-filter="transactionCreated" data-seed-summary="transactionCreated" data-seed-summary-label="거래생성">거래생성 0건</button>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="border rounded p-3 h-100 bg-white">
                    <button type="button" class="seed-summary-filter seed-summary-voucher" data-seed-status-filter="voucherCreated" data-seed-summary="voucherCreated" data-seed-summary-label="전표발행">전표발행 0건</button>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="border rounded p-3 h-100 bg-white">
                    <button type="button" class="seed-summary-filter seed-summary-correction" data-seed-status-filter="correctionNeeded" data-seed-summary="correctionNeeded" data-seed-summary-label="보정필요">보정필요 0건</button>
                </div>
            </div>
        </section>

        <section class="mb-3" id="seedRowsTypeSummary"></section>

        <div class="content-area">
            <?php
            $searchId = 'seedRows';

            $dateOptions = '
                <option value="mapped_payload.transaction_date">거래일자</option>
                <option value="created_at">생성일시</option>
                <option value="processed_at">처리일시</option>
                <option value="updated_at">수정일시</option>
            ';

            $searchFieldOptions = '
                <option value="">전체</option>
                <option value="process_status">처리상태</option>
                <option value="source_type">자료출처</option>
                <option value="import_type">자료유형</option>
                <option value="mapped_payload.transaction_direction">거래구분</option>
                <option value="client_name">거래처</option>
                <option value="mapped_payload.transaction_date">거래일자</option>
                <option value="mapped_payload.supply_amount">공급가액</option>
                <option value="mapped_payload.vat_amount">부가세</option>
                <option value="mapped_payload.total_amount">합계금액</option>
                <option value="mapped_payload.description">적요</option>
                <option value="transaction_id">거래번호</option>
                <option value="voucher_status">전표상태</option>
                <option value="format_name">양식명</option>
            ';

            $periodGuideTitle = '생성센터 기간 조건 안내';
            $periodGuideItems = [
                '거래일자, 생성일시, 처리일시, 수정일시 기준으로 증빙 데이터를 조회합니다.',
                'READY 상태는 거래 생성 가능, PROCESSED 상태는 거래 생성 완료 상태입니다.',
            ];

            $searchGuideTitle = '생성센터 검색 조건 안내';
            $searchGuideItems = [
                '자료출처, 자료유형, 거래구분, 거래처, 적요, 금액 등 증빙 원본의 주요 필드로 검색합니다.',
                '검색 결과에서 READY 행을 선택해 거래 생성을 진행할 수 있습니다.',
            ];

            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            ?>

            <?php
            $tableId = 'seedRowsTable';
            $tableClass = 'table table-bordered align-middle table-cross-highlight';
            $ajaxUrl = '/api/import/evidences';
            $columnsType = 'seedRows';

            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = false;

            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>

<?php
$modalId = 'seedRowsTrashModal';
$type = 'seedRows';
$modalTitle = '생성센터 휴지통';
$tableId = 'seedRows-trash-table';
$checkAllId = 'seedRowsTrashCheckAll';
$tableHead = '
    <th width="70">순번</th>
    <th>상태</th>
    <th>자료출처</th>
    <th>자료유형</th>
    <th>거래처</th>
    <th>합계금액</th>
    <th>거래일자</th>
    <th>삭제일시</th>
    <th width="150">관리</th>
';
$emptyMessage = '휴지통의 생성센터 데이터를 선택하면 상세 정보가 표시됩니다.';
$listUrl = '/api/import/evidences/trash';
$restoreUrl = '/api/import/evidences/restore';
$deleteUrl = '/api/import/evidences/purge';
$deleteAllUrl = '/api/import/evidences/purge-all';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>
