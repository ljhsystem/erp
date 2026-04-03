<?php
// 📄 경로: /app/views/notice/index.php
use Core\Helpers\AssetHelper;
if (!isset($_SESSION['username'])) {
    header("Location: /login/login.php");
    exit;
}

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/dashboard/notice/index.css') ?>

<main class="notice-dashboard">
    <h2 class="page-title">📢 공지사항 대시보드</h2>
    <p class="page-subtitle">전체 공지, 직원별, 부서별 공지 현황을 한눈에 확인할 수 있습니다.</p>

    <div class="grid-container">
        <div class="card-box">
            <h4>📋 공지대시보드</h4>
            <p>공지 확인여부, 최근 공지, 미확인 공지 등 요약</p>
        </div>
        <div class="card-box">
            <h4>👤 직원별공지</h4>
            <p>직원별로 전달된 공지 및 확인 현황</p>
        </div>
        <div class="card-box">
            <h4>🏢 부서별공지</h4>
            <p>부서별 공지사항 및 확인 현황</p>
        </div>
        <div class="card-box">
            <h4>🌐 전체공지</h4>
            <p>전체 대상 공지 및 확인 현황</p>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>