<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/employee/roles.php'
// 설명: 역할(Role) 관리 페이지
?>
<h5 class="fw-bold mb-3">역할(Role) 관리</h5>

<!-- 테이블 wrapper (초기 비표시 → JS에서 show 처리) -->
<div id="roles-table-wrapper" style="display:none;">
    <div class="table-responsive">
        <table id="roles-table" class="table table-bordered table-hover align-middle erp-table">
            <thead>
                <tr>
                    <th>코드</th>
                    <th>Role Key</th>
                    <th>Role Name</th>
                    <th>설명</th>
                    <th>활성</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- 역할 생성/수정 모달 -->
<?php include __DIR__ . '/partials/organization_roles_modal_edit.php'; ?>