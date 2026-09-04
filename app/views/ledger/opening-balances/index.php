<?php

use Core\Helpers\AssetHelper;

$layoutOptions = ['header' => true, 'navbar' => true, 'sidebar' => true, 'footer' => true, 'wrapper' => 'single'];
$pageStyles = AssetHelper::css('/assets/css/pages/ledger/opening-balances/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/opening-balances/index.js');
?>
<main id="openingBalancePage" class="container-fluid py-4 dt-page-shell">
    <div class="page-header d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-1 fw-bold"><i class="bi bi-cash-stack me-2"></i>기초금액</h5>
            <div class="text-muted small">회계연도 시작 전 계정별 잔액을 기초전표로 관리합니다.</div>
        </div>
    </div>

    <div class="content-area">
        <?php
        $tableId = 'opening-balance-table';
        $ajaxUrl = '/api/ledger/opening-balance/list';
        $columnsType = 'openingBalance';
        $enableButtons = true;
        $enableSearch = false;
        $enablePaging = true;
        $enableReorder = false;
        include PROJECT_ROOT . '/app/views/components/ui-table.php';
        ?>
    </div>
</main>

<div class="modal fade" id="openingBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">기초금액 작성</h5>
                    <div id="openingBalanceStatus" class="small text-muted mt-1">작성</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="openingBalanceId">
                <section class="card mb-3">
                    <div class="card-header">문서정보</div>
                    <div class="card-body row g-3">
                        <div class="col-md-4"><label class="form-label">회사 <span class="text-danger">*</span></label><select id="openingCompany" class="form-select"></select></div>
                        <div class="col-md-3"><label class="form-label">회계연도 <span class="text-danger">*</span></label><input id="openingYear" type="number" min="1900" max="9999" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">기초잔액 기준일</label><input id="openingDate" class="form-control" readonly></div>
                        <div class="col-12"><label class="form-label">비고</label><input id="openingNote" maxlength="500" class="form-control"></div>
                    </div>
                </section>
                <section class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>계정별 기초잔액</span><button type="button" id="btnAddOpeningLine" class="btn btn-outline-primary btn-sm">+ 행 추가</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 opening-lines">
                            <thead><tr><th class="line-no">순번</th><th>계정과목 <span class="text-danger">*</span></th><th>적요</th><th class="amount">차변</th><th class="amount">대변</th><th class="manage">관리</th></tr></thead>
                            <tbody id="openingLines"></tbody>
                            <tfoot><tr><th colspan="3" class="text-end">합계 / 차이</th><th id="openingDebitTotal" class="text-end">0원</th><th id="openingCreditTotal" class="text-end">0원</th><th id="openingDifference" class="text-end">0원</th></tr></tfoot>
                        </table>
                    </div>
                </section>
                <div class="alert alert-warning mt-3 mb-0 small">차변과 대변 합계가 같아야 저장할 수 있습니다. 전기된 기초금액은 장부에 반영됩니다.</div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" id="btnDeleteOpening" class="btn btn-outline-danger">삭제</button>
                <div class="d-flex gap-2">
                    <button type="button" id="btnOpeningTransition" class="btn btn-outline-primary d-none"></button>
                    <button type="button" id="btnSaveOpening" class="btn btn-success">저장</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                </div>
            </div>
        </div>
    </div>
</div>
