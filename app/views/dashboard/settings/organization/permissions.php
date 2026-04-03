<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/employee/permissions.php'
// 설명: 권한 상세 리스트
?>

<h5 class="fw-bold mb-3">권한 상세 리스트</h5>

<!-- 테이블 wrapper (초기 비표시 → JS에서 show) -->
<div id="permissions-table-wrapper" style="display:none;">
    <div class="table-responsive">
        <table id="permissions-table" class="table table-bordered table-hover align-middle erp-table">
            <thead>
                <tr>
                    <th>코드</th>
                    <th>Permission Key</th>
                    <th>Permission Name</th>
                    <th>설명</th>
                    <th>카테고리</th>
                    <th>활성</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- 모달 Include -->
<?php include __DIR__ . '/partials/organization_permissions_modal_edit.php'; ?>