<?php
// 📄 경로: /app/views/sukhyang/view.php
use Core\Helpers\AssetHelper;
if (!isset($_SESSION['username'])) {
    header("Location: /login/login.php");
    exit;
}
$username = $_SESSION['username'];

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/sukhyang/view.css') ?>

<main class="sukhyang-view-main">
    <h5 class="page-title">🔍 문서 상세 보기</h5>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">📄 출장보고서 - 홍길동</h6>

            <div class="mb-2">
                <strong>작성자:</strong> 홍길동<br>
                <strong>작성일:</strong> 2025-07-06<br>
                <strong>문서번호:</strong> DOC-20250706-001
            </div>

            <hr>

            <div class="mb-3">
                <strong>내용 요약</strong>
                <p class="small mt-1">
                    7월 4일~6일 부산 출장을 다녀온 내용 보고드립니다. 총 3건의 미팅을 진행하였으며,
                    주요 클라이언트 반응은 긍정적이었습니다.
                </p>
            </div>

            <div class="mb-3">
                <strong>첨부파일</strong><br>
                <a href="/uploads/documents/trip_report.pdf" class="btn btn-outline-secondary btn-sm mt-1" download>📎 trip_report.pdf</a>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <a href="/dashboard/sukhyang/edit.php" class="btn btn-warning btn-sm">✏️ 문서 수정</a>
                <a href="/dashboard/sukhyang/index.php" class="btn btn-secondary btn-sm ms-2">↩️ 목록으로</a>
            </div>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>