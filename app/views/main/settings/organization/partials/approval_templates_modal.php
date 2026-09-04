<?php
// Path: PROJECT_ROOT . '/app/views/main/settings/organization/partials/approval_templates_modal.php'
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
                    <label class="form-label" for="tpl-edit-name">템플릿명 <span class="text-danger">*</span></label>
                    <input type="text" id="tpl-edit-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="tpl-edit-doc-type">문서 유형 <span class="text-danger">*</span></label>
                    <input type="text" id="tpl-edit-doc-type" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="tpl-edit-desc">설명</label>
                    <textarea id="tpl-edit-desc" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-check form-switch approval-status-switch">
                    <input class="form-check-input" type="checkbox" id="tpl-edit-active">
                    <label class="form-check-label" for="tpl-edit-active">활성</label>
                </div>

                <section class="ui-form-card mt-3" id="tpl-system-info-card" aria-label="시스템 처리 정보">
                    <button type="button" class="ui-form-card__toggle collapsed"
                            data-ui-modal-card-collapse data-bs-target="#tplSystemInfoCollapse"
                            aria-expanded="false" aria-controls="tplSystemInfoCollapse">
                        <span class="ui-form-card__title">시스템 처리 정보</span>
                        <i class="bi bi-chevron-down ui-form-card__toggle-icon" aria-hidden="true"></i>
                    </button>
                    <div id="tplSystemInfoCollapse" class="collapse">
                      <div class="ui-form-card__body">
                        <div class="row g-3 small">
                            <div class="col-8"><span class="text-muted d-block">ID</span><span id="tpl-info-id"></span></div>
                            <div class="col-4"><span class="text-muted d-block">순번</span><span id="tpl-info-sort-no"></span></div>
                            <div class="col-6"><span class="text-muted d-block">생성일시</span><span id="tpl-info-created-at"></span></div>
                            <div class="col-6"><span class="text-muted d-block">생성자</span><span id="tpl-info-created-by"></span></div>
                            <div class="col-6"><span class="text-muted d-block">수정일시</span><span id="tpl-info-updated-at"></span></div>
                            <div class="col-6"><span class="text-muted d-block">수정자</span><span id="tpl-info-updated-by"></span></div>
                        </div>
                      </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer">
                <button id="btn-delete-template-edit" type="button" class="btn btn-danger btn-sm" style="display:none;">영구 삭제</button>
                <button id="btn-save-template-edit" type="button" class="btn btn-success btn-sm">저장</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>

        </div>
    </div>
</div>
