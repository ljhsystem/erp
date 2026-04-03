<?php
// 경로: PROJECT_ROOT . '/app/views/ledger/account/index.php'
use Core\Helpers\AssetHelper;
// 페이지 캐싱 방지
if (!headers_sent()) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

$userId = $_SESSION['user']['id'] ?? '';

$layoutOptions = [
  'header'  => true,
  'navbar'  => true,
  'sidebar' => true,
  'footer'  => true,
  'wrapper' => 'single'
];

$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';
$pageStyles = AssetHelper::css('/assets/css/pages/ledger/account.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/account.js');

// 세션 플래시 메시지 추출
$flashMsg = isset($_SESSION['account_message']) ? (string)$_SESSION['account_message'] : '';
unset($_SESSION['account_message']);

// 브레드크럼프
$breadcrumb = [
  '홈' => '/dashboard',
  '거래원장대시보드' => '/ledger',
  '계정등록' => '/ledger/basic/account'
];
?>
<?php include_once PROJECT_ROOT . '/app/views/layout/breadcrumb.php'; ?>

<main class="account-main"
      id="account-main"
      data-flash="<?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?>">

  <div class="account-page py-4">

    <!-- 타이틀 -->
    <div class="page-header">
      <h5 class="mb-1 fw-bold">🧾 계정과목관리</h5>
      <span id="accountCount"
            class="text-primary"
            style="font-size:1rem; margin-left:35px;"></span>
    </div>

    <div class="content-area">

      <!-- 검색 폼 -->
      <div id="searchFormContainer" class="search-form-container">

        <span id="toggleSearchForm" class="search-toggle-text">접기</span>

        <div id="searchFormBody" class="search-form-body">

          <label class="search-form-title">검색 폼</label>

          <!-- 기간 조건 -->
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
                  <li>기간 기준(등록일자, 발생일자 등)을 선택 후 원하는 날짜 범위를 지정하세요.</li>
                  <li>빠른 선택 버튼(오늘, 1개월 등)을 클릭하면 자동으로 날짜가 입력됩니다.</li>
                  <li>직접 시작일/종료일을 입력해도 됩니다.</li>
                  <li>기간 설정 버튼으로 원하는 기간을 적용할 수 있습니다.</li>
                </ul>
              </div>
            </div>

            <select id="dateType"
                    name="dateType"
                    class="form-select form-select-sm date-type-select">
              <option value="created_at">생성일자</option>
              <option value="registration_date" disabled>등록일자</option>
              <option value="occur_date" disabled>발생일자</option>
              <option value="issue_date" disabled>발급일자</option>
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

          <!-- 검색 조건 -->
          <form id="searchConditionsForm">

            <div id="searchWrapper" class="search-wrapper">

              <div class="search-label-area">
                <div class="label-btn" id="searchLabel">
                  검색어
                  <i class="fa fa-question-circle tooltip-trigger"
                     id="tooltipTrigger"></i>
                </div>

                <div id="tooltipContainer" class="tooltip-container">
                  <strong>검색어</strong>
                  <ul>
                    <li>검색어 여러 개를 쉼표(,)로 구분해서 10개까지 동시에 검색할 수 있습니다.</li>
                    <li>예시 : 1, 3, 5</li>
                    <li>조건 관련 항목은 1개씩만 검색할 수 있습니다.</li>
                    <li>검색 입력창은 최대 5개까지 추가 가능합니다.</li>
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

      <!-- 메인 2단 레이아웃 -->
      <div class="account-split-layout">

        <!-- 좌측: 계정 테이블 -->
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

        <!-- 우측: 보조계정 -->
        <aside class="account-right-panel">
          <div class="subaccount-box h-100">

            <div class="subaccount-header d-flex justify-content-between align-items-start">

              <div>
                <h6 class="fw-bold mb-0">보조계정</h6>
                <div id="subaccountGuide"
                    class="text-muted small mt-1">
                  보조계정을 추가하고 관리할 수 있습니다.
                </div>
              </div>

              <div class="d-flex align-items-center gap-2">
                
                <button id="btnAddSubAccount"
                        class="btn btn-sm btn-primary"
                        disabled>
                  보조계정 추가
                </button>

                <!-- 🔥 추가 -->
                <button id="btnCloseSubPanel"
                        class="btn btn-sm btn-outline-secondary"
                        title="닫기">
                  ✕
                </button>

              </div>

            </div>

            <div class="table-responsive subaccount-table-wrap">
              <table class="table table-sm table-bordered align-middle"
                    id="subaccount-table">
                <thead>
                  <tr>
                    <th width="50">코드</th>
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

  <!-- 모달 -->
  <?php include __DIR__ . '/_modal_account_edit.php'; ?>
  <?php include __DIR__ . '/_modal_account_excel.php'; ?>
  <?php include __DIR__ . '/_modal_account_trash.php'; ?>

  <!-- 피커 -->
  <div class="picker-root">
    <div id="mini-picker" class="picker is-hidden"></div>
    <div id="base-picker" class="picker is-hidden"></div>
    <div id="account-picker" class="picker is-hidden"></div>
    <div id="datetime-picker" class="picker is-hidden"></div>
    <div id="today-picker" class="picker is-hidden"></div>
    <div id="time-list-picker" class="picker is-hidden"></div>
  </div>

</main>