<?php
// 寃쎈줈: PROJECT_ROOT . '/routes/web.php';
global $router;



//?쇱슦???ъ슜踰?(key / name / category / menu / auth / log)   <<<< ?듭떖

// $router->get('/dashboard/settings/base-info/company', 'DashboardController@settingsBaseInfoCompany', [

//     /* =========================================================
//      * ?뵎 湲곕낯 ?앸퀎 ?뺣낫 (?꾩닔)
//      * ========================================================= */
//     'key'         => 'web.settings.baseinfo.company', // 沅뚰븳 怨좎쑀 ??(DB? ?곌껐)
//     'name'        => '?뚯궗?뺣낫',                      // ?붾㈃ ?쒖떆 ?대쫫
//     'description' => '?뚯궗 湲곕낯?뺣낫 ?ㅼ젙 ?붾㈃ ?묎렐',   // 湲곕뒫 ?ㅻ챸
//     'category'    => '湲곗큹?뺣낫',                      // 沅뚰븳/硫붾돱 洹몃９ 遺꾨쪟


//     /* =========================================================
//      * ?뱛 硫붾돱/?ㅻ퉬寃뚯씠???쒖뼱
//      * ========================================================= */
//     'menu'        => true,                    // ?ъ씠?쒕컮/硫붾돱???몄텧 ?щ?
//     'menu_order'  => 1,                       // 硫붾돱 ?뺣젹 ?쒖꽌
//     'menu_group'  => 'settings.baseinfo',     // ?곸쐞 洹몃９ (?몃━ 援ъ“??
//     'menu_label'  => '?뚯궗?뺣낫',              // 硫붾돱 ?쒖떆 ?띿뒪??
//     'menu_icon'   => 'fa-building',           // ?꾩씠肄?(FontAwesome ??
//     'menu_visible'=> true,                    // 硫붾돱 ?쒖떆 ?щ? (沅뚰븳怨?蹂꾧컻)
//     'menu_badge'  => null,                    // 諛곗? (?? NEW, 3 ??


//     /* =========================================================
//      * ?뵍 ?몄쬆 / 沅뚰븳 / ?묎렐 ?쒖뼱
//      * ========================================================= */
//     'auth'        => true,                    // 濡쒓렇???꾩슂 ?щ?
//     'guest_only'  => false,                   // 鍮꾨줈洹몄씤 ?꾩슜 ?섏씠吏 ?щ?
//     'roles'       => ['admin', 'manager'],    // ?묎렐 媛?ν븳 ??븷
//     'permissions' => ['view'],                // ?몃? 沅뚰븳 ???(view/save/delete ??
//     'policy'      => null,                    // ?뺤콉 ?대옒??(Laravel ?ㅽ???媛??
//     'ip_whitelist'=> [],                      // ?뱀젙 IP留??덉슜
//     'ip_blacklist'=> [],                      // ?뱀젙 IP 李⑤떒


//     /* =========================================================
//      * ?숋툘 ?붿껌 ?쒖뼱 / 蹂댁븞
//      * ========================================================= */
//     'methods'     => ['GET'],                 // ?덉슜 HTTP 硫붿꽌??
//     'ajax_only'   => false,                   // AJAX ?붿껌留??덉슜 ?щ?
//     'csrf'        => true,                    // CSRF 泥댄겕 ?щ?
//     'throttle'    => '60,1',                  // rate limit (60??1遺?
//     'timeout'     => 10,                      // ?붿껌 ??꾩븘??(珥?


//     /* =========================================================
//      * ?쭬 ?섏씠吏 ?숈옉 / UI ?ㅼ젙
//      * ========================================================= */
//     'layout'      => 'dashboard',             // ?덉씠?꾩썐 ?좏깮
//     'breadcrumb'  => true,                    // breadcrumb ?먮룞 ?앹꽦
//     'page_title'  => '?뚯궗?뺣낫 ?ㅼ젙',          // 釉뚮씪?곗? ??댄?
//     'page_class'  => 'settings-company',      // body class
//     'container'   => 'fluid',                 // container ???


