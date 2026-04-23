<?php
// 경로: PROJECT_ROOT . '/routes/web.php';

global $router;
// $routePost('/api/sample/path', 'SampleController@apiMethod', [

//     /* =========================
//      * 기본 식별 / 표시
//      * ========================= */

//     'key'         => 'api.sample.path.method',   // 🔴 필수: 고유 식별 (중복 절대 금지)
//     'name'        => '샘플 기능명',               // 🔴 필수: UI/로그 표시용
//     'category'    => '기능분류',                 // 🔴 필수: 시스템 분류 (회계관리, 기초정보 등)

//     /* =========================
//      * 인증 / 권한
//      * ========================= */

//     'auth'        => true,   // 로그인 필요 여부
//                             // true  = 로그인 필요
//                             // false = 비로그인 접근 허용

//     'permissions' => ['view'], // 권한 배열
//                               // ['view']   조회
//                               // ['save']   등록/수정
//                               // ['delete'] 삭제
//                               // []         권한 없음

//     'skip'        => false,  // ⭐ 권한 체크 스킵 여부
//                             // true  = permission 체크 완전 제외
//                             // false = permission 체크 수행

//     /* =========================
//      * 상태 기반 접근 제어 (선택)
//      * ========================= */

//     'allow_statuses' => ['NORMAL'],
//     // 특정 상태에서만 접근 허용
//     // 예: ['2FA_PENDING'], ['PASSWORD_EXPIRED']

//     /* =========================
//      * 미들웨어 제어 (선택)
//      * ========================= */

//     'middleware'  => [],
//     // 특정 미들웨어 강제 지정
//     // 예: ['ApiAccessMiddleware']

//     /* =========================
//      * 특수 접근 제어 (선택)
//      * ========================= */

//     'guest_only'  => false,
//     // true = 로그인된 사용자는 접근 불가

//     /* =========================
//      * 로그 설정
//      * ========================= */

//     'log'         => true,
//     // true  = 로그 기록
//     // false = 로그 제외

// ]);
$router->get('/error', 'ErrorController@forbidden', [
    'key' => 'web.error',
    'name' => '오류 페이지',
    'category' => '시스템',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/autologout/keepalive', 'SessionController@apiKeepalive', [
    'key' => 'web.autologout.keepalive',
    'name' => '세션 유지',
    'category' => '시스템',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/autologout/extend', 'SessionController@webExtendView', [
    'key' => 'web.autologout.extend',
    'name' => '세션 연장',
    'category' => '시스템',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/autologout/expired', 'SessionController@webExpired', [
    'key' => 'web.autologout.expired',
    'name' => '세션 만료',
    'category' => '시스템',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/', 'HomeController@webRoot', [
    'key' => 'web.root',
    'name' => '홈',
    'category' => '공개페이지',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/home', 'HomeController@webIndex', [
    'key' => 'web.home',
    'name' => '메인',
    'category' => '공개페이지',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/about', 'AboutController@webAbout', [
    'key' => 'web.about',
    'name' => '회사 소개',
    'category' => '공개페이지',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/admin/about', 'AboutController@webAdminAbout', [
    'key' => 'web.admin.about.view',
    'name' => '관리자 회사 소개',
    'category' => '관리자',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '관리자용 회사 소개 페이지 접근',
]);

