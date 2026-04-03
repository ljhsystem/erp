<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/index.php'
// 페이지 캐싱 방지
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
use Core\Helpers\AssetHelper;

// 페이지별 스타일/스크립트
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';
$pageStyles = AssetHelper::css('/assets/css/pages/dashboard/index.css');
$pageScripts  = AssetHelper::js('https://cdn.jsdelivr.net/npm/chart.js');
$pageScripts .= AssetHelper::js('/assets/js/pages/dashboard/index.js');
// 브레드크럼프 (레이아웃 내부에서 자동 포함되지 않으므로 본문에 포함)
$breadcrumb = [
    '홈' => '/dashboard',  
  ];
?>
<?php include_once __DIR__ . '/../layout/breadcrumb.php'; ?>

<!-- ✅ 메인 콘텐츠 -->
<main class="dashboard-main"> 

    <!-- 타이틀 -->
    <div>
      <h5 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h5>
      <p translate="no">로그인후 첫번째 페이지.</p>
    </div>

    <div class="row row-cols-6 g-2 mb-3" style="font-size: 0.85rem;">
        <div class="col"><div class="card bg-success text-white"><div class="card-body py-2"><h6>이번 달 매출</h6><p>$12,300</p></div></div></div>
        <div class="col"><div class="card bg-info text-white"><div class="card-body py-2"><h6>거래처 수</h6><p>53개</p></div></div></div>
        <div class="col"><div class="card bg-warning text-dark"><div class="card-body py-2"><h6>결재 대기</h6><p>5건</p></div></div></div>
        <div class="col"><div class="card bg-primary text-white"><div class="card-body py-2"><h6>최근 문서 등록</h6><p>12건</p></div></div></div>
        <div class="col"><div class="card bg-secondary text-white"><div class="card-body py-2"><h6>이번달 일정</h6><p>8건</p></div></div></div>
        <div class="col"><div class="card bg-dark text-white"><div class="card-body py-2"><h6>금일 접속자</h6><p>21명</p></div></div></div>
    </div>

    <div class="row g-2 flex-grow-1 mb-2">
        <div class="col-md-6 d-flex flex-column">
            <div class="card flex-grow-1"><div class="card-body py-2">
                <h6>📈 월별 매출 추이</h6>
                <div style="height: 260px;"><canvas id="salesChart"></canvas></div>
            </div></div>
            <div class="card mt-2"><div class="card-body py-2">
                <h6>📅 오늘의 일정</h6>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item py-1">09:00 팀 미팅</li>
                    <li class="list-group-item py-1">11:00 납품 일정 확인</li>
                    <li class="list-group-item py-1">15:00 결재 마감</li>
                </ul>
            </div></div>
        </div>
        <div class="col-md-6 d-flex flex-column">
            <div class="card flex-grow-1"><div class="card-body py-2">
                <h6>🕒 최근 등록 내역</h6>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item py-1">[결재] 출장보고서 - 07-05</li>
                    <li class="list-group-item py-1">[매출] 홍길동상사 - $2,100</li>
                    <li class="list-group-item py-1">[거래처] 우신금속 등록</li>
                </ul>
            </div></div>
            <div class="card mt-2"><div class="card-body py-2">
                <h6>📢 공지사항</h6>
                <p class="small mb-0">7월 10일 02:00~04:00 시스템 점검 예정<br>문의: IT팀 내선 204</p>
            </div></div>
        </div>
    </div>

    <div class="card mt-2">
        <div class="card-body py-2">
            <h6>🧭 빠른 바로가기</h6>
            <div class="d-flex flex-wrap gap-2 quick-links">
                <a href="/sukhyang/file_register" class="btn btn-outline-primary btn-sm">📄 문서 등록</a>
                <a href="/approval" class="btn btn-outline-success btn-sm">📝 결재 작성</a>
                <a href="/ledger/client" class="btn btn-outline-warning btn-sm">📇 거래처 등록</a>
                <a href="/dashboard/report" class="btn btn-outline-secondary btn-sm">📈 통합 보고서</a>
                <a href="/dashboard/calendar" class="btn btn-outline-info btn-sm">📅 일정관리</a>
            </div>
        </div>
    </div>
</main>