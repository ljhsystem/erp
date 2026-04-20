<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/partials/permissions_modal.php'
?>

<div class="modal fade" id="permissionEditModal" tabindex="-1" aria-labelledby="permissionEditModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="permission-edit-form" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="permissionEditModalLabel">권한 등록</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="permission_edit_id">

          <div class="mb-3">
            <label class="form-label" for="permission_edit_name">퍼미션명</label>
            <input type="text" name="permission_name" id="permission_edit_name" class="form-control form-control-sm" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="permission_edit_category">카테고리</label>
            <input type="text" name="category" id="permission_edit_category" class="form-control form-control-sm">
          </div>

          <div class="mb-3">
            <label class="form-label" for="permission_edit_key">퍼미션키</label>
            <input type="text" name="permission_key" id="permission_edit_key" class="form-control form-control-sm" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="permission_edit_description">설명</label>
            <textarea name="description"
                      id="permission_edit_description"
                      class="form-control form-control-sm"
                      rows="3"
                      placeholder="권한 설명을 입력하세요"></textarea>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="permission_edit_is_active" name="is_active" checked>
            <label class="form-check-label" for="permission_edit_is_active">활성</label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" id="permission_edit_save_btn" class="btn btn-primary btn-sm">저장</button>
          <button type="button" id="permission_edit_delete_btn" class="btn btn-danger btn-sm">영구삭제</button>
          <button type="button" id="permission_edit_close_btn" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
        </div>
      </div>
    </form>
  </div>
</div>
