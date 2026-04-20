<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/organization/partials/roles_modal.php'
?>

<div class="modal fade" id="roleEditModal" tabindex="-1" aria-labelledby="roleEditModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="role-edit-form" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="roleEditModalLabel">역할 등록</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="role_edit_id">

          <div class="mb-3">
            <label class="form-label" for="role_edit_key">Role Key</label>
            <input type="text" name="role_key" id="role_edit_key" class="form-control form-control-sm" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="role_edit_name">Role Name</label>
            <input type="text" name="role_name" id="role_edit_name" class="form-control form-control-sm" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="role_edit_description">설명</label>
            <textarea name="description"
                      id="role_edit_description"
                      class="form-control form-control-sm"
                      rows="3"
                      placeholder="역할 설명을 입력하세요."></textarea>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="role_edit_is_active" name="is_active" checked>
            <label class="form-check-label" for="role_edit_is_active">활성</label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" id="role_edit_save_btn" class="btn btn-primary btn-sm">&#51200;&#51109;</button>
          <button type="button" id="role_edit_delete_btn" class="btn btn-danger btn-sm">&#50689;&#44396;&#49325;&#51228;</button>
          <button type="button" id="role_edit_close_btn" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">&#45803;&#44592;</button>
        </div>
      </div>
    </form>
  </div>
</div>
