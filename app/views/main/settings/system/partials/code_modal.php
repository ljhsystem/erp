<?php
// Path: PROJECT_ROOT . '/app/views/main/settings/system/partials/code_modal.php'
?>

<div class="modal fade" id="codeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="codeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="codeModalLabel">기준정보</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body code-modal-body">
                    <input type="hidden" name="id" id="modal_code_id">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">코드그룹값 <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="code_group" id="modal_code_group" required>
                                <option value="">선택</option>
                            </select>
                            <div class="input-group input-group-sm mt-1 d-none" id="modal_code_group_input_wrap">
                                <input type="text"
                                       class="form-control form-control-sm text-uppercase"
                                       id="modal_code_group_input"
                                       placeholder="신규 코드그룹 직접 입력">
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        id="btnBackCodeGroupSelect"
                                        title="목록으로 돌아가기"
                                        aria-label="목록으로 돌아가기">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">그룹명 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="group_name" id="modal_code_group_name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">코드값 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" name="code" id="modal_code_code" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">코드명 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="code_name" id="modal_code_code_name" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">추가 속성(JSON)</label>
                            <textarea class="form-control form-control-sm" name="extra_data" id="modal_code_extra_data" rows="4"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">비고</label>
                            <input type="text" class="form-control form-control-sm" name="note" id="modal_code_note">
                        </div>

                        <div class="col-12">
                            <label class="form-label">메모</label>
                            <textarea class="form-control form-control-sm" name="memo" id="modal_code_memo" rows="3"></textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <div class="form-check form-switch code-status-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       name="is_active" id="modal_code_is_active" value="1" checked>
                                <label class="form-check-label" for="modal_code_is_active">사용 상태</label>
                            </div>
                        </div>
                    </div>

                    <section class="ui-form-card code-system-card mt-3" aria-label="시스템 처리 정보">
                        <button type="button" class="ui-form-card__toggle collapsed"
                                data-ui-modal-card-collapse data-bs-target="#codeSystemInfoCollapse"
                                aria-expanded="false" aria-controls="codeSystemInfoCollapse">
                            <span class="ui-form-card__title">시스템 처리 정보</span>
                            <i class="bi bi-chevron-down ui-form-card__toggle-icon" aria-hidden="true"></i>
                        </button>
                        <div id="codeSystemInfoCollapse" class="collapse">
                            <div class="ui-form-card__body code-system-info-grid" id="codeSystemInfoFields"></div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer justify-content-end">
                    <span id="codeReferenceSummary" class="me-auto small text-muted"></span>
                    <button type="button" id="btnDeleteCode" class="btn btn-danger btn-sm" style="display:none;">영구삭제</button>
                    <button type="submit" id="btnSaveCode" name="code_save" class="btn btn-success btn-sm">저장</button>
                    <button type="button" id="btnCloseCode" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="codeQuickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="codeQuickForm">
                <div class="modal-header">
                    <h5 class="modal-title">기준정보 빠른 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">코드그룹 <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="code_group">
                                <option value="">선택</option>
                            </select>
                            <div class="input-group input-group-sm mt-1 d-none" data-role="quick-code-group-input-wrap">
                                <input type="text"
                                       class="form-control form-control-sm text-uppercase"
                                       data-role="quick-code-group-input"
                                       placeholder="신규 코드그룹 직접 입력">
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        data-role="quick-code-group-back"
                                        title="목록으로 돌아가기"
                                        aria-label="목록으로 돌아가기">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">그룹명 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="group_name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">코드 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" name="code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">코드명 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="code_name" required>
                        </div>
                    </div>
                    <div class="small text-danger mt-2" data-role="message"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-role="detail">상세정보</button>
                    <button type="submit" class="btn btn-success btn-sm">저장</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
