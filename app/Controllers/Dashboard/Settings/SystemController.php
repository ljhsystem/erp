<?php
// 寃쎈줈: PROJECT_ROOT/app/Controllers/Dashboard/Settings/SystemController.php
// ??쒕낫???ㅼ젙>?쒖뒪?쒖꽕???ъ씠?몄젙蹂? ?몄뀡愿由? 蹂댁븞?뺤콉, ?몃??곕룞(API), ?몃??쒕퉬?ㅼ뿰?? ?뚯씪??μ냼, ?곗씠?곕갚?? ?쒖뒪?쒕줈洹?API 而⑦듃濡ㅻ윭
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Services\System\SettingService;
use App\Services\Backup\DatabaseBackupService;
use App\Services\System\DatabaseReplicationStatusService;

class SystemController
{
    private SettingService $systemsettingService;
    private DatabaseBackupService $backupService;

    public function __construct()
    {
        Session::requireAuth();
        $this->systemsettingService = new SettingService(DbPdo::conn());
        $this->backupService = new DatabaseBackupService(DbPdo::conn());
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            http_response_code(500);
            $json = json_encode([
                'success' => false,
                'message' => 'JSON 응답 생성에 실패했습니다.'
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
    }

    // ============================================================
    // WEB: ?ъ씠???뺣낫 ?ㅼ젙 ?붾㈃
    // URL: GET /dashboard/settings/system/site
    // permission: web.settings.system.site
    // controller: SystemController@webSite
    // ============================================================
    public function webSite()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/site.php';
    }

    // ============================================================
    // API: ?ъ씠???ㅼ젙 議고쉶
    // URL: GET /api/settings/system/site/get
    // permission: settings.system.site.view
    // controller: SystemController@apiSiteGet
    // ============================================================
    public function apiSiteGet()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = $this->systemsettingService->getByCategory('SITE');

            // JS?먯꽌 ?곌린 醫뗪쾶 key => value ?뺥깭濡?蹂??
            $data = [];
            foreach ($rows as $key => $row) {
                $data[$key] = $row['config_value'];
            }

            $apiSecret = (string)($data['api_secret'] ?? '');
            $data['has_api_secret'] = $apiSecret !== '';
            $data['api_secret_masked'] = $apiSecret !== ''
                ? str_repeat('*', max(12, min(strlen($apiSecret), 24)))
                : '';
            unset($data['api_secret']);

            echo json_encode([
                'success' => true,
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // API: ?ъ씠???ㅼ젙 ???
    // URL: POST /api/settings/system/site/save
    // permission: settings.system.site.edit
    // controller: SystemController@apiSiteSave
    // ============================================================
    public function apiSiteSave()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 鍮꾩뼱?덉쓬');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('?낅젰 ?곗씠?곌? 諛곗뿴???꾨떂');
            }
            if (!array_key_exists('font_family', $input) && array_key_exists('site_font_family', $input)) {
                $input['font_family'] = $input['site_font_family'];
            }

            if (!array_key_exists('site_font_family', $input) && array_key_exists('font_family', $input)) {
                $input['site_font_family'] = $input['font_family'];
            }

            if (!array_key_exists('security_inactive_2fa_days', $input) && array_key_exists('security_inactive_warn_days', $input)) {
                $input['security_inactive_2fa_days'] = $input['security_inactive_warn_days'];
            }

            if (!array_key_exists('security_inactive_warn_days', $input) && array_key_exists('security_inactive_2fa_days', $input)) {
                $input['security_inactive_warn_days'] = $input['security_inactive_2fa_days'];
            }

            if (!array_key_exists('security_login_time_mode', $input) || !in_array($input['security_login_time_mode'], ['2fa', 'block'], true)) {
                $input['security_login_time_mode'] = '2fa';
            }

            $userId = $_SESSION['user']['id'] ?? null;

            $result = $this->systemsettingService->saveBatch(
                $input,
                'SITE',
                $userId,   // ??諛섎뱶???꾨떖
                [
                    'site_description'        => '사이트 소개 문구',
                    'site_slogan'             => '메인 문구',
                    'page_title'              => '브라우저 페이지 제목',
                    'site_font_family'        => '기본 글꼴',
                    'site_slogan_style'       => '메인 문구 강조 스타일',
                    'home_intro_description'  => '홈 소개 설명',
                    'home_intro_title'        => '홈 소개 제목',
                    'home_intro_url'          => '홈 소개 링크',
                    'sidebar_default'         => '사이드바 기본 상태',
                    'table_density'           => '테이블 밀도',
                    'card_density'            => '카드 밀도',
                    'radius_style'            => '모서리 스타일',
                    'button_style'            => '버튼 스타일',
                    'motion_mode'             => '모션 효과',
                    'link_underline'          => '링크 밑줄',
                    'alert_style'             => '알림 스타일',
                    'theme_mode'              => '테마 모드',
                    'site_title'              => '사이트 제목',
                    'icon_scale'              => '아이콘 크기',
                    'font_scale'              => '글자 크기',
                    'row_focus'               => '행 강조',
                    'ui_skin'                 => 'UI 스킨',
                    'footer_text'             => '푸터 문구'
                ]
            );


            echo json_encode([
                'success' => true,
                'result'  => $result
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // WEB: ?몄뀡 ?ㅼ젙 ?붾㈃
    // URL: GET /dashboard/settings/system/session
    // permission: web.settings.system.session
    // controller: SystemController@webSession
    // ============================================================
    public function webSession()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/session.php';
    }

    // ============================================================
    // API: ?몄뀡 ?ㅼ젙 議고쉶
    // URL: GET /api/settings/system/session/get
    // permission: api.settings.system.session.view
    // controller: SystemController@apiSessionGet
    // ============================================================
    public function apiSessionGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('SESSION');

            // JS?먯꽌 ?곌린 醫뗭? key => value 援ъ“濡?蹂??
            $data = [];
            foreach ($rows as $row) {
                $data[$row['config_key']] = $row['config_value'];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // API: ?몄뀡 ?ㅼ젙 ???
    // URL: POST /api/settings/system/session/save
    // permission: api.settings.system.session.edit
    // controller: SystemController@apiSessionSave
    // ============================================================
    public function apiSessionSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 鍮꾩뼱?덉쓬');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('?낅젰 ?곗씠?곌? 諛곗뿴???꾨떂');
            }
            $userId = $_SESSION['user']['id'] ?? null;
            $result = $this->systemsettingService->saveBatch(
                $input,
                'SESSION',
                $userId,   // ??
                [
                    'session_timeout' => '세션 유지 시간(분)',
                    'session_alert'   => '세션 만료 알림 시간(분)',
                    'session_sound'   => '세션 만료 알림 사운드'
                ]
            );


            echo json_encode([
                'success' => true,
                'result'  => $result
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }



    // ============================================================
    // WEB: 蹂댁븞 ?뺤콉 ?붾㈃
    // URL: GET /dashboard/settings/system/security
    // permission: web.settings.system.security
    // controller: SystemController@webSecurity
    // ============================================================
    public function webSecurity()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/security.php';
    }

    // ============================================================
    // API: 蹂댁븞 ?뺤콉 議고쉶
    // URL: GET /api/settings/system/security/get
    // permission: api.settings.system.security.view
    // controller: SystemController@apiSecurityGet
    // ============================================================
    public function apiSecurityGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('SECURITY');

            // JS-friendly key => value
            $data = [];
            foreach ($rows as $row) {
                $data[$row['config_key']] = $row['config_value'];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // API: 蹂댁븞 ?뺤콉 ???
    // URL: POST /api/settings/system/security/save
    // permission: api.settings.system.security.edit
    // controller: SystemController@apiSecuritySave
    // ============================================================
    public function apiSecuritySave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 鍮꾩뼱?덉쓬');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('?낅젰 ?곗씠?곌? 諛곗뿴???꾨떂');
            }

            $userId = $_SESSION['user']['id'] ?? null;

            $result = $this->systemsettingService->saveBatch(
                $input,
                'SECURITY',
                $userId,
                [

                    /* =====================================================
                    * ?뵍 鍮꾨?踰덊샇 ?뺤콉
                    * ===================================================== */
                    'security_password_policy_enabled' => '鍮꾨?踰덊샇 ?뺤콉 ?ъ슜 ?щ?',
                    'security_password_min'            => '鍮꾨?踰덊샇 理쒖냼 湲몄씠',
                    'security_password_expire'         => '鍮꾨?踰덊샇 留뚮즺 ?쇱닔',
                    'security_pw_upper'                => '鍮꾨?踰덊샇 ?臾몄옄 ?꾩닔',
                    'security_pw_number'               => '鍮꾨?踰덊샇 ?レ옄 ?꾩닔',
                    'security_pw_special'              => '鍮꾨?踰덊샇 ?뱀닔臾몄옄 ?꾩닔',

                    /* =====================================================
                    * ?슟 濡쒓렇???ㅽ뙣 ?뺤콉
                    * ===================================================== */
                    'security_login_fail_policy_enabled' => '濡쒓렇???ㅽ뙣 ?뺤콉 ?ъ슜 ?щ?',
                    'security_login_fail_max'            => '濡쒓렇???ㅽ뙣 ?덉슜 ?잛닔',
                    'security_login_lock_minutes'        => '濡쒓렇???좉툑 ?쒓컙(遺?',

                    /* =====================================================
                    * ?뵍 ?묎렐 蹂댁븞 媛뺥솕 (?몄쬆 以묒떖)
                    * ===================================================== */
                    'security_access_policy_enabled' => '?묎렐 蹂댁븞 ?뺤콉 ?ъ슜 ?щ?',

                    // ??吏곸썝 媛뺤젣 蹂댁븞
                    'security_force_2fa'              => '??吏곸썝 2李??몄쬆 媛뺤젣',

                    // ?됱쐞 湲곕컲 蹂댁븞
                    'security_new_device_2fa'         => '?좉퇋 湲곌린 濡쒓렇????異붽? ?몄쬆',
                    'security_login_time_restrict'    => '濡쒓렇???쒓컙 ?쒗븳 ?ъ슜 ?щ?',

                    // ??濡쒓렇???덉슜 ?쒓컙? (?뵦 ?닿쾶 鍮좎졇 ?덉뿀??
                    'security_login_time_start'       => '濡쒓렇???덉슜 ?쒖옉 ?쒓컙',
                    'security_login_time_end'         => '濡쒓렇???덉슜 醫낅즺 ?쒓컙',

                    // ?κ린 誘몄궗??蹂댄샇
                    'security_inactive_warn_days'     => '誘몄젒??寃쎄퀬 ??異붽? ?몄쬆 ?쇱닔',
                    'security_inactive_lock_days'     => '誘몄젒??怨꾩젙 ?좉툑 ?쇱닔',
                ]
            );

            echo json_encode([
                'success' => true,
                'result'  => $result
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }





    // ============================================================
    // WEB: API ?ㅼ젙 ?붾㈃
    // URL: GET /dashboard/settings/system/api
    // permission: web.settings.system.api
    // controller: SystemController@webApi
    // ============================================================
    public function webApi()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/api.php';
    }

    // ============================================================
    // API: ?몃? API ?ㅼ젙 議고쉶
    // URL: GET /api/settings/system/api/get
    // permission: api.settings.system.api.view
    // controller: SystemController@apiApiGet
    // ============================================================
    public function apiApiGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('API');

            // JS?먯꽌 ?곌린 醫뗭? key => value 援ъ“
            $data = [];
            foreach ($rows as $key => $row) {
                $data[$key] = $row['config_value'];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // API: ?몃? API ?ㅼ젙 ???
    // URL: POST /api/settings/system/api/save
    // permission: api.settings.system.api.edit
    // controller: SystemController@apiApiSave
    // ============================================================
    public function apiApiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 鍮꾩뼱?덉쓬');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('?낅젰 ?곗씠?곌? 諛곗뿴???꾨떂');
            }

            $userId = $_SESSION['user']['id'] ?? null;
            $current = $this->systemsettingService->getByCategory('API');
            $currentKey = (string)($current['api_key']['config_value'] ?? '');
            $currentSecret = (string)($current['api_secret']['config_value'] ?? '');
            $regenerateKey = !empty($input['regenerate_api_key']);
            $regenerateSecret = !empty($input['regenerate_api_secret']);

            /* =====================================================
            * ?뵍 API Key / Secret ?먮룞 ?앹꽦 (?듭떖)
            * ===================================================== */

            // API Key媛 ?놁쑝硫??먮룞 ?앹꽦
            if ($regenerateKey) {
                $input['api_key'] = bin2hex(random_bytes(16)); // 32 chars
            } elseif (empty($input['api_key'])) {
                $input['api_key'] = $currentKey;
            }

            // API Secret???놁쑝硫??먮룞 ?앹꽦
            if ($regenerateSecret) {
                $input['api_secret'] = bin2hex(random_bytes(32)); // 64 chars
            } elseif (empty($input['api_secret'])) {
                $input['api_secret'] = $currentSecret;
            }

            unset($input['regenerate_api_key'], $input['regenerate_api_secret']);

            /* =====================================================
            * ???
            * ===================================================== */
            $result = $this->systemsettingService->saveBatch(
                $input,
                'API',
                $userId,
                [
                    /* =================================================
                    * ?뵎 API 湲곕낯 ?ㅼ젙
                    * ================================================= */
                    'api_enabled'        => '?몃? API ?ъ슜 ?щ?',
                    'api_key'            => 'API Key',
                    'api_secret'         => 'API Secret',

                    /* =================================================
                    * ?깍툘 ?좏겙 쨌 ?붿껌 ?쒗븳
                    * ================================================= */
                    'api_token_ttl'      => 'Access Token 留뚮즺 ?쒓컙(珥?',
                    'api_ratelimit'      => 'API ?붿껌 ?쒗븳(遺꾨떦)',

                    /* =================================================
                    * ?뙋 ?묎렐 ?쒖뼱 / ?곕룞 ?뺣낫
                    * ================================================= */
                    'api_ip_whitelist'   => '?몃? API ?몄텧 ?덉슜 IP ?붿씠?몃━?ㅽ듃',
                    'api_callback_url'   => 'API Callback URL',
                ]
            );

            echo json_encode([
                'success' => true,
                'result'  => $result,
                'data'    => [
                    // ?꾨줎?몄뿉???꾩슂?섎㈃ 諛붾줈 ?????덇쾶 諛섑솚 (?좏깮)
                    'api_key'    => $input['api_key'],
                    'api_secret' => $input['api_secret'],
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /* ============================================================
     * WEB: ?몃? ?쒕퉬???곕룞 ?ㅼ젙 ?붾㈃
     * URL: GET /dashboard/settings/system/external-services
     * permission: web.settings.system.external
     * ============================================================ */
    public function webExternalServices()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/external_services.php';
    }

    /* ============================================================
     * API: ?몃? ?쒕퉬???곕룞 ?ㅼ젙 議고쉶
     * URL: GET /api/settings/system/external-services/get
     * permission: api.settings.system.external.view
     * ============================================================ */
    public function apiExternalServicesGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('EXTERNAL_SERVICE');

            $data = [];
            foreach ($rows as $key => $row) {
                $data[$key] = $row['config_value'];
            }

            echo json_encode([
                'success' => true,
                'data'    => $data
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /* ============================================================
     * API: ?몃? ?쒕퉬???곕룞 ?ㅼ젙 ???
     * URL: POST /api/settings/system/external-services/save
     * permission: api.settings.system.external.edit
     * ============================================================ */
    public function apiExternalServicesSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 鍮꾩뼱?덉쓬');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('?낅젰 ?곗씠?곌? 諛곗뿴???꾨떂');
            }

            $userId = $_SESSION['user']['id'] ?? null;

            /* =====================================================
             * ???(?몃? ?쒕퉬???곕룞)
             * ===================================================== */
            $result = $this->systemsettingService->saveBatch(
                $input,
                'EXTERNAL_SERVICE',
                $userId,
                [
                    /* =================================================
                     * ?뱟 Synology Calendar
                     * ================================================= */
                    'synology_enabled'        => 'Synology Calendar ?ъ슜 ?щ?',
                    'synology_host'           => 'Synology ?쒕쾭 二쇱냼',
                    'synology_caldav_path'    => 'CalDAV 寃쎈줈',
                    'synology_ssl_verify'     => 'SSL ?몄쬆??寃利??щ?',
                ]
            );

            echo json_encode([
                'success' => true,
                'result'  => $result
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }










    // ============================================================
    // WEB: ?ㅽ넗由ъ? ?뺤콉 ?붾㈃
    // URL: GET /dashboard/settings/system/storage
    // permission: web.settings.system.storage
    // controller: SystemController@webStorage
    // ============================================================
    public function webStorage()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/storage.php';
    }

    // ============================================================
    // WEB: DB 諛깆뾽 ?붾㈃
    // URL: GET /dashboard/settings/system/database
    // permission: web.settings.system.database
    // controller: SystemController@webDatabase
    // ============================================================
    public function webDatabase()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/databasebackup.php';
    }

    // ============================================================
    // API: DB 諛깆뾽 ?ㅽ뻾
    // URL: POST /api/settings/system/database/run
    // permission: api.settings.system.database.run
    // controller: SystemController@apiBackupRun
    // ============================================================
    public function apiBackupRun()
    {
        try {
            $result = $this->backupService->backupDatabase();
            $this->respondJson([
                'success' => (bool)($result['success'] ?? false),
                'message' => $result['message'] ?? '',
                'filename' => $result['filename'] ?? null,
                'time' => $result['time'] ?? null,
                'size' => $result['size'] ?? null,
            ], !empty($result['success']) ? 200 : 500);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // ============================================================
    // API: DB 諛깆뾽 ?ㅼ젙 議고쉶
    // URL: GET /api/settings/system/database/get
    // permission: api.settings.system.database.view
    // controller: SystemController@apiDatabaseGet
    // ============================================================
    public function apiDatabaseGet()
    {
        try {
            $rows = $this->systemsettingService->getByCategory('BACKUP');

            // JS?먯꽌 ?곌린 醫뗭? key => value 援ъ“濡?蹂??
            $data = [];
            foreach ($rows as $row) {
                $data[$row['config_key']] = $row['config_value'];
            }

            $this->respondJson([
                'success' => true,
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // ============================================================
    // API: DB 諛깆뾽 ?ㅼ젙 ???
    // URL: POST /api/settings/system/database/save
    // permission: api.settings.system.database.edit
    // controller: SystemController@apiDatabaseSave
    // ============================================================
    public function apiDatabaseSave()
    {
        try {
            // 1截뤴깵 JSON ?낅젰 ?섏떊
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 鍮꾩뼱?덉쓬');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('?낅젰 ?곗씠?곌? 諛곗뿴???꾨떂');
            }

            // 2截뤴깵 ?ъ슜??ID
            $userId = $_SESSION['user']['id'] ?? null;

            // 3截뤴깵 ?ㅼ젙 ???(BACKUP 移댄뀒怨좊━)
            $result = $this->systemsettingService->saveBatch(
                $input,
                'BACKUP',
                $userId,
                [
                    'backup_auto_enabled'                 => '?먮룞 諛깆뾽 ?ъ슜 ?щ?',
                    'backup_schedule'                     => '諛깆뾽 ?ㅽ뻾 二쇨린 (daily/weekly/monthly)',
                    'backup_retention_days'               => '諛깆뾽 蹂닿? 湲곌컙(??',
                    'backup_cleanup_enabled'              => '?ㅻ옒??諛깆뾽 ?먮룞 ?뺣━',
                    'backup_restore_secondary_enabled'    => 'Secondary DB ?먮룞 蹂듭썝 ?ъ슜 ?щ?',
                    'backup_time'                         => '諛깆뾽 ?ㅽ뻾 ?쒓컙(HH:MM)',
                ]
            );

            $this->respondJson([
                'success' => true,
                'result'  => $result
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // ============================================================
    // API: DB 諛깆뾽 ?곹깭 ?뺣낫 議고쉶 (寃쎈줈/理쒖떊諛깆뾽)
    // URL: GET /api/settings/system/database/info
    // permission: api.settings.system.database.view
    // controller: SystemController@apiBackupInfo
    // ============================================================
    public function apiBackupInfo()
    {
        try {
            $latest = $this->backupService->getLatestBackupFile();

            $this->respondJson([
                'success' => true,
                'data' => [
                    'backup_directory_masked' => $this->backupService->getBackupDirectoryMasked(),
                    'latest_backup'    => $latest,
                ]
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // ============================================================
    // API: DB 諛깆뾽 濡쒓렇 議고쉶
    // URL: GET /api/settings/system/database/log
    // permission: api.settings.system.database.view
    // controller: SystemController@apiBackupLog
    // ============================================================
    public function apiBackupLog()
    {
        try {
            $dir = $this->backupService->getBackupDirectory();
            $logFile = rtrim($dir, '/') . '/backup_log.txt';

            $text = '濡쒓렇 ?뚯씪???놁뒿?덈떎.';
            if (is_file($logFile)) {
                // ?덈Т 而ㅼ???寃??鍮? 留덉?留?20000諛붿씠?몃쭔 ?쎄린(?먰븯硫?議곗젅)
                $fp = fopen($logFile, 'rb');
                if ($fp) {
                    $size = filesize($logFile);
                    $readSize = min($size, 20000);
                    fseek($fp, -$readSize, SEEK_END);
                    $text = fread($fp, $readSize) ?: '';
                    fclose($fp);
                }
            }

            $this->respondJson([
                'success' => true,
                'data' => [
                    'log' => mb_convert_encoding((string)$text, 'UTF-8', 'UTF-8,CP949,EUC-KR,ISO-8859-1')
                ]
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // API: DB ?댁쨷??Replication) ?곹깭 議고쉶
    // URL: GET /api/settings/system/database/replication-status
    // permission: api.settings.system.database.view
    // controller: SystemController@apiDatabaseReplicationStatus
    // ============================================================
    public function apiDatabaseReplicationStatus()
    {
        try {
            $service = new DatabaseReplicationStatusService(DbPdo::conn());
            $status  = $service->check();

            // ?뵦 JS?먯꽌 諛붾줈 ?곕룄濡?data ?섑븨 ?쒓굅
            $this->respondJson([
                'success'     => true,
                'primary'     => $status['primary'] ?? null,
                'secondary'   => $status['secondary'] ?? null,
                'checked_at'  => $status['checked_at'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error'   => get_class($e),
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // API: Secondary DB ?섎룞/?먮룞 蹂듭썝
    // URL: POST /api/settings/system/database/restore-secondary
    // permission: api.settings.system.database.restore
    // controller: SystemController@apiRestoreSecondary
    // ============================================================
    public function apiRestoreSecondary()
    {
        try {
            @session_write_close();
            ignore_user_abort(true);
            @set_time_limit(0);

            $this->respondJson([
                'success' => true,
                'state' => 'running',
                'message' => 'Secondary DB 복원 요청을 접수했습니다. 아래 복원 상태에서 진행 여부를 확인하세요.'
            ], 202);

            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            } else {
                @flush();
            }

            $this->backupService->restoreLatestBackupToSecondary('manual');
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'state' => 'failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // ============================================================
    // API: Secondary DB 理쒖떊 蹂듭썝 ?곹깭 議고쉶
    // URL: GET /api/settings/system/database/secondary-restore-info
    // permission: api.settings.system.database.view
    // controller: SystemController@apiSecondaryRestoreInfo
    // ============================================================
    public function apiSecondaryRestoreInfo()
    {
        try {
            $info = $this->backupService->getLatestSecondaryRestore();

            $this->respondJson([
                'success' => true,
                'data' => $info, // null or {time, file}
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    // ============================================================
    // WEB: ?쒖뒪??濡쒓렇 ?붾㈃
    // URL: GET /dashboard/settings/system/logs
    // permission: web.settings.system.logs
    // controller: SystemController@webLogs
    // ============================================================
    public function webLogs()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/logs.php';
    }

    // ============================================================
    // API: 濡쒓렇 ?댁슜 議고쉶
    // URL: POST /api/settings/system/logs/view
    // permission: api.settings.system.logs.view
    // controller: SystemController@apiLogView
    // ============================================================
    public function apiLogView()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $file  = basename($input['file'] ?? '');

            if (!$file || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
                throw new \Exception('Invalid file name');
            }

            $path = LOGS_DIR . '/' . $file;
            if (!is_file($path)) {
                throw new \Exception('Log file not found');
            }

            // ?뵦 ??⑸웾 ?鍮? 留덉?留?50KB留??쎄린
            $maxBytes = 50 * 1024;
            $size = filesize($path);

            $fp = fopen($path, 'rb');
            if (!$fp) {
                throw new \Exception('Cannot open log file');
            }

            if ($size > $maxBytes) {
                fseek($fp, -$maxBytes, SEEK_END);
            }

            $content = fread($fp, $maxBytes);
            fclose($fp);

            echo json_encode([
                'success' => true,
                'data' => [
                    'file'    => $file,
                    'content' => $content,
                    'partial' => ($size > $maxBytes),
                ]
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }


    // ============================================================
    // API: 濡쒓렇 ?뚯씪 ??젣
    // URL: POST /api/settings/system/logs/delete
    // permission: api.settings.system.logs.delete
    // controller: SystemController@apiLogDelete
    // ============================================================
    public function apiLogDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $file  = basename($input['file'] ?? '');

            if (!$file || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
                throw new \Exception('Invalid file name');
            }

            $path = LOGS_DIR . '/' . $file;
            if (!is_file($path)) {
                throw new \Exception('Log file not found');
            }

            unlink($path);

            echo json_encode([
                'success' => true
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // API: ?꾩껜 濡쒓렇 ??젣
    // URL: POST /api/settings/system/logs/delete-all
    // permission: api.settings.system.logs.delete_all
    // controller: SystemController@apiLogDeleteAll
    // ============================================================
    public function apiLogDeleteAll()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $count = 0;

            foreach (scandir(LOGS_DIR) as $f) {
                $path = LOGS_DIR . '/' . $f;
                if (is_file($path)) {
                    unlink($path);
                    $count++;
                }
            }

            echo json_encode([
                'success' => true,
                'deleted' => $count
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    // ============================================================
    // WEB: 濡쒓렇 ?뚯씪 ?ㅼ슫濡쒕뱶
    // URL: GET /dashboard/settings/system/logs/download?file=xxx.log
    // permission: web.settings.system.logs.download
    // controller: SystemController@webLogDownload
    // ============================================================
    public function webLogDownload()
    {
        $file = basename($_GET['file'] ?? '');
        if (!$file || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            http_response_code(400);
            exit('Invalid file name');
        }

        $path = LOGS_DIR . '/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            exit('Log file not found');
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));

        readfile($path);
        exit;
    }
}

