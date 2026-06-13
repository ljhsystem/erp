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

$router->post('/approval/request/create', 'ApprovalRequestController@apiCreate', [
    'key' => 'api.approval.request.create',
    'page' => '전자결재',
    'page_description' => '전자결재 관리',
    'permission_name' => '생성',
    'permission_description' => '전자결재 생성',
    'name' => '전자결재 생성',
    'description' => '전자결재 > 결재 > 전자결재 > 생성',
    'category' => '전자결재 > 결재',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/approval/request/detail', 'ApprovalRequestController@apiDetail', [
    'key' => 'api.approval.request.detail',
    'page' => '전자결재',
    'page_description' => '전자결재 관리',
    'permission_name' => '상세조회',
    'permission_description' => '전자결재 상세 조회',
    'name' => '전자결재 상세조회',
    'description' => '전자결재 > 결재 > 전자결재 > 상세조회',
    'category' => '전자결재 > 결재',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/approval/request/approve', 'ApprovalRequestController@apiApproveStep', [
    'key' => 'api.approval.step.approve',
    'page' => '전자결재',
    'page_description' => '전자결재 관리',
    'permission_name' => '승인',
    'permission_description' => '전자결재 승인',
    'name' => '전자결재 승인',
    'description' => '전자결재 > 결재 > 전자결재 > 승인',
    'category' => '전자결재 > 결재',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/approval/request/reject', 'ApprovalRequestController@apiRejectStep', [
    'key' => 'api.approval.step.reject',
    'page' => '전자결재',
    'page_description' => '전자결재 관리',
    'permission_name' => '반려',
    'permission_description' => '전자결재 반려',
    'name' => '전자결재 반려',
    'description' => '전자결재 > 결재 > 전자결재 > 반려',
    'category' => '전자결재 > 결재',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/approval/request/status', 'ApprovalRequestController@apiStatus', [
    'key' => 'api.approval.request.status',
    'page' => '전자결재',
    'page_description' => '전자결재 관리',
    'permission_name' => '상태조회',
    'permission_description' => '전자결재 상태 조회',
    'name' => '전자결재 상태조회',
    'description' => '전자결재 > 결재 > 전자결재 > 상태조회',
    'category' => '전자결재 > 결재',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/approval/request/step/delete', 'ApprovalRequestController@apiDeleteStep', [
    'key' => 'api.approval.step.delete',
    'page' => '전자결재',
    'page_description' => '전자결재 관리',
    'permission_name' => '결재단계삭제',
    'permission_description' => '결재단계 삭제',
    'name' => '결재단계 삭제',
    'description' => '전자결재 > 결재 > 전자결재 > 결재단계삭제',
    'category' => '전자결재 > 결재',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

