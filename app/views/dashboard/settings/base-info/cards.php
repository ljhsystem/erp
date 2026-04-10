<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/cards.php'
?>

<div class="card-page" id="card-main" data-flash="<?= htmlspecialchars($flashMsg ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- HEADER -->
  <div class="page-header">
    <h5 class="mb-1 fw-bold">💳 카드관리</h5>
    <span id="cardCount" class="text-primary card-count"></span>
  </div>

  <div class="content-area">

    <?php
    /* =========================
       검색폼
    ========================== */

    $searchId = 'card';

    $dateOptions = '
      <option value="created_at">등록일자</option>
    ';

    $searchFieldOptions = '
      <option value="card_name">카드명</option>
      <option value="card_number">카드번호</option>
      <option value="card_type">카드유형</option>
      <option value="client_name">카드사</option>
      <option value="account_name">결제계좌</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    /* =========================
       테이블
    ========================== */

    $tableId       = 'card-table';
    $ajaxUrl       = '/api/settings/base-info/card/list';
    $columnsType   = 'card';

    $enableButtons = true;
    $enableSearch  = true;
    $enablePaging  = true;
    $enableReorder = true;

    include PROJECT_ROOT . '/app/views/components/ui-table.php';
    ?>

  </div>
</div>

<?php
/* =========================
   엑셀 모달
========================= */

$templateUrl = '/api/settings/base-info/card/template';
$downloadUrl = '/api/settings/base-info/card/excel';
$uploadUrl   = '/api/settings/base-info/card/excel-upload';

$modalId        = 'cardExcelModal';
$formId         = 'cardExcelForm';
$modalTitle     = '카드 엑셀관리';

$fileInputId    = 'cardExcelFile';
$spinnerId      = 'cardExcelSpinner';

$btnTemplateId  = 'cardBtnDownloadTemplate';
$btnDownloadAll = 'cardBtnDownloadAll';

$uploadBtnId    = 'cardBtnUploadExcel';

include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>

<?php
/* =========================
   휴지통 모달
========================= */

$modalId      = 'cardTrashModal';
$type         = 'card';
$modalTitle   = '카드 휴지통';

$tableId      = 'card-trash-table';
$checkAllId   = 'cardTrashCheckAll';

$btnRestoreId = 'cardBtnRestoreSelected';
$btnDeleteId  = 'cardBtnDeleteSelected';
$btnDeleteAll = 'cardBtnDeleteAll';

$tableHead = '
  <th>코드</th>
  <th>카드명</th>
  <th>카드번호</th>
  <th>카드유형</th>
  <th>카드사</th>
  <th>결제계좌</th>
  <th>통화</th>
  <th>사용여부</th>
  <th>삭제일</th>
  <th>삭제자</th>
  <th>관리</th>
';

$emptyMessage = '삭제된 카드를 선택하세요';

include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php
/* =========================
   카드 모달
========================= */
include __DIR__ . '/partials/card_modal.php';
?>

<?php
/* =========================
   Picker Root
========================= */
?>
<div class="picker-root">
  <div id="mini-picker" class="picker is-hidden"></div>
  <div id="base-picker" class="picker is-hidden"></div>
  <div id="datetime-picker" class="picker is-hidden"></div>
  <div id="today-picker" class="picker is-hidden"></div>
  <div id="time-list-picker" class="picker is-hidden"></div>
</div>