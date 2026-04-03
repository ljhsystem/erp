<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/activity.php'
use Core\Helpers\AssetHelper;
// 페이지 캐싱 방지
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
// 페이지별 스타일만 필요 (JS 없음)
$pageStyles  = $pageStyles  ?? '';
$pageScripts = $pageScripts ?? '';
$pageStyles = AssetHelper::css('/assets/css/pages/dashboard/activity.css');
$pageScripts = '';
// 브레드크럼프 (본문에 포함)
$breadcrumb = [
    '홈' => '/dashboard',
    '최근활동' => '/dashboard/activity'
  ];
?>
<?php include_once __DIR__ . '/../layout/breadcrumb.php'; ?>

<!-- ✅ 메인 콘텐츠 -->
<main class="activity-main">

    <!-- 타이틀 -->
    <div>
      <h5 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h5>
      <p translate="no">최근 활동 내역 페이지.</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-bold mb-3">📋 오늘의 활동 내역</h6>
            <ul class="list-group list-group-flush activity-list">
                <li class="list-group-item"><span class="time">09:10</span> 📄 문서 "2025년 7월 회의록" 등록</li>
                <li class="list-group-item"><span class="time">10:25</span> 📝 기안서 "출장신청서" 작성</li>
                <li class="list-group-item"><span class="time">11:15</span> 📈 보고서 열람 - "6월 매출 현황"</li>
                <li class="list-group-item"><span class="time">13:40</span> 📇 거래처 "우신금속" 등록</li>
                <li class="list-group-item"><span class="time">15:00</span> ✅ 결재 완료 - "거래처 계약서"</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="fw-bold mb-3">📅 최근 7일간 활동</h6>
            <table class="table table-hover table-sm text-center small">
                <thead class="table-light">
                    <tr>
                        <th>날짜</th>
                        <th>시간</th>
                        <th>활동 유형</th>
                        <th>내용</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>07-06</td><td>10:12</td><td>문서 등록</td><td>설계도면 3차</td></tr>
                    <tr><td>07-05</td><td>14:45</td><td>결재 완료</td><td>출장보고서</td></tr>
                    <tr><td>07-05</td><td>09:30</td><td>문서 열람</td><td>프로젝트 계획서</td></tr>
                    <tr><td>07-04</td><td>16:20</td><td>거래처 등록</td><td>대양산업</td></tr>
                    <tr><td>07-04</td><td>11:00</td><td>기안서 작성</td><td>회의비 신청</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>