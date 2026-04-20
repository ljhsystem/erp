<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/organization/departments.php'
?>

<div class="department-page" id="department-main">

  <div class="page-header">
    <h5 class="mb-1 fw-bold">부서관리</h5>
    <span id="departmentCount" class="text-primary"></span>
  </div>

  <div class="content-area">

    <?php
    $searchId = 'department';

    $dateOptions = '
      <option value="created_at">등록일자</option>
      <option value="updated_at">수정일자</option>
    ';

    $searchFieldOptions = '
      <option value="">선택</option>
      <option value="dept_name">부서명</option>
      <option value="code">코드</option>
      <option value="manager_name">부서장</option>
      <option value="description">설명</option>
      <option value="is_active">상태</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    $tableId       = 'department-table';
    $ajaxUrl       = '/api/settings/organization/department/list';
    $columnsType   = 'department';

    $enableButtons = true;
    $enableSearch  = true;
    $enablePaging  = true;
    $enableReorder = false;

    include PROJECT_ROOT . '/app/views/components/ui-table.php';
    ?>

  </div>
</div>

<?php include __DIR__ . '/partials/dept_modal.php'; ?>

<div class="picker-root">
  <div id="today-picker" class="picker is-hidden"></div>
</div>
