<?php
// 경로: PROJECT_ROOT . '/routes/web.php';
global $router;



//라우터 사용법 (key / name / category / menu / auth / log)   <<<< 핵심

// $router->get('/dashboard/settings/base-info/company', 'DashboardController@settingsBaseInfoCompany', [

//     /* =========================================================
//      * 🔑 기본 식별 정보 (필수)
//      * ========================================================= */
//     'key'         => 'web.settings.baseinfo.company', // 권한 고유 키 (DB와 연결)
//     'name'        => '회사정보',                      // 화면 표시 이름
//     'description' => '회사 기본정보 설정 화면 접근',   // 기능 설명
//     'category'    => '기초정보',                      // 권한/메뉴 그룹 분류


//     /* =========================================================
//      * 📂 메뉴/네비게이션 제어
//      * ========================================================= */
//     'menu'        => true,                    // 사이드바/메뉴에 노출 여부
//     'menu_order'  => 1,                       // 메뉴 정렬 순서
//     'menu_group'  => 'settings.baseinfo',     // 상위 그룹 (트리 구조용)
//     'menu_label'  => '회사정보',              // 메뉴 표시 텍스트
//     'menu_icon'   => 'fa-building',           // 아이콘 (FontAwesome 등)
//     'menu_visible'=> true,                    // 메뉴 표시 여부 (권한과 별개)
//     'menu_badge'  => null,                    // 배지 (예: NEW, 3 등)


//     /* =========================================================
//      * 🔐 인증 / 권한 / 접근 제어
//      * ========================================================= */
//     'auth'        => true,                    // 로그인 필요 여부
//     'guest_only'  => false,                   // 비로그인 전용 페이지 여부
//     'roles'       => ['admin', 'manager'],    // 접근 가능한 역할
//     'permissions' => ['view'],                // 세부 권한 타입 (view/save/delete 등)
//     'policy'      => null,                    // 정책 클래스 (Laravel 스타일 가능)
//     'ip_whitelist'=> [],                      // 특정 IP만 허용
//     'ip_blacklist'=> [],                      // 특정 IP 차단


//     /* =========================================================
//      * ⚙️ 요청 제어 / 보안
//      * ========================================================= */
//     'methods'     => ['GET'],                 // 허용 HTTP 메서드
//     'ajax_only'   => false,                   // AJAX 요청만 허용 여부
//     'csrf'        => true,                    // CSRF 체크 여부
//     'throttle'    => '60,1',                  // rate limit (60회/1분)
//     'timeout'     => 10,                      // 요청 타임아웃 (초)


//     /* =========================================================
//      * 🧠 페이지 동작 / UI 설정
//      * ========================================================= */
//     'layout'      => 'dashboard',             // 레이아웃 선택
//     'breadcrumb'  => true,                    // breadcrumb 자동 생성
//     'page_title'  => '회사정보 설정',          // 브라우저 타이틀
//     'page_class'  => 'settings-company',      // body class
//     'container'   => 'fluid',                 // container 타입


//     /* =========================================================
//      * 📦 리소스 (JS / CSS)
//      * ========================================================= */
//     'assets' => [
//         'css' => [
//             '/assets/css/pages/dashboard/settings/company.css'
//         ],
//         'js' => [
//             '/assets/js/pages/dashboard/settings/base/company.js'
//         ],
//         'module' => [
//             // ES Module 필요 시
//         ]
//     ],


//     /* =========================================================
//      * 📊 로깅 / 감사 (ERP 핵심)
//      * ========================================================= */
//     'log'         => true,                    // 접근 로그 기록 여부
//     'log_action'  => 'VIEW_COMPANY_SETTINGS',// 로그 액션명
//     'audit'       => true,                    // 감사 로그 여부
//     'audit_level' => 'high',                  // low / medium / high


//     /* =========================================================
//      * 🧩 기능 플래그 (Feature Toggle)
//      * ========================================================= */
//     'feature'     => 'company_settings',      // 기능 식별자
//     'enabled'     => true,                    // 기능 활성화 여부
//     'beta'        => false,                   // 베타 기능 여부


//     /* =========================================================
//      * 🌍 다국어 / i18n
//      * ========================================================= */
//     'title_key'       => 'settings.company.title',
//     'description_key' => 'settings.company.description',
//     'lang'            => 'ko',


