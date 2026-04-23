<?php
// 경로: PROJECT_ROOT . '/app/views/ledger/account/index.php'

use Core\Helpers\AssetHelper;

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$currentUserId = $currentUserId ?? '';
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
            <span id="accountCount"
                  class="text-primary"
                  style="font-size:1rem; margin-left:35px;"></span>
        </div>

        <div class="content-area">

            <div id="searchFormContainer" class="search-form-container">
                <span id="toggleSearchForm" class="search-toggle-text">접기</span>

                <div id="searchFormBody" class="search-form-body">
                    <label class="search-form-title">검색</label>

                    <div class="period-row">
                        <div class="period-label-area">
                            <button type="button" id="periodLabel" class="label-btn">
                                기간
                                <i class="fa fa-question-circle tooltip-trigger"
                                   id="periodTooltipTrigger"></i>
                            </button>

                            <div id="periodTooltipContainer" class="tooltip-container">
                                <strong>기간 조건 안내</strong>
                                <ul>
                                    <li>기간 기준을 선택한 뒤 시작일과 종료일을 지정해 조회할 수 있습니다.</li>
                                    <li>오늘, 1개월, 3개월 같은 빠른 선택 버튼으로 기간을 바로 채울 수 있습니다.</li>
                                    <li>기간 설정 후 검색 버튼을 누르면 조건이 적용됩니다.</li>
                                </ul>
                            </div>
                        </div>

                        <select id="dateType"
                                name="dateType"
                                class="form-select form-select-sm date-type-select">
                            <option value="created_at">생성일자</option>
                            <option value="registration_date" disabled>등록일자</option>
                            <option value="occur_date" disabled>발생일자</option>
                            <option value="issue_date" disabled>발행일자</option>
                            <option value="deal_date" disabled>거래일자</option>
                        </select>

                        <div class="period-quick-btns">
                            <button type="button" onclick="setPeriod('today')" class="btn btn-outline-secondary btn-sm">오늘</button>
                            <button type="button" onclick="setPeriod('yesterday')" class="btn btn-outline-secondary btn-sm">어제</button>
                            <button type="button" onclick="setPeriod('3days')" class="btn btn-outline-secondary btn-sm">3일</button>
                            <button type="button" onclick="setPeriod('7days')" class="btn btn-outline-secondary btn-sm">7일</button>
                            <button type="button" onclick="setPeriod('15days')" class="btn btn-outline-secondary btn-sm">15일</button>
                            <button type="button" onclick="setPeriod('1month')" class="btn btn-outline-secondary btn-sm">1개월</button>
                            <button type="button" onclick="setPeriod('3months')" class="btn btn-outline-secondary btn-sm">3개월</button>
                            <button type="button" onclick="setPeriod('6months')" class="btn btn-outline-secondary btn-sm">6개월</button>
                        </div>

                        <div class="date-range">
                            <div class="date-input">
                                <input type="text"
                                       name="dateStart"
                                       class="form-control form-control-sm admin-date"
                                       placeholder="시작일">
                                <span class="date-icon">📅</span>
                            </div>

                            <span class="date-tilde">~</span>

                            <div class="date-input">
                                <input type="text"
                                       name="dateEnd"
                                       class="form-control form-control-sm admin-date"
                                       placeholder="종료일">
                                <span class="date-icon">📅</span>
                            </div>
                        </div>
                    </div>

                    <form id="searchConditionsForm">
                        <div id="searchWrapper" class="search-wrapper">
                            <div class="search-label-area">
                                <div class="label-btn" id="searchLabel">
                                    검색어
                                    <i class="fa fa-question-circle tooltip-trigger"
                                       id="tooltipTrigger"></i>
                                </div>

                                <div id="tooltipContainer" class="tooltip-container">
                                    <strong>검색어 안내</strong>
                                    <ul>
                                        <li>검색어는 쉼표로 구분하여 여러 개를 동시에 입력할 수 있습니다.</li>
                                        <li>조건별 검색 입력창을 최대 5개까지 추가할 수 있습니다.</li>
                                        <li>검색 조건과 기간 조건을 함께 적용할 수 있습니다.</li>
                                    </ul>
                                </div>
                            </div>

                            <div id="searchConditions" class="search-conditions">
                                <div class="search-condition">
                                    <select name="searchField[]"
                                            class="form-select form-select-sm search-field"></select>

                                    <input type="text"
                                           name="searchValue[]"
                                           class="form-control search-input"
                                           placeholder="검색어 입력">

                                    <button type="button"
                                            id="addSearchCondition"
                                            class="btn btn-dark">
                                        +
                                    </button>

                                    <button type="reset"
                                            id="resetButton"
                                            class="btn btn-secondary">
                                        초기화
                                    </button>

                                    <button type="submit"
                                            id="searchButton"
                                            class="btn btn-success">
                                        검색
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="account-split-layout">

                <section class="account-left-panel">
                    <div class="table-box h-100">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle"
                                   id="account-table">
                                <thead>
                                    <tr></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <aside class="account-right-panel">
                    <div class="subaccount-box h-100">

                        <div class="subaccount-header d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="fw-bold mb-0">보조계정</h6>
                                <div id="subaccountGuide"
                                     class="text-muted small mt-1">
                                    선택한 계정과목에 연결된 보조계정을 관리할 수 있습니다.
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <button id="btnAddSubAccount"
                                        class="btn btn-sm btn-primary"
                                        disabled>
                                    보조계정 추가
                                </button>

                                <button id="btnCloseSubPanel"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="닫기">
                                    ×
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive subaccount-table-wrap">
                            <table class="table table-sm table-bordered align-middle"
                                   id="subaccount-table">
                                <thead>
                                    <tr>
                                        <th width="50">순번</th>
                                        <th>보조계정명</th>
                                        <th width="120">관리</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                    </div>
                </aside>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/partials/account_modal.php'; ?>

    <?php
    $templateUrl = '/api/ledger/account/template';
    $downloadUrl = '/api/ledger/accounts/excel';
    $uploadUrl = '/api/ledger/account/excel-upload';

    $modalId = 'accountExcelModal';
    $formId = 'account-excel-upload-form';
    $modalTitle = '계정과목 엑셀 관리';
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
      <th width="100">계정코드</th>
      <th>계정과목명</th>
      <th width="80">구분</th>
      <th width="150">삭제일</th>
      <th width="120">삭제자</th>
      <th width="140" class="text-center">관리</th>
    ';
    $emptyMessage = '삭제된 계정과목을 선택해주세요.';

    $modalTitle = '계정과목 휴지통';
    $tableHead = '
      <th width="110">계정코드</th>
      <th>계정과목명</th>
      <th width="90">구분</th>
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
