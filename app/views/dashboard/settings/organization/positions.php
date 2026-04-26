<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/organization/positions.php'
?>

<div class="position-page" id="position-main">

  <div class="page-header">
    <h5 class="mb-1 fw-bold">직책관리</h5>
    <span id="positionCount" class="text-primary"></span>
  </div>

  <div class="content-area">

    <?php
    $searchId = 'position';

    $dateOptions = '
      <option value="created_at">등록일자</option>
      <option value="updated_at">수정일자</option>
    ';

    $searchFieldOptions = '
      <option value="">선택</option>
      <option value="position_name">직책명</option>
      <option value="sort_no">순번</option>
      <option value="level_rank">레벨</option>
      <option value="description">설명</option>
      <option value="is_active">상태</option>
      <option value="created_by">생성자</option>
      <option value="updated_by">수정자</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    $tableId       = 'position-table';
    $ajaxUrl       = '/api/settings/organization/position/list';
    $columnsType   = 'position';

    $enableButtons = true;
    $enableSearch  = true;
    $enablePaging  = true;
    $enableReorder = false;

    include PROJECT_ROOT . '/app/views/components/ui-table.php';
    ?>

  </div>
</div>

<?php include __DIR__ . '/partials/positions_modal.php'; ?>

<div class="picker-root">
  <div id="today-picker" class="picker is-hidden"></div>
</div>
