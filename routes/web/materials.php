<?php

global $router;

$router->get('/document/stats', 'DocumentController@webStats', [
    'key' => 'web.document.stats',
    'page' => '문서통계',
    'page_description' => '문서 통계',
    'permission_name' => '화면조회',
    'permission_description' => '문서통계 화면 조회',
    'name' => '문서통계',
    'description' => '자료관리 > 문서 > 문서통계',
    'category' => '자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/document', 'DocumentController@webIndex', [
    'key' => 'web.document.index',
    'page' => '문서관리',
    'page_description' => '문서 관리',
    'permission_name' => '화면조회',
    'permission_description' => '문서관리 화면 조회',
    'name' => '문서관리',
    'description' => '자료관리 > 문서 > 문서관리',
    'category' => '자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/document/file_register', 'DocumentController@webFileRegister', [
    'key' => 'web.document.file_register',
    'page' => '문서등록',
    'page_description' => '문서 등록',
    'permission_name' => '화면조회',
    'permission_description' => '문서등록 화면 조회',
    'name' => '문서등록',
    'description' => '자료관리 > 문서 > 문서등록',
    'category' => '자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/document/view', 'DocumentController@webView', [
    'key' => 'web.document.view',
    'page' => '문서보기',
    'page_description' => '문서 보기',
    'permission_name' => '화면조회',
    'permission_description' => '문서보기 화면 조회',
    'name' => '문서보기',
    'description' => '자료관리 > 문서 > 문서보기',
    'category' => '자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/document/edit', 'DocumentController@webEdit', [
    'key' => 'web.document.edit',
    'page' => '문서수정',
    'page_description' => '문서 수정',
    'permission_name' => '화면조회',
    'permission_description' => '문서수정 화면 조회',
    'name' => '문서수정',
    'description' => '자료관리 > 문서 > 문서수정',
    'category' => '자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/document/stats', 'DocumentController@webStats', [
    'key' => 'web.document.stats',
    'page' => '문서통계',
    'page_description' => '문서 통계',
    'permission_name' => '화면조회',
    'permission_description' => '문서통계 화면 조회',
    'name' => '문서통계',
    'description' => '자료관리 > 문서 > 문서통계',
    'category' => '자료관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/profile', 'ProfileController@webProfile', [
    'key' => 'web.user.profile.view',
    'page' => '내정보 관리',
    'page_description' => '내정보 관리',
    'permission_name' => '화면조회',
    'permission_description' => '내정보 관리 화면 조회',
    'name' => '내정보 관리',
    'description' => '내정보 > 프로필 > 내정보 관리',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

