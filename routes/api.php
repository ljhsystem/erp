<?php
error_log('[ROUTES] api.php LOADED');

// 경로: PROJECT_ROOT . '/routes/api.php';
global $router;

/* =========================================
 * 계정 잠금 / 인증 관련 API (★ 퍼미션 체크 제외)
 * -----------------------------------------
 * 로그인 전에도 접근해야 하는 API이므로
 * 권한(permission) 체크를 절대 수행하면 안 됨.
 * ========================================= */
// 계정 잠금 상태 조회
$router->get('/api/account/lock/status', 'AccountLockController@apiStatus');
// 계정 잠금 설정
$router->post('/api/account/lock/set', 'AccountLockController@apiLock');
// 계정 잠금 해제
$router->post('/api/account/lock/unlock', 'AccountLockController@apiUnlock');
// 회원가입 API
$router->post('/api/auth/register', 'RegisterController@apiRegister');
// 2단계 인증 API
$router->post('/api/2fa/verify', 'TwoFactorController@apiVerify');
// 로그인 API
$router->post('/api/auth/login', 'LoginController@apiLogin');
// 만료일(비밀번호변경) API
$router->post('/api/auth/password/change', 'PasswordController@apiChangePassword');
// 만료일(비밀번호 변경 유예) API
$router->post('/api/auth/password/change-later', 'PasswordController@apiChangeLater');
// 문의(Contact) — ★ 퍼미션 체크 제외
$router->post('/api/contact/send', 'ContactController@apiSend');
// 가입 승인 — ★ 퍼미션 체크 제외
$router->post('/api/approve/user', 'ApprovalController@apiApproveUser');







// 사업자 상태 조회 (외부 연동)
$router->post('/api/integration/biz-status', 'ExternalIntegrationController@apiBizStatus', [
    'key'         => 'api.integration.biz-status',
    'name'        => '사업자 상태 조회',
    'description' => '외부 API를 통해 사업자등록 상태 조회',
    'category'    => '외부연동'
]);








/* =========================================================
 * 회사 기본정보 단건 조회
 * ========================================================= */
