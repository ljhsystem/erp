<?php

global $router;

$router->get('/main', 'MainController@webDashboard', [
    'key' => 'web.main.dashboard',
    'page' => '대시보드',
    'page_description' => '대시보드',
    'permission_name' => '화면조회',
    'permission_description' => '대시보드 화면 조회',
    'name' => '대시보드',
    'description' => '메인 > 대시보드',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/main/report', 'MainController@webReport', [
    'key' => 'web.main.report',
    'page' => '통합보고서',
    'page_description' => '통합 보고서',
    'permission_name' => '화면조회',
    'permission_description' => '통합보고서 화면 조회',
    'name' => '통합보고서',
    'description' => '메인 > 통합보고서',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/main/calendar', 'MainController@webCalendar', [
    'key' => 'web.main.calendar',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '화면조회',
    'permission_description' => '일정/캘린더 화면 조회',
    'name' => '일정/캘린더',
    'description' => '메인 > 일정/캘린더',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/main/activity', 'MainController@webActivity', [
    'key' => 'web.main.activity',
    'page' => '최근활동',
    'page_description' => '최근 활동',
    'permission_name' => '화면조회',
    'permission_description' => '최근활동 화면 조회',
    'name' => '최근활동',
    'description' => '메인 > 최근활동',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/main/notifications', 'MainController@webNotifications', [
    'key' => 'web.main.notifications',
    'page' => '알림센터',
    'page_description' => '내 업무 알림센터',
    'permission_name' => '화면조회',
    'permission_description' => '알림센터 화면 조회',
    'name' => '알림센터',
    'description' => '메인 > 알림센터',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/main/kpi', 'MainController@webKpi', [
    'key' => 'web.main.kpi',
    'page' => '실적현황',
    'page_description' => '실적 현황',
    'permission_name' => '화면조회',
    'permission_description' => '실적현황 화면 조회',
    'name' => '실적현황',
    'description' => '메인 > 실적현황',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/main/settings', 'MainController@webSettings', [
    'key' => 'web.main.settings',
    'page' => '설정',
    'page_description' => 'ERP 공통 설정',
    'permission_name' => '화면조회',
    'permission_description' => '설정 화면 조회',
    'name' => '설정',
    'description' => '메인 > 설정',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

// 운영 DB의 기존 메뉴 URL을 보존하는 전환기 Redirect다.
$router->get('/dashboard', 'MainController@redirectLegacyDashboard', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/report', 'MainController@redirectLegacyReport', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/calendar', 'MainController@redirectLegacyCalendar', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/activity', 'MainController@redirectLegacyActivity', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/notifications', 'MainController@redirectLegacyNotifications', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/kpi', 'MainController@redirectLegacyKpi', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings', 'MainController@redirectLegacySettings', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);
