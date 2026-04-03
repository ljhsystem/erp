<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/partials/organization_approval_templates_modal_create.php';
?>

<div class="modal fade" id="modal-template-create" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">템플릿 생성</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label>템플릿 이름</label>
                    <input type="text" id="tpl-create-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label>문서 유형</label>
                    <input type="text" id="tpl-create-doc-type" class="form-control">
                </div>

                <div class="mb-3">
                    <label>설명</label>
                    <textarea id="tpl-create-desc" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="tpl-create-active" checked>
                    <label class="form-check-label" for="tpl-create-active">활성</label>
                </div>
            </div>

            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <!-- 왼쪽은 비워둠 -->
                </div>
                <div>
                    <button id="btn-save-template-create" class="btn btn-primary">저장</button>
                    <button class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>

        </div>
    </div>
</div>