//     /* =========================================================
//      * ?벀 由ъ냼??(JS / CSS)
//      * ========================================================= */
//     'assets' => [
//         'css' => [
//             '/assets/css/pages/dashboard/settings/company.css'
//         ],
//         'js' => [
//             '/assets/js/pages/dashboard/settings/base/company.js'
//         ],
//         'module' => [
//             // ES Module ?꾩슂 ??
//         ]
//     ],


//     /* =========================================================
//      * ?뱤 濡쒓퉭 / 媛먯궗 (ERP ?듭떖)
//      * ========================================================= */
//     'log'         => true,                    // ?묎렐 濡쒓렇 湲곕줉 ?щ?
//     'log_action'  => 'VIEW_COMPANY_SETTINGS',// 濡쒓렇 ?≪뀡紐?
//     'audit'       => true,                    // 媛먯궗 濡쒓렇 ?щ?
//     'audit_level' => 'high',                  // low / medium / high


//     /* =========================================================
//      * ?㎥ 湲곕뒫 ?뚮옒洹?(Feature Toggle)
//      * ========================================================= */
//     'feature'     => 'company_settings',      // 湲곕뒫 ?앸퀎??
//     'enabled'     => true,                    // 湲곕뒫 ?쒖꽦???щ?
//     'beta'        => false,                   // 踰좏? 湲곕뒫 ?щ?


//     /* =========================================================
//      * ?뙇 ?ㅺ뎅??/ i18n
//      * ========================================================= */
//     'title_key'       => 'settings.company.title',
//     'description_key' => 'settings.company.description',
//     'lang'            => 'ko',


//     /* =========================================================
//      * ?뵕 API / ?곕룞 愿??
//      * ========================================================= */
//     'api' => [
//         'enabled' => true,
//         'prefix'  => '/api/settings/base-info/company',
//         'version' => 'v1'
//     ],


//     /* =========================================================
//      * ?㎚ ?뺤옣 / 而ㅼ뒪?곕쭏?댁쭠
//      * ========================================================= */
//     'middleware' => ['auth', 'permission', 'logging'], // ?ㅽ뻾 誘몃뱾?⑥뼱
//     'hooks' => [
//         'before' => null, // ?ㅽ뻾 ??肄쒕갚
//         'after'  => null  // ?ㅽ뻾 ??肄쒕갚
//     ],


//     /* =========================================================
//      * ?뮶 罹먯떛
//      * ========================================================= */
//     'cache' => [
//         'enabled' => false,
//         'ttl'     => 300, // seconds
//     ],


//     /* =========================================================
//      * ?㎦ 媛쒕컻/?붾쾭洹??듭뀡
//      * ========================================================= */
//     'debug' => [
//         'enabled' => false,
//         'log_sql' => false,
//         'log_time'=> false
//     ]
// ]);
















/* =========================================
 * ?쒖뒪??> ?먮윭 諛??몄뀡 愿由??쇱슦??
 * ========================================= */

