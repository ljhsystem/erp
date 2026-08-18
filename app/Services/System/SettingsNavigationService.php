<?php

namespace App\Services\System;

use App\Models\System\MenuRegistryModel;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\PermissionService;
use PDO;

class SettingsNavigationService
{
    private MenuRegistryModel $menuRegistryModel;
    private PermissionService $permissionService;
    private AuthSessionService $authSessionService;

    public function __construct(PDO $pdo)
    {
        $this->menuRegistryModel = new MenuRegistryModel($pdo);
        $this->permissionService = new PermissionService($pdo);
        $this->authSessionService = new AuthSessionService();
    }

    public function getViewData(): array
    {
        try {
            $rows = $this->menuRegistryModel->getSettingsMenus();
        } catch (\Throwable) {
            $rows = [];
        }

        $permissionKeys = ['work_team.view', 'code.view'];
        foreach ($rows as $row) {
            $key = trim((string) ($row['default_route_key'] ?? ''));
            if ($key !== '') {
                $permissionKeys[] = $key;
            }
        }

        $allowed = [];
        $user = $this->authSessionService->getCurrentUser();
        $userId = trim((string) ($user['id'] ?? ''));
        foreach (array_values(array_unique($permissionKeys)) as $permissionKey) {
            try {
                $allowed[$permissionKey] = $userId !== ''
                    && $this->permissionService->hasPermission($userId, $permissionKey);
            } catch (\Throwable) {
                $allowed[$permissionKey] = false;
            }
        }

        return [
            'settingsMenuRows' => $rows,
            'settingsPermissionAllowed' => $allowed,
        ];
    }
}
