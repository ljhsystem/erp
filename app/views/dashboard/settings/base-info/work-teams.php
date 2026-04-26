<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/work-teams.php'
?>

<div class="work-team-page" id="work-team-main">
    <div class="page-header">
        <h5 class="mb-1 fw-bold">작업팀 관리</h5>
        <span id="workTeamCount" class="text-primary work-team-count page-count"></span>
    </div>

    <div class="content-area">
        <?php
        $searchId = 'workTeam';
        $dateOptions = '
            <option value="created_at">등록일시</option>
            <option value="updated_at">수정일시</option>
        ';
        $searchFieldOptions = '<option value="">선택</option>';
        include PROJECT_ROOT . '/app/views/components/ui-search.php';
        ?>

        <?php
        $tableId = 'work-team-table';
        $ajaxUrl = '/api/settings/base-info/work-team/list';
        $columnsType = 'work-team';
        $enableButtons = true;
        $enableSearch = true;
        $enablePaging = true;
        $enableReorder = true;
        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>
    </div>
</div>

<?php include __DIR__ . '/partials/work_team_modal.php'; ?>

<template id="work-team-client-modal-template">
    <?php include __DIR__ . '/partials/client_modal.php'; ?>
</template>

<?php
$modalId = 'workTeamTrashModal';
$type = 'workTeam';
$modalTitle = '작업팀 휴지통';
$tableId = 'work-team-trash-table';
$checkAllId = 'workTeamTrashCheckAll';
$btnRestoreId = 'workTeamBtnRestoreSelected';
$btnDeleteId = 'workTeamBtnDeleteSelected';
$btnDeleteAll = 'workTeamBtnDeleteAll';
$tableHead = '
    <th>순번</th>
    <th>팀명</th>
    <th>팀장</th>
    <th>삭제일시</th>
    <th>삭제자</th>
    <th>관리</th>
';
$emptyMessage = '삭제된 작업팀을 선택하세요.';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php
$templateUrl = '/api/settings/base-info/work-team/template';
$downloadUrl = '/api/settings/base-info/work-team/excel';
$uploadUrl = '/api/settings/base-info/work-team/excel-upload';

$modalId = 'workTeamExcelModal';
$formId = 'workTeamExcelForm';
$modalTitle = '작업팀 엑셀관리';

$fileInputId = 'workTeamExcelFile';
$spinnerId = 'workTeamExcelSpinner';

$btnTemplateId = 'workTeamBtnDownloadTemplate';
$btnDownloadAll = 'workTeamBtnDownloadAll';
$uploadBtnId = 'workTeamBtnUploadExcel';

include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>

<div class="picker-root">
    <div id="today-picker" class="picker is-hidden"></div>
</div>