//     /* =========================================================
//      * 🔗 API / 연동 관련
//      * ========================================================= */
//     'api' => [
//         'enabled' => true,
//         'prefix'  => '/api/settings/base-info/company',
//         'version' => 'v1'
//     ],


//     /* =========================================================
//      * 🧬 확장 / 커스터마이징
//      * ========================================================= */
//     'middleware' => ['auth', 'permission', 'logging'], // 실행 미들웨어
//     'hooks' => [
//         'before' => null, // 실행 전 콜백
//         'after'  => null  // 실행 후 콜백
//     ],


//     /* =========================================================
//      * 💾 캐싱
//      * ========================================================= */
//     'cache' => [
//         'enabled' => false,
//         'ttl'     => 300, // seconds
//     ],


//     /* =========================================================
//      * 🧪 개발/디버그 옵션
//      * ========================================================= */
//     'debug' => [
//         'enabled' => false,
//         'log_sql' => false,
//         'log_time'=> false
//     ]
// ]);

















// ==============================
// 에러 페이지 라우트
// ==============================
$router->get('/error', 'ErrorController@forbidden');

// 세션 자동 로그아웃 관련
$router->get('/autologout/keepalive', 'SessionController@apiKeepalive');
$router->get('/autologout/extend', 'SessionController@webExtendView');
$router->get('/autologout/expired', 'SessionController@webExpired');

/* =========================================================
 * 공개 페이지 (홈, 소개, 문의, 정책)
 * → ★ 퍼미션 체크 제외해야 함 ★
 * ========================================================= */
// 홈
$router->get('/', 'HomeController@webRoot');
// 홈 인덱스
$router->get('/home', 'HomeController@webIndex');
// 회사 소개
$router->get('/about', 'AboutController@webAbout');
// 관리자 회사 소개 (이건 내부이므로 제외 X)
$router->get('/admin/about', 'AboutController@webAdminAbout', [
    'key' => 'web.admin.about.view',
    'name' => '관리자 회사 소개',
    'description' => '관리자용 회사 소개 페이지 접근',
    'category' => '관리자'
]);
// 기업 비전
$router->get('/vision', 'HomeController@webVision');
// 문의하기
$router->get('/contact', 'HomeController@webContact');
// 개인정보 처리방침
$router->get('/privacy', 'HomeController@webPrivacy');
// 사이트맵
$router->get('/sitemap', 'HomeController@webSitemap');



/* =========================================================
 * 승인(Approval) 관련 WEB 페이지 (★ 퍼미션 체크 제외)
 * ========================================================= */
// 승인 요청 페이지
$router->get('/approve_request', 'ApprovalController@webApproveRequest');
// 승인 결과 페이지
$router->get('/approve_result', 'ApprovalController@webApproveResult');
/* =========================================================
 * 승인(Approval) 처리 (POST) — 퍼미션 체크 제외
 * ========================================================= */
$router->post('/approve_user', 'ApprovalController@apiApproveUser');



/* =========================================================
 * 인증 관련 WEB 페이지 (★ 모두 퍼미션 체크 제외)
 * 로그인 필요 없이 접근해야 하는 공개 페이지들
 * ========================================================= */
/* 로그인 페이지 */
$router->get('/login', 'LoginController@webLoginPage');
/* 로그아웃웃 페이지 */
$router->get('/logout', 'LoginController@apiLogout');
/* 아이디 찾기 페이지 */
$router->get('/find-id', 'PasswordController@webFindId');
/* 아이디 찾기 결과 페이지 */
$router->get('/find-id/result', 'PasswordController@webFindIdResult');
/* 비밀번호 찾기 페이지 */
$router->get('/find-password', 'PasswordController@webFindPassword');
/* 비밀번호 찾기 결과 페이지 */
$router->get('/find-password/result', 'PasswordController@webFindPasswordResult');
/* 회원가입 페이지 */
$router->get('/register', 'RegisterController@webRegisterPage');
/* 회원가입 성공 안내 페이지 */
$router->get('/register_success', 'RegisterController@webRegisterSuccess');
/* 회원가입 승인 대기 안내 페이지 */
$router->get('/waiting_approval', 'RegisterController@webWaitingApproval');
/* 2차 인증(OTP) 입력 페이지 */
$router->get('/2fa', 'TwoFactorController@webTwoFactor');
// 비밀번호 만료 → 변경 페이지
$router->get('/password/change', 'PasswordController@webChangePassword');

