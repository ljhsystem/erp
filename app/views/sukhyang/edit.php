<?php
// 📄 경로: /app/views/sukhyang/edit.php
use Core\Helpers\AssetHelper;
if (!isset($_SESSION['username'])) {
    header("Location: /login/login.php");
    exit;
}
$username = $_SESSION['username'];

// 임시 샘플 데이터 (DB 연동 시 GET['id'] 등을 통해 조회)
$doc = [
    'title' => '출장보고서 - 홍길동',
    'author' => '홍길동',
    'date' => '2025-07-06',
    'summary' => '7월 4~6일 부산 출장 내용 보고...',
    'file' => 'trip_report.pdf'
];

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/sukhyang/edit.css') ?>

<main class="sukhyang-edit-main">
    <h5 class="page-title">✏️ 문서 수정</h5>

    <form action="update_process.php" method="post" enctype="multipart/form-data">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">문서 제목</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($doc['title']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">작성자</label>
                    <input type="text" name="author" class="form-control" value="<?= htmlspecialchars($doc['author']) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">작성일</label>
                    <input type="date" name="date" class="form-control" value="<?= $doc['date'] ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">요약 내용</label>
                    <textarea name="summary" class="form-control" rows="4"><?= htmlspecialchars($doc['summary']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">첨부파일 (기존: <?= $doc['file'] ?>)</label>
                    <input type="file" name="attachment" class="form-control">
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">저장</button>
                    <a href="/dashboard/sukhyang/view.php" class="btn btn-secondary ms-2">취소</a>
                </div>
            </div>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>