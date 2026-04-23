<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/permissions.php'
?>

<div class="permission-page" id="permission-main">

  <div class="page-header">
    <h5 class="mb-1 fw-bold">권한관리</h5>
    <span id="permissionCount" class="text-primary"></span>
  </div>

  <div class="content-area">

    <?php
    $searchId = 'permission';

    $dateOptions = '
      <option value="created_at">등록일자</option>
      <option value="updated_at">수정일자</option>
    ';

    $searchFieldOptions = '
      <option value="">선택</option>
      <option value="permission_name">퍼미션명</option>
      <option value="category">카테고리</option>
      <option value="permission_key">퍼미션키</option>
      <option value="sort_no">순번</option>
      <option value="description">설명</option>
      <option value="is_active">상태</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    $tableId       = 'permission-table';
    $ajaxUrl       = '/api/settings/organization/permission/list';
    $columnsType   = 'permission';

    $enableButtons = true;
    $enableSearch  = true;
    $enablePaging  = true;
    $enableReorder = false;

    include PROJECT_ROOT . '/app/views/components/ui-table.php';
    ?>

  </div>
</div>

<?php include __DIR__ . '/partials/permissions_modal.php'; ?>

<div class="picker-root">
  <div id="today-picker" class="picker is-hidden"></div>
</div>
