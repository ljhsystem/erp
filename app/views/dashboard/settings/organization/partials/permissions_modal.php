<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/permissions_modal_edit.php';
?>
<div class="modal fade" id="permissionEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="permission-edit-form">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">권한 수정</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <!-- ID -->
          <input type="hidden" name="id" id="permission_edit_id">

          <!-- 권한 Key -->
          <div class="mb-3">
            <label class="form-label">권한 Key</label>
            <input type="text" name="permission_key" id="permission_edit_key" class="form-control" required>
          </div>

          <!-- 권한명 -->
          <div class="mb-3">
            <label class="form-label">권한명</label>
            <input type="text" name="permission_name" id="permission_edit_name" class="form-control" required>
          </div>

          <!-- 카테고리 -->
          <div class="mb-3">
            <label class="form-label">카테고리</label>
            <input type="text" name="category" id="permission_edit_category" class="form-control">
          </div>

          <!-- 설명 -->
          <div class="mb-3">
            <label class="form-label">설명</label>
            <textarea name="description" id="permission_edit_desc" class="form-control" rows="3"></textarea>
          </div>

          <!-- 활성 여부 -->
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="permission_edit_is_active" name="is_active">
            <label class="form-check-label" for="permission_edit_is_active">활성</label>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">저장</button>
          <button type="button" id="permission_edit_delete_btn" class="btn btn-danger me-auto">삭제</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
        </div>

      </div>
    </form>
  </div>
</div>
