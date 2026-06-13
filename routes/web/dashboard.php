<?php

global $router;

$router->get('/dashboard', 'DashboardController@webDashboard', [
    'key' => 'web.dashboard.main',
    'page' => '대시보드',
    'page_description' => '대시보드',
    'permission_name' => '화면조회',
    'permission_description' => '대시보드 화면 조회',
    'name' => '대시보드',
    'description' => '대시보드 > 대시보드 > 대시보드',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/dashboard/report', 'DashboardController@webReport', [
    'key' => 'web.dashboard.report',
    'page' => '통합보고서',
    'page_description' => '통합 보고서',
    'permission_name' => '화면조회',
    'permission_description' => '통합보고서 화면 조회',
    'name' => '통합보고서',
    'description' => '대시보드 > 대시보드 > 통합보고서',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/dashboard/calendar', 'DashboardController@webCalendar', [
    'key' => 'web.dashboard.calendar',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '화면조회',
    'permission_description' => '일정/캘린더 화면 조회',
    'name' => '일정/캘린더',
    'description' => '대시보드 > 대시보드 > 일정/캘린더',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/dashboard/activity', 'DashboardController@webActivity', [
    'key' => 'web.dashboard.activity',
    'page' => '최근활동',
    'page_description' => '최근 활동',
    'permission_name' => '화면조회',
    'permission_description' => '최근활동 화면 조회',
    'name' => '최근활동',
    'description' => '대시보드 > 대시보드 > 최근활동',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/dashboard/notifications', 'DashboardController@webNotifications', [
    'key' => 'web.dashboard.notifications',
    'page' => '공지사항',
    'page_description' => '공지사항',
    'permission_name' => '화면조회',
    'permission_description' => '공지사항 화면 조회',
    'name' => '공지사항',
    'description' => '대시보드 > 대시보드 > 공지사항',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/dashboard/kpi', 'DashboardController@webKpi', [
    'key' => 'web.dashboard.kpi',
    'page' => '실적현황',
    'page_description' => '실적 현황',
    'permission_name' => '화면조회',
    'permission_description' => '실적현황 화면 조회',
    'name' => '실적현황',
    'description' => '대시보드 > 대시보드 > 실적현황',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/dashboard/settings', 'DashboardController@webSettings', [
    'key' => 'web.dashboard.settings',
    'page' => '설정',
    'page_description' => '대시보드 설정',
    'permission_name' => '화면조회',
    'permission_description' => '설정 화면 조회',
    'name' => '설정',
    'description' => '대시보드 > 대시보드 > 설정',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

