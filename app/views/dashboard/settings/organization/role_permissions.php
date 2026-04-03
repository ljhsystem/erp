<?php
// 경로: PROJECT_ROOT . '/app/views/dashboard/settings/employee/role_permissions.php'
?>

<style>
/* 테이블 헤더 고정 */
table thead {
    position: sticky;
    top: 0;
    background-color: #fff;
    z-index: 100;
}

.table-container {
    max-height: 400px;
    overflow-y: auto;
}

/* 검색 + 카운트 + 전체선택 한 줄 정렬 */
#permission-header {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
}

#permission-count {
    white-space: nowrap; /* 줄바꿈 방지 */
    font-size: 13px;
    font-weight: bold;
}
#permission-save-btn {
    padding: 4px 12px !important;   /* 여유 있는 좌우 여백 */
    font-size: 12px !important;     /* 작은 글씨 */
    border-radius: 4px !important;
    height: auto !important;        /* 강제 높이 제거 */
    line-height: normal !important; /* 글씨가 눌리는 문제 해결 */
    white-space: nowrap !important; /* 글씨 줄바꿈 방지 */
}

</style>



<h5 class="fw-bold mb-3">역할별 권한 설정</h5>

<div class="row">
    <!-- 왼쪽: 역할 리스트 -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header fw-bold">역할 목록</div>
            <div class="card-body p-0">
                <table id="role-list-table" class="table table-bordered table-hover m-0">
                    <thead>
                        <tr>
                            <th>코드</th>
                            <th>역할명</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 오른쪽: 권한 목록 -->
    <div class="col-md-8">
        <div class="card shadow-sm">



        <div class="card-header fw-bold d-flex align-items-center justify-content-between">
            <div>
                권한 목록
                <span id="rp-selected-role-name" class="text-primary ms-2"></span>
            </div>

            <div id="permission-header" style="display: none;">          
                <!-- 검색창 -->
                <input type="text" id="permission-search" class="form-control form-control-sm"
                    placeholder="검색...">

                <!-- 개수 표시 -->
                <span id="permission-count" class="text-muted small"></span>

                <!-- 전체 선택 -->
                <div class="form-check ms-2">
                    <input class="form-check-input" type="checkbox" id="permission-check-all">
                    <label class="form-check-label small" for="permission-check-all"></label>
                </div>

                <!-- 저장 버튼 -->
                <button id="permission-save-btn" class="btn btn-sm btn-primary ms-2">
                    저장
                </button>

            </div>

            
        </div>




            </div>

            <div class="card-body p-0">
                <div class="table-container">
                    <table id="role-permissions-table" class="table table-bordered table-hover m-0">
                        <thead>
                            <tr>
                                <th>권한 Key</th>
                                <th>권한명</th>
                                <th>카테고리</th>
                                <th>부여</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>


