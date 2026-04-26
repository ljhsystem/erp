<?php
// 📄 경로: /app/views/sukhyang/index.php
use Core\Helpers\AssetHelper;

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<!-- 📦 스타일 적용 -->
<?= AssetHelper::css('/assets/css/pages/sukhyang/index.css') ?>


<main class="sukhyang-main">
    <h5 class="page-title">📁 석향서류 대시보드</h5>

    <div class="row row-cols-md-3 g-3 mb-3">
        <div class="col">
            <div class="card summary-card bg-primary text-white">
                <div class="card-body">
                    <h6>총 문서 수</h6>
                    <p class="fs-4 mb-0">142건</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card summary-card bg-success text-white">
                <div class="card-body">
                    <h6>최근 등록 문서</h6>
                    <p class="fs-6 mb-0">2025-07-06 홍길동 출장보고서</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card summary-card bg-warning text-dark">
                <div class="card-body">
                    <h6>결재 대기 문서</h6>
                    <p class="fs-4 mb-0">5건</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h6 class="fw-bold mb-3">🕒 최근 문서 등록 내역</h6>
            <ul class="list-group list-group-flush small doc-list">
                <li class="list-group-item py-1">2025-07-06 출장보고서 - 홍길동</li>
                <li class="list-group-item py-1">2025-07-05 회의록 - 전지현</li>
                <li class="list-group-item py-1">2025-07-04 계약서 - 김영희</li>
            </ul>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
