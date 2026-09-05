<?php
use Core\Helpers\AssetHelper;
$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];
$pageStyles=AssetHelper::css('/assets/css/pages/ledger/index.css');
$pageScripts=AssetHelper::module('/assets/js/pages/ledger/index.js');
$currentYear=(int)date('Y');
?>
<main class="ledger-dashboard" id="ledgerDashboard">
 <header class="ledger-dashboard-header">
  <div><h5><i class="bi bi-grid-1x2-fill"></i> 회계관리 통합 대시보드</h5><p>전기된 회계자료와 처리할 업무를 한 화면에서 확인합니다.</p></div>
  <div class="ledger-dashboard-filters">
   <label>회계연도<select id="ledgerFiscalYear" class="form-select form-select-sm"><?php for($year=$currentYear;$year>=2013;$year--): ?><option value="<?= $year ?>"><?= $year ?>년</option><?php endfor; ?></select></label>
   <span class="ledger-freshness"><i class="bi bi-circle-fill"></i><b id="ledgerDashboardAsOf">기준시각 -</b></span>
   <button type="button" class="ledger-refresh-button" id="ledgerDashboardRefresh" aria-label="대시보드 새로고침" title="최신 회계자료로 새로고침"><i class="bi bi-arrow-clockwise"></i></button>
  </div>
 </header>
 <section class="ledger-dashboard-status" id="ledgerDashboardStatus" aria-live="polite">회계 현황을 불러오고 있습니다.</section>
 <section class="ledger-pulse" aria-label="핵심 회계지표">
  <div class="ledger-pulse-item is-revenue"><i class="bi bi-graph-up-arrow"></i><span>수익<strong id="yearRevenue">0원</strong><small>전기 기준</small></span></div>
  <div class="ledger-pulse-item is-expense"><i class="bi bi-graph-down-arrow"></i><span>비용<strong id="yearExpense">0원</strong><small>전기 기준</small></span></div>
  <div class="ledger-pulse-item is-profit"><i class="bi bi-activity"></i><span>당기손익<strong id="yearProfit">0원</strong><small id="profitCaption">수익 - 비용</small></span></div>
  <div class="ledger-pulse-item is-asset"><i class="bi bi-buildings"></i><span>자산 장부가<strong id="assetBookValue">0원</strong><small id="assetCaption">사용 중 0건</small></span></div>
  <div class="ledger-pulse-item is-inventory"><i class="bi bi-box-seam"></i><span>기말재고<strong id="inventoryClosing">0원</strong><small id="inventoryCaption">증감 기준</small></span></div>
  <div class="ledger-pulse-item is-balance"><i class="bi bi-bank"></i><span>차대차이<strong id="yearDifference">0원</strong><small id="balanceCaption">일치</small></span></div>
 </section>
 <section class="ledger-primary-grid">
  <article class="ledger-panel ledger-trend-panel">
   <div class="ledger-panel-heading"><div><h6>월별 수익·비용·손익 추이</h6><p id="dashboardPeriod">-</p></div><div class="ledger-chart-legend"><span class="is-blue">수익</span><span class="is-coral">비용</span><span class="is-teal">손익</span></div></div>
   <div class="ledger-chart" id="ledgerPerformanceChart" role="img" aria-label="월별 수익 비용 손익 차트"></div>
   <a class="ledger-panel-link" href="/ledger/financial/income-statement">손익계산서 자세히 <i class="bi bi-chevron-right"></i></a>
  </article>
  <article class="ledger-panel ledger-alert-panel">
   <div class="ledger-panel-heading"><div><h6>지금 확인할 일</h6><p>처리가 필요한 항목부터 표시합니다.</p></div><span id="alertCount" class="ledger-count-badge">0건</span></div>
   <div id="ledgerAlerts" class="ledger-alert-list"></div>
  </article>
  <article class="ledger-panel ledger-readiness-panel">
   <div class="ledger-panel-heading"><div><h6>결산 준비도</h6><p id="closureSummary">회계기간 상태를 확인합니다.</p></div></div>
   <div class="ledger-readiness-body"><div class="ledger-ring" id="readinessRing"><strong id="readinessPercent">0%</strong><span>준비 완료</span></div><div id="readinessList" class="ledger-readiness-list"></div></div>
   <a class="ledger-panel-link" href="/ledger/closing/check">결산 체크리스트 <i class="bi bi-chevron-right"></i></a>
  </article>
 </section>
 <section class="ledger-secondary-grid">
  <article class="ledger-panel ledger-flow-panel">
   <div class="ledger-panel-heading"><div><h6>전표 처리 현황</h6><p>작성부터 장부 반영까지의 현재 흐름입니다.</p></div><a href="/ledger/vouchers/review">전표관리 <i class="bi bi-chevron-right"></i></a></div>
   <div class="ledger-flow-grid">
    <a href="/ledger/vouchers/input"><i class="bi bi-pencil"></i><span>작성<strong id="voucherDraftCount">0건</strong></span></a><b>›</b>
    <a href="/ledger/vouchers/review"><i class="bi bi-person-check"></i><span>검토요청<strong id="voucherRequestedCount">0건</strong></span></a><b>›</b>
    <a href="/ledger/vouchers/review"><i class="bi bi-check2-circle"></i><span>검토완료<strong id="voucherReviewedCount">0건</strong></span></a><b>›</b>
    <a href="/ledger/book/journal" class="is-posted"><i class="bi bi-journal-text"></i><span>전기<strong id="voucherPostedCount">0건</strong></span></a><b>›</b>
    <a href="/ledger/closing/periods" class="is-closed"><i class="bi bi-book"></i><span>마감<strong id="voucherClosedCount">0건</strong></span></a>
   </div>
  </article>
  <article class="ledger-panel ledger-movement-panel">
   <div class="ledger-panel-heading"><div><h6>자산·재고 변동</h6><p>장부와 결산에 반영될 관리자료입니다.</p></div></div>
   <div class="ledger-movement-grid"><a href="/ledger/assets"><span>자산 취득가</span><strong id="assetAcquisition">0원</strong><small id="depreciationSummary">감가상각 0원</small></a><a href="/ledger/settings/inventory-balances"><span>재고 증가 / 감소</span><strong><em id="inventoryIncrease">0원</em> / <em id="inventoryDecrease">0원</em></strong><small id="inventoryOpening">기초재고 0원</small></a></div>
  </article>
 </section>
 <section class="ledger-bottom-grid">
  <article class="ledger-panel ledger-recent-panel">
   <div class="ledger-panel-heading"><div><h6>최근 전기 완료 전표</h6><p>공식 장부에 반영된 전표만 표시합니다.</p></div><a href="/ledger/book/journal">분개장 <i class="bi bi-chevron-right"></i></a></div>
   <div class="table-responsive"><table class="table table-sm ledger-recent-table"><thead><tr><th>전표일자</th><th>전표번호</th><th>적요</th><th>상태</th><th class="text-end">금액</th></tr></thead><tbody id="recentPostedVouchers"><tr><td colspan="5" class="ledger-empty">전기된 전표가 없습니다.</td></tr></tbody></table></div>
  </article>
  <nav class="ledger-panel ledger-quick-links" aria-label="회계관리 바로가기">
   <div class="ledger-panel-heading"><div><h6>빠른 이동</h6><p>자주 사용하는 회계 화면입니다.</p></div></div>
   <div><a href="/ledger/data"><i class="bi bi-file-earmark-check"></i><span>증빙원본</span></a><a href="/ledger/transactions/input"><i class="bi bi-arrow-left-right"></i><span>거래입력</span></a><a href="/ledger/vouchers/input"><i class="bi bi-journal-plus"></i><span>전표입력</span></a><a href="/ledger/book/general"><i class="bi bi-book-half"></i><span>총계정원장</span></a><a href="/ledger/financial/trial-balance"><i class="bi bi-calculator"></i><span>재무제표</span></a><a href="/ledger/assets"><i class="bi bi-buildings"></i><span>자산관리</span></a><a href="/ledger/settings/inventory-balances"><i class="bi bi-box-seam"></i><span>재고관리</span></a><a href="/ledger/book/vehicle-log"><i class="bi bi-car-front"></i><span>차량운행기록부</span></a><a href="/ledger/closing/check"><i class="bi bi-clipboard2-check"></i><span>결산관리</span></a></div>
  </nav>
 </section>
</main>
