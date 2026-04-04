<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/employee/approval.php';
?>
<h5 class="fw-bold mb-3">결재 템플릿 설정</h5>

<div class="row">

    <!-- ========================== 템플릿 리스트 ========================== -->
    <div class="col-md-6">
        <div class="card shadow-sm">

            <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                결재 템플릿 목록
            </div>

            <div class="card-body">

                <!-- 버튼 그룹 -->
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-primary btn-sm" id="btn-create-template">
                        + 새 템플릿
                    </button>
                </div>

                <table id="template-list-table" class="table table-bordered table-hover m-0">
                    <thead>
                        <tr>
                            <th>템플릿이름</th>
                            <th>문서유형</th>
                            <th>설명</th>
                            <th>활성</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

            </div>
        </div>
    </div>

    <!-- ========================== 템플릿 스텝 리스트 ========================== -->
    <div class="col-md-6">
        <div class="card shadow-sm">

            <div class="card-header fw-bold">
                결재 스텝 구성
                <span id="ap-selected-template-name" class="ms-2 text-primary"></span>
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <button id="btn-add-step" class="btn btn-success btn-sm" disabled>
                        + 결재 스텝 추가
                    </button>
                </div>

                <table id="template-steps-table" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th style="width:60px">순서</th>
                            <th>단계이름</th>
                            <th>결재자역할</th>
                            <th>특정결재자</th>
                            <th>활성</th>
                        </tr>
                    </thead>
                    <tbody id="steps-sortable">
                        <!-- 스텝 목록 AJAX로 로딩됨 -->
                    </tbody>
                </table>

                <p class="text-muted small mt-2">
                    ※ 스텝은 드래그하여 순서를 변경하면 자동 저장됩니다.
                </p>

            </div>
        </div>
    </div>

</div>

<!-- ====================== 모달 Include ====================== -->
<?php //include __DIR__ . '/partials/organization_approval_templates_modal_create.php'; ?>
<?php include __DIR__ . '/partials/organization_approval_templates_modal_edit.php'; ?>
<?php //include __DIR__ . '/partials/organization_approval_step_modal_create.php'; ?>
<?php include __DIR__ . '/partials/organization_approval_step_modal_edit.php'; ?>