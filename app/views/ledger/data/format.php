<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = json_decode('"\\uC591\\uC2DD\\uAD00\\uB9AC"');
$isModal = ($_GET['modal'] ?? '') === '1';

$layoutOptions = [
    'header' => true,
    'navbar' => !$isModal,
    'sidebar' => !$isModal,
    'breadcrumb' => !$isModal,
    'footer' => !$isModal,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/data-format.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataFormat.js');
?>

<main class="ledger-data-format-page <?= $isModal ? 'is-modal-page' : '' ?>" id="ledgerDataFormatPage">
    <div class="container-fluid <?= $isModal ? 'p-0' : 'py-4' ?>">
        <div class="page-header mb-3 <?= $isModal ? 'd-none' : '' ?>">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-table me-2"></i>&#50577;&#49885;&#44288;&#47532;
            </h5>
        </div>

        <div class="row g-3 data-format-layout">
            <section class="col-12 col-lg-4 data-format-list-panel">
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

            <section class="col-12 col-lg-8 data-format-editor-panel">
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

                        <div class="format-editor-hint mb-3">
                            <i class="bi bi-columns-gap"></i>
                            <span>&#50641;&#49472; &#52972;&#47100;&#51012; &#50577;&#49885; &#49692;&#49436;&#45824;&#47196; &#47588;&#54609;&#54616;&#44256;, &#54364;&#49884;/&#54596;&#49688;&#45716; &#52972;&#47100; &#54756;&#45908;&#50640;&#49436; &#51204;&#52404; &#49440;&#53469;&#54624; &#49688; &#51080;&#49845;&#45768;&#45796;.</span>
                            <span class="requirement-legend ms-auto">
                                <span><i class="requirement-dot requirement-none"></i>&#49440;&#53469;&#50630;&#51020;</span>
                                <span><i class="requirement-dot requirement-optional"></i>&#49440;&#53469;</span>
                                <span><i class="requirement-dot requirement-required"></i>&#54596;&#49688;</span>
                            </span>
                        </div>

                        <div class="table-responsive mb-3 format-column-table-wrap">
                            <table class="table table-bordered align-middle mb-0 format-column-table" id="formatColumnTable">
                                <thead class="table-light">
                                <tr>
                                    <th style="width: 92px;">&#49692;&#49436;</th>
                                    <th>&#50641;&#49472; &#52972;&#47100;&#47749;</th>
                                    <th>&#49884;&#49828;&#53596; &#54596;&#46300;</th>
                                    <th style="width: 94px;" class="text-center">
                                        <label class="format-bulk-check" title="&#54868;&#47732;&#54364;&#49884; &#51204;&#52404; &#49440;&#53469;/&#54644;&#51228;">
                                            <input type="checkbox" class="form-check-input format-column-toggle-all" data-target=".is-visible">
                                            <span>&#54868;&#47732;&#54364;&#49884;</span>
                                        </label>
                                    </th>
                                    <th style="width: 112px;" class="text-center">
                                        <span>&#54596;&#49688;&#44396;&#48516;</span>
                                    </th>
                                    <th style="width: 112px;" class="text-center">
                                        <button type="button" class="btn btn-link btn-sm format-text-action" id="addColumnBtn">+&#52628;&#44032;</button>
                                    </th>
                                </tr>
                                </thead>
                                <tbody id="formatColumnBody"></tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </section>
        </div>

        <div class="format-modal-footer d-flex justify-content-end align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="downloadCurrentFormatBtn">&#54788;&#51116;&#50577;&#49885;&#45796;&#50868;&#47196;&#46300;</button>
            <button type="button" class="btn btn-primary btn-sm" id="saveFormatBtn">&#51200;&#51109;</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="closeFormatBtn">&#45803;&#44592;</button>
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
