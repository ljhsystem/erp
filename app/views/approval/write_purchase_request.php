<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_purchase_request.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>

<?= AssetHelper::css('/assets/css/pages/approval/write_purchase_request.css') ?>

<main class="approval-main">
    <h5 class="mb-4 fw-bold">🛒 구매요청서 (발주요청) 작성</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="title" class="col-sm-2 col-form-label">구매 제목</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="title" name="title" placeholder="구매 제목 입력">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="items" class="col-sm-2 col-form-label">구매 항목</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="items" name="items" rows="4" placeholder="건너 리스트로 입력 ex) 자유 10ea, 칼럼 3set"></textarea>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="vendor" class="col-sm-2 col-form-label">구매 계정/거래차</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="vendor" name="vendor" placeholder="거래차 명">
            </div>

            <label for="delivery_date" class="col-sm-2 col-form-label">교리 예정일</label>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="delivery_date" name="delivery_date">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="remarks" class="col-sm-2 col-form-label">비고</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="구매 이유 및 참고 사항"></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success">💾 저장</button>
            <button type="reset" class="btn btn-secondary">🔄 초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
