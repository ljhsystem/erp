<?php
// 경로: PROJECT_ROOT . '/app/views/ledger/index.php'
use Core\Helpers\AssetHelper;
// 페이지 캐싱 방지
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
$userId = $currentUserId ?? '';
$layoutOptions = [
    'header'  => true,
    'navbar'  => true,
    'sidebar' => true,
    'footer'  => true,
    'wrapper' => 'single'
];
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';
$pageScripts =
    AssetHelper::js('https://cdn.jsdelivr.net/npm/chart.js') .
    AssetHelper::js('/assets/js/pages/ledger/index.js');
// 브레드크럼프 (레이아웃 내부에서 자동 포함되지 않으므로 본문에 포함)
?>
<!-- ✅ 메인 콘텐츠 -->
<main class="dashboard-main"> 

    <!-- 타이틀 -->
    <div>
      <h5 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h5>
      <p translate="no">실시간 회계 흐름 요약.</p>
    </div>

    <!-- 상단 요약 카드 -->
    <div class="row row-cols-1 row-cols-md-3 g-2 mb-3">
    <div class="col">
        <div class="card h-100">
        <div class="card-body py-2">
            <h6 class="mb-2">💰 현재 잔고</h6>
            <ul class="small mb-0">
            <li>IBK 보통예금: ₩12,500,000</li>
            <li>농협 운영자금: ₩4,800,000</li>
            <li><strong>합계: ₩17,300,000</strong></li>
            </ul>
        </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100">
        <div class="card-body py-2">
            <h6 class="mb-2">📋 미확정 전표</h6>
            <p class="mb-0 small">총 5건 <a href="/ledger/input/general" class="link-primary">→ 바로가기</a></p>
        </div>
        </div>
    </div>
    <div class="col">
        <div class="card h-100">
        <div class="card-body py-2">
            <h6 class="mb-2">🧾 주요 지표</h6>
            <ul class="small mb-0">
            <li>이번 달 매출: ₩45,200,000</li>
            <li>이번 달 지출: ₩31,700,000</li>
            </ul>
        </div>
        </div>
    </div>
    </div>

    <!-- 차트 2개 -->
    <div class="row g-2 mb-2">
    <div class="col-md-6 d-flex flex-column">
        <div class="card flex-grow-1">
        <div class="card-body py-2">
            <h6>📈 매출 추이</h6>
            <div style="height: 260px;"><canvas id="ledgerSalesChart"></canvas></div>
        </div>
        </div>
    </div>
    <div class="col-md-6 d-flex flex-column">
        <div class="card flex-grow-1">
        <div class="card-body py-2">
            <h6>📊 수익 vs 지출</h6>
            <div style="height: 260px;"><canvas id="ledgerProfitChart"></canvas></div>
        </div>
        </div>
    </div>
    </div>

    <!-- 최근 입출금 -->
    <div class="card mt-2">
    <div class="card-body py-2">
        <h6>🏦 최근 입출금 내역</h6>
        <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 text-center small">
            <thead class="table-light">
            <tr>
                <th style="width:100px;">날짜</th>
                <th>적요</th>
                <th style="width:140px;">금액</th>
                <th style="width:140px;">계정</th>
            </tr>
            </thead>
            <tbody>
            <tr><td>07-06</td><td>거래처 A 입금</td><td class="text-success fw-bold">+1,200,000</td><td>보통예금</td></tr>
            <tr><td>07-06</td><td>세금납부</td><td class="text-danger fw-bold">-500,000</td><td>국세청</td></tr>
            </tbody>
        </table>
        </div>
    </div>
    </div>
    </main>


