<?php
use Core\Helpers\AssetHelper;
$layoutOptions=['header'=>true,'navbar'=>true,'sidebar'=>true,'footer'=>true,'wrapper'=>'single'];$pageStyles=AssetHelper::css('/assets/css/pages/ledger/financial/report/index.css');$pageScripts=AssetHelper::module('/assets/js/pages/ledger/financial/report/index.js');$year=(int)date('Y');
?>
<main class="financial-report-page" id="financialReportPage" data-report-type="<?= htmlspecialchars($type,ENT_QUOTES,'UTF-8') ?>">
 <div class="container-fluid py-4">
  <header class="report-page-header"><div><h5><i class="bi bi-file-earmark-spreadsheet me-2"></i><?= htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8') ?></h5><p><?= htmlspecialchars($pageDescription,ENT_QUOTES,'UTF-8') ?></p></div><span id="reportScopeBadge" class="report-scope-badge is-official">공식 재무제표</span></header>
  <section class="report-toolbar" aria-label="재무제표 조회조건"><label>회사<input value="석향" disabled></label><label>회계연도<select id="reportYear"><?php for($y=$year;$y>=2013;$y--):?><option value="<?= $y ?>"><?= $y ?></option><?php endfor;?></select></label><label>조회 시작일<input type="date" id="reportDateFrom" value="<?= $year ?>-01-01"></label><label>조회 종료일<input type="date" id="reportDateTo" value="<?= $year ?>-12-31"></label><label>표시단위<select id="reportUnit"><option value="1">원</option><option value="1000">천원</option><option value="1000000">백만원</option></select></label><button type="button" class="btn btn-primary" id="reportSearch"><i class="bi bi-search"></i> 조회</button></section>
  <section class="report-status" id="reportStatus" aria-live="polite">전기완료·마감 전표를 기준으로 조회합니다.</section>
  <section class="report-summary" id="reportSummary"></section>
  <section class="report-paper"><div class="report-paper-heading"><div><h4><?= htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8') ?></h4><span id="reportPeriodLabel"></span></div><span id="reportUnitLabel">(단위: 원)</span></div><div class="report-table-wrap"><table class="report-table"><thead id="reportHead"></thead><tbody id="reportBody"></tbody></table></div><div class="report-empty d-none" id="reportEmpty">조회 조건에 해당하는 전기 자료가 없습니다.</div></section>
  <p class="report-footnote"><i class="bi bi-info-circle"></i> 재무제표 금액은 별도 저장하지 않으며 기초금액과 전기된 분개를 조회 시점에 집계합니다. 계정 행을 선택하면 계정별원장으로 이동합니다.</p>
 </div>
</main>
