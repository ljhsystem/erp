<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_expenditure.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>
<?= AssetHelper::css('/assets/css/pages/approval/write_expenditure.css') ?>

<main class="approval-main">
    <h5 class="mb-4 fw-bold">💰 지출결의서 작성</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="title" class="col-sm-2 col-form-label">지출 제목</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="title" name="title" placeholder="지출 제목 입력">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="amount" class="col-sm-2 col-form-label">결제 금액</label>
            <div class="col-sm-4">
                <input type="number" class="form-control" id="amount" name="amount" placeholder="금액 입력">
            </div>

            <label for="date" class="col-sm-2 col-form-label">결제 일자</label>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="date" name="date">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="recipient" class="col-sm-2 col-form-label">결제 대상자/거래처</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="recipient" name="recipient" placeholder="대상자 또는 거래처 입력">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="content" class="col-sm-2 col-form-label">지출 사유</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="content" name="content" rows="5" placeholder="지출 사유를 입력하세요"></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">💾 저장</button>
            <button type="reset" class="btn btn-secondary">🔄 초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
