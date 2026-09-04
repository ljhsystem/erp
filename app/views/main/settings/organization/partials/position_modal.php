<?php
// 경로: PROJECT_ROOT . '/app/views/main/settings/organization/partials/position_modal.php'
?>

<div class="modal fade" id="positionEditModal" tabindex="-1" aria-labelledby="positionEditModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="position-edit-form" autocomplete="off">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="positionEditModalLabel">직책 등록</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="position_edit_id">

          <div class="mb-3">
            <label class="form-label" for="position_edit_name">직책명</label>
            <input type="text" name="position_name" id="position_edit_name" class="form-control form-control-sm" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="position_edit_rank">레벨</label>
            <input type="number" name="level_rank" id="position_edit_rank" class="form-control form-control-sm" step="1">
          </div>

          <div class="mb-3">
            <label class="form-label" for="position_edit_description">설명</label>
            <textarea name="description"
                      id="position_edit_description"
                      class="form-control form-control-sm"
                      rows="3"
                      placeholder="직책 설명을 입력하세요."></textarea>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="position_edit_is_active" name="is_active" checked>
            <label class="form-check-label" for="position_edit_is_active">활성</label>
          </div>

          <section class="ui-form-card position-system-card mt-3" aria-label="시스템 처리 정보">
            <button type="button" class="ui-form-card__toggle collapsed"
                    data-ui-modal-card-collapse data-bs-target="#positionSystemInfoCollapse"
                    aria-expanded="false" aria-controls="positionSystemInfoCollapse">
              <span class="ui-form-card__title">시스템 처리 정보</span>
              <i class="bi bi-chevron-down ui-form-card__toggle-icon" aria-hidden="true"></i>
            </button>
            <div id="positionSystemInfoCollapse" class="collapse">
              <div class="ui-form-card__body position-system-info-grid" id="positionSystemInfoFields"></div>
            </div>
          </section>
        </div>

        <div class="modal-footer">
          <button type="button" id="position_edit_delete_btn" class="btn btn-danger btn-sm" style="display:none;">영구삭제</button>
          <button type="submit" id="position_edit_save_btn" class="btn btn-success btn-sm">저장</button>
          <button type="button" id="position_edit_close_btn" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
        </div>
      </div>
    </form>
  </div>
</div>
