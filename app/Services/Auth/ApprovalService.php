<?php
// 경로: PROJECT_ROOT . '/app/Services/Auth/ApprovalService.php'
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\Auth\LogModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class ApprovalService
{
    private readonly PDO $pdo;
    private $authLogs;
    private $authUsers;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo       = $pdo;
        $this->authLogs  = new LogModel($pdo);
        $this->authUsers = new UserModel($pdo);
        $this->logger    = LoggerFactory::getLogger('service-auth.ApprovalService');
    }

    /* ---------------------------------------------------------
     * 1. 승인용 Token 생성
     * --------------------------------------------------------- */
    public function generateApprovalToken(string $userId, string $adminEmail): string
    {
        $this->logger->info('generateApprovalToken 시작', [
            'user_id' => $userId,
            'admin'   => $adminEmail,
        ]);

        $payload = [
            'user_id' => $userId,
            'admin'   => $adminEmail,
            'ts'      => time(),
        ];

        // ✅ 공용 Secret 헬퍼 사용
        $secret = ConfigHelper::secret();

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return base64_encode(json_encode([
            'data' => $payload,
            'sig'  => hash_hmac('sha256', $raw, $secret),
        ], JSON_UNESCAPED_UNICODE));
    }

    /* ---------------------------------------------------------
     * 2. 승인 토큰 검증
     * --------------------------------------------------------- */
    public function verifyApprovalToken(string $token): ?array
    {
        $decoded = json_decode(base64_decode($token), true);

        if (!is_array($decoded) || empty($decoded['data']) || empty($decoded['sig'])) {
            return null;
        }

        $data   = $decoded['data'];
        $secret = ConfigHelper::secret();
        $raw    = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sig    = hash_hmac('sha256', $raw, $secret);

        if (!hash_equals($sig, $decoded['sig'])) {
            return null;
        }

        // ⏱ 토큰 유효기간: 24시간
        if (($data['ts'] ?? 0) < (time() - 86400)) {
            return null;
        }

        return $data;
    }

    /* ---------------------------------------------------------
     * 3. 승인 처리
     * --------------------------------------------------------- */
    public function approveUser(string $userId, string $approvedBy = null): bool
    {
        try {
            $ok = $this->authUsers->approveUserFull($userId, $approvedBy);

            if ($ok) {
                $username = $this->authUsers->getUsername($userId);

                $this->authLogs->write([
                    'id'            => UuidHelper::generate(),
                    'user_id'       => $userId,
                    'username'      => $username,
                    'log_type'      => 'auth',
                    'action_type'   => 'approve',
                    'action_detail' => '계정승인',
                    'success'       => 1,
                    'ref_table'     => 'auth_users',
                    'ref_id'        => $userId,
                    'created_by'    => $approvedBy,
                ]);
            }

            return $ok;

        } catch (\Throwable $e) {
            $this->logger->error('approveUser 예외', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /* ---------------------------------------------------------
     * 4. 사용자 활성/비활성 변경
     * --------------------------------------------------------- */
    public function toggleActive(string $userId, int $active): bool
    {
        $this->logger->info('toggleActive 호출', [
            'user_id'   => $userId,
            'is_active' => $active,
        ]);

        $ok = $this->authUsers->setActive($userId, $active, null);

        $this->logger->info('toggleActive 결과', [
            'user_id' => $userId,
            'success' => $ok,
        ]);

        return $ok;
    }

    /* ---------------------------------------------------------
     * 5. 사용자 삭제 처리 (soft delete)
     * --------------------------------------------------------- */
    public function softDelete(string $userId, string $adminId): bool
    {
        $this->logger->info('softDelete 호출', [
            'user_id'    => $userId,
            'deleted_by' => $adminId,
        ]);

        $ok = $this->authUsers->softDelete($userId, $adminId);

        $this->logger->warning('softDelete 결과', [
            'user_id' => $userId,
            'success' => $ok,
        ]);

        return $ok;
    }
}
