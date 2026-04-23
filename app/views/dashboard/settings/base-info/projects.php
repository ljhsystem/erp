<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/projects.php'
?>

<div class="project-page" id="project-main" data-flash="<?= htmlspecialchars($flashMsg ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- =========================
       HEADER
  ========================== -->
  <div class="page-header">
    <h5 class="mb-1 fw-bold">🏗 프로젝트관리</h5>
    <span id="projectCount" class="text-primary project-count page-count"></span>
  </div>

  <div class="content-area">

    <?php
    /* =========================================================
       🔥 공통 검색폼
    ========================================================= */

    $searchId = 'project';

    $dateOptions = '
      <option value="start_date">착공일자</option>
      <option value="completion_date">준공일자</option>
      <option value="contract_date">계약일자</option>
      <option value="permit_date">인허가일자</option>
      <option value="bid_notice_date">입찰공고일</option>
      <option value="updated_at">수정일자</option>
    ';

    $searchFieldOptions = '
      <option value="">선택</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    /* =========================================================
       🔥 공통 테이블
    ========================================================= */

    $tableId       = 'project-table';
    $ajaxUrl       = '/api/settings/base-info/project/list';
    $columnsType   = 'project';

    $enableButtons = true;
    $enableSearch  = true;
    $enablePaging  = true;
    $enableReorder = true;

    include PROJECT_ROOT . '/app/views/components/ui-table.php';
    ?>

  </div>
</div>


<?php
/* =========================================================
   🔥 공통 엑셀 모달
========================================================= */

/* 🔥 프로젝트 전용 URL */
$templateUrl = '/api/settings/base-info/project/template';
$downloadUrl = '/api/settings/base-info/project/excel';
$uploadUrl   = '/api/settings/base-info/project/excel-upload';

$modalId        = 'projectExcelUploadModal';
$formId         = 'project-excel-upload-form';
$modalTitle     = '프로젝트 엑셀관리';

$fileInputId    = 'projectExcelFile';
$spinnerId      = 'projectExcelUploadSpinner';

$btnTemplateId  = 'btnDownloadProjectTemplate';
$btnDownloadAll = 'btnDownloadAllProjects';

$uploadBtnId    = 'btnUploadProjectExcel';

include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>


<?php
/* =========================================================
   🔥 공통 휴지통 모달
========================================================= */
$modalId      = 'projectTrashModal';
$type         = 'project';
$modalTitle   = '프로젝트 휴지통';

$tableId      = 'project-trash-table';
$checkAllId   = 'projectTrashCheckAll';

$btnRestoreId = 'btnRestoreSelectedProject';
$btnDeleteId  = 'btnDeleteSelectedProject';
$btnDeleteAll = 'btnDeleteAllProjects';

$tableHead = '
  <th>순번</th>
  <th>프로젝트명</th>
  <th>삭제일</th>
  <th>삭제자</th>
  <th>관리</th>
';

$emptyMessage = '삭제된 프로젝트를 선택하세요';

include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>


<?php
/* =========================================================
   🔥 프로젝트 수정 모달 (개별 유지)
========================================================= */
include __DIR__ . '/partials/project_modal.php';
?>


<?php
/* =========================================================
   🔥 Picker Root
========================================================= */
?>
<div class="picker-root">
  <div id="mini-picker" class="picker is-hidden"></div>
  <div id="base-picker" class="picker is-hidden"></div>
  <div id="datetime-picker" class="picker is-hidden"></div>
  <div id="today-picker" class="picker is-hidden"></div>
  <div id="time-list-picker" class="picker is-hidden"></div>
</div>
