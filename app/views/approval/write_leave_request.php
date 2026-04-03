<?php
// 경로: PROJECT_ROOT . 'app/views/approval/write_leave_request.php'
use Core\Helpers\AssetHelper;
include(__DIR__ . '/../layout/header.php');
include(__DIR__ . '/../layout/sidebar.php');
?>
<?= AssetHelper::css('/assets/css/pages/approval/write_leave_request.css') ?>
<main class="approval-main">
    <h5 class="mb-4 fw-bold">휴가신청서 작성</h5>

    <form action="#" method="post">
        <div class="mb-3 row">
            <label for="leaveType" class="col-sm-2 col-form-label">휴가 유형</label>
            <div class="col-sm-10">
                <select class="form-select" id="leaveType" name="leaveType">
                    <option value="범주">범주</option>
                    <option value="계획">계획</option>
                    <option value="기타">기타</option>
                </select>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="startDate" class="col-sm-2 col-form-label">철열시작일</label>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="startDate" name="startDate">
            </div>

            <label for="endDate" class="col-sm-2 col-form-label">철열종료일</label>
            <div class="col-sm-4">
                <input type="date" class="form-control" id="endDate" name="endDate">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="reason" class="col-sm-2 col-form-label">신청 사유</label>
            <div class="col-sm-10">
                <textarea class="form-control" id="reason" name="reason" rows="5" placeholder="신청 사유를 입력해주세요."></textarea>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary">확인</button>
            <button type="reset" class="btn btn-secondary">초기화</button>
        </div>
    </form>
</main>

<?php include(__DIR__ . '/../layout/footer.php'); ?>
