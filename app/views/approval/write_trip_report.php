<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_trip_report.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/write_trip_report.css') ?>

<main class="approval-main">
    <h5 class="mb-4 fw-bold">핬장보고서 작성</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="title" class="col-sm-2 col-form-label">보고서 제목</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="title" name="title" placeholder="핬장 보고서 제목 입력">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="destination" class="col-sm-2 col-form-label">핬장지</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="destination" name="destination" placeholder="핬장지 입력">
            </div>

            <label for="duration" class="col-sm-2 col-form-label">기간</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="duration" name="duration" placeholder="2025-07-10 ~ 2025-07-12">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="purpose" class="col-sm-2 col-form-label">목적</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="purpose" name="purpose" placeholder="핬장 목적 입력">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="content" class="col-sm-2 col-form-label">결과 및 노후</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="content" name="content" rows="6" placeholder="핬장 결과 및 보고 내용 기입"></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">파일 저장</button>
            <button type="reset" class="btn btn-secondary">초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