// ============================================================
// ?묎렐 ?쒗븳 ?섏씠吏
// ============================================================
$router->get('/error', 'ErrorController@forbidden', [
    'key'         => 'web.system.error.forbidden',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?몄뀡 ?좎? (Keepalive API)
// ============================================================
$router->get('/autologout/keepalive', 'SessionController@apiKeepalive', [
    'key'         => 'api.system.session.keepalive',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?몄뀡 ?곗옣 ?덈궡 ?섏씠吏
// ============================================================
$router->get('/autologout/extend', 'SessionController@webExtendView', [
    'key'         => 'web.system.session.extend',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?몄뀡 留뚮즺 ?섏씠吏
// ============================================================
$router->get('/autologout/expired', 'SessionController@webExpired', [
    'key'         => 'web.system.session.expired',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);













/* =========================================================
 * 怨듦컻 ?섏씠吏 (?? ?뚭컻, 臾몄쓽, ?뺤콉)
 * ???쇰???泥댄겕 ?쒖쇅 (auth: false)
 * ========================================================= */

// ============================================================
// ??(猷⑦듃)
// ============================================================
$router->get('/', 'HomeController@webRoot', [
    'key'         => 'web.public.home.root',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ???몃뜳??
// ============================================================
$router->get('/home', 'HomeController@webIndex', [
    'key'         => 'web.public.home.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?뚯궗 ?뚭컻
// ============================================================
$router->get('/about', 'AboutController@webAbout', [
    'key'         => 'web.public.about',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 湲곗뾽 鍮꾩쟾
// ============================================================
$router->get('/vision', 'HomeController@webVision', [
    'key'         => 'web.public.vision',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 臾몄쓽?섍린
// ============================================================
$router->get('/contact', 'HomeController@webContact', [
    'key'         => 'web.public.contact',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 媛쒖씤?뺣낫 泥섎━諛⑹묠
// ============================================================
$router->get('/privacy', 'HomeController@webPrivacy', [
    'key'         => 'web.public.privacy',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?ъ씠?몃㏊
// ============================================================
$router->get('/sitemap', 'HomeController@webSitemap', [
    'key'         => 'web.public.sitemap',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

/* =========================================================
 * 愿由ъ옄 ?섏씠吏 (?몄쬆 ?꾩슂)
 * ========================================================= */

// ============================================================
// 愿由ъ옄 ?뚯궗 ?뚭컻
// ============================================================
$router->get('/admin/about', 'AboutController@webAdminAbout', [
    'key'         => 'web.admin.about.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);












/* =========================================================
 * ?몄쬆 > 濡쒓렇??諛?怨꾩젙 愿???섏씠吏 (怨듦컻 ?묎렐)
 * ========================================================= */

// ============================================================
// 濡쒓렇???섏씠吏
// ============================================================
$router->get('/login', 'LoginController@webLoginPage', [
    'key'         => 'web.auth.login',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 濡쒓렇?꾩썐
// ============================================================
$router->get('/logout', 'LoginController@apiLogout', [
    'key'         => 'web.auth.logout',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => true,
]);

$router->get('/auth/logout', 'LoginController@apiLogout', [
    'key'         => 'web.auth.logout.alias',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => true,
]);

// ============================================================
// ?꾩씠??李얘린
// ============================================================
$router->get('/find-id', 'PasswordController@webFindId', [
    'key'         => 'web.auth.find_id',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?꾩씠??李얘린 寃곌낵
// ============================================================
$router->get('/find-id/result', 'PasswordController@webFindIdResult', [
    'key'         => 'web.auth.find_id_result',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 鍮꾨?踰덊샇 李얘린
// ============================================================
$router->get('/find-password', 'PasswordController@webFindPassword', [
    'key'         => 'web.auth.find_password',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// 鍮꾨?踰덊샇 李얘린 寃곌낵
// ============================================================
$router->get('/find-password/result', 'PasswordController@webFindPasswordResult', [
    'key'         => 'web.auth.find_password_result',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?뚯썝媛??
// ============================================================
$router->get('/register', 'RegisterController@webRegisterPage', [
    'key'         => 'web.auth.register',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?뚯썝媛???꾨즺
// ============================================================
$router->get('/register_success', 'RegisterController@webRegisterSuccess', [
    'key'         => 'web.auth.register_success',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);

// ============================================================
// ?뱀씤 ?湲??섏씠吏
// ============================================================
$router->get('/waiting_approval', 'RegisterController@webWaitingApproval', [
    'key'         => 'web.auth.waiting_approval',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => false,
    'skip_permission' => true,
    'permissions' => [],
    'log'         => false,
]);




// ============================================================
// ?뚯썝媛???뱀씤 ?붿껌 ?섏씠吏
// ============================================================
$router->get('/auth/approval/request', 'UserApprovalController@webApproveRequest', [
    'key'             => 'web.approval.request.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'            => false,
    'skip_permission' => true,
    'log'             => false,
]);

// ============================================================
// ?뚯썝媛???뱀씤 寃곌낵 ?섏씠吏
// ============================================================
$router->get('/auth/approval/result', 'UserApprovalController@webApproveResult', [
    'key'             => 'web.approval.result.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'            => false,
    'skip_permission' => true,
    'log'             => false,
]);

// ============================================================
// 2李??몄쬆 ?섏씠吏
// ============================================================
$router->get('/2fa', 'TwoFactorController@webTwoFactor', [
    'key'             => 'web.auth.2fa',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'            => false,
    'allow_statuses'  => ['2FA_PENDING'],
    'skip_permission' => true,
    'permissions'     => [],
    'log'             => false,
]);

// ============================================================
// 鍮꾨?踰덊샇 蹂寃?(留뚮즺 ?ы븿)
// ============================================================
$router->get('/password/change', 'PasswordController@webChangePassword', [
    'key'             => 'web.auth.password_change',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'            => false,
    'allow_statuses'  => ['NORMAL', 'PASSWORD_EXPIRED'],
    'skip_permission' => true,
    'permissions'     => [],
    'log'             => false,
]);










/* =========================================================
 * ??쒕낫??WEB ?섏씠吏
 * ========================================================= */

// ============================================================
// ??쒕낫??硫붿씤
// ============================================================
$router->get('/dashboard', 'DashboardController@webDashboard', [
    'key'         => 'web.dashboard.main',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// ?듯빀 蹂닿퀬??
// ============================================================
$router->get('/dashboard/report', 'DashboardController@webReport', [
    'key'         => 'web.dashboard.report',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 罹섎┛??
// ============================================================
$router->get('/dashboard/calendar', 'DashboardController@webCalendar', [
    'key'         => 'web.dashboard.calendar',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 理쒓렐 ?쒕룞
// ============================================================
$router->get('/dashboard/activity', 'DashboardController@webActivity', [
    'key'         => 'web.dashboard.activity',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 怨듭??ы빆
// ============================================================
$router->get('/dashboard/notifications', 'DashboardController@webNotifications', [
    'key'         => 'web.dashboard.notifications',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// KPI / ?ㅼ쟻?꾪솴
// ============================================================
$router->get('/dashboard/kpi', 'DashboardController@webKpi', [
    'key'         => 'web.dashboard.kpi',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);



// ============================================================
// ?ㅼ젙 硫붿씤 ?섏씠吏
// ============================================================
$router->get('/dashboard/settings', 'DashboardController@webSettings', [
    'key'         => 'web.dashboard.settings',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);





/* =========================================================
 * ?ㅼ젙: 湲곗큹?뺣낫愿由??뚯궗?뺣낫, 釉뚮옖?? 而ㅻ쾭?대?吏, 嫄곕옒泥? ?꾨줈?앺듃
 * ========================================================= */

$router->get('/dashboard/settings/base-info/company', 'DashboardController@settingsBaseInfoCompany', [
    'key'         => 'web.settings.base-info.company',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/brand-logo', 'DashboardController@settingsBaseInfoBrand', [
    'key'         => 'web.settings.base-info.brand_logo',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/cover', 'DashboardController@settingsBaseInfoCover', [
    'key'         => 'web.settings.base-info.cover',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/codes', 'DashboardController@settingsBaseInfoCodes', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/code', 'DashboardController@settingsBaseInfoCodes', [
    'key'         => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/clients', 'DashboardController@settingsBaseInfoClients', [
    'key'         => 'web.settings.base-info.clients',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/projects', 'DashboardController@settingsBaseInfoProjects', [
    'key'         => 'web.settings.base-info.projects',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/bank-accounts', 'DashboardController@settingsBaseInfoAccounts', [
    'key'         => 'web.settings.base-info.accounts',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/cards', 'DashboardController@settingsBaseInfoCards', [
    'key'         => 'web.settings.base-info.cards',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/work-teams', 'DashboardController@settingsBaseInfoWorkTeams', [
    'key'         => 'work_team.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);

$router->get('/dashboard/settings/base-info/work-team', 'DashboardController@settingsBaseInfoWorkTeams', [
    'key'         => 'work_team.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);





/* =========================================================
 * ?ㅼ젙: 議곗쭅愿由?吏곸썝, 遺?? 吏곸콉, ??븷, 沅뚰븳, 沅뚰븳遺?? 寃곗옱?쒗뵆由?
 * ========================================================= */

$router->get('/dashboard/settings/organization/employees', 'DashboardController@settingsOrgEmployees', [
    'key' => 'web.settings.organization.employees',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/departments', 'DashboardController@settingsOrgDepartments', [
    'key' => 'web.settings.organization.departments',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/positions', 'DashboardController@settingsOrgPositions', [
    'key' => 'web.settings.organization.positions',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/roles', 'DashboardController@settingsOrgRoles', [
    'key' => 'web.settings.organization.roles',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/role_permissions', 'DashboardController@settingsOrgRolePermissions', [
    'key' => 'web.settings.organization.role_permissions',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/organization/approval', 'DashboardController@settingsOrgApproval', [
    'key' => 'web.settings.organization.approval',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);


/* =========================================================
 * ?ㅼ젙: ?쒖뒪?쒖꽕???ъ씠?몄젙蹂? ?몄뀡愿由? 蹂댁븞?뺤콉, ?몃??곕룞(API), ?몃??쒕퉬?ㅼ뿰?? ?뚯씪??μ냼, ?곗씠?곕갚?? ?쒖뒪?쒕줈洹?
 * ========================================================= */
$router->get('/dashboard/settings/system/site', 'DashboardController@settingsSystemSite', [
    'key' => 'web.settings.system.site',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/session', 'DashboardController@settingsSystemSession', [
    'key' => 'web.settings.system.session',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/security', 'DashboardController@settingsSystemSecurity', [
    'key' => 'web.settings.system.security',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/codes', 'DashboardController@settingsSystemCodes', [
    'key' => 'code.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/api', 'DashboardController@settingsSystemApi', [
    'key' => 'web.settings.system.api',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/external_services', 'DashboardController@settingsSystemExternal', [
    'key' => 'web.settings.system.external_services',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/storage', 'DashboardController@settingsSystemStorage', [
    'key' => 'web.settings.system.storage',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/databasebackup', 'DashboardController@settingsSystemBackup', [
    'key' => 'web.settings.system.databasebackup',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/logs', 'DashboardController@settingsSystemLogs', [
    'key' => 'web.settings.system.logs',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);

$router->get('/dashboard/settings/system/logs/download', 'SystemController@webLogDownload', [
    'key' => 'web.settings.system.logs.download',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true
]);




// ============================================================
// ?대?臾몄꽌
// ============================================================

// ?대?臾몄꽌 硫붿씤
$router->get('/document', 'DocumentController@webIndex', [
    'key'         => 'web.document.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 臾몄꽌 ?깅줉
$router->get('/document/file_register', 'DocumentController@webFileRegister', [
    'key'         => 'web.document.file_register',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 臾몄꽌 ?곸꽭
$router->get('/document/view', 'DocumentController@webView', [
    'key'         => 'web.document.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 臾몄꽌 ?섏젙
$router->get('/document/edit', 'DocumentController@webEdit', [
    'key'         => 'web.document.edit',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// 臾몄꽌 ?듦퀎
$router->get('/document/stats', 'DocumentController@webStats', [
    'key'         => 'web.document.stats',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);











/* =========================================================
 * ?꾩옄寃곗옱 > ?몃? ?뱀씤 ?섏씠吏 (?좏겙 湲곕컲 ?묎렐)
 * ========================================================= */
$router->get('/approval', 'ApprovalController@webIndex', [
    'key'         => 'web.approval.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);







/* =========================================================
 * ?뚭퀎愿由?(Ledger) WEB ?섏씠吏
 * ========================================================= */

// ============================================================
// ?뚭퀎愿由???쒕낫??
// ============================================================
$router->get('/ledger', 'LedgerController@webIndex', [
    'key'         => 'web.ledger.dashboard',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

/* =========================================================
 * 怨꾩젙怨쇰ぉ 愿由?
 * ========================================================= */

// ============================================================
// 怨꾩젙怨쇰ぉ ?붾㈃
// ============================================================
$router->get('/ledger/accounts', 'LedgerController@webAccount', [
    'key'         => 'web.ledger.accounts',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);



/* =========================================================
 * ?꾪몴 ?낅젰 / 嫄곕옒 愿由?WEB ?섏씠吏
 * ========================================================= */

// ============================================================
// ?꾪몴?낅젰
// ============================================================
$router->get('/ledger/journal', 'LedgerController@webJournal', [
    'key'         => 'web.ledger.journal',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 嫄곕옒?낅젰 ?⑥씪 ?섏씠吏(湲곗〈 嫄곕옒?댁뿭 ?명솚 寃쎈줈)
// ============================================================
$router->get('/ledger/transaction', 'TransactionController@webLedgerTransaction', [
    'key'         => 'web.ledger.transaction.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 嫄곕옒 ?낅젰
// ============================================================
$router->get('/ledger/transaction/create', 'TransactionController@webLedgerCreate', [
    'key'         => 'web.ledger.transaction.create',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/ledger/vouchers/review', 'LedgerController@webVoucherReview', [
    'key'         => 'web.ledger.vouchers.review',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/ledger/data/upload', 'LedgerController@webDataUpload', [
    'key'         => 'web.ledger.data.upload',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/ledger/data/format', 'LedgerController@webDataFormat', [
    'key'         => 'web.ledger.data.format',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$router->get('/ledger/data', 'LedgerController@webDataIndex', [
    'key'         => 'web.ledger.data.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

$ledgerPlaceholderRoutes = [
    ['/ledger/opening-balances', 'web.ledger.opening_balances', 'route', 'route'],
    ['/ledger/book/journal', 'web.ledger.book.journal', 'route', 'route'],
    ['/ledger/book/account', 'web.ledger.book.account', 'route', 'route'],
    ['/ledger/book/general', 'web.ledger.book.general', 'route', 'route'],
    ['/ledger/book/partner', 'web.ledger.book.partner', 'route', 'route'],
    ['/ledger/book/project', 'web.ledger.book.project', 'route', 'route'],
    ['/ledger/book/daily', 'web.ledger.book.daily', 'route', 'route'],
    ['/ledger/book/purchase-sales', 'web.ledger.book.purchase_sales', 'route', 'route'],
    ['/ledger/book/vehicle-log', 'web.ledger.book.vehicle_log', 'route', 'route'],
    ['/ledger/financial/trial-balance', 'web.ledger.financial.trial_balance', 'route', 'route'],
    ['/ledger/financial/income-statement', 'web.ledger.financial.income_statement', 'route', 'route'],
    ['/ledger/financial/statement-position', 'web.ledger.financial.statement_position', 'route', 'route'],
    ['/ledger/financial/product-cost', 'web.ledger.financial.product_cost', 'route', 'route'],
    ['/ledger/financial/construction-cost', 'web.ledger.financial.construction_cost', 'route', 'route'],
    ['/ledger/financial/retained-earnings', 'web.ledger.financial.retained_earnings', 'route', 'route'],
    ['/ledger/assets/create', 'web.ledger.assets.create', 'route', 'route'],
    ['/ledger/assets', 'web.ledger.assets.index', 'route', 'route'],
    ['/ledger/assets/depreciation', 'web.ledger.assets.depreciation', 'route', 'route'],
    ['/ledger/assets/transfer', 'web.ledger.assets.transfer', 'route', 'route'],
    ['/ledger/assets/disposal', 'web.ledger.assets.disposal', 'route', 'route'],
    ['/ledger/tax/trial-balance', 'web.ledger.tax.trial_balance', 'route', 'route'],
    ['/ledger/tax/income-statement', 'web.ledger.tax.income_statement', 'route', 'route'],
    ['/ledger/tax/statement-position', 'web.ledger.tax.statement_position', 'route', 'route'],
    ['/ledger/tax/cost-statement', 'web.ledger.tax.cost_statement', 'route', 'route'],
    ['/ledger/tax/retained-earnings', 'web.ledger.tax.retained_earnings', 'route', 'route'],
    ['/ledger/tax/comparison', 'web.ledger.tax.comparison', 'route', 'route'],
];

foreach ($ledgerPlaceholderRoutes as [$path, $key, $name, $description]) {
    $router->get($path, 'LedgerController@webPlaceholder', [
        'key'         => $key,
        'name' => 'route',
        'description' => 'route',
        'category' => 'system',
        'auth'        => true,
        'permissions' => ['view'],
        'log'         => false,
    ]);
}











// ============================================================
// ???湲곌??낅Т
// ============================================================

$router->get('/institution', 'InstitutionController@webIndex', [
    'key' => 'web.institution.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth' => true,
    'permissions' => ['view'],
    'log' => true,
]);









/* =========================================================
 * ?꾩옣愿由?> ?낅젰 ?곗씠??(嫄곕옒 ?먮낯)
 * ========================================================= */

// ============================================================
// ?꾩옣 ?낅젰 紐⑸줉
// ============================================================
$router->get('/site/entry', 'TransactionController@webTransaction', [
    'key'         => 'web.site.entry.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// ?꾩옣 ?낅젰
// ============================================================
$router->get('/site/entry/create', 'TransactionController@webCreate', [
    'key'         => 'web.site.entry.create',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// ?꾩옣 ??쒕낫??
// ============================================================
$router->get('/site', 'SiteController@dashboard', [
    'key'         => 'web.site.dashboard',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);





/* =========================================================
 * ?쇳븨紐곌?由?WEB ?섏씠吏
 * ========================================================= */

// ============================================================
// ?쇳븨紐???쒕낫??
// ============================================================
$router->get('/shop', 'ShopController@webIndex', [
    'key'         => 'web.shop.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// ?곹뭹愿由?
// ============================================================
$router->get('/shop/products', 'ShopController@webProducts', [
    'key'         => 'web.shop.products',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 移댄뀒怨좊━愿由?
// ============================================================
$router->get('/shop/categories', 'ShopController@webCategories', [
    'key'         => 'web.shop.categories',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 二쇰Ц愿由?
// ============================================================
$router->get('/shop/orders', 'ShopController@webOrders', [
    'key'         => 'web.shop.orders',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 寃곗젣愿由?
// ============================================================
$router->get('/shop/payments', 'ShopController@webPayments', [
    'key'         => 'web.shop.payments',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);

// ============================================================
// 留ㅼ텧/?뺤궛
// ============================================================
$router->get('/shop/settlement', 'ShopController@webSettlement', [
    'key'         => 'web.shop.settlement',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);




// ============================================================
// 怨듭?/?뚯쓽
// ============================================================
$router->get('/notice', 'NoticeController@webIndex', [
    'key'         => 'web.notice.index',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => false,
]);






// ============================================================
// ???꾨줈???섏씠吏
// ============================================================
$router->get('/profile', 'ProfileController@webProfile', [
    'key'         => 'web.user.profile.view',
    'name' => 'route',
    'description' => 'route',
    'category' => 'system',
    'auth'        => true,
    'permissions' => ['view'],
    'log'         => true,
]);




