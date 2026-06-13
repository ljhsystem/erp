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

