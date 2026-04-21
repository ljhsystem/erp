<?php

namespace App\Services\System;

use PDO;

class SessionConfigService
{
    private SettingService $settingService;

    public function __construct(PDO $pdo)
    {
        $this->settingService = new SettingService($pdo);
    }

    public function getTimeoutMinutes(): int
    {
        return max(1, $this->settingService->getInt('session_timeout', 30));
    }

    public function getAlertTimeMinutes(): int
    {
        return max(1, $this->settingService->getInt('session_alert', 5));
    }

    public function getAlertSound(): string
    {
        return (string)$this->settingService->get('session_sound', 'alert1.mp3');
    }
}
