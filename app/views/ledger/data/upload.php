<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = '&#51088;&#47308; &#50629;&#47196;&#46300;';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = <<<'HTML'
<style>
.ledger-data-upload-page .upload-file-control {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.ledger-data-upload-page .upload-file-input {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
    pointer-events: none;
}
.ledger-data-upload-page .upload-file-summary {
    display: flex;
    align-items: center;
    min-width: 0;
    max-width: 280px;
    height: 31px;
    padding: 0 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: #f8f9fa;
    color: #6c757d;
    font-size: 13px;
    cursor: pointer;
}
.ledger-data-upload-page .upload-file-summary.has-file {
    background: #eef6ff;
    border-color: #9ec5fe;
    color: #084298;
}
.ledger-data-upload-page .upload-file-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ledger-data-upload-page .upload-file-size {
    flex: 0 0 auto;
    margin-left: 6px;
    color: #6c757d;
}
</style>
HTML;
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataUpload.js');
?>

<main class="ledger-data-upload-page" id="ledgerDataUploadPage">
    <div class="container-fluid py-4">
        <div class="page-header mb-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-upload me-2"></i>&#51088;&#47308; &#50629;&#47196;&#46300;
            </h5>
        </div>

        <section class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-2 col-md-3">
                        <label class="form-label" for="dataType">&#51088;&#47308;&#50976;&#54805;</label>
                        <select class="form-select form-select-sm" id="dataType">
                            <option value="TAX_INVOICE">&#49464;&#44552;&#44228;&#49328;&#49436;</option>
                            <option value="CASH_RECEIPT">&#54788;&#44552;&#50689;&#49688;&#51613;</option>
                            <option value="CARD_PURCHASE">&#52852;&#46300;(&#47588;&#51077;)</option>
                            <option value="CARD_SALE">&#52852;&#46300;(&#47588;&#52636;)</option>
                            <option value="BANK">&#51077;&#52636;</option>
                            <option value="ETC">&#44592;&#53440;</option>
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
                        <div class="upload-file-control">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="uploadFileBtn">
                                &#49440;&#53469;
                            </button>
                            <div class="upload-file-summary" id="uploadFileSummary" title="&#49440;&#53469;&#54620; &#54028;&#51068;&#51060; &#50630;&#49845;&#45768;&#45796;.">
                                <span class="upload-file-name" id="uploadFileName">&#49440;&#53469;&#54620; &#54028;&#51068;&#51060; &#50630;&#49845;&#45768;&#45796;.</span>
                                <span class="upload-file-size d-none" id="uploadFileSize"></span>
                            </div>
                            <input type="file"
                                   class="upload-file-input"
                                   id="uploadFile"
                                   accept=".xlsx,.xls,.csv">
                        </div>
                    </div>

                    <div class="col-12 col-lg-auto col-md-6 text-md-end">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="previewBtn">
                            &#48120;&#47532;&#48372;&#44592;
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="alert alert-info d-none" id="uploadResultAlert">
            <span id="uploadResultText"></span>
            <a href="/ledger/data" class="btn btn-sm btn-primary ms-2">&#51088;&#47308;&#47785;&#47197;&#51004;&#47196; &#51060;&#46041;</a>
        </div>

        <section class="card mb-3">
            <div class="card-header fw-semibold">&#48120;&#47532;&#48372;&#44592;</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" id="previewTable">
                        <thead class="table-light">
                        <tr>
                            <th>&#49345;&#53468;</th>
                            <th>&#50724;&#47448;&#45236;&#50857;</th>
                            <th>&#45216;&#51676;</th>
                            <th>&#44144;&#47000;&#52376;</th>
                            <th>&#49324;&#50629;&#47749;</th>
                            <th>&#51201;&#50836;</th>
                            <th class="text-end">&#44277;&#44553;&#44032;</th>
                            <th class="text-end">&#48512;&#44032;&#49464;</th>
                            <th class="text-end">&#54633;&#44228;</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">&#54028;&#51068;&#51012; &#49440;&#53469;&#54620; &#46244; &#48120;&#47532;&#48372;&#44592;&#47484; &#49892;&#54665;&#54616;&#49464;&#50836;.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</main>
