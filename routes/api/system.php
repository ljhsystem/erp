<?php

global $router;

$router->get('/api/account/lock/status', 'AccountLockController@apiStatus', [
    'key' => 'api.auth.account_lock.status',
    'page' => '계정잠금',
    'page_description' => '계정잠금 관리',
    'permission_name' => '잠금조회',
    'permission_description' => '계정잠금 상태 조회',
    'name' => '계정잠금 잠금',
    'description' => '인증 > 계정보안 > 계정잠금 > 잠금',
    'category' => '인증 > 계정보안',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/account/lock/set', 'AccountLockController@apiLock', [
    'key' => 'api.auth.account_lock.lock',
    'page' => '계정잠금',
    'page_description' => '계정잠금 관리',
    'permission_name' => '잠금해제',
    'permission_description' => '계정잠금 해제',
    'name' => '계정잠금 잠금해제',
    'description' => '인증 > 계정보안 > 계정잠금 > 잠금해제',
    'category' => '인증 > 계정보안',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/account/lock/unlock', 'AccountLockController@apiUnlock', [
    'key' => 'api.auth.account_lock.unlock',
    'page' => '계정잠금',
    'page_description' => '계정잠금 관리',
    'permission_name' => '잠금해제',
    'permission_description' => '계정잠금 해제',
    'name' => '계정잠금 잠금해제',
    'description' => '인증 > 계정보안 > 계정잠금 > 잠금해제',
    'category' => '인증 > 계정보안',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/auth/register', 'RegisterController@apiRegister', [
    'key' => 'api.auth.register',
    'page' => '회원가입',
    'page_description' => '회원가입 관리',
    'permission_name' => '회원가입',
    'permission_description' => '회원가입 처리',
    'name' => '회원가입',
    'description' => '인증 > 회원관리 > 회원가입 > 회원가입',
    'category' => '인증 > 회원관리',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/auth/login', 'LoginController@apiLogin', [
    'key' => 'api.auth.login',
    'page' => '로그인',
    'page_description' => '로그인 관리',
    'permission_name' => '로그인',
    'permission_description' => '로그인 처리',
    'name' => '로그인',
    'description' => '인증 > 로그인 > 로그인 > 로그인',
    'category' => '인증 > 로그인',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/auth/password/change', 'PasswordController@apiChangePassword', [
    'key' => 'api.auth.password.change',
    'name' => '비밀번호변경 변경연기',
    'description' => '인증 > 계정보안 > 비밀번호변경 > 변경연기',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => false,
    'allow_statuses' => ['NORMAL', 'PASSWORD_EXPIRED'],
]);

$router->post('/api/auth/password/change', 'PasswordController@apiChangePassword', [
    'key' => 'api.auth.password.change',
    'page' => '비밀번호변경',
    'page_description' => '비밀번호변경 관리',
    'permission_name' => '변경연기',
    'permission_description' => '비밀번호변경 연기',
    'name' => '비밀번호변경 변경연기',
    'description' => '인증 > 계정보안 > 비밀번호변경 > 변경연기',
    'category' => '인증 > 계정보안',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => false,
    'allow_statuses' => ['NORMAL', 'PASSWORD_EXPIRED'],
]);

$router->post('/api/auth/password/change-later', 'PasswordController@apiChangeLater', [
    'key' => 'api.auth.password.change_later',
    'page' => '비밀번호변경',
    'page_description' => '비밀번호변경 관리',
    'permission_name' => '변경연기',
    'permission_description' => '비밀번호 변경을 나중에 처리',
    'name' => '비밀번호변경 변경연기',
    'description' => '인증 > 계정보안 > 비밀번호변경 > 변경연기',
    'category' => '인증 > 계정보안',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => false,
    'allow_statuses' => ['PASSWORD_EXPIRED'],
]);

