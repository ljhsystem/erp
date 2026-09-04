<?php

use Core\Helpers\AssetHelper;

$pageStyles = AssetHelper::css('/assets/css/components/html-grid.css')
    . AssetHelper::css('/assets/css/components/income-calculation-cards.css')
    . AssetHelper::css('/assets/css/pages/institution/daily-employment-income/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/institution/daily-employment-income/index.js');
?>
<main class="daily-income-page" data-page="daily-employment-income">
    <div class="container-fluid py-4 dt-page-shell">
        <div class="page-header d-flex align-items-center gap-2 mb-3"><h4 class="mb-0 fw-bold">일용근로소득</h4><span id="dailyIncomeCount" class="text-primary"></span></div>
        <div class="dt-content-stack">
            <?php
            $searchId = 'dailyIncome';
            $dateOptions = '';
            $searchFieldOptions = '<option value="income_year_month">귀속연월</option><option value="document_title">제목</option><option value="business_unit">사업구분</option><option value="project_id">프로젝트</option><option value="work_team_id">소속팀</option><option value="worker_client_id">작업자(거래처)</option><option value="status_code">문서상태</option><option value="approval_status">결재상태</option>';
            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            $tableId = 'daily-income-table';
            $ajaxUrl = '/api/institution/income-data/daily-employment/list';
            $columnsType = 'dailyIncome';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = false;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>
<div class="modal fade" id="dailyIncomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" id="dailyIncomeForm">
        <div class="modal-header"><h2 class="modal-title fs-5">일용근로소득</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button></div>
        <div class="modal-body"><input type="hidden" name="id">
            <section class="ui-form-card income-document-card"><div class="ui-form-card__body"><div class="income-document-fields">
                <div class="income-document-field income-document-field--month daily-income-date-field" data-table-settings-field="income_year_month"><label class="form-label" for="dailyIncomeYearMonthDisplay">귀속연월</label><input type="hidden" name="income_year_month"><div class="date-input-wrap"><input class="form-control" id="dailyIncomeYearMonthDisplay" required inputmode="numeric" maxlength="7" autocomplete="off" placeholder="YYYY-MM" aria-label="귀속연월 입력"><span class="date-icon" id="dailyIncomeYearMonthButton" role="button" tabindex="0" aria-label="귀속연월 달력 열기" aria-haspopup="dialog"><i class="bi bi-calendar3" aria-hidden="true"></i></span></div></div>
                <div class="income-document-field income-document-field--month daily-income-date-field" data-table-settings-field="withholding_date"><label class="form-label" for="dailyIncomeWithholdingDate">원천징수일</label><div class="date-input-wrap"><input class="form-control" id="dailyIncomeWithholdingDate" name="withholding_date" required inputmode="numeric" maxlength="10" autocomplete="off" placeholder="YYYY-MM-DD" aria-label="원천징수일 입력"><span class="date-icon" id="dailyIncomeWithholdingDateButton" role="button" tabindex="0" aria-label="원천징수일 달력 열기"><i class="bi bi-calendar3" aria-hidden="true"></i></span></div></div>
                <div class="income-document-field income-document-field--title" data-table-settings-field="document_title"><label class="form-label">제목</label><input class="form-control" name="document_title" required></div>
                <div class="income-document-field income-document-field--description" data-table-settings-field="description"><label class="form-label">비고</label><input class="form-control" name="description" maxlength="500"></div>
                <div class="income-document-field income-document-field--memo" data-table-settings-field="memo"><label class="form-label">메모</label><input class="form-control" name="memo"></div>
            </div></div></section>
            <div id="dailyIncomeCalculationReadiness" class="alert alert-warning income-calculation-guidance mt-3 mb-3 d-none" role="status" aria-live="polite"></div>
            <div class="daily-income-section-heading"><h6>근무그룹별 근무내역</h6><div class="d-flex gap-2"><button type="button" class="btn btn-outline-success btn-sm" id="dailyIncomeExcelManager"><i class="bi bi-file-earmark-spreadsheet"></i> 엑셀관리</button><button type="button" class="btn btn-outline-primary btn-sm" id="dailyIncomeAddWorker"><i class="bi bi-plus-lg"></i> 근무그룹 추가</button></div></div>
            <div class="daily-income-entry-layout"><div id="dailyIncomeWorkers" class="daily-income-workers"></div><aside id="dailyIncomeWorkerResult" class="daily-income-worker-result" aria-live="polite"><h6>선택 작업자 계산 결과</h6><div class="daily-income-result-empty">작업자 카드를 선택해 주세요.</div></aside></div>
            <div id="dailyIncomeDocumentSummary" class="daily-income-document-summary" aria-live="polite"></div>
            <section class="ui-form-card daily-income-system-card" id="dailyIncomeSystemCard" aria-label="시스템 처리 정보">
                <button type="button" class="ui-form-card__toggle collapsed" id="dailyIncomeSystemToggle" data-ui-modal-card-collapse data-bs-target="#dailyIncomeSystemInfoCollapse" aria-expanded="false" aria-controls="dailyIncomeSystemInfoCollapse">
                    <span class="ui-form-card__title">시스템 처리 정보</span><i class="bi bi-chevron-down ui-form-card__toggle-icon" aria-hidden="true"></i>
                </button>
                <div id="dailyIncomeSystemInfoCollapse" class="collapse"><div class="ui-form-card__body daily-income-system-info-grid" id="dailyIncomeSystemInfo"></div></div>
            </section>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-danger me-auto" id="dailyIncomeDelete">삭제</button><button type="button" class="btn btn-outline-secondary" id="dailyIncomeWithdraw">회수</button><button type="button" class="btn btn-primary" id="dailyIncomeSubmit">결재요청</button><button type="submit" class="btn btn-success">저장</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button></div>
    </form></div>
