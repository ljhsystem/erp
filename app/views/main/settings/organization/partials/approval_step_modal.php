<?php
// Path: PROJECT_ROOT . '/app/views/main/settings/organization/partials/approval_step_modal.php'
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
                    <label class="form-label" for="step-edit-name">단계명 <span class="text-danger">*</span></label>
                    <input type="text" id="step-edit-name" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="step-edit-type">단계유형 <span class="text-danger">*</span></label>
                    <select id="step-edit-type" class="form-select">
                        <option value="SUBMIT">발의</option>
                        <option value="APPROVAL">승인</option>
                        <option value="FINAL_APPROVAL">최종승인</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="step-edit-role">결재 역할</label>
                    <select id="step-edit-role" class="form-select"></select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="step-edit-user">특정 결재자</label>
                    <select id="step-edit-user" class="form-select"></select>
                </div>

                <div class="form-check form-switch approval-status-switch">
                    <input class="form-check-input" type="checkbox" id="step-edit-active">
                    <label class="form-check-label" for="step-edit-active">활성</label>
                </div>

                <section class="ui-form-card mt-3" id="step-system-info-card" aria-label="시스템 처리 정보">
                    <button type="button" class="ui-form-card__toggle collapsed"
                            data-ui-modal-card-collapse data-bs-target="#stepSystemInfoCollapse"
                            aria-expanded="false" aria-controls="stepSystemInfoCollapse">
                        <span class="ui-form-card__title">시스템 처리 정보</span>
                        <i class="bi bi-chevron-down ui-form-card__toggle-icon" aria-hidden="true"></i>
                    </button>
                    <div id="stepSystemInfoCollapse" class="collapse">
                        <div class="ui-form-card__body">
                            <div class="row g-3 small">
                                <div class="col-8"><span class="text-muted d-block">ID</span><span id="step-info-id"></span></div>
                                <div class="col-4"><span class="text-muted d-block">순번</span><span id="step-info-sort-no"></span></div>
                                <div class="col-12"><span class="text-muted d-block">Template ID</span><span id="step-info-template-id"></span></div>
                                <div class="col-6"><span class="text-muted d-block">생성일시</span><span id="step-info-created-at"></span></div>
                                <div class="col-6"><span class="text-muted d-block">생성자</span><span id="step-info-created-by"></span></div>
                                <div class="col-6"><span class="text-muted d-block">수정일시</span><span id="step-info-updated-at"></span></div>
                                <div class="col-6"><span class="text-muted d-block">수정자</span><span id="step-info-updated-by"></span></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer">
                <button id="btn-delete-step-edit" type="button" class="btn btn-danger btn-sm" style="display:none;">영구 삭제</button>
                <button id="btn-save-step-edit" type="button" class="btn btn-success btn-sm">저장</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>

        </div>
    </div>
</div>
