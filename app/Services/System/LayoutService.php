<?php

namespace App\Services\System;

use App\Services\Auth\AuthSessionService;
use PDO;

class LayoutService
{
    private readonly PDO $pdo;
    private SettingService $settingService;
    private SessionConfigService $sessionConfigService;
    private AuthSessionService $authSessionService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->settingService = new SettingService($pdo);
        $this->sessionConfigService = new SessionConfigService($pdo);
        $this->authSessionService = new AuthSessionService();
    }

    public function getUiSettings(): array
    {
        return [
            'ui_skin' => $this->settingService->get('ui_skin', 'default'),
            'theme_mode' => $this->settingService->get('theme_mode', 'light'),
            'font_family' => $this->settingService->get('site_font_family', ''),
            'ui_density' => $this->settingService->get('ui_density', 'normal'),
            'font_scale' => $this->settingService->get('font_scale', 'normal'),
            'table_density' => $this->settingService->get('table_density', 'normal'),
            'card_density' => $this->settingService->get('card_density', 'normal'),
            'radius_style' => $this->settingService->get('radius_style', 'rounded'),
            'button_style' => $this->settingService->get('button_style', 'solid'),
            'row_focus' => $this->settingService->get('row_focus', 'normal'),
            'link_underline' => $this->settingService->get('link_underline', 'off'),
            'icon_scale' => $this->settingService->get('icon_scale', 'normal'),
            'alert_style' => $this->settingService->get('alert_style', 'normal'),
            'motion_mode' => $this->settingService->get('motion_mode', 'on'),
            'sidebar_default' => $this->settingService->get('sidebar_default', 'expanded'),
        ];
    }

    public function getSessionInfo(): array
    {
        $timeout = $this->sessionConfigService->getTimeoutMinutes();
        \Core\Session::start($timeout);

        return [
            'expire_time' => max(\Core\Session::getExpireTime(), time()),
            'timeout' => $timeout,
            'alert' => $this->sessionConfigService->getAlertTimeMinutes(),
            'sound' => $this->sessionConfigService->getAlertSound(),
        ];
    }

    public function getUserInfo(): array
    {
        if ($this->authSessionService->isAuthenticated()) {
            $user = $this->authSessionService->getCurrentUser() ?? [];
            $userId = $this->authSessionService->getCurrentUserId();
            $employeeName = trim((string)($user['employee_name'] ?? ''));

            if ($employeeName === '' && $userId) {
                $employeeName = $this->findEmployeeNameByUserId((string)$userId);
            }

            $displayName = $employeeName
                ?: trim((string)($user['username'] ?? ''))
                ?: trim((string)($user['email'] ?? ''));

            return [
                'display_name' => $displayName,
                'employee_name' => $employeeName ?: null,
                'username' => $user['username'] ?? null,
                'user_id' => $userId,
                'role_key' => $user['role_key'] ?? null,
                'role_name' => $user['role_name'] ?? null,
                'is_guest' => false,
                'menu_auth_state' => 'authenticated',
            ];
        }

        return [
            'display_name' => '',
            'employee_name' => null,
            'username' => null,
            'user_id' => null,
            'role_key' => null,
            'role_name' => null,
            'is_guest' => true,
            'menu_auth_state' => 'guest',
        ];
    }

    private function findEmployeeNameByUserId(string $userId): string
    {
        $stmt = $this->pdo->prepare("
            SELECT employee_name
            FROM user_employees
            WHERE user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $userId]);

        return trim((string)($stmt->fetchColumn() ?: ''));
    }

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
            'favicon_url' => $raw['favicon'] ?? null,
        ];
    }

    public function getLayoutData(): array
    {
        return [
            'ui' => $this->getUiSettings(),
            'session' => $this->getSessionInfo(),
            'user' => $this->getUserInfo(),
            'brand' => $this->getBrandInfo(),
        ];
    }
}
