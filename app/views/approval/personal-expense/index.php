<?php

use Core\Helpers\AssetHelper;

$pageStyles = AssetHelper::css('https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/styles/ag-grid.css')
    . AssetHelper::css('https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/styles/ag-theme-quartz.css')
    . AssetHelper::css('/assets/css/pages/ledger/transaction/modal.css')
    . AssetHelper::css('/assets/css/pages/ledger/transaction/grid.css')
    . AssetHelper::css('/assets/css/pages/ledger/transaction/select2.css')
    . AssetHelper::css('/assets/css/pages/approval/personal-expense.css');
$pageScripts = AssetHelper::js('https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/dist/ag-grid-community.min.js')
    . AssetHelper::module('/assets/js/pages/approval/personal-expense/index.js');
?>
<main class="personal-expense-page" data-page="personal-expense">
    <div class="container-fluid py-4 personal-expense-shell dt-page-shell">
        <div class="page-header">
            <h5 class="mb-0 fw-bold"><i class="bi bi-wallet2 me-2"></i>개인경비 신청</h5>
            <span id="personalExpenseCount" class="text-primary page-count"></span>
        </div>
        <div class="content-area">
            <?php
            $searchId = 'personalExpense';
            $dateOptions = '<option value="application_date">신청일자</option>';
            $searchFieldOptions = '<option value="keyword">신청제목·비고·메모</option>';
            $periodGuideTitle = '개인경비 조회 기간 안내';
            $periodGuideItems = ['신청일자를 기준으로 개인경비 신청서를 조회합니다.'];
            $searchGuideTitle = '개인경비 검색 안내';
            $searchGuideItems = ['신청제목, 비고, 메모를 검색합니다.'];
            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            $tableId = 'personal-expense-table';
            $tableClass = 'table table-bordered align-middle table-cross-highlight personal-expense-table';
            $ajaxUrl = '/api/approval/personal-expense/list';
            $columnsType = 'personal-expense';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = true;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>
