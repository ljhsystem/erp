<?php
// ?롪퍔?δ빳? PROJECT_ROOT/app/Controllers/Dashboard/Settings/SystemController.php
// ????類ｊ텠?????깆젧>??戮?츩??戮?맟??????筌뤾쑴?잏솻? ?筌뤾쑬??믨슈??? ?곌랜?삯뇡?筌먦끉?? ?筌???⑤베吏?API), ?筌???類λ룴???곗뿼?? ???逾?????? ??⑥щ턄??⑤벚而?? ??戮?츩??類ㅼŦ??API ???쳜?猿낆뿉??댁몠
namespace App\Controllers\Dashboard\Settings;

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
                'message' => 'JSON ?臾먮뼗 ??밴쉐????쎈솭??됰뮸??덈뼄.'
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
    }

    // ============================================================
    // WEB: ??????筌먲퐢沅????깆젧 ??븐뻼??
    // URL: GET /dashboard/settings/system/site
    // permission: web.settings.system.site
    // controller: SystemController@webSite
    // ============================================================
    public function webSite()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/site.php';
    }

    // ============================================================
    // API: ????????깆젧 ?브퀗???
    // URL: GET /api/settings/system/site/get
    // permission: settings.system.site.view
    // controller: SystemController@apiSiteGet
    // ============================================================
    public function apiSiteGet()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rows = $this->systemsettingService->getByCategory('SITE');

            // JS???????⑤슢????ル역??key => value ?筌먐븍Ф???곌떠???
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
    // API: ????????깆젧 ????
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
                throw new \Exception('php://input ???닷젆???깅쾳');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
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


            $result = $this->systemsettingService->saveBatch(
                $input,
                'SITE',
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
    // WEB: ?筌뤾쑬?????깆젧 ??븐뻼??
    // URL: GET /dashboard/settings/system/session
    // permission: web.settings.system.session
    // controller: SystemController@webSession
    // ============================================================
    public function webSession()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/session.php';
    }

    // ============================================================
    // API: ?筌뤾쑬?????깆젧 ?브퀗???
    // URL: GET /api/settings/system/session/get
    // permission: api.settings.system.session.view
    // controller: SystemController@apiSessionGet
    // ============================================================
    public function apiSessionGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('SESSION');

            // JS???????⑤슢????ル열? key => value ??뚮벣??륁뿉??곌떠???
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
    // API: ?筌뤾쑬?????깆젧 ????
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
                throw new \Exception('php://input ???닷젆???깅쾳');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
            }            $result = $this->systemsettingService->saveBatch(
                $input,
                'SESSION',
                [
                    'session_timeout' => '세션 유지 시간(분)',
                    'session_alert'   => '세션 만료 알림 시간(분)',
                    'session_sound'   => '세션 알림음'
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
    // WEB: ?곌랜?삯뇡??筌먦끉????븐뻼??
    // URL: GET /dashboard/settings/system/security
    // permission: web.settings.system.security
    // controller: SystemController@webSecurity
    // ============================================================
    public function webSecurity()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/security.php';
    }

    // ============================================================
    // API: ?곌랜?삯뇡??筌먦끉???브퀗???
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
    // API: ?곌랜?삯뇡??筌먦끉??????
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
                throw new \Exception('php://input ???닷젆???깅쾳');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
            }
            $result = $this->systemsettingService->saveBatch(
                $input,
                'SECURITY',
                [
                    'security_password_policy_enabled' => '비밀번호 정책 사용 여부',
                    'security_password_min'            => '비밀번호 최소 길이',
                    'security_password_expire'         => '비밀번호 만료 일수',
                    'security_pw_upper'                => '비밀번호 대문자 필수',
                    'security_pw_number'               => '비밀번호 숫자 필수',
                    'security_pw_special'              => '비밀번호 특수문자 필수',
                    'security_login_fail_policy_enabled' => '로그인 실패 정책 사용 여부',
                    'security_login_fail_max'            => '로그인 실패 허용 횟수',
                    'security_login_lock_minutes'        => '로그인 잠금 시간(분)',
                    'security_access_policy_enabled' => '접근 보안 정책 사용 여부',
                    'security_force_2fa'              => '전직원 2차 인증 강제',
                    'security_new_device_2fa'         => '신규 기기 로그인 추가 인증',
                    'security_login_time_restrict'    => '로그인 시간 제한 사용 여부',
                    'security_login_time_start'       => '로그인 허용 시작 시간',
                    'security_login_time_end'         => '로그인 허용 종료 시간',
                    'security_inactive_warn_days'     => '미접속 경고 추가 인증 일수',
                    'security_inactive_lock_days'     => '미접속 계정 잠금 일수'
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
    // WEB: API ???깆젧 ??븐뻼??
    // URL: GET /dashboard/settings/system/api
    // permission: web.settings.system.api
    // controller: SystemController@webApi
    // ============================================================
    public function webApi()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/api.php';
    }

    // ============================================================
    // API: ?筌? API ???깆젧 ?브퀗???
    // URL: GET /api/settings/system/api/get
    // permission: api.settings.system.api.view
    // controller: SystemController@apiApiGet
    // ============================================================
    public function apiApiGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('API');

            // JS???????⑤슢????ル열? key => value ??뚮벣??
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
    // API: ?筌? API ???깆젧 ????
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
                throw new \Exception('php://input ???닷젆???깅쾳');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
            }            $current = $this->systemsettingService->getByCategory('API');
            $currentKey = (string)($current['api_key']['config_value'] ?? '');
            $currentSecret = (string)($current['api_secret']['config_value'] ?? '');
            $regenerateKey = !empty($input['regenerate_api_key']);
            $regenerateSecret = !empty($input['regenerate_api_secret']);

            /* =====================================================
            * ???API Key / Secret ???吏???諛댁뎽 (???堉?
            * ===================================================== */

            // API Key?띠럾? ??怨몃さ嶺????吏???諛댁뎽
            if ($regenerateKey) {
                $input['api_key'] = bin2hex(random_bytes(16)); // 32 chars
            } elseif (empty($input['api_key'])) {
                $input['api_key'] = $currentKey;
            }

            // API Secret????怨몃さ嶺????吏???諛댁뎽
            if ($regenerateSecret) {
                $input['api_secret'] = bin2hex(random_bytes(32)); // 64 chars
            } elseif (empty($input['api_secret'])) {
                $input['api_secret'] = $currentSecret;
            }

            unset($input['regenerate_api_key'], $input['regenerate_api_secret']);

            /* =====================================================
            * ????
            * ===================================================== */
            $result = $this->systemsettingService->saveBatch(
                $input,
                'API',
                [
                    'api_enabled'        => '외부 API 사용 여부',
                    'api_key'            => 'API Key',
                    'api_secret'         => 'API Secret',
                    'api_token_ttl'      => 'Access Token 만료 시간(초)',
                    'api_ratelimit'      => 'API 요청 제한(분당)',
                    'api_ip_whitelist'   => '외부 API 허용 IP 목록',
                    'api_callback_url'   => 'API Callback URL'
                ]
            );

            echo json_encode([
                'success' => true,
                'result'  => $result,
                'data'    => [
                    // ?熬곣뫁夷?筌뤾쑬????熬곣뫗???濡?듆 ?꾩룆?餓????????쀬벟 ?꾩룇瑗??(??ルㅎ臾?
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
     * WEB: ?筌? ??類λ룴????⑤베吏????깆젧 ??븐뻼??
     * URL: GET /dashboard/settings/system/external-services
     * permission: web.settings.system.external
     * ============================================================ */
    public function webExternalServices()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/external_services.php';
    }

    /* ============================================================
     * API: ?筌? ??類λ룴????⑤베吏????깆젧 ?브퀗???
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
     * API: ?筌? ??類λ룴????⑤베吏????깆젧 ????
     * URL: POST /api/settings/system/external-services/save
     * permission: api.settings.system.external.edit
     * ============================================================ */
    public function apiExternalServicesSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input ???닷젆???깅쾳');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
            }
            /* =====================================================
             * ????(?筌? ??類λ룴????⑤베吏?
             * ===================================================== */
            $result = $this->systemsettingService->saveBatch(
                $input,
                'EXTERNAL_SERVICE',
                [
                    'synology_enabled'        => 'Synology Calendar 사용 여부',
                    'synology_host'           => 'Synology 서버 주소',
                    'synology_caldav_path'    => 'CalDAV 경로',
                    'synology_ssl_verify'     => 'SSL 인증서 검증 여부'
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
    // WEB: ???덇퐛?洹? ?筌먦끉????븐뻼??
    // URL: GET /dashboard/settings/system/storage
    // permission: web.settings.system.storage
    // controller: SystemController@webStorage
    // ============================================================
    public function webStorage()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/storage.php';
    }

    // ============================================================
    // WEB: DB ?꾩룄??캆???븐뻼??
    // URL: GET /dashboard/settings/system/database
    // permission: web.settings.system.database
    // controller: SystemController@webDatabase
    // ============================================================
    public function webDatabase()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/databasebackup.php';
    }

    // ============================================================
    // API: DB ?꾩룄??캆????덈뺄
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
    // API: DB ?꾩룄??캆????깆젧 ?브퀗???
    // URL: GET /api/settings/system/database/get
    // permission: api.settings.system.database.view
    // controller: SystemController@apiDatabaseGet
    // ============================================================
    public function apiDatabaseGet()
    {
        try {
            $rows = $this->systemsettingService->getByCategory('BACKUP');

            // JS???????⑤슢????ル열? key => value ??뚮벣??륁뿉??곌떠???
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
    // API: DB ?꾩룄??캆????깆젧 ????
    // URL: POST /api/settings/system/database/save
    // permission: api.settings.system.database.edit
    // controller: SystemController@apiDatabaseSave
    // ============================================================
    public function apiDatabaseSave()
    {
        try {
            // 1??뗣뀈繹?JSON ???놁졑 ??琉용뼁
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input ???닷젆???깅쾳');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
            }

            // 2??뗣뀈繹??????ID
            // 3??뗣뀈繹????깆젧 ????(BACKUP ?곸궠??誘ㅒ?μ쪚??
            $result = $this->systemsettingService->saveBatch(
                $input,
                'BACKUP',
                [
                    'backup_auto_enabled'                 => '자동 백업 사용 여부',
                    'backup_schedule'                     => '백업 실행 주기(daily/weekly/monthly)',
                    'backup_retention_days'               => '백업 보관 기간(일)',
                    'backup_cleanup_enabled'              => '오래된 백업 자동 정리',
                    'backup_restore_secondary_enabled'    => 'Secondary DB 자동 복원 사용 여부',
                    'backup_time'                         => '백업 실행 시간(HH:MM)'
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
    // API: DB ?꾩룄??캆???⑤객臾??筌먲퐢沅??브퀗???(?롪퍔?δ빳?嶺뚣끉裕??귥낯繹먮끏??
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
    // API: DB ?꾩룄??캆??β돦裕???브퀗???
    // URL: GET /api/settings/system/database/log
    // permission: api.settings.system.database.view
    // controller: SystemController@apiBackupLog
    // ============================================================
    public function apiBackupLog()
    {
        try {
            $dir = $this->backupService->getBackupDirectory();
            $logFile = rtrim($dir, '/') . '/backup_log.txt';

            $text = '?β돦裕?????逾????怨룸????덈펲.';
            if (is_file($logFile)) {
                // ??????ｋ걠????????? 嶺뚮씭??嶺?20000?꾩룆???筌뤾퍔異???袁ⓥ뵛(??믨퀡由?춯??브퀗???
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
    // API: DB ??怨멥돡??Replication) ??⑤객臾??브퀗???
    // URL: GET /api/settings/system/database/replication-status
    // permission: api.settings.system.database.view
    // controller: SystemController@apiDatabaseReplicationStatus
    // ============================================================
    public function apiDatabaseReplicationStatus()
    {
        try {
            $service = new DatabaseReplicationStatusService(DbPdo::conn());
            $status  = $service->check();

            // ???JS??????꾩룆?餓???⑤베利꿨슖?data ??臾먮쫭 ??蹂ㅽ깴
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
    // API: Secondary DB ??濡レ쭢/???吏??곌랜踰??
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
                'message' => 'Secondary DB 癰귣벊???遺욧퍕???臾믩땾??됰뮸??덈뼄. ?袁⑥삋 癰귣벊???怨밴묶?癒?퐣 筌욊쑵六???????類ㅼ뵥??뤾쉭??'
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
    // API: Secondary DB 嶺뚣끉裕???곌랜踰????⑤객臾??브퀗???
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
    // WEB: ??戮?츩???β돦裕????븐뻼??
    // URL: GET /dashboard/settings/system/logs
    // permission: web.settings.system.logs
    // controller: SystemController@webLogs
    // ============================================================
    public function webLogs()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/logs.php';
    }

    // ============================================================
    // API: ?β돦裕????怨몃뮔 ?브퀗???
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

            // ???????紐꾩럸 ???? 嶺뚮씭??嶺?50KB嶺???袁ⓥ뵛
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
    // API: ?β돦裕?????逾?????
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
    // API: ?熬곣뫕???β돦裕??????
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
    // WEB: ?β돦裕?????逾????깅뮧?β돦裕녻キ?
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

