<?php

use Core\Helpers\AssetHelper;

$pageTitle = $pageTitle ?? '결제정보';
$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => false,
    'wrapper' => 'single',
];
$pageStyles = AssetHelper::css('/assets/css/pages/funds/payment-info/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/funds/payment-info/index.js');
?>

<main class="funds-payment-info-page" id="fundsPaymentInfoPage">
    <div class="container-fluid py-3">
        <div class="page-header mb-3 d-flex justify-content-between align-items-start">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-credit-card-2-front me-2"></i>결제정보
                    <span id="fundsPaymentInfoCount" class="text-primary page-count"></span>
                </h5>
                <div class="small text-muted mt-1">
                    전표에 등록된 결제수단을 기준으로 계좌/카드, 입출금 방향, 금액, 전표 연결 정보를 조회합니다.
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="/ledger/funds/account-transactions" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-bank me-1"></i>계좌별거래내역
                </a>
                <a href="/ledger/vouchers/input" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-journal-text me-1"></i>전표입력
                </a>
            </div>
        </div>

        <section class="payment-summary-grid mb-3" aria-label="결제정보 요약">
            <div class="payment-summary-card">
                <span>전체 결제건수</span>
                <strong data-payment-summary="payment_count">0건</strong>
            </div>
            <div class="payment-summary-card">
                <span>입금 합계</span>
                <strong data-payment-summary="in_total">0</strong>
            </div>
            <div class="payment-summary-card">
                <span>출금 합계</span>
                <strong data-payment-summary="out_total">0</strong>
            </div>
            <div class="payment-summary-card">
                <span>은행입출금 매칭</span>
                <strong>준비중</strong>
            </div>
        </section>

        <div class="content-area">
            <?php
            $searchId = 'fundsPaymentInfo';
            $dateOptions = '
                <option value="voucher_date">전표일자</option>
                <option value="created_at">결제등록일시</option>
            ';
            $searchFieldOptions = '
                <option value="">전체</option>
                <option value="payment_name">결제수단</option>
                <option value="voucher_no">전표번호</option>
                <option value="summary_text">전표적요</option>
                <option value="payment_direction">입출금</option>
                <option value="payment_type">결제유형</option>
            ';
            $periodGuideTitle = '결제정보 기간 조건 안내';
            $periodGuideItems = [
                '전표일자 또는 결제등록일시 기준으로 전표에 등록된 결제수단을 조회합니다.',
                '은행 입출금 원본은 계좌별거래내역에서 별도로 관리하고, 이 화면은 전표 결제정보를 기준으로 조회합니다.',
            ];
            $searchGuideTitle = '결제정보 검색 조건 안내';
            $searchGuideItems = [
                '결제수단, 전표번호, 전표적요, 입출금, 결제유형 조건으로 조회할 수 있습니다.',
                '결제정보와 은행 입출금의 직접 매칭 상태는 추후 매칭 테이블이 추가되면 이 화면에 반영합니다.',
            ];
            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            ?>

            <section class="payment-filter-panel mb-3" aria-label="결제정보 상세 필터">
                <div class="payment-filter-grid">
                    <label>
                        <span>입출금</span>
                        <select class="form-select form-select-sm" id="paymentFilterDirection">
                            <option value="">전체</option>
                            <option value="IN">입금</option>
                            <option value="OUT">출금</option>
                        </select>
                    </label>
                    <label>
                        <span>결제유형</span>
                        <select class="form-select form-select-sm" id="paymentFilterType">
                            <option value="">전체</option>
                            <option value="ACCOUNT">계좌</option>
                            <option value="CARD">카드</option>
                        </select>
                    </label>
                    <label>
                        <span>결제수단</span>
                        <input type="search" class="form-control form-control-sm" id="paymentFilterName" placeholder="계좌명, 은행명, 카드명">
                    </label>
                    <label>
                        <span>전표번호</span>
                        <input type="search" class="form-control form-control-sm" id="paymentFilterVoucherNo" placeholder="전표번호">
                    </label>
                    <label>
                        <span>전표적요</span>
                        <input type="search" class="form-control form-control-sm" id="paymentFilterSummary" placeholder="전표적요">
                    </label>
                </div>
                <div class="payment-filter-actions">
                    <button type="button" class="btn btn-primary btn-sm" id="paymentFilterApplyBtn">상세필터 적용</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="paymentFilterResetBtn">상세필터 초기화</button>
                </div>
            </section>

            <?php
            $tableId = 'fundsPaymentInfoTable';
            $ajaxUrl = '/api/funds/payment-info';
            $columnsType = 'fundsPaymentInfo';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = false;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>
