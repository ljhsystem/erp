<?php

namespace App\Controllers\Dashboard\Settings;

use App\Services\Auth\UserContextService;
use App\Services\Backup\DatabaseBackupService;
use App\Services\System\DatabaseActiveSwitchService;
use App\Services\System\DatabaseReplicationStatusService;
use App\Services\System\SettingService;
use Core\DbPdo;

class SystemController
{
    private SettingService $systemsettingService;
    private DatabaseBackupService $backupService;
    private DatabaseActiveSwitchService $activeSwitchService;
    private UserContextService $userContextService;

    public function __construct()
    {
        $this->systemsettingService = new SettingService(DbPdo::conn());
        $this->backupService = new DatabaseBackupService(DbPdo::conn());
        $this->activeSwitchService = new DatabaseActiveSwitchService();
        $this->userContextService = new UserContextService();
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
                'message' => 'JSON 응답 인코딩 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
    }

    public function webSite()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/site.php';
    }

    public function apiSiteGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('SITE');

            // JS에서 바로 쓰기 쉽게 key => value 형태로 변환한다.
            $data = [];
            foreach ($rows as $key => $row) {
                $data[$key] = $row['config_value'];
            }

            echo json_encode([
                'success' => true,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiSiteSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 본문을 읽을 수 없습니다.');
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
                'SITE',
                [
                    'site_description' => '사이트 소개 문구',
                    'site_slogan' => '메인 문구',
                    'page_title' => '브라우저 페이지 제목',
                    'site_font_family' => '기본 글꼴',
                    'site_slogan_style' => '메인 문구 강조 스타일',
                    'home_intro_description' => '홈 소개 설명',
                    'home_intro_title' => '홈 소개 제목',
                    'home_intro_url' => '홈 소개 링크',
                    'sidebar_default' => '사이드바 기본 상태',
                    'ui_density' => 'UI 밀도',
                    'table_density' => '테이블 밀도',
                    'card_density' => '카드 밀도',
                    'radius_style' => '모서리 스타일',
                    'button_style' => '버튼 스타일',
                    'motion_mode' => '모션 효과',
                    'link_underline' => '링크 밑줄',
                    'alert_style' => '알림 스타일',
                    'theme_mode' => '테마 모드',
                    'site_title' => '사이트 제목',
                    'icon_scale' => '아이콘 크기',
                    'font_scale' => '글자 크기',
                    'row_focus' => '행 강조',
                    'ui_skin' => 'UI 스킨',
                    'footer_text' => '푸터 문구',
                ]
            );

            echo json_encode([
                'success' => true,
                'result' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function webSession()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/session.php';
    }

    public function apiSessionGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('SESSION');

            // JS에서 바로 쓰기 쉽게 key => value 형태로 변환한다.
            $data = [];
            foreach ($rows as $row) {
                $data[$row['config_key']] = $row['config_value'];
            }

            echo json_encode([
                'success' => true,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiSessionSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 본문을 읽을 수 없습니다.');
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
                'SESSION',
                [
                    'session_timeout' => '세션 유지 시간(분)',
                    'session_alert' => '세션 만료 알림 시간(분)',
                    'session_sound' => '세션 알림음',
                ]
            );

            echo json_encode([
                'success' => true,
                'result' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function webSecurity()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/security.php';
    }

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
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiSecuritySave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 본문을 읽을 수 없습니다.');
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
                    'security_password_min' => '비밀번호 최소 길이',
                    'security_password_expire' => '비밀번호 만료 일수',
                    'security_pw_upper' => '비밀번호 대문자 필수',
                    'security_pw_number' => '비밀번호 숫자 필수',
                    'security_pw_special' => '비밀번호 특수문자 필수',
                    'security_login_fail_policy_enabled' => '로그인 실패 정책 사용 여부',
                    'security_login_fail_max' => '로그인 실패 허용 횟수',
                    'security_login_lock_minutes' => '로그인 잠금 시간(분)',
                    'security_access_policy_enabled' => '접근 보안 정책 사용 여부',
                    'security_force_2fa' => '전 직원 2차 인증 강제',
                    'security_new_device_2fa' => '신규 기기 로그인 추가 인증',
                    'security_login_time_restrict' => '로그인 시간 제한 사용 여부',
                    'security_login_time_start' => '로그인 허용 시작 시간',
                    'security_login_time_end' => '로그인 허용 종료 시간',
                    'security_inactive_warn_days' => '미접속 경고 일수',
                    'security_inactive_lock_days' => '미접속 계정 잠금 일수',
                ]
            );

            echo json_encode([
                'success' => true,
                'result' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function webApi()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/api.php';
    }

    public function apiApiGet()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $rows = $this->systemsettingService->getByCategory('API');

            // JS에서 바로 쓰기 쉽게 key => value 형태로 변환한다.
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
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiApiSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 본문을 읽을 수 없습니다.');
            }

            $input = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON decode error: ' . json_last_error_msg());
            }

            if (!is_array($input)) {
                throw new \Exception('잘못된 요청 형식입니다.');
            }

            $current = $this->systemsettingService->getByCategory('API');
            $currentKey = (string)($current['api_key']['config_value'] ?? '');
            $currentSecret = (string)($current['api_secret']['config_value'] ?? '');
            $regenerateKey = !empty($input['regenerate_api_key']);
            $regenerateSecret = !empty($input['regenerate_api_secret']);

            if ($regenerateKey) {
                $input['api_key'] = bin2hex(random_bytes(16));
            } elseif (empty($input['api_key'])) {
                $input['api_key'] = $currentKey;
            }

            if ($regenerateSecret) {
                $input['api_secret'] = bin2hex(random_bytes(32));
            } elseif (empty($input['api_secret'])) {
                $input['api_secret'] = $currentSecret;
            }

            unset($input['regenerate_api_key'], $input['regenerate_api_secret']);

            $result = $this->systemsettingService->saveBatch(
                $input,
                'API',
                [
                    'api_enabled' => '외부 API 사용 여부',
                    'api_key' => 'API Key',
                    'api_secret' => 'API Secret',
                    'api_token_ttl' => 'Access Token 만료 시간(초)',
                    'api_ratelimit' => 'API 요청 제한(분당)',
                    'api_ip_whitelist' => '외부 API 허용 IP 목록',
                    'api_callback_url' => 'API Callback URL',
                ]
            );

            echo json_encode([
                'success' => true,
                'result' => $result,
                'data' => [
                    'api_key' => $input['api_key'],
                    'api_secret' => $input['api_secret'],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function webExternalServices()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/external_services.php';
    }

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
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiExternalServicesSave()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 본문을 읽을 수 없습니다.');
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
                'EXTERNAL_SERVICE',
                [
                    'synology_enabled' => 'Synology Calendar 사용 여부',
                    'synology_host' => 'Synology 서버 주소',
                    'synology_caldav_path' => 'CalDAV 경로',
                    'synology_ssl_verify' => 'SSL 인증서 검증 여부',
                ]
            );

            echo json_encode([
                'success' => true,
                'result' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function webStorage()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/storage.php';
    }

    public function webDatabase()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/databasebackup.php';
    }

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
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDatabaseGet()
    {
        try {
            $rows = $this->systemsettingService->getByCategory('BACKUP');

            $data = [];
            foreach ($rows as $row) {
                $data[$row['config_key']] = $row['config_value'];
            }

            $this->respondJson([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDatabaseSave()
    {
        try {
            $raw = file_get_contents('php://input');
            if (!$raw) {
                throw new \Exception('php://input 본문을 읽을 수 없습니다.');
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
                'BACKUP',
                [
                    'backup_auto_enabled' => 'Auto backup enabled',
                    'backup_auto_trigger_mode' => 'Auto backup trigger mode (manual/data-change)',
                    'backup_auto_min_interval_hours' => 'Auto backup minimum interval hours (12/24/48)',
                    'backup_retention_days' => 'Backup retention days',
                    'backup_cleanup_enabled' => 'Auto cleanup enabled',
                ]
            );

            $this->respondJson([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiBackupInfo()
    {
        try {
            $latest = $this->backupService->getLatestBackupFile();

            $this->respondJson([
                'success' => true,
                'data' => [
                    'backup_directory' => $this->backupService->getBackupDirectory(),
                    'backup_directory_masked' => $this->backupService->getBackupDirectoryMasked(),
                    'latest_backup' => $latest,
                    'backup_files' => $this->backupService->getRecentBackupFiles(12),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiBackupLog()
    {
        try {
            $dir = $this->backupService->getBackupDirectory();
            $switchLog = $this->readLogTail(rtrim($dir, '/') . '/active_db_switch_log.txt', 'Active DB 전환 로그가 없습니다.');
            $logFile = rtrim($dir, '/') . '/backup_log.txt';

            $text = '백업 로그가 없습니다.';
            if (is_file($logFile)) {
                // 너무 큰 로그 파일은 마지막 20,000바이트만 읽어 응답한다.
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
                    'log' => mb_convert_encoding((string)$text, 'UTF-8', 'UTF-8,CP949,EUC-KR,ISO-8859-1'),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDatabaseReplicationStatus()
    {
        try {
            $service = new DatabaseReplicationStatusService(DbPdo::conn());
            $status = $service->check();
            $latestSwitch = $this->activeSwitchService->getLatestSwitch();

            // 기존 JS 호환을 위해 data 래핑 없이 바로 반환한다.
            $this->respondJson([
                'success' => true,
                'primary' => $status['primary'] ?? null,
                'secondary' => $status['secondary'] ?? null,
                'active_db' => $status['active_db'] ?? null,
                'checked_at' => $status['checked_at'] ?? null,
                'latest_switch' => $latestSwitch,
                'can_switch_active' => $this->canSwitchActiveDatabase(),
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiRestoreSecondary()
    {
        try {
            @session_write_close();
            ignore_user_abort(true);
            @set_time_limit(0);

            $this->respondJson([
                'success' => true,
                'state' => 'running',
                'message' => 'Secondary DB 복원 요청이 접수되었습니다. 진행 상태는 복원 상태 영역에서 확인해 주세요.',
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiSecondaryRestoreInfo()
    {
        try {
            $info = $this->backupService->getLatestSecondaryRestore();

            $this->respondJson([
                'success' => true,
                'data' => $info,
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDatabaseStatus()
    {
        try {
            $service = new DatabaseReplicationStatusService(DbPdo::conn());
            $status = $service->check();

            $this->respondJson([
                'success' => true,
                'primary' => $status['primary'] ?? null,
                'secondary' => $status['secondary'] ?? null,
                'active_db' => $status['active_db'] ?? null,
                'checked_at' => $status['checked_at'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => '현재 DB 상태 조회 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    public function apiSwitchActiveDatabase()
    {
        try {
            if (!$this->canSwitchActiveDatabase()) {
                $this->respondJson([
                    'success' => false,
                    'message' => '관리자만 Active DB를 전환할 수 있습니다.',
                ], 403);
                return;
            }

            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = $_POST;
            }

            $target = (string) ($payload['target'] ?? '');
            if ($target === '') {
                $this->respondJson([
                    'success' => false,
                    'message' => '전환 대상 DB를 선택해 주세요.',
                ], 422);
                return;
            }

            $result = $this->activeSwitchService->switchActiveDatabase($target, [
                'user_id' => (string) ($this->userContextService->currentUserId() ?? ''),
                'display_name' => $this->userContextService->currentDisplayName(),
            ]);

            $this->respondJson([
                'success' => true,
                'data' => $result,
                'message' => 'Active DB 전환이 완료되었습니다.',
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => $e->getMessage() ?: 'Active DB 전환 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    public function apiDatabaseSync()
    {
        try {
            $latest = $this->backupService->getLatestSecondaryRestore();
            if (($latest['state'] ?? '') === 'running') {
                $this->respondJson([
                    'success' => false,
                    'state' => 'running',
                    'message' => 'DB 동기화가 이미 진행 중입니다.',
                ], 409);
                return;
            }

            @session_write_close();
            ignore_user_abort(true);
            @set_time_limit(0);

            $this->respondJson([
                'success' => true,
                'state' => 'running',
                'message' => 'DB 동기화 요청을 접수했습니다. 진행 상태를 확인해 주세요.',
            ], 202);

            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            } else {
                @flush();
            }

            $this->backupService->restoreLatestBackupToSecondary('sync');
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'state' => 'failed',
                'message' => 'DB 동기화 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    public function apiDatabaseSyncInfo()
    {
        try {
            $info = $this->backupService->getLatestSecondaryRestore();

            $this->respondJson([
                'success' => true,
                'data' => [
                    'state' => $info['state'] ?? 'idle',
                    'message' => $this->toSyncMessage($info),
                    'file' => $info['file'] ?? null,
                    'started_at' => $info['started_at'] ?? null,
                    'finished_at' => $info['finished_at'] ?? null,
                    'updated_at' => $info['updated_at'] ?? null,
                    'stage' => $info['stage'] ?? null,
                    'last_synced_file' => $info['applied_file'] ?? null,
                    'last_synced_at' => $info['applied_at'] ?? null,
                    'last_error' => $info['error'] ?? null,
                    'stale' => (bool) ($info['stale'] ?? false),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => 'DB 동기화 상태 조회 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    public function apiDatabaseActivityLog()
    {
        try {
            $dir = $this->backupService->getBackupDirectory();
            $backupLog = $this->readLogTail(rtrim($dir, '/') . '/backup_log.txt', '백업 로그가 없습니다.');
            $syncLog = $this->readLogTail(rtrim($dir, '/') . '/secondary_restore_log.txt', '동기화 로그가 없습니다.');
            $restoreLog = 'Primary DB 복원 로그가 없습니다.';

            $this->respondJson([
                'success' => true,
                'data' => [
                    'log' => "[SQL 백업]\n{$backupLog}\n\n[DB 동기화]\n{$syncLog}\n\n[DB 복원]\n{$restoreLog}",
                ],
            ]);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'message' => '통합 로그 조회 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    private function readLogTail(string $path, string $fallback): string
    {
        if (!is_file($path)) {
            return $fallback;
        }

        $fp = fopen($path, 'rb');
        if (!$fp) {
            return $fallback;
        }

        $size = filesize($path);
        $readSize = min($size, 20000);
        if ($size > 0) {
            fseek($fp, -$readSize, SEEK_END);
        }

        $text = fread($fp, $readSize) ?: '';
        fclose($fp);

        return mb_convert_encoding((string) $text, 'UTF-8', 'UTF-8,CP949,EUC-KR,ISO-8859-1');
    }

    private function toSyncMessage(array $info): string
    {
        return match ((string) ($info['state'] ?? 'idle')) {
            'running' => 'DB 동기화가 진행 중입니다.',
            'success' => 'DB 동기화가 완료되었습니다.',
            'failed' => 'DB 동기화에 실패했습니다.',
            default => 'DB 동기화 이력이 없습니다.',
        };
    }

    private function canSwitchActiveDatabase(): bool
    {
        $roleKey = strtolower((string) ($this->userContextService->currentRoleKey() ?? ''));
        return in_array($roleKey, ['admin', 'super_admin'], true);
    }

    public function webLogs()
    {
        include PROJECT_ROOT . '/app/views/dashboard/settings/system/logs.php';
    }

    public function apiLogView()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $file = basename($input['file'] ?? '');

            if (!$file || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
                throw new \Exception('Invalid file name');
            }

            $path = LOGS_DIR . '/' . $file;
            if (!is_file($path)) {
                throw new \Exception('Log file not found');
            }

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
                    'file' => $file,
                    'content' => $content,
                    'partial' => ($size > $maxBytes),
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function apiLogDelete()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $file = basename($input['file'] ?? '');

            if (!$file || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
                throw new \Exception('Invalid file name');
            }

            $path = LOGS_DIR . '/' . $file;
            if (!is_file($path)) {
                throw new \Exception('Log file not found');
            }

            unlink($path);

            echo json_encode([
                'success' => true,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

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
                'deleted' => $count,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

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
