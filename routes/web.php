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
















/* =========================================
 * 시스템 > 에러 및 세션 관리 라우트
 * ========================================= */

// ============================================================
// 접근 제한 페이지
// ============================================================
$router->get('/error', 'ErrorController@forbidden', [
    'key'         => 'web.system.error.forbidden',
    'name'        => '접근 제한 페이지',
    'description' => '권한이 없는 접근 시 표시되는 페이지',
    'category'    => '시스템',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 세션 유지 (Keepalive API)
// ============================================================
$router->get('/autologout/keepalive', 'SessionController@apiKeepalive', [
    'key'         => 'api.system.session.keepalive',
    'name'        => '세션 유지',
    'description' => '세션 유지 요청 (자동 로그아웃 방지)',
    'category'    => '시스템',
    'auth'        => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 세션 연장 안내 페이지
// ============================================================
$router->get('/autologout/extend', 'SessionController@webExtendView', [
    'key'         => 'web.system.session.extend',
    'name'        => '세션 연장 페이지',
    'description' => '세션 만료 전 연장 안내 화면',
    'category'    => '시스템',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 세션 만료 페이지
// ============================================================
$router->get('/autologout/expired', 'SessionController@webExpired', [
    'key'         => 'web.system.session.expired',
    'name'        => '세션 만료 페이지',
    'description' => '세션 만료 후 표시되는 화면',
    'category'    => '시스템',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);













/* =========================================================
 * 공개 페이지 (홈, 소개, 문의, 정책)
 * → 퍼미션 체크 제외 (auth: false)
 * ========================================================= */

// ============================================================
// 홈 (루트)
// ============================================================
$router->get('/', 'HomeController@webRoot', [
    'key'         => 'web.public.home.root',
    'name'        => '홈 (루트)',
    'description' => '메인 홈 페이지',
    'category'    => '공개페이지',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 홈 인덱스
// ============================================================
$router->get('/home', 'HomeController@webIndex', [
    'key'         => 'web.public.home.index',
    'name'        => '홈',
    'description' => '메인 홈 페이지',
    'category'    => '공개페이지',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 회사 소개
// ============================================================
$router->get('/about', 'AboutController@webAbout', [
    'key'         => 'web.public.about',
    'name'        => '회사 소개',
    'description' => '회사 소개 페이지',
    'category'    => '공개페이지',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 기업 비전
// ============================================================
$router->get('/vision', 'HomeController@webVision', [
    'key'         => 'web.public.vision',
    'name'        => '기업 비전',
    'description' => '기업 비전 소개 페이지',
    'category'    => '공개페이지',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 문의하기
// ============================================================
$router->get('/contact', 'HomeController@webContact', [
    'key'         => 'web.public.contact',
    'name'        => '문의하기',
    'description' => '문의 페이지',
    'category'    => '공개페이지',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 개인정보 처리방침
// ============================================================
$router->get('/privacy', 'HomeController@webPrivacy', [
    'key'         => 'web.public.privacy',
    'name'        => '개인정보 처리방침',
    'description' => '개인정보 처리방침 페이지',
    'category'    => '공개페이지',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 사이트맵
// ============================================================
$router->get('/sitemap', 'HomeController@webSitemap', [
    'key'         => 'web.public.sitemap',
    'name'        => '사이트맵',
    'description' => '사이트 구조 안내 페이지',
    'category'    => '공개페이지',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

/* =========================================================
 * 관리자 페이지 (인증 필요)
 * ========================================================= */

// ============================================================
// 관리자 회사 소개
// ============================================================
$router->get('/admin/about', 'AboutController@webAdminAbout', [
    'key'         => 'web.admin.about.view',
    'name'        => '관리자 회사 소개',
    'description' => '관리자용 회사 소개 페이지',
    'category'    => '관리자',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);












/* =========================================================
 * 인증 > 로그인 및 계정 관련 페이지 (공개 접근)
 * ========================================================= */

// ============================================================
// 로그인 페이지
// ============================================================
$router->get('/login', 'LoginController@webLoginPage', [
    'key'         => 'web.auth.login',
    'name'        => '로그인',
    'description' => '사용자 로그인 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 로그아웃
// ============================================================
$router->get('/logout', 'LoginController@apiLogout', [
    'key'         => 'web.auth.logout',
    'name'        => '로그아웃',
    'description' => '사용자 로그아웃 처리',
    'category'    => '인증',
    'auth'        => true,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => true,
]);

$router->get('/auth/logout', 'LoginController@apiLogout', [
    'key'         => 'web.auth.logout.alias',
    'name'        => '로그아웃',
    'description' => '사용자 로그아웃 처리 호환 경로',
    'category'    => '인증',
    'auth'        => true,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => true,
]);

// ============================================================
// 아이디 찾기
// ============================================================
$router->get('/find-id', 'PasswordController@webFindId', [
    'key'         => 'web.auth.find_id',
    'name'        => '아이디 찾기',
    'description' => '아이디 찾기 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 아이디 찾기 결과
// ============================================================
$router->get('/find-id/result', 'PasswordController@webFindIdResult', [
    'key'         => 'web.auth.find_id_result',
    'name'        => '아이디 찾기 결과',
    'description' => '아이디 찾기 결과 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 비밀번호 찾기
// ============================================================
$router->get('/find-password', 'PasswordController@webFindPassword', [
    'key'         => 'web.auth.find_password',
    'name'        => '비밀번호 찾기',
    'description' => '비밀번호 찾기 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 비밀번호 찾기 결과
// ============================================================
$router->get('/find-password/result', 'PasswordController@webFindPasswordResult', [
    'key'         => 'web.auth.find_password_result',
    'name'        => '비밀번호 찾기 결과',
    'description' => '비밀번호 찾기 결과 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 회원가입
// ============================================================
$router->get('/register', 'RegisterController@webRegisterPage', [
    'key'         => 'web.auth.register',
    'name'        => '회원가입',
    'description' => '회원가입 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 회원가입 완료
// ============================================================
$router->get('/register_success', 'RegisterController@webRegisterSuccess', [
    'key'         => 'web.auth.register_success',
    'name'        => '회원가입 완료',
    'description' => '회원가입 완료 안내 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 승인 대기 페이지
// ============================================================
$router->get('/waiting_approval', 'RegisterController@webWaitingApproval', [
    'key'         => 'web.auth.waiting_approval',
    'name'        => '승인 대기',
    'description' => '관리자 승인 대기 안내 페이지',
    'category'    => '인증',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);




// ============================================================
// 회원가입 승인 요청 페이지
// ============================================================
$router->get('/auth/approval/request', 'UserApprovalController@webApproveRequest', [
    'key'             => 'web.approval.request.view',
    'name'            => '승인 요청 페이지',
    'description'     => '회원가입 승인 요청 정보를 조회하는 페이지',
    'category'        => '인증',
    'auth'            => false,
    'skip_permission' => true,
    'log'             => false,
]);

// ============================================================
// 회원가입 승인 결과 페이지
// ============================================================
$router->get('/auth/approval/result', 'UserApprovalController@webApproveResult', [
    'key'             => 'web.approval.result.view',
    'name'            => '승인 결과 페이지',
    'description'     => '회원가입 승인 처리 결과를 표시하는 페이지',
    'category'        => '인증',
    'auth'            => false,
    'skip_permission' => true,
    'log'             => false,
]);

// ============================================================
// 2차 인증 페이지
// ============================================================
$router->get('/2fa', 'TwoFactorController@webTwoFactor', [
    'key'             => 'web.auth.2fa',
    'name'            => '2차 인증',
    'description'     => 'OTP 2차 인증 입력 페이지',
    'category'        => '인증',
    'auth'            => false,
    'allow_statuses'  => ['2FA_PENDING'],
    'skip_permission' => true,
    'permissions'     => [],
    'log'             => false,
]);

// ============================================================
// 비밀번호 변경 (만료 포함)
// ============================================================
$router->get('/password/change', 'PasswordController@webChangePassword', [
    'key'             => 'web.auth.password_change',
    'name'            => '비밀번호 변경',
    'description'     => '비밀번호 변경 페이지',
    'category'        => '인증',
    'auth'            => false,
    'allow_statuses'  => ['NORMAL', 'PASSWORD_EXPIRED'],
    'skip_permission' => true,
    'permissions'     => [],
    'log'             => false,
]);










/* =========================================================
 * 대시보드 WEB 페이지
 * ========================================================= */

// ============================================================
// 대시보드 메인
// ============================================================
$router->get('/dashboard', 'DashboardController@webDashboard', [
    'key'         => 'web.dashboard.main',
    'name'        => '대시보드',
    'description' => '대시보드 메인 화면',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 통합 보고서
// ============================================================
$router->get('/dashboard/report', 'DashboardController@webReport', [
    'key'         => 'web.dashboard.report',
    'name'        => '통합보고서',
    'description' => '대시보드 통합보고서 화면',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 캘린더
// ============================================================
$router->get('/dashboard/calendar', 'DashboardController@webCalendar', [
    'key'         => 'web.dashboard.calendar',
    'name'        => '캘린더',
    'description' => '대시보드 캘린더 화면',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 최근 활동
// ============================================================
$router->get('/dashboard/activity', 'DashboardController@webActivity', [
    'key'         => 'web.dashboard.activity',
    'name'        => '최근활동',
    'description' => '최근 활동 로그 화면',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 공지사항
// ============================================================
$router->get('/dashboard/notifications', 'DashboardController@webNotifications', [
    'key'         => 'web.dashboard.notifications',
    'name'        => '공지사항',
    'description' => '공지사항 화면',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// KPI / 실적현황
// ============================================================
$router->get('/dashboard/kpi', 'DashboardController@webKpi', [
    'key'         => 'web.dashboard.kpi',
    'name'        => '실적현황',
    'description' => 'KPI 및 실적현황 화면',
    'category'    => '대시보드',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);



// ============================================================
// 설정 메인 페이지
// ============================================================
$router->get('/dashboard/settings', 'DashboardController@webSettings', [
    'key'         => 'web.dashboard.settings',
    'name'        => '설정',
    'description' => '시스템 설정 메인 화면 접근',
    'category'    => '설정',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
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

$router->get('/dashboard/settings/base-info/codes', 'DashboardController@settingsBaseInfoCodes', [
    'key'         => 'code.view',
    'name'        => '기준정보',
    'description' => '기준정보 관리 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/code', 'DashboardController@settingsBaseInfoCodes', [
    'key'         => 'code.view',
    'name'        => '기준정보',
    'description' => '기준정보 관리 화면 접근',
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

$router->get('/dashboard/settings/base-info/work-teams', 'DashboardController@settingsBaseInfoWorkTeams', [
    'key'         => 'work_team.view',
    'name'        => '팀',
    'description' => '작업팀 관리 화면 접근',
    'category'    => '기초정보',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/work-team', 'DashboardController@settingsBaseInfoWorkTeams', [
    'key'         => 'work_team.view',
    'name'        => '팀',
    'description' => '작업팀 관리 화면 접근',
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
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/departments', 'DashboardController@settingsOrgDepartments', [
    'key' => 'web.settings.organization.departments',
    'name' => '부서',
    'description' => '부서 관리 화면 접근',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/positions', 'DashboardController@settingsOrgPositions', [
    'key' => 'web.settings.organization.positions',
    'name' => '직책',
    'description' => '직책 관리 화면 접근',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/roles', 'DashboardController@settingsOrgRoles', [
    'key' => 'web.settings.organization.roles',
    'name' => '역할',
    'description' => '역할 관리 화면 접근',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/role_permissions', 'DashboardController@settingsOrgRolePermissions', [
    'key' => 'web.settings.organization.role_permissions',
    'name' => '권한부여',
    'description' => '권한 부여 설정 화면 접근',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/approval', 'DashboardController@settingsOrgApproval', [
    'key' => 'web.settings.organization.approval',
    'name' => '결재템플릿',
    'description' => '결재 템플릿 관리 화면 접근',
    'category' => '조직관리',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);


/* =========================================================
 * 설정: 시스템설정>사이트정보, 세션관리, 보안정책, 외부연동(API), 외부서비스연동, 파일저장소, 데이터백업, 시스템로그
 * ========================================================= */
$router->get('/dashboard/settings/system/site', 'DashboardController@settingsSystemSite', [
    'key' => 'web.settings.system.site',
    'name' => '사이트정보',
    'description' => '사이트 설정 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/session', 'DashboardController@settingsSystemSession', [
    'key' => 'web.settings.system.session',
    'name' => '세션관리',
    'description' => '세션 관리 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/security', 'DashboardController@settingsSystemSecurity', [
    'key' => 'web.settings.system.security',
    'name' => '보안정책',
    'description' => '보안 정책 설정 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/api', 'DashboardController@settingsSystemApi', [
    'key' => 'web.settings.system.api',
    'name' => '외부연동 API',
    'description' => '외부 API 설정 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/external_services', 'DashboardController@settingsSystemExternal', [
    'key' => 'web.settings.system.external_services',
    'name' => '외부서비스',
    'description' => '외부 서비스 연동 설정 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/storage', 'DashboardController@settingsSystemStorage', [
    'key' => 'web.settings.system.storage',
    'name' => '파일저장소',
    'description' => '파일 저장소 설정 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/databasebackup', 'DashboardController@settingsSystemBackup', [
    'key' => 'web.settings.system.databasebackup',
    'name' => '데이터백업',
    'description' => '데이터 백업 설정 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/logs', 'DashboardController@settingsSystemLogs', [
    'key' => 'web.settings.system.logs',
    'name' => '로그관리',
    'description' => '시스템 로그 관리 화면 접근',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/logs/download', 'SystemController@webLogDownload', [
    'key' => 'web.settings.system.logs.download',
    'name' => '로그 다운로드',
    'description' => '선택한 시스템 로그 파일 다운로드',
    'category' => '시스템설정',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);




// ============================================================
// 내부문서
// ============================================================

// 내부문서 메인
$router->get('/document', 'DocumentController@webIndex', [
    'key'         => 'web.document.index',
    'name'        => '내부문서',
    'description' => '내부문서 메인 화면',
    'category'    => '내부문서',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 문서 등록
$router->get('/document/file_register', 'DocumentController@webFileRegister', [
    'key'         => 'web.document.file_register',
    'name'        => '문서 등록',
    'description' => '내부문서 등록 화면',
    'category'    => '내부문서',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 문서 상세
$router->get('/document/view', 'DocumentController@webView', [
    'key'         => 'web.document.view',
    'name'        => '문서 상세',
    'description' => '내부문서 상세 화면',
    'category'    => '내부문서',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 문서 수정
$router->get('/document/edit', 'DocumentController@webEdit', [
    'key'         => 'web.document.edit',
    'name'        => '문서 수정',
    'description' => '내부문서 수정 화면',
    'category'    => '내부문서',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 문서 통계
$router->get('/document/stats', 'DocumentController@webStats', [
    'key'         => 'web.document.stats',
    'name'        => '문서 통계',
    'description' => '내부문서 통계 화면',
    'category'    => '내부문서',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);











/* =========================================================
 * 전자결재 > 외부 승인 페이지 (토큰 기반 접근)
 * ========================================================= */
$router->get('/approval', 'ApprovalController@webIndex', [
    'key'         => 'web.approval.index',
    'name'        => '전자결재',
    'description' => '전자결재 메인 화면',
    'category'    => '전자결재',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);







/* =========================================================
 * 회계관리 (Ledger) WEB 페이지
 * ========================================================= */

// ============================================================
// 회계관리 대시보드
// ============================================================
$router->get('/ledger', 'LedgerController@webIndex', [
    'key'         => 'web.ledger.dashboard',
    'name'        => '회계관리대시보드',
    'description' => '회계관리 메인 대시보드 화면',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 계정과목 관리
 * ========================================================= */

// ============================================================
// 계정과목 화면
// ============================================================
$router->get('/ledger/accounts', 'LedgerController@webAccount', [
    'key'         => 'web.ledger.accounts',
    'name'        => '계정과목',
    'description' => '회계관리 계정과목 화면',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);



/* =========================================================
 * 전표 입력 / 거래 관리 WEB 페이지
 * ========================================================= */

// ============================================================
// 일반전표 (전표 입력)
// ============================================================
$router->get('/ledger/journal', 'LedgerController@webJournal', [
    'key'         => 'web.ledger.journal',
    'name'        => '일반전표',
    'description' => '회계관리 일반전표 입력 화면',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 거래내역
// ============================================================
$router->get('/ledger/transaction', 'TransactionController@webLedgerTransaction', [
    'key'         => 'web.ledger.transaction.index',
    'name'        => '거래내역',
    'description' => '회계관리 거래내역 화면',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 거래 입력
// ============================================================
$router->get('/ledger/transaction/create', 'TransactionController@webLedgerCreate', [
    'key'         => 'web.ledger.transaction.create',
    'name'        => '거래입력',
    'description' => '회계관리 거래 입력 화면',
    'category'    => '회계관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);











// ============================================================
// 대외 기관업무
// ============================================================

$router->get('/institution', 'InstitutionController@webIndex', [
    'key' => 'web.institution.index',
    'name' => '대외기관업무',
    'description' => '대외기관업무 메인 화면 접근',
    'category' => '대외기관업무',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);









/* =========================================================
 * 현장관리 > 입력 데이터 (거래 원본)
 * ========================================================= */

// ============================================================
// 현장 입력 목록
// ============================================================
$router->get('/site/entry', 'TransactionController@webTransaction', [
    'key'         => 'web.site.entry.index',
    'name'        => '현장입력내역',
    'description' => '현장 입력 데이터 목록 화면',
    'category'    => '현장관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 현장 입력
// ============================================================
$router->get('/site/entry/create', 'TransactionController@webCreate', [
    'key'         => 'web.site.entry.create',
    'name'        => '현장입력',
    'description' => '현장 입력 데이터 등록 화면',
    'category'    => '현장관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 현장 대시보드
// ============================================================
$router->get('/site', 'SiteController@dashboard', [
    'key'         => 'web.site.dashboard',
    'name'        => '현장대시보드',
    'description' => '현장관리 메인 화면',
    'category'    => '현장관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);





/* =========================================================
 * 쇼핑몰관리 WEB 페이지
 * ========================================================= */

// ============================================================
// 쇼핑몰 대시보드
// ============================================================
$router->get('/shop', 'ShopController@webIndex', [
    'key'         => 'web.shop.index',
    'name'        => '쇼핑몰관리',
    'description' => '쇼핑몰관리 메인 화면',
    'category'    => '쇼핑몰관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 상품관리
// ============================================================
$router->get('/shop/products', 'ShopController@webProducts', [
    'key'         => 'web.shop.products',
    'name'        => '상품관리',
    'description' => '쇼핑몰 상품관리 화면',
    'category'    => '쇼핑몰관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 카테고리관리
// ============================================================
$router->get('/shop/categories', 'ShopController@webCategories', [
    'key'         => 'web.shop.categories',
    'name'        => '카테고리관리',
    'description' => '쇼핑몰 카테고리관리 화면',
    'category'    => '쇼핑몰관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 주문관리
// ============================================================
$router->get('/shop/orders', 'ShopController@webOrders', [
    'key'         => 'web.shop.orders',
    'name'        => '주문관리',
    'description' => '쇼핑몰 주문관리 화면',
    'category'    => '쇼핑몰관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 결제관리
// ============================================================
$router->get('/shop/payments', 'ShopController@webPayments', [
    'key'         => 'web.shop.payments',
    'name'        => '결제관리',
    'description' => '쇼핑몰 결제관리 화면',
    'category'    => '쇼핑몰관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 매출/정산
// ============================================================
$router->get('/shop/settlement', 'ShopController@webSettlement', [
    'key'         => 'web.shop.settlement',
    'name'        => '매출/정산',
    'description' => '쇼핑몰 정산 화면',
    'category'    => '쇼핑몰관리',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);




// ============================================================
// 공지/회의
// ============================================================
$router->get('/notice', 'NoticeController@webIndex', [
    'key'         => 'web.notice.index',
    'name'        => '공지/회의',
    'description' => '공지 및 회의 관리 메인 화면',
    'category'    => '공지/회의',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);






// ============================================================
// 내 프로필 페이지
// ============================================================
$router->get('/profile', 'ProfileController@webProfile', [
    'key'         => 'web.user.profile.view',
    'name'        => '내 프로필',
    'description' => '사용자 개인 프로필 페이지',
    'category'    => '사용자',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);




