<?php
use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '전표검토·전기';

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
    <div class="container-fluid py-4 voucher-review-shell dt-page-shell">
        <div class="page-header">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-check2-square me-2"></i>전표검토·전기
            </h5>
            <span id="voucherReviewCount" class="text-primary page-count"></span>
        </div>

        <?php
        $searchId = 'voucherReview';

        $dateOptions = '
            <option value="voucher_date">전표일자</option>
            <option value="updated_at">수정일시</option>
        ';

        $searchFieldOptions = '
            <option value="">선택</option>
        ';

        $periodGuideTitle = '전표 검토 기간 조건 안내';
        $periodGuideItems = [
            '전표일자 또는 수정일시를 기준으로 조회 기간을 지정합니다.',
            '빠른 기간 버튼이나 직접 입력으로 조회 범위를 조정할 수 있습니다.',
        ];

        $searchGuideTitle = '전표 검토 검색 조건 안내';
        $searchGuideItems = [
            '전표번호, 전표상태, 적요, 금액 기준으로 전표를 검색할 수 있습니다.',
            '검토·전기 흐름은 실제 status 컬럼 값으로 조회합니다.',
        ];

        include PROJECT_ROOT . '/app/views/components/ui-search.php';
        ?>

        <div class="voucher-review-layout">
            <section class="voucher-review-list-panel">
                <div class="voucher-review-table-wrap">
                    <?php
                    $tableId = 'voucherReviewTable';
                    $ajaxUrl = '/api/ledger/voucher/list';
                    $columnsType = 'voucher-header';
                    $tableClass = 'table table-bordered align-middle table-cross-highlight voucher-review-table';
                    $enableButtons = true;
                    $enableSearch = true;
                    $enablePaging = true;
                    $enableReorder = false;
                    include PROJECT_ROOT . '/app/views/components/ui-table.php';
                    ?>
                </div>
            </section>

            <aside class="voucher-review-detail-panel">
                <div class="voucher-review-detail-head">
                    <div>
                        <div class="voucher-review-detail-title" id="voucherReviewDetailTitle">전표를 선택해 주세요</div>
                        <div class="voucher-review-detail-sub" id="voucherReviewDetailSub">목록에서 전표를 클릭하면 상세 정보가 표시됩니다.</div>
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
                    <h6>연결정보</h6>
                    <div class="voucher-review-linked" id="voucherReviewLinkedInfo">연결 정보를 불러오지 않았습니다.</div>
                </div>

                <div class="voucher-review-actions">
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="rejectBtn">반려</button>
                    <button type="button" class="btn btn-primary btn-sm d-none" id="confirmBtn">검토완료</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="cancelConfirmBtn">검토완료 취소</button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="approveBtn">전기</button>
                    <button type="button" class="btn btn-outline-dark btn-sm d-none" id="reverseBtn">취소전표 작성</button>
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
                <textarea
                    class="form-control"
                    id="voucherRejectReason"
                    rows="5"
                    required
                    placeholder="입력자가 확인할 수 있도록 반려 사유를 입력해 주세요."
                ></textarea>
                <div class="invalid-feedback d-block d-none" id="voucherRejectReasonError">반려 사유를 입력해 주세요.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmRejectVoucher">반려 처리</button>
            </div>
        </div>
    </div>
</div>
