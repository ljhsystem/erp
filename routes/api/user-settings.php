<?php

global $router;

$router->get('/api/settings/system/user-settings/detail', 'UserSettingController@apiDetail', [
    'key' => 'api.settings.system.user-settings.detail',
    'page' => '사용자 화면설정',
    'page_description' => '사용자 화면설정 조회',
    'permission_name' => '상세조회',
    'permission_description' => '사용자 화면설정 상세 조회',
    'name' => '사용자 화면설정 조회',
    'description' => '설정 > 시스템설정 > 사용자 화면설정 > 상세조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/settings/system/user-settings/save', 'UserSettingController@apiSave', [
    'key' => 'api.settings.system.user-settings.save',
    'page' => '사용자 화면설정',
    'page_description' => '사용자 화면설정 저장',
    'permission_name' => '저장',
    'permission_description' => '사용자 화면설정 저장',
    'name' => '사용자 화면설정 저장',
    'description' => '설정 > 시스템설정 > 사용자 화면설정 > 저장',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/settings/system/user-settings/delete', 'UserSettingController@apiDelete', [
    'key' => 'api.settings.system.user-settings.delete',
    'page' => '사용자 화면설정',
    'page_description' => '사용자 화면설정 삭제',
    'permission_name' => '삭제',
    'permission_description' => '사용자 화면설정 삭제',
    'name' => '사용자 화면설정 삭제',
    'description' => '설정 > 시스템설정 > 사용자 화면설정 > 삭제',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);
