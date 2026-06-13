<?php
namespace App\Services\Auth;

use PDO;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class SecurityPolicyService
{
    private readonly PDO $pdo;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->logger = LoggerFactory::getLogger('service-auth.SecurityPolicyService');
    }

    public function isNewDevice(array $user): bool
    {
        $lastDevice = $user['last_login_device'] ?? null;
        $current    = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if (!$lastDevice || !$current) {
            return true;
        }

        return $lastDevice !== $current;
    }

    public function isOutsideAllowedTime(): bool
    {
        $enabled = (int) ConfigHelper::system('security_access_policy_enabled', 0) === 1;
        if (!$enabled) {
            return false;
        }

        $start = (string) ConfigHelper::system('security_login_time_start', '07:00');
        $end   = (string) ConfigHelper::system('security_login_time_end', '20:00');

        $now = new \DateTime('now');
        $cur = (int) $now->format('Hi'); // 0730 → 730
        $s   = (int) str_replace(':', '', $start);
        $e   = (int) str_replace(':', '', $end);

        if ($s <= $e) {
            return !($cur >= $s && $cur <= $e);
        }

        return !($cur >= $s || $cur <= $e);
    }

    public function getInactiveDays(array $user): int
    {
        if (empty($user['last_login'])) {
            return PHP_INT_MAX;
        }

        return (int) floor((time() - strtotime($user['last_login'])) / 86400);
    }

    public function isInactive2faRequired(array $user): bool
    {
        $days = $this->getInactiveDays($user);
        $limit = (int) ConfigHelper::system('security_inactive_2fa_days', 0);

        return ($limit > 0 && $days >= $limit);
    }

    public function isInactiveLockRequired(array $user): bool
    {
        $days = $this->getInactiveDays($user);
        $limit = (int) ConfigHelper::system('security_inactive_lock_days', 0);

        return ($limit > 0 && $days >= $limit);
    }

    public function isForce2fa(): bool
    {
        return (int) ConfigHelper::system('security_force_2fa', 0) === 1;
    }

    public function isUser2faEnabled(array $user): bool
    {
        return !empty($user['two_factor_enabled'])
            && (int) $user['two_factor_enabled'] === 1;
    }

    public function isNewDevice2fa(array $user): bool
    {
        return (int) ConfigHelper::system('security_new_device_2fa', 0) === 1
            && $this->isNewDevice($user);
    }

    public function isTimeWindow2fa(): bool
    {
        if (!$this->isOutsideAllowedTime()) {
            return false;
        }

        $mode = (string) ConfigHelper::system('security_login_time_mode', '2fa');
        return $mode === '2fa';
    }

    public function needTwoFactor(array $user): bool
    {
        return
            $this->isForce2fa()
            || $this->isUser2faEnabled($user)
            || $this->isNewDevice2fa($user)
            || $this->isTimeWindow2fa()
            || $this->isInactive2faRequired($user);
    }

    public function getTwoFactorReasons(array $user): array
    {
        return [
            'force_2fa'      => $this->isForce2fa(),
            'user_2fa'       => $this->isUser2faEnabled($user),
            'new_device_2fa' => $this->isNewDevice2fa($user),
            'time_window'    => $this->isTimeWindow2fa(),
            'inactive_guard' => $this->isInactive2faRequired($user),
        ];
    }
}
