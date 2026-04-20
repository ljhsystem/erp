<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/organization/partials/dept_modal.php'
?>

<div class="modal fade" id="deptEditModal" tabindex="-1" aria-labelledby="deptEditModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="dept-edit-form" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deptEditModalLabel">부서 등록</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="dept_edit_id">

          <div class="mb-3">
            <label class="form-label" for="dept_edit_name">부서명</label>
            <input type="text" name="dept_name" id="dept_edit_name" class="form-control form-control-sm" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="dept_edit_manager_id">부서장</label>
            <select name="manager_id" id="dept_edit_manager_id" class="form-select form-select-sm"></select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="dept_edit_description">설명</label>
            <textarea name="description"
                      id="dept_edit_description"
                      class="form-control form-control-sm"
                      rows="3"
                      placeholder="부서 설명을 입력하세요."></textarea>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="dept_edit_is_active" name="is_active" checked>
            <label class="form-check-label" for="dept_edit_is_active">활성</label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" id="dept_edit_save_btn" class="btn btn-primary btn-sm">&#51200;&#51109;</button>
          <button type="button" id="dept_edit_delete_btn" class="btn btn-danger btn-sm">&#50689;&#44396;&#49325;&#51228;</button>
          <button type="button" id="dept_edit_close_btn" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">&#45803;&#44592;</button>
        </div>
      </div>
    </form>
  </div>
</div>
