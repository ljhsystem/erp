<?php

namespace App\Services\Auth;

use App\Services\System\SessionConfigService;
use Core\Database;
use Core\Session;

class AuthSessionService
{
    public const STATUS_NORMAL = 'NORMAL';
    public const STATUS_TWO_FACTOR_PENDING = '2FA_PENDING';
    public const STATUS_PASSWORD_EXPIRED = 'PASSWORD_EXPIRED';

    private const KEY_AUTH_STATE = 'auth_state';
    private const KEY_USER = 'user';
    private const KEY_PENDING_TWO_FACTOR = 'pending_two_factor';
    private const KEY_FLASH = 'auth_flash';
    private SessionConfigService $sessionConfigService;

    public function __construct()
    {
        $this->sessionConfigService = new SessionConfigService(Database::getInstance()->getConnection());
    }

    private function ensureStarted(): void
    {
        Session::start($this->sessionConfigService->getTimeoutMinutes());
    }

    private function rotateSessionId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private function normalizeUser(array $user): array
    {
        return [
            'id'        => $user['id'] ?? null,
            'username'  => $user['username'] ?? null,
            'role_id'   => $user['role_id'] ?? null,
            'role_key'  => $user['role_key'] ?? null,
            'role_name' => $user['role_name'] ?? null,
            'employee_name' => $user['employee_name'] ?? null,
            'email'     => $user['email'] ?? null,
        ];
    }

    private function setAuthState(?string $userId, ?string $status): void
    {
        if (!$userId || !$status) {
            unset($_SESSION[self::KEY_AUTH_STATE]);
            return;
        }

        $_SESSION[self::KEY_AUTH_STATE] = [
            'user_id' => $userId,
            'status'  => $status,
        ];
    }

    public function getAuthState(): array
    {
        $this->ensureStarted();

        $state = $_SESSION[self::KEY_AUTH_STATE] ?? [];
        return is_array($state) ? $state : [];
    }

    public function getStatus(): ?string
    {
        return $this->getAuthState()['status'] ?? null;
    }

    public function getCurrentUser(): ?array
    {
        $this->ensureStarted();

        $user = $_SESSION[self::KEY_USER] ?? null;
        return is_array($user) ? $user : null;
    }

    public function getCurrentUserId(): ?string
    {
        $user = $this->getCurrentUser();
        if (!empty($user['id'])) {
            return (string)$user['id'];
        }

        $state = $this->getAuthState();
        return !empty($state['user_id']) ? (string)$state['user_id'] : null;
    }

    public function isAuthenticated(): bool
    {
        $this->ensureStarted();

        if (Session::isExpired()) {
            Session::destroy();
            return false;
        }

        if ($this->getStatus() !== self::STATUS_NORMAL) {
            return false;
        }

        return !empty($this->getCurrentUser()['id']);
    }

    public function hasStatus(string ...$statuses): bool
    {
        $status = $this->getStatus();
        return $status !== null && in_array($status, $statuses, true);
    }

    public function createLoginSession(array $user): void
    {
        $this->ensureStarted();
        $this->rotateSessionId();

        $_SESSION[self::KEY_USER] = $this->normalizeUser($user);
        $this->setAuthState((string)$user['id'], self::STATUS_NORMAL);
        unset($_SESSION[self::KEY_PENDING_TWO_FACTOR]);

        Session::extend($this->sessionConfigService->getTimeoutMinutes());
    }

    public function createPendingTwoFactorSession(array $user, array $reasons, string $codeHash, int $ttl, int $maxAttempts): void
    {
        $this->ensureStarted();
        $this->rotateSessionId();

        unset($_SESSION[self::KEY_USER]);

        $_SESSION[self::KEY_PENDING_TWO_FACTOR] = [
            'user'         => $this->normalizeUser($user),
            'reasons'      => $reasons,
            'code_hash'    => $codeHash,
            'expires_at'   => time() + $ttl,
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
        ];

        $this->setAuthState((string)$user['id'], self::STATUS_TWO_FACTOR_PENDING);
        $_SESSION['expire_time'] = time() + $ttl;
    }

    public function getPendingTwoFactor(): ?array
    {
        $this->ensureStarted();

        $pending = $_SESSION[self::KEY_PENDING_TWO_FACTOR] ?? null;
        return is_array($pending) ? $pending : null;
    }

    public function incrementPendingTwoFactorAttempts(): int
    {
        $this->ensureStarted();

        if (!isset($_SESSION[self::KEY_PENDING_TWO_FACTOR]['attempts'])) {
            return 0;
        }

        $_SESSION[self::KEY_PENDING_TWO_FACTOR]['attempts']++;
        return (int)$_SESSION[self::KEY_PENDING_TWO_FACTOR]['attempts'];
    }

    public function clearPendingTwoFactor(): void
    {
        $this->ensureStarted();

        unset($_SESSION[self::KEY_PENDING_TWO_FACTOR]);

        if ($this->getStatus() === self::STATUS_TWO_FACTOR_PENDING) {
            $this->setAuthState(null, null);
        }
    }

    public function createPasswordExpiredSession(array $user): void
    {
        $this->ensureStarted();
        $this->rotateSessionId();

        $_SESSION[self::KEY_USER] = $this->normalizeUser($user);
        unset($_SESSION[self::KEY_PENDING_TWO_FACTOR]);
        $this->setAuthState((string)$user['id'], self::STATUS_PASSWORD_EXPIRED);

        Session::extend($this->sessionConfigService->getTimeoutMinutes());
    }

    public function markPasswordExpiredFlowComplete(array $user): void
    {
        $this->createLoginSession($user);
    }

    public function destroyAuthSession(): void
    {
        $this->ensureStarted();

        unset(
            $_SESSION[self::KEY_AUTH_STATE],
            $_SESSION[self::KEY_USER],
            $_SESSION[self::KEY_PENDING_TWO_FACTOR],
            $_SESSION['expire_time']
        );

        Session::destroy();
    }

    public function setFlash(string $key, string $message): void
    {
        $this->ensureStarted();
        $_SESSION[self::KEY_FLASH][$key] = $message;
    }

    public function pullFlash(string $key, string $default = ''): string
    {
        $this->ensureStarted();

        $message = $_SESSION[self::KEY_FLASH][$key] ?? $default;
        unset($_SESSION[self::KEY_FLASH][$key]);

        return is_string($message) ? $message : $default;
    }
}