$router->get('/vision', 'HomeController@webVision', [
    'key' => 'web.vision',
    'name' => '비전',
    'category' => '공개페이지',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/contact', 'HomeController@webContact', [
    'key' => 'web.contact',
    'name' => '문의하기',
    'category' => '공개페이지',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/privacy', 'HomeController@webPrivacy', [
    'key' => 'web.privacy',
    'name' => '개인정보처리방침',
    'category' => '공개페이지',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/sitemap', 'HomeController@webSitemap', [
    'key' => 'web.sitemap',
    'name' => '사이트맵',
    'category' => '현장관리',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/approve_request', 'ApprovalController@webApproveRequest', [
    'key' => 'web.approve_request',
    'name' => '결재 요청',
    'category' => '전자결재',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/approve_result', 'ApprovalController@webApproveResult', [
    'key' => 'web.approve_result',
    'name' => '결재 결과',
    'category' => '전자결재',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->post('/approve_user', 'ApprovalController@apiApproveUser', [
    'key' => 'web.approve_user',
    'name' => '가입 승인',
    'category' => '전자결재',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/login', 'LoginController@webLoginPage', [
    'key' => 'web.login',
    'name' => '로그인',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
    'guest_only' => true,
]);

$router->get('/logout', 'LoginController@apiLogout', [
    'key' => 'web.logout',
    'name' => '로그아웃',
    'category' => '인증',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => true,
]);

$router->get('/find-id', 'PasswordController@webFindId', [
    'key' => 'web.find_id',
    'name' => '아이디 찾기',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/find-id/result', 'PasswordController@webFindIdResult', [
    'key' => 'web.find_id.result',
    'name' => '아이디 찾기 결과',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/find-password', 'PasswordController@webFindPassword', [
    'key' => 'web.find_password',
    'name' => '비밀번호 찾기',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/find-password/result', 'PasswordController@webFindPasswordResult', [
    'key' => 'web.find_password.result',
    'name' => '비밀번호 찾기 결과',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/register', 'RegisterController@webRegisterPage', [
    'key' => 'web.register',
    'name' => '회원가입',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
    'guest_only' => true,
]);

$router->get('/register_success', 'RegisterController@webRegisterSuccess', [
    'key' => 'web.register_success',
    'name' => '회원가입 완료',
    'category' => '공개페이지',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/waiting_approval', 'RegisterController@webWaitingApproval', [
    'key' => 'web.waiting_approval',
    'name' => '승인 대기',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
]);

$router->get('/2fa', 'TwoFactorController@webTwoFactor', [
    'key' => 'web.2fa',
    'name' => '2차 인증',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
    'allow_statuses' => ['2FA_PENDING'],
]);

$router->get('/password/change', 'PasswordController@webChangePassword', [
    'key' => 'web.password.change',
    'name' => '비밀번호 변경',
    'category' => '인증',
    'auth' => false,
    'permissions' => [],
    'log' => false,
    'skip' => true,
    'allow_statuses' => ['NORMAL', 'PASSWORD_EXPIRED'],
]);

$router->get('/profile', 'ProfileController@webProfile', [
    'key' => 'web.profile',
    'name' => '내 프로필',
    'category' => '사용자',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => true,
    'description' => '사용자 개인 프로필 페이지',
]);

$router->get('/dashboard', 'DashboardController@webDashboard', [
    'key' => 'web.dashboard',
    'name' => '대시보드',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => true,
    'description' => '대시보드 메인 화면 접근',
]);

$router->get('/dashboard/report', 'DashboardController@webReport', [
    'key' => 'web.dashboard.report',
    'name' => '통합보고서',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '대시보드 통합보고서 화면 접근',
]);

$router->get('/dashboard/calendar', 'DashboardController@webCalendar', [
    'key' => 'web.dashboard.calendar',
    'name' => '캘린더',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '대시보드 캘린더 화면 접근',
]);

$router->get('/dashboard/activity', 'DashboardController@webActivity', [
    'key' => 'web.dashboard.activity',
    'name' => '최근활동',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '대시보드 최근활동 화면 접근',
]);

$router->get('/dashboard/notifications', 'DashboardController@webNotifications', [
    'key' => 'web.dashboard.notifications',
    'name' => '공지사항',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '대시보드 공지사항 화면 접근',
]);

$router->get('/dashboard/kpi', 'DashboardController@webKpi', [
    'key' => 'web.dashboard.kpi',
    'name' => '실적현황',
    'category' => '대시보드',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '대시보드 실적현황 화면 접근',
]);

$router->get('/dashboard/settings', 'DashboardController@webSettings', [
    'key' => 'web.dashboard.settings',
    'name' => '설정',
    'category' => '설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '시스템 설정 메인 화면 접근',
]);

$router->get('/dashboard/settings/base-info/company', 'DashboardController@settingsBaseInfoCompany', [
    'key' => 'web.settings.base-info.company',
    'name' => '회사정보',
    'category' => '기초정보',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '회사 기본정보 설정 화면 접근',
]);

$router->get('/dashboard/settings/base-info/brand-logo', 'DashboardController@settingsBaseInfoBrand', [
    'key' => 'web.settings.base-info.brand_logo',
    'name' => '브랜드',
    'category' => '기초정보',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '브랜드 설정 화면 접근',
]);

$router->get('/dashboard/settings/base-info/cover', 'DashboardController@settingsBaseInfoCover', [
    'key' => 'web.settings.base-info.cover',
    'name' => '커버이미지',
    'category' => '기초정보',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '커버이미지 설정 화면 접근',
]);

$router->get('/dashboard/settings/base-info/clients', 'DashboardController@settingsBaseInfoClients', [
    'key' => 'web.settings.base-info.clients',
    'name' => '거래처',
    'category' => '기초정보',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '거래처 관리 화면 접근',
]);

$router->get('/dashboard/settings/base-info/projects', 'DashboardController@settingsBaseInfoProjects', [
    'key' => 'web.settings.base-info.projects',
    'name' => '프로젝트',
    'category' => '기초정보',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '프로젝트 관리 화면 접근',
]);

$router->get('/dashboard/settings/base-info/bank-accounts', 'DashboardController@settingsBaseInfoAccounts', [
    'key' => 'web.settings.base-info.accounts',
    'name' => '계좌',
    'category' => '기초정보',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '계좌 관리 화면 접근',
]);

$router->get('/dashboard/settings/base-info/cards', 'DashboardController@settingsBaseInfoCards', [
    'key' => 'web.settings.base-info.cards',
    'name' => '카드',
    'category' => '기초정보',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '카드 관리 화면 접근',
]);

$router->get('/dashboard/settings/organization/employees', 'DashboardController@settingsOrgEmployees', [
    'key' => 'web.settings.organization.employees',
    'name' => '직원',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '직원 관리 화면 접근',
]);

$router->get('/dashboard/settings/organization/departments', 'DashboardController@settingsOrgDepartments', [
    'key' => 'web.settings.organization.departments',
    'name' => '부서',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '부서 관리 화면 접근',
]);

$router->get('/dashboard/settings/organization/positions', 'DashboardController@settingsOrgPositions', [
    'key' => 'web.settings.organization.positions',
    'name' => '직책',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '직책 관리 화면 접근',
]);

$router->get('/dashboard/settings/organization/roles', 'DashboardController@settingsOrgRoles', [
    'key' => 'web.settings.organization.roles',
    'name' => '역할',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '역할 관리 화면 접근',
]);

$router->get('/dashboard/settings/organization/permissions', 'DashboardController@settingsOrgPermissions', [
    'key' => 'web.settings.organization.permissions',
    'name' => '권한',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '권한 관리 화면 접근',
]);

$router->get('/dashboard/settings/organization/role_permissions', 'DashboardController@settingsOrgRolePermissions', [
    'key' => 'web.settings.organization.role_permissions',
    'name' => '권한부여',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '권한 부여 설정 화면 접근',
]);

$router->get('/dashboard/settings/organization/approval', 'DashboardController@settingsOrgApproval', [
    'key' => 'web.settings.organization.approval',
    'name' => '결재템플릿',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '결재 템플릿 관리 화면 접근',
]);

$router->get('/dashboard/settings/system/site', 'DashboardController@settingsSystemSite', [
    'key' => 'web.settings.system.site',
    'name' => '사이트정보',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '사이트 설정 화면 접근',
]);

$router->get('/dashboard/settings/system/session', 'DashboardController@settingsSystemSession', [
    'key' => 'web.settings.system.session',
    'name' => '세션관리',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '세션 관리 화면 접근',
]);

$router->get('/dashboard/settings/system/security', 'DashboardController@settingsSystemSecurity', [
    'key' => 'web.settings.system.security',
    'name' => '보안정책',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '보안 정책 설정 화면 접근',
]);

$router->get('/dashboard/settings/system/api', 'DashboardController@settingsSystemApi', [
    'key' => 'web.settings.system.api',
    'name' => '외부연동 API',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '외부 API 설정 화면 접근',
]);

$router->get('/dashboard/settings/system/external_services', 'DashboardController@settingsSystemExternal', [
    'key' => 'web.settings.system.external_services',
    'name' => '외부서비스',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '외부 서비스 연동 설정 화면 접근',
]);

$router->get('/dashboard/settings/system/storage', 'DashboardController@settingsSystemStorage', [
    'key' => 'web.settings.system.storage',
    'name' => '파일저장소',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '파일 저장소 설정 화면 접근',
]);

$router->get('/dashboard/settings/system/databasebackup', 'DashboardController@settingsSystemBackup', [
    'key' => 'web.settings.system.databasebackup',
    'name' => '데이터백업',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '데이터 백업 설정 화면 접근',
]);

$router->get('/dashboard/settings/system/logs', 'DashboardController@settingsSystemLogs', [
    'key' => 'web.settings.system.logs',
    'name' => '로그관리',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '시스템 로그 관리 화면 접근',
]);

$router->get('/dashboard/settings/system/logs/download', 'SystemController@webLogDownload', [
    'key' => 'web.settings.system.logs.download',
    'name' => '로그 다운로드',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '선택한 시스템 로그 파일 다운로드',
]);

$router->get('/ledger', 'LedgerController@webIndex', [
    'key' => 'web.ledger',
    'name' => '회계관리대시보드',
    'category' => '회계관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => true,
    'description' => '회계관리 메인 대시보드 화면 접근',
]);

$router->get('/ledger/accounts', 'LedgerController@webAccount', [
    'key' => 'web.ledger.accounts',
    'name' => '계정과목',
    'category' => '회계관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => true,
    'description' => '회계관리 계정과목 화면 접근',
]);

$router->get('/ledger/journal', 'LedgerController@webJournal', [
    'key' => 'web.ledger.journal',
    'name' => '일반전표',
    'category' => '회계관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => true,
    'description' => '회계관리 일반전표 화면 접근',
]);

$router->get('/ledger/transaction', 'TransactionController@webLedgerTransaction', [
    'key' => 'web.ledger.transaction.index',
    'name' => '거래내역',
    'category' => '회계관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '회계관리 거래내역 화면 접근',
]);

$router->get('/ledger/transaction/create', 'TransactionController@webLedgerCreate', [
    'key' => 'web.ledger.transaction.create',
    'name' => '거래입력',
    'category' => '회계관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '회계관리 거래입력 화면 접근',
]);

$router->get('/site', 'SiteController@dashboard', [
    'key' => 'web.site.dashboard',
    'name' => '현장대시보드',
    'category' => '현장관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '현장관리 메인 화면 접근',
]);

$router->get('/site/transaction', 'TransactionController@webTransaction', [
    'key' => 'web.site.transaction.index',
    'name' => '거래내역',
    'category' => '현장관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '현장 거래내역 화면 접근',
]);

$router->get('/site/transaction/create', 'TransactionController@webCreate', [
    'key' => 'web.site.transaction.create',
    'name' => '거래입력',
    'category' => '현장관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '현장 거래입력 화면 접근',
]);

$router->get('/shop', 'ShopController@webIndex', [
    'key' => 'web.shop.index',
    'name' => '쇼핑몰관리',
    'category' => '쇼핑몰관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '쇼핑몰관리 메인 화면 접근',
]);

$router->get('/shop/products', 'ShopController@webProducts', [
    'key' => 'web.shop.products',
    'name' => '상품관리',
    'category' => '쇼핑몰관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '쇼핑몰 상품관리 화면 접근',
]);

$router->get('/shop/categories', 'ShopController@webCategories', [
    'key' => 'web.shop.categories',
    'name' => '카테고리관리',
    'category' => '쇼핑몰관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '쇼핑몰 카테고리관리 화면 접근',
]);

$router->get('/shop/orders', 'ShopController@webOrders', [
    'key' => 'web.shop.orders',
    'name' => '주문관리',
    'category' => '쇼핑몰관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '쇼핑몰 주문관리 화면 접근',
]);

$router->get('/shop/payments', 'ShopController@webPayments', [
    'key' => 'web.shop.payments',
    'name' => '결제관리',
    'category' => '쇼핑몰관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '쇼핑몰 결제관리 화면 접근',
]);

$router->get('/shop/settlement', 'ShopController@webSettlement', [
    'key' => 'web.shop.settlement',
    'name' => '매출/정산',
    'category' => '쇼핑몰관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '쇼핑몰 정산 화면 접근',
]);

$router->get('/institution', 'InstitutionController@webIndex', [
    'key' => 'web.institution.index',
    'name' => '대외기관업무',
    'category' => '대외기관업무',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '대외기관업무 메인 화면 접근',
]);

$router->get('/notice', 'NoticeController@webIndex', [
    'key' => 'web.notice.index',
    'name' => '공지/회의',
    'category' => '공지/회의',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '공지/회의 메인 화면 접근',
]);

$router->get('/document', 'DocumentController@webIndex', [
    'key' => 'web.document.index',
    'name' => '내부문서',
    'category' => '내부문서',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '내부문서 메인 화면 접근',
]);

$router->get('/document/file_register', 'DocumentController@webFileRegister', [
    'key' => 'web.document.file_register',
    'name' => '내부문서 등록',
    'category' => '내부문서',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '내부문서 등록 화면 접근',
]);

$router->get('/document/view', 'DocumentController@webView', [
    'key' => 'web.document.view',
    'name' => '내부문서 상세',
    'category' => '내부문서',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '내부문서 상세 화면 접근',
]);

$router->get('/document/edit', 'DocumentController@webEdit', [
    'key' => 'web.document.edit',
    'name' => '내부문서 수정',
    'category' => '내부문서',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '내부문서 수정 화면 접근',
]);

$router->get('/document/stats', 'DocumentController@webStats', [
    'key' => 'web.document.stats',
    'name' => '내부문서 통계',
    'category' => '내부문서',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '내부문서 통계 화면 접근',
]);

$router->get('/approval', 'ApprovalController@webIndex', [
    'key' => 'web.approval.index',
    'name' => '전자결재',
    'category' => '전자결재',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
    'skip' => false,
    'description' => '전자결재 메인 화면 접근',
]);

$router->get('/sukhyang', 'DocumentController@webIndex', [
    'key' => 'web.document.legacy_index',
    'name' => '내부문서',
    'category' => '내부문서',
    'auth' => true,
    'permissions' => ['view'],
    'log' => false,
    'skip' => false,
    'description' => '기존 sukhyang 경로 호환',
]);
