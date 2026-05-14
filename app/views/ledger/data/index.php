<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '자료목록';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => false,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/voucher-recommendation-modal.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataList.js');
?>

<main class="ledger-data-list-page" id="ledgerDataListPage">
    <div class="container-fluid py-3">
        <div class="page-header mb-2 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-database-check me-2"></i>자료목록
                </h5>
                <div class="small text-muted mt-1">
                    Seed Data를 검토하고 수정한 뒤 선택한 행으로 거래를 생성합니다.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="/ledger/data/upload"
                   class="btn btn-outline-primary btn-sm"
                   title="원본 파일을 검증하고 Seed Data로 적재합니다.">
                    <i class="bi bi-upload me-1"></i>자료 업로드
                </a>
            </div>
        </div>

        <div class="content-area">
            <?php
            $searchId = 'seedRows';

            $dateOptions = '
                <option value="mapped_payload.transaction_date">작성일자</option>
                <option value="created_at">생성일시</option>
                <option value="processed_at">처리일시</option>
                <option value="updated_at">수정일시</option>
            ';

            $searchFieldOptions = '
                <option value="">선택</option>
                <option value="process_status">상태</option>
                <option value="source_type">자료출처</option>
                <option value="import_type">자료유형</option>
                <option value="mapped_payload.transaction_direction">거래구분</option>
                <option value="client_name">거래처</option>
                <option value="mapped_payload.transaction_date">작성일자</option>
                <option value="mapped_payload.supply_amount">공급가</option>
                <option value="mapped_payload.vat_amount">부가세</option>
                <option value="mapped_payload.total_amount">합계금액</option>
                <option value="mapped_payload.description">적요</option>
                <option value="transaction_id">거래번호</option>
                <option value="file_name">파일명</option>
                <option value="format_name">양식명</option>
            ';

            $periodGuideTitle = 'Seed Data 기간 조건 안내';
            $periodGuideItems = [
                '작성일자, 생성일시, 처리일시, 수정일시 기준으로 Seed Data를 조회합니다.',
                'READY 상태는 거래 생성 가능, PROCESSED 상태는 거래 생성 완료 상태입니다.',
            ];

            $searchGuideTitle = 'Seed Data 검색 조건 안내';
            $searchGuideItems = [
                '자료출처, 자료유형, 거래구분, 거래처, 적요, 금액 등 Seed 기준 필드로 검색합니다.',
                '검색 결과에서 READY 행을 선택해 거래를 생성할 수 있습니다.',
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

<div class="modal fade" id="seedRowEditModal" tabindex="-1" aria-labelledby="seedRowEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="seedRowEditModalLabel">Seed Data 상세/수정</h5>
                    <div class="small text-muted" id="seedRowEditSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="seedRowEditId">
                <div class="alert alert-info py-2 small">
                    raw_json은 원본 보존값이므로 수정하지 않습니다. READY 상태에서만 아래 표준 Seed 값을 수정할 수 있습니다.
                </div>
                <div id="seedRowEditFields" class="seed-row-edit-fields"></div>
            </div>
            <div class="modal-footer justify-content-end">
                <button type="button" class="btn btn-danger btn-sm" id="seedRowEditDeleteBtn">
                    <i class="bi bi-trash me-1"></i>삭제
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="seedRowEditSaveBtn">저장</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<?php
$modalId = 'seedRowsTrashModal';
$type = 'seedRows';
$modalTitle = 'Seed Data 휴지통';
$tableId = 'seedRows-trash-table';
$checkAllId = 'seedRowsTrashCheckAll';
$tableHead = '
    <th>상태</th>
    <th>자료출처</th>
    <th>자료유형</th>
    <th>거래처</th>
    <th>합계금액</th>
    <th>작성일자</th>
    <th>삭제일시</th>
    <th width="150">관리</th>
';
$emptyMessage = '휴지통 Seed Data를 선택하면 상세 정보가 표시됩니다.';
$listUrl = '/api/import/evidences/trash';
$restoreUrl = '/api/import/evidences/restore';
$deleteUrl = '/api/import/evidences/purge';
$deleteAllUrl = '/api/import/evidences/purge-all';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php include PROJECT_ROOT . '/app/views/ledger/partials/voucher_recommendation_modal.php'; ?>
