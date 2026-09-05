<?php
use Core\Helpers\AssetHelper;

$layoutOptions = ['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles = AssetHelper::css('/assets/css/pages/ledger/book/general/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/book/general/index.js');
?>
<main class="ledger-general-book" id="ledgerGeneralBookPage">
  <div class="container-fluid py-4 dt-page-shell">
    <div class="page-header">
      <div><h5 class="mb-0 fw-bold"><i class="bi bi-journals me-2"></i>총계정원장</h5><div class="text-muted small mt-1">계정과목별 기초잔액과 전기 증감, 기말잔액을 조회합니다.</div></div>
      <span id="ledgerGeneralBookCount" class="text-primary page-count"></span>
    </div>
    <?php
    $searchId='ledgerGeneralBook';
    $dateOptions='<option value="voucher_date">전표일자</option>';
    $searchFieldOptions='<option value="">선택</option>';
    $periodGuideTitle='총계정원장 기간 조건 안내';
    $periodGuideItems=['조회 시작일에 적용되는 기초금액과 기간 내 전기된 분개를 합산합니다.','공식 장부에는 전기완료·마감 전표만 포함됩니다.'];
    $searchGuideTitle='총계정원장 검색 조건 안내';
    $searchGuideItems=['계정코드 또는 계정과목으로 검색할 수 있습니다.','계정 행을 선택하면 아래에서 해당 계정의 분개 흐름과 누적잔액을 확인합니다.'];
    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>
    <section class="general-scope-bar">
      <div><strong id="generalScopeTitle">공식 장부</strong><span id="generalScopeDescription">전기완료·마감 전표만 조회합니다.</span></div>
    </section>
    <section class="general-summary" aria-label="총계정원장 합계">
      <div><span>사용 계정</span><strong id="generalAccountCount">0개</strong></div>
      <div><span>기초 차변</span><strong id="generalOpeningDebit">0원</strong></div>
      <div><span>기초 대변</span><strong id="generalOpeningCredit">0원</strong></div>
      <div><span>당기 차변</span><strong id="generalPeriodDebit">0원</strong></div>
      <div><span>당기 대변</span><strong id="generalPeriodCredit">0원</strong></div>
      <div><span>기말 차변</span><strong id="generalEndingDebit">0원</strong></div>
      <div><span>기말 대변</span><strong id="generalEndingCredit">0원</strong></div>
      <div><span>차이</span><strong id="generalDifference">0원</strong></div>
    </section>
    <section class="general-panel">
      <div class="general-panel-title"><strong>계정별 집계</strong><span>계정을 클릭하면 상세원장이 표시됩니다.</span></div>
      <?php
      $tableId='ledgerGeneralBookTable'; $ajaxUrl='/api/ledger/book/general/list'; $columnsType='ledger-general-book';
      $tableClass='table table-bordered align-middle table-cross-highlight ledger-general-book-table';
      $enableButtons=true; $enableSearch=true; $enablePaging=true; $enableReorder=false;
      include PROJECT_ROOT . '/app/views/components/ui-table.php';
      ?>
    </section>
    <section class="general-panel general-detail-panel">
      <div class="general-panel-title"><div><strong id="generalDetailTitle">계정 상세원장</strong><span id="generalDetailDescription">위 목록에서 계정과목을 선택해 주세요.</span></div><span id="generalDetailCount" class="text-primary"></span></div>
      <?php
      $tableId='ledgerGeneralDetailTable'; $ajaxUrl='/api/ledger/book/general/detail'; $columnsType='ledger-general-detail';
      $tableClass='table table-bordered align-middle table-cross-highlight ledger-general-detail-table';
      $enableButtons=true; $enableSearch=false; $enablePaging=true; $enableReorder=false;
      include PROJECT_ROOT . '/app/views/components/ui-table.php';
      ?>
    </section>
    <div class="general-help text-muted small"><i class="bi bi-info-circle me-1"></i>총계정원장은 조회·출력 전용입니다. 오류는 원장 직접 수정이 아니라 전표의 취소·정정 흐름으로 처리합니다.</div>
  </div>
</main>
