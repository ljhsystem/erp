<?php
// 📄 /app/views/institution/index.php
use Core\Helpers\AssetHelper;

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/dashboard/institution/index.css') ?>

<main class="institution-dashboard">
    <h2 class="page-title">🏢 기관업무 대시보드</h2>
    <p class="page-subtitle">주요 기관별 신고 및 관리 현황을 한눈에 확인할 수 있습니다.</p>

    <div class="grid-container">
        <div class="card-box">
            <h4>🧾 세무서(국세)</h4>
            <p>국세 신고 및 관리 현황</p>
        </div>
        <div class="card-box">
            <h4>🏛️ 지방자치단체(지방세)</h4>
            <p>지방세 신고 및 관리 현황</p>
        </div>
        <div class="card-box">
            <h4>👷 근로복지공단</h4>
            <p>보수총액신고, 고용산재근로내용확인신고</p>
        </div>
        <div class="card-box">
            <h4>🏥 건강보험공단</h4>
            <p>건강보험 신고 현황</p>
        </div>
        <div class="card-box">
            <h4>💳 국민연금관리공단</h4>
            <p>국민연금 신고 현황</p>
        </div>
        <div class="card-box">
            <h4>🔒 신용보증기금</h4>
            <p>신용보증 관리 현황</p>
        </div>
        <div class="card-box">
            <h4>🏗️ 대한전문건설협회</h4>
            <p>실적신고 현황</p>
        </div>
        <div class="card-box">
            <h4>🛡️ 전문건설공제조합</h4>
            <p>보증 및 공제 관리 현황</p>
        </div>
        <div class="card-box">
            <h4>👨‍🔧 기술인협회</h4>
            <p>상용근로자 경력신고 현황</p>
        </div>
        <div class="card-box">
            <h4>👷‍♂️ 건설근로자공제회</h4>
            <p>퇴직공제부금 신고 현황</p>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
