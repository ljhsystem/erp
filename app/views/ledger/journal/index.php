<?php
// 경로: PROJECT_ROOT . '/app/views/ledger/journal/index.php'

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '일반전표';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles =
    AssetHelper::css('/assets/css/pages/ledger/journal.css') .
    AssetHelper::css('/assets/css/pages/ledger/account.css');

$pageScripts = AssetHelper::module('/assets/js/pages/ledger/journal.js');
?>

<main class="journal-page" id="journal-main">
    <div class="container-fluid py-4 journal-shell">
        <div class="page-header">
            <h5 class="mb-1 fw-bold">
                <i class="bi bi-journal-check me-2"></i>일반전표
            </h5>
            <span id="journalCount" class="text-primary journal-count page-count"></span>
        </div>

        <div class="content-area">
            <?php
            $searchId = 'journal';

            $dateOptions = '
                <option value="voucher_date">전표일자</option>
                <option value="updated_at">수정일시</option>
            ';

            $searchFieldOptions = '
                <option value="">선택</option>
            ';

            $periodGuideTitle = '전표 기간 조건 안내';
            $periodGuideItems = [
                '전표일자 또는 수정일시 기준으로 조회 기간을 지정합니다.',
                '빠른 선택 버튼으로 자주 쓰는 기간을 바로 입력할 수 있습니다.',
            ];

            $searchGuideTitle = '전표 검색 조건 안내';
            $searchGuideItems = [
                '상태, 타입, 계정과목, 거래연결여부 조건을 선택해 검색합니다.',
                '거래연결여부는 linked 또는 unlinked 값으로 검색합니다.',
            ];

            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            ?>

            <?php
            $tableId = 'journal-table';
            $tableClass = 'table table-bordered align-middle table-cross-highlight journal-table';
            $ajaxUrl = '/api/ledger/voucher/list';
            $columnsType = 'journal';

            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = true;

            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>

<?php
$templateUrl = '/api/ledger/voucher/template';
$downloadUrl = '/api/ledger/voucher/download';
$uploadUrl = '/api/ledger/voucher/excel-upload';

$modalId = 'journalExcelModal';
$formId = 'journalExcelForm';
$modalTitle = '전표 엑셀 관리';

$fileInputId = 'journalExcelFile';
$spinnerId = 'journalExcelSpinner';

$btnTemplateId = 'journalBtnDownloadTemplate';
$btnDownloadAll = 'journalBtnDownloadAll';
$uploadBtnId = 'journalBtnUploadExcel';

include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>

<?php
$modalId = 'journalTrashModal';
$type = 'journal';
$modalTitle = '전표 휴지통';

$tableId = 'journal-trash-table';
$checkAllId = 'journalTrashCheckAll';

$btnRestoreId = 'journalBtnRestoreSelected';
$btnDeleteId = 'journalBtnDeleteSelected';
$btnDeleteAll = 'journalBtnDeleteAll';

$tableHead = '
    <th>전표번호</th>
    <th>전표일자</th>
    <th>적요</th>
    <th>삭제일시</th>
    <th>관리</th>
';

$emptyMessage = '삭제된 전표를 선택하세요.';

include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php include __DIR__ . '/partials/journal_modal.php'; ?>

<?php include PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php'; ?>

<template id="journal-account-modal-template">
    <?php include PROJECT_ROOT . '/app/views/ledger/account/partials/account_modal.php'; ?>
</template>

<div class="picker-root">
    <div id="mini-picker" class="picker is-hidden"></div>
    <div id="base-picker" class="picker is-hidden"></div>
    <div id="datetime-picker" class="picker is-hidden"></div>
    <div id="today-picker" class="picker is-hidden"></div>
    <div id="time-list-picker" class="picker is-hidden"></div>
</div>
