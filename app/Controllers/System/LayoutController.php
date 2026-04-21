<?php

namespace App\Controllers\System;

use App\Services\System\BrandService;
use App\Services\System\LayoutService;
use Core\DbPdo;
use PDO;

class LayoutController
{
    private LayoutService $layoutService;
    private BrandService $brandService;

    public function __construct(?PDO $pdo = null)
    {
        $connection = $pdo ?? DbPdo::conn();
        $this->layoutService = new LayoutService($connection);
        $this->brandService = new BrandService($connection);
    }

    public function render(array $params = []): void
    {
        extract($params, EXTR_SKIP);

        $layoutData = $this->layoutService->getLayoutData();
        $ui = $layoutData['ui'] ?? [];
        $session = $layoutData['session'] ?? [];
        $user = $layoutData['user'] ?? [];

        $sessionTimeout = (int)($session['timeout'] ?? 30);
        $sessionAlert = (int)($session['alert'] ?? 5);
        $sessionSound = !empty($session['sound']) ? $session['sound'] : 'default.mp3';
        $expireTime = (int)($session['expire_time'] ?? (time() + ($sessionTimeout * 60)));

        $mainLogo = $this->brandService->getActive('main_logo');
        $favicon = $this->brandService->getActive('favicon');
        $mainLogoUrl = $mainLogo['url'] ?? null;
        $faviconUrl = $favicon['url'] ?? null;

        $pageTitle = $params['pageTitle'] ?? 'SUKHYANG ERP';
        $content = $params['content'] ?? '';
        $pageScripts = $params['pageScripts'] ?? '';
        $pageStyles = $params['pageStyles'] ?? '';

        extract([
            'sessionAlert' => $sessionAlert,
            'sessionTimeout' => $sessionTimeout,
            'sessionSound' => $sessionSound,
            'expireTime' => $expireTime,
            'ui' => $ui,
            'user' => $user,
            'userId' => $user['user_id'] ?? '',
            'displayName' => $user['display_name'] ?? '',
            'menuAuthState' => $user['menu_auth_state'] ?? 'guest',
            'pageTitle' => $pageTitle,
            'content' => $content,
            'pageScripts' => $pageScripts,
            'pageStyles' => $pageStyles,
            'mainLogoUrl' => $mainLogoUrl,
            'faviconUrl' => $faviconUrl,
        ], EXTR_SKIP);

        require PROJECT_ROOT . '/app/views/layout/layout.php';
    }
}
