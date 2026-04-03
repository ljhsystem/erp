<?php
// 경로: PROJECT_ROOT/app/controllers/dashboard/settings/SystemSettingsController.php
// 대시보드>설정>시스템설정>사이트정보, 세션관리, 보안정책, 외부연동(API), 외부서비스연동, 파일저장소, 데이터백업, 시스템로그 API 컨트롤러
namespace App\Controllers\Dashboard\Settings;

use Core\Session;
use Core\DbPdo;
use App\Models\Auth\AuthUserModel;
use App\Services\System\SettingService;
use App\Services\Backup\DatabaseBackupService;
use App\Services\System\DatabaseReplicationStatusService;

class SystemSettingsController
{
    private AuthUserModel $usersModel;
    private SettingService $systemsettingService;
    private DatabaseBackupService $backupService;

    public function __construct()
    {
        Session::requireAuth();
        $this->usersModel    = new AuthUserModel(DbPdo::conn());
        $this->systemsettingService = new SettingService(DbPdo::conn());
        $this->backupService = new DatabaseBackupService(DbPdo::conn());
    }

    // ============================================================
    // WEB: 사이트 정보 설정 화면
    // URL: GET /dashboard/settings/system/site
    // permission: web.settings.system.site
    // controller: SystemSettingsController@webSite
    // ============================================================
    public function webSite()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/site.php';
    }

    // ============================================================
    // API: 사이트 설정 조회
    // URL: GET /api/settings/system/site/get
    // permission: settings.system.site.view
    // controller: SystemSettingsController@apiSiteGet
    // ============================================================
    public function apiSiteGet()
    {
        header('Content-Type: application/json; charset=utf-8');
        try {           
            $rows = $this->systemsettingService->getByCategory('SITE');

            // JS에서 쓰기 좋게 key => value 형태로 변환
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
    // API: 사이트 설정 저장
    // URL: POST /api/settings/system/site/save
    // permission: settings.system.site.edit
    // controller: SystemSettingsController@apiSiteSave
    // ============================================================
    public function apiSiteSave()
    {
        header('Content-Type: application/json; charset=utf-8');    
        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 비어있음');
            }
    
            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }
    
            if (!is_array($input)) {
                throw new \Exception('입력 데이터가 배열이 아님');
            }
            $userId = $_SESSION['user']['id'] ?? null;

            $result = $this->systemsettingService->saveBatch(
                $input,
                'SITE',
                $userId,   // ✅ 반드시 전달
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
                    'font_scale'              => '글꼴 크기',
                    'row_focus'               => '행 포커스',
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
    // WEB: 세션 설정 화면
    // URL: GET /dashboard/settings/system/session
    // permission: web.settings.system.session
    // controller: SystemSettingsController@webSession
    // ============================================================
    public function webSession()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/session.php';
    }

    // ============================================================
    // API: 세션 설정 조회
    // URL: GET /api/settings/system/session/get
    // permission: api.settings.system.session.view
    // controller: SystemSettingsController@apiSessionGet
    // ============================================================
    public function apiSessionGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('SESSION');

            // JS에서 쓰기 좋은 key => value 구조로 변환
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
    // API: 세션 설정 저장
    // URL: POST /api/settings/system/session/save
    // permission: api.settings.system.session.edit
    // controller: SystemSettingsController@apiSessionSave
    // ============================================================
    public function apiSessionSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 비어있음');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('입력 데이터가 배열이 아님');
            }
            $userId = $_SESSION['user']['id'] ?? null;
            $result = $this->systemsettingService->saveBatch(
                $input,
                'SESSION',
                $userId,   // ✅
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
    // WEB: 보안 정책 화면
    // URL: GET /dashboard/settings/system/security
    // permission: web.settings.system.security
    // controller: SystemSettingsController@webSecurity
    // ============================================================
    public function webSecurity()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/security.php';
    }

    // ============================================================
    // API: 보안 정책 조회
    // URL: GET /api/settings/system/security/get
    // permission: api.settings.system.security.view
    // controller: SystemSettingsController@apiSecurityGet
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
    // API: 보안 정책 저장
    // URL: POST /api/settings/system/security/save
    // permission: api.settings.system.security.edit
    // controller: SystemSettingsController@apiSecuritySave
    // ============================================================
    public function apiSecuritySave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 비어있음');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('입력 데이터가 배열이 아님');
            }

            $userId = $_SESSION['user']['id'] ?? null;

            $result = $this->systemsettingService->saveBatch(
                $input,
                'SECURITY',
                $userId,
                [

                    /* =====================================================
                    * 🔐 비밀번호 정책
                    * ===================================================== */
                    'security_password_policy_enabled' => '비밀번호 정책 사용 여부',
                    'security_password_min'            => '비밀번호 최소 길이',
                    'security_password_expire'         => '비밀번호 만료 일수',
                    'security_pw_upper'                => '비밀번호 대문자 필수',
                    'security_pw_number'               => '비밀번호 숫자 필수',
                    'security_pw_special'              => '비밀번호 특수문자 필수',

                    /* =====================================================
                    * 🚫 로그인 실패 정책
                    * ===================================================== */
                    'security_login_fail_policy_enabled' => '로그인 실패 정책 사용 여부',
                    'security_login_fail_max'            => '로그인 실패 허용 횟수',
                    'security_login_lock_minutes'        => '로그인 잠금 시간(분)',

                    /* =====================================================
                    * 🔐 접근 보안 강화 (인증 중심)
                    * ===================================================== */
                    'security_access_policy_enabled' => '접근 보안 정책 사용 여부',

                    // 전 직원 강제 보안
                    'security_force_2fa'              => '전 직원 2차 인증 강제',

                    // 행위 기반 보안
                    'security_new_device_2fa'         => '신규 기기 로그인 시 추가 인증',
                    'security_login_time_restrict'    => '로그인 시간 제한 사용 여부',

                    // ⏰ 로그인 허용 시간대 (🔥 이게 빠져 있었음)
                    'security_login_time_start'       => '로그인 허용 시작 시간',
                    'security_login_time_end'         => '로그인 허용 종료 시간',

                    // 장기 미사용 보호
                    'security_inactive_warn_days'     => '미접속 경고 후 추가 인증 일수',
                    'security_inactive_lock_days'     => '미접속 계정 잠금 일수',
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
    // WEB: API 설정 화면
    // URL: GET /dashboard/settings/system/api
    // permission: web.settings.system.api
    // controller: SystemSettingsController@webApi
    // ============================================================
    public function webApi()
    {   
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/api.php';
    }

    // ============================================================
    // API: 외부 API 설정 조회
    // URL: GET /api/settings/system/api/get
    // permission: api.settings.system.api.view
    // controller: SystemSettingsController@apiApiGet
    // ============================================================
    public function apiApiGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('API');

            // JS에서 쓰기 좋은 key => value 구조
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
    // API: 외부 API 설정 저장
    // URL: POST /api/settings/system/api/save
    // permission: api.settings.system.api.edit
    // controller: SystemSettingsController@apiApiSave
    // ============================================================
    public function apiApiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 비어있음');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('입력 데이터가 배열이 아님');
            }

            $userId = $_SESSION['user']['id'] ?? null;

            /* =====================================================
            * 🔐 API Key / Secret 자동 생성 (핵심)
            * ===================================================== */

            // API Key가 없으면 자동 생성
            if (empty($input['api_key'])) {
                $input['api_key'] = bin2hex(random_bytes(16)); // 32 chars
            }

            // API Secret이 없으면 자동 생성
            if (empty($input['api_secret'])) {
                $input['api_secret'] = bin2hex(random_bytes(32)); // 64 chars
            }

            /* =====================================================
            * 저장
            * ===================================================== */
            $result = $this->systemsettingService->saveBatch(
                $input,
                'API',
                $userId,
                [
                    /* =================================================
                    * 🔑 API 기본 설정
                    * ================================================= */
                    'api_enabled'        => '외부 API 사용 여부',
                    'api_key'            => 'API Key',
                    'api_secret'         => 'API Secret',

                    /* =================================================
                    * ⏱️ 토큰 · 요청 제한
                    * ================================================= */
                    'api_token_ttl'      => 'Access Token 만료 시간(초)',
                    'api_ratelimit'      => 'API 요청 제한(분당)',

                    /* =================================================
                    * 🌐 접근 제어 / 연동 정보
                    * ================================================= */
                    'api_ip_whitelist'   => '외부 API 호출 허용 IP 화이트리스트',
                    'api_callback_url'   => 'API Callback URL',
                ]
            );

            echo json_encode([
                'success' => true,
                'result'  => $result,
                'data'    => [
                    // 프론트에서 필요하면 바로 쓸 수 있게 반환 (선택)
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
     * WEB: 외부 서비스 연동 설정 화면
     * URL: GET /dashboard/settings/system/external-services
     * permission: web.settings.system.external
     * ============================================================ */
    public function webExternalServices()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/external_services.php';
    }

    /* ============================================================
     * API: 외부 서비스 연동 설정 조회
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
     * API: 외부 서비스 연동 설정 저장
     * URL: POST /api/settings/system/external-services/save
     * permission: api.settings.system.external.edit
     * ============================================================ */
    public function apiExternalServicesSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 비어있음');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('입력 데이터가 배열이 아님');
            }

            $userId = $_SESSION['user']['id'] ?? null;

            /* =====================================================
             * 저장 (외부 서비스 연동)
             * ===================================================== */
            $result = $this->systemsettingService->saveBatch(
                $input,
                'EXTERNAL_SERVICE',
                $userId,
                [
                    /* =================================================
                     * 📅 Synology Calendar
                     * ================================================= */
                    'synology_enabled'        => 'Synology Calendar 사용 여부',
                    'synology_host'           => 'Synology 서버 주소',
                    'synology_caldav_path'    => 'CalDAV 경로',
                    'synology_ssl_verify'     => 'SSL 인증서 검증 여부',
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
    // WEB: 스토리지 정책 화면
    // URL: GET /dashboard/settings/system/storage
    // permission: web.settings.system.storage
    // controller: SystemSettingsController@webStorage
    // ============================================================
    public function webStorage()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/storage.php';
    }

    // ============================================================
    // WEB: DB 백업 화면
    // URL: GET /dashboard/settings/system/database
    // permission: web.settings.system.database
    // controller: SystemSettingsController@webDatabase
    // ============================================================
    public function webDatabase()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/databasebackup.php';
    }

    // ============================================================
    // API: DB 백업 실행
    // URL: POST /api/settings/system/database/run
    // permission: api.settings.system.database.run
    // controller: SystemSettingsController@apiBackupRun
    // ============================================================
    public function apiBackupRun()
    {
        header('Content-Type: application/json; charset=UTF-8');

        try {
            $result = $this->backupService->backupDatabase();
            http_response_code($result['success'] ? 200 : 500);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    // ============================================================
    // API: DB 백업 설정 조회
    // URL: GET /api/settings/system/database/get
    // permission: api.settings.system.database.view
    // controller: SystemSettingsController@apiDatabaseGet
    // ============================================================
    public function apiDatabaseGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('BACKUP');

            // JS에서 쓰기 좋은 key => value 구조로 변환
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
    // API: DB 백업 설정 저장
    // URL: POST /api/settings/system/database/save
    // permission: api.settings.system.database.edit
    // controller: SystemSettingsController@apiDatabaseSave
    // ============================================================
    public function apiDatabaseSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            // 1️⃣ JSON 입력 수신
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 비어있음');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('입력 데이터가 배열이 아님');
            }

            // 2️⃣ 사용자 ID
            $userId = $_SESSION['user']['id'] ?? null;

            // 3️⃣ 설정 저장 (BACKUP 카테고리)
            $result = $this->systemsettingService->saveBatch(
                $input,
                'BACKUP',
                $userId,
                [
                    'backup_auto_enabled'                 => '자동 백업 사용 여부',
                    'backup_schedule'                     => '백업 실행 주기 (daily/weekly/monthly)',
                    'backup_retention_days'               => '백업 보관 기간(일)',
                    'backup_cleanup_enabled'              => '오래된 백업 자동 정리',
                    'backup_restore_secondary_enabled'    => 'Secondary DB 자동 복원 사용 여부',
                    'backup_time'                         => '백업 실행 시간(HH:MM)',
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
    // API: DB 백업 상태 정보 조회 (경로/최신백업)
    // URL: GET /api/settings/system/database/info
    // permission: api.settings.system.database.view
    // controller: SystemSettingsController@apiBackupInfo
    // ============================================================
    public function apiBackupInfo()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dir = $this->backupService->getBackupDirectory();
            $latest = $this->backupService->getLatestBackupFile();

            echo json_encode([
                'success' => true,
                'data' => [
                    'backup_directory' => $dir,
                    'latest_backup'    => $latest, // null or {file,time,path}
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
    // ============================================================
    // API: DB 백업 로그 조회
    // URL: GET /api/settings/system/database/log
    // permission: api.settings.system.database.view
    // controller: SystemSettingsController@apiBackupLog
    // ============================================================
    public function apiBackupLog()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $dir = $this->backupService->getBackupDirectory();
            $logFile = rtrim($dir, '/') . '/backup_log.txt';

            $text = '로그 파일이 없습니다.';
            if (is_file($logFile)) {
                // 너무 커지는 것 대비: 마지막 20000바이트만 읽기(원하면 조절)
                $fp = fopen($logFile, 'rb');
                if ($fp) {
                    $size = filesize($logFile);
                    $readSize = min($size, 20000);
                    fseek($fp, -$readSize, SEEK_END);
                    $text = fread($fp, $readSize) ?: '';
                    fclose($fp);
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'log' => $text
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

    // ============================================================
    // API: DB 이중화(Replication) 상태 조회
    // URL: GET /api/settings/system/database/replication-status
    // permission: api.settings.system.database.view
    // controller: SystemSettingsController@apiDatabaseReplicationStatus
    // ============================================================
    public function apiDatabaseReplicationStatus()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $service = new DatabaseReplicationStatusService(DbPdo::conn());
            $status  = $service->check();

            // 🔥 JS에서 바로 쓰도록 data 래핑 제거
            echo json_encode([
                'success'     => true,
                'primary'     => $status['primary'] ?? null,
                'secondary'   => $status['secondary'] ?? null,
                'checked_at'  => $status['checked_at'] ?? null,
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
    // API: Secondary DB 수동/자동 복원
    // URL: POST /api/settings/system/database/restore-secondary
    // permission: api.settings.system.database.restore
    // controller: SystemSettingsController@apiRestoreSecondary
    // ============================================================
    public function apiRestoreSecondary()
    {
        header('Content-Type: application/json; charset=utf-8');

        // 🔑 1. 세션 락 즉시 해제 (매우 중요)
        session_write_close();

        try {
            // 🔑 2. 즉시 성공 응답 반환
            echo json_encode([
                'success' => true,
                'message' => 'Secondary DB 복원 요청이 접수되었습니다.'
            ], JSON_UNESCAPED_UNICODE);

            // 🔑 3. 클라이언트와 연결 종료
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // 🔥 4. 백그라운드에서 실제 복원 실행
            $this->backupService->restoreLatestBackupToSecondary();

        } catch (\Throwable $e) {
            // ⚠️ 여기까지 오면 거의 없음 (응답은 이미 끝남)
            error_log('[RESTORE_SECONDARY_ERROR] ' . $e->getMessage());
        }
    }


    // ============================================================
    // API: Secondary DB 최신 복원 상태 조회
    // URL: GET /api/settings/system/database/secondary-restore-info
    // permission: api.settings.system.database.view
    // controller: SystemSettingsController@apiSecondaryRestoreInfo
    // ============================================================
    public function apiSecondaryRestoreInfo()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $info = $this->backupService->getLatestSecondaryRestore();

            echo json_encode([
                'success' => true,
                'data' => $info, // null or {time, file}
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
    // WEB: 시스템 로그 화면
    // URL: GET /dashboard/settings/system/logs
    // permission: web.settings.system.logs
    // controller: SystemSettingsController@webLogs
    // ============================================================
    public function webLogs()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/logs.php';
    }

    // ============================================================
    // API: 로그 내용 조회
    // URL: POST /api/settings/system/logs/view
    // permission: api.settings.system.logs.view
    // controller: SystemSettingsController@apiLogView
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

            // 🔥 대용량 대비: 마지막 50KB만 읽기
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
    // API: 로그 파일 삭제
    // URL: POST /api/settings/system/logs/delete
    // permission: api.settings.system.logs.delete
    // controller: SystemSettingsController@apiLogDelete
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
    // API: 전체 로그 삭제
    // URL: POST /api/settings/system/logs/delete-all
    // permission: api.settings.system.logs.delete_all
    // controller: SystemSettingsController@apiLogDeleteAll
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
    // WEB: 로그 파일 다운로드
    // URL: GET /dashboard/settings/system/logs/download?file=xxx.log
    // permission: web.settings.system.logs.download
    // controller: SystemSettingsController@webLogDownload
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
