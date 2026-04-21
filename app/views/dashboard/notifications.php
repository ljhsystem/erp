<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/notifications.php'
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
$pageStyles = AssetHelper::css('/assets/css/pages/dashboard/notifications.css');
$pageScripts = '';
// 브레드크럼프 (본문에 포함)
?>
<!-- ✅ 메인 콘텐츠 -->
<main class="notifications-main">

    <!-- 타이틀 -->
    <div>
      <h5 translate="no"><?= htmlentities($pageTitle, ENT_NOQUOTES, 'UTF-8', false) ?></h5>
      <p translate="no">공지사항 페이지.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="fw-bold mb-3">📋 최근 공지 목록</h6>

            <ul class="list-group list-group-flush notification-list">
                <li class="list-group-item">
                    <strong>[시스템 점검]</strong> 7월 10일(수) 02:00~04:00까지 서버 점검이 있습니다.<br>
                    <small class="text-muted">작성일: 2025-07-05</small>
                </li>
                <li class="list-group-item">
                    <strong>[휴가 신청 안내]</strong> 하계휴가 신청은 7월 15일까지 각 팀장에게 제출 바랍니다.<br>
                    <small class="text-muted">작성일: 2025-07-03</small>
                </li>
                <li class="list-group-item">
                    <strong>[보안교육]</strong> 전사 보안교육은 7월 8일 오후 2시에 진행됩니다. 장소: 회의실 B.<br>
                    <small class="text-muted">작성일: 2025-07-01</small>
                </li>
                <li class="list-group-item">
                    <strong>[정기회의]</strong> 7월 팀장회의는 7월 9일 오전 10시, 본관 회의실 A에서 열립니다.<br>
                    <small class="text-muted">작성일: 2025-06-29</small>
                </li>
            </ul>
        </div>
    </div>
</main>



