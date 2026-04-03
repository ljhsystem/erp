<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_approval_step_modal_edit.php';
?>

<div class="modal fade" id="modal-step-edit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">단계 수정</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="step-edit-id">

                <div class="mb-3">
                    <label>단계 이름</label>
                    <input type="text" id="step-edit-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label>결재자 역할</label>
                    <select id="step-edit-role" class="form-select"></select>
                </div>

                <div class="mb-3">
                    <label>특정 결재자(선택)</label>
                    <select id="step-edit-user" class="form-select"></select>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="step-edit-active">
                    <label class="form-check-label" for="step-edit-active">활성</label>
                </div>

            </div>

            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button id="btn-save-step-edit" class="btn btn-primary me-1">저장</button>
                    <button id="btn-delete-step-edit" class="btn btn-danger">삭제</button>
                </div>
                <div>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>

        </div>
    </div>
</div>
