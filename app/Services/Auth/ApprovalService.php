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
        $this->logger->info('계정승인 인증정보 생성을 시작합니다.', [
            'event_code' => 'ACCOUNT_APPROVAL_TOKEN_STARTED',
            'result' => 'STARTED',
            'service' => self::class,
            'action' => 'account_approval_token.generate',
            'user_id' => $userId,
            'recipient_domain' => str_contains($adminEmail, '@') ? substr(strrchr($adminEmail, '@'), 1) : '',
        ]);

        $payload = [
            'user_id' => $userId,
            'admin'   => $adminEmail,
            'ts'      => time(),
        ];

        $secret = ConfigHelper::secret();

        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $approvalToken = base64_encode(json_encode([
            'data' => $payload,
            'sig'  => hash_hmac('sha256', $raw, $secret),
        ], JSON_UNESCAPED_UNICODE));
        $this->logger->info('계정승인 인증정보 생성을 완료했습니다.', [
            'event_code' => 'ACCOUNT_APPROVAL_TOKEN_CREATED', 'result' => 'SUCCESS',
            'service' => self::class, 'action' => 'account_approval_token.generate', 'user_id' => $userId,
        ]);
        return $approvalToken;
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

            $this->logger->{$ok ? 'info' : 'warning'}($ok ? '사용자 계정승인을 완료했습니다.' : '사용자 계정승인이 차단되었습니다.', [
                'event_code' => $ok ? 'ACCOUNT_APPROVAL_COMPLETED' : 'ACCOUNT_APPROVAL_BLOCKED',
                'result' => $ok ? 'SUCCESS' : 'BLOCKED', 'user_id' => $userId,
            ]);

            return $ok;

        } catch (\Throwable $e) {
            $this->logger->error('사용자 계정승인에 실패했습니다.', [
                'event_code' => 'ACCOUNT_APPROVAL_FAILED', 'result' => 'FAILED',
                'user_id' => $userId, 'error_code' => get_class($e), 'error' => $e,
            ]);
            return false;
        }
    }

    public function toggleActive(string $userId, int $active): bool
    {
        $ok = $this->authUsers->setActive($userId, $active, null);

        $this->logger->{$ok ? 'info' : 'warning'}($ok ? '사용자 계정 활성상태를 변경했습니다.' : '사용자 계정 활성상태 변경이 차단되었습니다.', [
            'event_code' => $ok ? 'ACCOUNT_ACTIVE_CHANGED' : 'ACCOUNT_ACTIVE_CHANGE_BLOCKED',
            'result' => $ok ? 'SUCCESS' : 'BLOCKED',
            'user_id' => $userId,
            'success' => $ok,
        ]);

        return $ok;
    }

    public function softDelete(string $userId, string $adminId): bool
    {
        $ok = $this->authUsers->softDelete($userId, $adminId);

        $this->logger->{$ok ? 'info' : 'warning'}($ok ? '사용자 계정을 삭제했습니다.' : '사용자 계정 삭제가 차단되었습니다.', [
            'event_code' => $ok ? 'ACCOUNT_DELETED' : 'ACCOUNT_DELETE_BLOCKED',
            'result' => $ok ? 'SUCCESS' : 'BLOCKED',
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
