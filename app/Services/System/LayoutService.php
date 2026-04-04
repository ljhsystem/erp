<?php
// 경로: PROJECT_ROOT/app/Services/System/LayoutService.php
namespace App\Services\System;

use PDO;
use Throwable;
use App\Services\System\SettingService;
use Core\LoggerFactory;

class LayoutService
{
    private readonly PDO $pdo;
    private SettingService $settingService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->settingService = new SettingService($pdo);
        $this->logger = LoggerFactory::getLogger('service-system.LayoutService');
        $this->logger->debug('[DB CHECK]', [
            'db' => $this->pdo->query('SELECT DATABASE()')->fetchColumn()
          ]);
    }



    /* ============================================================
     * 1) UI 설정 (기존 layout.php 변수명 100% 유지)
     * ============================================================ */
    public function getUiSettings(): array
    {
        $test = $this->settingService->get('ui_skin', 'default');
        error_log('[UI TEST] ui_skin = ' . var_export($test, true));
        return [
            'ui_skin'         => $this->settingService->get('ui_skin', 'default'),
            'theme_mode'      => $this->settingService->get('theme_mode', 'light'),
            'font_family'     => $this->settingService->get('site_font_family', ''),
            'font_scale'      => $this->settingService->get('font_scale', 'normal'),
            'table_density'   => $this->settingService->get('table_density', 'normal'),
            'card_density'    => $this->settingService->get('card_density', 'normal'),
            'radius_style'    => $this->settingService->get('radius_style', 'rounded'),
            'button_style'    => $this->settingService->get('button_style', 'solid'),
            'row_focus'       => $this->settingService->get('row_focus', 'normal'),
            'link_underline'  => $this->settingService->get('link_underline', 'off'),
            'icon_scale'      => $this->settingService->get('icon_scale', 'normal'),
            'alert_style'     => $this->settingService->get('alert_style', 'normal'),
            'motion_mode'     => $this->settingService->get('motion_mode', 'on'),
            'sidebar_default' => $this->settingService->get('sidebar_default', 'expanded'),
        ];
    }

    /* ============================================================
     * 2) 세션 설정 (DB)
     * ============================================================ */
    public function loadSessionConfig(): array
    {
        $config = [
            'session_timeout' => 30,        // 기본값: 30분
            'session_alert'   => 5,         // 기본값: 5분
            'session_sound'   => 'default.mp3', // 기본값
        ];
    
        try {
            $stmt = $this->pdo->prepare("SELECT config_key, config_value FROM system_settings_config WHERE config_key IN ('session_timeout', 'session_alert', 'session_sound')");
            $stmt->execute();
    
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($row['config_key'] === 'session_timeout' && is_numeric($row['config_value'])) {
                    $config['session_timeout'] = (int)$row['config_value'];
                }
                if ($row['config_key'] === 'session_alert' && is_numeric($row['config_value'])) {
                    $config['session_alert'] = (int)$row['config_value'];
                }
                if ($row['config_key'] === 'session_sound' && !empty($row['config_value'])) {
                    $config['session_sound'] = (string)$row['config_value'];
                }
            }
    
            // 디버깅 로그 추가
            $this->logger->debug('[SESSION CONFIG] Loaded session settings:', $config);
    
        } catch (Throwable $e) {
            $this->logger->warning('[SESSION] DB config load failed, fallback to default', [
                'error' => $e->getMessage()
            ]);
        }
    
        return $config;
    }
    

    

    /* ============================================================
     * 3) 세션 정보 (🔥 즉시 로그아웃 문제 완전 해결)
     * ============================================================ */
    public function getSessionInfo(): array
    {
        $db = $this->loadSessionConfig();
    
        // 1️⃣ timeout (분 → 초)
        $timeoutMinutes = (int)($db['session_timeout'] ?? 30);
        if ($timeoutMinutes <= 0) {
            $timeoutMinutes = 30;
        }
        $timeoutSeconds = $timeoutMinutes * 60;
    
        // 2️⃣ 기존 expire_time
        $expireTime = $_SESSION['expire_time'] ?? 0;
    
        // 🔥 로그인은 되어 있는데 expire_time만 깨진 경우
        if (!empty($_SESSION['user']['id']) && $expireTime < time()) {
            error_log('[SESSION FIX] expire_time was invalid, resetting');
            $expireTime = time() + (30 * 60);
            $_SESSION['expire_time'] = $expireTime;
        }
    
        // 3️⃣ alert
        $alertMinutes = (int)($db['session_alert'] ?? 5);
        if ($alertMinutes < 0) {
            $alertMinutes = 5;
        }
    
        // sound 기본값 처리
        $sound = $db['session_sound'] ?? 'default.mp3';
    
        return [
            'expire_time' => $expireTime,     // ⭐ 항상 미래
            'timeout'     => $timeoutMinutes, // 분
            'alert'       => $alertMinutes,   // 분
            'sound'       => $sound,          // 기본값 적용
        ];
    }
    
    
    

    /* ============================================================
     * 4) 사용자 정보 (View가 그대로 쓰도록 유지)
     * ============================================================ */
    public function getUserInfo(): array
    {
        if (!empty($_SESSION['user']['id'])) {
            $u = $_SESSION['user'];
    
            return [
                'display_name' => $u['employee_name']
                    ?? $u['username']
                    ?? $u['email']
                    ?? 'User',
    
                'user_id'    => $u['id'],
                'role_key'   => $u['role_key'] ?? null,
                'role_name'  => $u['role_name'] ?? null,
    
                'is_guest'   => false,
                'auth_state' => 'authenticated',
            ];
        }
    
        // ❗ guest는 “비로그인 페이지”에서만 의미 있음
        return [
            'display_name' => '',
            'user_id'      => null,
            'role_key'     => null,
            'role_name'    => null,
            'is_guest'     => true,
            'auth_state'   => 'guest',
        ];
    }
    

    /* ============================================================
     * 5) 브랜드 정보 (기존 navbar 기대 구조 유지)
     * ============================================================ */
    public function getBrandInfo(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT config_key, config_value
            FROM system_settings_config
            WHERE config_key IN ('main_logo','favicon')
        ");
        $stmt->execute();

        $raw = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $raw[$row['config_key']] = $row['config_value'];
        }

        return [
            'main_logo_url' => $raw['main_logo'] ?? null,
            'favicon_url'   => $raw['favicon'] ?? null,
        ];
    }

    /* ============================================================
     * 6) 레이아웃 전체 데이터
     * ============================================================ */
    public function getLayoutData(): array
    {
        return [
            'ui'      => $this->getUiSettings(),
            'session' => $this->getSessionInfo(),
            'user'    => $this->getUserInfo(),
            'brand'   => $this->getBrandInfo(),
        ];
    }
}
