<?php

namespace App\Services\Auth;

class UserContextService
{
    private AuthSessionService $authSessionService;

    public function __construct(?AuthSessionService $authSessionService = null)
    {
        $this->authSessionService = $authSessionService ?? new AuthSessionService();
    }

    public function currentUser(): ?array
    {
        return $this->authSessionService->getCurrentUser();
    }

    public function currentUserId(): ?string
    {
        return $this->authSessionService->getCurrentUserId();
    }

    public function currentAuthStatus(): ?string
    {
        return $this->authSessionService->getStatus();
    }

    public function isAuthenticated(): bool
    {
        return $this->authSessionService->isAuthenticated();
    }

    public function currentDisplayName(): string
    {
        $user = $this->currentUser() ?? [];

        foreach (['employee_name', 'username', 'email'] as $key) {
            $value = trim((string)($user[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Guest';
    }

    public function currentRoleKey(): ?string
    {
        $user = $this->currentUser() ?? [];
        return !empty($user['role_key']) ? (string)$user['role_key'] : null;
    }

    public function currentRoleName(): ?string
    {
        $user = $this->currentUser() ?? [];
        return !empty($user['role_name']) ? (string)$user['role_name'] : null;
    }
}