<script type="application/json" id="personalExpenseCodeOptions"><?= json_encode([
    'expense_categories' => array_values($expenseCategories ?? []),
    'payment_methods' => array_values($paymentMethods ?? []),
    'receipt_types' => array_values($receiptTypes ?? []),
    'units' => array_values($units ?? []),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/json" id="personalExpenseCurrentEmployee"><?= json_encode([
    'id' => (string) ($currentEmployee['id'] ?? ''),
    'employee_name' => (string) ($currentEmployee['employee_name'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable expense-dialog">
        <div class="modal-content expense-modal-card">
            <form id="expenseForm" novalidate autocomplete="off">
                <div class="modal-header expense-modal-header">
                    <div>
                        <h5 class="modal-title mb-0">개인경비 신청서</h5>
                        <p class="expense-modal-subtitle mb-0">신청서 헤더와 여러 경비 아이템을 함께 저장합니다.</p>
                        <span id="expenseModalStatus" class="expense-modal-state"></span>
                    </div>
                    <button type="button" class="btn-close" data-expense-close aria-label="닫기"></button>
                </div>
                <div class="modal-body expense-modal-body">
                    <input type="hidden" name="id">
                    <section class="expense-form-section expense-approval-card" id="expenseApprovalInfo" aria-label="결재 진행정보">
                        <button type="button"
                                class="expense-card-toggle expense-approval-toggle"
                                aria-expanded="true"
                                aria-controls="expenseApprovalCollapse">
                            <span>
                                <span class="expense-card-toggle-title">결재 진행정보</span>
                                <span class="expense-card-toggle-description">결재 템플릿의 단계와 재상신 이력을 확인합니다.</span>
                            </span>
                            <i class="bi bi-chevron-down expense-card-toggle-icon" aria-hidden="true"></i>
                        </button>
                        <div id="expenseApprovalCollapse" class="collapse show">
                            <div class="expense-card-body">
                                <div id="expenseApprovalSteps" class="expense-approval-timeline-wrap"></div>
                                <div id="expenseApprovalRejection" class="expense-approval-rejection d-none"></div>
                                <details class="expense-approval-history">
                                    <summary>과거 상신 이력</summary>
                                    <div id="expenseApprovalHistory"></div>
                                </details>
                            </div>
                        </div>
                    </section>
                    <section class="expense-form-section expense-business-card">
                        <div class="expense-card-header"><div><h6>신청 헤더</h6><p>신청일자와 신청 내용을 입력합니다.</p></div></div>
                        <div class="expense-form-grid expense-basic-grid">
                            <label class="expense-field expense-date-field"><span class="form-label">신청일자 <span>*</span></span><span class="date-input"><input class="form-control form-control-sm admin-date" name="application_date" type="text" inputmode="numeric" placeholder="YYYY-MM-DD" maxlength="10" required><i class="fa fa-calendar-days date-icon" aria-hidden="true"></i></span></label>
                            <label class="expense-field expense-title-field"><span class="form-label">신청제목 <span>*</span></span><input class="form-control form-control-sm" name="title" maxlength="200" required></label>
                            <label class="expense-field expense-description-field"><span class="form-label">비고</span><input class="form-control form-control-sm" name="description" type="text" maxlength="500"></label>
                            <label class="expense-field expense-memo-field"><span class="form-label">메모</span><textarea class="form-control form-control-sm" name="memo" rows="3"></textarea></label>
                        </div>
                    </section>
                    <section class="expense-form-section expense-lines-section transaction-card transaction-lines-section">
                        <div class="expense-card-header transaction-card-header"><div class="transaction-card-heading"><h6 class="transaction-card-title">경비 아이템 <span class="text-danger">*</span></h6><p class="transaction-card-description">아이템은 신청서 내부 순서대로 저장되며 금액은 서버에서 다시 계산합니다.</p></div><div class="expense-grid-toolbar transaction-overview-header-actions"><button type="button" class="btn btn-outline-success btn-sm" id="expenseExcelManager"><i class="bi bi-file-earmark-spreadsheet me-1"></i>엑셀관리</button></div></div>
                        <div class="expense-item-grid-wrap"><div id="expenseItemGrid" class="expense-item-grid ag-theme-quartz" aria-label="개인경비 아이템 편집"></div></div>
                        <div id="expenseItemGridFeedback" class="expense-grid-feedback" role="alert"></div>
                        <div class="expense-summary transaction-lines-footer transaction-settlement-footer">
                            <span>아이템 <strong id="expenseItemCount">0</strong>건</span>
                            <span>공급가액 <strong id="expenseSupplyTotal">0</strong>원</span>
                            <span>부가세 <strong id="expenseVatTotal">0</strong>원</span>
                            <span>합계 <strong id="expenseTotal">0</strong>원</span>
                        </div>
                    </section>
                    <section class="expense-form-section expense-system-card" aria-label="시스템 처리 정보">
                        <button type="button"
                                class="expense-system-toggle collapsed"
                                aria-expanded="false"
                                aria-controls="expenseSystemInfoCollapse">
                            <span>
                                <span class="expense-system-title">시스템 처리 정보</span>
                                <span class="expense-system-description">신청 헤더 외 컬럼을 데이터베이스 순서대로 확인합니다.</span>
                            </span>
                            <i class="bi bi-chevron-down expense-system-icon" aria-hidden="true"></i>
                        </button>
                        <div id="expenseSystemInfoCollapse" class="collapse">
                            <div id="expenseSystemInfoFields" class="expense-form-grid expense-system-grid"></div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer expense-modal-footer">
                    <button type="button" class="btn btn-danger btn-sm d-none" id="expenseDelete">삭제</button>
                    <button type="button" class="btn btn-warning btn-sm d-none" id="expenseWithdraw">회수</button>
                    <button type="submit" class="btn btn-success btn-sm d-none" id="expenseSave">저장</button>
                    <button type="button" class="btn btn-primary btn-sm d-none" id="expenseSubmit">저장 후 결재요청</button>
                    <button type="button" class="btn btn-primary btn-sm d-none" id="expenseResubmit">재상신</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-expense-close>닫기</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="expense-date-picker" class="admin-picker is-hidden"></div>

<div class="modal fade" id="expenseAmountCalculatorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered expense-calculator-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">공급가액, 세액 자동계산</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body">
                <div class="expense-calculator-guide">
                    <p>· 합계를 입력하고 엔터키를 누르시면 공급가액, 세액이 자동계산됩니다. (공급가액, 세액 수정가능)</p>
                    <p>· 공급가액, 세액이 1원정도의 차이가 있을 수 있으므로 <span>계산된 금액</span>을 다시 한번 확인하시기 바랍니다.</p>
                </div>
                <p class="expense-calculator-unit">( 단위 : 원 )</p>
                <div class="expense-calculator-grid">
                    <label for="expenseCalculatorTotal">합계</label>
                    <div class="expense-calculator-total-input">
                        <input type="text" class="form-control form-control-sm text-end" id="expenseCalculatorTotal" inputmode="numeric">
                        <button type="button" id="expenseCalculatorRun">계산</button>
                    </div>
                    <label for="expenseCalculatorSupply">공급가액</label>
                    <input type="text" class="form-control form-control-sm text-end" id="expenseCalculatorSupply" inputmode="numeric">
                    <label for="expenseCalculatorVat">세액</label>
                    <input type="text" class="form-control form-control-sm text-end" id="expenseCalculatorVat" inputmode="numeric">
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary btn-sm" id="expenseCalculatorApply">확인</button>
            </div>
        </div>
    </div>
</div>

<?php
$modalId = 'personalExpenseTrashModal';
$type = 'personal-expense';
$modalTitle = '개인경비 신청 휴지통';
$tableId = 'personal-expense-trash-table';
$listUrl = '/api/approval/personal-expense/trash';
$restoreUrl = '/api/approval/personal-expense/restore';
$deleteUrl = '/api/approval/personal-expense/purge';
$tableHead = '<th>신청번호</th><th>신청일자</th><th>신청제목</th><th>신청자</th><th>아이템</th><th>총금액</th><th>삭제일시</th><th>관리</th>';
$emptyMessage = '휴지통에 개인경비 신청서가 없습니다.';
$purgeConfirm = '완전삭제하면 신청서와 모든 개인경비 아이템이 영구 삭제되며 복구할 수 없습니다. 완전삭제하시겠습니까?';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';

$modalId = 'personalExpenseExcelModal';
$formId = 'personal-expense-excel-upload-form';
$modalTitle = '개인경비 아이템 엑셀관리';
$modalSubtitle = '공용 엑셀관리 설정으로 아이템 양식·다운로드·업로드 컬럼을 관리합니다.';
$fileInputId = 'personalExpenseExcelFile';
$spinnerId = 'personalExpenseExcelSpinner';
$btnTemplateId = 'personalExpenseExcelTemplate';
$btnDownloadAll = 'personalExpenseExcelDownload';
$uploadBtnId = 'personalExpenseExcelUpload';
$templateUrl = '/api/approval/personal-expense/template';
$downloadUrl = '/api/approval/personal-expense/excel';
$uploadUrl = '/api/approval/personal-expense/excel-upload';
include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>
