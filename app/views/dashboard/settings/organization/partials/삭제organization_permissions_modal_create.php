<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_permissions_modal_create.php';
?>

<div class="modal fade" id="permissionCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="permission-create-form">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">새 권한 추가</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <!-- 권한 Key -->
          <div class="mb-3">
            <label class="form-label">권한 키</label>
            <input type="text" name="permission_key" id="permission_create_key" class="form-control" required>
          </div>

          <!-- 권한명 -->
          <div class="mb-3">
            <label class="form-label">권한명</label>
            <input type="text" name="permission_name" id="permission_create_name" class="form-control" required>
          </div>

          <!-- 카테고리 -->
          <div class="mb-3">
            <label class="form-label">카테고리</label>
            <input type="text" name="category" id="permission_create_category" class="form-control" placeholder="예: system, user, approval">
          </div>

          <!-- 설명 -->
          <div class="mb-3">
            <label class="form-label">설명</label>
            <textarea name="description" id="permission_create_desc" class="form-control" rows="3"></textarea>
          </div>

          <!-- 활성 여부 -->
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="permission_create_is_active" name="is_active" checked>
            <label class="form-check-label" for="permission_create_is_active">활성</label>
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
