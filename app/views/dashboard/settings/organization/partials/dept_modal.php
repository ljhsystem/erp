<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/dept_modal_edit.php';
?>
<div class="modal fade" id="deptEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="dept-edit-form">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">부서 수정</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="dept_edit_id">

          <div class="mb-3">
            <label class="form-label">부서명</label>
            <input type="text" name="dept_name" id="dept_edit_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">부서장</label>
            <select name="manager_id" id="dept_edit_manager_id" class="form-control"></select>
          </div>

          <div class="mb-3">
            <label class="form-label">설명</label>
            <textarea name="description" id="dept_edit_description"
                      class="form-control" rows="2"
                      placeholder="부서 설명을 입력하세요."></textarea>
          </div>
          
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="dept_edit_is_active" name="is_active">
            <label class="form-check-label" for="dept_edit_is_active">활성</label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">저장</button>
          <button type="button" id="dept_edit_delete_btn" class="btn btn-danger me-auto">삭제</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
        </div>

      </div>
    </form>
  </div>
</div>
