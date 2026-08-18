<?php

global $router;

$router->post('/api/2fa/verify', 'TwoFactorController@apiVerify', [
    'key' => 'api.auth.2fa.verify',
    'page' => '2차인증',
    'page_description' => '2차인증 관리',
    'permission_name' => '확인',
    'permission_description' => '2차인증 확인',
    'name' => '2차인증 확인',
    'description' => '인증 > 계정보안 > 2차인증 > 확인',
    'category' => '인증 > 계정보안',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
    'allow_statuses' => ['2FA_PENDING'],
]);

$router->post('/api/contact/send', 'ContactController@apiSend', [
    'key' => 'api.contact.send',
    'page' => '사용자승인',
    'page_description' => '사용자승인 관리',
    'permission_name' => '승인',
    'permission_description' => '사용자 승인',
    'name' => '사용자승인 승인',
    'description' => '인증 > 가입승인 > 사용자승인 > 승인',
    'category' => '인증 > 가입승인',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/api/external/ping', 'ExternalApiController@ping', [
    'key' => 'api.external.ping',
    'page' => '연동상태',
    'page_description' => '외부연동 상태관리',
    'permission_name' => '연결확인',
    'permission_description' => '연동 상태 확인',
    'name' => '연동상태 연결확인',
    'description' => '외부연동 > 연동상태 > 연동상태 > 연결확인',
    'category' => '외부연동 > 연동상태',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
    'middleware' => ['ApiAccessMiddleware'],
]);

$router->get('/api/external/employees/list', 'ExternalApiController@employees', [
    'key' => 'api.external.employee.list',
    'page' => '직원연동',
    'page_description' => '직원연동 관리',
    'permission_name' => '조회',
    'permission_description' => '직원연동 조회',
    'name' => '직원연동 조회',
    'description' => '외부연동 > 직원연동 > 직원연동 > 조회',
    'category' => '외부연동 > 직원연동',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
    'middleware' => ['ApiAccessMiddleware'],
]);

$router->get('/api/external/employees', 'ExternalApiController@employees', [
    'key' => 'api.external.employee.list',
    'page' => '직원연동',
    'page_description' => '직원연동 관리',
    'permission_name' => '조회',
    'permission_description' => '직원연동 조회',
    'name' => '직원연동 조회',
    'description' => '외부연동 > 직원연동 > 직원연동 > 조회',
    'category' => '외부연동 > 직원연동',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
    'middleware' => ['ApiAccessMiddleware'],
]);

$router->get('/api/account/sub-accounts', 'SubChartAccountController@apiList', [
    'key' => 'api.account.sub_accounts.list',
    'page' => '보조계정',
    'page_description' => '보조계정 관리',
    'permission_name' => '전표입력 조회',
    'permission_description' => '전표/증빙/데이터생성에서 계정별 보조계정 목록 조회',
    'name' => '보조계정 조회',
    'description' => '회계관리 > 기초정보관리 > 보조계정 > 조회',
    'category' => '회계관리 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);
