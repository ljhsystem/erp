<?php
// 경로: PROJECT_ROOT . '/app/services/auth/SecurityPolicyService.php'
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

    /**
     * 신규 기기 여부 판단
     * 기준:
     * - 마지막 로그인 device 와 현재 UA 다르면 신규 기기
     */
    public function isNewDevice(array $user): bool
    {
        $lastDevice = $user['last_login_device'] ?? null;
        $current    = $_SERVER['HTTP_USER_AGENT'] ?? null;

        if (!$lastDevice || !$current) {
            return true; // 기록 없으면 신규
        }

        return $lastDevice !== $current;
    }




    /**
     * 로그인 허용 시간대 판단
     * - block / 2fa 판단은 호출부에서
     * security_login_time_enabled (0/1)
     * security_login_time_start (예: "07:00")
     * security_login_time_end (예: "20:00")
     * security_login_time_mode ("2fa" 또는 "block")
     */
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

        // 같은날 범위
        if ($s <= $e) {
            return !($cur >= $s && $cur <= $e);
        }

        // 자정 넘어가는 범위
        return !($cur >= $s || $cur <= $e);
    }

    


    /**
     * 장기 미접속 일수 계산
     */
    public function getInactiveDays(array $user): int
    {
        if (empty($user['last_login'])) {
            return PHP_INT_MAX; // 로그인 기록 없음 → 최장 미접속
        }

        return (int) floor((time() - strtotime($user['last_login'])) / 86400);
    }

    /**
     * 장기 미접속 → 2FA 필요 여부
     */
    public function isInactive2faRequired(array $user): bool
    {
        $days = $this->getInactiveDays($user);
        $limit = (int) ConfigHelper::system('security_inactive_2fa_days', 0);

        return ($limit > 0 && $days >= $limit);
    }

    /**
     * 장기 미접속 → 계정 잠금 필요 여부 (판단만)
     */
    public function isInactiveLockRequired(array $user): bool
    {
        $days = $this->getInactiveDays($user);
        $limit = (int) ConfigHelper::system('security_inactive_lock_days', 0);

        return ($limit > 0 && $days >= $limit);
    }

    /**
     * 강제 2FA 여부
     */
    public function isForce2fa(): bool
    {
        return (int) ConfigHelper::system('security_force_2fa', 0) === 1;
    }

    /**
     * 사용자 개별 2FA 여부
     */
    public function isUser2faEnabled(array $user): bool
    {
        return !empty($user['two_factor_enabled'])
            && (int) $user['two_factor_enabled'] === 1;
    }

    /**
     * 신규 디바이스 2FA 여부
     */
    public function isNewDevice2fa(array $user): bool
    {
        return (int) ConfigHelper::system('security_new_device_2fa', 0) === 1
            && $this->isNewDevice($user);
    }

    /**
     * 시간대 기반 2FA 여부
     */
    public function isTimeWindow2fa(): bool
    {
        if (!$this->isOutsideAllowedTime()) {
            return false;
        }

        $mode = (string) ConfigHelper::system('security_login_time_mode', '2fa');
        return $mode === '2fa';
    }

    /**
     * ⭐ 최종 2FA 필요 여부 판단 (핵심)
     */
    public function needTwoFactor(array $user): bool
    {
        return
            $this->isForce2fa()
            || $this->isUser2faEnabled($user)
            || $this->isNewDevice2fa($user)
            || $this->isTimeWindow2fa()
            || $this->isInactive2faRequired($user);
    }

    /**
     * 2FA 발생 사유 정리
     */
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
