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
$router->get('/api/settings/base-info/company/detail', 'CompanySettingsController@apiDetail', [
    'key'         => 'api.settings.base-info.company.view',
    'name'        => '회사 기본정보 조회',
    'description' => '시스템에 단 1건 존재하는 회사 기본정보 조회',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 회사 기본정보 저장 (신규 / 수정)
 * ========================================================= */
$router->post('/api/settings/base-info/company/save', 'CompanySettingsController@apiSave', [
    'key'         => 'api.settings.base-info.company.save',
    'name'        => '회사 기본정보 저장',
    'description' => '시스템에 단 1건 존재하는 회사 기본정보 신규/수정',
    'category'    => '시스템설정'
]);





/* =========================================================
 * 브랜드 자산 조회 (단건 / 활성)
 * ========================================================= */
$router->post('/api/settings/base-info/brand/get', 'BrandSettingsController@apiSearch', [
    'key'         => 'api.settings.base-info.brand.view',
    'name'        => '브랜드 자산 조회',
    'description' => '메인로고 / 파비콘 활성 자산 조회',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 브랜드 자산 목록 조회
 * ========================================================= */
$router->post('/api/settings/base-info/brand/list', 'BrandSettingsController@apiList', [
    'key'         => 'api.settings.base-info.brand.list',
    'name'        => '브랜드 자산 목록 조회',
    'description' => '브랜드 자산 전체 목록 조회',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 브랜드 자산 저장 (업로드)
 * ========================================================= */
$router->post('/api/settings/base-info/brand/upload', 'BrandSettingsController@apiSave', [
    'key'         => 'api.settings.base-info.brand.save',
    'name'        => '브랜드 자산 저장',
    'description' => '브랜드 로고 및 파비콘 업로드/교체',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 브랜드 자산 삭제
 * ========================================================= */
$router->post('/api/settings/base-info/brand/delete', 'BrandSettingsController@apiDelete', [
    'key'         => 'api.settings.base-info.brand.delete',
    'name'        => '브랜드 자산 삭제',
    'description' => '브랜드 로고 및 파비콘 삭제',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 브랜드 자산 활성화
 * ========================================================= */
$router->post('/api/settings/base-info/brand/activate', 'BrandSettingsController@apiActivate', [
    'key'         => 'api.settings.base-info.brand.activate',
    'name'        => '브랜드 자산 활성화',
    'description' => '브랜드 로고 및 파비콘 활성화',
    'category'    => '시스템설정'
]);



/* =========================================================
 * 커버 이미지 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/cover/list', 'CoverSettingsController@apiList', [
    'key'         => 'api.settings.base-info.cover.list',
    'name'        => '커버 이미지 목록 조회',
    'description' => '커버 이미지 전체 목록을 조회합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 저장 (신규 / 수정)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/save', 'CoverSettingsController@apiSave', [
    'key'         => 'api.settings.base-info.cover.save',
    'name'        => '커버 이미지 저장',
    'description' => '커버 이미지를 신규 등록하거나 수정합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 삭제 (소프트삭제)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/delete', 'CoverSettingsController@apiDelete', [
    'key'         => 'api.settings.base-info.cover.delete',
    'name'        => '커버 이미지 삭제',
    'description' => '커버 이미지를 소프트삭제 처리합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 휴지통 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/cover/trash', 'CoverSettingsController@apiTrashList', [
    'key'         => 'api.settings.base-info.cover.trash.list',
    'name'        => '커버 이미지 휴지통 목록 조회',
    'description' => '삭제된 커버 이미지 목록을 조회합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 복원 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/restore', 'CoverSettingsController@apiRestore', [
    'key'         => 'api.settings.base-info.cover.restore',
    'name'        => '커버 이미지 복원',
    'description' => '삭제된 커버 이미지를 복원합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 완전삭제 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/purge', 'CoverSettingsController@apiPurge', [
    'key'         => 'api.settings.base-info.cover.purge',
    'name'        => '커버 이미지 완전삭제',
    'description' => '삭제된 커버 이미지를 DB와 스토리지에서 완전히 삭제합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 순서 변경
 * ========================================================= */
$router->post('/api/settings/base-info/cover/reorder', 'CoverSettingsController@apiReorder', [
    'key'         => 'api.settings.base-info.cover.reorder',
    'name'        => '커버 이미지 순서 변경',
    'description' => '커버 이미지 정렬 순서를 변경합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 복원 (다건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/restore-bulk', 'CoverSettingsController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.cover.restore-bulk',
    'name'        => '커버 이미지 일괄 복원',
    'description' => '삭제된 커버 이미지를 여러 건 복원합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 완전삭제 (다건)
 * ========================================================= */
$router->post('/api/settings/base-info/cover/purge-bulk', 'CoverSettingsController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.cover.purge-bulk',
    'name'        => '커버 이미지 일괄 완전삭제',
    'description' => '삭제된 커버 이미지를 여러 건 완전히 삭제합니다.',
    'category'    => '시스템설정'
]);

/* =========================================================
 * 커버 이미지 전체 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/cover/purge-all', 'CoverSettingsController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.cover.purge-all',
    'name'        => '커버 이미지 전체 완전삭제',
    'description' => '휴지통에 있는 커버 이미지를 전체 완전히 삭제합니다.',
    'category'    => '시스템설정'
]);








/* =========================================================
 * 거래처 검색 (검색폼용)
 * ========================================================= */
$router->get('/api/settings/base-info/client/search', 'ClientSettingsController@apiSearch', [
    'key'         => 'api.settings.base-info.client.search',
    'name'        => '거래처 검색',
    'description' => '검색 조건 기반 거래처 목록 조회',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/client/list', 'ClientSettingsController@apiList', [
    'key'         => 'api.settings.base-info.client.list',
    'name'        => '거래처 목록 조회',
    'description' => '전체 거래처 목록을 조회합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 상세 조회
 * ========================================================= */
$router->get('/api/settings/base-info/client/detail', 'ClientSettingsController@apiDetail', [
    'key'         => 'api.settings.base-info.client.detail',
    'name'        => '거래처 상세 조회',
    'description' => '특정 거래처 상세 정보를 조회합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 저장 (신규 / 수정)
 * ========================================================= */
$router->post('/api/settings/base-info/client/save', 'ClientSettingsController@apiSave', [
    'key'         => 'api.settings.base-info.client.save',
    'name'        => '거래처 저장',
    'description' => '거래처를 신규 등록하거나 수정합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 삭제 (소프트삭제)
 * ========================================================= */
$router->post('/api/settings/base-info/client/delete', 'ClientSettingsController@apiDelete', [
    'key'         => 'api.settings.base-info.client.delete',
    'name'        => '거래처 삭제',
    'description' => '거래처를 삭제 처리합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 순서 변경
 * ========================================================= */
$router->post('/api/settings/base-info/client/reorder', 'ClientSettingsController@apiReorder', [
    'key'         => 'api.settings.base-info.client.reorder',
    'name'        => '거래처 순서 변경',
    'description' => '거래처 정렬 순서를 변경합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 엑셀 다운로드
 * ========================================================= */
$router->get('/api/settings/base-info/clients/excel', 'ClientSettingsController@apiDownload', [
    'key'         => 'api.settings.base-info.client.excel',
    'name'        => '거래처 엑셀 다운로드',
    'description' => '전체 거래처 데이터를 엑셀로 다운로드합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 엑셀 양식 다운로드
 * ========================================================= */
$router->get('/api/settings/base-info/clients/template', 'ClientSettingsController@apiTemplate', [
    'key'         => 'api.settings.base-info.client.template',
    'name'        => '거래처 엑셀 양식 다운로드',
    'description' => '거래처 등록용 엑셀 양식을 다운로드합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 엑셀 업로드
 * ========================================================= */
$router->post('/api/settings/base-info/client/excel-upload', 'ClientSettingsController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.client.excel-upload',
    'name'        => '거래처 엑셀 업로드',
    'description' => '엑셀 파일을 통해 거래처 데이터를 등록합니다.',
    'category'    => '거래원장'
]);



/* =========================================================
 * 거래처 휴지통 목록 조회
 * ========================================================= */
$router->get('/api/settings/base-info/client/trash', 'ClientSettingsController@apiTrashList', [
    'key'         => 'api.settings.base-info.client.trash.list',
    'name'        => '거래처 휴지통 목록',
    'description' => '삭제된 거래처 목록을 조회합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 복원 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/client/restore', 'ClientSettingsController@apiRestore', [
    'key'         => 'api.settings.base-info.client.restore',
    'name'        => '거래처 복원',
    'description' => '삭제된 거래처를 복원합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 완전삭제 (단건)
 * ========================================================= */
$router->post('/api/settings/base-info/client/purge', 'ClientSettingsController@apiPurge', [
    'key'         => 'api.settings.base-info.client.purge',
    'name'        => '거래처 완전삭제',
    'description' => '거래처를 DB 및 파일까지 완전히 삭제합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 선택 복원
 * ========================================================= */
$router->post('/api/settings/base-info/client/restore-bulk', 'ClientSettingsController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.client.restore-bulk',
    'name'        => '거래처 일괄 복원',
    'description' => '여러 거래처를 동시에 복원합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 선택 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/client/purge-bulk', 'ClientSettingsController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.client.purge-bulk',
    'name'        => '거래처 일괄 완전삭제',
    'description' => '여러 거래처를 동시에 완전 삭제합니다.',
    'category'    => '거래원장'
]);

/* =========================================================
 * 거래처 전체 완전삭제
 * ========================================================= */
$router->post('/api/settings/base-info/client/purge-all', 'ClientSettingsController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.client.purge-all',
    'name'        => '거래처 전체 완전삭제',
    'description' => '휴지통의 모든 거래처를 완전히 삭제합니다.',
    'category'    => '거래원장'
]);











/* =========================================
 * 프로젝트 API
 * ========================================= */

// 프로젝트 검색
$router->get('/api/settings/base-info/project/search', 'ProjectSettingsController@apiSearch', [
    'key'         => 'api.settings.base-info.project.search',
    'name'        => '프로젝트 검색',
    'description' => '프로젝트 검색용 목록 조회',
    'category'    => '프로젝트원장'
]);

// 프로젝트 목록 조회
$router->get('/api/settings/base-info/project/list', 'ProjectSettingsController@apiList', [
    'key'         => 'api.settings.base-info.project.list',
    'name'        => '프로젝트 목록 조회',
    'description' => '프로젝트 전체 목록 조회',
    'category'    => '프로젝트원장'
]);

// 프로젝트 저장
$router->post('/api/settings/base-info/project/save', 'ProjectSettingsController@apiSave', [
    'key'         => 'api.settings.base-info.project.save',
    'name'        => '프로젝트 저장',
    'description' => '프로젝트 신규 등록 및 수정',
    'category'    => '프로젝트원장'
]);

// 프로젝트 삭제
$router->post('/api/settings/base-info/project/delete', 'ProjectSettingsController@apiDelete', [
    'key'         => 'api.settings.base-info.project.delete',
    'name'        => '프로젝트 삭제',
    'description' => '프로젝트 삭제 처리',
    'category'    => '프로젝트원장'
]);

// 프로젝트 상세
$router->get('/api/settings/base-info/project/detail', 'ProjectSettingsController@apiDetail', [
    'key'         => 'api.settings.base-info.project.detail',
    'name'        => '프로젝트 상세 조회',
    'description' => '프로젝트 상세 정보 조회',
    'category'    => '프로젝트원장'
]);

// 순서변경
$router->post('/api/settings/base-info/project/reorder', 'ProjectSettingsController@apiReorder', [
    'key'         => 'api.settings.base-info.project.reorder',
    'name'        => '프로젝트 순서 변경',
    'description' => '프로젝트 정렬 순서 변경',
    'category'    => '프로젝트원장'
]);

// 엑셀 다운로드
$router->get('/api/settings/base-info/project/excel', 'ProjectSettingsController@apiDownloadAllExcel', [
    'key'         => 'api.settings.base-info.project.excel',
    'name'        => '프로젝트 엑셀 다운로드',
    'description' => '프로젝트 전체 엑셀 다운로드',
    'category'    => '프로젝트원장'
]);

// 엑셀 템플릿
$router->get('/api/settings/base-info/project/template', 'ProjectSettingsController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.project.template',
    'name'        => '프로젝트 엑셀 템플릿',
    'description' => '프로젝트 업로드용 템플릿 다운로드',
    'category'    => '프로젝트원장'
]);

// 엑셀 업로드
$router->post('/api/settings/base-info/project/excel-upload', 'ProjectSettingsController@apiExcelUpload', [
    'key'         => 'api.settings.base-info.project.excel-upload',
    'name'        => '프로젝트 엑셀 업로드',
    'description' => '프로젝트 엑셀 데이터 업로드',
    'category'    => '프로젝트원장'
]);



/* =========================================================
   프로젝트 휴지통
========================================================= */

// 휴지통 목록
$router->get('/api/settings/base-info/project/trash', 'ProjectSettingsController@apiTrashList', [
    'key'         => 'api.settings.base-info.project.trash',
    'name'        => '프로젝트 휴지통 목록',
    'description' => '삭제된 프로젝트 목록 조회',
    'category'    => '프로젝트원장'
]);

// 복원
$router->post('/api/settings/base-info/project/restore', 'ProjectSettingsController@apiRestore', [
    'key'         => 'api.settings.base-info.project.restore',
    'name'        => '프로젝트 복원',
    'description' => '삭제된 프로젝트 복원',
    'category'    => '프로젝트원장'
]);

// 완전삭제
$router->post('/api/settings/base-info/project/purge', 'ProjectSettingsController@apiPurge', [
    'key'         => 'api.settings.base-info.project.purge',
    'name'        => '프로젝트 영구 삭제',
    'description' => '프로젝트 완전 삭제',
    'category'    => '프로젝트원장'
]);

// 선택 복원
$router->post('/api/settings/base-info/project/restore-bulk', 'ProjectSettingsController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.project.restore-bulk',
    'name'        => '프로젝트 선택 복원',
    'description' => '선택한 프로젝트 복원',
    'category'    => '프로젝트원장'
]);

// 선택 삭제
$router->post('/api/settings/base-info/project/purge-bulk', 'ProjectSettingsController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.project.purge-bulk',
    'name'        => '프로젝트 선택 삭제',
    'description' => '선택한 프로젝트 영구 삭제',
    'category'    => '프로젝트원장'
]);

// 전체 삭제
$router->post('/api/settings/base-info/project/purge-all', 'ProjectSettingsController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.project.purge-all',
    'name'        => '프로젝트 전체 삭제',
    'description' => '모든 프로젝트 영구 삭제',
    'category'    => '프로젝트원장'
]);

















/* =========================================
 * 직원 / 부서 / 직책
 * ========================================= */

/* =========================
   직원
========================= */

// 직원 검색 (GET 유지)
$router->get('/api/settings/employee/search', 'EmployeeSettingsController@apiSearch');

// 직원 목록 (🔥 GET + POST 둘 다 허용)
$router->get('/api/settings/employee/list', 'EmployeeSettingsController@apiList');
$router->post('/api/settings/employee/list', 'EmployeeSettingsController@apiList');

// 직원 저장
$router->post('/api/settings/employee/save', 'EmployeeSettingsController@apiSave');

// 직원 순서 변경
$router->post('/api/settings/employee/reorder', 'EmployeeSettingsController@apiReorder');





/* =========================
   부서
========================= */

// 부서 목록 (GET + POST 허용)
$router->get('/api/settings/department/list', 'DepartmentSettingsController@apiList');
$router->post('/api/settings/department/list', 'DepartmentSettingsController@apiList');

// 부서 저장
$router->post('/api/settings/department/save', 'DepartmentSettingsController@apiSave');





/* =========================
   직책
========================= */

// 직책 목록 (GET + POST 허용)
$router->get('/api/settings/position/list', 'PositionSettingsController@apiList');
$router->post('/api/settings/position/list', 'PositionSettingsController@apiList');

// 직책 저장
$router->post('/api/settings/position/save', 'PositionSettingsController@apiSave');






/* =========================================
 * 역할 / 권한
 * ========================================= */
$router->post('/api/settings/role/list', 'RoleSettingsController@apiList', [
    'key' => 'api.settings.role.list',
    'name' => '역할 목록 조회',
    'description' => '역할 리스트 조회',
    'category' => '권한관리'
]);

$router->post('/api/settings/role/save', 'RoleSettingsController@apiSave', [
    'key' => 'api.settings.role.save',
    'name' => '역할 저장',
    'description' => '역할 정보 저장',
    'category' => '권한관리'
]);

$router->post('/api/settings/permission/list', 'PermissionSettingsController@apiList', [
    'key' => 'api.settings.permission.list',
    'name' => '권한 목록 조회',
    'description' => '전체 권한 조회',
    'category' => '권한관리'
]);

$router->post('/api/settings/permission/save', 'PermissionSettingsController@apiSave', [
    'key' => 'api.settings.permission.save',
    'name' => '권한 저장',
    'description' => '권한 정보 저장',
    'category' => '권한관리'
]);

$router->post('/api/settings/role-permission/list', 'RolePermissionSettingsController@apiList', [
    'key' => 'api.settings.rolepermission.list',
    'name' => '역할별 권한 조회',
    'description' => '역할에 부여된 권한 목록 조회',
    'category' => '권한관리'
]);

$router->post('/api/settings/role-permission/assign', 'RolePermissionSettingsController@apiAssign', [
    'key' => 'api.settings.rolepermission.assign',
    'name' => '권한 부여',
    'description' => '역할에 권한 부여',
    'category' => '권한관리'
]);

$router->post('/api/settings/role-permission/remove', 'RolePermissionSettingsController@apiRemove', [
    'key' => 'api.settings.rolepermission.remove',
    'name' => '권한 제거',
    'description' => '역할에서 권한 제거',
    'category' => '권한관리'
]);


/* =========================================
 * 결재(Approval)
 * ========================================= */
$router->post('/api/settings/approval/template/list', 'ApprovalSettingsController@apiTemplateList', [
    'key' => 'api.settings.approval.template.list',
    'name' => '결재 템플릿 목록',
    'description' => '결재 템플릿 리스트 조회',
    'category' => '결재관리'
]);

$router->post('/api/settings/approval/template/save', 'ApprovalSettingsController@apiTemplateSave', [
    'key' => 'api.settings.approval.template.save',
    'name' => '결재 템플릿 저장',
    'description' => '템플릿 저장',
    'category' => '결재관리'
]);

$router->post('/api/settings/approval/template/delete', 'ApprovalSettingsController@apiTemplateDelete', [
    'key' => 'api.settings.approval.template.delete',
    'name' => '결재 템플릿 삭제',
    'description' => '템플릿 삭제',
    'category' => '결재관리'
]);

$router->post('/api/settings/approval/step/list', 'ApprovalSettingsController@apiStepList', [
    'key' => 'api.settings.approval.step.list',
    'name' => '결재 단계 목록',
    'description' => '결재 단계 리스트 조회',
    'category' => '결재관리'
]);

$router->post('/api/settings/approval/step/save', 'ApprovalSettingsController@apiStepSave', [
    'key' => 'api.settings.approval.step.save',
    'name' => '결재 단계 저장',
    'description' => '결재 단계 저장',
    'category' => '결재관리'
]);

$router->post('/api/settings/approval/step/delete', 'ApprovalSettingsController@apiStepDelete', [
    'key' => 'api.settings.approval.step.delete',
    'name' => '결재 단계 삭제',
    'description' => '결재 단계 삭제',
    'category' => '결재관리'
]);


/* =========================================
 * 시스템 설정
 * ========================================= */
//사이트정보
$router->get('/api/settings/system/site/get', 'SystemSettingsController@apiSiteGet', [
    'key'         => 'api.settings.system.site.view',
    'name'        => '사이트 설정 조회',
    'description' => '사이트 기본설정(SITE) 카테고리 설정값 조회',
    'category'    => '시스템설정'
]);

$router->post('/api/settings/system/site/save', 'SystemSettingsController@apiSiteSave', [
    'key'         => 'api.settings.system.site.edit',
    'name'        => '사이트 설정 저장',
    'description' => '사이트 기본설정(SITE) 카테고리 설정값 저장',
    'category'    => '시스템설정'
]);
//세션
$router->get('/api/settings/system/session/get', 'SystemSettingsController@apiSessionGet', [
    'key'         => 'api.settings.system.session.view',
    'name'        => '세션 설정 조회',
    'description' => '세션 관리(SESSION) 카테고리 설정값 조회',
    'category'    => '시스템설정'
]);

$router->post('/api/settings/system/session/save', 'SystemSettingsController@apiSessionSave', [
    'key'         => 'api.settings.system.session.edit',
    'name'        => '세션 설정 저장',
    'description' => '세션 관리(SESSION) 카테고리 설정값 저장',
    'category'    => '시스템설정'
]);
//보안정책
$router->get('/api/settings/system/security/get', 'SystemSettingsController@apiSecurityGet', [
    'key'         => 'api.settings.system.security.view',
    'name'        => '보안 설정 조회',
    'description' => '보안 정책(SECURITY) 카테고리 설정값 조회',
    'category'    => '시스템설정'
]);

$router->post('/api/settings/system/security/save', 'SystemSettingsController@apiSecuritySave', [
    'key'         => 'api.settings.system.security.edit',
    'name'        => '보안 설정 저장',
    'description' => '보안 정책(SECURITY) 카테고리 설정값 저장',
    'category'    => '시스템설정'
]);


/* =========================================
 * 시스템 설정 - 외부 API
 * ========================================= */
// 외부 API 설정 조회
$router->get('/api/settings/system/api/get', 'SystemSettingsController@apiApiGet', [
    'key'         => 'api.settings.system.api.view',
    'name'        => '외부 API 설정 조회',
    'description' => '외부 연동(API) 설정 값을 조회',
    'category'    => '시스템설정'
]);

// 외부 API 설정 저장
$router->post('/api/settings/system/api/save', 'SystemSettingsController@apiApiSave', [
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
$router->get('/api/settings/system/external-services/get', 'SystemSettingsController@apiExternalServicesGet', [
    'key'         => 'api.settings.system.external.view',
    'name'        => '외부 서비스 연동 설정 조회',
    'description' => '외부 서비스 연동 시스템 설정 조회',
    'category'    => '시스템설정'
]);


//외부 서비스 연동 (설정저장장)
$router->post('/api/settings/system/external-services/save', 'SystemSettingsController@apiExternalServicesSave', [
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






/* =========================================
 * 사용자 프로필 API
 * ========================================= */
// 내 프로필 조회 (세션 기반, user_id 없음)
$router->get('/api/user/profile', 'ProfileController@apiProfileMe', [
    'key' => 'api.profile.me',
    'name' => '내 프로필 조회',
    'description' => '현재 로그인한 사용자의 프로필 조회 (세션 기반)',
    'category' => '프로필'
]);

// 프로필 조회
$router->get('/api/user/profile/get', 'ProfileController@apiGet', [
    'key' => 'api.profile.view',
    'name' => '프로필 조회',
    'description' => '사용자 프로필 단건 조회',
    'category' => '프로필'
]);

// 프로필 생성
$router->post('/api/user/profile/create', 'ProfileController@apiCreate', [
    'key' => 'api.profile.create',
    'name' => '프로필 생성',
    'description' => '사용자 프로필 생성',
    'category' => '프로필'
]);

// 프로필 이미지 수정
$router->post('/api/user/profile/update-image', 'ProfileController@apiUpdateImage', [
    'key' => 'api.profile.update_image',
    'name' => '프로필 이미지 수정',
    'description' => '사용자 프로필 이미지 업로드',
    'category' => '프로필'
]);

// 직원 이름 수정
$router->post('/api/user/profile/update-name', 'ProfileController@apiUpdateName', [
    'key' => 'api.profile.update_name',
    'name' => '직원 이름 수정',
    'description' => '사용자 이름 수정',
    'category' => '프로필'
]);

// 사용자 + 프로필 정보
$router->get('/api/user/profile/user-info', 'ProfileController@apiGetUserInfo', [
    'key' => 'api.profile.userinfo',
    'name' => '사용자 정보 조회',
    'description' => '사용자 + 프로필 통합 정보 조회',
    'category' => '프로필'
]);

// 2FA
$router->post('/api/user/profile/update-2fa', 'ProfileController@apiUpdateTwoFactor', [
    'key' => 'api.profile.update_2fa',
    'name' => '2단계 인증 설정',
    'description' => '사용자의 2단계 인증 활성화/비활성화 설정',
    'category' => '보안'
]);

// 프로필수정
$router->post('/api/user/profile/update', 'ProfileController@apiUpdateProfile', [
    'key' => 'api.profile.update',
    'name' => '내 프로필 수정',
    'category' => '프로필'
]);

// 프로필 내 비밀번호변경
$router->post('/api/user/profile/change-password', 'ProfileController@apiChangePassword', [
    'key' => 'api.profile.changepassword',
    'name' => '내 비밀번호변경 수정',
    'category' => '프로필'
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
$router->get('/api/settings/system/database/get', 'SystemSettingsController@apiDatabaseGet', [
    'key'         => 'api.settings.system.database.view',
    'name'        => 'DB 백업 설정 조회',
    'description' => '데이터베이스 백업 설정 정보 조회',
    'category'    => '시스템설정'
]);
// 🔹 DB 백업 설정 저장
$router->post('/api/settings/system/database/save', 'SystemSettingsController@apiDatabaseSave', [
    'key'         => 'api.settings.system.database.edit',
    'name'        => 'DB 백업 설정 저장',
    'description' => '데이터베이스 백업 설정 저장',
    'category'    => '시스템설정'
]);
// 🔹 DB 백업 즉시 실행
$router->post('/api/settings/system/database/run', 'SystemSettingsController@apiBackupRun', [
    'key'         => 'api.settings.system.database.run',
    'name'        => 'DB 백업 실행',
    'description' => '데이터베이스 백업 기능 실행',
    'category'    => '시스템설정'
]);

// 데이터베이스백업: 상태정보(경로/최신백업)
$router->get('/api/settings/system/database/info', 'SystemSettingsController@apiBackupInfo', [
    'key' => 'api.settings.system.database.view',
    'name' => 'DB 백업 상태 조회',
    'description' => '백업 저장 경로 및 최신 백업 파일 정보 조회',
    'category'    => '시스템설정'
]);

// 데이터베이스백업: 로그 조회
$router->get('/api/settings/system/database/log', 'SystemSettingsController@apiBackupLog', [
    'key' => 'api.settings.system.database.view',
    'name' => 'DB 백업 로그 조회',
    'description' => '백업 로그 텍스트 조회',
    'category'    => '시스템설정'
]);

// 데이터베이스 이중화 상태 조회
$router->get('/api/settings/system/database/replication-status', 'SystemSettingsController@apiDatabaseReplicationStatus', [
    'key'         => 'api.settings.system.database.view',
    'name'        => 'DB 이중화 상태 조회',
    'description' => 'Primary / Secondary 데이터베이스 이중화(Replication) 상태 및 지연 시간 조회',
    'category'    => '시스템설정'
]);

// 🔹 Secondary DB 복원 실행 (수동 / 자동 공용)
$router->post(
    '/api/settings/system/database/restore-secondary', 'SystemSettingsController@apiRestoreSecondary',
    [
        'key'         => 'api.settings.system.database.restore',
        'name'        => 'Secondary DB 복원 실행',
        'description' => '최신 백업 파일을 Secondary DB에 복원',
        'category'    => '시스템설정'
    ]
);

// 🔹 Secondary DB 최신 복원 상태 조회
$router->get(
    '/api/settings/system/database/secondary-restore-info', 'SystemSettingsController@apiSecondaryRestoreInfo',
    [
        'key'         => 'api.settings.system.database.view',
        'name'        => 'Secondary DB 최신 복원 상태 조회',
        'description' => 'Secondary DB에 마지막으로 복원된 백업 정보 조회',
        'category'    => '시스템설정'
    ]
);

// 🔹 시스템 로그 내용 조회
$router->post('/api/settings/system/logs/view', 'SystemSettingsController@apiLogView', [
    'key'         => 'api.settings.system.logs.view',
    'name'        => '시스템 로그 내용 조회',
    'description' => '선택한 로그 파일의 내용을 조회 (대용량 로그는 일부만 반환)',
    'category'    => '시스템설정'
]);

// 🔹 시스템 로그 파일 삭제
$router->post('/api/settings/system/logs/delete', 'SystemSettingsController@apiLogDelete', [
    'key'         => 'api.settings.system.logs.delete',
    'name'        => '시스템 로그 파일 삭제',
    'description' => '선택한 로그 파일 1개를 삭제',
    'category'    => '시스템설정'
]);

// 🔹 시스템 로그 전체 삭제
$router->post('/api/settings/system/logs/delete-all', 'SystemSettingsController@apiLogDeleteAll', [
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








$router->get ('/api/dashboard/calendar/list',        'CalendarController@apiList');
$router->get('/api/dashboard/calendar/events-all',   'CalendarController@apiEventsAll');
$router->get ('/api/dashboard/calendar/events',      'CalendarController@apiEvents');
$router->post('/api/dashboard/calendar/event/create','CalendarController@apiEventCreate');
$router->post('/api/dashboard/calendar/event/update','CalendarController@apiEventUpdate');
$router->post('/api/dashboard/calendar/event/delete','CalendarController@apiEventDelete');

$router->get ('/api/dashboard/calendar/tasks',       'CalendarController@apiTasks');
$router->post('/api/dashboard/calendar/task/create', 'CalendarController@apiTaskCreate');
$router->post('/api/dashboard/calendar/task/toggle-complete', 'CalendarController@apiToggleTaskComplete');
$router->post('/api/dashboard/calendar/task/update', 'CalendarController@apiTaskUpdate');
$router->post('/api/dashboard/calendar/task/delete', 'CalendarController@apiTaskDelete');

$router->get('/api/dashboard/calendar/tasks-all',     'CalendarController@apiTasksAll' );
  
// 캘린더 / 작업목록 컬렉션 삭제
$router->post('/api/dashboard/calendar/collection/delete', 'CalendarController@apiCollectionDelete');


// 캘린더 / 이벤트삭제(하드)
$router->post('/api/dashboard/calendar/event/hard-delete', 'CalendarController@apiEventHardDelete');

//DB 전체 재빌드라 GET 금지
  $router->post('/api/dashboard/calendar/cache-rebuild','CalendarController@apiCacheRebuild'
);

// 캘린더 / 태스크삭제(하드)
$router->post('/api/dashboard/calendar/task/hard-delete','CalendarController@apiTaskHardDelete');

// 어드민캘린더 컬러 수정
$router->post('/api/dashboard/calendar/update-admin-color', 'CalendarController@apiUpdateAdminColor');

//태스크패널
$router->get('/api/dashboard/calendar/tasks-panel', 'CalendarController@apiTasksPanel');


//소프트 삭제된 이벤트조회
$router->get(    '/api/dashboard/calendar/events-deleted',    'CalendarController@apiEventsDeleted');

//소프트 삭제된 태스크조회
$router->get(    '/api/dashboard/calendar/tasks-deleted','CalendarController@apiTasksDeleted');

//소프트 삭제된 이벤트 전체삭제
$router->post(    '/api/dashboard/calendar/event/hard-delete-all',    'CalendarController@apiEventHardDeleteAll');

//소프트 삭제된 태스크 전체삭제
$router->post(    '/api/dashboard/calendar/task/hard-delete-all',    'CalendarController@apiTaskHardDeleteAll');

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
$router->get('/api/ledger/account/list', 'AccountController@apiList');
$router->get('/api/ledger/account/tree', 'AccountController@apiTree');
$router->get('/api/ledger/account/detail', 'AccountController@apiDetail');
$router->get('/api/ledger/account/template', 'AccountController@apiTemplate');
$router->get('/api/ledger/account/trash', 'AccountController@apiTrashList');

$router->post('/api/ledger/account/save', 'AccountController@apiSave');
$router->post('/api/ledger/account/soft-delete', 'AccountController@apiSoftDelete');
$router->post('/api/ledger/account/restore', 'AccountController@apiRestore');
$router->post('/api/ledger/account/hard-delete', 'AccountController@apiHardDelete');
$router->post('/api/ledger/account/excel-upload', 'AccountController@apiExcelUpload');
$router->post('/api/ledger/account/reorder', 'AccountController@apiReorder');

$router->post('/api/ledger/account/restore-bulk', 'AccountController@apiRestoreBulk');
$router->post('/api/ledger/account/hard-delete-bulk', 'AccountController@apiHardDeleteBulk');
$router->post('/api/ledger/account/hard-delete-all', 'AccountController@apiHardDeleteAll');

$router->get( '/api/ledger/accounts/excel', 'AccountController@apidownloadAllExcel');

/* =========================================================
보조계정 관리
========================================================= */
$router->get('/api/ledger/sub-account/list', 'SubAccountController@apiList');
$router->post('/api/ledger/sub-account/save', 'SubAccountController@apiSave');
$router->post('/api/ledger/sub-account/update', 'SubAccountController@apiUpdate');
$router->post('/api/ledger/sub-account/delete', 'SubAccountController@apiDelete');

/* =========================================================
전표 입력용 계정 조회
========================================================= */
$router->get('/api/ledger/account/search', 'AccountController@apiSearch');
$router->get('/api/ledger/account/posting', 'AccountController@apiPosting');

