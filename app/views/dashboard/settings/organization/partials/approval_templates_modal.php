<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/approval_templates_modal_edit.php';
?>

<div class="modal fade" id="modal-template-edit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">템플릿 수정</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="tpl-edit-id">

                <div class="mb-3">
                    <label>템플릿 이름</label>
                    <input type="text" id="tpl-edit-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label>문서 유형</label>
                    <input type="text" id="tpl-edit-doc-type" class="form-control">
                </div>

                <div class="mb-3">
                    <label>설명</label>
                    <textarea id="tpl-edit-desc" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="tpl-edit-active">
                    <label class="form-check-label" for="tpl-edit-active">활성</label>
                </div>

            </div>

            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button id="btn-save-template-edit" class="btn btn-primary me-1">저장</button>
                    <button id="btn-delete-template-edit" class="btn btn-danger">삭제</button>
                </div>
                <div>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>

        </div>
    </div>
</div>
