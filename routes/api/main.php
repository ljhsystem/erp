<?php

global $router;

$router->get('/api/main/calendar/list', 'CalendarController@apiList', [
    'key' => 'api.main.calendar.list',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '전체일정조회',
    'permission_description' => '전체 일정 조회',
    'name' => '전체 일정 조회',
    'description' => '메인 > 일정/캘린더 > 전체일정조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/main/calendar/events-all', 'CalendarController@apiEventsAll', [
    'key' => 'api.main.calendar.events_all',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정조회',
    'permission_description' => '일정 조회',
    'name' => '일정 조회',
    'description' => '메인 > 일정/캘린더 > 일정조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/main/calendar/events', 'CalendarController@apiEvents', [
    'key' => 'api.main.calendar.events',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정조회',
    'permission_description' => '일정 조회',
    'name' => '일정 조회',
    'description' => '메인 > 일정/캘린더 > 일정조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/main/calendar/event/create', 'CalendarController@apiEventCreate', [
    'key' => 'api.main.calendar.event.create',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정생성',
    'permission_description' => '일정 생성',
    'name' => '일정 생성',
    'description' => '메인 > 일정/캘린더 > 일정생성',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/event/update', 'CalendarController@apiEventUpdate', [
    'key' => 'api.main.calendar.event.update',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정수정',
    'permission_description' => '일정 수정',
    'name' => '일정 수정',
    'description' => '메인 > 일정/캘린더 > 일정수정',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/event/delete', 'CalendarController@apiEventDelete', [
    'key' => 'api.main.calendar.event.delete',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정삭제',
    'permission_description' => '일정 삭제',
    'name' => '일정 삭제',
    'description' => '메인 > 일정/캘린더 > 일정삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/main/calendar/tasks', 'CalendarController@apiTasks', [
    'key' => 'api.main.calendar.tasks',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '전체할일조회',
    'permission_description' => '전체 할일 조회',
    'name' => '전체 할일 조회',
    'description' => '메인 > 일정/캘린더 > 전체할일조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/main/calendar/tasks-all', 'CalendarController@apiTasksAll', [
    'key' => 'api.main.calendar.tasks_all',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일조회',
    'permission_description' => '할일 조회',
    'name' => '할일 조회',
    'description' => '메인 > 일정/캘린더 > 할일조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/main/calendar/task/create', 'CalendarController@apiTaskCreate', [
    'key' => 'api.main.calendar.task.create',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일생성',
    'permission_description' => '할일 생성',
    'name' => '할일 생성',
    'description' => '메인 > 일정/캘린더 > 할일생성',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/task/toggle-complete', 'CalendarController@apiToggleTaskComplete', [
    'key' => 'api.main.calendar.task.toggle_complete',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '완료전환',
    'permission_description' => '할일 완료 전환',
    'name' => '할일 완료전환',
    'description' => '메인 > 일정/캘린더 > 완료전환',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/task/update', 'CalendarController@apiTaskUpdate', [
    'key' => 'api.main.calendar.task.update',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일수정',
    'permission_description' => '할일 수정',
    'name' => '할일 수정',
    'description' => '메인 > 일정/캘린더 > 할일수정',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/task/delete', 'CalendarController@apiTaskDelete', [
    'key' => 'api.main.calendar.task.delete',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일삭제',
    'permission_description' => '할일 삭제',
    'name' => '할일 삭제',
    'description' => '메인 > 일정/캘린더 > 할일삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/main/calendar/collection/delete', 'CalendarController@apiCollectionDelete', [
    'key' => 'api.main.calendar.collection.delete',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일괄삭제',
    'permission_description' => '일정/할일 일괄 삭제',
    'name' => '일괄삭제',
    'description' => '메인 > 일정/캘린더 > 일괄삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/main/calendar/event/hard-delete', 'CalendarController@apiEventHardDelete', [
    'key' => 'api.main.calendar.event.hard_delete',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정영구삭제',
    'permission_description' => '일정 영구 삭제',
    'name' => '일정 영구삭제',
    'description' => '메인 > 일정/캘린더 > 일정영구삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/main/calendar/cache-rebuild', 'CalendarController@apiCacheRebuild', [
    'key' => 'api.main.calendar.cache_rebuild',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '캐시재생성',
    'permission_description' => '캘린더 캐시 재생성',
    'name' => '캘린더캐시 재생성',
    'description' => '메인 > 일정/캘린더 > 캐시재생성',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/task/hard-delete', 'CalendarController@apiTaskHardDelete', [
    'key' => 'api.main.calendar.task.hard_delete',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일영구삭제',
    'permission_description' => '할일 영구 삭제',
    'name' => '할일 영구삭제',
    'description' => '메인 > 일정/캘린더 > 할일영구삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/main/calendar/update-admin-color', 'CalendarController@apiUpdateAdminColor', [
    'key' => 'api.main.calendar.update_admin_color',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '관리자색상변경',
    'permission_description' => '관리자 색상 변경',
    'name' => '관리자색상변경',
    'description' => '메인 > 일정/캘린더 > 관리자색상변경',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/main/calendar/tasks-panel', 'CalendarController@apiTasksPanel', [
    'key' => 'api.main.calendar.tasks_panel',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일요약조회',
    'permission_description' => '할일 요약 조회',
    'name' => '할일 요약조회',
    'description' => '메인 > 일정/캘린더 > 할일요약조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/main/calendar/events-deleted', 'CalendarController@apiEventsDeleted', [
    'key' => 'api.main.calendar.events_deleted',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '삭제일정조회',
    'permission_description' => '삭제 일정 조회',
    'name' => '삭제일정조회',
    'description' => '메인 > 일정/캘린더 > 삭제일정조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/main/calendar/tasks-deleted', 'CalendarController@apiTasksDeleted', [
    'key' => 'api.main.calendar.tasks_deleted',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '삭제할일조회',
    'permission_description' => '삭제 할일 조회',
    'name' => '삭제할일조회',
    'description' => '메인 > 일정/캘린더 > 삭제할일조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/main/calendar/event/hard-delete-all', 'CalendarController@apiEventHardDeleteAll', [
    'key' => 'api.main.calendar.event.hard_delete_all',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정전체영구삭제',
    'permission_description' => '일정 전체 영구 삭제',
    'name' => '일정 전체영구삭제',
    'description' => '메인 > 일정/캘린더 > 일정전체영구삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/main/calendar/task/hard-delete-all', 'CalendarController@apiTaskHardDeleteAll', [
    'key' => 'api.main.calendar.task.hard_delete_all',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일전체영구삭제',
    'permission_description' => '할일 전체 영구 삭제',
    'name' => '할일 전체영구삭제',
    'description' => '메인 > 일정/캘린더 > 할일전체영구삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/main/calendar/event/restore', 'CalendarController@apiEventRestore', [
    'key' => 'api.main.calendar.event.restore',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '일정복구',
    'permission_description' => '일정 복구',
    'name' => '일정 복구',
    'description' => '메인 > 일정/캘린더 > 일정복구',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/task/restore', 'CalendarController@apiTaskRestore', [
    'key' => 'api.main.calendar.task.restore',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일복구',
    'permission_description' => '할일 복구',
    'name' => '할일 복구',
    'description' => '메인 > 일정/캘린더 > 할일복구',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/main/calendar/task/delete-bulk', 'CalendarController@apiTaskDeleteBulk', [
    'key' => 'api.main.calendar.task.delete_bulk',
    'page' => '일정/캘린더',
    'page_description' => '일정 및 캘린더 관리',
    'permission_name' => '할일일괄삭제',
    'permission_description' => '할일 일괄 삭제',
    'name' => '할일 일괄삭제',
    'description' => '메인 > 일정/캘린더 > 할일일괄삭제',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/main/profile-summary', 'CalendarController@apiProfileSummary', [
    'key' => 'api.main.profile_summary',
    'page' => '대시보드',
    'page_description' => '대시보드 관리',
    'permission_name' => '프로필요약조회',
    'permission_description' => '프로필 요약 조회',
    'name' => '프로필요약 조회',
    'description' => '대시보드 > 대시보드 > 대시보드 > 프로필요약조회',
    'category' => '메인',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);
