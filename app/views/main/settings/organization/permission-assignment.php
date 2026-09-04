<?php
// Path: PROJECT_ROOT . '/app/views/main/settings/organization/permission-assignment.php'
$pageRegistryRows = isset($pageRegistryRows) && is_array($pageRegistryRows) ? $pageRegistryRows : [];
?>

<script>
window.ERP_PAGE_REGISTRY = <?= json_encode($pageRegistryRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<div class="role-permission-page" id="rolePermissionPage">
    <div class="page-header">
        <h5 class="mb-1 fw-bold">권한부여</h5>
    </div>

    <ul class="nav nav-tabs mb-3" id="permissionAssignmentTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="individual-permission-tab" data-bs-toggle="tab"
                    data-bs-target="#individual-permission-pane" type="button" role="tab">개별</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="role-permission-tab" data-bs-toggle="tab"
                    data-bs-target="#role-permission-pane" type="button" role="tab">역할별</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="individual-permission-pane" role="tabpanel">
            <div class="rp-row">
                <div class="rp-col-left">
                    <div class="card shadow-sm rp-card rp-table-pending" id="individualUserListCard">
                        <div class="card-header fw-bold">사용자목록 <span id="individualUserCount" class="text-primary ms-2"></span></div>
                        <div class="card-body rp-card-body" id="individualUserListCardBody" style="visibility:hidden;">
                            <table id="individual-user-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100">
                                <thead><tr><th>순번</th><th>로그인 ID</th><th>직원명</th><th>역할</th><th>상태</th><th>권한방식</th><th>개인권한 수</th><th>직원상태</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="rp-col-right">
                    <div class="card shadow-sm rp-card rp-table-pending" id="individualPermissionCard">
                        <div class="card-header fw-bold d-flex align-items-center justify-content-between gap-3">
                            <div>권한목록 <span id="individualSelectedUser" class="text-primary ms-2"></span>
                                <span id="individualPermissionCount" class="text-muted small ms-2"></span></div>
                            <div class="rp-actions d-flex align-items-center gap-2">
                                <select id="individualPermissionMode" class="form-select form-select-sm" disabled>
                                    <option value="ROLE">역할별</option><option value="EXTEND">역할 + 개인</option><option value="REPLACE">개인별</option>
                                </select>
                                <div class="form-check m-0">
                                    <input class="form-check-input" type="checkbox" id="individualPermissionCheckAll" disabled>
                                    <label class="form-check-label small" for="individualPermissionCheckAll">전체선택</label>
                                </div>
                                <button id="individualPermissionSave" type="button" class="btn btn-sm btn-secondary" disabled>저장</button>
                            </div>
                        </div>
                        <div class="card-body rp-card-body" id="individualPermissionCardBody" style="visibility:hidden;">
                            <table id="individual-permission-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100">
                                <thead><tr><th></th><th><i class="bi bi-arrows-move"></i></th><th>순번</th><th>페이지</th><th>구분</th><th>카테고리</th><th>권한명</th><th>권한설명</th><th>권한부여</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="role-permission-pane" role="tabpanel">

    <div class="rp-row">
        <div class="rp-col-left">
            <div class="card shadow-sm rp-card rp-table-pending" id="roleListCard">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between">
                    <div>
                        역할목록
                        <span id="roleListCount" class="text-primary ms-2"></span>
                    </div>
                </div>
                <div class="card-body rp-card-body" id="roleListCardBody" style="visibility:hidden;">
                    <table id="role-list-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:80px">순번</th>
                                <th style="width:160px">역할 키</th>
                                <th style="width:180px">역할명</th>
                                <th style="width:220px">설명</th>
                                <th class="text-center" style="width:90px">상태</th>
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

                    <div id="permission-header" class="rp-actions">
                        <div class="form-check m-0">
                            <input class="form-check-input" type="checkbox" id="permission-check-all" disabled>
                            <label class="form-check-label small" for="permission-check-all">전체선택</label>
                        </div>
                        <button id="permission-save-btn" type="button" class="btn btn-sm btn-secondary" disabled>저장</button>
                    </div>
                </div>

                <div class="card-body rp-card-body" id="permissionListCardBody" style="visibility:hidden;">
                    <div id="permission-assignment-table-wrap">
                        <table id="permission-assignment-table" class="table table-bordered table-hover table-cross-highlight align-middle w-100">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:36px"></th>
                                    <th class="text-center" style="width:56px"><i class="bi bi-arrows-move"></i></th>
                                    <th class="text-center" style="width:72px">순번</th>
                                    <th>페이지</th>
                                    <th class="text-center" style="width:80px">구분</th>
                                    <th>카테고리</th>
                                    <th>권한명</th>
                                    <th>권한설명</th>
                                    <th class="text-center" style="width:96px">권한부여</th>
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
    </div>
</div>
