<?php
// 경로: PROJECT_ROOT . '/app/services/auth/TwoFactorService.php'
namespace App\Services\Auth;

use Core\Session;
use Core\LoggerFactory;

// require_once PROJECT_ROOT . '/core/Session.php';

/* ============================================================
 * TwoFactorService : SUKHYANG ERP 2FA 전담 서비스 (최종본)
 * ============================================================ */
class TwoFactorService
{
    private int $ttl = 300;          // 코드 유효시간 (초) : 5분
    private int $maxAttempts = 5;    // 최대 시도 횟수

    private $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger('service-auth.TwoFactorService');
    }

    /* ============================================================
     * 1) 2FA 코드 생성 + pending 세션 생성
     * ============================================================ */
    public function createPendingSession(array $user): string
    {
        $this->logger->info('2FA 코드 생성 요청', [
            'user_id'  => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
        ]);

        // 6자리 숫자 코드 생성
        try {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            $code = sprintf('%06d', mt_rand(0, 999999));
            $this->logger->warning('random_int 실패 → mt_rand fallback', [
                'error' => $e->getMessage()
            ]);
        }

        $_SESSION['pending_2fa'] = [
            'user' => [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'email'     => $user['email'] ?? null,
                'role_id'   => $user['role_id'] ?? null,
                'role_key'  => $user['role_key'] ?? null,
                'role_name' => $user['role_name'] ?? null,
            ],

            // 🔑 AuthService에서 계산된 "2FA 발생 사유"
            'reasons' => $user['reasons'] ?? [],

            // 인증 코드
            'code'       => $code,
            'code_hash'  => hash('sha256', $code),
            'expires_at' => time() + $this->ttl,
            'attempts'   => 0,
        ];

        Session::write();

        $this->logger->info('2FA pending 세션 생성 완료', [
            'user_id'    => $user['id'],
            'expires_at' => $_SESSION['pending_2fa']['expires_at'],
            'reasons'    => array_keys(array_filter($user['reasons'] ?? [])),
        ]);

        return $code;
    }

    /* ============================================================
     * 2) 2FA 대기 상태 여부 확인
     * ============================================================ */
    public function isPending(): bool
    {
        return isset($_SESSION['pending_2fa'])
            && ($_SESSION['pending_2fa']['expires_at'] > time());
    }

    /* ============================================================
     * 3) 2FA 코드 검증
     * ============================================================ */
    public function verifyCode(string $code): bool
    {
        if (!$this->isPending()) {
            $this->logger->warning('2FA 검증 실패 - pending 없음 또는 만료');
            return false;
        }

        $pending = &$_SESSION['pending_2fa'];
        $userId  = $pending['user']['id'] ?? null;

        // 시도 횟수 초과
        if ($pending['attempts'] >= $this->maxAttempts) {
            $this->logger->warning('2FA 검증 실패 - 시도 횟수 초과', [
                'user_id'  => $userId,
                'attempts' => $pending['attempts'],
            ]);
            return false;
        }

        $pending['attempts']++;

        // 코드 비교
        if (hash_equals($pending['code_hash'], hash('sha256', $code))) {
            $this->logger->info('2FA 코드 검증 성공', [
                'user_id'  => $userId,
                'attempts' => $pending['attempts'],
            ]);
            return true;
        }

        $this->logger->warning('2FA 코드 불일치', [
            'user_id'  => $userId,
            'attempts' => $pending['attempts'],
        ]);

        Session::write();
        return false;
    }

    /* ============================================================
     * 4) 2FA 완료 후 pending 세션 제거
     * ============================================================ */
    public function clearPending(): void
    {
        if (isset($_SESSION['pending_2fa'])) {
            $this->logger->info('2FA pending 세션 삭제', [
                'user_id' => $_SESSION['pending_2fa']['user']['id'] ?? null,
            ]);
        }

        unset($_SESSION['pending_2fa']);
        Session::write();
    }

    /* ============================================================
     * 5) 2FA 코드 재발송
     * ============================================================ */
    public function resendCode(): ?string
    {
        if (!$this->isPending()) {
            $this->logger->warning('2FA 재발송 실패 - pending 없음/만료');
            return null;
        }

        $pending = $_SESSION['pending_2fa'];
        $user    = $pending['user'];
        $reasons = $pending['reasons'] ?? [];

        if (empty($user['email'])) {
            $this->logger->warning('2FA 재발송 실패 - 이메일 없음', [
                'user_id' => $user['id'] ?? null,
            ]);
            return null;
        }

        // 기존 사유(reasons)를 그대로 유지한 채 재생성
        $newCode = $this->createPendingSession([
            'id'        => $user['id'],
            'username'  => $user['username'],
            'email'     => $user['email'],
            'role_id'   => $user['role_id'] ?? null,
            'role_key'  => $user['role_key'] ?? null,
            'role_name' => $user['role_name'] ?? null,
            'reasons'   => $reasons,
        ]);

        $this->logger->info('2FA 코드 재발송 요청', [
            'user_id' => $user['id'],
            'email'   => $user['email'],
        ]);

        return $newCode;
    }
}
