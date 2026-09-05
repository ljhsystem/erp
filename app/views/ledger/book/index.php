<?php
use Core\Helpers\AssetHelper;

$layoutOptions = ['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles = AssetHelper::css('/assets/css/pages/ledger/book/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/ledger/book/index.js');
?>
<main class="ledger-journal-book" id="ledgerJournalBookPage">
  <div class="container-fluid py-4 dt-page-shell">
    <div class="page-header">
      <div><h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2"></i>분개장</h5><div class="text-muted small mt-1">전기된 전표의 분개라인을 일자 순서로 조회합니다.</div></div>
      <span id="ledgerJournalBookCount" class="text-primary page-count"></span>
    </div>
    <?php
    $searchId = 'ledgerJournalBook';
    $dateOptions = '<option value="voucher_date">전표일자</option>';
    $searchFieldOptions = '<option value="">선택</option>';
    $periodGuideTitle = '분개장 기간 조건 안내';
    $periodGuideItems = ['전표일자를 기준으로 조회 기간을 지정합니다.','공식 장부에는 전기완료·마감 전표만 포함됩니다.'];
    $searchGuideTitle = '분개장 검색 조건 안내';
    $searchGuideItems = ['전표번호, 계정코드, 계정과목, 적요, 전표상태로 검색합니다.','검색조건은 공용 테이블 설정의 표시 컬럼과 연동됩니다.'];
    include PROJECT_ROOT . '/app/views/components/ui-search.php';
    ?>
    <section class="journal-scope-bar">
      <div><strong id="journalScopeTitle">공식 장부</strong><span id="journalScopeDescription">전기완료·마감 전표만 조회합니다.</span></div>
    </section>
    <section class="journal-summary" aria-label="분개장 합계">
      <div><span>전표</span><strong id="journalVoucherCount">0건</strong></div><div><span>분개라인</span><strong id="journalLineCount">0건</strong></div><div><span>차변합계</span><strong id="journalDebitTotal">0원</strong></div><div><span>대변합계</span><strong id="journalCreditTotal">0원</strong></div><div><span>차이</span><strong id="journalDifference">0원</strong></div>
    </section>
    <div class="journal-table-wrap">
      <?php
      $tableId='ledgerJournalBookTable'; $ajaxUrl='/api/ledger/book/journal/list'; $columnsType='ledger-journal-book';
      $tableClass='table table-bordered align-middle table-cross-highlight ledger-journal-book-table';
      $enableButtons=true; $enableSearch=true; $enablePaging=true; $enableReorder=false;
      include PROJECT_ROOT . '/app/views/components/ui-table.php';
      ?>
    </div>
    <div class="journal-help text-muted small"><i class="bi bi-info-circle me-1"></i>행을 두 번 클릭하면 해당 전표 작성 화면으로 이동합니다. 분개장은 조회·출력 전용이며 직접 저장하거나 삭제하지 않습니다.</div>
  </div>
</main>
