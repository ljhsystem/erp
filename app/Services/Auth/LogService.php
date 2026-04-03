<?php
// 경로: PROJECT_ROOT . '/app/services/auth/LogService.php'
namespace App\Services\Auth;

// require_once PROJECT_ROOT . '/app/models/auth/AuthLogModel.php';

use PDO;
use App\Models\Auth\AuthLogModel;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;

class LogService
{
    private readonly PDO $pdo;
    private $authLogs;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->authLogs   = new AuthLogModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.LogService');
    }

    // ---------------------------------------------------------------
    // 공통 로그 기록 (DB 기록 + 파일 기록)
    // ---------------------------------------------------------------
    private function writeLog(array $data): void
    {
        // ⭐ UUID 생성은 서비스 책임
        $data['id'] = UuidHelper::generate();

        // IP, UserAgent 자동 설정이 Model에서 처리됨

        // DB 로그 기록
        $this->authLogs->write($data);
                
        // 파일 로그 (Serilog)
        $this->logger->info("AuthLog", [
            'id'            => $data['id'],
            'user_id'       => $data['user_id']    ?? null,
            'username'      => $data['username']   ?? null,
            'log_type'      => $data['log_type']   ?? 'auth',
            'action_type'   => $data['action_type'] ?? '',
            'detail'        => $data['action_detail'] ?? null,
            'success'       => $data['success'] ?? null,
        ]);
    }
    // ---------------------------------------------------------------
    // 회원가입 성공
    // ---------------------------------------------------------------
    public function registerSuccess(string $userId, string $username): void
    {
        $this->writeLog([
            'user_id'       => $userId,
            'username'      => $username,
            'log_type'      => 'auth',
            'action_type'   => 'register',
            'action_detail' => '회원가입성공',
            'success'       => 1,
            'ref_table'     => 'auth_users',
            'ref_id'        => $userId,
            'created_by'    => $userId,
        ]);
    }

    // ---------------------------------------------------------------
    // 회원가입 실패
    // ---------------------------------------------------------------
    public function registerFail(string $username, string $detail): void
    {
        $this->writeLog([
            'username'      => $username,
            'log_type'      => 'auth',
            'action_type'   => 'register',
            'action_detail' => $detail,
            'success'       => 0,
        ]);
    }


    // ---------------------------------------------------------------
    // 로그인 성공
    // ---------------------------------------------------------------
    public function loginSuccess(string $userId, string $username, string $detail = '정상로그인성공', array $extra = []): void
    {
        $this->writeLog([
            'user_id'        => $userId,
            'username'       => $username,
            'log_type'       => 'auth',
            'action_type'    => 'login',
            'action_detail'  => $detail,
            'success'        => 1,
            'ref_table'      => $extra['ref_table'] ?? null,
            'ref_id'         => $extra['ref_id'] ?? null,
            'created_by'     => $extra['created_by'] ?? $userId,
        ]);
    }

    // ---------------------------------------------------------------
    // 로그인 실패
    // ---------------------------------------------------------------
    public function loginFail(string $username, string $detail = '로그인실패', array $extra = []): void
    {
        $this->writeLog([
            'user_id'        => $extra['user_id'] ?? null,
            'username'       => $username,
            'log_type'       => 'auth',
            'action_type'    => 'login',
            'action_detail'  => $detail,
            'success'        => 0,
            'ref_table'      => $extra['ref_table'] ?? null,
            'ref_id'         => $extra['ref_id'] ?? null,
            'created_by'     => $extra['created_by'] ?? null,
        ]);
    }

    // ---------------------------------------------------------------
    // 계정 잠김
    // ---------------------------------------------------------------
    public function accountLocked(string $userId): void
    {
        $this->writeLog([
            'user_id'       => $userId,
            'log_type'      => 'auth',
            'action_type'   => 'account_locked',
            'action_detail' => '계정이 잠김',
            'success'       => 0,
        ]);
    }

    // ---------------------------------------------------------------
    // 계정 잠금 해제
    // ---------------------------------------------------------------
    public function accountUnlocked(string $userId): void
    {
        $this->writeLog([
            'user_id'       => $userId,
            'log_type'      => 'auth',
            'action_type'   => 'account_unlocked',
            'action_detail' => '계정 잠금 해제',
            'success'       => 1,
        ]);
    }

    // ---------------------------------------------------------------
    // 비밀번호 변경
    // ---------------------------------------------------------------
    public function passwordChanged(string $userId): void
    {
        $this->writeLog([
            'user_id'       => $userId,
            'log_type'      => 'auth',
            'action_type'   => 'password_changed',
            'action_detail' => '비밀번호 변경',
            'success'       => 1,
        ]);
    }

    // ---------------------------------------------------------------
    // 관리자 승인
    // ---------------------------------------------------------------
    public function approved(string $userId, string $approvedByUserId, string $approvedByUsername = null): void
    {
        $this->writeLog([
            'user_id'       => $userId,
            'username'      => $approvedByUsername,
            'log_type'      => 'auth',
            'action_type'   => 'approve',
            'action_detail' => '계정승인',
            'success'       => 1,
            'ref_table'     => 'auth_users',
            'ref_id'        => $userId,
            'created_by'    => $approvedByUserId,
        ]);
    }

    // ---------------------------------------------------------------
    // 2FA 인증코드 발송
    // ---------------------------------------------------------------
    public function twoFactorSend(string $userId): void
    {
        $this->writeLog([
            'user_id'     => $userId,
            'log_type'    => 'auth',
            'action_type' => '2fa_send',
            'action_detail' => '2FA 인증코드 발송',
            'success'     => 1,
        ]);
    }

    // ---------------------------------------------------------------
    // 2FA 성공
    // ---------------------------------------------------------------
    public function twoFactorSuccess(string $userId): void
    {
        $this->writeLog([
            'user_id'     => $userId,
            'log_type'    => 'auth',
            'action_type' => '2fa_success',
            'action_detail' => '2FA 성공',
            'success'     => 1,
        ]);
    }

    // ---------------------------------------------------------------
    // 2FA 실패
    // ---------------------------------------------------------------
    public function twoFactorFail(string $userId): void
    {
        $this->writeLog([
            'user_id'     => $userId,
            'log_type'    => 'auth',
            'action_type' => '2fa_fail',
            'action_detail' => '2FA 실패',
            'success'     => 0,
        ]);
    }

    // ---------------------------------------------------------------
    // 로그아웃
    // ---------------------------------------------------------------
    public function logout(string $userId = null, string $username = null): void
    {
        $this->writeLog([
            'user_id'       => $userId,
            'username'      => $username,
            'log_type'      => 'auth',
            'action_type'   => 'logout',
            'action_detail' => '로그아웃',
            'success'       => 1,
            'ref_table'     => 'auth_users',
            'ref_id'        => $userId,
            'created_by'    => $userId,
        ]);
    }


}
