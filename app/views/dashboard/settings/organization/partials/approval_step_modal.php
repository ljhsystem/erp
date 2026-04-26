<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/partials/approval_step_modal.php'
?>

<div class="modal fade" id="modal-step-edit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">단계 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="step-edit-id">

                <div class="mb-3">
                    <label class="form-label">단계 이름 <span class="text-danger">*</span></label>
                    <input type="text" id="step-edit-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">결재자 역할</label>
                    <select id="step-edit-role" class="form-select"></select>
                </div>

                <div class="mb-3">
                    <label class="form-label">특정 결재자</label>
                    <select id="step-edit-user" class="form-select"></select>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="step-edit-active">
                    <label class="form-check-label" for="step-edit-active">활성</label>
                </div>
            </div>

            <div class="modal-footer">
                <button id="btn-delete-step-edit" type="button" class="btn btn-danger btn-sm" style="display:none;">영구삭제</button>
                <button id="btn-save-step-edit" type="button" class="btn btn-success btn-sm">저장</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>

        </div>
    </div>
</div>
