<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/base-info/cards.php'
?>

<div class="card-page" id="card-main" data-flash="<?= htmlspecialchars($flashMsg ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <div class="page-header">
    <h5 class="mb-1 fw-bold">💳 카드관리</h5>
    <span id="cardCount" class="text-primary card-count page-count"></span>
  </div>

  <div class="content-area">

    <?php
    $searchId = 'card';

    $dateOptions = '
      <option value="created_at">등록일자</option>
      <option value="updated_at">수정일자</option>
    ';

    $searchFieldOptions = '
      <option value="card_name">카드명</option>
      <option value="card_number">카드번호</option>
      <option value="card_type">카드유형</option>
      <option value="client_name">카드사</option>
      <option value="account_name">결제계좌</option>
      <option value="is_active">상태</option>
      <option value="currency">통화</option>
      <option value="note">비고</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
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
$modalId      = 'cardTrashModal';
$type         = 'card';
$modalTitle   = '카드 휴지통';

$tableId      = 'card-trash-table';
$checkAllId   = 'cardTrashCheckAll';

$btnRestoreId = 'cardBtnRestoreSelected';
$btnDeleteId  = 'cardBtnDeleteSelected';
$btnDeleteAll = 'cardBtnDeleteAll';

$tableHead = '
  <th>순번</th>
  <th>카드명</th>
  <th>카드사</th>
  <th>카드번호</th>
  <th>카드유형</th>
  <th>결제계좌</th>
  <th>통화</th>
  <th>상태</th>
  <th>삭제일시</th>
  <th>삭제자</th>
  <th>관리</th>
';

$emptyMessage = '삭제된 카드가 없습니다.';

include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php
include __DIR__ . '/partials/card_modal.php';
?>

<div class="picker-root">
  <div id="mini-picker" class="picker is-hidden"></div>
  <div id="base-picker" class="picker is-hidden"></div>
  <div id="datetime-picker" class="picker is-hidden"></div>
  <div id="today-picker" class="picker is-hidden"></div>
  <div id="time-list-picker" class="picker is-hidden"></div>
</div>
