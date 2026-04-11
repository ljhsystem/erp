<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/roles_modal_edit.php';
?>
<div class="modal fade" id="roleEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="role-edit-form">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">권한 수정</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="role_edit_id">

          <div class="mb-3">
            <label class="form-label">역할 키</label>
            <input type="text" name="role_key" id="role_edit_key" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">역할할명</label>
            <input type="text" name="role_name" id="role_edit_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">설명</label>
            <textarea name="description" id="role_edit_desc" class="form-control" rows="3"></textarea>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="role_edit_is_active" name="is_active">
            <label class="form-check-label" for="role_edit_is_active">활성</label>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">저장</button>
          <button type="button" id="role_edit_delete_btn" class="btn btn-danger me-auto">삭제</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
        </div>

      </div>
    </form>
  </div>
</div>
