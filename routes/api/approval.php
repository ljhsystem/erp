<?php

global $router;

$router->post('/api/auth/approval/approve', 'UserApprovalController@apiApprove', [
    'key' => 'api.auth.user.approve',
    'page' => '사용자승인',
    'page_description' => '사용자승인 관리',
    'permission_name' => '승인',
    'permission_description' => '사용자 승인',
    'name' => '사용자승인 승인',
    'description' => '인증 > 가입승인 > 사용자승인 > 승인',
    'category' => '인증 > 가입승인',
    'auth' => false,
    'skip_permission' => true,
    'log' => true,
]);

$approvalInboxRoutes = [
    ['POST', '/api/approval/inbox/list', 'apiList', 'list', '목록조회', 'view'],
    ['GET', '/api/approval/inbox/detail', 'apiDetail', 'detail', '상세조회', 'view'],
    ['POST', '/api/approval/inbox/act', 'apiAct', 'act', '결재처리', 'save'],
];
foreach ($approvalInboxRoutes as [$method, $url, $action, $keySuffix, $permissionName, $permission]) {
    $router->{strtolower($method)}($url, 'ApprovalInboxController@' . $action, [
        'key' => 'api.approval.inbox.' . $keySuffix,
        'page' => '결재함',
        'page_description' => '통합 전자결재 문서함',
        'permission_name' => $permissionName,
        'permission_description' => '결재함 ' . $permissionName,
        'name' => '결재함 ' . $permissionName,
        'description' => '전자결재 > 결재함 > ' . $permissionName,
        'category' => '전자결재',
        'auth' => true,
        'permissions' => [$permission],
        'log' => $method !== 'GET' && $action !== 'apiList',
    ]);
}

$personalExpenseRoutes = [
    ['POST', '/api/approval/personal-expense/list', 'apiList', 'list', '목록조회', 'view'],
    ['GET', '/api/approval/personal-expense/detail', 'apiDetail', 'detail', '상세조회', 'view'],
    ['POST', '/api/approval/personal-expense/save', 'apiSave', 'save', '저장', 'save'],
    ['POST', '/api/approval/personal-expense/reorder', 'apiReorder', 'reorder', '정렬저장', 'save'],
    ['POST', '/api/approval/personal-expense/delete', 'apiDelete', 'delete', '삭제', 'delete'],
    ['GET', '/api/approval/personal-expense/trash', 'apiTrashList', 'trash', '휴지통조회', 'view'],
    ['POST', '/api/approval/personal-expense/restore', 'apiRestore', 'restore', '복구', 'save'],
    ['POST', '/api/approval/personal-expense/restore-bulk', 'apiRestoreBulk', 'restore-bulk', '일괄복구', 'save'],
    ['POST', '/api/approval/personal-expense/restore-all', 'apiRestoreAll', 'restore-all', '전체복구', 'save'],
    ['POST', '/api/approval/personal-expense/purge', 'apiPurge', 'purge', '완전삭제', 'delete'],
    ['POST', '/api/approval/personal-expense/purge-bulk', 'apiPurgeBulk', 'purge-bulk', '일괄완전삭제', 'delete'],
    ['POST', '/api/approval/personal-expense/purge-all', 'apiPurgeAll', 'purge-all', '전체완전삭제', 'delete'],
    ['POST', '/api/approval/personal-expense/save-submit', 'apiSaveAndSubmit', 'save-submit', '저장후결재요청', 'save'],
    ['POST', '/api/approval/personal-expense/withdraw', 'apiWithdraw', 'withdraw', '기안회수', 'save'],
    ['GET', '/api/approval/personal-expense/template', 'apiTemplate', 'template', '엑셀양식다운로드', 'view'],
    ['POST', '/api/approval/personal-expense/excel', 'apiExcel', 'excel', '엑셀다운로드', 'view'],
    ['POST', '/api/approval/personal-expense/excel-upload', 'apiExcelUpload', 'excel-upload', '엑셀업로드', 'save'],
];
foreach ($personalExpenseRoutes as [$method, $url, $action, $keySuffix, $permissionName, $permission]) {
    $router->{strtolower($method)}($url, 'PersonalExpenseController@' . $action, [
        'key' => 'api.approval.personal-expense.' . $keySuffix, 'page' => '개인경비 신청', 'page_description' => '개인경비 신청 및 결재',
        'permission_name' => $permissionName, 'permission_description' => '개인경비 ' . $permissionName,
        'name' => '개인경비 ' . $permissionName, 'description' => '전자결재 > 개인경비 신청 > ' . $permissionName,
        'category' => '전자결재', 'auth' => true, 'permissions' => [$permission], 'log' => $method !== 'GET' && $action !== 'apiList',
    ]);
}
