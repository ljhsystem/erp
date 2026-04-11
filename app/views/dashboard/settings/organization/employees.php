<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/organization/employees.php'
?>

<div class="employee-page" id="employee-main" data-flash="<?= htmlspecialchars($flashMsg ?? '', ENT_QUOTES, 'UTF-8') ?>">

  <!-- =========================
       HEADER
  ========================== -->
  <div class="page-header">
    <h5 class="mb-1 fw-bold">👤 직원관리</h5>
    <span id="employeeCount" class="text-primary"></span>
  </div>

  <div class="content-area">

    <?php
    /* =========================================================
       🔥 공통 검색폼
    ========================================================= */

    $searchId = 'employee';

    $dateOptions = '
    <option value="user_created_at">등록일자</option>
    <option value="last_login">마지막 로그인</option>
    <option value="real_hire_date">입사일</option>
    <option value="real_retire_date">퇴사일</option>
    <option value="deleted_at">비활성화일</option>
    '; 

    $searchFieldOptions = '
      <option value="">선택</option>
      <option value="employee_name">직원명</option>
      <option value="username">아이디</option>
      <option value="department_name">부서</option>
      <option value="position_name">직책</option>
    ';

    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>

    <?php
    /* =========================================================
       🔥 공통 테이블
    ========================================================= */

    $tableId       = 'employee-table';
    $ajaxUrl       = '/api/settings/organization/employee/list';
    $columnsType   = 'employee';

    $enableButtons = true;
    $enableSearch  = true;
    $enablePaging  = true;
    $enableReorder = true; // 필요없으면 false

    include PROJECT_ROOT . '/app/views/components/ui-table.php';
    ?>

  </div>
</div>


<?php
/* =========================================================
   🔥 직원 모달
========================================================= */
include __DIR__ . '/partials/employee_modal.php';
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