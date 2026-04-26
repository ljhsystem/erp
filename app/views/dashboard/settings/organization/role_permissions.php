<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/role_permissions.php'
?>

<div class="role-permission-page" id="rolePermissionPage">
    <div class="page-header">
        <h5 class="mb-1 fw-bold">역할별 권한 설정</h5>
    </div>

    <div class="rp-row">
        <div class="rp-col-left">
            <div class="card shadow-sm rp-card" id="roleListCard">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between">
                    <div>
                        역할목록
                        <span id="roleListCount" class="text-primary ms-2"></span>
                    </div>
                </div>
                <div class="card-body rp-card-body">
                    <table id="role-list-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:72px">순번</th>
                                <th>역할명</th>
                                <th class="text-center" style="width:96px">상태</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rp-col-right">
            <div class="card shadow-sm rp-card" id="permissionListCard">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between gap-3">
                    <div>
                        권한목록
                        <span id="rp-selected-role-name" class="text-primary ms-2"></span>
                        <span id="permission-count" class="text-muted small ms-2"></span>
                    </div>

                    <div id="permission-header" class="rp-actions" style="display:none;">
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" id="permission-check-all">
                            <label class="form-check-label small" for="permission-check-all">전체선택</label>
                        </div>
                        <button id="permission-save-btn" type="button" class="btn btn-sm btn-secondary">저장</button>
                    </div>
                </div>

                <div class="card-body rp-card-body">
                    <table id="role-permissions-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:72px">순번</th>
                                <th>카테고리</th>
                                <th>권한명</th>
                                <th>권한키</th>
                                <th>설명</th>
                                <th class="text-center">상태</th>
                                <th>생성일시</th>
                                <th>생성자</th>
                                <th>수정일시</th>
                                <th>수정자</th>
                                <th class="text-center no-colvis" style="width:112px">부여체크박스</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
