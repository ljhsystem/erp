<?php
// Path: PROJECT_ROOT . '/app/views/ledger/account/index.php'

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$flashMsg = $flashMsg ?? '';

$layoutOptions = [
    'header' => true,
    'navbar' => true,
    'sidebar' => true,
    'footer' => true,
    'wrapper' => 'single',
];

$pageStyles = AssetHelper::css('/assets/css/pages/ledger/account.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/account.js');
?>

<main class="account-main"
      id="account-main"
      data-flash="<?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>">

    <div class="account-page py-4">

        <div class="page-header">
            <h5 class="mb-1 fw-bold">
                <i class="bi bi-journal-text me-2"></i>계정과목
            </h5>
            <span id="accountCount" class="text-primary page-count"></span>
        </div>

        <div class="content-area">
            <?php
            $searchId = 'ledgerAccount';
            $dateOptions = '
                <option value="created_at">생성일자</option>
                <option value="updated_at">수정일자</option>
            ';
            $searchFieldOptions = '
                <option value="account_name">계정과목명</option>
                <option value="account_code">계정코드</option>
                <option value="account_group">계정구분</option>
                <option value="parent_name">상위계정</option>
                <option value="normal_balance">정상잔액</option>
                <option value="is_posting">전표입력</option>
                <option value="is_active">상태</option>
                <option value="note">비고</option>
                <option value="memo">메모</option>
            ';
            include PROJECT_ROOT . '/app/views/components/ui-search.php';
            ?>

            <?php
            $tableId = 'account-table';
            $ajaxUrl = '/api/ledger/account/list';
            $columnsType = 'ledgerAccount';
            $enableButtons = true;
            $enableSearch = true;
            $enablePaging = true;
            $enableReorder = true;
            include PROJECT_ROOT . '/app/views/components/ui-table.php';
            ?>
        </div>
    </div>

    <?php include __DIR__ . '/partials/account_modal.php'; ?>
    <?php include PROJECT_ROOT . '/app/views/dashboard/settings/system/partials/code_modal.php'; ?>

    <?php
    $templateUrl = '/api/ledger/account/template';
    $downloadUrl = '/api/ledger/account/excel';
    $uploadUrl = '/api/ledger/account/excel-upload';

    $modalId = 'accountExcelModal';
    $formId = 'account-excel-upload-form';
    $modalTitle = '계정과목 엑셀관리';
    $fileInputId = 'excelUpload';
    $fileInputName = 'file';
    $spinnerId = 'excelUploadSpinner';
    $btnTemplateId = 'btnDownloadAccountTemplate';
    $btnDownloadAll = 'btnDownloadAllAccounts';
    $uploadBtnId = 'btnUploadExcel';
    include PROJECT_ROOT . '/app/views/components/ui-modal-excel.php';
    ?>

    <?php
    $modalId = 'accountTrashModal';
    $type = 'account';
    $modalTitle = '계정과목 휴지통';
    $tableId = 'account-trash-table';
    $checkAllId = 'trashCheckAllAccount';
    $btnRestoreId = 'btnRestoreSelectedAccount';
    $btnDeleteId = 'btnDeleteSelectedAccount';
    $btnDeleteAll = 'btnDeleteAllAccounts';
    $listUrl = '/api/ledger/account/trash';
    $restoreUrl = '/api/ledger/account/restore';
    $deleteUrl = '/api/ledger/account/hard-delete';
    $deleteAllUrl = '/api/ledger/account/hard-delete-all';
    $tableHead = '
      <th width="110">계정코드</th>
      <th>계정과목명</th>
      <th width="90">계정구분</th>
      <th width="160">삭제일시</th>
      <th width="130">삭제자</th>
      <th width="160" class="text-center">관리</th>
    ';
    $emptyMessage = '삭제된 계정과목을 선택해 주세요.';
    include PROJECT_ROOT . '/app/views/components/ui-modal-trash.php';
    ?>

    <div class="picker-root">
        <div id="mini-picker" class="picker is-hidden"></div>
        <div id="base-picker" class="picker is-hidden"></div>
        <div id="account-picker" class="picker is-hidden"></div>
        <div id="datetime-picker" class="picker is-hidden"></div>
        <div id="today-picker" class="picker is-hidden"></div>
        <div id="time-list-picker" class="picker is-hidden"></div>
    </div>
</main>
