<?php

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$pageTitle = json_decode('"\\uC99D\\uBE59\\uC6D0\\uBCF8"');

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => false,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/data-status.css')
    . AssetHelper::css('/assets/css/pages/dashboard/settings/system/code.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/dataStatus.js');
?>

<main class="ledger-data-status-page" id="ledgerDataStatusPage">
    <div class="container-fluid py-3">
        <div class="page-header mb-3 d-flex justify-content-between align-items-start">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-clipboard-data me-2"></i>&#51613;&#48729;&#50896;&#48376;
                </h5>
                <div class="small text-muted mt-1">
                    &#51088;&#47308;&#50976;&#54805;&#48324; &#50629;&#47196;&#46300; &#50896;&#48376;&#51012; &#54869;&#51064;&#54616;&#44256; &#44144;&#47000; &#49373;&#49457; &#51204;&#50640; &#54596;&#50836;&#54620; &#51221;&#48372;&#47484; &#51221;&#47532;&#54633;&#45768;&#45796;.
                </div>
            </div>
            <div class="evidence-type-header-controls">
                <div class="evidence-type-select-box">
                    <div class="d-flex align-items-center justify-content-start gap-2 mb-1">
                        <label for="evidenceTypeSelect" class="form-label small fw-semibold mb-0">&#51088;&#47308;&#50976;&#54805;</label>
                        <span id="evidenceTypeSelectCount" class="evidence-type-select-count">0&#44148;</span>
                    </div>
                    <select id="evidenceTypeSelect"
                            class="form-select form-select-sm"
                            data-code-group="IMPORT_TYPE"
                            data-empty-option="false"
                            data-empty-label="&#51088;&#47308;&#50976;&#54805; &#49440;&#53469;">
                    </select>
                </div>
            </div>
        </div>

        <section class="card mb-3">
            <div class="card-body py-3">
                <div id="evidenceTypeTabs" class="evidence-type-tabs" role="tablist" aria-label="&#51613;&#48729;&#50896;&#48376; &#51088;&#47308;&#50976;&#54805;"></div>
            </div>
        </section>

        <div class="content-area">
            <?php
            $searchId = 'evidenceStatus';
            $dateOptions = '
                <option value="mapped_payload.transaction_date">&#44144;&#47000;&#51068;&#51088;</option>
                <option value="created_at">&#46321;&#47197;&#51068;&#49884;</option>
                <option value="processed_at">&#52376;&#47532;&#51068;&#49884;</option>
                <option value="updated_at">&#49688;&#51221;&#51068;&#49884;</option>
            ';
            $searchFieldOptions = '<option value="">&#51204;&#52404;</option>';
            $periodGuideTitle = json_decode('"\\uC99D\\uBE59\\uC6D0\\uBCF8 \\uAE30\\uAC04 \\uC870\\uAC74 \\uC548\\uB0B4"');
            $periodGuideItems = [
                json_decode('"\\uC790\\uB8CC\\uC720\\uD615\\uBCC4 \\uAC70\\uB798\\uC77C\\uC790, \\uB4F1\\uB85D\\uC77C\\uC2DC, \\uCC98\\uB9AC\\uC77C\\uC2DC, \\uC218\\uC815\\uC77C\\uC2DC \\uAE30\\uC900\\uC73C\\uB85C \\uC99D\\uBE59\\uC6D0\\uBCF8\\uC744 \\uC870\\uD68C\\uD569\\uB2C8\\uB2E4."'),
                json_decode('"\\uC790\\uB8CC\\uC720\\uD615\\uC744 \\uC120\\uD0DD\\uD558\\uBA74 \\uD574\\uB2F9 \\uC6D0\\uBCF8 \\uB370\\uC774\\uD130\\uB9CC \\uBAA9\\uB85D\\uC5D0 \\uD45C\\uC2DC\\uD569\\uB2C8\\uB2E4."'),
            ];
            $searchGuideTitle = json_decode('"\\uC99D\\uBE59\\uC6D0\\uBCF8 \\uAC80\\uC0C9 \\uC870\\uAC74 \\uC548\\uB0B4"');
            $searchGuideItems = [
                json_decode('"\\uAC70\\uB798\\uCC98, \\uC801\\uC694, \\uAE08\\uC561, \\uC790\\uB8CC\\uC720\\uD615 \\uB4F1 \\uC99D\\uBE59\\uC6D0\\uBCF8\\uC758 \\uC8FC\\uC694 \\uD544\\uB4DC\\uB85C \\uAC80\\uC0C9\\uD560 \\uC218 \\uC788\\uC2B5\\uB2C8\\uB2E4."'),
                json_decode('"\\uC6D0\\uBCF8 \\uD30C\\uC77C \\uC5C5\\uB85C\\uB4DC \\uD6C4 \\uC774 \\uD654\\uBA74\\uC5D0\\uC11C \\uAC70\\uB798 \\uC0DD\\uC131\\uC5D0 \\uD544\\uC694\\uD55C \\uAE30\\uC900\\uC815\\uBCF4\\uC640 \\uAE30\\uCD08\\uC815\\uBCF4\\uB97C \\uBCF4\\uC644\\uD569\\uB2C8\\uB2E4."'),
            ];
            $searchInitialCollapsed = true;
            include PROJECT_ROOT . '/app/views/components/ui-search.php';

            $tableId = 'evidenceStatusTable';
            $ajaxUrl = '/api/import/evidences?import_type=TAX_INVOICE';
            $columnsType = 'evidenceStatus';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = true;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>
