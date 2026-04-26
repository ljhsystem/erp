<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/partials/approval_templates_modal.php'
?>

<div class="modal fade" id="modal-template-edit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">템플릿 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="tpl-edit-id">

                <div class="mb-3">
                    <label class="form-label">템플릿 이름 <span class="text-danger">*</span></label>
                    <input type="text" id="tpl-edit-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">문서 유형 <span class="text-danger">*</span></label>
                    <input type="text" id="tpl-edit-doc-type" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">설명</label>
                    <textarea id="tpl-edit-desc" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="tpl-edit-active">
                    <label class="form-check-label" for="tpl-edit-active">활성</label>
                </div>
            </div>

            <div class="modal-footer">
                <button id="btn-delete-template-edit" type="button" class="btn btn-danger btn-sm" style="display:none;">영구삭제</button>
                <button id="btn-save-template-edit" type="button" class="btn btn-success btn-sm">저장</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>

        </div>
    </div>
</div>
