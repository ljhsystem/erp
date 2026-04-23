<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/organization/roles.php'
?>

<div class="role-page" id="role-main">

  <div class="page-header">
    <h5 class="mb-1 fw-bold">역할관리</h5>
    <span id="roleCount" class="text-primary"></span>
  </div>

  <div class="content-area">

    <?php
    $searchId = 'role';

    $dateOptions = '
      <option value="created_at">등록일자</option>
      <option value="updated_at">수정일자</option>
    ';

    $searchFieldOptions = '
      <option value="">선택</option>
      <option value="role_key">Role Key</option>
      <option value="role_name">Role Name</option>
      <option value="sort_no">순번</option>
      <option value="description">설명</option>
      <option value="is_active">상태</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    $tableId       = 'role-table';
    $ajaxUrl       = '/api/settings/organization/role/list';
    $columnsType   = 'role';

    $enableButtons = true;
    $enableSearch  = true;
    $enablePaging  = true;
    $enableReorder = false;

    include PROJECT_ROOT . '/app/views/components/ui-table.php';
    ?>

  </div>
</div>

<?php include __DIR__ . '/partials/roles_modal.php'; ?>

<div class="picker-root">
  <div id="today-picker" class="picker is-hidden"></div>
</div>
