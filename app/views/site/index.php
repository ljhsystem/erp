<?php
// 📄 경로: /app/views/site/index.php
use Core\Helpers\AssetHelper;

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/dashboard/site/index.css') ?>

<main class="site-dashboard">
    <h2 class="page-title">🏗️ 현장관리 대시보드</h2>
    <p class="page-subtitle">현장별 주요 현황과 진행상황을 한눈에 확인할 수 있습니다.</p>

    <div class="grid-container">
        <div class="card-box">
            <h4>📑 견적관리</h4>
            <p>견적서 등록/조회, 견적 비교, 승인 내역</p>
        </div>
        <div class="card-box">
            <h4>📄 계약관리</h4>
            <p>계약서 등록/조회, 계약 조건, 변경 이력</p>
        </div>
        <div class="card-box">
            <h4>📝 실행관리</h4>
            <p>실행 예산, 실행 내역, 실행 계획 관리</p>
        </div>
        <div class="card-box">
            <h4>🔒 보증/보험관리</h4>
            <p>보증서, 보험증권 등록/조회, 만기 관리</p>
        </div>
        <div class="card-box">
            <h4>📈 기성확정내역</h4>
            <p>공사 진행분 확정 내역, 기성금 청구 관리</p>
        </div>
        <div class="card-box">
            <h4>🏢 시공기성확정내역</h4>
            <p>시공별 기성 확정, 시공업체별 내역 관리</p>
        </div>
        <div class="card-box">
            <h4>💳 거래내역</h4>
            <p>현장별 거래명세서 관리</p>
        </div>
        <div class="card-box">
            <h4>🦺 안전관리</h4>
            <p>각 현장별 안전점검, 사고/위험 보고, 안전교육 이력</p>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
