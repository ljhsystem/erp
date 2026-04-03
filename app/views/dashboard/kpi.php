<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/kpi.php'
use Core\Helpers\AssetHelper;
// 페이지 캐싱 방지
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
// 페이지별 스타일/스크립트
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';
$pageStyles = AssetHelper::css('/assets/css/pages/dashboard/kpi.css');
$pageScripts = AssetHelper::js('https://cdn.jsdelivr.net/npm/chart.js');
$pageScripts = AssetHelper::js('/assets/js/pages/dashboard/kpi.js');
// 브레드크럼프 (본문에 포함)
$breadcrumb = [
    '홈' => '/dashboard',
    '실적현황' => '/dashboard/kpi'
  ];
?>
<?php include_once __DIR__ . '/../layout/breadcrumb.php'; ?>

<!-- ✅ 메인 콘텐츠 -->
<main class="kpi-main">

    <!-- 타이틀 -->
    <div>
      <h5 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h5>
      <p translate="no">실적현황 페이지.</p>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="monthSelect" class="form-label">📅 기준 월 선택</label>
            <input type="month" id="monthSelect" class="form-control">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button id="kpiSearchBtn" class="btn btn-primary w-100">조회</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card kpi-card bg-success text-white">
                <div class="card-body">
                    <h6>총 매출</h6>
                    <h4>$28,700</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card bg-info text-white">
                <div class="card-body">
                    <h6>신규 거래처</h6>
                    <h4>15곳</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card bg-warning text-dark">
                <div class="card-body">
                    <h6>완료된 결재</h6>
                    <h4>28건</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <h6 class="fw-bold mb-3">📈 부서별 실적 비교</h6>
            <canvas id="kpiChart" height="100"></canvas>
        </div>
    </div>
</main>