$router->get('/api/settings/base-info/company/detail', 'CompanyController@apiDetail', [
    'key'         => 'api.settings.base-info.company.view',
    'name'        => '회사 기본정보 조회',
    'description' => '시스템에 단 1건 존재하는 회사 기본정보 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 회사 기본정보 저장 (신규 / 수정)
 * ========================================================= */
$router->post('/api/settings/base-info/company/save', 'CompanyController@apiSave', [
    'key'         => 'api.settings.base-info.company.save',
    'name'        => '회사 기본정보 저장',
    'description' => '시스템에 단 1건 존재하는 회사 기본정보 신규/수정',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);








/* =========================================================
 * 브랜드 자산 목록 조회
 * ========================================================= */
$router->post('/api/settings/base-info/brand/list', 'BrandController@apiList', [
    'key'         => 'api.settings.base-info.brand.list',
    'name'        => '브랜드 자산 목록 조회',
    'description' => '브랜드 자산 전체 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 브랜드 자산 단건 조회
 * ========================================================= */
$router->post('/api/settings/base-info/brand/detail', 'BrandController@apiDetail', [
    'key'         => 'api.settings.base-info.brand.detail',
    'name'        => '브랜드 자산 단건 조회',
    'description' => '브랜드 자산 단건 조회 (ID 기준)',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 브랜드 자산 검색
 * ========================================================= */
$router->post('/api/settings/base-info/brand/active-type', 'BrandController@apiActiveType', [
    'key'         => 'api.settings.base-info.brand.active-type',
    'name'        => '브랜드 자산 활성 조회',
    'description' => '타입별 활성 브랜드 자산 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 브랜드 자산 저장 (신규 / 수정)
 * ========================================================= */
$router->post('/api/settings/base-info/brand/save', 'BrandController@apiSave', [
    'key'         => 'api.settings.base-info.brand.save',
    'name'        => '브랜드 자산 저장',
    'description' => '브랜드 자산 신규 등록 또는 수정',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 브랜드 자산 삭제
 * ========================================================= */
$router->post('/api/settings/base-info/brand/purge', 'BrandController@apiPurge', [
    'key'         => 'api.settings.base-info.brand.delete',
    'name'        => '브랜드 자산 삭제',
    'description' => '브랜드 자산 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 브랜드 자산 상태 변경 (활성/비활성)
 * ========================================================= */
$router->post('/api/settings/base-info/brand/updatestatus', 'BrandController@apiUpdateStatus', [
    'key'         => 'api.settings.base-info.brand.status',
    'name'        => '브랜드 자산 상태 변경',
    'description' => '브랜드 자산 활성/비활성 처리',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['update'],
    'log'         => true,
]);









/* =========================================================
 * 커버 이미지 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/cover/list', 'CoverController@apiList', [
    'key'         => 'api.settings.base-info.cover.list',
    'name'        => '커버 이미지 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 오픈 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/cover/public', 'CoverController@apiPublicList', [
    'key'         => 'api.settings.base-info.cover.public',
    'name'        => '커버 이미지 오픈 목록 조회',
    'category'    => '시스템설정',
    'auth'        => false,
    'permissions' => [],
    'log'         => false,
]);

/* =========================================================
 * 커버 이미지 단건 조회
 * ========================================================= */
$router->get('/api/settings/base-info/cover/detail', 'CoverController@apiDetail', [
    'key'         => 'api.settings.base-info.cover.detail',
    'name'        => '커버 이미지 단건 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 저장
 * ========================================================= */
$router->post('/api/settings/base-info/cover/save', 'CoverController@apiSave', [
    'key'         => 'api.settings.base-info.cover.save',
    'name'        => '커버 이미지 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 삭제 (소프트)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/delete', 'CoverController@apiDelete', [
    'key'         => 'api.settings.base-info.cover.delete',
    'name'        => '커버 이미지 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 휴지통 목록
 * ========================================================= */
$router->get('/api/settings/base-info/cover/trash', 'CoverController@apiTrashList', [
    'key'         => 'api.settings.base-info.cover.trash.list',
    'name'        => '커버 이미지 휴지통 목록',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 복원 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/restore', 'CoverController@apiRestore', [
    'key'         => 'api.settings.base-info.cover.restore',
    'name'        => '커버 이미지 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 복원 (다건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/restore-bulk', 'CoverController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.cover.restore.bulk',
    'name'        => '커버 이미지 일괄 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 복원 (전체)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/restore-all', 'CoverController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.cover.restore.all',
    'name'        => '커버 이미지 전체 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 영구삭제 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/purge', 'CoverController@apiPurge', [
    'key'         => 'api.settings.base-info.cover.purge',
    'name'        => '커버 이미지 영구 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 영구삭제 (다건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/purge-bulk', 'CoverController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.cover.purge.bulk',
    'name'        => '커버 이미지 일괄 영구 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 영구삭제 (전체)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/purge-all', 'CoverController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.cover.purge.all',
    'name'        => '커버 이미지 전체 영구 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 커버 이미지 순서 변경
 * ========================================================= */
$router->post('/api/settings/base-info/cover/reorder', 'CoverController@apiReorder', [
    'key'         => 'api.settings.base-info.cover.reorder',
    'name'        => '커버 이미지 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);











/* =========================================================
 * 거래처 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/client/list', 'ClientController@apiList', [
    'key'         => 'api.settings.base-info.client.list',
    'name'        => '거래처 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 상세 조회
 * ========================================================= */
$router->get('/api/settings/base-info/client/detail', 'ClientController@apiDetail', [
    'key'         => 'api.settings.base-info.client.detail',
    'name'        => '거래처 상세 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 자동완성 검색 (Picker)
 * ========================================================= */
$router->get('/api/settings/base-info/client/search-picker', 'ClientController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.client.search-picker',
    'name'        => '거래처 자동검색',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 거래처 저장
 * ========================================================= */
$router->post('/api/settings/base-info/client/save', 'ClientController@apiSave', [
    'key'         => 'api.settings.base-info.client.save',
    'name'        => '거래처 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 삭제 (soft)
 * ========================================================= */
$router->post('/api/settings/base-info/client/delete', 'ClientController@apiDelete', [
    'key'         => 'api.settings.base-info.client.delete',
    'name'        => '거래처 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 휴지통
 * ========================================================= */
$router->get('/api/settings/base-info/client/trash', 'ClientController@apiTrashList', [
    'key'         => 'api.settings.base-info.client.trash.list',
    'name'        => '거래처 휴지통 목록',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 복원 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/client/restore', 'ClientController@apiRestore', [
    'key'         => 'api.settings.base-info.client.restore',
    'name'        => '거래처 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 복원 (bulk)
 * ========================================================= */
$router->post('/api/settings/base-info/client/restore-bulk', 'ClientController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.client.restore-bulk',
    'name'        => '거래처 일괄 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 전체 복원
 * ========================================================= */
$router->post('/api/settings/base-info/client/restore-all', 'ClientController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.client.restore-all',
    'name'        => '거래처 전체 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 완전삭제 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/client/purge', 'ClientController@apiPurge', [
    'key'         => 'api.settings.base-info.client.purge',
    'name'        => '거래처 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 완전삭제 (bulk)
 * ========================================================= */
$router->post('/api/settings/base-info/client/purge-bulk', 'ClientController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.client.purge-bulk',
    'name'        => '거래처 일괄 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 전체 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/client/purge-all', 'ClientController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.client.purge-all',
    'name'        => '거래처 전체 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 순서 변경
 * ========================================================= */
$router->post('/api/settings/base-info/client/reorder', 'ClientController@apiReorder', [
    'key'         => 'api.settings.base-info.client.reorder',
    'name'        => '거래처 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 엑셀 양식
 * ========================================================= */
$router->get('/api/settings/base-info/client/template', 'ClientController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.client.template',
    'name'        => '거래처 엑셀 양식 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 거래처 엑셀 업로드
 * ========================================================= */
$router->post('/api/settings/base-info/client/excel-upload', 'ClientController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.client.excel-upload',
    'name'        => '거래처 엑셀 업로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 거래처 엑셀 다운로드
 * ========================================================= */
$router->get('/api/settings/base-info/client/download', 'ClientController@apiDownload', [
    'key'         => 'api.settings.base-info.client.excel',
    'name'        => '거래처 엑셀 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);









/* =========================================================
 * 프로젝트 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/project/list', 'ProjectController@apiList', [
    'key'         => 'api.settings.base-info.project.list',
    'name'        => '프로젝트 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 상세 조회
 * ========================================================= */
$router->get('/api/settings/base-info/project/detail', 'ProjectController@apiDetail', [
    'key'         => 'api.settings.base-info.project.detail',
    'name'        => '프로젝트 상세 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 검색 (Picker)
 * ========================================================= */
$router->get('/api/settings/base-info/project/search-picker', 'ProjectController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.project.search-picker',
    'name'        => '프로젝트 자동검색',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 프로젝트 저장
 * ========================================================= */
$router->post('/api/settings/base-info/project/save', 'ProjectController@apiSave', [
    'key'         => 'api.settings.base-info.project.save',
    'name'        => '프로젝트 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 삭제 (soft)
 * ========================================================= */
$router->post('/api/settings/base-info/project/delete', 'ProjectController@apiDelete', [
    'key'         => 'api.settings.base-info.project.delete',
    'name'        => '프로젝트 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 휴지통 목록
 * ========================================================= */
$router->get('/api/settings/base-info/project/trash', 'ProjectController@apiTrashList', [
    'key'         => 'api.settings.base-info.project.trash.list',
    'name'        => '프로젝트 휴지통 목록',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 복원 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/project/restore', 'ProjectController@apiRestore', [
    'key'         => 'api.settings.base-info.project.restore',
    'name'        => '프로젝트 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 복원 (bulk)
 * ========================================================= */
$router->post('/api/settings/base-info/project/restore-bulk', 'ProjectController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.project.restore-bulk',
    'name'        => '프로젝트 일괄 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 전체 복원
 * ========================================================= */
$router->post('/api/settings/base-info/project/restore-all', 'ProjectController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.project.restore-all',
    'name'        => '프로젝트 전체 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 완전삭제 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/project/purge', 'ProjectController@apiPurge', [
    'key'         => 'api.settings.base-info.project.purge',
    'name'        => '프로젝트 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 완전삭제 (bulk)
 * ========================================================= */
$router->post('/api/settings/base-info/project/purge-bulk', 'ProjectController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.project.purge-bulk',
    'name'        => '프로젝트 일괄 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 전체 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/project/purge-all', 'ProjectController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.project.purge-all',
    'name'        => '프로젝트 전체 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 순서 변경
 * ========================================================= */
$router->post('/api/settings/base-info/project/reorder', 'ProjectController@apiReorder', [
    'key'         => 'api.settings.base-info.project.reorder',
    'name'        => '프로젝트 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 엑셀 템플릿
 * ========================================================= */
$router->get('/api/settings/base-info/project/template', 'ProjectController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.project.template',
    'name'        => '프로젝트 엑셀 양식 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 프로젝트 엑셀 업로드
 * ========================================================= */
$router->post('/api/settings/base-info/project/excel-upload', 'ProjectController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.project.excel-upload',
    'name'        => '프로젝트 엑셀 업로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 프로젝트 엑셀 다운로드
 * ========================================================= */
$router->get('/api/settings/base-info/project/download', 'ProjectController@apiDownload', [
    'key'         => 'api.settings.base-info.project.excel',
    'name'        => '프로젝트 엑셀 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);















/* =========================================================
 * 계좌 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/bank-account/list', 'BankAccountController@apiList', [
    'key'         => 'api.settings.base-info.bank-account.list',
    'name'        => '계좌 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 상세 조회
 * ========================================================= */
$router->get('/api/settings/base-info/bank-account/detail', 'BankAccountController@apiDetail', [
    'key'         => 'api.settings.base-info.bank-account.detail',
    'name'        => '계좌 상세 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 검색 (Picker)
 * ========================================================= */
$router->get('/api/settings/base-info/bank-account/search-picker', 'BankAccountController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.bank-account.search-picker',
    'name'        => '계좌 자동검색',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 계좌 저장
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/save', 'BankAccountController@apiSave', [
    'key'         => 'api.settings.base-info.bank-account.save',
    'name'        => '계좌 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 삭제 (soft)
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/delete', 'BankAccountController@apiDelete', [
    'key'         => 'api.settings.base-info.bank-account.delete',
    'name'        => '계좌 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 휴지통 목록
 * ========================================================= */
$router->get('/api/settings/base-info/bank-account/trash', 'BankAccountController@apiTrashList', [
    'key'         => 'api.settings.base-info.bank-account.trash.list',
    'name'        => '계좌 휴지통 목록',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 복원
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/restore', 'BankAccountController@apiRestore', [
    'key'         => 'api.settings.base-info.bank-account.restore',
    'name'        => '계좌 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 선택 복원
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/restore-bulk', 'BankAccountController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.bank-account.restore-bulk',
    'name'        => '계좌 선택 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 전체 복원
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/restore-all', 'BankAccountController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.bank-account.restore-all',
    'name'        => '계좌 전체 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/purge', 'BankAccountController@apiPurge', [
    'key'         => 'api.settings.base-info.bank-account.purge',
    'name'        => '계좌 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 선택 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/purge-bulk', 'BankAccountController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.bank-account.purge-bulk',
    'name'        => '계좌 선택 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 전체 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/purge-all', 'BankAccountController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.bank-account.purge-all',
    'name'        => '계좌 전체 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 순서 변경
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/reorder', 'BankAccountController@apiReorder', [
    'key'         => 'api.settings.base-info.bank-account.reorder',
    'name'        => '계좌 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 엑셀 템플릿
 * ========================================================= */
$router->get('/api/settings/base-info/bank-account/template', 'BankAccountController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.bank-account.template',
    'name'        => '계좌 엑셀 양식 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 계좌 엑셀 업로드
 * ========================================================= */
$router->post('/api/settings/base-info/bank-account/excel-upload', 'BankAccountController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.bank-account.excel-upload',
    'name'        => '계좌 엑셀 업로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 계좌 엑셀 다운로드
 * ========================================================= */
$router->get('/api/settings/base-info/bank-account/download', 'BankAccountController@apiDownload', [
    'key'         => 'api.settings.base-info.bank-account.excel',
    'name'        => '계좌 엑셀 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);










/* =========================================================
 * 카드 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/card/list', 'CardController@apiList', [
    'key'         => 'api.settings.base-info.card.list',
    'name'        => '카드 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 카드 상세 조회
 * ========================================================= */
$router->get('/api/settings/base-info/card/detail', 'CardController@apiDetail', [
    'key'         => 'api.settings.base-info.card.detail',
    'name'        => '카드 상세 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 카드 검색 (Picker)
 * ========================================================= */
$router->get('/api/settings/base-info/card/search-picker', 'CardController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.card.search-picker',
    'name'        => '카드 자동검색',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);




/* =========================================================
 * 카드 저장
 * ========================================================= */
$router->post('/api/settings/base-info/card/save', 'CardController@apiSave', [
    'key'         => 'api.settings.base-info.card.save',
    'name'        => '카드 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 카드 삭제 (soft)
 * ========================================================= */
$router->post('/api/settings/base-info/card/delete', 'CardController@apiDelete', [
    'key'         => 'api.settings.base-info.card.delete',
    'name'        => '카드 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 카드 휴지통 목록
 * ========================================================= */
$router->get('/api/settings/base-info/card/trash', 'CardController@apiTrashList', [
    'key'         => 'api.settings.base-info.card.trash.list',
    'name'        => '카드 휴지통 목록',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

/* =========================================================
 * 카드 복원
 * ========================================================= */
$router->post('/api/settings/base-info/card/restore', 'CardController@apiRestore', [
    'key'         => 'api.settings.base-info.card.restore',
    'name'        => '카드 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 카드 선택 복원
 * ========================================================= */
$router->post('/api/settings/base-info/card/restore-bulk', 'CardController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.card.restore-bulk',
    'name'        => '카드 선택 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 카드 전체 복원
 * ========================================================= */
$router->post('/api/settings/base-info/card/restore-all', 'CardController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.card.restore-all',
    'name'        => '카드 전체 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 카드 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/card/purge', 'CardController@apiPurge', [
    'key'         => 'api.settings.base-info.card.purge',
    'name'        => '카드 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 카드 선택 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/card/purge-bulk', 'CardController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.card.purge-bulk',
    'name'        => '카드 선택 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 카드 전체 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/card/purge-all', 'CardController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.card.purge-all',
    'name'        => '카드 전체 완전삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

/* =========================================================
 * 카드 순서 변경
 * ========================================================= */
$router->post('/api/settings/base-info/card/reorder', 'CardController@apiReorder', [
    'key'         => 'api.settings.base-info.card.reorder',
    'name'        => '카드 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 카드 엑셀 템플릿
 * ========================================================= */
$router->get('/api/settings/base-info/card/template', 'CardController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.card.template',
    'name'        => '카드 엑셀 양식 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 카드 엑셀 업로드
 * ========================================================= */
$router->post('/api/settings/base-info/card/excel-upload', 'CardController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.card.excel-upload',
    'name'        => '카드 엑셀 업로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 카드 엑셀 다운로드
 * ========================================================= */
$router->get('/api/settings/base-info/card/download', 'CardController@apiDownload', [
    'key'         => 'api.settings.base-info.card.excel',
    'name'        => '카드 엑셀 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);











// ============================================================
// 직원 목록 조회
// ============================================================
$router->get('/api/settings/organization/employee/list', 'EmployeeController@apiList', [
    'key'         => 'api.settings.employee.list',
    'name'        => '직원 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 직원 상세 조회
// ============================================================
$router->get('/api/settings/organization/employee/detail', 'EmployeeController@apiDetail', [
    'key'         => 'api.settings.employee.detail',
    'name'        => '직원 상세 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 직원 검색 (Select2)
// ============================================================
$router->get('/api/settings/organization/employee/search-picker', 'EmployeeController@apiSearchPicker', [
    'key'         => 'api.settings.employee.search',
    'name'        => '직원 검색',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 직원 저장
// ============================================================
$router->post('/api/settings/organization/employee/save', 'EmployeeController@apiSave', [
    'key'         => 'api.settings.employee.save',
    'name'        => '직원 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

// ============================================================
// 직원 상태 변경
// ============================================================
$router->post('/api/settings/organization/employee/update-status', 'EmployeeController@apiUpdateStatus', [
    'key'         => 'api.settings.employee.update-status',
    'name'        => '직원 상태 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);



// ============================================================
// 직원 영구삭제
// ============================================================
$router->post('/api/settings/organization/employee/purge', 'EmployeeController@apiPurge', [
    'key'         => 'api.settings.employee.purge',
    'name'        => '직원 영구삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

// ============================================================
// 직원 순서 변경
// ============================================================
$router->post('/api/settings/organization/employee/reorder', 'EmployeeController@apiReorder', [
    'key'         => 'api.settings.employee.reorder',
    'name'        => '직원 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);












/* =========================================
 * 사용자 프로필 API (표준화 완료)
 * ========================================= */

// ============================================================
// 프로필 조회
// ============================================================
$router->get('/api/user/profile/detail', 'ProfileController@apiDetail', [
    'key'         => 'api.user.profile.detail',
    'name'        => '프로필 조회',
    'description' => '내 프로필 또는 특정 사용자 프로필 조회',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 프로필 저장
// ============================================================
$router->post('/api/user/profile/save', 'ProfileController@apiSave', [
    'key'         => 'api.user.profile.save',
    'name'        => '프로필 저장',
    'description' => '프로필 생성 및 수정 (이미지 포함)',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);














/* =========================
   부서
========================= */

// 목록
$router->get('/api/settings/organization/department/list', 'DepartmentController@apiList', [
    'key'         => 'api.settings.department.list',
    'name'        => '부서 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);
$router->post('/api/settings/organization/department/list', 'DepartmentController@apiList', [
    'key'         => 'api.settings.department.list.post',
    'name'        => '부서 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 저장
$router->post('/api/settings/organization/department/save', 'DepartmentController@apiSave', [
    'key'         => 'api.settings.department.save',
    'name'        => '부서 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/department/reorder', 'DepartmentController@apiReorder', [
    'key'         => 'api.settings.department.reorder',
    'name'        => '부서 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);




/* =========================
   직책
========================= */

// 직책 목록 (GET + POST 허용)
$router->get('/api/settings/organization/position/list', 'PositionController@apiList', [
    'key'         => 'api.settings.position.list',
    'name'        => '직책 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);
$router->post('/api/settings/organization/position/list', 'PositionController@apiList', [
    'key'         => 'api.settings.position.list.post',
    'name'        => '직책 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]); // 🔥 수정
// 직책 저장
$router->post('/api/settings/organization/position/save', 'PositionController@apiSave', [
    'key'         => 'api.settings.position.save',
    'name'        => '직책 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/position/reorder', 'PositionController@apiReorder', [
    'key'         => 'api.settings.position.reorder',
    'name'        => '직책 순서 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);






/* =========================================
 * 역할 / 권한
 * ========================================= */
$router->get('/api/settings/organization/role/list', 'RoleController@apiList', [
    'key' => 'api.settings.role.list.get',
    'name' => '역할 목록 조회',
    'description' => '역할 리스트 조회',
    'category' => '권한관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
]);

$router->post('/api/settings/organization/role/list', 'RoleController@apiList', [
    'key' => 'api.settings.role.list',
    'name' => '역할 목록 조회',
    'description' => '역할 리스트 조회',
    'category' => '권한관리'
]);

$router->post('/api/settings/organization/role/save', 'RoleController@apiSave', [
    'key' => 'api.settings.role.save',
    'name' => '역할 저장',
    'description' => '역할 정보 저장',
    'category' => '권한관리'
]);

$router->post('/api/settings/organization/role/reorder', 'RoleController@apiReorder', [
    'key' => 'api.settings.role.reorder',
    'name' => '역할 순서 변경',
    'description' => '역할 코드 순서 변경',
    'category' => '권한관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true
]);

$router->get('/api/settings/organization/permission/list', 'PermissionController@apiList', [
    'key' => 'api.settings.permission.list.get',
    'name' => '권한 목록 조회',
    'description' => '전체 권한 조회',
    'category' => '권한관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false
]);

$router->post('/api/settings/organization/permission/list', 'PermissionController@apiList', [
    'key' => 'api.settings.permission.list',
    'name' => '권한 목록 조회',
    'description' => '전체 권한 조회',
    'category' => '권한관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false
]);

$router->post('/api/settings/organization/permission/save', 'PermissionController@apiSave', [
    'key' => 'api.settings.permission.save',
    'name' => '권한 저장',
    'description' => '권한 정보 저장',
    'category' => '권한관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true
]);

$router->post('/api/settings/organization/permission/reorder', 'PermissionController@apiReorder', [
    'key' => 'api.settings.permission.reorder',
    'name' => '권한 순서 변경',
    'description' => '권한 코드 순서 변경',
    'category' => '권한관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true
]);

$router->post('/api/settings/organization/role-permission/list', 'RolePermissionController@apiList', [
    'key' => 'api.settings.rolepermission.list',
    'name' => '역할별 권한 조회',
    'description' => '역할에 부여된 권한 목록 조회',
    'category' => '권한관리'
]);

$router->post('/api/settings/organization/role-permission/assign', 'RolePermissionController@apiAssign', [
    'key' => 'api.settings.rolepermission.assign',
    'name' => '권한 부여',
    'description' => '역할에 권한 부여',
    'category' => '권한관리'
]);

$router->post('/api/settings/organization/role-permission/remove', 'RolePermissionController@apiRemove', [
    'key' => 'api.settings.rolepermission.remove',
    'name' => '권한 제거',
    'description' => '역할에서 권한 제거',
    'category' => '권한관리'
]);


/* =========================================
 * 결재(Approval)
 * ========================================= */
$router->post('/api/settings/organization/approval/template/list', 'ApprovalTemplateController@apiTemplateList', [
    'key' => 'api.settings.approval.template.list',
    'name' => '결재 템플릿 목록',
    'description' => '결재 템플릿 리스트 조회',
    'category' => '결재관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false
]);





$router->post('/api/settings/organization/approval/template/save', 'ApprovalTemplateController@apiTemplateSave', [
    'key' => 'api.settings.approval.template.save',
    'name' => '결재 템플릿 저장',
    'description' => '템플릿 저장',
    'category' => '결재관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true
]);

$router->post('/api/settings/organization/approval/template/delete', 'ApprovalTemplateController@apiTemplateDelete', [
    'key' => 'api.settings.approval.template.delete',
    'name' => '결재 템플릿 삭제',
    'description' => '템플릿 삭제',
    'category' => '결재관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true
]);

$router->post('/api/settings/organization/approval/step/list', 'ApprovalTemplateController@apiStepList', [
    'key' => 'api.settings.approval.step.list',
    'name' => '결재 단계 목록',
    'description' => '결재 단계 리스트 조회',
    'category' => '결재관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false
]);

$router->post('/api/settings/organization/approval/step/save', 'ApprovalTemplateController@apiStepSave', [
    'key' => 'api.settings.approval.step.save',
    'name' => '결재 단계 저장',
    'description' => '결재 단계 저장',
    'category' => '결재관리',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true
]);

$router->post('/api/settings/organization/approval/step/delete', 'ApprovalTemplateController@apiStepDelete', [
    'key' => 'api.settings.approval.step.delete',
    'name' => '결재 단계 삭제',
    'description' => '결재 단계 삭제',
    'category' => '결재관리',
    'auth' => true,
    'permissions' => ['delete'],
    'log' => true
]);


/* =========================================
 * 시스템 설정
 * ========================================= */
//사이트정보
$router->get('/api/settings/system/site/get', 'SystemController@apiSiteGet', [
    'key'         => 'api.settings.system.site.view',
    'name'        => '사이트 설정 조회',
    'description' => '사이트 기본설정(SITE) 카테고리 설정값 조회',
    'category'    => '시스템설정'
]);

$router->post('/api/settings/system/site/save', 'SystemController@apiSiteSave', [
    'key'         => 'api.settings.system.site.edit',
    'name'        => '사이트 설정 저장',
    'description' => '사이트 기본설정(SITE) 카테고리 설정값 저장',
    'category'    => '시스템설정'
]);
//세션
$router->get('/api/settings/system/session/get', 'SystemController@apiSessionGet', [
    'key'         => 'api.settings.system.session.view',
    'name'        => '세션 설정 조회',
    'description' => '세션 관리(SESSION) 카테고리 설정값 조회',
    'category'    => '시스템설정'
]);

$router->post('/api/settings/system/session/save', 'SystemController@apiSessionSave', [
    'key'         => 'api.settings.system.session.edit',
    'name'        => '세션 설정 저장',
    'description' => '세션 관리(SESSION) 카테고리 설정값 저장',
    'category'    => '시스템설정'
]);
//보안정책
$router->get('/api/settings/system/security/get', 'SystemController@apiSecurityGet', [
    'key'         => 'api.settings.system.security.view',
    'name'        => '보안 설정 조회',
    'description' => '보안 정책(SECURITY) 카테고리 설정값 조회',
    'category'    => '시스템설정'
]);

$router->post('/api/settings/system/security/save', 'SystemController@apiSecuritySave', [
    'key'         => 'api.settings.system.security.edit',
    'name'        => '보안 설정 저장',
    'description' => '보안 정책(SECURITY) 카테고리 설정값 저장',
    'category'    => '시스템설정'
]);


/* =========================================
 * 시스템 설정 - 외부 API
 * ========================================= */
// 외부 API 설정 조회
$router->get('/api/settings/system/api/get', 'SystemController@apiApiGet', [
    'key'         => 'api.settings.system.api.view',
    'name'        => '외부 API 설정 조회',
    'description' => '외부 연동(API) 설정 값을 조회',
    'category'    => '시스템설정'
]);

// 외부 API 설정 저장
$router->post('/api/settings/system/api/save', 'SystemController@apiApiSave', [
    'key'         => 'api.settings.system.api.edit',
    'name'        => '외부 API 설정 저장',
    'description' => '외부 연동(API) 설정 값을 저장',
    'category'    => '시스템설정'
]);

/* =========================================
 * 외부 API (External API)
 * ========================================= */
// 외부 API 핑 테스트
$router->get('/api/external/ping', 'ExternalApiController@ping', [
    'key'             => 'api.external.ping',
    'name'            => '외부 API 핑 테스트',
    'description'     => '외부 API 접근 가능 여부 확인',
    'category'        => '외부API',
    'skip_permission' => true,          // ⭐ 핵심
    'middleware'      => ['ApiAccessMiddleware']
]);
//외부 API - 직원 조회(테스트 25.12.16)
$router->get('/api/external/employees/list', 'ExternalEmployeeController@list', [
    'key'             => 'api.external.employee.list',
    'name'            => '외부 API 직원 목록 조회',
    'description'     => '외부 시스템(Excel 등)에서 직원 목록 조회',
    'category'        => '외부API',

    // ⭐ 핵심 차이점
    'skip_permission' => true,               // 내부 권한 시스템 완전 제외
    'middleware'      => ['ApiAccessMiddleware'], // API Key 기반 인증
]);
//외부 API - 직원 조회
$router->get('/api/external/employees', 'ExternalApiController@employees', [
    'key'             => 'api.external.employee.list',
    'name'            => '외부 API 직원 조회',
    'description'     => '외부 시스템(Excel 등)에서 직원 목록 조회',
    'category'        => '외부API',
    'skip_permission' => true,                 // ⭐ 내부 권한 체크 제외
    'middleware'      => ['ApiAccessMiddleware']
]);


// ------------------------------------------------------------
// 설정/시스템설정/외부 서비스 연동 부분분
// ------------------------------------------------------------

//외부 서비스 연동 (설정조회)
$router->get('/api/settings/system/external-services/get', 'SystemController@apiExternalServicesGet', [
    'key'         => 'api.settings.system.external.view',
    'name'        => '외부 서비스 연동 설정 조회',
    'description' => '외부 서비스 연동 시스템 설정 조회',
    'category'    => '시스템설정'
]);


//외부 서비스 연동 (설정저장장)
$router->post('/api/settings/system/external-services/save', 'SystemController@apiExternalServicesSave', [
    'key'         => 'api.settings.system.external.edit',
    'name'        => '외부 서비스 연동 설정 저장',
    'description' => '외부 서비스 연동 시스템 설정 저장',
    'category'    => '시스템설정'
]);

/* ============================================================
 * 외부 서비스 계정 (사용자 프로필 연동)
 * ============================================================ */

// ------------------------------------------------------------
// 내 외부 서비스 계정 전체 목록
// ------------------------------------------------------------
$router->get('/api/user/external-accounts', 'ExternalAccountController@apiList', [
    'key'         => 'api.user.external_accounts.view',
    'name'        => '외부 서비스 계정 조회',
    'description' => '로그인 사용자의 외부 서비스 계정 목록 조회',
    'category'    => '사용자'
]);

// ------------------------------------------------------------
// 특정 외부 서비스 계정 단일 조회
// ------------------------------------------------------------
$router->get('/api/user/external-accounts/get', 'ExternalAccountController@apiGet', [
    'key'         => 'api.user.external_accounts.view',
    'name'        => '외부 서비스 계정 단일 조회',
    'description' => '특정 외부 서비스 계정 정보 조회',
    'category'    => '사용자'
]);

// ------------------------------------------------------------
// 외부 서비스 계정 저장 (생성/수정)
// ------------------------------------------------------------
$router->post('/api/user/external-accounts/save', 'ExternalAccountController@apiSave', [
    'key'         => 'api.user.external_accounts.edit',
    'name'        => '외부 서비스 계정 저장',
    'description' => '외부 서비스 계정 추가 및 수정',
    'category'    => '사용자'
]);

// ------------------------------------------------------------
// 외부 서비스 계정 삭제
// ------------------------------------------------------------
$router->post('/api/user/external-accounts/delete', 'ExternalAccountController@apiDelete', [
    'key'         => 'api.user.external_accounts.delete',
    'name'        => '외부 서비스 계정 삭제',
    'description' => '외부 서비스 계정 삭제',
    'category'    => '사용자'
]);









/* =========================================================
 * 파일 - 보안관련
 * ========================================================= */
//보안 - 파일 미리보기
$router->get('/api/file/preview', 'FileController@apiPreview', [
    'key'         => 'api.file.preview',
    'name'        => '파일 미리보기',
    'description' => 'private://id_doc 및 private://certificate 문서 미리보기 전용',
    'category'    => '보안'
]);

//파일 - 업로드 테스트
$router->post('/api/file/upload-test', 'FileController@apiUploadTest', [
    'key'         => 'api.file.upload.test',
    'name'        => '파일 업로드 테스트',
    'description' => '파일 업로드 정책 또는 bucket 직접 지정 테스트',
    'category'    => '파일'
]);

//시스템 - 파일 업로드 정책 목록 조회
$router->get('/api/system/file-policies', 'FileController@apiPolicyList', [
    'key'         => 'api.settings.system.storage.policy.view',
    'name'        => '파일 업로드 정책 목록 조회',
    'description' => '시스템에 등록된 파일 업로드 정책 목록 조회',
    'category'    => '시스템설정'
]);

//시스템 - 파일 업로드 정책 생성
$router->post('/api/system/file-policies', 'FileController@apiPolicyCreate', [
    'key'         => 'api.settings.system.storage.policy.create',
    'name'        => '파일 업로드 정책 생성',
    'description' => '새 파일 업로드 정책 생성',
    'category'    => '시스템설정'
]);
// 시스템 - 파일 업로드 정책 수정
$router->post('/api/system/file-policies/update', 'FileController@apiPolicyUpdate', [
    'key'         => 'api.settings.system.storage.policy.edit',
    'name'        => '파일 업로드 정책 수정',
    'description' => '파일 업로드 정책 수정',
    'category'    => '시스템설정'
]);
//시스템 - 파일 업로드 정책 삭제
$router->post('/api/system/file-policies/delete', 'FileController@apiPolicyDelete', [
    'key'         => 'api.settings.system.storage.policy.delete',
    'name'        => '파일 업로드 정책 삭제',
    'description' => '파일 업로드 정책 삭제',
    'category'    => '시스템설정'
]);
// 시스템 - 파일 업로드 정책 활성/비활성
$router->post('/api/system/file-policies/toggle', 'FileController@apiPolicyToggle', [
    'key'         => 'api.settings.system.storage.policy.toggle',
    'name'        => '파일 업로드 정책 활성/비활성',
    'description' => '파일 업로드 정책 활성 상태 변경',
    'category'    => '시스템설정'
]);
// 시스템 - 버킷 폴더 조회
$router->get('/api/system/storage/bucket-browse', 'FileController@apiBucketBrowse', [
    'key'         => 'api.settings.system.storage.browse',
    'name'        => '버킷 폴더 조회',
    'description' => '스토리지 버킷 내 파일 목록 조회',
    'category'    => '시스템설정'
]);




// 🔹 DB 백업 설정 조회
$router->get('/api/settings/system/database/get', 'SystemController@apiDatabaseGet', [
    'key'         => 'api.settings.system.database.view',
    'name'        => 'DB 백업 설정 조회',
    'description' => '데이터베이스 백업 설정 정보 조회',
    'category'    => '시스템설정'
]);
// 🔹 DB 백업 설정 저장
$router->post('/api/settings/system/database/save', 'SystemController@apiDatabaseSave', [
    'key'         => 'api.settings.system.database.edit',
    'name'        => 'DB 백업 설정 저장',
    'description' => '데이터베이스 백업 설정 저장',
    'category'    => '시스템설정'
]);
// 🔹 DB 백업 즉시 실행
$router->post('/api/settings/system/database/run', 'SystemController@apiBackupRun', [
    'key'         => 'api.settings.system.database.run',
    'name'        => 'DB 백업 실행',
    'description' => '데이터베이스 백업 기능 실행',
    'category'    => '시스템설정'
]);

// 데이터베이스백업: 상태정보(경로/최신백업)
$router->get('/api/settings/system/database/info', 'SystemController@apiBackupInfo', [
    'key' => 'api.settings.system.database.view',
    'name' => 'DB 백업 상태 조회',
    'description' => '백업 저장 경로 및 최신 백업 파일 정보 조회',
    'category'    => '시스템설정'
]);

// 데이터베이스백업: 로그 조회
$router->get('/api/settings/system/database/log', 'SystemController@apiBackupLog', [
    'key' => 'api.settings.system.database.view',
    'name' => 'DB 백업 로그 조회',
    'description' => '백업 로그 텍스트 조회',
    'category'    => '시스템설정'
]);

// 데이터베이스 이중화 상태 조회
$router->get('/api/settings/system/database/replication-status', 'SystemController@apiDatabaseReplicationStatus', [
    'key'         => 'api.settings.system.database.view',
    'name'        => 'DB 이중화 상태 조회',
    'description' => 'Primary / Secondary 데이터베이스 이중화(Replication) 상태 및 지연 시간 조회',
    'category'    => '시스템설정'
]);

// 🔹 Secondary DB 복원 실행 (수동 / 자동 공용)
$router->post(
    '/api/settings/system/database/restore-secondary',
    'SystemController@apiRestoreSecondary',
    [
        'key'         => 'api.settings.system.database.restore',
        'name'        => 'Secondary DB 복원 실행',
        'description' => '최신 백업 파일을 Secondary DB에 복원',
        'category'    => '시스템설정'
    ]
);

// 🔹 Secondary DB 최신 복원 상태 조회
$router->get(
    '/api/settings/system/database/secondary-restore-info',
    'SystemController@apiSecondaryRestoreInfo',
    [
        'key'         => 'api.settings.system.database.view',
        'name'        => 'Secondary DB 최신 복원 상태 조회',
        'description' => 'Secondary DB에 마지막으로 복원된 백업 정보 조회',
        'category'    => '시스템설정'
    ]
);

// 🔹 시스템 로그 내용 조회
$router->post('/api/settings/system/logs/view', 'SystemController@apiLogView', [
    'key'         => 'api.settings.system.logs.view',
    'name'        => '시스템 로그 내용 조회',
    'description' => '선택한 로그 파일의 내용을 조회 (대용량 로그는 일부만 반환)',
    'category'    => '시스템설정'
]);

// 🔹 시스템 로그 파일 삭제
$router->post('/api/settings/system/logs/delete', 'SystemController@apiLogDelete', [
    'key'         => 'api.settings.system.logs.delete',
    'name'        => '시스템 로그 파일 삭제',
    'description' => '선택한 로그 파일 1개를 삭제',
    'category'    => '시스템설정'
]);

// 🔹 시스템 로그 전체 삭제
$router->post('/api/settings/system/logs/delete-all', 'SystemController@apiLogDeleteAll', [
    'key'         => 'api.settings.system.logs.delete_all',
    'name'        => '시스템 로그 전체 삭제',
    'description' => '시스템 로그 디렉터리 내 모든 로그 파일 삭제',
    'category'    => '시스템설정'
]);










/* =========================================
 * 결재 요청 (Approval)
 * ========================================= */
$router->post('/approval/request/create', 'ApprovalRequestController@apiCreate', [
    'key'         => 'api.approval.request.create',
    'name'        => '결재 요청 생성',
    'description' => '결재 요청을 생성하고 템플릿 기반 스텝을 자동 생성',
    'category'    => '결재요청'
]);

$router->get('/approval/request/detail', 'ApprovalRequestController@apiDetail', [
    'key'         => 'api.approval.request.detail',
    'name'        => '결재 요청 상세 조회',
    'description' => '요청 정보와 결재 스텝 전체 조회',
    'category'    => '결재요청'
]);

$router->post('/approval/request/approve', 'ApprovalRequestController@apiApproveStep', [
    'key'         => 'api.approval.step.approve',
    'name'        => '결재 스텝 승인',
    'description' => '요청된 결재 스텝을 승인 처리',
    'category'    => '결재스텝'
]);

$router->post('/approval/request/reject', 'ApprovalRequestController@apiRejectStep', [
    'key'         => 'api.approval.step.reject',
    'name'        => '결재 스텝 반려',
    'description' => '요청된 결재 스텝을 반려 처리',
    'category'    => '결재스텝'
]);

$router->get('/approval/request/status', 'ApprovalRequestController@apiStatus', [
    'key'         => 'api.approval.request.status',
    'name'        => '결재 요청 상태 조회',
    'description' => '요청의 현재 상태(진행/완료/반려) 및 현재 스텝 조회',
    'category'    => '결재요청'
]);

$router->post('/approval/request/step/delete', 'ApprovalRequestController@apiDeleteStep', [
    'key'         => 'api.approval.step.delete',
    'name'        => '결재 스텝 삭제',
    'description' => '특정 결재 스텝 삭제(관리자/특수 시나리오용)',
    'category'    => '결재스텝'
]);








$router->get('/api/dashboard/calendar/list',        'CalendarController@apiList');
$router->get('/api/dashboard/calendar/events-all',   'CalendarController@apiEventsAll');
$router->get('/api/dashboard/calendar/events',      'CalendarController@apiEvents');
$router->post('/api/dashboard/calendar/event/create', 'CalendarController@apiEventCreate');
$router->post('/api/dashboard/calendar/event/update', 'CalendarController@apiEventUpdate');
$router->post('/api/dashboard/calendar/event/delete', 'CalendarController@apiEventDelete');

$router->get('/api/dashboard/calendar/tasks',       'CalendarController@apiTasks');
$router->post('/api/dashboard/calendar/task/create', 'CalendarController@apiTaskCreate');
$router->post('/api/dashboard/calendar/task/toggle-complete', 'CalendarController@apiToggleTaskComplete');
$router->post('/api/dashboard/calendar/task/update', 'CalendarController@apiTaskUpdate');
$router->post('/api/dashboard/calendar/task/delete', 'CalendarController@apiTaskDelete');

$router->get('/api/dashboard/calendar/tasks-all',     'CalendarController@apiTasksAll');

// 캘린더 / 작업목록 컬렉션 삭제
$router->post('/api/dashboard/calendar/collection/delete', 'CalendarController@apiCollectionDelete');


// 캘린더 / 이벤트삭제(하드)
$router->post('/api/dashboard/calendar/event/hard-delete', 'CalendarController@apiEventHardDelete');

//DB 전체 재빌드라 GET 금지
$router->post(
    '/api/dashboard/calendar/cache-rebuild',
    'CalendarController@apiCacheRebuild'
);

// 캘린더 / 태스크삭제(하드)
$router->post('/api/dashboard/calendar/task/hard-delete', 'CalendarController@apiTaskHardDelete');

// 어드민캘린더 컬러 수정
$router->post('/api/dashboard/calendar/update-admin-color', 'CalendarController@apiUpdateAdminColor');

//태스크패널
$router->get('/api/dashboard/calendar/tasks-panel', 'CalendarController@apiTasksPanel');


//소프트 삭제된 이벤트조회
$router->get('/api/dashboard/calendar/events-deleted',    'CalendarController@apiEventsDeleted');

//소프트 삭제된 태스크조회
$router->get('/api/dashboard/calendar/tasks-deleted', 'CalendarController@apiTasksDeleted');

//소프트 삭제된 이벤트 전체삭제
$router->post('/api/dashboard/calendar/event/hard-delete-all',    'CalendarController@apiEventHardDeleteAll');

//소프트 삭제된 태스크 전체삭제
$router->post('/api/dashboard/calendar/task/hard-delete-all',    'CalendarController@apiTaskHardDeleteAll');

//소프트 삭제된 이벤트복원
$router->post('/api/dashboard/calendar/event/restore',    'CalendarController@apiEventRestore');

//소프트 삭제된 태스크복원원
$router->post('/api/dashboard/calendar/task/restore',    'CalendarController@apiTaskRestore');

//태스크 벌크 소프트삭제(완료된 작업 전체삭제)
$router->post('/api/dashboard/calendar/task/delete-bulk',   'CalendarController@apiTaskDeleteBulk');

//시놀로지달력 프로필써머리
$router->get('/api/dashboard/profile-summary', 'CalendarController@apiProfileSummary');

















/* =========================================================
계정과목 관리
========================================================= */
$router->get('/api/ledger/account/list', 'ChartAccountController@apiList');
$router->get('/api/ledger/account/tree', 'ChartAccountController@apiTree');
$router->get('/api/ledger/account/detail', 'ChartAccountController@apiDetail');
$router->get('/api/ledger/account/template', 'ChartAccountController@apiTemplate');
$router->get('/api/ledger/account/trash', 'ChartAccountController@apiTrashList');

$router->post('/api/ledger/account/save', 'ChartAccountController@apiSave');
$router->post('/api/ledger/account/soft-delete', 'ChartAccountController@apiSoftDelete');
$router->post('/api/ledger/account/restore', 'ChartAccountController@apiRestore');
$router->post('/api/ledger/account/hard-delete', 'ChartAccountController@apiHardDelete');
$router->post('/api/ledger/account/excel-upload', 'ChartAccountController@apiExcelUpload');
$router->post('/api/ledger/account/reorder', 'ChartAccountController@apiReorder');

$router->post('/api/ledger/account/restore-bulk', 'ChartAccountController@apiRestoreBulk');
$router->post('/api/ledger/account/hard-delete-bulk', 'ChartAccountController@apiHardDeleteBulk');
$router->post('/api/ledger/account/hard-delete-all', 'ChartAccountController@apiHardDeleteAll');

$router->get('/api/ledger/accounts/excel', 'ChartAccountController@apidownloadAllExcel');

/* =========================================================
전표 입력용 계정 조회
========================================================= */
$router->get('/api/ledger/account/search', 'ChartAccountController@apiSearch');
$router->get('/api/ledger/account/posting', 'ChartAccountController@apiPosting');


/* =========================================================
보조계정 관리
========================================================= */
$router->get('/api/ledger/sub-account/list', 'SubChartAccountController@apiList');
$router->post('/api/ledger/sub-account/save', 'SubChartAccountController@apiSave');
$router->post('/api/ledger/sub-account/update', 'SubChartAccountController@apiUpdate');
$router->post('/api/ledger/sub-account/delete', 'SubChartAccountController@apiDelete');






















