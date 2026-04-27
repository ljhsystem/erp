<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php'
?>

<div class="modal fade" id="codeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="codeForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="codeModalLabel">기준정보</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body code-modal-body">
                    <input type="hidden" name="id" id="modal_code_id">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">코드그룹 <span class="text-danger">*</span></label>
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
                                        title="목록으로 되돌아가기"
                                        aria-label="목록으로 되돌아가기">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">코드 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" name="code" id="modal_code_code" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">코드명 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="code_name" id="modal_code_code_name" required>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">상태</label>
                            <select class="form-select form-select-sm" name="is_active" id="modal_code_is_active">
                                <option value="1">사용</option>
                                <option value="0">미사용</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">추가 속성(JSON)</label>
                            <textarea class="form-control form-control-sm" name="extra_data" id="modal_code_extra_data" rows="4"></textarea>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">비고</label>
                            <input type="text" class="form-control form-control-sm" name="note" id="modal_code_note">
                        </div>

                        <div class="col-12">
                            <label class="form-label">메모</label>
                            <textarea class="form-control form-control-sm" name="memo" id="modal_code_memo" rows="3"></textarea>
                        </div>


                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" id="btnDeleteCode" class="btn btn-danger btn-sm" style="display:none;">삭제</button>
                    <button type="submit" id="btnSaveCode" name="code_save" class="btn btn-success btn-sm">저장</button>
                    <button type="button" id="btnCloseCode" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
