<div class="modal fade" id="voucherSelectModal" tabindex="-1" aria-labelledby="voucherSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable voucher-select-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="voucherSelectModalLabel">전표 선택</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>

            <div class="modal-body voucher-select-body">
                <form class="voucher-select-filter" id="voucherSelectFilterForm">
                    <label class="voucher-select-field">
                        <span>기간</span>
                        <div class="voucher-select-date-range">
                            <input type="date" class="form-control form-control-sm" name="date_from" id="voucherSelectDateFrom">
                            <input type="date" class="form-control form-control-sm" name="date_to" id="voucherSelectDateTo">
                        </div>
                    </label>

                    <label class="voucher-select-field">
                        <span>상태</span>
                        <select class="form-select form-select-sm" name="status" id="voucherSelectStatus">
                            <option value="">전체</option>
                            <option value="draft">작성중</option>
                            <option value="confirmed">확정</option>
                        </select>
                    </label>

                    <label class="voucher-select-field">
                        <span>거래처</span>
                        <input type="text" class="form-control form-control-sm" name="client_id" id="voucherSelectClientId" placeholder="거래처 ID">
                    </label>

                    <label class="voucher-select-field">
                        <span>금액</span>
                        <div class="voucher-select-amount-range">
                            <input type="number" class="form-control form-control-sm" name="min_amount" id="voucherSelectMinAmount" placeholder="최소">
                            <input type="number" class="form-control form-control-sm" name="max_amount" id="voucherSelectMaxAmount" placeholder="최대">
                        </div>
                    </label>

                    <label class="voucher-select-field voucher-select-keyword-field">
                        <span>키워드</span>
                        <input type="search" class="form-control form-control-sm" name="keyword" id="voucherSelectKeyword" placeholder="전표번호 / 거래처 / 적요" autocomplete="off">
                    </label>

                    <div class="voucher-select-filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm">조회</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVoucherSelectReset">초기화</button>
                    </div>
                </form>

                <div class="voucher-select-layout">
                    <div class="voucher-select-list-wrap">
                        <table class="table table-sm table-hover align-middle voucher-select-table" id="voucherSelectTable">
                            <thead>
                                <tr>
                                    <th>전표번호</th>
                                    <th>전표일자</th>
                                    <th>거래처</th>
                                    <th>적요</th>
                                    <th class="text-end">금액</th>
                                    <th>상태</th>
                                </tr>
                            </thead>
                            <tbody id="voucherSelectTableBody"></tbody>
                        </table>
                    </div>

                    <aside class="voucher-select-detail">
                        <div class="voucher-select-detail-title">전표 라인</div>
                        <div class="voucher-select-detail-summary" id="voucherSelectDetailSummary">전표를 선택해 주세요.</div>
                        <div class="voucher-select-lines" id="voucherSelectLines"></div>
                    </aside>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnConfirmVoucherSelection" disabled>선택</button>
            </div>
        </div>
    </div>
</div>
