<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_dept_modal_create.php';
?>
<div class="modal fade" id="deptCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="dept-create-form">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">새 부서 추가</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">부서명</label>
            <input type="text" name="dept_name" id="dept_create_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">부서장</label>
            <select name="manager_id" id="dept_create_manager_id" class="form-control"></select>
          </div>

          <div class="mb-3">
            <label class="form-label">설명</label>
            <textarea name="description" id="dept_create_description"
                      class="form-control" rows="2"
                      placeholder="부서에 대한 간단한 설명을 작성하세요."></textarea>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="dept_create_is_active" name="is_active" checked>
            <label class="form-check-label" for="dept_create_is_active">활성</label>
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
