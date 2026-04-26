<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/client.php'
?>

<div class="client-page" id="client-main" data-flash="<?= htmlspecialchars($flashMsg ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- =========================
       HEADER
  ========================== -->
  <div class="page-header">
    <h5 class="mb-1 fw-bold">🧾 거래처관리</h5>
    <span id="clientCount" class="text-primary client-count page-count"></span>
  </div>

  <div class="content-area">

    <?php
    /* =========================================================
       🔥 공통 검색폼
    ========================================================= */

    $searchId = 'client';

    $dateOptions = '
      <option value="registration_date">등록일자</option>
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

    $tableId       = 'client-table';
    $ajaxUrl       = '/api/settings/base-info/client/list';
    $columnsType   = 'client';

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

/* 🔥 여기 넣는거다 */
$templateUrl = '/api/settings/base-info/client/template';
$downloadUrl = '/api/settings/base-info/client/excel';
$uploadUrl   = '/api/settings/base-info/client/excel-upload';

$modalId        = 'clientExcelModal';
$formId         = 'clientExcelForm';
$modalTitle     = '거래처 엑셀관리';

$fileInputId    = 'clientExcelFile';
$spinnerId      = 'clientExcelSpinner';

$btnTemplateId  = 'clientBtnDownloadTemplate';
$btnDownloadAll = 'clientBtnDownloadAll';

$uploadBtnId    = 'clientBtnUploadExcel';

include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>


<?php
/* =========================================================
   🔥 공통 휴지통 모달
========================================================= */
$modalId      = 'clientTrashModal';
$type         = 'client';
$modalTitle   = '거래처 휴지통';

$tableId      = 'client-trash-table';
$checkAllId   = 'clientTrashCheckAll';

$btnRestoreId = 'clientBtnRestoreSelected';
$btnDeleteId  = 'clientBtnDeleteSelected';
$btnDeleteAll = 'clientBtnDeleteAll';

$tableHead = '
  <th>순번</th>
  <th>거래처명</th>
  <th>삭제일</th>
  <th>삭제자</th>
  <th>관리</th>
';

$emptyMessage = '삭제된 거래처를 선택하세요';

include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>


<?php
/* =========================================================
   🔥 거래처 수정 모달 (개별 유지)
========================================================= */
include __DIR__ . '/partials/client_modal.php';
?>

<?php
/* =========================================================
   기준정보 오리지널 모달
========================================================= */
include __DIR__ . '/partials/code_modal.php';
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
