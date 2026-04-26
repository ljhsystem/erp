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

                <div class="modal-body">
                    <input type="hidden" name="id" id="modal_account_id">
                    <input type="hidden" name="sub_policies" id="modal_sub_policies" value="[]">
                    <input type="hidden" name="allow_sub_account" id="modal_allow_sub_account" value="0">

                    <ul class="nav nav-tabs mb-3" id="accountModalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active"
                                    id="account-basic-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#account-basic-pane"
                                    type="button"
                                    role="tab">
                                기본정보
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link"
                                    id="account-policy-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#account-policy-pane"
                                    type="button"
                                    role="tab">
                                보조계정 정책
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="accountModalTabContent">
                        <div class="tab-pane fade show active" id="account-basic-pane" role="tabpanel">
                            <div class="card mb-3">
                                <div class="card-header py-1 px-2">기본정보</div>
                                <div class="card-body py-2">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <label class="form-label" for="modal_sort_no">순번</label>
                                            <input type="text"
                                                   name="sort_no"
                                                   id="modal_sort_no"
                                                   class="form-control form-control-sm"
                                                   placeholder="자동 생성"
                                                   readonly>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label" for="modal_account_code">계정코드 <span class="text-danger">*</span></label>
                                            <input type="text"
                                                   name="account_code"
                                                   id="modal_account_code"
                                                   class="form-control form-control-sm"
                                                   required>
                                        </div>

                                        <div class="col-md-6">
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
                                            <label class="form-label" for="modal_parent_name">상위계정</label>
                                            <div class="input-group input-group-sm">
                                                <input type="hidden" name="parent_id" id="modal_parent_id">
                                                <input type="text"
                                                       id="modal_parent_name"
                                                       class="form-control"
                                                       placeholder="상위계정 선택"
                                                       readonly>
                                                <button type="button" class="btn btn-outline-secondary" id="btnSelectParent">선택</button>
                                                <button type="button" class="btn btn-outline-danger" id="btnClearParent">해제</button>
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

                                        <div class="col-md-2">
                                            <label class="form-label" for="modal_allow_sub_account_label">보조계정</label>
                                            <input type="text"
                                                   id="modal_allow_sub_account_label"
                                                   class="form-control form-control-sm"
                                                   value="미사용"
                                                   readonly>
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

                        <div class="tab-pane fade" id="account-policy-pane" role="tabpanel">
                            <div class="card mb-3">
                                <div class="card-header py-1 px-2 d-flex justify-content-between align-items-center">
                                    <span>보조계정 정책 목록</span>
                                    <button type="button"
                                            id="btnAddSubPolicy"
                                            class="btn btn-sm btn-outline-primary">
                                        정책 추가
                                    </button>
                                </div>
                                <div class="card-body py-2">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0" id="sub-policy-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 26%;">유형</th>
                                                    <th style="width: 18%;">필수</th>
                                                    <th style="width: 18%;">다중선택</th>
                                                    <th>Custom 그룹코드</th>
                                                    <th style="width: 90px;">관리</th>
                                                </tr>
                                            </thead>
                                            <tbody id="sub-policy-tbody">
                                                <tr class="sub-policy-empty">
                                                    <td colspan="5" class="text-center text-muted">등록된 보조계정 정책이 없습니다.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="form-text mt-2">
                                        partner: 거래처, project: 프로젝트, custom: 별도 그룹코드 기준 보조계정입니다.
                                    </div>
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
