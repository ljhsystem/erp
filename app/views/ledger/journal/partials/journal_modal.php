<div class="modal fade"
     id="journalModal"
     tabindex="-1"
     aria-labelledby="journalModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form id="journal-edit-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="journalModalLabel">일반전표 등록 / 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="id" id="journal_id">
                    <input type="hidden" name="ref_id" id="voucher_ref_id">

                    <div class="card mb-3">
                        <div class="card-header bg-white fw-bold">전표 기본정보</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-2">
                                    <label class="form-label">전표일자</label>
                                    <input type="text"
                                           class="form-control form-control-sm admin-date"
                                           name="voucher_date"
                                           id="voucher_date"
                                           placeholder="날짜 선택"
                                           autocomplete="off"
                                           readonly
                                           required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">상태</label>
                                    <select class="form-select form-select-sm journal-picker-select" name="status" id="voucher_status">
                                        <option value="draft">임시저장</option>
                                        <option value="posted">확정</option>
                                        <option value="locked">마감</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">참조유형</label>
                                    <select class="form-select form-select-sm journal-picker-select" name="ref_type" id="voucher_ref_type">
                                        <option value="">선택</option>
                                        <option value="CLIENT">거래처</option>
                                        <option value="PROJECT">프로젝트</option>
                                        <option value="ACCOUNT">계좌</option>
                                        <option value="CARD">카드</option>
                                        <option value="EMPLOYEE">직원</option>
                                        <option value="ORDER">주문</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">적요</label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           name="summary_text"
                                           id="voucher_summary_text"
                                           placeholder="전표 요약을 입력하세요">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">비고</label>
                                    <textarea class="form-control form-control-sm"
                                              name="note"
                                              id="voucher_note"
                                              rows="3"
                                              placeholder="비고를 입력하세요"></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">메모</label>
                                    <textarea class="form-control form-control-sm"
                                              name="memo"
                                              id="voucher_memo"
                                              rows="3"
                                              placeholder="메모를 입력하세요"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <span class="fw-bold">분개 라인</span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddVoucherLine">라인 추가</button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0" id="voucher-line-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="70">순번</th>
                                            <th width="280">계정과목</th>
                                            <th width="160">차변</th>
                                            <th width="160">대변</th>
                                            <th>라인 적요</th>
                                            <th width="90">삭제</th>
                                        </tr>
                                    </thead>
                                    <tbody id="voucher-line-body">
                                        <tr class="voucher-line-empty">
                                            <td colspan="6" class="text-center text-muted py-4">분개 라인을 추가해주세요.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white fw-bold">전표 합계</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">차변 합계</label>
                                    <input type="text" class="form-control form-control-sm" id="voucher_debit_total" value="0.00" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">대변 합계</label>
                                    <input type="text" class="form-control form-control-sm" id="voucher_credit_total" value="0.00" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">검증 상태</label>
                                    <input type="text" class="form-control form-control-sm" id="voucher_balance_status" value="차변/대변 합계를 확인해주세요." readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="journal-today-picker" class="is-hidden"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteVoucher" style="display:none;">삭제</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btnSaveVoucher">저장</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
