<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '양식관리';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = '';
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataFormat.js');
?>

<main class="ledger-data-format-page" id="ledgerDataFormatPage">
    <div class="container-fluid py-4">
        <div class="page-header mb-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-table me-2"></i>양식관리
            </h5>
        </div>

        <div class="row g-3">
            <section class="col-12 col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold">양식 목록</span>
                            <button type="button" class="btn btn-primary btn-sm" id="newFormatBtn">새 양식 추가</button>
                        </div>
                        <select class="form-select form-select-sm" id="formatTypeFilter">
                            <option value="TAX_INVOICE">세금계산서</option>
                            <option value="CASH_RECEIPT">현금영수증</option>
                            <option value="CARD_PURCHASE">카드(매입)</option>
                            <option value="CARD_SALE">카드(매출)</option>
                            <option value="BANK">입출</option>
                            <option value="ETC">기타</option>
                        </select>
                    </div>
                    <div class="list-group list-group-flush" id="formatList"></div>
                </div>
            </section>

            <section class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">컬럼 매핑 설정</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="copyFormatBtn">복사</button>
                    </div>
                    <div class="card-body">
                        <input type="hidden" id="formatId">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="formatName">양식명</label>
                                <input type="text" class="form-control form-control-sm" id="formatName" placeholder="예: 홈택스 세금계산서">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="formatDataType">자료유형</label>
                                <select class="form-select form-select-sm" id="formatDataType">
                                    <option value="TAX_INVOICE">세금계산서</option>
                                    <option value="CASH_RECEIPT">현금영수증</option>
                                    <option value="CARD_PURCHASE">카드(매입)</option>
                                    <option value="CARD_SALE">카드(매출)</option>
                                    <option value="BANK">입출</option>
                                    <option value="ETC">기타</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="formatIsDefault">
                                    <label class="form-check-label" for="formatIsDefault">기본양식</label>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">순서</th>
                                    <th>엑셀 컬럼명</th>
                                    <th>시스템 필드명</th>
                                    <th style="width: 80px;">필수</th>
                                    <th style="width: 80px;">삭제</th>
                                </tr>
                                </thead>
                                <tbody id="formatColumnBody"></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="addColumnBtn">컬럼 추가</button>
                            <div>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="deleteFormatBtn">삭제</button>
                                <button type="button" class="btn btn-primary btn-sm" id="saveFormatBtn">저장</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<div class="modal fade" id="newFormatModal" tabindex="-1" aria-labelledby="newFormatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newFormatModalLabel">새 양식 추가</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="newFormatName">양식명</label>
                    <input type="text" class="form-control form-control-sm" id="newFormatName" placeholder="예: 카드사 매입 양식">
                </div>
                <div>
                    <label class="form-label" for="newFormatDataType">자료유형</label>
                    <select class="form-select form-select-sm" id="newFormatDataType">
                        <option value="TAX_INVOICE">세금계산서</option>
                        <option value="CASH_RECEIPT">현금영수증</option>
                        <option value="CARD_PURCHASE">카드(매입)</option>
                        <option value="CARD_SALE">카드(매출)</option>
                        <option value="BANK">입출</option>
                        <option value="ETC">기타</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmNewFormatBtn">추가</button>
            </div>
        </div>
    </div>
</div>