</div>
<div id="dailyIncomeYearMonthPicker" class="picker is-hidden" aria-hidden="true"></div>
<div id="dailyIncomeWithholdingDatePicker" class="picker is-hidden" aria-hidden="true"></div>
<?php
$modalId = 'dailyIncomeTrashModal';
$type = 'daily-employment-income';
$modalTitle = '일용근로소득 휴지통';
$tableId = 'daily-employment-income-trash-table';
$tableHead = '<th>귀속연월</th><th>제목</th><th>문서상태</th><th>삭제일시</th><th>관리</th>';
$emptyMessage = '휴지통에 일용근로소득 문서가 없습니다.';
$purgeConfirm = '영구삭제하면 일용근로소득 문서와 근로자별 근무내역을 복구할 수 없습니다. 계속하시겠습니까?';
$listUrl = '/api/institution/income-data/daily-employment/trash';
$restoreUrl = '/api/institution/income-data/daily-employment/restore';
$deleteUrl = '/api/institution/income-data/daily-employment/purge';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
include PROJECT_ROOT . '/app/views/main/settings/base-info/partials/client_modal.php';
include PROJECT_ROOT . '/app/views/main/settings/base-info/partials/work-team_modal.php';
include PROJECT_ROOT . '/app/views/main/settings/system/partials/code_modal.php';

$modalId = 'dailyIncomeExcelModal';
$formId = 'daily-income-excel-form';
$modalTitle = '일용근로소득 엑셀관리';
$modalSubtitle = '업로드 자료는 검증 Preview 후 현재 문서의 Group Grid에만 반영됩니다.';
$fileInputId = 'dailyIncomeExcelFile';
$spinnerId = 'dailyIncomeExcelSpinner';
$btnTemplateId = 'dailyIncomeExcelTemplate';
$btnDownloadAll = 'dailyIncomeExcelDownload';
$uploadBtnId = 'dailyIncomeExcelUpload';
$templateUrl = '/api/institution/income-data/daily-employment/template';
$downloadUrl = '/api/institution/income-data/daily-employment/excel';
$uploadUrl = '/api/institution/income-data/daily-employment/excel-upload-preview';
include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';

?>
