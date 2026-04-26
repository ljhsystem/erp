<div class="modal fade"
     id="journalModal"
     tabindex="-1"
     aria-labelledby="journalModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl journal-voucher-dialog">
        <div class="modal-content">
            <form id="journal-edit-form" class="journal-modal-form" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="journalModalLabel">전표 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body journal-modal-body">
                    <input type="hidden" name="id" id="journal_id">

                    <section class="journal-section journal-voucher-header" aria-label="전표개요">
                        <div class="journal-section-head">
                            <strong class="journal-section-title">1. 전표개요</strong>
                        </div>

                        <div class="journal-header-grid">
                            <div class="journal-form-field">
                                <label class="form-label" for="voucher_date">전표일자</label>
                                <input type="text"
                                       class="form-control form-control-sm admin-date"
                                       name="voucher_date"
                                       id="voucher_date"
                                       placeholder="날짜 선택"
                                       autocomplete="off"
                                       readonly
                                       required>
                            </div>

                            <div class="journal-form-field">
                                <label class="form-label" for="voucher_status">상태</label>
                                <select class="form-select form-select-sm"
                                        name="status"
                                        id="voucher_status">
                                    <option value="draft">임시저장</option>
                                    <option value="posted">확정</option>
                                    <option value="locked">마감</option>
                                </select>
                            </div>

                            <div class="journal-form-field">
                                <label class="form-label" for="voucher_ref_type">타입</label>
                                <select class="form-select form-select-sm"
                                        name="ref_type"
                                        id="voucher_ref_type"
                                        data-code-group="REF_TYPE">
                                    <option value="">선택</option>
                                </select>
                            </div>

                            <div class="journal-form-field journal-summary-text-field">
                                <label class="form-label" for="voucher_summary_text">적요</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       name="summary_text"
                                       id="voucher_summary_text"
                                       placeholder="전표 요약을 입력하세요">
                            </div>
                        </div>

                        <div class="journal-note-grid" aria-label="비고와 메모">
                            <div class="journal-form-field">
                                <label class="form-label" for="voucher_note">비고</label>
                                <textarea class="form-control form-control-sm"
                                          name="note"
                                          id="voucher_note"
                                          rows="2"
                                          maxlength="255"
                                          placeholder="비고를 입력하세요"></textarea>
                            </div>

                            <div class="journal-form-field">
                                <label class="form-label" for="voucher_memo">메모</label>
                                <textarea class="form-control form-control-sm"
                                          name="memo"
                                          id="voucher_memo"
                                          rows="2"
                                          placeholder="메모를 입력하세요"></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="journal-section journal-lines-panel" aria-label="분개라인">
                        <div class="journal-lines-toolbar">
                            <strong class="journal-section-title">2. 분개라인</strong>
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    id="btnAddVoucherLine">라인 추가</button>
                        </div>

                        <div class="journal-lines-wrap">
                            <div class="table-responsive journal-lines-table-wrap">
                                <table class="table table-bordered align-middle mb-0" id="voucher-line-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="64">순번</th>
                                            <th width="280">계정과목</th>
                                            <th width="150">차변</th>
                                            <th width="150">대변</th>
                                            <th>라인 적요</th>
                                            <th width="82">삭제</th>
                                        </tr>
                                    </thead>
                                    <tbody id="voucher-line-body">
                                        <tr class="voucher-line-empty">
                                            <td colspan="6" class="text-center text-muted py-4">
                                                분개라인을 추가해 주세요.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="journal-summary" aria-label="합계">
                            <div class="journal-form-field journal-summary-item">
                                <label class="form-label" for="voucher_debit_total">차변 합계</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="voucher_debit_total"
                                       value="0"
                                       readonly>
                            </div>

                            <div class="journal-form-field journal-summary-item">
                                <label class="form-label" for="voucher_credit_total">대변 합계</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="voucher_credit_total"
                                       value="0"
                                       readonly>
                            </div>

                            <div class="journal-form-field journal-summary-item journal-summary-item--status">
                                <label class="form-label" for="voucher_balance_status">검증 상태</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="voucher_balance_status"
                                       value="차변/대변 합계를 확인해 주세요."
                                       readonly>
                            </div>
                        </div>
                    </section>

                    <section class="journal-section journal-transaction-panel" aria-label="거래연결">
                        <div class="journal-section-head">
                            <strong class="journal-section-title">3. 거래연결</strong>
                        </div>

                        <div class="journal-transaction-link">
                            <input type="hidden"
                                   name="linked_transaction_id"
                                   id="linked_transaction_id">
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    id="btnSelectTransaction">거래 선택</button>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    id="btnClearTransactionLink">연결 해제</button>
                            <div class="journal-transaction-summary"
                                 id="linked_transaction_summary">연결된 거래가 없습니다.</div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success btn-sm" id="btnSaveVoucher">저장</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>

                <div id="journal-today-picker" class="is-hidden"></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade"
     id="journalTransactionSearchModal"
     tabindex="-1"
     aria-labelledby="journalTransactionSearchModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="journalTransactionSearchModalLabel">거래 선택</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <div class="journal-transaction-search">
                    <input type="search"
                           class="form-control form-control-sm"
                           id="journal_transaction_search_keyword"
                           placeholder="거래일자, 거래처, 금액, 적요로 검색">
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            id="btnSearchTransaction">검색</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0 journal-transaction-search-table">
                        <thead class="table-light">
                            <tr>
                                <th width="120">거래일자</th>
                                <th width="180">거래처</th>
                                <th>적요</th>
                                <th width="140">금액</th>
                                <th width="90">선택</th>
                            </tr>
                        </thead>
                        <tbody id="journal_transaction_search_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    거래를 검색해 주세요.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
