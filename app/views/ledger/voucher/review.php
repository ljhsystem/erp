<?php
use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '전표검토/승인';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/voucher-review.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/voucherReview.js');
?>

<main class="voucher-review-page" id="voucher-review-main">
    <div class="container-fluid py-4 voucher-review-shell">
        <div class="page-header">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-check2-square me-2"></i>전표검토/승인
            </h5>
            <span id="voucherReviewCount" class="text-primary page-count"></span>
        </div>

        <div class="voucher-review-layout">
            <section class="voucher-review-list-panel">
                <div class="voucher-review-toolbar">
                    <form class="voucher-review-filter" id="voucherReviewFilterForm">
                        <div class="voucher-review-filter-group voucher-review-filter-period">
                            <span class="voucher-review-filter-label">기간</span>
                            <input type="date" class="form-control form-control-sm" name="date_from" aria-label="시작일">
                            <span class="voucher-review-date-separator">~</span>
                            <input type="date" class="form-control form-control-sm" name="date_to" aria-label="종료일">
                        </div>
                        <div class="voucher-review-filter-group">
                            <span class="voucher-review-filter-label">전표상태</span>
                            <select class="form-select form-select-sm" name="status" aria-label="전표상태">
                                <option value="">전체</option>
                                <option value="draft">임시저장</option>
                                <option value="confirmed">검토요청</option>
                                <option value="reviewed">검토완료</option>
                                <option value="posted">승인</option>
                                <option value="closed">마감</option>
                            </select>
                        </div>
                        <div class="voucher-review-filter-group">
                            <span class="voucher-review-filter-label">검토상태</span>
                            <select class="form-select form-select-sm" name="review_status" aria-label="검토상태">
                                <option value="">전체</option>
                                <option value="error">오류</option>
                                <option value="pending">검토대기</option>
                                <option value="ready">검토완료</option>
                            </select>
                        </div>
                        <div class="voucher-review-filter-group voucher-review-filter-keyword">
                            <span class="voucher-review-filter-label">검색어</span>
                            <input type="search" class="form-control form-control-sm" name="keyword" placeholder="전표번호 / 거래처 / 적요" aria-label="키워드">
                        </div>
                        <div class="voucher-review-filter-actions">
                            <button type="submit" class="btn btn-primary btn-sm">조회</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetVoucherReview">초기화</button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive voucher-review-table-wrap">
                    <table class="table table-bordered table-hover align-middle voucher-review-table" id="voucherReviewTable"></table>
                </div>
            </section>

            <aside class="voucher-review-detail-panel">
                <div class="voucher-review-detail-head">
                    <div>
                        <div class="voucher-review-detail-title" id="voucherReviewDetailTitle">전표를 선택해 주세요.</div>
                        <div class="voucher-review-detail-sub" id="voucherReviewDetailSub">목록에서 전표를 클릭하면 상세가 표시됩니다.</div>
                    </div>
                    <span class="voucher-review-status-badge" id="voucherReviewDetailStatus">-</span>
                </div>

                <div class="voucher-review-detail-section">
                    <h6>기본정보</h6>
                    <dl class="voucher-review-info" id="voucherReviewBasicInfo"></dl>
                </div>

                <div class="voucher-review-detail-section">
                    <h6>전표라인</h6>
                    <div class="voucher-review-lines" id="voucherReviewLines"></div>
                </div>

                <div class="voucher-review-detail-section">
                    <h6>합계</h6>
                    <div class="voucher-review-total" id="voucherReviewTotal"></div>
                </div>

                <div class="voucher-review-detail-section">
                    <h6>거래 연결 정보</h6>
                    <div class="voucher-review-linked" id="voucherReviewLinkedInfo">연결 정보를 불러오지 않았습니다.</div>
                </div>

                <div class="voucher-review-actions">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="rejectBtn">반려</button>
                    <button type="button" class="btn btn-primary btn-sm d-none" id="confirmBtn">검토완료</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="cancelConfirmBtn">검토완료취소</button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="approveBtn">승인</button>
                    <button type="button" class="btn btn-outline-dark btn-sm d-none" id="reverseBtn">취소전표 생성</button>
                </div>
            </aside>
        </div>
    </div>
</main>

<div class="modal fade" id="voucherRejectModal" tabindex="-1" aria-labelledby="voucherRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="voucherRejectModalLabel">전표 반려</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="voucherRejectReason">반려 사유</label>
                <textarea class="form-control"
                          id="voucherRejectReason"
                          rows="5"
                          required
                          placeholder="입력자가 확인할 수 있도록 반려 사유를 입력해 주세요."></textarea>
                <div class="invalid-feedback d-block d-none" id="voucherRejectReasonError">반려 사유를 입력해 주세요.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmRejectVoucher">반려 처리</button>
            </div>
        </div>
    </div>
</div>