//프로필페이지
$router->get('/profile', 'ProfileController@webProfile', [
    'key'         => 'web.profile.view',
    'name'        => '내 프로필',
    'description' => '사용자 개인 프로필 페이지',
    'category'    => '사용자'
]);



/* =========================================================
 * Dashboard
 * ========================================================= */
/* 로그인후 대시보드 페이지 */
$router->get('/dashboard', 'DashboardController@webDashboard');


$router->get('/dashboard/report', 'DashboardController@webReport', [
    'key' => 'web.dashboard.report',
    'name' => '보고서',
    'description' => '통합 보고서 화면 접근',
    'category' => '대시보드'
]);

$router->get('/dashboard/calendar', 'DashboardController@webCalendar', [
    'key' => 'web.dashboard.calendar',
    'name' => '캘린더',
    'description' => '캘린더 접근',
    'category' => '대시보드'
]);

$router->get('/dashboard/activity', 'DashboardController@webActivity', [
    'key' => 'web.dashboard.activity',
    'name' => '최근 활동',
    'description' => '활동 로그 조회',
    'category' => '대시보드'
]);

$router->get('/dashboard/notifications', 'DashboardController@webNotifications', [
    'key' => 'web.dashboard.notifications',
    'name' => '알림 목록',
    'description' => '알림 화면 접근',
    'category' => '대시보드'
]);

$router->get('/dashboard/kpi', 'DashboardController@webKpi', [
    'key' => 'web.dashboard.kpi',
    'name' => 'KPI',
    'description' => 'KPI 화면 접근',
    'category' => '대시보드'
]);










$router->get('/dashboard/settings', 'DashboardController@webSettings', [
    'key' => 'web.dashboard.settings',
    'name' => '설정 메인',
    'description' => '설정 페이지 접근',
    'category' => '설정'
]);



