<?php
// 경로: PROJECT_ROOT . '/routes/api.php';
error_log('[ROUTES] api.php LOADED');

global $router;

/* =========================================
 * 인증 / 계정잠금 관련 API (보안/접근 제어)
 * -----------------------------------------
 * 로그인, 회원가입, 2차 인증 등 인증 관련 API와
 * 계정 잠금/해제 등의 보안 기능을 처리
 * ========================================= */
$router->get('/api/account/lock/status', 'AccountLockController@apiStatus', [
    'key' => 'api.auth.account_lock.status',
    'name' => '계정 잠금 상태 조회',
    'description' => '계정 잠금 상태 조회',
    'category' => '인증',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);

$router->post('/api/account/lock/set', 'AccountLockController@apiLock', [
    'key' => 'api.auth.account_lock.lock',
    'name' => '계정 잠금 설정',
    'description' => '계정 잠금 설정',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/account/lock/unlock', 'AccountLockController@apiUnlock', [
    'key' => 'api.auth.account_lock.unlock',
    'name' => '계정 잠금 해제',
    'description' => '계정 잠금 해제',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->post('/api/auth/register', 'RegisterController@apiRegister', [
    'key' => 'api.auth.register',
    'name' => '회원가입',
    'description' => '회원가입',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/2fa/verify', 'TwoFactorController@apiVerify', [
    'key' => 'api.auth.2fa.verify',
    'name' => '2차 인증 확인',
    'description' => '2차 인증 확인',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
    'allow_statuses' => ['2FA_PENDING'],
]);

$router->post('/api/auth/login', 'LoginController@apiLogin', [
    'key' => 'api.auth.login',
    'name' => '로그인',
    'description' => '로그인',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);

$router->post('/api/auth/password/change', 'PasswordController@apiChangePassword', [
    'key' => 'api.auth.password.change',
    'name' => '비밀번호 변경',
    'description' => '비밀번호 변경',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => false,
    'allow_statuses' => ['NORMAL', 'PASSWORD_EXPIRED'],
]);

$router->post('/api/auth/password/change-later', 'PasswordController@apiChangeLater', [
    'key' => 'api.auth.password.change_later',
    'name' => '비밀번호 변경 연기',
    'description' => '비밀번호 변경 연기',
    'category' => '인증',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => ['save'],
    'log' => false,
    'allow_statuses' => ['PASSWORD_EXPIRED'],
]);

$router->post('/api/contact/send', 'ContactController@apiSend', [
    'key' => 'api.contact.send',
    'name' => '문의 접수',
    'description' => '문의 접수',
    'category' => '공개API',
    'auth' => false,
    'skip_permission' => true,
    'permissions' => [],
    'log' => false,
]);
// 회원가입 승인 처리 API
$router->post('/api/auth/approval/approve', 'UserApprovalController@apiApprove', [
    'key'             => 'api.auth.user.approve',
    'name' => '사용자 승인',
    'description' => '토큰 기반 회원가입 승인 처리를 수행하는 API',
    'category' => '인증',
    'auth'            => false,
    'skip_permission' => true,
    'log'             => true,
]);

$router->post('/api/integration/biz-status', 'ExternalIntegrationController@apiBizStatus', [
    'key' => 'api.integration.biz_status',
    'name' => '사업자 상태 조회',
    'description' => '사업자 상태 조회',
    'category' => '외부연동',
    'auth' => true,
    'permissions' => ['save'],
    'log' => true,
]);

$router->get('/api/settings/base-info/company/detail', 'CompanyController@apiDetail', [
    'key'         => 'api.settings.base-info.company.view',
    'name'        => '회사정보 상세 조회',
    'description' => '회사정보 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/company/save', 'CompanyController@apiSave', [
    'key'         => 'api.settings.base-info.company.save',
    'name'        => '회사정보 저장',
    'description' => '회사정보 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/list', 'BrandController@apiList', [
    'key'         => 'api.settings.base-info.brand.list',
    'name'        => '브랜드 목록 조회',
    'description' => '브랜드 목록 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/detail', 'BrandController@apiDetail', [
    'key'         => 'api.settings.base-info.brand.detail',
    'name'        => '브랜드 상세 조회',
    'description' => '브랜드 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/active-type', 'BrandController@apiActiveType', [
    'key'         => 'api.settings.base-info.brand.active-type',
    'name'        => '브랜드 활성 타입 조회',
    'description' => '브랜드 활성 타입 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/save', 'BrandController@apiSave', [
    'key'         => 'api.settings.base-info.brand.save',
    'name'        => '브랜드 저장',
    'description' => '브랜드 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/purge', 'BrandController@apiPurge', [
    'key'         => 'api.settings.base-info.brand.delete',
    'name'        => '브랜드 완전 삭제',
    'description' => '브랜드 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/brand/updatestatus', 'BrandController@apiUpdateStatus', [
    'key'         => 'api.settings.base-info.brand.status',
    'name'        => '브랜드 상태 변경',
    'description' => '브랜드 상태 변경',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['update'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/list', 'CodeController@apiList', [
    'key'         => 'code.view',
    'name'        => '기준정보 목록 조회',
    'description' => '기준정보 목록 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/detail', 'CodeController@apiDetail', [
    'key'         => 'code.view',
    'name'        => '기준정보 상세 조회',
    'description' => '기준정보 상세 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/groups', 'CodeController@apiGroups', [
    'key'         => 'code.view',
    'name'        => '기준정보 그룹 조회',
    'description' => '기준정보 그룹 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/system/code/save', 'CodeController@apiSave', [
    'key'         => 'code.save',
    'name'        => '기준정보 저장',
    'description' => '기준정보 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/delete', 'CodeController@apiDelete', [
    'key'         => 'code.delete',
    'name'        => '기준정보 삭제',
    'description' => '기준정보 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/trash', 'CodeController@apiTrashList', [
    'key'         => 'code.view',
    'name'        => '기준정보 휴지통 조회',
    'description' => '기준정보 휴지통 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/restore', 'CodeController@apiRestore', [
    'key'         => 'code.save',
    'name'        => '기준정보 복원',
    'description' => '기준정보 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/restore-bulk', 'CodeController@apiRestoreBulk', [
    'key'         => 'code.save',
    'name'        => '기준정보 일괄 복원',
    'description' => '기준정보 일괄 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/restore-all', 'CodeController@apiRestoreAll', [
    'key'         => 'code.save',
    'name'        => '기준정보 전체 복원',
    'description' => '기준정보 전체 복원',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/purge', 'CodeController@apiPurge', [
    'key'         => 'code.delete',
    'name'        => '기준정보 완전 삭제',
    'description' => '기준정보 완전 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/purge-bulk', 'CodeController@apiPurgeBulk', [
    'key'         => 'code.delete',
    'name'        => '기준정보 일괄 완전 삭제',
    'description' => '기준정보 일괄 완전 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/purge-all', 'CodeController@apiPurgeAll', [
    'key'         => 'code.delete',
    'name'        => '기준정보 전체 완전 삭제',
    'description' => '기준정보 전체 완전 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/reorder', 'CodeController@apiReorder', [
    'key'         => 'code.save',
    'name'        => '기준정보 순번 저장',
    'description' => '기준정보 순번 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/code/template', 'CodeController@apiDownloadTemplate', [
    'key'         => 'code.view',
    'name'        => '기준정보 양식 다운로드',
    'description' => '기준정보 양식 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/system/code/excel', 'CodeController@apiDownloadExcel', [
    'key'         => 'code.view',
    'name'        => '기준정보 엑셀 다운로드',
    'description' => '기준정보 엑셀 다운로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/code/excel-upload', 'CodeController@apiExcelUpload', [
    'key'         => 'code.save',
    'name'        => '기준정보 엑셀 업로드',
    'description' => '기준정보 엑셀 업로드',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$codeBaseInfoRedirectRoutes = [
    ['get', '/api/settings/base-info/code/list', 'code.view', ['view'], true],
    ['get', '/api/settings/base-info/code/detail', 'code.view', ['view'], true],
    ['get', '/api/settings/base-info/code/groups', 'code.view', ['view'], false],
    ['post', '/api/settings/base-info/code/save', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/delete', 'code.delete', ['delete'], true],
    ['get', '/api/settings/base-info/code/trash', 'code.view', ['view'], true],
    ['post', '/api/settings/base-info/code/restore', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/restore-bulk', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/restore-all', 'code.save', ['save'], true],
    ['post', '/api/settings/base-info/code/purge', 'code.delete', ['delete'], true],
    ['post', '/api/settings/base-info/code/purge-bulk', 'code.delete', ['delete'], true],
    ['post', '/api/settings/base-info/code/purge-all', 'code.delete', ['delete'], true],
    ['post', '/api/settings/base-info/code/reorder', 'code.save', ['save'], true],
    ['get', '/api/settings/base-info/code/template', 'code.view', ['view'], false],
    ['get', '/api/settings/base-info/code/excel', 'code.view', ['view'], true],
    ['post', '/api/settings/base-info/code/excel-upload', 'code.save', ['save'], true],
];

foreach ($codeBaseInfoRedirectRoutes as [$method, $path, $key, $permissions, $log]) {
    $router->{$method}($path, 'CodeController@redirectBaseInfoApi', [
        'key' => $key,
        'name' => '기준정보 이전 경로 리다이렉트',
        'description' => '기존 base-info 기준정보 API를 system 경로로 리다이렉트',
        'category' => '시스템설정',
        'auth' => true,
        'permissions' => $permissions,
        'log' => $log,
    ]);
}

$router->get('/api/settings/base-info/cover/list', 'CoverController@apiList', [
    'key'         => 'api.settings.base-info.cover.list',
    'name'        => '커버이미지 목록 조회',
    'description' => '커버이미지 목록 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/cover/public', 'CoverController@apiPublicList', [
    'key'         => 'api.settings.base-info.cover.public',
    'name'        => '커버이미지 공개 목록 조회',
    'description' => '커버이미지 공개 목록 조회',
    'category'    => '기초정보',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

$router->get('/api/settings/base-info/cover/detail', 'CoverController@apiDetail', [
    'key'         => 'api.settings.base-info.cover.detail',
    'name'        => '커버이미지 상세 조회',
    'description' => '커버이미지 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/save', 'CoverController@apiSave', [
    'key'         => 'api.settings.base-info.cover.save',
    'name'        => '커버이미지 저장',
    'description' => '커버이미지 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/delete', 'CoverController@apiDelete', [
    'key'         => 'api.settings.base-info.cover.delete',
    'name'        => '커버이미지 삭제',
    'description' => '커버이미지 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/cover/trash', 'CoverController@apiTrashList', [
    'key'         => 'api.settings.base-info.cover.trash.list',
    'name'        => '커버이미지 휴지통 조회',
    'description' => '커버이미지 휴지통 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/restore', 'CoverController@apiRestore', [
    'key'         => 'api.settings.base-info.cover.restore',
    'name'        => '커버이미지 복원',
    'description' => '커버이미지 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/restore-bulk', 'CoverController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.cover.restore.bulk',
    'name'        => '커버이미지 일괄 복원',
    'description' => '커버이미지 일괄 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/restore-all', 'CoverController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.cover.restore.all',
    'name'        => '커버이미지 전체 복원',
    'description' => '커버이미지 전체 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/purge', 'CoverController@apiPurge', [
    'key'         => 'api.settings.base-info.cover.purge',
    'name'        => '커버이미지 완전 삭제',
    'description' => '커버이미지 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/purge-bulk', 'CoverController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.cover.purge.bulk',
    'name'        => '커버이미지 일괄 완전 삭제',
    'description' => '커버이미지 일괄 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/purge-all', 'CoverController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.cover.purge.all',
    'name'        => '커버이미지 전체 완전 삭제',
    'description' => '커버이미지 전체 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/cover/reorder', 'CoverController@apiReorder', [
    'key'         => 'api.settings.base-info.cover.reorder',
    'name'        => '커버이미지 정렬 저장',
    'description' => '커버이미지 정렬 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/list', 'ClientController@apiList', [
    'key'         => 'api.settings.base-info.client.list',
    'name'        => '거래처 목록 조회',
    'description' => '거래처 목록 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/detail', 'ClientController@apiDetail', [
    'key'         => 'api.settings.base-info.client.detail',
    'name'        => '거래처 상세 조회',
    'description' => '거래처 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/search-picker', 'ClientController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.client.search-picker',
    'name'        => '거래처 검색',
    'description' => '거래처 검색',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/client/save', 'ClientController@apiSave', [
    'key'         => 'api.settings.base-info.client.save',
    'name'        => '거래처 저장',
    'description' => '거래처 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/delete', 'ClientController@apiDelete', [
    'key'         => 'api.settings.base-info.client.delete',
    'name'        => '거래처 삭제',
    'description' => '거래처 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/trash', 'ClientController@apiTrashList', [
    'key'         => 'api.settings.base-info.client.trash.list',
    'name'        => '거래처 휴지통 조회',
    'description' => '거래처 휴지통 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/restore', 'ClientController@apiRestore', [
    'key'         => 'api.settings.base-info.client.restore',
    'name'        => '거래처 복원',
    'description' => '거래처 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/restore-bulk', 'ClientController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.client.restore-bulk',
    'name'        => '거래처 일괄 복원',
    'description' => '거래처 일괄 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/restore-all', 'ClientController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.client.restore-all',
    'name'        => '거래처 전체 복원',
    'description' => '거래처 전체 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/purge', 'ClientController@apiPurge', [
    'key'         => 'api.settings.base-info.client.purge',
    'name'        => '거래처 완전 삭제',
    'description' => '거래처 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/purge-bulk', 'ClientController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.client.purge-bulk',
    'name'        => '거래처 일괄 완전 삭제',
    'description' => '거래처 일괄 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/purge-all', 'ClientController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.client.purge-all',
    'name'        => '거래처 전체 완전 삭제',
    'description' => '거래처 전체 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/client/reorder', 'ClientController@apiReorder', [
    'key'         => 'api.settings.base-info.client.reorder',
    'name'        => '거래처 정렬 저장',
    'description' => '거래처 정렬 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/template', 'ClientController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.client.template',
    'name'        => '거래처 양식 다운로드',
    'description' => '거래처 양식 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/client/excel-upload', 'ClientController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.client.excel-upload',
    'name'        => '거래처 엑셀 업로드',
    'description' => '거래처 엑셀 업로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/client/download', 'ClientController@apiDownload', [
    'key'         => 'api.settings.base-info.client.excel',
    'name'        => '거래처 다운로드',
    'description' => '거래처 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/list', 'ProjectController@apiList', [
    'key'         => 'api.settings.base-info.project.list',
    'name'        => '프로젝트 목록 조회',
    'description' => '프로젝트 목록 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/detail', 'ProjectController@apiDetail', [
    'key'         => 'api.settings.base-info.project.detail',
    'name'        => '프로젝트 상세 조회',
    'description' => '프로젝트 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/search-picker', 'ProjectController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.project.search-picker',
    'name'        => '프로젝트 검색',
    'description' => '프로젝트 검색',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/project/save', 'ProjectController@apiSave', [
    'key'         => 'api.settings.base-info.project.save',
    'name'        => '프로젝트 저장',
    'description' => '프로젝트 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/delete', 'ProjectController@apiDelete', [
    'key'         => 'api.settings.base-info.project.delete',
    'name'        => '프로젝트 삭제',
    'description' => '프로젝트 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/trash', 'ProjectController@apiTrashList', [
    'key'         => 'api.settings.base-info.project.trash.list',
    'name'        => '프로젝트 휴지통 조회',
    'description' => '프로젝트 휴지통 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/restore', 'ProjectController@apiRestore', [
    'key'         => 'api.settings.base-info.project.restore',
    'name'        => '프로젝트 복원',
    'description' => '프로젝트 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/restore-bulk', 'ProjectController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.project.restore-bulk',
    'name'        => '프로젝트 일괄 복원',
    'description' => '프로젝트 일괄 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/restore-all', 'ProjectController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.project.restore-all',
    'name'        => '프로젝트 전체 복원',
    'description' => '프로젝트 전체 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/purge', 'ProjectController@apiPurge', [
    'key'         => 'api.settings.base-info.project.purge',
    'name'        => '프로젝트 완전 삭제',
    'description' => '프로젝트 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/purge-bulk', 'ProjectController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.project.purge-bulk',
    'name'        => '프로젝트 일괄 완전 삭제',
    'description' => '프로젝트 일괄 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/purge-all', 'ProjectController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.project.purge-all',
    'name'        => '프로젝트 전체 완전 삭제',
    'description' => '프로젝트 전체 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/project/reorder', 'ProjectController@apiReorder', [
    'key'         => 'api.settings.base-info.project.reorder',
    'name'        => '프로젝트 정렬 저장',
    'description' => '프로젝트 정렬 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/template', 'ProjectController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.project.template',
    'name'        => '프로젝트 양식 다운로드',
    'description' => '프로젝트 양식 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/project/excel-upload', 'ProjectController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.project.excel-upload',
    'name'        => '프로젝트 엑셀 업로드',
    'description' => '프로젝트 엑셀 업로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/project/download', 'ProjectController@apiDownload', [
    'key'         => 'api.settings.base-info.project.excel',
    'name'        => '프로젝트 다운로드',
    'description' => '프로젝트 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/list', 'BankAccountController@apiList', [
    'key'         => 'api.settings.base-info.bank-account.list',
    'name'        => '계좌 목록 조회',
    'description' => '계좌 목록 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/detail', 'BankAccountController@apiDetail', [
    'key'         => 'api.settings.base-info.bank-account.detail',
    'name'        => '계좌 상세 조회',
    'description' => '계좌 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/search-picker', 'BankAccountController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.bank-account.search-picker',
    'name'        => '계좌 검색',
    'description' => '계좌 검색',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/bank-account/save', 'BankAccountController@apiSave', [
    'key'         => 'api.settings.base-info.bank-account.save',
    'name'        => '계좌 저장',
    'description' => '계좌 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/delete', 'BankAccountController@apiDelete', [
    'key'         => 'api.settings.base-info.bank-account.delete',
    'name'        => '계좌 삭제',
    'description' => '계좌 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/trash', 'BankAccountController@apiTrashList', [
    'key'         => 'api.settings.base-info.bank-account.trash.list',
    'name'        => '계좌 휴지통 조회',
    'description' => '계좌 휴지통 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/restore', 'BankAccountController@apiRestore', [
    'key'         => 'api.settings.base-info.bank-account.restore',
    'name'        => '계좌 복원',
    'description' => '계좌 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/restore-bulk', 'BankAccountController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.bank-account.restore-bulk',
    'name'        => '계좌 일괄 복원',
    'description' => '계좌 일괄 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/restore-all', 'BankAccountController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.bank-account.restore-all',
    'name'        => '계좌 전체 복원',
    'description' => '계좌 전체 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/purge', 'BankAccountController@apiPurge', [
    'key'         => 'api.settings.base-info.bank-account.purge',
    'name'        => '계좌 완전 삭제',
    'description' => '계좌 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/purge-bulk', 'BankAccountController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.bank-account.purge-bulk',
    'name'        => '계좌 일괄 완전 삭제',
    'description' => '계좌 일괄 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/purge-all', 'BankAccountController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.bank-account.purge-all',
    'name'        => '계좌 전체 완전 삭제',
    'description' => '계좌 전체 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/bank-account/reorder', 'BankAccountController@apiReorder', [
    'key'         => 'api.settings.base-info.bank-account.reorder',
    'name'        => '계좌 정렬 저장',
    'description' => '계좌 정렬 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/template', 'BankAccountController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.bank-account.template',
    'name'        => '계좌 양식 다운로드',
    'description' => '계좌 양식 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/bank-account/excel-upload', 'BankAccountController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.bank-account.excel-upload',
    'name'        => '계좌 엑셀 업로드',
    'description' => '계좌 엑셀 업로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/bank-account/download', 'BankAccountController@apiDownload', [
    'key'         => 'api.settings.base-info.bank-account.excel',
    'name'        => '계좌 다운로드',
    'description' => '계좌 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/list', 'CardController@apiList', [
    'key'         => 'api.settings.base-info.card.list',
    'name'        => '카드 목록 조회',
    'description' => '카드 목록 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/detail', 'CardController@apiDetail', [
    'key'         => 'api.settings.base-info.card.detail',
    'name'        => '카드 상세 조회',
    'description' => '카드 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/search-picker', 'CardController@apiSearchPicker', [
    'key'         => 'api.settings.base-info.card.search-picker',
    'name'        => '카드 검색',
    'description' => '카드 검색',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/card/save', 'CardController@apiSave', [
    'key'         => 'api.settings.base-info.card.save',
    'name'        => '카드 저장',
    'description' => '카드 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/delete', 'CardController@apiDelete', [
    'key'         => 'api.settings.base-info.card.delete',
    'name'        => '카드 삭제',
    'description' => '카드 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/trash', 'CardController@apiTrashList', [
    'key'         => 'api.settings.base-info.card.trash.list',
    'name'        => '카드 휴지통 조회',
    'description' => '카드 휴지통 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/restore', 'CardController@apiRestore', [
    'key'         => 'api.settings.base-info.card.restore',
    'name'        => '카드 복원',
    'description' => '카드 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/restore-bulk', 'CardController@apiRestoreBulk', [
    'key'         => 'api.settings.base-info.card.restore-bulk',
    'name'        => '카드 일괄 복원',
    'description' => '카드 일괄 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/restore-all', 'CardController@apiRestoreAll', [
    'key'         => 'api.settings.base-info.card.restore-all',
    'name'        => '카드 전체 복원',
    'description' => '카드 전체 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/purge', 'CardController@apiPurge', [
    'key'         => 'api.settings.base-info.card.purge',
    'name'        => '카드 완전 삭제',
    'description' => '카드 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/purge-bulk', 'CardController@apiPurgeBulk', [
    'key'         => 'api.settings.base-info.card.purge-bulk',
    'name'        => '카드 일괄 완전 삭제',
    'description' => '카드 일괄 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/purge-all', 'CardController@apiPurgeAll', [
    'key'         => 'api.settings.base-info.card.purge-all',
    'name'        => '카드 전체 완전 삭제',
    'description' => '카드 전체 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/card/reorder', 'CardController@apiReorder', [
    'key'         => 'api.settings.base-info.card.reorder',
    'name'        => '카드 정렬 저장',
    'description' => '카드 정렬 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/template', 'CardController@apiDownloadTemplate', [
    'key'         => 'api.settings.base-info.card.template',
    'name'        => '카드 양식 다운로드',
    'description' => '카드 양식 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/base-info/card/excel-upload', 'CardController@apiSaveFromExcel', [
    'key'         => 'api.settings.base-info.card.excel-upload',
    'name'        => '카드 엑셀 업로드',
    'description' => '카드 엑셀 업로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/card/download', 'CardController@apiDownload', [
    'key'         => 'api.settings.base-info.card.download',
    'name'        => '카드 다운로드',
    'description' => '카드 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/list', 'WorkTeamController@apiList', [
    'key'         => 'work_team.view',
    'name'        => '작업팀 목록 조회',
    'description' => '작업팀 목록 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/detail', 'WorkTeamController@apiDetail', [
    'key'         => 'work_team.view',
    'name'        => '작업팀 상세 조회',
    'description' => '작업팀 상세 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/save', 'WorkTeamController@apiSave', [
    'key'         => 'work_team.save',
    'name'        => '작업팀 저장',
    'description' => '작업팀 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/delete', 'WorkTeamController@apiDelete', [
    'key'         => 'work_team.delete',
    'name'        => '작업팀 삭제',
    'description' => '작업팀 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/trash', 'WorkTeamController@apiTrashList', [
    'key'         => 'work_team.view',
    'name'        => '작업팀 휴지통 조회',
    'description' => '작업팀 휴지통 조회',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/restore', 'WorkTeamController@apiRestore', [
    'key'         => 'work_team.save',
    'name'        => '작업팀 복원',
    'description' => '작업팀 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/restore-bulk', 'WorkTeamController@apiRestoreBulk', [
    'key'         => 'work_team.save',
    'name'        => '작업팀 일괄 복원',
    'description' => '작업팀 일괄 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/restore-all', 'WorkTeamController@apiRestoreAll', [
    'key'         => 'work_team.save',
    'name'        => '작업팀 전체 복원',
    'description' => '작업팀 전체 복원',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/purge', 'WorkTeamController@apiPurge', [
    'key'         => 'work_team.delete',
    'name'        => '작업팀 완전 삭제',
    'description' => '작업팀 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/purge-bulk', 'WorkTeamController@apiPurgeBulk', [
    'key'         => 'work_team.delete',
    'name'        => '작업팀 일괄 완전 삭제',
    'description' => '작업팀 일괄 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/purge-all', 'WorkTeamController@apiPurgeAll', [
    'key'         => 'work_team.delete',
    'name'        => '작업팀 전체 완전 삭제',
    'description' => '작업팀 전체 완전 삭제',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/reorder', 'WorkTeamController@apiReorder', [
    'key'         => 'work_team.save',
    'name'        => '작업팀 순번 저장',
    'description' => '작업팀 순번 저장',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/base-info/work-team/template', 'WorkTeamController@apiDownloadTemplate', [
    'key'         => 'work_team.view',
    'name'        => '작업팀 양식 다운로드',
    'description' => '작업팀 양식 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/base-info/work-team/excel', 'WorkTeamController@apiDownloadExcel', [
    'key'         => 'work_team.view',
    'name'        => '작업팀 엑셀 다운로드',
    'description' => '작업팀 엑셀 다운로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/base-info/work-team/excel-upload', 'WorkTeamController@apiExcelUpload', [
    'key'         => 'work_team.save',
    'name'        => '작업팀 엑셀 업로드',
    'description' => '작업팀 엑셀 업로드',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/employee/list', 'EmployeeController@apiList', [
    'key'         => 'api.settings.employee.list',
    'name'        => '직원 목록 조회',
    'description' => '직원 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/employee/detail', 'EmployeeController@apiDetail', [
    'key'         => 'api.settings.employee.detail',
    'name'        => '직원 상세 조회',
    'description' => '직원 상세 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/employee/search-picker', 'EmployeeController@apiSearchPicker', [
    'key'         => 'api.settings.employee.search',
    'name'        => '직원 검색',
    'description' => '직원 검색',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/employee/save', 'EmployeeController@apiSave', [
    'key'         => 'api.settings.employee.save',
    'name'        => '직원 저장',
    'description' => '직원 저장',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/employee/update-status', 'EmployeeController@apiUpdateStatus', [
    'key'         => 'api.settings.employee.update-status',
    'name'        => '직원 상태 변경',
    'description' => '직원 상태 변경',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/employee/delete', 'EmployeeController@apiDelete', [
    'key'         => 'api.settings.employee.delete',
    'name'        => '직원 삭제',
    'description' => '직원 삭제',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/employee/reorder', 'EmployeeController@apiReorder', [
    'key'         => 'api.settings.employee.reorder',
    'name'        => '직원 순번 변경',
    'description' => '직원 순번 변경',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/user/profile/detail', 'ProfileController@apiDetail', [
    'key'         => 'api.user.profile.detail',
    'name'        => '내 프로필 상세 조회',
    'description' => '내 프로필 상세 조회',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/user/profile/save', 'ProfileController@apiSave', [
    'key'         => 'api.user.profile.save',
    'name'        => '내 프로필 저장',
    'description' => '내 프로필 저장',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/department/list', 'DepartmentController@apiList', [
    'key'         => 'api.settings.department.list',
    'name'        => '부서 목록 조회',
    'description' => '부서 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/department/detail', 'DepartmentController@apiDetail', [
    'key'         => 'api.settings.department.detail',
    'name'        => '부서 상세 조회',
    'description' => '부서 상세 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/department/save', 'DepartmentController@apiSave', [
    'key'         => 'api.settings.department.save',
    'name'        => '부서 저장',
    'description' => '부서 저장',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/department/delete', 'DepartmentController@apiDelete', [
    'key'         => 'api.settings.department.delete',
    'name'        => '부서 삭제',
    'description' => '부서 삭제',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/department/reorder', 'DepartmentController@apiReorder', [
    'key'         => 'api.settings.department.reorder',
    'name'        => '부서 순번 변경',
    'description' => '부서 순번 변경',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/position/list', 'PositionController@apiList', [
    'key'         => 'api.settings.position.list',
    'name'        => '직책 목록 조회',
    'description' => '직책 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/position/detail', 'PositionController@apiDetail', [
    'key'         => 'api.settings.position.detail',
    'name'        => '직책 상세 조회',
    'description' => '직책 상세 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/position/save', 'PositionController@apiSave', [
    'key'         => 'api.settings.position.save',
    'name'        => '직책 저장',
    'description' => '직책 저장',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/position/delete', 'PositionController@apiDelete', [
    'key'         => 'api.settings.position.delete',
    'name'        => '직책 삭제',
    'description' => '직책 삭제',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/position/reorder', 'PositionController@apiReorder', [
    'key'         => 'api.settings.position.reorder',
    'name'        => '직책 순번 변경',
    'description' => '직책 순번 변경',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/role/list', 'RoleController@apiList', [
    'key'         => 'api.settings.role.list',
    'name'        => '역할 목록 조회',
    'description' => '역할 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/settings/organization/role/detail', 'RoleController@apiDetail', [
    'key'         => 'api.settings.role.detail',
    'name'        => '역할 상세 조회',
    'description' => '역할 상세 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/role/save', 'RoleController@apiSave', [
    'key'         => 'api.settings.role.save',
    'name'        => '역할 저장',
    'description' => '역할 저장',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/role/delete', 'RoleController@apiDelete', [
    'key'         => 'api.settings.role.delete',
    'name'        => '역할 삭제',
    'description' => '역할 삭제',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/organization/role/reorder', 'RoleController@apiReorder', [
    'key'         => 'api.settings.role.reorder',
    'name'        => '역할 순번 변경',
    'description' => '역할 순번 변경',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/organization/permission/list', 'PermissionController@apiList', [
    'key'         => 'api.settings.permission.list',
    'name'        => '권한 목록 조회',
    'description' => '권한 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/role-permission/list', 'RolePermissionController@apiList', [
    'key'         => 'api.settings.rolepermission.list',
    'name'        => '권한부여 목록 조회',
    'description' => '권한부여 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/role-permission/assign', 'RolePermissionController@apiAssign', [
    'key'         => 'api.settings.rolepermission.assign',
    'name'        => '권한부여 할당',
    'description' => '권한부여 할당',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/organization/role-permission/remove', 'RolePermissionController@apiRemove', [
    'key'         => 'api.settings.rolepermission.remove',
    'name'        => '권한부여 해제',
    'description' => '권한부여 해제',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/settings/organization/approval/template/list', 'ApprovalTemplateController@apiTemplateList', [
    'key'         => 'api.settings.approval.template.list',
    'name'        => '결재 템플릿 목록 조회',
    'description' => '결재 템플릿 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/approval/template/list', 'ApprovalTemplateController@apiTemplateList', [
    'key'         => 'api.settings.approval.template.list',
    'name'        => '결재 템플릿 목록 조회',
    'description' => '결재 템플릿 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/settings/organization/approval/template/save', 'ApprovalTemplateController@apiTemplateSave', [
    'key'         => 'api.settings.approval.template.save',
    'name'        => '결재 템플릿 저장',
    'description' => '결재 템플릿 저장',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true
]);

$router->post('/api/settings/organization/approval/template/delete', 'ApprovalTemplateController@apiTemplateDelete', [
    'key'         => 'api.settings.approval.template.delete',
    'name'        => '결재 템플릿 삭제',
    'description' => '결재 템플릿 삭제',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true
]);

$router->post('/api/settings/organization/approval/template/reorder', 'ApprovalTemplateController@apiTemplateReorder', [
    'key'         => 'api.settings.approval.template.save',
    'name'        => '결재 템플릿 순번 저장',
    'description' => '결재 템플릿 순번 저장',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true
]);

$router->get('/api/settings/organization/approval/step/list', 'ApprovalTemplateController@apiStepList', [
    'key'         => 'api.settings.approval.step.list',
    'name'        => '결재 단계 목록 조회',
    'description' => '결재 단계 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false
]);

$router->post('/api/settings/organization/approval/step/list', 'ApprovalTemplateController@apiStepList', [
    'key'         => 'api.settings.approval.step.list',
    'name'        => '결재 단계 목록 조회',
    'description' => '결재 단계 목록 조회',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false
]);

$router->post('/api/settings/organization/approval/step/save', 'ApprovalTemplateController@apiStepSave', [
    'key'         => 'api.settings.approval.step.save',
    'name'        => '결재 단계 저장',
    'description' => '결재 단계 저장',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true
]);

$router->post('/api/settings/organization/approval/step/delete', 'ApprovalTemplateController@apiStepDelete', [
    'key'         => 'api.settings.approval.step.delete',
    'name'        => '결재 단계 삭제',
    'description' => '결재 단계 삭제',
    'category'    => '조직관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true
]);

$router->get('/api/settings/system/site/get', 'SystemController@apiSiteGet', [
    'key'         => 'api.settings.system.site.view',
    'name'        => '사이트정보 조회',
    'description' => '사이트정보 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/site/save', 'SystemController@apiSiteSave', [
    'key'         => 'api.settings.system.site.edit',
    'name'        => '사이트정보 저장',
    'description' => '사이트정보 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/session/get', 'SystemController@apiSessionGet', [
    'key'         => 'api.settings.system.session.view',
    'name'        => '세션관리 조회',
    'description' => '세션관리 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/session/save', 'SystemController@apiSessionSave', [
    'key'         => 'api.settings.system.session.edit',
    'name'        => '세션관리 저장',
    'description' => '세션관리 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/security/get', 'SystemController@apiSecurityGet', [
    'key'         => 'api.settings.system.security.view',
    'name'        => '보안정책 조회',
    'description' => '보안정책 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/security/save', 'SystemController@apiSecuritySave', [
    'key'         => 'api.settings.system.security.edit',
    'name'        => '보안정책 저장',
    'description' => '보안정책 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/api/get', 'SystemController@apiApiGet', [
    'key'         => 'api.settings.system.api.view',
    'name'        => '외부연동 API 조회',
    'description' => '외부연동 API 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/api/save', 'SystemController@apiApiSave', [
    'key'         => 'api.settings.system.api.edit',
    'name'        => '외부연동 API 저장',
    'description' => '외부연동 API 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/external/ping', 'ExternalApiController@ping', [
    'key'         => 'api.external.ping',
    'name'        => '외부연동 상태 조회',
    'description' => '외부연동 상태 조회',
    'category'    => '외부연동',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
    'middleware'  => ['ApiAccessMiddleware'],
]);

$router->get('/api/external/employees/list', 'ExternalEmployeeController@list', [
    'key'         => 'api.external.employee.list',
    'name'        => '외부 직원 목록 조회',
    'description' => '외부 직원 목록 조회',
    'category'    => '외부연동',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
    'middleware'  => ['ApiAccessMiddleware'],
]);

$router->get('/api/external/employees', 'ExternalApiController@employees', [
    'key'         => 'api.external.employee.list',
    'name'        => '외부 직원 조회',
    'description' => '외부 직원 조회',
    'category'    => '외부연동',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
    'middleware'  => ['ApiAccessMiddleware'],
]);

$router->get('/api/settings/system/external-services/get', 'SystemController@apiExternalServicesGet', [
    'key'         => 'api.settings.system.external.view',
    'name'        => '외부서비스 조회',
    'description' => '외부서비스 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/external-services/save', 'SystemController@apiExternalServicesSave', [
    'key'         => 'api.settings.system.external.edit',
    'name'        => '외부서비스 저장',
    'description' => '외부서비스 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/user/external-accounts', 'ExternalAccountController@apiList', [
    'key'         => 'api.user.external_accounts.view',
    'name'        => '외부계정 조회',
    'description' => '외부계정 조회',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/user/external-accounts/get', 'ExternalAccountController@apiGet', [
    'key'         => 'api.user.external_accounts.detail',
    'name'        => '외부계정 조회',
    'description' => '외부계정 조회',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/user/external-accounts/save', 'ExternalAccountController@apiSave', [
    'key'         => 'api.user.external_accounts.edit',
    'name'        => '외부계정 저장',
    'description' => '외부계정 저장',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/user/external-accounts/delete', 'ExternalAccountController@apiDelete', [
    'key'         => 'api.user.external_accounts.delete',
    'name'        => '외부계정 삭제',
    'description' => '외부계정 삭제',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/file/preview', 'FileController@apiPreview', [
    'key'         => 'api.file.preview',
    'name'        => '파일 미리보기 조회',
    'description' => '파일 미리보기 조회',
    'category'    => '시스템설정',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

$router->post('/api/file/upload-test', 'FileController@apiUploadTest', [
    'key'         => 'api.file.upload.test',
    'name'        => '파일 업로드 테스트 처리',
    'description' => '파일 업로드 테스트 처리',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/system/file-policies', 'FileController@apiPolicyList', [
    'key'         => 'api.settings.system.storage.policy.view',
    'name'        => '파일 정책 생성',
    'description' => '파일 정책 생성',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/system/file-policies', 'FileController@apiPolicyCreate', [
    'key'         => 'api.settings.system.storage.policy.create',
    'name'        => '파일 정책 생성',
    'description' => '파일 정책 생성',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/system/file-policies/update', 'FileController@apiPolicyUpdate', [
    'key'         => 'api.settings.system.storage.policy.edit',
    'name'        => '파일 정책 수정',
    'description' => '파일 정책 수정',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/system/file-policies/delete', 'FileController@apiPolicyDelete', [
    'key'         => 'api.settings.system.storage.policy.delete',
    'name'        => '파일 정책 삭제',
    'description' => '파일 정책 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/system/file-policies/toggle', 'FileController@apiPolicyToggle', [
    'key'         => 'api.settings.system.storage.policy.toggle',
    'name'        => '파일 정책 상태 변경',
    'description' => '파일 정책 상태 변경',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/system/storage/bucket-browse', 'FileController@apiBucketBrowse', [
    'key'         => 'api.settings.system.storage.browse',
    'name'        => '파일저장소 탐색',
    'description' => '파일저장소 탐색',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/get', 'SystemController@apiDatabaseGet', [
    'key'         => 'api.settings.system.database.view',
    'name'        => '데이터백업 조회',
    'description' => '데이터백업 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/database/save', 'SystemController@apiDatabaseSave', [
    'key'         => 'api.settings.system.database.edit',
    'name'        => '데이터백업 저장',
    'description' => '데이터백업 저장',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/settings/system/database/run', 'SystemController@apiBackupRun', [
    'key'         => 'api.settings.system.database.run',
    'name'        => '데이터백업 실행',
    'description' => '데이터백업 실행',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/info', 'SystemController@apiBackupInfo', [
    'key'         => 'api.settings.system.database.info',
    'name'        => '데이터백업 정보 조회',
    'description' => '데이터백업 정보 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/log', 'SystemController@apiBackupLog', [
    'key'         => 'api.settings.system.database.log',
    'name'        => '데이터백업 로그 조회',
    'description' => '데이터백업 로그 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/replication-status', 'SystemController@apiDatabaseReplicationStatus', [
    'key'         => 'api.settings.system.database.replication',
    'name'        => '데이터백업 복제 상태 조회',
    'description' => '데이터백업 복제 상태 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/database/restore-secondary', 'SystemController@apiRestoreSecondary', [
    'key'         => 'api.settings.system.database.restore',
    'name'        => 'Secondary DB 복원 실행',
    'description' => 'Secondary DB 복원 실행',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/settings/system/database/secondary-restore-info', 'SystemController@apiSecondaryRestoreInfo', [
    'key'         => 'api.settings.system.database.view',
    'name'        => 'Secondary DB 복원 정보 조회',
    'description' => 'Secondary DB 복원 정보 조회',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/logs/view', 'SystemController@apiLogView', [
    'key'         => 'api.settings.system.logs.view',
    'name'        => '로그관리 처리',
    'description' => '로그관리 처리',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/settings/system/logs/delete', 'SystemController@apiLogDelete', [
    'key'         => 'api.settings.system.logs.delete',
    'name'        => '로그관리 삭제',
    'description' => '로그관리 삭제',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/settings/system/logs/delete-all', 'SystemController@apiLogDeleteAll', [
    'key'         => 'api.settings.system.logs.delete_all',
    'name'        => '로그관리 처리',
    'description' => '로그관리 처리',
    'category'    => '시스템설정',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/approval/request/create', 'ApprovalRequestController@apiCreate', [
    'key'         => 'api.approval.request.create',
    'name'        => '결재 요청 생성',
    'description' => '결재 요청 생성',
    'category'    => '전자결재',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/approval/request/detail', 'ApprovalRequestController@apiDetail', [
    'key'         => 'api.approval.request.detail',
    'name'        => '결재 요청 상세 조회',
    'description' => '결재 요청 상세 조회',
    'category'    => '전자결재',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/approval/request/approve', 'ApprovalRequestController@apiApproveStep', [
    'key'         => 'api.approval.step.approve',
    'name'        => '결재 단계 승인',
    'description' => '결재 단계 승인',
    'category'    => '전자결재',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/approval/request/reject', 'ApprovalRequestController@apiRejectStep', [
    'key'         => 'api.approval.step.reject',
    'name'        => '결재 단계 반려',
    'description' => '결재 단계 반려',
    'category'    => '전자결재',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/approval/request/status', 'ApprovalRequestController@apiStatus', [
    'key'         => 'api.approval.request.status',
    'name'        => '결재 요청 상태 조회',
    'description' => '결재 요청 상태 조회',
    'category'    => '전자결재',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/approval/request/step/delete', 'ApprovalRequestController@apiDeleteStep', [
    'key'         => 'api.approval.step.delete',
    'name'        => '결재 단계 삭제',
    'description' => '결재 단계 삭제',
    'category'    => '전자결재',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/dashboard/calendar/list', 'CalendarController@apiList', [
    'key'         => 'api.dashboard.calendar.list',
    'name'        => '캘린더 목록 조회',
    'description' => '캘린더 목록 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/events-all', 'CalendarController@apiEventsAll', [
    'key'         => 'api.dashboard.calendar.events_all',
    'name'        => '캘린더 전체 일정 조회',
    'description' => '캘린더 전체 일정 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/events', 'CalendarController@apiEvents', [
    'key'         => 'api.dashboard.calendar.events',
    'name'        => '캘린더 일정 조회',
    'description' => '캘린더 일정 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/dashboard/calendar/event/create', 'CalendarController@apiEventCreate', [
    'key'         => 'api.dashboard.calendar.event.create',
    'name'        => '일정 생성',
    'description' => '일정 생성',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/update', 'CalendarController@apiEventUpdate', [
    'key'         => 'api.dashboard.calendar.event.update',
    'name'        => '일정 수정',
    'description' => '일정 수정',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/delete', 'CalendarController@apiEventDelete', [
    'key'         => 'api.dashboard.calendar.event.delete',
    'name'        => '일정 삭제',
    'description' => '일정 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/dashboard/calendar/tasks', 'CalendarController@apiTasks', [
    'key'         => 'api.dashboard.calendar.tasks',
    'name'        => '작업 조회',
    'description' => '작업 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/tasks-all', 'CalendarController@apiTasksAll', [
    'key'         => 'api.dashboard.calendar.tasks_all',
    'name'        => '전체 작업 조회',
    'description' => '전체 작업 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/dashboard/calendar/task/create', 'CalendarController@apiTaskCreate', [
    'key'         => 'api.dashboard.calendar.task.create',
    'name'        => '작업 생성',
    'description' => '작업 생성',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/toggle-complete', 'CalendarController@apiToggleTaskComplete', [
    'key'         => 'api.dashboard.calendar.task.toggle_complete',
    'name'        => '작업 완료 상태 변경',
    'description' => '작업 완료 상태 변경',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/update', 'CalendarController@apiTaskUpdate', [
    'key'         => 'api.dashboard.calendar.task.update',
    'name'        => '작업 수정',
    'description' => '작업 수정',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/delete', 'CalendarController@apiTaskDelete', [
    'key'         => 'api.dashboard.calendar.task.delete',
    'name'        => '작업 삭제',
    'description' => '작업 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/collection/delete', 'CalendarController@apiCollectionDelete', [
    'key'         => 'api.dashboard.calendar.collection.delete',
    'name'        => '캘린더 컬렉션 삭제',
    'description' => '캘린더 컬렉션 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/hard-delete', 'CalendarController@apiEventHardDelete', [
    'key'         => 'api.dashboard.calendar.event.hard_delete',
    'name'        => '일정 완전 삭제',
    'description' => '일정 완전 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/cache-rebuild', 'CalendarController@apiCacheRebuild', [
    'key'         => 'api.dashboard.calendar.cache_rebuild',
    'name'        => '캘린더 캐시 재구성',
    'description' => '캘린더 캐시 재구성',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/hard-delete', 'CalendarController@apiTaskHardDelete', [
    'key'         => 'api.dashboard.calendar.task.hard_delete',
    'name'        => '작업 완전 삭제',
    'description' => '작업 완전 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/update-admin-color', 'CalendarController@apiUpdateAdminColor', [
    'key'         => 'api.dashboard.calendar.update_admin_color',
    'name'        => '관리자 색상 변경',
    'description' => '관리자 색상 변경',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->get('/api/dashboard/calendar/tasks-panel', 'CalendarController@apiTasksPanel', [
    'key'         => 'api.dashboard.calendar.tasks_panel',
    'name'        => '작업 패널 조회',
    'description' => '작업 패널 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/events-deleted', 'CalendarController@apiEventsDeleted', [
    'key'         => 'api.dashboard.calendar.events_deleted',
    'name'        => '삭제 일정 조회',
    'description' => '삭제 일정 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/dashboard/calendar/tasks-deleted', 'CalendarController@apiTasksDeleted', [
    'key'         => 'api.dashboard.calendar.tasks_deleted',
    'name'        => '삭제 작업 조회',
    'description' => '삭제 작업 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/dashboard/calendar/event/hard-delete-all', 'CalendarController@apiEventHardDeleteAll', [
    'key'         => 'api.dashboard.calendar.event.hard_delete_all',
    'name'        => '일정 전체 완전 삭제',
    'description' => '일정 전체 완전 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/hard-delete-all', 'CalendarController@apiTaskHardDeleteAll', [
    'key'         => 'api.dashboard.calendar.task.hard_delete_all',
    'name'        => '작업 전체 완전 삭제',
    'description' => '작업 전체 완전 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/event/restore', 'CalendarController@apiEventRestore', [
    'key'         => 'api.dashboard.calendar.event.restore',
    'name'        => '일정 복원',
    'description' => '일정 복원',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/restore', 'CalendarController@apiTaskRestore', [
    'key'         => 'api.dashboard.calendar.task.restore',
    'name'        => '작업 복원',
    'description' => '작업 복원',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/dashboard/calendar/task/delete-bulk', 'CalendarController@apiTaskDeleteBulk', [
    'key'         => 'api.dashboard.calendar.task.delete_bulk',
    'name'        => '작업 일괄 삭제',
    'description' => '작업 일괄 삭제',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/dashboard/profile-summary', 'CalendarController@apiProfileSummary', [
    'key'         => 'api.dashboard.profile_summary',
    'name'        => '프로필 요약 조회',
    'description' => '프로필 요약 조회',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/list', 'ChartAccountController@apiList', [
    'key'         => 'api.ledger.account.list',
    'name'        => '계정과목 목록 조회',
    'description' => '계정과목 목록 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/account/tree', 'ChartAccountController@apiTree', [
    'key'         => 'api.ledger.account.tree',
    'name'        => '계정과목 트리 조회',
    'description' => '계정과목 트리 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/detail', 'ChartAccountController@apiDetail', [
    'key'         => 'api.ledger.account.detail',
    'name'        => '계정과목 상세 조회',
    'description' => '계정과목 상세 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/account/template', 'ChartAccountController@apiTemplate', [
    'key'         => 'api.ledger.account.template',
    'name'        => '계정과목 양식 다운로드',
    'description' => '계정과목 양식 다운로드',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/trash', 'ChartAccountController@apiTrashList', [
    'key'         => 'api.ledger.account.trash',
    'name'        => '계정과목 휴지통 조회',
    'description' => '계정과목 휴지통 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/ledger/account/save', 'ChartAccountController@apiSave', [
    'key'         => 'api.ledger.account.save',
    'name'        => '계정과목 저장',
    'description' => '계정과목 저장',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/soft-delete', 'ChartAccountController@apiSoftDelete', [
    'key'         => 'api.ledger.account.soft_delete',
    'name'        => '계정과목 삭제',
    'description' => '계정과목 삭제',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/account/restore', 'ChartAccountController@apiRestore', [
    'key'         => 'api.ledger.account.restore',
    'name'        => '계정과목 복원',
    'description' => '계정과목 복원',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/hard-delete', 'ChartAccountController@apiHardDelete', [
    'key'         => 'api.ledger.account.hard_delete',
    'name'        => '계정과목 완전 삭제',
    'description' => '계정과목 완전 삭제',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/ledger/account/template', 'ChartAccountController@apiTemplate', [
    'key'         => 'api.ledger.account.template',
    'name'        => '계정과목 양식 다운로드',
    'description' => '계정과목 양식 다운로드',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 계정과목 엑셀 다운로드
// ============================================================
$router->get('/api/ledger/account/excel', 'ChartAccountController@apiDownloadAllExcel', [
    'key'         => 'api.ledger.account.excel',
    'name'        => '계정과목 엑셀 다운로드',
    'description' => '계정과목 엑셀 다운로드',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/ledger/account/excel-upload', 'ChartAccountController@apiExcelUpload', [
    'key'         => 'api.ledger.account.excel_upload',
    'name'        => '계정과목 엑셀 업로드',
    'description' => '계정과목 엑셀 업로드',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/reorder', 'ChartAccountController@apiReorder', [
    'key'         => 'api.ledger.account.reorder',
    'name'        => '계정과목 정렬 저장',
    'description' => '계정과목 정렬 저장',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/restore-bulk', 'ChartAccountController@apiRestoreBulk', [
    'key'         => 'api.ledger.account.restore_bulk',
    'name'        => '계정과목 일괄 복원',
    'description' => '계정과목 일괄 복원',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/restore-all', 'ChartAccountController@apiRestoreAll', [
    'key'         => 'api.ledger.account.restore_all',
    'name'        => '계정과목 전체 복원',
    'description' => '계정과목 전체 복원',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/account/hard-delete-bulk', 'ChartAccountController@apiHardDeleteBulk', [
    'key'         => 'api.ledger.account.hard_delete_bulk',
    'name'        => '계정과목 일괄 완전 삭제',
    'description' => '계정과목 일괄 완전 삭제',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/account/hard-delete-all', 'ChartAccountController@apiHardDeleteAll', [
    'key'         => 'api.ledger.account.hard_delete_all',
    'name'        => '계정과목 전체 완전 삭제',
    'description' => '계정과목 전체 완전 삭제',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

// ============================================================
// 계정과목 엑셀 다운로드
// ============================================================
$router->get('/api/ledger/account/excel', 'ChartAccountController@apiDownloadAllExcel', [
    'key'         => 'api.ledger.account.excel',
    'name'        => '계정과목 엑셀 다운로드',
    'description' => '계정과목 엑셀 다운로드',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/account/search', 'ChartAccountController@apiSearch', [
    'key'         => 'api.ledger.account.search',
    'name'        => '계정과목 검색',
    'description' => '계정과목 검색',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/account/posting', 'ChartAccountController@apiPosting', [
    'key'         => 'api.ledger.account.posting',
    'name'        => '계정과목 전기 가능 계정 조회',
    'description' => '계정과목 전기 가능 계정 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/api/ledger/sub-account/list', 'SubChartAccountController@apiList', [
    'key'         => 'api.ledger.sub_account.list',
    'name'        => '보조계정 목록 조회',
    'description' => '보조계정 목록 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->post('/api/ledger/sub-account/save', 'SubChartAccountController@apiSave', [
    'key'         => 'api.ledger.sub_account.save',
    'name'        => '보조계정 저장',
    'description' => '보조계정 저장',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/sub-account/update', 'SubChartAccountController@apiUpdate', [
    'key'         => 'api.ledger.sub_account.update',
    'name'        => '보조계정 수정',
    'description' => '보조계정 수정',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/sub-account/delete', 'SubChartAccountController@apiDelete', [
    'key'         => 'api.ledger.sub_account.delete',
    'name'        => '보조계정 삭제',
    'description' => '보조계정 삭제',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->get('/api/ledger/voucher/list', 'VoucherController@apiList', [
    'key'         => 'api.ledger.voucher.list',
    'name'        => '일반전표 목록 조회',
    'description' => '일반전표 목록 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/voucher/detail', 'VoucherController@apiDetail', [
    'key'         => 'api.ledger.voucher.detail',
    'name'        => '일반전표 상세 조회',
    'description' => '일반전표 상세 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/api/ledger/voucher/trash', 'VoucherController@apiTrashList', [
    'key'         => 'api.ledger.voucher.trash',
    'name'        => '일반전표 휴지통 조회',
    'description' => '일반전표 휴지통 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/save', 'VoucherController@apiSave', [
    'key'         => 'api.ledger.voucher.save',
    'name'        => '일반전표 저장',
    'description' => '일반전표 저장',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

/* =========================================================
 * 회계관리 > 전표 처리 및 거래 연계 API
 * ========================================================= */

// ============================================================
// 전표 기반 거래 생성
// ============================================================
$router->post('/api/ledger/voucher/create-transaction', 'VoucherController@apiCreateTransaction', [
    'key'         => 'api.ledger.voucher.create_transaction',
    'name'        => '전표 기반 거래 생성',
    'description' => '전표 기반 거래 생성',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/delete', 'VoucherController@apiDelete', [
    'key'         => 'api.ledger.voucher.delete',
    'name'        => '일반전표 삭제',
    'description' => '일반전표 삭제',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/restore', 'VoucherController@apiRestore', [
    'key'         => 'api.ledger.voucher.restore',
    'name'        => '일반전표 복원',
    'description' => '일반전표 복원',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

$router->post('/api/ledger/voucher/purge', 'VoucherController@apiPurge', [
    'key'         => 'api.ledger.voucher.purge',
    'name'        => '일반전표 완전 삭제',
    'description' => '일반전표 완전 삭제',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['delete'],
    'log'         => true,
]);







/* =========================================================
 * 회계관리 > 거래 관리 API
 * ========================================================= */

// ============================================================
// 거래 목록 조회
// ============================================================
$router->get('/api/ledger/transaction/list', 'TransactionController@apiList', [
    'key'         => 'api.ledger.transaction.list',
    'name'        => '거래 목록 조회',
    'description' => '거래 목록 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 거래 상세 조회
// ============================================================
$router->get('/api/ledger/transaction/detail', 'TransactionController@apiDetail', [
    'key'         => 'api.ledger.transaction.detail',
    'name'        => '거래 상세 조회',
    'description' => '거래 상세 조회',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 거래 저장
// ============================================================
$router->post('/api/ledger/transaction/save', 'TransactionController@apiSave', [
    'key'         => 'api.ledger.transaction.save',
    'name'        => '거래 저장',
    'description' => '거래 저장',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);

// ============================================================
// 거래 기반 전표 생성
// ============================================================
$router->post('/api/ledger/transaction/create-voucher', 'TransactionController@apiCreateVoucher', [
    'key'         => 'api.ledger.transaction.create_voucher',
    'name'        => '거래 기반 전표 생성',
    'description' => '거래 기반 전표 생성',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['save'],
    'log'         => true,
]);