</main>

<div class="modal fade data-management-modal" id="dataUploadModal" tabindex="-1" aria-labelledby="dataUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataUploadModalLabel">&#51088;&#47308; &#50629;&#47196;&#46300;</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#45803;&#44592;"></button>
            </div>
            <div class="modal-body p-0">
                <iframe class="data-management-frame" src="/ledger/data/upload?modal=1" title="&#51088;&#47308; &#50629;&#47196;&#46300;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade data-management-modal" id="dataFormatModal" tabindex="-1" aria-labelledby="dataFormatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataFormatModalLabel">&#50577;&#49885; &#44288;&#47532;</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#45803;&#44592;"></button>
            </div>
            <div class="modal-body p-0">
                <iframe class="data-management-frame" src="/ledger/data/formats?modal=1" title="&#50577;&#49885; &#44288;&#47532;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="evidenceSeedRowEditModal" tabindex="-1" aria-labelledby="evidenceSeedRowEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content evidence-edit-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="evidenceSeedRowEditModalLabel">&#51613;&#48729;&#50896;&#48376; &#49345;&#49464;/&#49688;&#51221;</h5>
                    <div class="small text-muted" id="evidenceSeedRowEditSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#45803;&#44592;"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="evidenceSeedRowEditId">
                <div class="alert alert-info py-2 small">
                    &#50896;&#48376; &#54028;&#51068;&#51032; &#45936;&#51060;&#53552;&#47484; &#44144;&#47000; &#49373;&#49457; &#51204;&#50640; &#48372;&#50756;&#54624; &#49688; &#51080;&#49845;&#45768;&#45796;. &#51060;&#48120; &#44050;&#51060; &#51080;&#45716; &#54637;&#47785;&#51008; &#54596;&#50836;&#54620; &#44221;&#50864;&#50640;&#47564; &#49688;&#51221;&#54616;&#49464;&#50836;.
                </div>
                <div id="evidenceSeedRowEditFields" class="evidence-edit-fields"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm" id="evidenceSeedRowEditSaveBtn">&#51200;&#51109;</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">&#45803;&#44592;</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="evidenceBulkEditModal" tabindex="-1" aria-labelledby="evidenceBulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content evidence-bulk-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="evidenceBulkEditModalLabel">&#49440;&#53469; &#51068;&#44292;&#48372;&#51221;</h5>
                    <div class="small text-muted" id="evidenceBulkEditSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="&#45803;&#44592;"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-2">
                    &#49440;&#53469;&#54620; &#54665;&#50640; &#44057;&#51008; &#44592;&#51456;&#51221;&#48372;&#50752; &#44592;&#52488;&#51221;&#48372;&#47484; &#54620; &#48264;&#50640; &#48152;&#50689;&#54633;&#45768;&#45796;. &#52404;&#53356;&#54620; &#54637;&#47785;&#47564; &#51200;&#51109;&#46121;&#45768;&#45796;.
                </div>
                <div class="evidence-bulk-options mb-3">
                    <label class="evidence-bulk-mode">
                        <input type="radio" name="evidenceBulkMode" value="fill_blank" checked>
                        <span>&#48712; &#44050;&#47564; &#52292;&#50864;&#44592;</span>
                    </label>
                    <label class="evidence-bulk-mode">
                        <input type="radio" name="evidenceBulkMode" value="overwrite">
                        <span>&#49440;&#53469;&#44050;&#51004;&#47196; &#45934;&#50612;&#50416;&#44592;</span>
                    </label>
                </div>
                <div id="evidenceBulkEditFields" class="evidence-bulk-fields"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm" id="evidenceBulkEditSaveBtn">&#49440;&#53469;&#54637;&#47785; &#51200;&#51109;</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">&#45803;&#44592;</button>
            </div>
        </div>
    </div>
