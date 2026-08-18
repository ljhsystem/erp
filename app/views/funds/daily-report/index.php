<?php

use Core\Helpers\AssetHelper;

$escape = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$accounts = $filterOptions['accounts'] ?? [];
$layoutOptions = ['header' => true, 'navbar' => true, 'sidebar' => true, 'footer' => false, 'wrapper' => 'single'];
$pageStyles = AssetHelper::css('/assets/css/pages/funds/daily-report/index.css');
$pageScripts = AssetHelper::module('/assets/js/pages/funds/daily-report/index.js');
?>
<main class="daily-funds-report" id="dailyFundsReport">
    <div class="container-fluid py-3">
        <header class="daily-report-heading">
            <div><h5><i class="bi bi-calendar2-check me-2"></i>자금일보</h5><p>기준일의 전일잔액, 입출금, 마감잔액과 가용자금을 한눈에 확인합니다.</p></div>
            <div class="daily-report-actions"><button class="btn btn-outline-success btn-sm" id="dailyReportExcel"><i class="bi bi-file-earmark-excel me-1"></i>엑셀</button><button class="btn btn-outline-secondary btn-sm" id="dailyReportPrint"><i class="bi bi-printer me-1"></i>인쇄</button></div>
        </header>
        <form id="dailyReportFilter" class="daily-report-filter">
            <label><span>기준일</span><input class="form-control" type="date" name="report_date" value="<?= $escape($initialFilters['report_date'] ?? '') ?>"></label>
            <label><span>자금구분</span><select class="form-select" name="fund_type"><option value="">전체</option><option value="BANK">은행</option><option value="CASH">현금</option></select></label>
            <label><span>계좌/자금수단</span><select class="form-select" name="bank_account_id"><option value="">전체</option><?php foreach ($accounts as $account): ?><option value="<?= $escape($account['id'] ?? '') ?>"><?= $escape($account['account_name'] ?? '') ?></option><?php endforeach; ?></select></label>
            <label><span>금융기관</span><input class="form-control" name="bank_name" placeholder="은행명"></label>
            <label><span>사업구분</span><input class="form-control" name="business_unit" placeholder="사업구분 코드"></label>
            <label><span>프로젝트</span><input class="form-control" name="project" placeholder="프로젝트명"></label>
            <label><span>입출금</span><select class="form-select" name="direction"><option value="">전체</option><option value="IN">입금</option><option value="OUT">출금</option></select></label>
            <label><span>미연결</span><select class="form-select" name="unlinked_only"><option value="0">전체</option><option value="1">미연결만</option></select></label>
            <label class="daily-report-keyword"><span>검색어</span><input class="form-control" type="search" name="q" placeholder="거래내용, 거래처, 프로젝트, 전표번호"></label>
            <div class="daily-report-filter-buttons"><button type="reset" class="btn btn-outline-secondary btn-sm">초기화</button><button type="submit" class="btn btn-primary btn-sm">조회</button></div>
        </form>
        <section class="daily-report-cards">
            <?php foreach (['opening_balance'=>'전일 실제잔액','deposit_total'=>'당일 실제입금','withdraw_total'=>'당일 실제출금','ending_balance'=>'당일 실제잔액','accounting_balance'=>'당일 회계잔액','balance_difference'=>'실제·회계 차이','scheduled_payment'=>'지급예정액','available_funds'=>'지급 후 가용자금'] as $key=>$label): ?>
                <article><span><?= $label ?></span><strong data-summary="<?= $key ?>">0</strong><small>원</small></article>
            <?php endforeach; ?>
        </section>
        <div class="alert alert-warning d-none" id="dailyReportIntegrity">자금 합계 검증식이 일치하지 않습니다.</div>
        <section class="daily-report-panel"><div class="daily-report-panel-title"><h6>자금수단별 현황</h6><span id="dailyReportDateLabel"></span></div><table id="dailyInstrumentTable" class="table table-hover w-100"></table></section>
        <section class="daily-report-subgrid">
            <a class="daily-report-panel daily-report-link-panel" id="dailyUnlinkedLink" href="/ledger/funds/account-transactions?link_status=UNLINKED"><h6>미연결 입출금</h6><div class="daily-report-metrics"><span>입금 <strong data-unlinked="deposit_count">0건</strong></span><span>입금액 <strong data-unlinked="deposit_amount">0</strong></span><span>출금 <strong data-unlinked="withdraw_count">0건</strong></span><span>출금액 <strong data-unlinked="withdraw_amount">0</strong></span></div></a>
            <a class="daily-report-panel daily-report-link-panel" id="dailyPaymentLink" href="/ledger/funds/payment-schedule"><h6>지급예정</h6><div class="daily-report-metrics"><span>오늘 <strong data-payment="today">0</strong></span><span>7일 <strong data-payment="within_7_days">0</strong></span><span>30일 <strong data-payment="within_30_days">0</strong></span><span>연체 <strong data-payment="overdue">0</strong></span></div></a>
        </section>
        <section class="daily-report-panel"><div class="daily-report-panel-title"><h6>당일 입출금 내역</h6><span>전체 거래 · 금액순 정렬 지원</span></div><table id="dailyTransactionTable" class="table table-hover w-100"></table></section>
    </div>
</main>
