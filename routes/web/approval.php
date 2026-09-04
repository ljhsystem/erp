<?php

global $router;

$router->get('/auth/approval/request', 'UserApprovalController@webApproveRequest', [
    'key' => 'web.approval.request.view',
    'page' => '사용자승인',
    'page_description' => '사용자 승인',
    'permission_name' => '화면조회',
    'permission_description' => '사용자승인 화면 조회',
    'name' => '사용자승인',
    'description' => '인증 > 가입승인 > 사용자승인',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/auth/approval/result', 'UserApprovalController@webApproveResult', [
    'key' => 'web.approval.result.view',
    'page' => '승인결과',
    'page_description' => '가입 승인 결과',
    'permission_name' => '화면조회',
    'permission_description' => '승인결과 화면 조회',
    'name' => '승인결과',
    'description' => '인증 > 가입승인 > 승인결과',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/approval', 'ApprovalController@webIndex', [
    'key' => 'web.approval.index',
    'page' => '전자결재',
    'page_description' => '전자결재 관리',
    'permission_name' => '화면조회',
    'permission_description' => '전자결재 화면 조회',
    'name' => '전자결재',
    'description' => '전자결재 > 결재 > 전자결재',
    'category' => '전자결재 > 결재',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/approval/status', 'ApprovalInboxController@webIndex', [
    'key' => 'web.approval.inbox',
    'page_key' => 'approval.inbox',
    'page' => '결재함',
    'page_description' => '통합 전자결재 문서함',
    'permission_name' => '화면조회',
    'permission_description' => '결재함 화면 조회',
    'name' => '결재함',
    'description' => '전자결재 > 결재함',
    'category' => '전자결재',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/approval/personal-expense', 'PersonalExpenseController@webIndex', [
    'key' => 'web.approval.personal-expense', 'page_key' => 'approval.personal_expense', 'page' => '개인경비 신청', 'page_description' => '개인경비 신청 및 결재',
    'permission_name' => '화면조회', 'permission_description' => '개인경비 신청 화면 조회', 'name' => '개인경비 신청',
    'description' => '전자결재 > 개인경비 신청', 'category' => '전자결재', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);

$router->get('/approval/leave-request', 'LeaveRequestController@webIndex', [
    'key' => 'web.approval.leave-request', 'page_key' => 'approval.leave_request', 'page' => '휴가신청', 'page_description' => '직원 본인 휴가 신청 및 결재',
    'permission_name' => '화면조회', 'permission_description' => '휴가신청 화면 조회', 'name' => '휴가신청',
    'description' => '전자결재 > 휴가신청', 'category' => '전자결재', 'auth' => true, 'permissions' => ['view'], 'log' => false,
]);
