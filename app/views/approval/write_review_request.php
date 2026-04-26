<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_review_request.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/write_review_request.css') ?>

<main class="approval-main">
    <h5 class="mb-4 fw-bold">📝 실행검토요청서 작성</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="project" class="col-sm-2 col-form-label">프로젝트명</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="project" name="project" placeholder="검토할 프로젝트 또는 업무명을 입력">
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
            <label for="reason" class="col-sm-2 col-form-label">요청 사유</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="reason" name="reason" rows="4" placeholder="검토를 요청하는 배경과 목적을 설명해주세요."></textarea>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="details" class="col-sm-2 col-form-label">상세 내용</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="details" name="details" rows="6" placeholder="실행 검토가 필요한 내용을 구체적으로 작성해주세요."></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">💾 저장</button>
            <button type="reset" class="btn btn-secondary">🔄 초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
