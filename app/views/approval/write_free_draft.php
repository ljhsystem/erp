<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_free_draft.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>
<?= AssetHelper::css('/assets/css/pages/approval/write_free_draft.css') ?>
<main class="approval-main">
    <h5 class="mb-4 fw-bold">📝 자유양식 기안서 작성</h5>

    <form action="#" method="post" enctype="multipart/form-data">
        <div class="mb-3 row">
            <label for="title" class="col-sm-2 col-form-label">기안 제목</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="title" name="title" placeholder="기안 제목을 입력하세요">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="writer" class="col-sm-2 col-form-label">기안자</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="writer" name="writer" value="<?php echo htmlspecialchars($username); ?>" readonly>
            </div>

            <label for="date" class="col-sm-2 col-form-label">작성일</label>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="date" name="date" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="content" class="col-sm-2 col-form-label">기안 내용</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="content" name="content" rows="8" placeholder="자유양식으로 내용을 입력하세요"></textarea>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="attachment" class="col-sm-2 col-form-label">첨부 파일</label>
            <div class="col-sm-10">
                <input type="file" class="form-control" id="attachment" name="attachment">
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">📤 제출</button>
            <button type="reset" class="btn btn-secondary">🔄 초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
