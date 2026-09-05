<?php
use Core\Helpers\AssetHelper;
$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles=AssetHelper::css('/assets/css/pages/ledger/book/partner/index.css');
$pageScripts=AssetHelper::module('/assets/js/pages/ledger/book/partner/index.js');
?>
<main class="ledger-partner-book" id="ledgerPartnerBookPage"><div class="container-fluid py-4 dt-page-shell">
  <div class="page-header"><div><h5 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>거래처원장</h5><div class="text-muted small mt-1">선택 거래처와 연결된 계정별 기초잔액·전표 증감·잔액을 조회합니다.</div></div><span id="ledgerPartnerBookCount" class="text-primary page-count"></span></div>
  <section class="partner-selector-bar"><label for="partnerLedgerClient"><span>조회 거래처</span><strong>*</strong></label><select id="partnerLedgerClient" class="form-select form-select-sm" aria-label="조회 거래처"><option value="">거래처 검색</option></select><span id="partnerLedgerSelectionGuide">거래처명을 검색해 선택해 주세요.</span></section>
  <?php
  $searchId='ledgerPartnerBook';$dateOptions='<option value="voucher_date">전표일자</option>';$searchFieldOptions='<option value="">선택</option>';
  $periodGuideTitle='거래처원장 기간 조건 안내';$periodGuideItems=['조회 시작일에 적용되는 거래처별 기초금액과 기간 내 분개를 합산합니다.','공식 장부에는 전기완료·마감 전표만 포함됩니다.'];
  $searchGuideTitle='거래처원장 검색 조건 안내';$searchGuideItems=['전표번호, 계정코드, 계정과목, 적요로 검색할 수 있습니다.','선택 거래처가 보조원장으로 연결된 분개만 조회합니다.'];
  include PROJECT_ROOT.'/app/views/components/ui-search.php';
  ?>
  <section class="partner-scope-bar"><div><strong>공식 장부</strong><span>전기완료·마감 전표만 조회합니다.</span></div></section>
  <section class="partner-summary" aria-label="거래처원장 합계">
    <div><span>사용계정</span><strong id="partnerAccountCount">0개</strong></div><div><span>기초차변</span><strong id="partnerOpeningDebit">0원</strong></div><div><span>기초대변</span><strong id="partnerOpeningCredit">0원</strong></div><div><span>당기차변</span><strong id="partnerPeriodDebit">0원</strong></div><div><span>당기대변</span><strong id="partnerPeriodCredit">0원</strong></div><div><span>기말차변</span><strong id="partnerEndingDebit">0원</strong></div><div><span>기말대변</span><strong id="partnerEndingCredit">0원</strong></div><div><span>전표/분개</span><strong id="partnerVoucherLines">0건 / 0건</strong></div>
  </section>
  <section class="partner-table-panel"><?php $tableId='ledgerPartnerBookTable';$ajaxUrl='/api/ledger/book/partner/list';$columnsType='ledger-partner-book';$tableClass='table table-bordered align-middle table-cross-highlight ledger-partner-book-table';$enableButtons=true;$enableSearch=true;$enablePaging=true;$enableReorder=false;include PROJECT_ROOT.'/app/views/components/ui-table.php';?></section>
  <div class="partner-help text-muted small"><i class="bi bi-info-circle me-1"></i>서로 다른 계정의 잔액을 임의로 합치지 않고 계정별 정상잔액 방향으로 계산합니다. 오류는 전표 취소·정정으로 처리합니다.</div>
</div></main>