/* =========================================================
 * 설정: 기초정보관리>회사정보, 브랜드, 커버이미지, 거래처, 프로젝트
 * ========================================================= */

 $router->get('/dashboard/settings/base-info/company', 'DashboardController@settingsBaseInfoCompany', [
    'key'         => 'web.settings.base-info.company',
    'name'        => '회사정보',
    'description' => '회사 기본정보 설정 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/brand-logo', 'DashboardController@settingsBaseInfoBrand', [
    'key'         => 'web.settings.base-info.brand_logo',
    'name'        => '브랜드',
    'description' => '브랜드 설정 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/cover', 'DashboardController@settingsBaseInfoCover', [
    'key'         => 'web.settings.base-info.cover',
    'name'        => '커버이미지',
    'description' => '커버이미지 설정 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/clients', 'DashboardController@settingsBaseInfoClients', [
    'key'         => 'web.settings.base-info.clients',
    'name'        => '거래처',
    'description' => '거래처 관리 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/projects', 'DashboardController@settingsBaseInfoProjects', [
    'key'         => 'web.settings.base-info.projects',
    'name'        => '프로젝트',
    'description' => '프로젝트 관리 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/bank-accounts', 'DashboardController@settingsBaseInfoAccounts', [
    'key'         => 'web.settings.base-info.accounts',
    'name'        => '계좌',
    'description' => '계좌 관리 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/cards', 'DashboardController@settingsBaseInfoCards', [
    'key'         => 'web.settings.base-info.cards',
    'name'        => '카드',
    'description' => '카드 관리 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);





/* =========================================================
 * 설정: 조직관리>직원, 부서, 직책, 역할, 권한, 권한부여, 결재템플릿
 * ========================================================= */

$router->get('/dashboard/settings/organization/employees', 'DashboardController@settingsOrgEmployees', [
    'key' => 'web.settings.organization.employees',
    'name' => '직원',
    'description' => '직원 관리 화면 접근',
    'category' => '조직관리'
]);

$router->get('/dashboard/settings/organization/departments', 'DashboardController@settingsOrgDepartments', [
    'key' => 'web.settings.organization.departments',
    'name' => '부서',
    'description' => '부서 관리 화면 접근',
    'category' => '조직관리'
]);

$router->get('/dashboard/settings/organization/positions', 'DashboardController@settingsOrgPositions', [
    'key' => 'web.settings.organization.positions',
    'name' => '직책',
    'description' => '직책 관리 화면 접근',
    'category' => '조직관리'
]);

$router->get('/dashboard/settings/organization/roles', 'DashboardController@settingsOrgRoles', [
    'key' => 'web.settings.organization.roles',
    'name' => '역할',
    'description' => '역할 관리 화면 접근',
    'category' => '조직관리'
]);

$router->get('/dashboard/settings/organization/permissions', 'DashboardController@settingsOrgPermissions', [
    'key' => 'web.settings.organization.permissions',
    'name' => '권한',
    'description' => '권한 관리 화면 접근',
    'category' => '조직관리'
]);

$router->get('/dashboard/settings/organization/role_permissions', 'DashboardController@settingsOrgRolePermissions', [
    'key' => 'web.settings.organization.role_permissions',
    'name' => '권한부여',
    'description' => '권한 부여 설정 화면 접근',
    'category' => '조직관리'
]);

$router->get('/dashboard/settings/organization/approval', 'DashboardController@settingsOrgApproval', [
    'key' => 'web.settings.organization.approval',
    'name' => '결재템플릿',
    'description' => '결재 템플릿 관리 화면 접근',
    'category' => '조직관리'
]);


/* =========================================================
 * 설정: 시스템설정>사이트정보, 세션관리, 보안정책, 외부연동(API), 외부서비스연동, 파일저장소, 데이터백업, 시스템로그
 * ========================================================= */
$router->get('/dashboard/settings/system/site', 'DashboardController@settingsSystemSite', [
    'key' => 'web.settings.system.site',
    'name' => '사이트정보',
    'description' => '사이트 설정 화면 접근',
    'category' => '시스템설정'
]);

$router->get('/dashboard/settings/system/session', 'DashboardController@settingsSystemSession', [
    'key' => 'web.settings.system.session',
    'name' => '세션관리',
    'description' => '세션 관리 화면 접근',
    'category' => '시스템설정'
]);

$router->get('/dashboard/settings/system/security', 'DashboardController@settingsSystemSecurity', [
    'key' => 'web.settings.system.security',
    'name' => '보안정책',
    'description' => '보안 정책 설정 화면 접근',
    'category' => '시스템설정'
]);

$router->get('/dashboard/settings/system/api', 'DashboardController@settingsSystemApi', [
    'key' => 'web.settings.system.api',
    'name' => '외부연동 API',
    'description' => '외부 API 설정 화면 접근',
    'category' => '시스템설정'
]);

$router->get('/dashboard/settings/system/external_services', 'DashboardController@settingsSystemExternal', [
    'key' => 'web.settings.system.external_services',
    'name' => '외부서비스',
    'description' => '외부 서비스 연동 설정 화면 접근',
    'category' => '시스템설정'
]);

$router->get('/dashboard/settings/system/storage', 'DashboardController@settingsSystemStorage', [
    'key' => 'web.settings.system.storage',
    'name' => '파일저장소',
    'description' => '파일 저장소 설정 화면 접근',
    'category' => '시스템설정'
]);

$router->get('/dashboard/settings/system/databasebackup', 'DashboardController@settingsSystemBackup', [
    'key' => 'web.settings.system.databasebackup',
    'name' => '데이터백업',
    'description' => '데이터 백업 설정 화면 접근',
    'category' => '시스템설정'
]);

$router->get('/dashboard/settings/system/logs', 'DashboardController@settingsSystemLogs', [
    'key' => 'web.settings.system.logs',
    'name' => '로그관리',
    'description' => '시스템 로그 관리 화면 접근',
    'category' => '시스템설정'
]);
























/* =========================================================
 * 거래원장 (Ledger) WEB 페이지
 * ========================================================= */
 $router->get('/ledger', 'LedgerController@webIndex', [
    'key'         => 'web.ledger.index.view',
    'name'        => '거래원장 대시보드',
    'description' => '거래원장 메인 대시보드',
    'category'    => '거래원장'
]);



/* =========================================================
 * 계정과목 관리
 * ========================================================= */
$router->get('/ledger/accounts', 'LedgerController@webAccount', [
    'key'         => 'web.ledger.account.view',
    'name'        => '계정과목 관리',
    'description' => '계정과목 관리 페이지',
    'category'    => '거래원장'
]);


/* =========================================================
 * 전표 입력
 * ========================================================= */
$router->get('/ledger/journal', 'LedgerController@webJournal', [
    'key'         => 'web.ledger.journal.view',
    'name'        => '일반전표입력',
    'description' => '일반전표 입력 화면 접근',
    'category'    => '거래원장'
]);