</div>

<?php
$modalId = 'dataExcelModal';
$formId = 'dataExcelForm';
$modalTitle = json_decode('"\\uC591\\uC2DD \\uAD00\\uB9AC"');
$modalSubtitle = json_decode('"\\uD604\\uC7AC \\uC790\\uB8CC\\uC720\\uD615\\uC758 \\uC591\\uC2DD\\uC744 \\uC120\\uD0DD\\uD558\\uC138\\uC694."');
$templateUrl = '';
$downloadUrl = '';
$uploadUrl = '';
$fileInputId = 'dataExcelFile';
$fileInputName = 'file';
$spinnerId = 'dataExcelSpinner';
$btnTemplateId = 'btnDownloadEvidenceTemplate';
$btnDownloadAll = 'btnDownloadEvidenceData';
$uploadBtnId = 'btnUploadEvidenceExcel';
$templateLabel = json_decode('"\\uD604\\uC7AC\\uC591\\uC2DD\\uB2E4\\uC6B4\\uB85C\\uB4DC"');
$downloadLabel = json_decode('"\\uC790\\uB8CC\\uB2E4\\uC6B4\\uB85C\\uB4DC"');
$showFileInput = true;
$helperText = json_decode('"\\uD604\\uC7AC \\uC790\\uB8CC\\uC720\\uD615\\uC758 \\uC591\\uC2DD\\uC73C\\uB85C \\uC5D1\\uC140\\uC744 \\uC5C5\\uB85C\\uB4DC\\uD558\\uAC70\\uB098 \\uB2E4\\uC6B4\\uB85C\\uB4DC\\uD560 \\uC218 \\uC788\\uC2B5\\uB2C8\\uB2E4."');
$dropzoneTitle = json_decode('"\\uC5D1\\uC140 \\uD30C\\uC77C\\uC744 \\uC120\\uD0DD\\uD558\\uAC70\\uB098 \\uC5EC\\uAE30\\uC5D0 \\uB04C\\uC5B4 \\uB193\\uC73C\\uC138\\uC694."');
include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
?>

<?php
$modalId = 'evidenceStatusTrashModal';
$type = 'evidenceStatus';
$modalTitle = json_decode('"\\uC99D\\uBE59\\uC6D0\\uBCF8 \\uD734\\uC9C0\\uD1B5"');
$tableId = 'evidenceStatus-trash-table';
$checkAllId = 'evidenceStatusTrashCheckAll';
$tableHead = '
    <th width="70">&#49692;&#48264;</th>
    <th>&#51088;&#47308;&#50976;&#54805;</th>
    <th>&#44144;&#47000;&#52376;</th>
    <th>&#54633;&#44228;</th>
    <th>&#49325;&#51228;&#51068;&#49884;</th>
    <th width="150">&#44288;&#47532;</th>
';
$emptyMessage = json_decode('"\\uD734\\uC9C0\\uD1B5\\uC758 \\uC99D\\uBE59\\uC6D0\\uBCF8\\uC744 \\uC120\\uD0DD\\uD558\\uBA74 \\uC0C1\\uC138 \\uC815\\uBCF4\\uAC00 \\uD45C\\uC2DC\\uB429\\uB2C8\\uB2E4."');
$listUrl = '/api/import/evidences/trash';
$restoreUrl = '/api/import/evidences/restore';
$deleteUrl = '/api/import/evidences/purge';
$deleteAllUrl = '/api/import/evidences/purge-all';
include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
?>

<?php include PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php'; ?>
