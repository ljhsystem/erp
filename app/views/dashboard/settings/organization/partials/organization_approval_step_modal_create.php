<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_approval_step_modal_create.php';
?>

<div class="modal fade" id="modal-step-create" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">단계 추가</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label>단계 이름</label>
                    <input type="text" id="step-create-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label>결재자 역할</label>
                    <select id="step-create-role" class="form-select"></select>
                </div>

                <div class="mb-3">
                    <label>특정 결재자(선택)</label>
                    <select id="step-create-user" class="form-select"></select>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="step-create-active" checked>
                    <label class="form-check-label" for="step-create-active">활성</label>
                </div>

            </div>

            <div class="modal-footer d-flex justify-content-between">
                <div></div>
                <div>
                    <button id="btn-save-step-create" class="btn btn-primary">저장</button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>

        </div>
    </div>
</div>
