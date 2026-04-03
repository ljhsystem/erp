<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/employee/departments.php'
// 설명: 직원관리 → 부서 관리
?>
<h5 class="fw-bold mb-3">부서 관리</h5>

<div id="dept-table-wrapper" style="display:none;">
    <div class="table-responsive">
    <table id="dept-table" class="table table-bordered table-hover align-middle erp-table">
           
            <thead>
                <tr>
                    <th style="width:30px;"></th>
                    <th>코드</th>
                    <th>부서명</th>
                    <th>부서장</th>
                    <th>설명</th>
                    <th>활성</th>
                </tr>
            </thead>

            <tbody></tbody>
        </table>
    </div>
</div>

<!-- 모달 Include -->
<?php include __DIR__ . '/partials/organization_dept_modal_edit.php'; ?>