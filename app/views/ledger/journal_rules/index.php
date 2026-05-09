<?php
use Core\Helpers\AssetHelper;

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/journal-rules.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/journalRules.js');
?>

<main class="journal-rules-main">
    <div class="journal-rules-page py-4">
        <div class="page-header">
            <h5 class="mb-1 fw-bold">
                <i class="bi bi-diagram-3 me-2"></i>분개규칙
            </h5>
            <span id="journalRuleCount" class="text-primary page-count"></span>
        </div>

        <div class="content-area">
            <?php
            $searchId = 'journalRule';
            $dateOptions = '
                <option value="created_at">생성일</option>
                <option value="updated_at">수정일</option>
            ';
            $searchFieldOptions = '
                <option value="rule_code">규칙코드</option>
                <option value="rule_name">규칙명</option>
                <option value="business_unit">사업구분</option>
                <option value="transaction_type">거래유형</option>
                <option value="transaction_direction">거래구분</option>
                <option value="client_type">거래처구분</option>
                <option value="import_type">자료유형</option>
                <option value="debit_account_name">차변계정</option>
                <option value="credit_account_name">대변계정</option>
                <option value="vat_account_name">부가세계정</option>
                <option value="is_active">사용여부</option>
                <option value="description">비고</option>
            ';
            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            ?>

            <?php
            $tableId = 'journal-rule-table';
            $ajaxUrl = '/api/ledger/journal-rules/list';
            $columnsType = 'journalRule';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = false;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>

    <div class="modal fade" id="journalRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <form class="modal-content" id="journalRuleForm">
                <div class="modal-header">
                    <h5 class="modal-title">분개규칙</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="journalRuleId">

                    <section class="journal-rule-section">
                        <h6 class="section-title">조건</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">규칙코드 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="rule_code" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">규칙명 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="rule_name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">사업구분 <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm js-business-unit"
                                        name="business_unit"
                                        data-code-group="BUSINESS_UNIT"
                                        required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">거래유형 <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm js-transaction-type"
                                        name="transaction_type"
                                        data-code-group="TRANSACTION_TYPE"
                                        required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">거래구분 <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm js-transaction-direction"
                                        name="transaction_direction"
                                        data-code-group="TRANSACTION_DIRECTION"
                                        required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">거래처구분 <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm js-client-type"
                                        name="client_type"
                                        data-code-group="CLIENT_TYPE"
                                        required></select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">자료유형 <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm js-import-type"
                                        name="import_type"
                                        data-code-group="IMPORT_TYPE"
                                        required></select>
                            </div>
                        </div>
                    </section>

                    <section class="journal-rule-section mt-3">
                        <h6 class="section-title">분개 결과</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">차변계정 <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm js-account-select" name="debit_account_id" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">대변계정 <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm js-account-select" name="credit_account_id" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">부가세계정</label>
                                <select class="form-select form-select-sm js-account-select" name="vat_account_id"></select>
                            </div>
                        </div>
                    </section>

                    <section class="journal-rule-section mt-3">
                        <h6 class="section-title">설정</h6>
                        <div class="row g-3">
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="journalRuleActive" value="1" checked>
                                    <label class="form-check-label" for="journalRuleActive">사용</label>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label">설명/적요</label>
                                <textarea class="form-control form-control-sm" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger btn-sm" id="journalRuleDeleteBtn">삭제</button>
                    <button type="submit" class="btn btn-primary btn-sm">저장</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                </div>
            </form>
        </div>
    </div>

    <?php include PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php'; ?>

    <template id="journal-account-modal-template">
        <?php include PROJECT_ROOT . '/app/views/ledger/account/partials/account_modal.php'; ?>
    </template>

    <?php
    $templateUrl = '/api/ledger/journal-rules/template';
    $downloadUrl = '/api/ledger/journal-rules/excel';
    $uploadUrl = '/api/ledger/journal-rules/excel-upload';

    $modalId = 'journalRuleExcelModal';
    $formId = 'journal-rule-excel-upload-form';
    $modalTitle = '분개규칙 엑셀관리';
    $fileInputId = 'journalRuleExcelUpload';
    $fileInputName = 'file';
    $spinnerId = 'journalRuleExcelUploadSpinner';
    $btnTemplateId = 'btnDownloadJournalRuleTemplate';
    $btnDownloadAll = 'btnDownloadAllJournalRules';
    $uploadBtnId = 'btnUploadJournalRuleExcel';
    include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
    ?>

    <?php
    $modalId = 'journalRuleTrashModal';
    $type = 'journalRule';
    $modalTitle = '분개규칙 휴지통';
    $tableId = 'journal-rule-trash-table';
    $checkAllId = 'journalRuleTrashCheckAll';
    $listUrl = '/api/ledger/journal-rules/trash';
    $restoreUrl = '/api/ledger/journal-rules/restore';
    $deleteUrl = '/api/ledger/journal-rules/purge';
    $deleteAllUrl = '/api/ledger/journal-rules/purge-all';
    $tableHead = '
        <th>규칙코드</th>
        <th>규칙명</th>
        <th>사업구분</th>
        <th>거래유형</th>
        <th>거래구분</th>
        <th>거래처구분</th>
        <th>자료유형</th>
        <th>삭제일시</th>
        <th width="150">관리</th>
    ';
    $emptyMessage = '휴지통의 분개규칙을 선택하면 상세 정보가 표시됩니다.';
    include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
    ?>
</main>
