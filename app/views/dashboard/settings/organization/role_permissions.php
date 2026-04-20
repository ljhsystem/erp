<?php
// Path: PROJECT_ROOT . '/app/views/dashboard/settings/organization/role_permissions.php'
?>

<div class="role-permission-page" id="rolePermissionPage">
    <h5 class="fw-bold mb-3">역할별 권한 설정</h5>

    <div class="rp-row">
        <div class="rp-col-left">
            <div class="card shadow-sm rp-card" id="roleListCard">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between">
                    <div>
                        역할 목록
                        <span id="roleListCount" class="text-primary ms-2"></span>
                    </div>
                </div>
                <div class="card-body rp-card-body">
                    <div class="rp-table-wrap" id="roleListWrap">
                        <table id="role-list-table" class="table table-bordered table-hover m-0">
                            <thead>
                                <tr>
                                    <th style="width:72px">코드</th>
                                    <th>역할명</th>
                                    <th style="width:96px">상태</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="rp-col-right">
            <div class="card shadow-sm rp-card" id="permissionListCard">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between">
                    <div>
                        권한 목록
                        <span id="rp-selected-role-name" class="text-primary ms-2"></span>
                    </div>

                    <div id="permission-header" style="display: none;">
                        <input type="text" id="permission-search" class="form-control form-control-sm" placeholder="검색..">
                        <span id="permission-count" class="text-muted small"></span>

                        <div class="form-check ms-2">
                            <input class="form-check-input" type="checkbox" id="permission-check-all">
                            <label class="form-check-label small" for="permission-check-all"></label>
                        </div>

                        <button id="permission-save-btn" class="btn btn-sm btn-secondary ms-2">저장</button>
                    </div>
                </div>

                <div class="card-body rp-card-body">
                    <div class="rp-table-wrap" id="permissionListWrap">
                        <table id="role-permissions-table" class="table table-bordered table-hover m-0">
                            <thead>
                                <tr>
                                    <th>카테고리</th>
                                    <th>권한명</th>
                                    <th>권한키</th>
                                    <th style="width:88px">부여</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
