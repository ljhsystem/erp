<?php
// 파일 경로: /app/views/sukhyang/file_register.php
use Core\Helpers\AssetHelper;

include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<!-- 파일 연결: 컬렉션 스타일 -->
<?= AssetHelper::css('/assets/css/pages/sukhyang/file_register.css') ?>

<main class="file-register-main">
    <h5 class="page-title">📝 문서 등록</h5>

    <div class="card">
        <div class="card-body">
            <form method="post" action="file_register_process.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="docTitle" class="form-label">문서 제목</label>
                    <input type="text" class="form-control" id="docTitle" name="docTitle" required>
                </div>
                <div class="mb-3">
                    <label for="docType" class="form-label">문서 유형</label>
                    <select class="form-select" id="docType" name="docType">
                        <option value="참여자 메뉴">참여자 메뉴</option>
                        <option value="보고서">보고서</option>
                        <option value="통지"> 통지</option>
                        <option value="귀사서">귀사서</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="docDate" class="form-label">등록 날짜</label>
                    <input type="date" class="form-control" id="docDate" name="docDate" required>
                </div>
                <div class="mb-3">
                    <label for="uploadedFile" class="form-label">파일 업로드</label>
                    <input type="file" class="form-control" id="uploadedFile" name="uploadedFile" accept=".pdf,.doc,.docx,.xls,.xlsx">
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">등록</button>
                    <a href="index.php" class="btn btn-secondary">취소</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
