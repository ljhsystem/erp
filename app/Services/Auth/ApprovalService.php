<?php
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\Auth\LogModel;
use App\Services\Mail\MailToken;
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

        $secret = ConfigHelper::secret();

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return base64_encode(json_encode([
            'data' => $payload,
            'sig'  => hash_hmac('sha256', $raw, $secret),
        ], JSON_UNESCAPED_UNICODE));
    }

    public function verifyApprovalToken(string $token): ?array
    {
        $secret = ConfigHelper::secret();

        $mailTokenData = MailToken::verify($token, $secret);
        if (is_array($mailTokenData) && !empty($mailTokenData['user_id'])) {
            return $mailTokenData;
        }

        $decoded = json_decode(base64_decode($token), true);

        if (!is_array($decoded) || empty($decoded['data']) || empty($decoded['sig'])) {
            return null;
        }

        $data   = $decoded['data'];
        $raw    = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sig    = hash_hmac('sha256', $raw, $secret);

        if (!hash_equals($sig, $decoded['sig'])) {
            return null;
        }

        if (($data['ts'] ?? 0) < (time() - 86400)) {
            return null;
        }

        return $data;
    }

    public function getApprovalRequestViewData(string $token): array
    {
        $viewData = [
            'approveToken'  => $token,
            'message'       => '',
            'user'          => null,
            'formattedDate' => '',
        ];

        try {
            if (trim($token) === '') {
                throw new \InvalidArgumentException('approve_token 파라미터가 필요합니다.');
            }

            $tokenData = $this->verifyApprovalToken($token);
            if (!$tokenData || empty($tokenData['user_id'])) {
                throw new \RuntimeException('유효하지 않은 승인 요청 토큰입니다.');
            }

            $user = $this->authUsers->findApprovalRequestUser((string) $tokenData['user_id']);
            if (!$user) {
                throw new \RuntimeException('해당 사용자를 찾을 수 없습니다.');
            }

            $user['approved'] = (int) ($user['approved'] ?? 0);
            $viewData['user'] = $user;
            $viewData['formattedDate'] = $this->formatApprovalRequestDate($user['created_at'] ?? null);
            $viewData['message'] = $user['approved'] === 1
                ? '이미 승인된 사용자입니다.'
                : '승인 대기중인 사용자입니다.';
        } catch (\Throwable $e) {
            $viewData['message'] = '오류 발생: ' . $e->getMessage();
        }

        return $viewData;
    }

    public function approveUser(string $userId, ?string $approvedBy = null): bool
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
    private function formatApprovalRequestDate(?string $createdAt): string
    {
        if ($createdAt === null || trim($createdAt) === '') {
            return '';
        }

        $dt = new \DateTime($createdAt);
        $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
        $dayName = $weekdays[(int) $dt->format('w')];

        return $dt->format('Y-m-d') . "({$dayName}) " . $dt->format('H:i:s');
    }
}
