<div class="modal fade"
     id="accountModal"
     tabindex="-1"
     aria-labelledby="accountModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-lg">
        <div class="modal-content account-modal-content">
            <form id="account-edit-form" method="post">

                <div class="modal-header">
                    <h5 class="modal-title" id="accountModalLabel">계정과목 등록 / 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body account-modal-body">
                    <input type="hidden" name="id" id="modal_account_id">
                            <div class="card mb-3">
                                <div class="card-header py-1 px-2">기본정보</div>
                                <div class="card-body py-2">
                                    <div class="row g-2">


                                        <div class="col-md-4">
                                            <label class="form-label" for="modal_account_code">계정코드 <span class="text-danger">*</span></label>
                                            <input type="text"
                                                   name="account_code"
                                                   id="modal_account_code"
                                                   class="form-control form-control-sm"
                                                   required>
                                        </div>

                                        <div class="col-md-8">
                                            <label class="form-label" for="modal_account_name">계정과목명 <span class="text-danger">*</span></label>
                                            <input type="text"
                                                   name="account_name"
                                                   id="modal_account_name"
                                                   class="form-control form-control-sm"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header py-1 px-2">계정분류</div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-md-5">
                                            <label class="form-label" for="modal_parent_id">상위계정</label>
                                            <select name="parent_id"
                                                    id="modal_parent_id"
                                                    class="form-select form-select-sm">
                                                <option value="">선택 안 함</option>
                                            </select>
                                            <div class="input-group input-group-sm mt-1 d-none" id="modal_parent_account_input_wrap">
                                                <input type="text"
                                                       class="form-control form-control-sm"
                                                       name="new_parent_code"
                                                       id="modal_new_parent_code"
                                                       placeholder="신규 상위계정 코드">
                                                <input type="text"
                                                       class="form-control form-control-sm"
                                                       name="new_parent_name"
                                                       id="modal_new_parent_name"
                                                       placeholder="신규 상위계정명">
                                                <button type="button"
                                                        class="btn btn-outline-secondary"
                                                        id="btnBackParentAccountSelect"
                                                        title="목록으로 되돌리기"
                                                        aria-label="목록으로 되돌리기">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label" for="modal_account_group">계정구분 <span class="text-danger">*</span></label>
                                            <select name="account_group"
                                                    id="modal_account_group"
                                                    class="form-select form-select-sm"
                                                    required>
                                                <option value="">선택</option>
                                                <option value="자산">자산</option>
                                                <option value="부채">부채</option>
                                                <option value="자본">자본</option>
                                                <option value="수익">수익</option>
                                                <option value="비용">비용</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="modal_normal_balance">정상잔액</label>
                                            <select name="normal_balance"
                                                    id="modal_normal_balance"
                                                    class="form-select form-select-sm">
                                                <option value="debit">차변</option>
                                                <option value="credit">대변</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label" for="allowSubAccount">보조계정</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <select name="allow_sub_account"
                                                        id="modal_allow_sub_account"
                                                        class="form-select form-select-sm">
                                                    <option value="0">미사용</option>
                                                    <option value="1">사용</option>
                                                </select>

                                                <button type="button"
                                                        id="btnSubAccountManage"
                                                        class="btn btn-outline-primary btn-sm text-nowrap d-none">
                                                    보조계정 관리
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header py-1 px-2">입력설정</div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <label class="form-label" for="modal_is_posting">전표입력</label>
                                            <select name="is_posting"
                                                    id="modal_is_posting"
                                                    class="form-select form-select-sm">
                                                <option value="1">가능</option>
                                                <option value="0">불가</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label" for="modal_is_active">상태</label>
                                            <select name="is_active"
                                                    id="modal_is_active"
                                                    class="form-select form-select-sm">
                                                <option value="1">사용</option>
                                                <option value="0">미사용</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header py-1 px-2">비고 / 메모</div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label" for="modal_note">비고</label>
                                            <textarea name="note"
                                                      id="modal_note"
                                                      class="form-control form-control-sm"
                                                      rows="4"></textarea>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label" for="modal_memo">메모</label>
                                            <textarea name="memo"
                                                      id="modal_memo"
                                                      class="form-control form-control-sm"
                                                      rows="4"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                </div>

                <div class="modal-footer">
                    <button type="button" id="btnDeleteAccount" class="btn btn-danger btn-sm">삭제</button>
                    <button type="submit" class="btn btn-success btn-sm">저장</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
