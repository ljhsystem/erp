<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_foreign_remit.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>
<?= AssetHelper::css('/assets/css/pages/approval/write_foreign_remit.css') ?>
<main class="approval-main">
    <h5 class="mb-4 fw-bold">💱 외화송금결재요청서</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="title" class="col-sm-2 col-form-label">제목</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="title" name="title" placeholder="예: 자재 입고 외화송금 요청">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="amount" class="col-sm-2 col-form-label">송금 금액</label>
            <div class="col-sm-4">
                <input type="number" class="form-control" id="amount" name="amount" placeholder="USD 기준">
            </div>

            <label for="currency" class="col-sm-2 col-form-label">통화</label>
            <div class="col-sm-4">
                <select class="form-select" id="currency" name="currency">
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="JPY">JPY</option>
                    <option value="CNY">CNY</option>
                </select>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="beneficiary" class="col-sm-2 col-form-label">수취인</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="beneficiary" name="beneficiary" placeholder="회사명 또는 개인명">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="bankInfo" class="col-sm-2 col-form-label">은행 정보</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="bankInfo" name="bankInfo" rows="3" placeholder="은행명, 계좌번호, SWIFT코드 등"></textarea>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="description" class="col-sm-2 col-form-label">송금 사유</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="description" name="description" rows="5" placeholder="외화 송금 요청 사유를 입력하세요"></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">💾 저장</button>
            <button type="reset" class="btn btn-secondary">🔄 초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
