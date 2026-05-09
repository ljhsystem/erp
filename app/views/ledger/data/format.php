<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = json_decode('"\\uC591\\uC2DD\\uAD00\\uB9AC"');

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/data-format.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataFormat.js');
?>

<main class="ledger-data-format-page" id="ledgerDataFormatPage">
    <div class="container-fluid py-4">
        <div class="page-header mb-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-table me-2"></i>&#50577;&#49885;&#44288;&#47532;
            </h5>
        </div>

        <div class="row g-3">
            <section class="col-12 col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold">&#50577;&#49885; &#47785;&#47197;</span>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-primary btn-sm" id="newFormatBtn">+ &#50577;&#49885; &#52628;&#44032;</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="formatTrashBtn" title="&#55092;&#51648;&#53685;">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <select class="form-select form-select-sm" id="formatTypeFilter" data-empty-label="&#51088;&#47308;&#50976;&#54805;&#51012; &#49440;&#53469;&#54616;&#49464;&#50836;">
                            <option value="">&#51088;&#47308;&#50976;&#54805;&#51012; &#49440;&#53469;&#54616;&#49464;&#50836;</option>
                        </select>
                    </div>
                    <div class="list-group list-group-flush" id="formatList"></div>
                </div>
            </section>

            <section class="col-12 col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">&#52972;&#47100; &#47588;&#54609; &#49444;&#51221;</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="copyFormatBtn">&#48373;&#49324;</button>
                    </div>
                    <div class="card-body">
                        <input type="hidden" id="formatId">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="formatName">&#50577;&#49885;&#47749;</label>
                                <input type="text" class="form-control form-control-sm" id="formatName" placeholder="&#50696;: &#50629;&#47196;&#46300; &#50577;&#49885;&#47749;">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="formatDataType">&#51088;&#47308;&#50976;&#54805;</label>
                                <select class="form-select form-select-sm" id="formatDataType" data-empty-label="&#51088;&#47308;&#50976;&#54805;&#51012; &#49440;&#53469;&#54616;&#49464;&#50836;">
                                    <option value="">&#51088;&#47308;&#50976;&#54805;&#51012; &#49440;&#53469;&#54616;&#49464;&#50836;</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="formatIsDefault">
                                    <label class="form-check-label" for="formatIsDefault">&#44592;&#48376;&#50577;&#49885;</label>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-3 format-column-table-wrap">
                            <table class="table table-bordered align-middle mb-0 format-column-table" id="formatColumnTable">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 92px;">&#49692;&#49436;</th>
                                    <th>&#50641;&#49472; &#52972;&#47100;&#47749;</th>
                                    <th>&#49884;&#49828;&#53596; &#54596;&#46300;&#47749;</th>
                                    <th style="width: 80px;">&#54596;&#49688;</th>
                                    <th style="width: 104px;">&#49325;&#51228;</th>
                                </tr>
                                </thead>
                                <tbody id="formatColumnBody"></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="addColumnBtn">&#52972;&#47100; &#52628;&#44032;</button>
                            <div>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="deleteFormatBtn">&#49325;&#51228;</button>
                                <button type="button" class="btn btn-primary btn-sm" id="saveFormatBtn">&#51200;&#51109;</button>
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
                <h5 class="modal-title" id="newFormatModalLabel">&#50577;&#49885; &#52628;&#44032;</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#45803;&#44592;"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="newFormatName">&#50577;&#49885;&#47749;</label>
                    <input type="text" class="form-control form-control-sm" id="newFormatName" placeholder="&#50696;: &#49324;&#50857;&#51088; &#50577;&#49885;&#47749;">
                </div>
                <div>
                    <label class="form-label" for="newFormatDataType">&#51088;&#47308;&#50976;&#54805;</label>
                    <select class="form-select form-select-sm" id="newFormatDataType" data-empty-label="&#51088;&#47308;&#50976;&#54805;&#51012; &#49440;&#53469;&#54616;&#49464;&#50836;">
                        <option value="">&#51088;&#47308;&#50976;&#54805;&#51012; &#49440;&#53469;&#54616;&#49464;&#50836;</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">&#52712;&#49548;</button>
                <button type="button" class="btn btn-primary btn-sm" id="confirmNewFormatBtn">&#52628;&#44032;</button>
            </div>
        </div>
    </div>
</div>

<?php
$modalId = 'dataFormatTrashModal';
$type = 'dataFormat';
$modalTitle = '&#50577;&#49885; &#55092;&#51648;&#53685;';
$tableId = 'data-format-trash-table';
$checkAllId = 'dataFormatTrashCheckAll';
$listUrl = '/api/import/formats/trash';
$restoreUrl = '/api/import/formats/restore';
$deleteUrl = '/api/import/formats/purge';
$deleteAllUrl = '/api/import/formats/purge-all';
$tableHead = '
    <th>&#50577;&#49885;&#47749;</th>
    <th>&#51088;&#47308;&#50976;&#54805;</th>
    <th>&#44592;&#48376;</th>
    <th>&#49325;&#51228;&#51068;&#49884;</th>
    <th>&#49325;&#51228;&#51088;</th>
    <th width="150">&#44288;&#47532;</th>
';
$emptyMessage = '&#49325;&#51228;&#46108; &#50577;&#49885;&#51012; &#49440;&#53469;&#54616;&#47732; &#49345;&#49464; &#51221;&#48372;&#44032; &#54364;&#49884;&#46121;&#45768;&#45796;.';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>
