<div class="modal fade" id="voucherRecommendationModal" tabindex="-1" aria-labelledby="voucherRecommendationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable voucher-recommendation-dialog">
        <div class="modal-content voucher-recommendation-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="voucherRecommendationModalLabel">추천분개검토</h5>
                    <p class="mb-0 small text-muted">거래 기반 추천 분개를 검토하고 수정한 뒤 draft 전표로 저장합니다.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <section class="voucher-recommendation-section">
                    <div class="voucher-recommendation-title">STEP 1. 거래 헤더 확인</div>
                    <div class="voucher-recommendation-header-grid">
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">거래처</span>
                            <input type="text" class="form-control form-control-sm" id="voucherRecommendationClientName" readonly>
                        </label>
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">프로젝트</span>
                            <input type="text" class="form-control form-control-sm" id="voucherRecommendationProjectName" readonly>
                        </label>
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">거래유형</span>
                            <input type="text" class="form-control form-control-sm" id="voucherRecommendationTransactionType" readonly>
                        </label>
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">거래방향</span>
                            <input type="text" class="form-control form-control-sm" id="voucherRecommendationTransactionDirection" readonly>
                        </label>
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">공급가</span>
                            <input type="text" class="form-control form-control-sm text-end" id="voucherRecommendationSupplyAmount" readonly>
                        </label>
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">부가세</span>
                            <input type="text" class="form-control form-control-sm text-end" id="voucherRecommendationVatAmount" readonly>
                        </label>
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">합계금액</span>
                            <input type="text" class="form-control form-control-sm text-end" id="voucherRecommendationTotalAmount" readonly>
                        </label>
                        <label class="voucher-recommendation-field">
                            <span class="voucher-recommendation-field-label">적요</span>
                            <input type="text" class="form-control form-control-sm" id="voucherRecommendationDescription">
                        </label>
                    </div>
                </section>

                <section class="voucher-recommendation-section">
                    <div class="voucher-recommendation-title">STEP 2. 추천 분개라인 검토</div>
                    <div class="voucher-recommendation-balance" id="voucherRecommendationBalanceText"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle voucher-recommendation-line-table">
                            <thead>
                            <tr>
                                <th>차대구분</th>
                                <th>계정과목</th>
                                <th>보조계정</th>
                                <th>거래처</th>
                                <th>프로젝트</th>
                                <th>금액</th>
                                <th>추천근거</th>
                                <th>신뢰도</th>
                                <th>관리</th>
                            </tr>
                            </thead>
                            <tbody id="voucherRecommendationLineBody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="voucherRecommendationAddLineBtn">+ 라인 추가</button>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success btn-sm" id="voucherRecommendationSaveBtn">draft 전표 저장</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>
