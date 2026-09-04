<?php

global $router;

$router->get('/autologout/keepalive', 'SessionController@apiKeepalive', [
    'key' => 'api.system.session.keepalive',
    'page' => '세션 유지',
    'page_description' => '세션 유지 요청',
    'permission_name' => '세션 유지',
    'permission_description' => '세션 유지 요청 권한',
    'name' => '세션 유지',
    'description' => '설정 > 시스템설정 > 세션 유지',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/autologout/extend', 'SessionController@webExtendView', [
    'key' => 'web.system.session.extend',
    'page' => '세션 연장',
    'page_description' => '세션 연장 화면',
    'permission_name' => '세션 연장',
    'permission_description' => '세션 연장 화면 접근 권한',
    'name' => '세션 연장',
    'description' => '설정 > 시스템설정 > 세션 연장',
    'category' => '설정 > 시스템설정',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/autologout/expired', 'SessionController@webExpired', [
    'key' => 'web.system.session.expired',
    'page' => '세션 만료',
    'page_description' => '세션 만료 화면',
    'permission_name' => '세션 만료',
    'permission_description' => '세션 만료 화면 접근 권한',
    'name' => '세션 만료',
    'description' => '설정 > 시스템설정 > 세션 만료',
    'category' => '설정 > 시스템설정',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/admin/about', 'AboutController@webAdminAbout', [
    'key' => 'web.admin.about.view',
    'page' => '회사소개 관리자',
    'page_description' => '회사소개 관리자 화면',
    'permission_name' => '회사소개 관리자 조회',
    'permission_description' => '회사소개 관리자 화면 조회 권한',
    'name' => '회사소개 관리자',
    'description' => '관리자 > 회사소개 > 회사소개 관리자',
    'category' => '관리자',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/login', 'LoginController@webLoginPage', [
    'key' => 'web.auth.login',
    'page' => '로그인',
    'page_description' => '로그인 화면',
    'permission_name' => '로그인 화면 접근',
    'permission_description' => '로그인 화면 접근 권한',
    'name' => '로그인',
    'description' => '인증 > 로그인 > 로그인',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/logout', 'LoginController@apiLogout', [
    'key' => 'web.auth.logout',
    'page' => '로그아웃',
    'page_description' => '로그아웃 요청',
    'permission_name' => '로그아웃',
    'permission_description' => '로그아웃 요청 권한',
    'name' => '로그아웃',
    'description' => '인증 > 로그인 > 로그아웃',
    'category' => '인증',
    'auth' => true,
    'skip_permission' => true,
    'permissions' => [],
    'log' => true,
]);

$router->get('/auth/logout', 'LoginController@apiLogout', [
    'key' => 'web.auth.logout.alias',
    'page' => '로그아웃',
    'page_description' => '로그아웃 별칭 요청',
    'permission_name' => '로그아웃',
    'permission_description' => '로그아웃 별칭 요청 권한',
    'name' => '로그아웃',
    'description' => '인증 > 로그인 > 로그아웃 별칭',
    'category' => '인증',
    'auth' => true,
    'skip_permission' => true,
    'permissions' => [],
    'log' => true,
]);

$router->get('/main/settings/organization/role', 'MainController@settingsOrgRoles', [
    'key' => 'web.settings.organization.role',
    'page' => '역할',
    'page_description' => '역할 설정 화면',
    'permission_name' => '역할 조회',
    'permission_description' => '역할 설정 화면 조회 권한',
    'name' => '역할',
    'description' => '설정 > 조직관리 > 역할',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/organization/roles', 'MainController@redirectOrgRoleLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/main/settings/organization/role_permissions', 'MainController@redirectOrgPermissionAssignmentLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/main/settings/system/site', 'MainController@settingsSystemSite', [
    'key' => 'web.settings.system.site',
    'page' => '사이트정보',
    'page_description' => '사이트정보 설정 화면',
    'permission_name' => '사이트정보 조회',
    'permission_description' => '사이트정보 설정 화면 조회 권한',
    'name' => '사이트정보',
    'description' => '설정 > 시스템설정 > 사이트정보',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/session', 'MainController@settingsSystemSession', [
    'key' => 'web.settings.system.session',
    'page' => '세션관리',
    'page_description' => '세션관리 설정 화면',
    'permission_name' => '세션관리 조회',
    'permission_description' => '세션관리 설정 화면 조회 권한',
    'name' => '세션관리',
    'description' => '설정 > 시스템설정 > 세션관리',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/security', 'MainController@settingsSystemSecurity', [
    'key' => 'web.settings.system.security',
    'page' => '보안정책',
    'page_description' => '보안정책 설정 화면',
    'permission_name' => '보안정책 조회',
    'permission_description' => '보안정책 설정 화면 조회 권한',
    'name' => '보안정책',
    'description' => '설정 > 시스템설정 > 보안정책',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/api', 'MainController@settingsSystemApi', [
    'key' => 'web.settings.system.api',
    'page' => 'API',
    'page_description' => 'API 설정 화면',
    'permission_name' => 'API 조회',
    'permission_description' => 'API 설정 화면 조회 권한',
    'name' => 'API',
    'description' => '설정 > 시스템설정 > API',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/external_services', 'MainController@settingsSystemExternal', [
    'key' => 'web.settings.system.external_services',
    'page' => '외부서비스',
    'page_description' => '외부서비스 설정 화면',
    'permission_name' => '외부서비스 조회',
    'permission_description' => '외부서비스 설정 화면 조회 권한',
    'name' => '외부서비스',
    'description' => '설정 > 시스템설정 > 외부서비스',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/storage', 'MainController@settingsSystemStorage', [
    'key' => 'web.settings.system.storage',
    'page' => '파일저장소',
    'page_description' => '파일저장소 설정 화면',
    'permission_name' => '파일저장소 조회',
    'permission_description' => '파일저장소 설정 화면 조회 권한',
    'name' => '파일저장소',
    'description' => '설정 > 시스템설정 > 파일저장소',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/databasebackup', 'MainController@settingsSystemBackup', [
    'key' => 'web.settings.system.database_backup',
    'page' => '데이터백업',
    'page_description' => '데이터백업 설정 화면',
    'permission_name' => '데이터백업 조회',
    'permission_description' => '데이터백업 설정 화면 조회 권한',
    'name' => '데이터백업',
    'description' => '설정 > 시스템설정 > 데이터백업',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/logs', 'MainController@settingsSystemLogs', [
    'key' => 'web.settings.system.logs',
    'page' => '로그관리',
    'page_description' => '로그관리 설정 화면',
    'permission_name' => '로그관리 조회',
    'permission_description' => '로그관리 설정 화면 조회 권한',
    'name' => '로그관리',
    'description' => '설정 > 시스템설정 > 로그관리',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/main/settings/system/logs/download', 'SystemController@webLogDownload', [
    'key' => 'web.settings.system.logs.download',
    'page' => '로그 다운로드',
    'page_description' => '로그 다운로드 요청',
    'permission_name' => '로그 다운로드',
    'permission_description' => '로그 다운로드 요청 권한',
    'name' => '로그 다운로드',
    'description' => '설정 > 시스템설정 > 로그관리 > 로그 다운로드',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);
