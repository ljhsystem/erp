<div class="modal fade"
     id="journalModal"
     tabindex="-1"
     aria-labelledby="journalModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable journal-voucher-dialog">
        <div class="modal-content">
            <form id="journal-edit-form" class="journal-modal-form" autocomplete="off">
                <div class="modal-header">
                    <h5 class="modal-title" id="journalModalLabel">전표 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>

                <div class="modal-body journal-modal-body">
                    <input type="hidden" name="id" id="journal_id">
                    <input type="hidden" name="status" id="voucher_status" value="draft">

                    <section class="form-section journal-section journal-status-panel" aria-label="전표처리현황">
                        <div class="section-header journal-section-head">
                            <span class="section-title journal-section-title">전표처리현황</span>
                        </div>

                        <div class="section-body">
                            <div id="voucher_status_badge"
                                 class="voucher-status-timeline"
                                 aria-live="polite"></div>
                        </div>
                    </section>

                    <section class="form-section journal-section journal-voucher-header" aria-label="전표개요">
                        <div class="section-header journal-section-head">
                            <span class="section-title journal-section-title">전표개요</span>
                        </div>

                        <div class="section-body">
                        <div class="journal-header-grid">
                            <div class="journal-form-field journal-voucher-no-field">
                                <label class="form-label" for="voucher_no_display">전표코드</label>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="voucher_no_display"
                                       value="자동발번"
                                       readonly>
                            </div>

                            <div class="journal-form-field">
                                <label class="form-label" for="voucher_date">전표일자</label>
                                <div class="date-input">
                                    <input type="text"
                                           class="form-control form-control-sm admin-date"
                                           name="voucher_date"
                                           id="voucher_date"
                                           placeholder="날짜 선택"
                                           autocomplete="off"
                                           inputmode="numeric"
                                           maxlength="10"
                                           required>
                                    <i class="fa fa-calendar-days date-icon" aria-hidden="true"></i>
                                </div>
                            </div>

                            <div class="journal-form-field">
                                <input type="hidden" name="source_id" id="voucher_source_id">
                                <label class="form-label" for="voucher_source_type">자료출처</label>
                                <select class="form-select form-select-sm"
                                        name="source_type"
                                        id="voucher_source_type"
                                        data-code-group="SOURCE_TYPE"
                                        data-empty-option="false"
                                        required>
                                    <option value="MANUAL" selected>수기입력</option>
                                </select>
                            </div>

                            <div class="journal-form-field journal-summary-text-field">
                                <label class="form-label" for="voucher_summary_text">전표 적요</label>
                                <div class="summary-autocomplete-wrap">
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           name="summary_text"
                                           id="voucher_summary_text"
                                           placeholder="전표 적요를 입력하세요"
                                           autocomplete="off">
                                    <div id="voucher_summary_suggestions"
                                         class="summary-autocomplete-list d-none"
                                         role="listbox"></div>
                                </div>
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
                                            </div>
                    </section>

                    <section class="form-section journal-section journal-lines-panel" aria-label="분개라인">
                        <div class="section-header journal-lines-toolbar">
                            <span class="section-title journal-section-title">분개라인</span>
                        </div>

                        <div class="section-body">
                        <div class="journal-lines-wrap">
                            <div class="table-responsive journal-lines-table-wrap">
                                <table class="table table-bordered align-middle mb-0" id="voucher-line-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="64">순번</th>
                                            <th width="280">계정과목</th>
                                            <th width="260" class="line-ref-cell">보조계정</th>
                                            <th width="150">차변</th>
                                            <th width="150">대변</th>
                                            <th>라인 적요</th>
                                            <th width="64" class="journal-table-action-head">
                                                <button type="button"
                                                        class="btn btn-outline-primary btn-sm"
                                                        id="btnAddVoucherLine">라인추가</button>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="voucher-line-body">
                                        <tr class="voucher-line-empty">
                                            <td colspan="7" class="text-center text-muted py-4">
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
                                <div id="voucher_balance_status"
                                     class="voucher-validation-badge voucher-validation-error"
                                     aria-live="polite">차변/대변 합계를 확인해 주세요.</div>
                            </div>
                        </div>
                                            </div>
                    </section>

                    <section class="form-section journal-section journal-payment-panel" aria-label="결제">
                        <div class="section-header journal-lines-toolbar">
                            <span class="section-title journal-section-title">결제정보</span>
                        </div>

                        <div class="section-body">
                        <div class="journal-payments-wrap table-responsive">
                            <table class="table table-bordered align-middle mb-0" id="voucher-payment-table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="64">순번</th>
                                        <th width="160">결제유형</th>
                                        <th width="100" class="payment-direction-cell">입/출금</th>
                                        <th>결제수단</th>
                                        <th width="160">금액</th>
                                        <th width="64" class="journal-table-action-head">
                                            <button type="button"
                                                    class="btn btn-outline-primary btn-sm"
                                                    id="btnAddVoucherPayment">결제추가</button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="voucher-payment-body">
                                    <tr class="voucher-payment-empty">
                                        <td colspan="6" class="text-center text-muted py-3">
                                            결제수단이 필요한 경우 추가해주세요.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                                            </div>
                    </section>

                    <section class="form-section journal-section journal-transaction-panel" aria-label="거래연결">
                        <div class="section-header journal-section-head">
                            <span class="section-title journal-section-title">거래연결</span>
                        </div>

                        <div class="section-body">
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
                                            </div>
                    </section>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-danger btn-sm d-none"
                            id="btnDeleteVoucherInModal">삭제</button>
                    <button type="button"
                            class="btn btn-primary btn-sm d-none"
                            id="btnAdvanceVoucherStatus">확정</button>
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
                           placeholder="거래일자, 거래처, 금액, 거래 적요로 검색">
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
                                <th>거래 적요</th>
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
