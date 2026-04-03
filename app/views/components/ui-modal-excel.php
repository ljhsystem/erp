<?php
// 경로: PROJECT_ROOT . '/app/views/components/ui-modal-excel.php'
// 공통 엑셀 업로드 모달

$modalId        = $modalId        ?? 'excelModal';
$formId         = $formId         ?? 'excel-form';
$modalTitle     = $modalTitle     ?? '엑셀 관리';

$fileInputId    = $fileInputId    ?? 'excelFile';
$spinnerId      = $spinnerId      ?? 'excelSpinner';

$btnTemplateId  = $btnTemplateId  ?? 'btnDownloadTemplate';
$btnDownloadAll = $btnDownloadAll ?? 'btnDownloadAll';
$uploadBtnId    = $uploadBtnId    ?? 'btnUploadExcel';

/* 🔥 핵심: URL 변수 */
$templateUrl = $templateUrl ?? '';
$downloadUrl = $downloadUrl ?? '';
$uploadUrl   = $uploadUrl ?? '';
?>

<div class="modal fade"
     id="<?= $modalId ?>"
     tabindex="-1"
     aria-hidden="true">

  <div class="modal-dialog">
    <div class="modal-content">

    <form id="<?= $formId ?>"
      enctype="multipart/form-data"
      data-template-url="<?= htmlspecialchars($templateUrl, ENT_QUOTES) ?>"
      data-download-url="<?= htmlspecialchars($downloadUrl, ENT_QUOTES) ?>"
      data-upload-url="<?= htmlspecialchars($uploadUrl, ENT_QUOTES) ?>">

        <!-- HEADER -->
        <div class="modal-header">
          <h5 class="modal-title"><?= $modalTitle ?></h5>
          <button type="button"
                  class="btn-close"
                  data-bs-dismiss="modal"></button>
        </div>

        <!-- BODY -->
        <div class="modal-body">

          <div class="d-flex align-items-center gap-2 mb-3">

            <button type="button"
                    class="btn btn-outline-secondary btn-sm btn-template-download"
                    id="<?= $btnTemplateId ?>">
              양식다운로드
            </button>

            <button type="button"
                    class="btn btn-outline-primary btn-sm btn-download-all"
                    id="<?= $btnDownloadAll ?>">
              전체다운로드
            </button>

            <input type="file"
                   name="excel"
                   id="<?= $fileInputId ?>"
                   class="form-control form-control-sm"
                   accept=".xlsx,.xls">

          </div>

          <small class="form-text text-danger mb-3 d-block">
            엑셀 업로드 시 파일을 선택하세요.
          </small>

          <div class="excel-spinner text-center mt-2" id="<?= $spinnerId ?>" style="display:none;">
            <div class="spinner-border text-primary"></div>
            <div class="mt-1" style="font-size:.9em;">
              업로드 중입니다...
            </div>
          </div>

        </div>

        <!-- FOOTER -->
        <div class="modal-footer">

          <button type="button"
                  class="btn btn-success btn-sm btn-upload-excel"
                  id="<?= $uploadBtnId ?>">
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