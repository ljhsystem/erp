<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/approval-template.php'
?>

<div class="approval-page" id="approvalPage">
    <div class="page-header">
        <h5 class="mb-1 fw-bold">전자 결재템플릿 설정</h5>
    </div>

    <div class="approval-row">
        <div class="approval-col-left">
            <div class="card shadow-sm approval-card" id="approvalTemplateCard">
                <div class="card-header fw-bold">
                    <div>
                        결재 템플릿 목록
                        <span id="approvalTemplateCount" class="text-primary ms-2"></span>
                    </div>
                </div>

                <div class="card-body approval-card-body">
                    <table id="template-list-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100"></table>
                </div>
            </div>
        </div>

        <div class="approval-col-right">
            <div class="card shadow-sm approval-card" id="approvalStepCard">
                <div class="card-header fw-bold">
                    <div>
                        결재 단계 구성
                        <span id="ap-selected-template-name" class="text-primary ms-2"></span>
                        <span id="approvalStepCount" class="text-muted ms-2"></span>
                    </div>
                </div>

                <div class="card-body approval-card-body">
                    <table id="template-steps-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/approval_templates_modal.php'; ?>
<?php include __DIR__ . '/partials/approval_step_modal.php'; ?>