$router->post('/api/integration/biz-status', 'ExternalIntegrationController@apiBizStatus', [
    'key' => 'api.integration.biz_status',
    'page' => 'API관리',
    'page_description' => '외부 API 관리',
    'permission_name' => '사업자상태조회',
    'permission_description' => '사업자상태 조회',
    'name' => '사업자상태 조회',
    'description' => '설정 > 시스템설정 > API관리 > 사업자상태조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/code/list', 'CodeController@apiList', [
    'key' => 'code.view',
    'page' => '코드관리',
    'page_description' => '코드 관리',
    'permission_name' => '목록조회',
    'permission_description' => '코드 목록 조회',
    'name' => '코드 목록조회',
    'description' => '설정 > 기준관리 > 코드관리 > 목록조회',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/system/data-table-columns', 'SystemController@apiDataTableColumns', [
    'key' => 'api.settings.system.data_table_columns',
    'page' => '테이블설정',
    'page_description' => '공용 DataTable 컬럼 메타 조회',
    'permission_name' => '컬럼메타조회',
    'permission_description' => '공용 DataTable DB 컬럼 메타 조회',
    'name' => '공용 DataTable 컬럼 메타 조회',
    'description' => '설정 > 시스템설정 > 공용 DataTable 컬럼 메타 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/system/code/detail', 'CodeController@apiDetail', [
    'key' => 'code.view',
    'page' => '코드관리',
    'page_description' => '코드 관리',
    'permission_name' => '상세조회',
    'permission_description' => '코드 상세 조회',
    'name' => '코드 상세조회',
    'description' => '설정 > 기준관리 > 코드관리 > 상세조회',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/system/code/references', 'CodeController@apiReferences', [
    'key' => 'code.view',
    'page' => '코드관리',
    'page_description' => '코드 관리',
    'permission_name' => '참조내역조회',
    'permission_description' => '코드 참조내역 조회',
    'name' => '코드 참조내역조회',
    'description' => '설정 > 기준관리 > 코드관리 > 참조내역조회',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/system/code/groups', 'CodeController@apiGroups', [
    'key' => 'code.view',
    'page' => '코드관리',
    'page_description' => '코드 관리',
    'permission_name' => '그룹조회',
    'permission_description' => '코드 그룹 조회',
    'name' => '코드 그룹조회',
    'description' => '설정 > 기준관리 > 코드관리 > 그룹조회',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/system/code/save', 'CodeController@apiSave', [
    'key' => 'code.save',
    'page' => '코드관리',
    'page_description' => '코드 관리',
    'permission_name' => '저장',
    'permission_description' => '코드 저장',
    'name' => '코드 저장',
    'description' => '설정 > 기준관리 > 코드관리 > 저장',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/system/code/delete', 'CodeController@apiDelete', [
    'key' => 'code.delete',
    'page' => '코드관리',
    'page_description' => '코드 관리',
    'permission_name' => '삭제',
    'permission_description' => '코드 삭제',
    'name' => '코드 삭제',
    'description' => '설정 > 기준관리 > 코드관리 > 삭제',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/system/code/reorder', 'CodeController@apiReorder', [
    'key' => 'code.save',
    'page' => '코드관리',
    'page_description' => '코드 관리',
    'permission_name' => '정렬저장',
    'permission_description' => '코드 정렬 저장',
    'name' => '코드 정렬저장',
    'description' => '설정 > 기준관리 > 코드관리 > 정렬저장',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/organization/role/list', 'RoleController@apiList', [
    'key' => 'api.settings.role.list',
    'page' => '역할관리',
    'page_description' => '역할 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '역할 목록 조회',
    'name' => '역할 목록조회',
    'description' => '설정 > 조직관리 > 역할관리 > 목록조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->get('/api/settings/organization/role/detail', 'RoleController@apiDetail', [
    'key' => 'api.settings.role.detail',
    'page' => '역할관리',
    'page_description' => '역할 정보관리',
    'permission_name' => '상세조회',
    'permission_description' => '역할 상세 조회',
    'name' => '역할 상세조회',
    'description' => '설정 > 조직관리 > 역할관리 > 상세조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/role/save', 'RoleController@apiSave', [
    'key' => 'api.settings.role.save',
    'page' => '역할관리',
    'page_description' => '역할 정보관리',
    'permission_name' => '저장',
    'permission_description' => '역할 저장',
    'name' => '역할 저장',
    'description' => '설정 > 조직관리 > 역할관리 > 저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/role/delete', 'RoleController@apiDelete', [
    'key' => 'api.settings.role.delete',
    'page' => '역할관리',
    'page_description' => '역할 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '역할 삭제',
    'name' => '역할 삭제',
    'description' => '설정 > 조직관리 > 역할관리 > 삭제',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/organization/role/reorder', 'RoleController@apiReorder', [
    'key' => 'api.settings.role.reorder',
    'page' => '역할관리',
    'page_description' => '역할 정보관리',
    'permission_name' => '정렬저장',
    'permission_description' => '역할 정렬 저장',
    'name' => '역할 정렬저장',
    'description' => '설정 > 조직관리 > 역할관리 > 정렬저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/organization/permission/list', 'PermissionController@apiList', [
    'key' => 'api.settings.permission.list',
    'page' => '권한관리',
    'page_description' => '권한 정보관리',
    'permission_name' => '목록조회',
    'permission_description' => '권한 목록 조회',
    'name' => '권한 목록조회',
    'description' => '설정 > 조직관리 > 권한관리 > 목록조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/permission/delete', 'PermissionController@apiDelete', [
    'key' => 'api.settings.permission.delete',
    'page' => '권한관리',
    'page_description' => '권한 정보관리',
    'permission_name' => '삭제',
    'permission_description' => '권한 삭제',
    'name' => '권한 삭제',
    'description' => '설정 > 조직관리 > 권한관리 > 삭제',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/organization/role-permission/list', 'RolePermissionController@apiList', [
    'key' => 'api.settings.rolepermission.list',
    'page' => '권한부여',
    'page_description' => '역할별 권한부여 관리',
    'permission_name' => '조회',
    'permission_description' => '권한부여 조회',
    'name' => '권한부여 조회',
    'description' => '설정 > 조직관리 > 권한부여 > 조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/role-permission/save', 'RolePermissionController@apiSave', [
    'key' => 'api.settings.rolepermission.assign',
    'page' => '권한부여',
    'page_description' => '역할별 권한부여 관리',
    'permission_name' => '저장',
    'permission_description' => '역할별 전체 권한 저장',
    'name' => '권한부여 저장',
    'description' => '설정 > 조직관리 > 권한부여 > 저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/role-permission/reorder', 'RolePermissionController@apiReorder', [
    'key' => 'api.settings.rolepermission.reorder',
    'page' => '권한부여',
    'page_description' => '역할별 권한부여 관리',
    'permission_name' => '순서저장',
    'permission_description' => '권한 순서 저장',
    'name' => '권한순서저장',
    'description' => '설정 > 조직관리 > 권한부여 > 순서저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/organization/user-permission/list', 'UserPermissionController@apiList', [
    'key' => 'api.settings.user_permission.list',
    'page' => '권한부여',
    'page_description' => '개별 사용자 권한 관리',
    'permission_name' => '개별권한조회',
    'permission_description' => '사용자별 개별권한 대상 조회',
    'name' => '개별권한조회',
    'description' => '설정 > 조직관리 > 권한부여 > 개별권한조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/user-permission/detail', 'UserPermissionController@apiDetail', [
    'key' => 'api.settings.user_permission.detail',
    'page' => '권한부여',
    'page_description' => '개별 사용자 권한 관리',
    'permission_name' => '개별권한상세조회',
    'permission_description' => '사용자별 역할·개별·최종권한 조회',
    'name' => '개별권한상세조회',
    'description' => '설정 > 조직관리 > 권한부여 > 개별권한상세조회',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/user-permission/save', 'UserPermissionController@apiSave', [
    'key' => 'api.settings.user_permission.save',
    'page' => '권한부여',
    'page_description' => '개별 사용자 권한 관리',
    'permission_name' => '개별권한저장',
    'permission_description' => '사용자별 개별권한 전체 Set 저장',
    'name' => '개별권한저장',
    'description' => '설정 > 조직관리 > 권한부여 > 개별권한저장',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/site/get', 'SystemController@apiSiteGet', [
    'key' => 'api.settings.system.site.view',
    'page' => '사이트설정',
    'page_description' => '사이트 설정 관리',
    'permission_name' => '조회',
    'permission_description' => '사이트설정 조회',
    'name' => '사이트설정 조회',
    'description' => '설정 > 시스템설정 > 사이트설정 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/site/save', 'SystemController@apiSiteSave', [
    'key' => 'api.settings.system.site.edit',
    'page' => '사이트설정',
    'page_description' => '사이트 설정 관리',
    'permission_name' => '저장',
    'permission_description' => '사이트설정 저장',
    'name' => '사이트설정 저장',
    'description' => '설정 > 시스템설정 > 사이트설정 > 저장',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/session/get', 'SystemController@apiSessionGet', [
    'key' => 'api.settings.system.session.view',
    'page' => '세션설정',
    'page_description' => '세션 설정 관리',
    'permission_name' => '조회',
    'permission_description' => '세션설정 조회',
    'name' => '세션설정 조회',
    'description' => '설정 > 시스템설정 > 세션설정 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/session/save', 'SystemController@apiSessionSave', [
    'key' => 'api.settings.system.session.edit',
    'page' => '세션설정',
    'page_description' => '세션 설정 관리',
    'permission_name' => '저장',
    'permission_description' => '세션설정 저장',
    'name' => '세션설정 저장',
    'description' => '설정 > 시스템설정 > 세션설정 > 저장',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/security/get', 'SystemController@apiSecurityGet', [
    'key' => 'api.settings.system.security.view',
    'page' => '보안설정',
    'page_description' => '보안 설정 관리',
    'permission_name' => '조회',
    'permission_description' => '보안설정 조회',
    'name' => '보안설정 조회',
    'description' => '설정 > 시스템설정 > 보안설정 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/security/save', 'SystemController@apiSecuritySave', [
    'key' => 'api.settings.system.security.edit',
    'page' => '보안설정',
    'page_description' => '보안 설정 관리',
    'permission_name' => '저장',
    'permission_description' => '보안설정 저장',
    'name' => '보안설정 저장',
    'description' => '설정 > 시스템설정 > 보안설정 > 저장',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/api/get', 'SystemController@apiApiGet', [
    'key' => 'api.settings.system.api.view',
    'page' => 'API관리',
    'page_description' => 'API 관리',
    'permission_name' => '조회',
    'permission_description' => 'API관리 조회',
    'name' => 'API관리 조회',
    'description' => '설정 > 시스템설정 > API관리 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/api/save', 'SystemController@apiApiSave', [
    'key' => 'api.settings.system.api.edit',
    'page' => 'API관리',
    'page_description' => 'API 관리',
    'permission_name' => '저장',
    'permission_description' => 'API관리 저장',
    'name' => 'API관리 저장',
    'description' => '설정 > 시스템설정 > API관리 > 저장',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/external-services/get', 'SystemController@apiExternalServicesGet', [
    'key' => 'api.settings.system.external.view',
    'page' => '외부서비스',
    'page_description' => '외부서비스 관리',
    'permission_name' => '조회',
    'permission_description' => '외부서비스 조회',
    'name' => '외부서비스 조회',
    'description' => '설정 > 시스템설정 > 외부서비스 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/external-services/save', 'SystemController@apiExternalServicesSave', [
    'key' => 'api.settings.system.external.edit',
    'page' => '외부서비스',
    'page_description' => '외부서비스 관리',
    'permission_name' => '저장',
    'permission_description' => '외부서비스 저장',
    'name' => '외부서비스 저장',
    'description' => '설정 > 시스템설정 > 외부서비스 > 저장',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/user/external-accounts', 'ExternalAccountController@apiList', [
    'key' => 'api.user.external_accounts.view',
    'page' => '외부계정연동',
    'page_description' => '외부계정 연동관리',
    'permission_name' => '목록조회',
    'permission_description' => '외부계정 목록 조회',
    'name' => '외부계정 목록조회',
    'description' => '내정보 > 프로필 > 외부계정연동 > 목록조회',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/user/external-accounts/get', 'ExternalAccountController@apiGet', [
    'key' => 'api.user.external_accounts.detail',
    'page' => '외부계정연동',
    'page_description' => '외부계정 연동관리',
    'permission_name' => '상세조회',
    'permission_description' => '외부계정 상세 조회',
    'name' => '외부계정 상세조회',
    'description' => '내정보 > 프로필 > 외부계정연동 > 상세조회',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/user/external-accounts/save', 'ExternalAccountController@apiSave', [
    'key' => 'api.user.external_accounts.edit',
    'page' => '외부계정연동',
    'page_description' => '외부계정 연동관리',
    'permission_name' => '저장',
    'permission_description' => '외부계정 저장',
    'name' => '외부계정 저장',
    'description' => '내정보 > 프로필 > 외부계정연동 > 저장',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/user/external-accounts/delete', 'ExternalAccountController@apiDelete', [
    'key' => 'api.user.external_accounts.delete',
    'page' => '외부계정연동',
    'page_description' => '외부계정 연동관리',
    'permission_name' => '삭제',
    'permission_description' => '외부계정 삭제',
    'name' => '외부계정 삭제',
    'description' => '내정보 > 프로필 > 외부계정연동 > 삭제',
    'category' => '내정보 > 프로필',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->get('/api/settings/system/database/get', 'SystemController@apiDatabaseGet', [
    'key' => 'api.settings.system.database.view',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '조회',
    'permission_description' => '데이터베이스백업 조회',
    'name' => '데이터베이스백업 조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/database/save', 'SystemController@apiDatabaseSave', [
    'key' => 'api.settings.system.database.edit',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '저장',
    'permission_description' => '데이터베이스백업 저장',
    'name' => '데이터베이스백업 저장',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 저장',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/system/database/run', 'SystemController@apiBackupRun', [
    'key' => 'api.settings.system.database.run',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '백업실행',
    'permission_description' => '데이터베이스 백업 실행',
    'name' => '데이터베이스백업 백업실행',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 백업실행',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/database/info', 'SystemController@apiBackupInfo', [
    'key' => 'api.settings.system.database.info',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '정보조회',
    'permission_description' => '데이터베이스백업 정보 조회',
    'name' => '데이터베이스백업 정보조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 정보조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/system/database/log', 'SystemController@apiBackupLog', [
    'key' => 'api.settings.system.database.log',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '로그조회',
    'permission_description' => '데이터베이스백업 로그 조회',
    'name' => '데이터베이스백업 로그조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 로그조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/system/database/replication-status', 'SystemController@apiDatabaseReplicationStatus', [
    'key' => 'api.settings.system.database.replication',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '복제상태조회',
    'permission_description' => '데이터베이스 복제 상태 조회',
    'name' => '데이터베이스백업 복제상태조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 복제상태조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/system/database/status', 'DatabaseActiveController@apiStatus', [
    'key' => 'api.settings.system.database.status',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '상태조회',
    'permission_description' => '데이터베이스 상태 조회',
    'name' => '데이터베이스백업 상태조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 상태조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/database/switch-active', 'DatabaseActiveController@apiSwitchActive', [
    'key' => 'api.settings.system.database.switch-active',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => 'Active DB 전환',
    'permission_description' => '현재 Active DB를 수동으로 전환합니다.',
    'name' => '데이터베이스백업 Active DB 전환',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > Active DB 전환',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/settings/system/database/sync', 'DatabaseSyncController@apiSync', [
    'key' => 'api.settings.system.database.sync',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '동기화',
    'permission_description' => '데이터베이스 동기화 실행',
    'name' => '데이터베이스백업 동기화',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 동기화',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/database/sync-info', 'DatabaseSyncController@apiSyncInfo', [
    'key' => 'api.settings.system.database.sync-info',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '동기화상태조회',
    'permission_description' => '데이터베이스 동기화 상태 조회',
    'name' => '데이터베이스백업 동기화상태조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 동기화상태조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/settings/system/database/activity-log', 'DatabaseLogController@apiActivityLog', [
    'key' => 'api.settings.system.database.activity-log',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '통합로그조회',
    'permission_description' => '데이터베이스 통합 로그 조회',
    'name' => '데이터베이스백업 통합로그조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 통합로그조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/database/restore', 'DatabaseRestoreController@apiRestore', [
    'key' => 'api.settings.system.database.restore',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '복구',
    'permission_description' => '데이터베이스백업 복구',
    'name' => '데이터베이스백업 복구',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 복구',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/system/database/restore-info', 'DatabaseRestoreController@apiRestoreInfo', [
    'key' => 'api.settings.system.database.restore-info',
    'page' => '데이터베이스백업',
    'page_description' => '데이터베이스 백업 관리',
    'permission_name' => '조회',
    'permission_description' => '데이터베이스백업 조회',
    'name' => '데이터베이스백업 조회',
    'description' => '설정 > 시스템설정 > 데이터베이스백업 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/api/system/notifications', 'NotificationController@apiList', [
    'key' => 'api.system.notifications',
    'page' => '알림센터',
    'page_description' => '알림센터 관리',
    'permission_name' => '조회',
    'permission_description' => '알림 조회',
    'name' => '알림 조회',
    'description' => '대시보드 > 대시보드 > 알림센터 > 조회',
    'category' => '대시보드 > 대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'skip_permission' => true,
    'log' => false,
]);

$router->post('/api/system/notifications/read', 'NotificationController@apiRead', [
    'key' => 'api.system.notifications.read',
    'page' => '알림센터',
    'page_description' => '알림센터 관리',
    'permission_name' => '읽음처리',
    'permission_description' => '알림 읽음 처리',
    'name' => '알림 읽음처리',
    'description' => '대시보드 > 대시보드 > 알림센터 > 읽음처리',
    'category' => '대시보드 > 대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'skip_permission' => true,
    'log' => false,
]);

$router->post('/api/system/notifications/read-all', 'NotificationController@apiReadAll', [
    'key' => 'api.system.notifications.read_all',
    'page' => '알림센터',
    'page_description' => '알림센터 관리',
    'permission_name' => '전체읽음처리',
    'permission_description' => '알림 전체 읽음 처리',
    'name' => '알림 전체읽음처리',
    'description' => '대시보드 > 대시보드 > 알림센터 > 전체읽음처리',
    'category' => '대시보드 > 대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'skip_permission' => true,
    'log' => false,
]);

$router->post('/api/settings/system/logs/view', 'SystemController@apiLogView', [
    'key' => 'api.settings.system.logs.view',
    'page' => '시스템로그',
    'page_description' => '시스템 로그 관리',
    'permission_name' => '조회',
    'permission_description' => '시스템로그 조회',
    'name' => '시스템로그 조회',
    'description' => '설정 > 시스템설정 > 시스템로그 > 조회',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/settings/system/logs/delete', 'SystemController@apiLogDelete', [
    'key' => 'api.settings.system.logs.delete',
    'page' => '시스템로그',
    'page_description' => '시스템 로그 관리',
    'permission_name' => '삭제',
    'permission_description' => '시스템로그 삭제',
    'name' => '시스템로그 삭제',
    'description' => '설정 > 시스템설정 > 시스템로그 > 삭제',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);

$router->post('/api/settings/system/logs/delete-all', 'SystemController@apiLogDeleteAll', [
    'key' => 'api.settings.system.logs.delete_all',
    'page' => '시스템로그',
    'page_description' => '시스템 로그 관리',
    'permission_name' => '전체삭제',
    'permission_description' => '시스템로그 전체 삭제',
    'name' => '시스템로그 전체삭제',
    'description' => '설정 > 시스템설정 > 시스템로그 > 전체삭제',
    'category' => '설정 > 시스템설정',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true,
]);
