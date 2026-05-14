<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '&#51088;&#47308; &#50629;&#47196;&#46300;';
$isModal = ($_GET['modal'] ?? '') === '1';

$layoutOptions = [
    'header' => true,
    'navbar' => !$isModal,
    'sidebar' => !$isModal,
    'breadcrumb' => !$isModal,
    'footer' => !$isModal,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.css')
    . AssetHelper::css('/assets/css/pages/dashboard/settings/system/code.css')
    . AssetHelper::css('/assets/css/pages/ledger/data-upload.css');
$pageScripts = AssetHelper::js('https://cdn.jsdelivr.net/npm/handsontable@14.6.1/dist/handsontable.full.min.js')
    . AssetHelper::module('/assets/js/pages/ledger/dataUpload.js');
?>

<main class="ledger-data-upload-page" id="ledgerDataUploadPage">
    <div class="container-fluid <?= $isModal ? 'py-3' : 'py-4' ?>">
        <div class="page-header mb-3 <?= $isModal ? 'd-none' : '' ?>">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-upload me-2"></i>&#51088;&#47308; &#50629;&#47196;&#46300;
            </h5>
        </div>

        <section class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-2 col-md-3">
                        <label class="form-label" for="dataType">&#51088;&#47308;&#50976;&#54805;</label>
                        <select class="form-select form-select-sm"
                                id="dataType"
                                data-code-group="IMPORT_TYPE"
                                data-empty-label="자료유형 선택"
                                required>
                            <option value="">자료유형을 선택하세요</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-3 col-md-4">
                        <label class="form-label" for="formatSelect">&#50577;&#49885; &#49440;&#53469;</label>
                        <select class="form-select form-select-sm" id="formatSelect">
                            <option value="">&#50577;&#49885;&#51012; &#49440;&#53469;&#54616;&#49464;&#50836;</option>
                        </select>
                    </div>

                    <div class="col-12 col-lg-auto col-md-5">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="formatManageBtn">
                                &#50577;&#49885; &#44288;&#47532;
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="downloadTemplateBtn" disabled>
                                &#50577;&#49885; &#45796;&#50868;&#47196;&#46300;
                            </button>
                        </div>
                    </div>

                    <div class="col-12 col-lg-3 col-md-6">
                        <label class="form-label" for="uploadFile">&#54028;&#51068; &#49440;&#53469;</label>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="uploadFileBtn">
                                &#49440;&#53469;
                            </button>
                            <div class="form-control form-control-sm text-muted text-truncate" id="uploadFileSummary" title="&#49440;&#53469;&#54620; &#54028;&#51068;&#51060; &#50630;&#49845;&#45768;&#45796;." role="button">
                                <span id="uploadFileName">&#49440;&#53469;&#54620; &#54028;&#51068;&#51060; &#50630;&#49845;&#45768;&#45796;.</span>
                                <span class="upload-file-size d-none" id="uploadFileSize"></span>
                            </div>
                            <input type="file"
                                   class="visually-hidden"
                                   id="uploadFile"
                                   accept=".xlsx,.xls,.csv">
                        </div>
                    </div>

                    <div class="col-12 col-lg-auto col-md-6 text-md-end">
                        <div class="d-flex gap-2 justify-content-md-end">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="validateBtn">
                                검증
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="seedUploadBtn" disabled>
                                Seed 업로드
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card mb-3 d-none" id="validationSummaryCard">
            <div class="card-header fw-semibold">검증 요약</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2" id="validationSummaryList"></div>
                <div class="small text-muted mt-2">검증은 Seed를 저장하지 않습니다. 결과 확인 후 Seed 업로드를 실행해야 적재됩니다.</div>
            </div>
        </section>

        <section class="card mb-3 d-none" id="validationDetailCard">
            <div class="card-header fw-semibold">검증 상세</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>구분</th>
                            <th>내용</th>
                        </tr>
                        </thead>
                        <tbody id="validationDetailList">
                        <tr>
                            <td colspan="2" class="text-center text-muted py-3">검증 결과가 없습니다.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="alert alert-info d-none" id="uploadResultAlert">
            <span id="uploadResultText"></span>
            <a href="/ledger/data" class="btn btn-sm btn-primary ms-2">&#51088;&#47308;&#47785;&#47197;&#51004;&#47196; &#51060;&#46041;</a>
        </div>

        <section class="card mb-3">
            <div class="card-header fw-semibold">업로드 검증 결과</div>
            <div class="card-body p-0">
                <div class="spreadsheet-toolbar">
                    <div class="spreadsheet-options">
                        <label class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" id="previewFilterErrors">
                            <span class="form-check-label">&#50724;&#47448;&#47564; &#48372;&#44592;</span>
                        </label>
                        <label class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" id="previewFilterMapped">
                            <span class="form-check-label">&#47588;&#54609;&#52972;&#47100;&#47564; &#48372;&#44592;</span>
                        </label>
                        <label class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" id="previewHideUnused">
                            <span class="form-check-label">&#48120;&#49324;&#50857;&#52972;&#47100; &#49704;&#44592;&#44592;</span>
                        </label>
                    </div>
                    <span class="text-muted small" id="previewGridSummary"></span>
                </div>
                <div class="spreadsheet-shell" id="previewGridWrap">
                    <div class="spreadsheet-grid" id="previewGrid"></div>
                    <div class="spreadsheet-empty" id="previewEmpty">
                        &#54028;&#51068;&#51012; &#49440;&#53469;&#54620; &#46244; &#48120;&#47532;&#48372;&#44592;&#47484; &#49892;&#54665;&#54616;&#49464;&#50836;.
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>

<?php include PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php'; ?>
