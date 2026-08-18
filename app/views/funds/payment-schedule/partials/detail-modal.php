<div class="modal fade" id="paymentScheduleDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">지급예정 상세</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div>
        <div class="modal-body">
            <div class="payment-schedule-detail-grid" id="paymentScheduleDetail"></div>
            <section class="payment-schedule-detail-section">
                <div class="section-heading"><h6>실제 지급 연결</h6><small>입출금(은행) 출금 원본을 잔여액 범위에서 배분합니다.</small></div>
                <form class="payment-allocation-form" id="paymentAllocationForm">
                    <input type="hidden" name="schedule_id">
                    <select class="form-select" name="evidence_id" required><option value="">출금 원본을 검색해 선택하세요.</option></select>
                    <input class="form-control" type="number" min="0.01" step="0.01" name="amount" placeholder="연결금액" required>
                    <input class="form-control" name="memo" placeholder="연결 메모">
                    <button class="btn btn-primary" type="submit">연결</button>
                </form>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>거래일시</th><th>계좌</th><th>거래내용</th><th class="text-end">출금액</th><th class="text-end">배분액</th><th>연결일시</th><th>관리</th></tr></thead><tbody id="paymentAllocationRows"></tbody></table></div>
            </section>
            <section class="payment-schedule-detail-section"><div class="section-heading"><h6>업무이력</h6></div><div class="payment-history" id="paymentScheduleHistory"></div></section>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-warning" id="paymentScheduleHold" type="button">보류/해제</button>
            <button class="btn btn-outline-primary" id="paymentScheduleEdit" type="button">수정</button>
            <button class="btn btn-outline-danger" id="paymentScheduleDelete" type="button">삭제</button>
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">닫기</button>
        </div>
    </div></div>
</div>
