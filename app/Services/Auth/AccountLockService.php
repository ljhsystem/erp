<?php
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\UserModel;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class AccountLockService
{
    private readonly PDO $pdo;
    private $authUserModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->authUserModel = new UserModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.AccountLockService');
    }

    public function handleLoginFail(string $userId): void
    {
        $policyEnabled = (int) ConfigHelper::system('security_login_fail_policy_enabled', 0);
        if ($policyEnabled !== 1) {
            return;
        }

        $maxFail  = (int) ConfigHelper::system('security_login_fail_max', 5);
        $lockMin = (int) ConfigHelper::system('security_login_lock_minutes', 30);

        $this->authUserModel->increaseFailCount($userId);
        $count = $this->authUserModel->getFailCount($userId);

        if ($count >= $maxFail) {
            $this->authUserModel->lockAccount($userId, $lockMin);

            $this->logger->warning('로그인 실패 누적으로 계정을 잠갔습니다.', [
                'event_code' => 'ACCOUNT_LOCKED_AFTER_LOGIN_FAILURES',
                'result' => 'SUCCESS',
                'user_id' => $userId,
                'minutes' => $lockMin
            ]);
        }
    }

    public function getFailCount(string $userId): int
    {
        return $this->authUserModel->getFailCount($userId);
    }

    public function lockAccount(string $userId, int $minutes): bool
    {
        return $this->authUserModel->lockAccount($userId, $minutes);
    }

    public function unlockAccount(string $userId): bool
    {
        $ok = $this->authUserModel->unlockAccount($userId);

        $this->logger->{$ok ? 'info' : 'warning'}($ok ? '계정 잠금을 해제했습니다.' : '계정 잠금 해제가 차단되었습니다.', [
            'event_code' => $ok ? 'ACCOUNT_UNLOCKED' : 'ACCOUNT_UNLOCK_BLOCKED',
            'result' => $ok ? 'SUCCESS' : 'BLOCKED',
            'user_id' => $userId,
        ]);

        return $ok;
    }

}
