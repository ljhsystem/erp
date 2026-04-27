<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/system/codes.php'
?>

<div class="code-page" id="code-main">
    <div class="page-header">
        <h5 class="mb-1 fw-bold">기준정보 관리</h5>
        <span id="codeCount" class="text-primary code-count page-count"></span>
    </div>

    <div class="content-area">
        <?php
        $searchId = 'code';
        $dateOptions = '
            <option value="created_at">등록일시</option>
            <option value="updated_at">수정일시</option>
        ';
        $searchFieldOptions = '<option value="">선택</option>';
        include PROJECT_ROOT . '/app/views/components/ui-search.php';
        ?>

        <?php
        $tableId = 'code-table';
        $ajaxUrl = '/api/settings/system/code/list';
        $columnsType = 'code';
        $enableButtons = true;
        $enableSearch = true;
        $enablePaging = true;
        $enableReorder = true;
        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>
    </div>
</div>

<?php include __DIR__ . '/partials/code_modal.php'; ?>

<?php
$modalId = 'codeTrashModal';
$type = 'code';
$modalTitle = '기준정보 휴지통';
$tableId = 'code-trash-table';
$checkAllId = 'codeTrashCheckAll';
$btnRestoreId = 'codeBtnRestoreSelected';
$btnDeleteId = 'codeBtnDeleteSelected';
$btnDeleteAll = 'codeBtnDeleteAll';
$tableHead = '
    <th>순번</th>
    <th>코드</th>
    <th>코드명</th>
    <th>상태</th>
    <th>삭제일시</th>
    <th>삭제자</th>
    <th>관리</th>
';
$emptyMessage = '삭제된 기준정보를 선택하세요.';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php
$templateUrl = '/api/settings/system/code/template';
$downloadUrl = '/api/settings/system/code/excel';
$uploadUrl = '/api/settings/system/code/excel-upload';

$modalId = 'codeExcelModal';
$formId = 'codeExcelForm';
$modalTitle = '기준정보 엑셀관리';

$fileInputId = 'codeExcelFile';
$spinnerId = 'codeExcelSpinner';

$btnTemplateId = 'codeBtnDownloadTemplate';
$btnDownloadAll = 'codeBtnDownloadAll';
$uploadBtnId = 'codeBtnUploadExcel';

include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>

<div class="picker-root">
    <div id="today-picker" class="picker is-hidden"></div>
</div>
