<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/report.php'
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
$pageStyles = AssetHelper::css('/assets/css/pages/dashboard/report.css');
$pageScripts = '';
// 브레드크럼프 (레이아웃 내부에서 자동 포함되지 않으므로 본문에 포함)
$breadcrumb = [
    '홈' => '/dashboard',
    '통합보고서' => '/dashboard/report'
  ];
?>
<?php include_once __DIR__ . '/../layout/breadcrumb.php'; ?>

<!-- ✅ 메인 콘텐츠 -->
<main class="report-main">

    <!-- 타이틀 -->
    <div>
      <h5 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h5>
      <p translate="no">보고서 페이지.</p>
    </div>

    <div class="row mb-3 filter-section">
        <div class="col-md-3">
            <label for="startDate" class="form-label">시작일</label>
            <input type="date" id="startDate" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="endDate" class="form-label">종료일</label>
            <input type="date" id="endDate" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="reportType" class="form-label">보고서 유형</label>
            <select id="reportType" class="form-select">
                <option value="sales">매출 보고서</option>
                <option value="clients">거래처 보고서</option>
                <option value="approval">결재 보고서</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">조회</button>
        </div>
    </div>

    <div class="card flex-grow-1">
        <div class="card-body">
            <h6 class="fw-bold mb-3">📊 보고서 요약</h6>
            <div class="table-responsive">
                <table class="table table-bordered text-center small">
                    <thead class="table-light">
                        <tr>
                            <th>구분</th>
                            <th>항목</th>
                            <th>수치</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>매출</td>
                            <td>총 매출</td>
                            <td>$12,300</td>
                        </tr>
                        <tr>
                            <td>거래처</td>
                            <td>신규 등록</td>
                            <td>5곳</td>
                        </tr>
                        <tr>
                            <td>결재</td>
                            <td>진행 중</td>
                            <td>3건</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

