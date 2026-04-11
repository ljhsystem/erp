<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/employee/positions.php'
// 설명: 직원관리 → 직책(직급) 관리
?>

<h5 class="fw-bold mb-3">직책 관리</h5>

<!-- 테이블 래퍼 (처음엔 숨김 처리) -->
<div id="positions-table-wrapper" style="display:none;">
    <div class="table-responsive">
        <table id="positions-table" class="table table-bordered table-hover align-middle erp-table">
            <thead>
                <tr>
                    <th>코드</th>
                    <th>직책명</th>
                    <th>레벨</th>
                    <th>설명</th>
                    <th>활성</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- 모달 Include -->
<?php include __DIR__ . '/partials/positions_modal.php'; ?>