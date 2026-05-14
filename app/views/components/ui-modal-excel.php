<?php
// 공통 엑셀관리 모달

$modalId        = $modalId        ?? 'excelModal';
$formId         = $formId         ?? 'excel-form';
$modalTitle     = $modalTitle     ?? '엑셀관리';
$modalSubtitle  = $modalSubtitle  ?? '';
$modalSize      = $modalSize      ?? 'modal-md';

$fileInputId    = $fileInputId    ?? 'excelFile';
$fileInputName  = $fileInputName  ?? 'excel';
$spinnerId      = $spinnerId      ?? 'excelSpinner';

$btnTemplateId  = $btnTemplateId  ?? 'btnDownloadTemplate';
$btnDownloadAll = $btnDownloadAll ?? 'btnDownloadAll';
$uploadBtnId    = $uploadBtnId    ?? 'btnUploadExcel';

$templateUrl = $templateUrl ?? '';
$downloadUrl = $downloadUrl ?? '';
$uploadUrl   = $uploadUrl ?? '';

$templateLabel = $templateLabel ?? '양식 다운로드';
$downloadLabel = $downloadLabel ?? '전체 다운로드';
$helperText    = $helperText ?? '업로드할 엑셀 파일을 선택하거나 파일을 끌어 놓으세요.';
$dropzoneTitle = $dropzoneTitle ?? '엑셀 파일을 선택하거나 여기에 끌어 놓으세요';
$dropzoneHint  = $dropzoneHint ?? '.xlsx, .xls 파일만 업로드할 수 있습니다.';

$showFileInput = $showFileInput ?? true;
$showTemplateButton = $showTemplateButton ?? true;
$showDownloadButton = $showDownloadButton ?? true;
?>

<div class="modal fade"
     id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>"
     tabindex="-1"
     aria-hidden="true">

  <div class="modal-dialog modal-dialog-centered <?= htmlspecialchars($modalSize, ENT_QUOTES, 'UTF-8') ?>">
    <div class="modal-content">

      <form id="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>"
            enctype="multipart/form-data"
            data-template-url="<?= htmlspecialchars($templateUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-download-url="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-upload-url="<?= htmlspecialchars($uploadUrl, ENT_QUOTES, 'UTF-8') ?>">

        <div class="modal-header">
          <div>
            <h5 class="modal-title"><?= htmlspecialchars($modalTitle, ENT_QUOTES, 'UTF-8') ?></h5>
            <?php if ($modalSubtitle !== ''): ?>
              <div class="small text-muted excel-modal-subtitle"><?= htmlspecialchars($modalSubtitle, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>
          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="modal"
                  aria-label="닫기"></button>
        </div>

        <div class="modal-body">
          <div class="d-flex justify-content-center gap-2 flex-wrap excel-action-grid">
            <?php if ($showTemplateButton): ?>
              <button type="button"
                      class="btn btn-outline-primary excel-action-card btn-template-download"
                      id="<?= htmlspecialchars($btnTemplateId, ENT_QUOTES, 'UTF-8') ?>"
                      <?= $templateUrl === '' ? 'disabled' : '' ?>>
                <i class="bi bi-download me-1"></i><?= htmlspecialchars($templateLabel, ENT_QUOTES, 'UTF-8') ?>
              </button>
            <?php endif; ?>

            <?php if ($showDownloadButton): ?>
              <button type="button"
                      class="btn btn-outline-success excel-action-card btn-download-all"
                      id="<?= htmlspecialchars($btnDownloadAll, ENT_QUOTES, 'UTF-8') ?>"
                      <?= $downloadUrl === '' ? 'disabled' : '' ?>>
                <i class="bi bi-file-earmark-arrow-down me-1"></i><?= htmlspecialchars($downloadLabel, ENT_QUOTES, 'UTF-8') ?>
              </button>
            <?php endif; ?>
          </div>

          <?php if ($showFileInput): ?>
            <label class="excel-dropzone mt-3" for="<?= htmlspecialchars($fileInputId, ENT_QUOTES, 'UTF-8') ?>">
              <input type="file"
                     name="<?= htmlspecialchars($fileInputName, ENT_QUOTES, 'UTF-8') ?>"
                     id="<?= htmlspecialchars($fileInputId, ENT_QUOTES, 'UTF-8') ?>"
                     class="excel-file-input"
                     accept=".xlsx,.xls">
              <span class="excel-dropzone-icon"><i class="bi bi-file-earmark-spreadsheet"></i></span>
              <span class="excel-dropzone-title"><?= htmlspecialchars($dropzoneTitle, ENT_QUOTES, 'UTF-8') ?></span>
              <span class="excel-dropzone-hint"><?= htmlspecialchars($dropzoneHint, ENT_QUOTES, 'UTF-8') ?></span>
              <span class="excel-dropzone-file" data-excel-file-name>&#49440;&#53469;&#46108; &#54028;&#51068; &#50630;&#51020;</span>
            </label>

            <small class="form-text text-danger mt-2 mb-3 d-block">
              <?= htmlspecialchars($helperText, ENT_QUOTES, 'UTF-8') ?>
            </small>
          <?php elseif ($helperText !== ''): ?>
            <div class="alert alert-light border small mt-3 mb-0">
              <?= htmlspecialchars($helperText, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <div class="excel-spinner excel-progress-panel mt-2"
               id="<?= htmlspecialchars($spinnerId, ENT_QUOTES, 'UTF-8') ?>"
               style="display:none;">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
              <div class="fw-semibold small" data-excel-progress-title>&#50629;&#47196;&#46300; &#51456;&#48708; &#51473;</div>
              <div class="small text-muted" data-excel-progress-percent>0%</div>
            </div>
            <div class="progress excel-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
              <div class="progress-bar" data-excel-progress-bar style="width:0%"></div>
            </div>
            <div class="d-flex align-items-start gap-2 mt-2">
              <i class="bi bi-arrow-repeat excel-progress-icon" aria-hidden="true"></i>
              <div class="small text-muted" data-excel-progress-message>&#54028;&#51068;&#51012; &#54869;&#51064;&#54616;&#44256; &#51080;&#49845;&#45768;&#45796;.</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <?php if ($showFileInput): ?>
            <button type="button"
                    class="btn btn-success btn-sm btn-upload-excel"
                    id="<?= htmlspecialchars($uploadBtnId, ENT_QUOTES, 'UTF-8') ?>">
              &#50629;&#47196;&#46300;
            </button>
          <?php endif; ?>

          <button type="button"
                  class="btn btn-secondary btn-sm"
                  data-bs-dismiss="modal">
            &#45803;&#44592;
          </button>
        </div>

      </form>

    </div>
  </div>
</div>
