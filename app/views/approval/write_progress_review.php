<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_progress_review.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/write_progress_review.css') ?>

<main class="approval-main">
    <h5 class="mb-4 fw-bold">🧾 기성검토요청서 작성</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="project" class="col-sm-2 col-form-label">공사명 / 프로젝트</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="project" name="project" placeholder="기성 검토할 공사 또는 프로젝트명 입력">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="reviewer" class="col-sm-2 col-form-label">검토 요청자</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="reviewer" name="reviewer" value="<?php echo htmlspecialchars($username); ?>" readonly>
            </div>

            <label for="date" class="col-sm-2 col-form-label">요청일자</label>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="period" class="col-sm-2 col-form-label">기성 기간</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="period" name="period" placeholder="예: 2025-06-01 ~ 2025-06-30">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="amount" class="col-sm-2 col-form-label">기성 금액</label>
            <div class="col-sm-4">
                <input type="number" class="form-control" id="amount" name="amount" placeholder="금액 입력 (숫자만)">
            </div>

            <label for="rate" class="col-sm-2 col-form-label">진행률 (%)</label>
            <div class="col-sm-4">
                <input type="number" class="form-control" id="rate" name="rate" placeholder="예: 85">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="description" class="col-sm-2 col-form-label">기성 내역 설명</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="description" name="description" rows="6" placeholder="이번 기성 검토 요청에 대한 상세 내용을 작성해주세요."></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">💾 저장</button>
            <button type="reset" class="btn btn-secondary">🔄 초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
