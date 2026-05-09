<?php
// 공통 엑셀관리 모달

$modalId        = $modalId        ?? 'excelModal';
$formId         = $formId         ?? 'excel-form';
$modalTitle     = $modalTitle     ?? '엑셀관리';

$fileInputId    = $fileInputId    ?? 'excelFile';
$fileInputName  = $fileInputName  ?? 'excel';
$spinnerId      = $spinnerId      ?? 'excelSpinner';

$btnTemplateId  = $btnTemplateId  ?? 'btnDownloadTemplate';
$btnDownloadAll = $btnDownloadAll ?? 'btnDownloadAll';
$uploadBtnId    = $uploadBtnId    ?? 'btnUploadExcel';

$templateUrl = $templateUrl ?? '';
$downloadUrl = $downloadUrl ?? '';
$uploadUrl   = $uploadUrl ?? '';
?>

<div class="modal fade"
     id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>"
     tabindex="-1"
     aria-hidden="true">

  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <form id="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>"
            enctype="multipart/form-data"
            data-template-url="<?= htmlspecialchars($templateUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-download-url="<?= htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-upload-url="<?= htmlspecialchars($uploadUrl, ENT_QUOTES, 'UTF-8') ?>">

        <div class="modal-header">
          <h5 class="modal-title"><?= htmlspecialchars($modalTitle, ENT_QUOTES, 'UTF-8') ?></h5>
          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="modal"
                  aria-label="닫기"></button>
        </div>

        <div class="modal-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <button type="button"
                    class="btn btn-outline-secondary btn-sm btn-template-download"
                    id="<?= htmlspecialchars($btnTemplateId, ENT_QUOTES, 'UTF-8') ?>">
              양식 다운로드
            </button>

            <button type="button"
                    class="btn btn-outline-primary btn-sm btn-download-all"
                    id="<?= htmlspecialchars($btnDownloadAll, ENT_QUOTES, 'UTF-8') ?>">
              전체 다운로드
            </button>

            <input type="file"
                   name="<?= htmlspecialchars($fileInputName, ENT_QUOTES, 'UTF-8') ?>"
                   id="<?= htmlspecialchars($fileInputId, ENT_QUOTES, 'UTF-8') ?>"
                   class="form-control form-control-sm"
                   accept=".xlsx,.xls">
          </div>

          <small class="form-text text-danger mb-3 d-block">
            업로드할 엑셀 파일을 선택하세요.
          </small>

          <div class="excel-spinner text-center mt-2"
               id="<?= htmlspecialchars($spinnerId, ENT_QUOTES, 'UTF-8') ?>"
               style="display:none;">
            <div class="spinner-border text-primary"></div>
            <div class="mt-1" style="font-size:.9em;">
              업로드 중입니다...
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-success btn-sm btn-upload-excel"
                  id="<?= htmlspecialchars($uploadBtnId, ENT_QUOTES, 'UTF-8') ?>">
            업로드
          </button>

          <button type="button"
                  class="btn btn-secondary btn-sm"
                  data-bs-dismiss="modal">
            닫기
          </button>
        </div>

      </form>

    </div>
  </div>
</div>
