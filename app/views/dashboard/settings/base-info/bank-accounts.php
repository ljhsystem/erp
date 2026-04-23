<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/bank-accounts.php'
?>

<div class="account-page" id="account-main" data-flash="<?= htmlspecialchars($flashMsg ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- =========================
       HEADER
  ========================== -->
  <div class="page-header">
    <h5 class="mb-1 fw-bold">🏦 계좌관리</h5>
    <span id="accountCount" class="text-primary account-count page-count"></span>
  </div>

  <div class="content-area">

    <?php
    /* =========================================================
       🔥 공통 검색폼
    ========================================================= */

    $searchId = 'account';

    $dateOptions = '
      <option value="created_at">등록일자</option>
    ';

    $searchFieldOptions = '
      <option value="account_name">계좌명</option>
      <option value="bank_name">은행명</option>
      <option value="account_number">계좌번호</option>
      <option value="account_holder">예금주</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    /* =========================================================
       🔥 공통 테이블
    ========================================================= */

    $tableId       = 'account-table';
    $ajaxUrl       = '/api/settings/base-info/account/list';
    $columnsType   = 'account';

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

$templateUrl = '/api/settings/base-info/account/template';
$downloadUrl = '/api/settings/base-info/account/excel';
$uploadUrl   = '/api/settings/base-info/account/excel-upload';

$modalId        = 'accountExcelModal';
$formId         = 'accountExcelForm';
$modalTitle     = '계좌 엑셀관리';

$fileInputId    = 'accountExcelFile';
$spinnerId      = 'accountExcelSpinner';

$btnTemplateId  = 'accountBtnDownloadTemplate';
$btnDownloadAll = 'accountBtnDownloadAll';

$uploadBtnId    = 'accountBtnUploadExcel';

include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>


<?php
/* =========================================================
   🔥 공통 휴지통 모달
========================================================= */
$modalId      = 'accountTrashModal';
$type         = 'account';
$modalTitle   = '계좌 휴지통';

$tableId      = 'account-trash-table';
$checkAllId   = 'accountTrashCheckAll';

$btnRestoreId = 'accountBtnRestoreSelected';
$btnDeleteId  = 'accountBtnDeleteSelected';
$btnDeleteAll = 'accountBtnDeleteAll';

$tableHead = '
  <th>순번</th>
  <th>계좌명</th>
  <th>은행명</th>
  <th>계좌번호</th>
  <th>통장사본</th>
  <th>삭제일</th>
  <th>삭제자</th>
  <th>관리</th>
';

$emptyMessage = '삭제된 계좌를 선택하세요';

include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>


<?php
/* =========================================================
   🔥 계좌 수정 모달
========================================================= */
include __DIR__ . '/partials/bank_account_modal.php';
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
