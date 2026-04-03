<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_work_report.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/write_work_report.css') ?>

<main class="approval-main">
    <h5 class="mb-4 fw-bold">📄 업무보고서 작성</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="reportTitle" class="col-sm-2 col-form-label">보고서 제목</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="reportTitle" name="reportTitle" placeholder="업무 보고서 제목 입력">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="workDate" class="col-sm-2 col-form-label">수행 날짜</label>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="workDate" name="workDate">
            </div>

            <label for="department" class="col-sm-2 col-form-label">소속</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="department" name="department" placeholder="업무 수행 소속">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="content" class="col-sm-2 col-form-label">내용</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="content" name="content" rows="6" placeholder="업무 수행 내용을 삽입해주세요"></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">💾 저장</button>
            <button type="reset" class="btn btn-secondary">🔄 초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
