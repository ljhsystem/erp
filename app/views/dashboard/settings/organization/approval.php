<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/approval.php'
?>

<div class="approval-page" id="approvalPage">
    <h5 class="fw-bold mb-3">결재템플릿 설정</h5>

    <div class="approval-row">
        <div class="approval-col-left">
            <div class="card shadow-sm approval-card" id="approvalTemplateCard">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between">
                    <div>
                        결재 템플릿 목록
                        <span id="approvalTemplateCount" class="text-primary ms-2"></span>
                    </div>
                    <button class="btn btn-primary btn-sm" id="btn-create-template">새 템플릿</button>
                </div>

                <div class="card-body approval-card-body">
                    <div class="approval-table-wrap">
                        <table id="template-list-table" class="table table-bordered table-hover m-0">
                            <thead>
                                <tr>
                                    <th>템플릿명</th>
                                    <th>문서유형</th>
                                    <th>설명</th>
                                    <th>상태</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="approval-col-right">
            <div class="card shadow-sm approval-card" id="approvalStepCard">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between">
                    <div>
                        결재 단계 구성
                        <span id="ap-selected-template-name" class="text-primary ms-2"></span>
                        <span id="approvalStepCount" class="text-muted ms-2"></span>
                    </div>
                    <button id="btn-add-step" class="btn btn-success btn-sm" disabled>단계 추가</button>
                </div>

                <div class="card-body approval-card-body">
                    <div class="approval-table-wrap">
                        <table id="template-steps-table" class="table table-bordered table-hover m-0">
                            <thead>
                                <tr>
                                    <th class="approval-reorder-col text-center" style="width:44px"><i class="bi bi-arrows-move"></i></th>
                                    <th style="width:72px">코드</th>
                                    <th>단계명</th>
                                    <th>결재 역할</th>
                                    <th>지정 결재자</th>
                                    <th>상태</th>
                                </tr>
                            </thead>
                            <tbody id="steps-sortable"></tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer small text-muted">
                    왼쪽 손잡이를 드래그해서 단계 순서를 변경할 수 있습니다.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/approval_templates_modal.php'; ?>
<?php include __DIR__ . '/partials/approval_step_modal.php'; ?>
