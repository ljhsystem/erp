<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_roles_modal_create.php';
?>
<div class="modal fade" id="roleCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="role-create-form">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">새 권한 추가</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">역할 키</label>
            <input type="text" name="role_key" id="role_create_key" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">역할할명</label>
            <input type="text" name="role_name" id="role_create_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">설명</label>
            <textarea name="description" id="role_create_desc" class="form-control" rows="3"></textarea>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="role_create_is_active" name="is_active" checked>
            <label class="form-check-label" for="role_create_is_active">활성</label>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">저장</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
        </div>

      </div>
    </form>
  </div>
</div>
