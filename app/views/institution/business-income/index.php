<?php

use Core\Helpers\AssetHelper;

$pageStyles = AssetHelper::css('/assets/css/components/html-grid.css')
    . AssetHelper::css('/assets/css/components/income-calculation-cards.css')
    . AssetHelper::css('/assets/css/pages/institution/business-income/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/business-income/index.js');
?>
<main class="business-income-page" data-page="business-income">
    <div class="container-fluid py-4 dt-page-shell">
        <div class="page-header d-flex align-items-center gap-2 mb-3"><h4 class="mb-0 fw-bold">사업소득</h4><span id="businessIncomeCount" class="text-primary"></span></div>
        <div class="dt-content-stack">
            <?php
            $searchId='businessIncome';
            $dateOptions='';
            $searchFieldOptions='<option value="income_year_month">귀속연월</option><option value="title">제목</option><option value="document_status">업무문서 상태</option><option value="calculation_status">계산 상태</option><option value="approval_status">결재 상태</option>';
            include PROJECT_ROOT.'/app/views/components/ui-search.php';
            $tableId='business-income-table';$ajaxUrl='/api/institution/income-data/business-income/list';$columnsType='businessIncome';$enableButtons=true;$enableSearch=true;$enablePaging=true;$enableReorder=false;
            include PROJECT_ROOT.'/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>
<div class="modal fade" id="businessIncomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" id="businessIncomeForm">
        <div class="modal-header"><h2 class="modal-title fs-5">사업소득</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div>
        <div class="modal-body"><input type="hidden" name="id">
            <section class="ui-form-card income-document-card"><div class="ui-form-card__body"><div class="income-document-fields">
                <div class="income-document-field income-document-field--month" data-table-settings-field="income_year_month"><label class="form-label" for="businessIncomeYearMonthDisplay" data-column-label="income_year_month">귀속연월</label><input type="hidden" name="income_year_month"><div class="date-input-wrap"><input class="form-control" id="businessIncomeYearMonthDisplay" required inputmode="numeric" maxlength="7" autocomplete="off" placeholder="YYYY-MM"><span class="date-icon" id="businessIncomeYearMonthButton" role="button" tabindex="0" aria-label="귀속연월 달력 열기"><i class="bi bi-calendar3" aria-hidden="true"></i></span></div><div id="businessIncomeYearMonthPicker" class="picker is-hidden" aria-hidden="true"></div></div>
                <div class="income-document-field income-document-field--month" data-table-settings-field="withholding_date"><label class="form-label" for="businessIncomeWithholdingDate" data-column-label="withholding_date">원천징수일</label><div class="date-input-wrap"><input class="form-control" id="businessIncomeWithholdingDate" name="withholding_date" required inputmode="numeric" maxlength="10" autocomplete="off" placeholder="YYYY-MM-DD" aria-label="원천징수일 입력"><span class="date-icon" id="businessIncomeWithholdingDateButton" role="button" tabindex="0" aria-label="원천징수일 달력 열기"><i class="bi bi-calendar3" aria-hidden="true"></i></span></div></div>
                <div class="income-document-field income-document-field--title" data-table-settings-field="title"><label class="form-label" data-column-label="title">제목</label><input class="form-control" name="title" required></div>
                <div class="income-document-field income-document-field--description" data-table-settings-field="description"><label class="form-label" data-column-label="description">비고</label><input class="form-control" name="description"></div>
                <div class="income-document-field income-document-field--memo" data-table-settings-field="memo"><label class="form-label" data-column-label="memo">메모</label><input class="form-control" name="memo"></div>
            </div></div></section>
            <div id="businessIncomeCalculationGuidance" class="alert alert-warning income-calculation-guidance mt-3 mb-3 d-none" role="status" aria-live="polite"></div>
            <div class="d-flex justify-content-between align-items-center my-3"><h6 class="mb-0">소득그룹별 지급내역</h6><div class="d-flex gap-2"><button type="button" class="btn btn-outline-success btn-sm" id="businessIncomeExcelManager"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> 엑셀관리</button><button type="button" class="btn btn-outline-primary btn-sm" id="businessIncomeAddGroup"><i class="bi bi-plus-lg" aria-hidden="true"></i> 소득그룹 추가</button></div></div>
            <div class="business-income-entry-layout"><div id="businessIncomeGroups"></div><aside id="businessIncomeRecipientResult" class="business-income-recipient-result" aria-live="polite"><h6>선택 사업소득자 계산 결과</h6><div class="business-income-result-empty">소득자 카드를 선택해 주세요.</div></aside></div>
            <section class="business-income-document-summary mt-3" id="businessIncomePreview" aria-live="polite"></section>
            <section class="ui-form-card business-income-system-card" id="businessIncomeSystemCard" aria-label="시스템 처리 정보"><button type="button" class="ui-form-card__toggle collapsed" id="businessIncomeSystemToggle" data-ui-modal-card-collapse data-bs-target="#businessIncomeSystemInfoCollapse" aria-expanded="false" aria-controls="businessIncomeSystemInfoCollapse"><span class="ui-form-card__title">시스템 처리 정보</span><i class="bi bi-chevron-down ui-form-card__toggle-icon" aria-hidden="true"></i></button><div id="businessIncomeSystemInfoCollapse" class="collapse"><div class="ui-form-card__body business-income-system-info-grid" id="businessIncomeSystemInfo"></div></div></section>
            <div id="businessIncomeDatePicker" class="picker is-hidden" aria-hidden="true"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-danger me-auto" id="businessIncomeDelete">삭제</button><button type="button" class="btn btn-outline-secondary" id="businessIncomeWithdraw">회수</button><button type="button" class="btn btn-primary" id="businessIncomeSubmit">결재요청</button><button type="submit" class="btn btn-success">저장</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button></div>
    </form></div>
</div>
<?php
$modalId='businessIncomeTrashModal';$type='business-income';$modalTitle='사업소득 휴지통';$tableId='business-income-trash-table';
$tableHead='<th>귀속연월</th><th>제목</th><th>업무문서 상태</th><th>삭제일시</th><th>관리</th>';$emptyMessage='휴지통에 사업소득 문서가 없습니다.';
$purgeConfirm='영구삭제하면 사업소득 문서와 계산 Revision을 복구할 수 없습니다. 계속하시겠습니까?';
$listUrl='/api/institution/income-data/business-income/trash';$restoreUrl='/api/institution/income-data/business-income/restore';$deleteUrl='/api/institution/income-data/business-income/purge';
include PROJECT_ROOT.'/app/views/components/ui-modal-trash.php';

$modalId='businessIncomeExcelModal';$formId='business-income-excel-form';$modalTitle='사업소득 엑셀관리';
$modalSubtitle='업로드 자료는 검증 Preview 후 현재 문서의 소득그룹과 소득자 지급내역에만 반영됩니다.';
$fileInputId='businessIncomeExcelFile';$spinnerId='businessIncomeExcelSpinner';$btnTemplateId='businessIncomeExcelTemplate';
$btnDownloadAll='businessIncomeExcelDownload';$uploadBtnId='businessIncomeExcelUpload';
$templateUrl='/api/institution/income-data/business-income/template';$downloadUrl='/api/institution/income-data/business-income/excel';
$uploadUrl='/api/institution/income-data/business-income/excel-upload-preview';
include PROJECT_ROOT.'/app/views/components/ui-modal-excel.php';
?>
