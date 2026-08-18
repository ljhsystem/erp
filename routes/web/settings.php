<?php

global $router;

$router->get('/dashboard/settings/standard/statutory-standards', 'StatutoryStandardController@index', [
    'key' => 'web.settings.statutory_standards.manage', 'page' => '법정 세율·요율·계산기준관리',
    'page_description' => 'ERP 공통 법정기준 SSOT 관리', 'permission_name' => '법정기준관리 조회',
    'permission_description' => '법정기준관리 화면 조회', 'name' => '법정기준관리',
    'description' => '설정 > 기준관리 > 법정기준관리', 'category' => '설정 > 기준관리',
    'auth' => true, 'permissions' => ['view'], 'log' => true,
]);

$router->get('/dashboard/settings/base-info/company', 'DashboardController@settingsBaseInfoCompany', [
    'key' => 'web.settings.base-info.company',
    'page' => '회사정보',
    'page_description' => '회사정보 설정 화면',
    'permission_name' => '회사정보 조회',
    'permission_description' => '회사정보 설정 화면 조회 권한',
    'name' => '회사정보',
    'description' => '설정 > 기초정보관리 > 회사정보',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/base-info/brand', 'DashboardController@settingsBaseInfoBrand', [
    'key' => 'web.settings.base-info.brand_logo',
    'page' => '브랜드',
    'page_description' => '브랜드 설정 화면',
    'permission_name' => '브랜드 조회',
    'permission_description' => '브랜드 설정 화면 조회 권한',
    'name' => '브랜드',
    'description' => '설정 > 기초정보관리 > 브랜드',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/base-info/brand-logo', 'DashboardController@redirectBaseInfoBrandLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/base-info/cover', 'DashboardController@settingsBaseInfoCover', [
    'key' => 'web.settings.base-info.cover',
    'page' => '커버이미지',
    'page_description' => '커버이미지 설정 화면',
    'permission_name' => '커버이미지 조회',
    'permission_description' => '커버이미지 설정 화면 조회 권한',
    'name' => '커버이미지',
    'description' => '설정 > 기초정보관리 > 커버이미지',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/standard/code', 'DashboardController@settingsStandardCode', [
    'key' => 'code.view',
    'page' => '코드관리',
    'page_description' => '공통 코드 관리 화면',
    'permission_name' => '코드관리 조회',
    'permission_description' => '코드관리 화면 조회 권한',
    'name' => '코드관리',
    'description' => '설정 > 기준관리 > 코드관리',
    'category' => '설정 > 기준관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/base-info/client', 'DashboardController@settingsBaseInfoClient', [
    'key' => 'web.settings.base-info.clients',
    'page' => '거래처',
    'page_description' => '거래처 설정 화면',
    'permission_name' => '거래처 조회',
    'permission_description' => '거래처 설정 화면 조회 권한',
    'name' => '거래처',
    'description' => '설정 > 기초정보관리 > 거래처',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/base-info/clients', 'DashboardController@redirectBaseInfoClientLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/base-info/project', 'DashboardController@settingsBaseInfoProject', [
    'key' => 'web.settings.base-info.projects',
    'page' => '프로젝트',
    'page_description' => '프로젝트 설정 화면',
    'permission_name' => '프로젝트 조회',
    'permission_description' => '프로젝트 설정 화면 조회 권한',
    'name' => '프로젝트',
    'description' => '설정 > 기초정보관리 > 프로젝트',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/base-info/projects', 'DashboardController@redirectBaseInfoProjectLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/base-info/bank-account', 'DashboardController@settingsBaseInfoBankAccount', [
    'key' => 'web.settings.base-info.accounts',
    'page' => '계좌',
    'page_description' => '계좌 설정 화면',
    'permission_name' => '계좌 조회',
    'permission_description' => '계좌 설정 화면 조회 권한',
    'name' => '계좌',
    'description' => '설정 > 기초정보관리 > 계좌',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/base-info/bank-accounts', 'DashboardController@redirectBaseInfoBankAccountLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/base-info/card', 'DashboardController@settingsBaseInfoCard', [
    'key' => 'web.settings.base-info.cards',
    'page' => '카드',
    'page_description' => '카드 설정 화면',
    'permission_name' => '카드 조회',
    'permission_description' => '카드 설정 화면 조회 권한',
    'name' => '카드',
    'description' => '설정 > 기초정보관리 > 카드',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/base-info/cards', 'DashboardController@redirectBaseInfoCardLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/base-info/work-teams', 'DashboardController@redirectBaseInfoWorkTeamLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/base-info/work-team', 'DashboardController@settingsBaseInfoWorkTeams', [
    'key' => 'work_team.view',
    'page' => '팀',
    'page_description' => '팀 설정 화면',
    'permission_name' => '팀 조회',
    'permission_description' => '팀 설정 화면 조회 권한',
    'name' => '팀',
    'description' => '설정 > 기초정보관리 > 팀',
    'category' => '설정 > 기초정보관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/organization/employee', 'DashboardController@settingsOrgEmployees', [
    'key' => 'web.settings.organization.employees',
    'page' => '직원',
    'page_description' => '직원 설정 화면',
    'permission_name' => '직원 조회',
    'permission_description' => '직원 설정 화면 조회 권한',
    'name' => '직원',
    'description' => '설정 > 조직관리 > 직원',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/organization/employees', 'DashboardController@redirectOrgEmployeeLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/organization/department', 'DashboardController@settingsOrgDepartments', [
    'key' => 'web.settings.organization.departments',
    'page' => '부서',
    'page_description' => '부서 설정 화면',
    'permission_name' => '부서 조회',
    'permission_description' => '부서 설정 화면 조회 권한',
    'name' => '부서',
    'description' => '설정 > 조직관리 > 부서',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/organization/departments', 'DashboardController@redirectOrgDepartmentLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/organization/position', 'DashboardController@settingsOrgPositions', [
    'key' => 'web.settings.organization.positions',
    'page' => '직책',
    'page_description' => '직책 설정 화면',
    'permission_name' => '직책 조회',
    'permission_description' => '직책 설정 화면 조회 권한',
    'name' => '직책',
    'description' => '설정 > 조직관리 > 직책',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/organization/positions', 'DashboardController@redirectOrgPositionLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);

$router->get('/dashboard/settings/organization/permission-assignment', 'DashboardController@settingsOrgPermissionAssignment', [
    'key' => 'web.settings.organization.role_permissions',
    'page' => '권한부여',
    'page_description' => '권한부여 설정 화면',
    'permission_name' => '권한부여 조회',
    'permission_description' => '권한부여 설정 화면 조회 권한',
    'name' => '권한부여',
    'description' => '설정 > 조직관리 > 권한부여',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/organization/approval-template', 'DashboardController@settingsOrgApprovalTemplate', [
    'key' => 'web.settings.organization.approval',
    'page' => '결재템플릿',
    'page_description' => '결재템플릿 설정 화면',
    'permission_name' => '결재템플릿 조회',
    'permission_description' => '결재템플릿 설정 화면 조회 권한',
    'name' => '결재템플릿',
    'description' => '설정 > 조직관리 > 결재템플릿',
    'category' => '설정 > 조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->get('/dashboard/settings/organization/approval', 'DashboardController@redirectOrgApprovalLegacy', [
    'skip_permission' => true,
    'auth' => true,
    'permissions' => [],
    'log' => false,
]);
