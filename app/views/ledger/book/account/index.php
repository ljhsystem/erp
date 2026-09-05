<?php
use Core\Helpers\AssetHelper;

$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles=AssetHelper::css('/assets/css/pages/ledger/book/account/index.css');
$pageScripts=AssetHelper::module('/assets/js/pages/ledger/book/account/index.js');
?>
<main class="ledger-account-book" id="ledgerAccountBookPage">
  <div class="container-fluid py-4 dt-page-shell">
    <div class="page-header">
      <div><h5 class="mb-0 fw-bold"><i class="bi bi-journal-bookmark me-2"></i>계정별원장</h5><div class="text-muted small mt-1">선택 계정의 기초잔액과 전표별 증감·상대계정·누적잔액을 조회합니다.</div></div>
      <span id="ledgerAccountBookCount" class="text-primary page-count"></span>
    </div>
    <section class="account-selector-bar">
      <label for="accountLedgerAccount"><span>조회 계정과목</span><strong>*</strong></label>
      <select id="accountLedgerAccount" class="form-select form-select-sm" aria-label="조회 계정과목"><option value="">계정과목 선택</option></select>
      <span id="accountLedgerSelectionGuide">전표입력이 가능한 계정과목을 선택해 주세요.</span>
    </section>
    <?php
    $searchId='ledgerAccountBook';
    $dateOptions='<option value="voucher_date">전표일자</option>';
    $searchFieldOptions='<option value="">선택</option>';
    $periodGuideTitle='계정별원장 기간 조건 안내';
    $periodGuideItems=['조회 시작일에 적용되는 기초금액부터 기간 내 분개를 계산합니다.','기간을 지정하지 않으면 선택 계정의 전체 전표 내역을 조회합니다.'];
    $searchGuideTitle='계정별원장 검색 조건 안내';
    $searchGuideItems=['전표번호, 적요, 상대계정으로 선택 계정의 내역을 검색합니다.','검색 결과의 합계와 누적잔액은 같은 조회조건을 사용합니다.'];
    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>
    <section class="account-scope-bar">
      <div><strong id="accountScopeTitle">공식 장부</strong><span id="accountScopeDescription">전기완료·마감 전표만 조회합니다.</span></div>
    </section>
    <section class="account-summary" aria-label="계정별원장 합계">
      <div><span>기초잔액</span><strong id="accountOpeningBalance">0원</strong></div>
      <div><span>당기차변</span><strong id="accountPeriodDebit">0원</strong></div>
      <div><span>당기대변</span><strong id="accountPeriodCredit">0원</strong></div>
      <div><span>기말잔액</span><strong id="accountEndingBalance">0원</strong></div>
      <div><span>전표/분개</span><strong id="accountVoucherLines">0건 / 0건</strong></div>
    </section>
    <section class="account-table-panel">
      <?php
      $tableId='ledgerAccountBookTable'; $ajaxUrl='/api/ledger/book/account/list'; $columnsType='ledger-account-book';
      $tableClass='table table-bordered align-middle table-cross-highlight ledger-account-book-table';
      $enableButtons=true; $enableSearch=true; $enablePaging=true; $enableReorder=false;
      include PROJECT_ROOT . '/app/views/components/ui-table.php';
      ?>
    </section>
    <div class="account-help text-muted small"><i class="bi bi-info-circle me-1"></i>계정별원장은 조회·출력 전용입니다. 원장 오류는 해당 전표의 취소·정정으로 처리합니다.</div>
  </div>
</main>
