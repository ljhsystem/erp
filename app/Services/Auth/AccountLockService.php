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
        $this->logger->info('handleLoginFail 호출', [
            'user_id' => $userId
        ]);

        $policyEnabled = (int) ConfigHelper::system('security_login_fail_policy_enabled', 0);
        if ($policyEnabled !== 1) {
            return;
        }

        $maxFail  = (int) ConfigHelper::system('security_login_fail_max', 5);
        $lockMin = (int) ConfigHelper::system('security_login_lock_minutes', 30);

        $this->authUserModel->increaseFailCount($userId);
        $count = $this->authUserModel->getFailCount($userId);

        $this->logger->info('로그인 실패 카운트 증가', [
            'user_id'    => $userId,
            'fail_count' => $count
        ]);

        if ($count >= $maxFail) {
            $this->authUserModel->lockAccount($userId, $lockMin);

            $this->logger->warning('계정 잠금 처리', [
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
        $this->logger->info('unlockAccount 호출', [
            'user_id' => $userId
        ]);

        $ok = $this->authUserModel->unlockAccount($userId);

        $this->logger->info('unlockAccount 호출', [
            'user_id' => $userId,
            'success' => $ok
        ]);

        return $ok;
    }

}

