<?php

namespace App\Services\Auth;

use PDO;
use App\Models\Auth\UserModel;
use App\Services\Mail\MailService;
use App\Services\User\ProfileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;

class AuthService
{
    private readonly PDO $pdo;
    private UserModel $authUserModel;
    private ProfileService $profileService;
    private LogService $authLogService;
    private AccountLockService $accountLockService;
    private SecurityPolicyService $securityPolicyService;
    private AuthSessionService $authSessionService;
    private MailService $mailService;
    private TwoFactorService $twoFactorService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->authUserModel = new UserModel($pdo);
        $this->profileService = new ProfileService($pdo);
        $this->authLogService = new LogService($pdo);
        $this->accountLockService = new AccountLockService($pdo);
        $this->securityPolicyService = new SecurityPolicyService($pdo);
        $this->authSessionService = new AuthSessionService();
        $this->mailService = new MailService();
        $this->twoFactorService = new TwoFactorService();
        $this->logger = LoggerFactory::getLogger('service-auth.AuthService');
    }

    public function login(array $data): array
    {
        $username = trim((string)($data['username'] ?? ''));
        $password = trim((string)($data['password'] ?? ''));

        if ($username === '' || $password === '') {
            $this->authLogService->loginFail($username, '아이디/비밀번호 미입력');
            return ['success' => false, 'message' => '아이디와 비밀번호를 입력해 주세요.'];
        }

        $user = $this->authUserModel->getByUsername($username);
        if (!$user) {
            $this->authLogService->loginFail($username, 'USER_NOT_FOUND');
            return ['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.'];
        }

        $userId = (string)$user['id'];

        if (!password_verify($password, $user['password'] ?? '')) {
            $this->accountLockService->handleLoginFail($userId);

            $count = $this->authUserModel->getFailCount($userId);
            $max = (int)ConfigHelper::system('security_login_fail_max', 5);
            $left = max(0, $max - $count);

            $this->authLogService->loginFail($username, "비밀번호 불일치-{$count}", [
                'user_id'   => $userId,
                'ref_table' => 'auth_users',
                'ref_id'    => $userId,
            ]);

            $message = '아이디 또는 비밀번호가 올바르지 않습니다.';
            if ($count < $max) {
                $message .= " ({$left}회 남음)";
            } else {
                $message .= ' (계정 잠금 예정)';
            }

            return ['success' => false, 'message' => $message];
        }

        $can = $this->canLogin($user);
        if (!$can['allowed']) {
            return $this->buildBlockedLoginResponse($username, $userId, $can);
        }

        if ($this->isPasswordExpired($user)) {
            $this->handleLoginSuccess($userId, $this->getClientIp());
            $this->authSessionService->createPasswordExpiredSession($user);

            return [
                'success'  => true,
                'reason'   => 'password_expired',
                'message'  => '비밀번호 변경이 필요합니다.',
                'redirect' => '/password/change',
            ];
        }

        $need2fa = $this->needTwoFactor($user);
        if ($need2fa) {
            $code = $this->twoFactorService->generateCode();
            $this->authSessionService->createPendingTwoFactorSession(
                $user,
                $this->securityPolicyService->getTwoFactorReasons($user),
                $this->twoFactorService->hashCode($code),
                $this->twoFactorService->getTtl(),
                $this->twoFactorService->getMaxAttempts()
            );

            try {
                $this->mailService->sendTwoFactorMail([
                    'user' => [
                        'id'              => $userId,
                        'username'        => $user['username'],
                        'email'           => $user['email'] ?? null,
                        'two_factor_code' => $code,
                    ]
                ]);
                $this->authLogService->twoFactorSend($userId);
            } catch (\Throwable $e) {
                $this->authSessionService->clearPendingTwoFactor();
                $this->logger->error('2FA mail send failed', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
                return ['success' => false, 'message' => '2단계 인증 메일 발송에 실패했습니다. 관리자에게 문의하세요.'];
            }

            return [
                'success'  => true,
                'message'  => '2단계 인증 코드를 이메일로 발송했습니다.',
                'redirect' => '/2fa',
            ];
        }

        $this->handleLoginSuccess($userId, $this->getClientIp());
        $this->authSessionService->createLoginSession($user);
        $this->authLogService->loginSuccess($userId, $username, '로그인 성공', [
            'ref_table' => 'auth_users',
            'ref_id'    => $userId,
        ]);

        return [
            'success'  => true,
            'message'  => '로그인되었습니다.',
            'redirect' => '/dashboard',
        ];
    }

    public function verifyTwoFactor(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['success' => false, 'message' => '인증 코드를 입력해 주세요.'];
        }

        $pending = $this->authSessionService->getPendingTwoFactor();
        if (!$this->authSessionService->hasStatus(AuthSessionService::STATUS_TWO_FACTOR_PENDING) || !$pending) {
            return ['success' => false, 'message' => '인증 요청이 유효하지 않습니다. 다시 로그인해 주세요.', 'redirect' => '/login'];
        }

        if (($pending['expires_at'] ?? 0) < time()) {
            $this->authSessionService->clearPendingTwoFactor();
            return ['success' => false, 'message' => '인증 코드가 만료되었습니다.', 'redirect' => '/login'];
        }

        $attempts = (int)($pending['attempts'] ?? 0);
        $maxAttempts = (int)($pending['max_attempts'] ?? $this->twoFactorService->getMaxAttempts());
        if ($attempts >= $maxAttempts) {
            $this->authSessionService->clearPendingTwoFactor();
            return ['success' => false, 'message' => '인증 시도 횟수를 초과했습니다. 다시 로그인해 주세요.', 'redirect' => '/login'];
        }

        $currentAttempts = $this->authSessionService->incrementPendingTwoFactorAttempts();

        if (!$this->twoFactorService->matches($code, (string)($pending['code_hash'] ?? ''))) {
            $userId = $pending['user']['id'] ?? null;
            if ($userId) {
                $this->authLogService->twoFactorFail((string)$userId);
            }

            if ($currentAttempts >= $maxAttempts) {
                $this->authSessionService->clearPendingTwoFactor();
                return ['success' => false, 'message' => '인증 시도 횟수를 초과했습니다. 다시 로그인해 주세요.', 'redirect' => '/login'];
            }

            return ['success' => false, 'message' => '인증 코드가 올바르지 않습니다.'];
        }

        $user = $pending['user'] ?? null;
        if (!is_array($user) || empty($user['id'])) {
            $this->authSessionService->clearPendingTwoFactor();
            return ['success' => false, 'message' => '사용자 정보를 찾을 수 없습니다.', 'redirect' => '/login'];
        }

        $userId = (string)$user['id'];
        $this->handleLoginSuccess($userId, $this->getClientIp());
        $this->authSessionService->createLoginSession($user);
        $this->authLogService->twoFactorSuccess($userId);
        $this->authLogService->loginSuccess($userId, (string)($user['username'] ?? ''), '2FA 로그인 성공', [
            'ref_table' => 'auth_users',
            'ref_id'    => $userId,
        ]);

        return ['success' => true, 'message' => '로그인되었습니다.', 'redirect' => '/dashboard'];
    }

    public function getTwoFactorPageData(): array
    {
        $pending = $this->authSessionService->getPendingTwoFactor();
        if (!$this->authSessionService->hasStatus(AuthSessionService::STATUS_TWO_FACTOR_PENDING) || !$pending) {
            $this->authSessionService->setFlash('two_factor_message', '인증 요청이 만료되었습니다. 다시 로그인해 주세요.');
            return ['allowed' => false, 'redirect' => '/login'];
        }

        $reasonLabels = [
            'force_2fa'      => '관리자 정책에 따라 2단계 인증이 필요합니다.',
            'user_2fa'       => '사용자 계정에 2단계 인증이 설정되어 있습니다.',
            'new_device_2fa' => '새 기기 또는 새 위치에서 로그인하여 추가 인증이 필요합니다.',
            'time_window'    => '로그인 허용 시간 외 접근으로 추가 인증이 필요합니다.',
            'inactive_guard' => '장기 미접속 계정 보호 정책에 따라 2단계 인증이 필요합니다.',
        ];

        $activeReasons = [];
        foreach (($pending['reasons'] ?? []) as $key => $enabled) {
            if (!empty($enabled) && isset($reasonLabels[$key])) {
                $activeReasons[] = $reasonLabels[$key];
            }
        }

        return [
            'allowed'       => true,
            'email'         => $pending['user']['email'] ?? null,
            'activeReasons' => $activeReasons,
            'message'       => $this->authSessionService->pullFlash('two_factor_message'),
        ];
    }

    public function getPasswordChangePageData(): array
    {
        if ($this->authSessionService->hasStatus(AuthSessionService::STATUS_PASSWORD_EXPIRED)) {
            return [
                'allowed'       => true,
                'isForceChange' => true,
                'message'       => $this->authSessionService->pullFlash('password_message'),
            ];
        }

        if ($this->authSessionService->isAuthenticated()) {
            return [
                'allowed'       => true,
                'isForceChange' => false,
                'message'       => $this->authSessionService->pullFlash('password_message'),
            ];
        }

        return ['allowed' => false, 'redirect' => '/login'];
    }

    public function changePassword(array $data): array
    {
        $userId = $this->authSessionService->getCurrentUserId();
        if (!$userId) {
            return ['success' => false, 'message' => '로그인이 필요합니다.', 'status' => 401];
        }

        $newPassword = trim((string)($data['new_password'] ?? ''));
        $confirmPassword = trim((string)($data['confirm_password'] ?? ''));
        $currentPassword = trim((string)($data['current_password'] ?? ''));
        $isForceChange = $this->authSessionService->hasStatus(AuthSessionService::STATUS_PASSWORD_EXPIRED);

        if ($newPassword === '' || $confirmPassword === '') {
            return ['success' => false, 'message' => '새 비밀번호를 입력해 주세요.'];
        }

        if (!$isForceChange && $currentPassword === '') {
            return ['success' => false, 'message' => '현재 비밀번호를 입력해 주세요.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => '새 비밀번호와 확인 값이 일치하지 않습니다.'];
        }

        $result = $this->changePasswordWithVerify($userId, $isForceChange ? null : $currentPassword, $newPassword);
        if (!$result['success']) {
            return $result;
        }

        $user = $this->authUserModel->getById($userId);
        if ($user) {
            $this->authSessionService->markPasswordExpiredFlowComplete($user);
        }
        $this->authLogService->passwordChanged($userId);

        return [
            'success'  => true,
            'message'  => $result['message'],
            'redirect' => '/dashboard',
        ];
    }

    public function changePasswordLater(): array
    {
        if (!$this->authSessionService->hasStatus(AuthSessionService::STATUS_PASSWORD_EXPIRED)) {
            return ['success' => false, 'message' => '비밀번호 변경을 연기할 수 없는 상태입니다.', 'status' => 400];
        }

        $userId = $this->authSessionService->getCurrentUserId();
        if (!$userId) {
            return ['success' => false, 'message' => '사용자 정보를 찾을 수 없습니다.', 'status' => 401];
        }

        $user = $this->authUserModel->getById($userId);
        if (!$user) {
            return ['success' => false, 'message' => '사용자 정보를 찾을 수 없습니다.', 'status' => 404];
        }

        $this->authSessionService->createLoginSession($user);

        return ['success' => true, 'redirect' => '/dashboard'];
    }

    public function logoutCurrentSession(): void
    {
        $user = $this->authSessionService->getCurrentUser();
        $userId = $user['id'] ?? null;
        $username = $user['username'] ?? null;

        if ($userId || $username) {
            $this->authLogService->logout($userId, $username);
        }

        $this->authSessionService->destroyAuthSession();
    }

    public function canLogin(array $authUser): array
    {
        if (!empty($authUser['account_locked_until']) && strtotime($authUser['account_locked_until']) > time()) {
            return [
                'allowed' => false,
                'reason'  => 'locked',
                'until'   => $authUser['account_locked_until'],
            ];
        }

        if ((int)($authUser['approved'] ?? 0) !== 1) {
            return ['allowed' => false, 'reason' => 'not_approved'];
        }

        if ((int)($authUser['is_active'] ?? 0) !== 1) {
            return ['allowed' => false, 'reason' => 'inactive'];
        }

        return ['allowed' => true];
    }

    public function handleLoginSuccess(string $userId, string $ip): void
    {
        $device = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $this->authUserModel->updateLastLogin($userId, $ip, $device);
    }

    public function changePasswordWithVerify(string $userId, ?string $currentPassword, string $newPassword): array
    {
        $user = $this->authUserModel->getById($userId);
        if (!$user || empty($user['password'])) {
            return ['success' => false, 'message' => '사용자 정보를 찾을 수 없습니다.'];
        }

        $isForceChange = $this->authSessionService->hasStatus(AuthSessionService::STATUS_PASSWORD_EXPIRED);
        if (!$isForceChange) {
            if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => '현재 비밀번호가 올바르지 않습니다.'];
            }
        }

        $check = $this->validateNewPassword($userId, $newPassword);
        if (!$check['success']) {
            return $check;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $ok = $this->authUserModel->updatePassword($userId, $hash, $userId);

        if (!$ok) {
            return ['success' => false, 'message' => '비밀번호 변경에 실패했습니다.'];
        }

        return ['success' => true, 'message' => '비밀번호가 변경되었습니다.'];
    }

    public function validateNewPassword(string $userId, string $newPassword): array
    {
        if ($newPassword === '') {
            return ['success' => true];
        }

        if ($this->authUserModel->isSamePassword($userId, $newPassword)) {
            return ['success' => false, 'message' => '이전 비밀번호와 동일한 비밀번호는 사용할 수 없습니다.'];
        }

        return ['success' => true];
    }

    public function updateUserPassword(string $userId, string $newPlainPassword, string $updatedBy): array
    {
        $hash = password_hash($newPlainPassword, PASSWORD_DEFAULT);
        $ok = $this->authUserModel->updatePassword($userId, $hash, $updatedBy);

        if ($ok) {
            return ['success' => true, 'message' => '비밀번호가 변경되었습니다.'];
        }

        return ['success' => false, 'message' => '비밀번호 변경 실패'];
    }

    public function isPasswordExpired(array $user): bool
    {
        $policyEnabled = (int)ConfigHelper::system('security_password_policy_enabled', 0);
        if ($policyEnabled !== 1) {
            return false;
        }

        $expireDays = (int)ConfigHelper::system('security_password_expire', 0);
        if ($expireDays <= 0) {
            return false;
        }

        if (empty($user['password_updated_at'])) {
            return true;
        }

        $updatedAt = strtotime($user['password_updated_at']);
        $expireAt = strtotime("+{$expireDays} days", $updatedAt);

        return $expireAt < time();
    }

    public function createUserWithProfile(array $data): array
    {
        $username = trim((string)($data['username'] ?? ''));
        $password = trim((string)($data['password'] ?? ''));
        $employeeName = trim((string)($data['employee_name'] ?? ''));
        $createdBy = $data['created_by'] ?? null;

        $errors = [];
        if ($username === '') {
            $errors[] = '아이디(username)는 필수입니다.';
        }
        if ($password === '') {
            $errors[] = '비밀번호(password)는 필수입니다.';
        }
        if ($employeeName === '') {
            $errors[] = '직원명(employee_name)은 필수입니다.';
        }

        if ($errors) {
            return ['success' => false, 'message' => '필수값이 누락되었습니다.', 'errors' => $errors];
        }

        if ($this->authUserModel->getByUsername($username)) {
            return ['success' => false, 'message' => '중복된 아이디', 'errors' => ['이미 사용 중인 아이디입니다.']];
        }

        $userId = UuidHelper::generate();
        $userCode = SequenceHelper::next('auth_users', 'sort_no');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();

        try {
            $this->authUserModel->createUser([
                'id'                 => $userId,
                'sort_no'            => $userCode,
                'username'           => $username,
                'password'           => $passwordHash,
                'role_id'            => $data['role_id'] ?? null,
                'is_active'          => 1,
                'approved'           => 0,
                'email_notify'       => 1,
                'sms_notify'         => 0,
                'two_factor_enabled' => 0,
                'created_by'         => $createdBy,
            ]);

            $this->profileService->save([
                'user_id'       => $userId,
                'sort_no'       => null,
                'employee_name' => $employeeName,
                'department_id' => $data['department_id'] ?? null,
                'position_id'   => $data['position_id'] ?? null,
                'created_by'    => $createdBy,
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'user_id' => $userId,
                'code'    => $userCode,
                'message' => '사용자가 생성되었습니다.',
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => '사용자 생성 실패',
                'error'   => $e->getMessage(),
            ];
        }
    }

    private function buildBlockedLoginResponse(string $username, string $userId, array $can): array
    {
        $reason = (string)($can['reason'] ?? 'unknown');
        $extra = [
            'user_id' => $userId,
            'reason' => $reason,
        ];

        if ($reason === 'locked') {
            $until = (string)($can['locked_until'] ?? '');
            $minutesLeft = (int)($can['minutes_left'] ?? 0);
            $this->authLogService->loginFail($username, '계정 잠금', $extra);

            return [
                'success' => false,
                'message' => $minutesLeft > 0 ? "계정이 잠겨 있습니다. {$minutesLeft}분 후 다시 시도하세요." : '계정이 잠겨 있습니다.',
                'locked_until' => $until,
                'minutes_left' => $minutesLeft,
            ];
        }

        if ($reason === 'not_approved') {
            $this->authLogService->loginFail($username, '승인 대기', $extra);
            return [
                'success' => false,
                'message' => '관리자 승인 후 로그인할 수 있습니다.',
                'redirect' => '/waiting_approval',
            ];
        }

        if ($reason === 'inactive') {
            $this->authLogService->loginFail($username, '비활성 계정', $extra);
            return ['success' => false, 'message' => '비활성 계정입니다. 관리자에게 문의하세요.'];
        }

        $this->authLogService->loginFail($username, '로그인 차단', $extra);
        return ['success' => false, 'message' => '로그인할 수 없습니다.'];
    }
    private function needTwoFactor(array $user): bool
    {
        $securityPolicyEnabled = (int)ConfigHelper::system('security_access_policy_enabled', 0) === 1;
        $timeOutside = $securityPolicyEnabled && $this->securityPolicyService->isOutsideAllowedTime();
        $timeMode = (string)ConfigHelper::system('security_login_time_mode', '2fa');

        if ($timeOutside && $timeMode === 'block') {
            return false;
        }

        $inactiveDays = 0;
        if (!empty($user['last_login'])) {
            $inactiveDays = (int) floor((time() - strtotime($user['last_login'])) / 86400);
        }

        $inactiveLockDays = (int)ConfigHelper::system('security_inactive_lock_days', 10);
        if ($inactiveLockDays > 0 && $inactiveDays >= $inactiveLockDays) {
            $this->accountLockService->lockAccount((string)$user['id'], 30);
            return false;
        }

        return $this->securityPolicyService->needTwoFactor($user);
    }

    private function getClientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';

        $external = '';
        if ($forwarded !== '') {
            $list = explode(',', $forwarded);
            $external = trim($list[0]);
        } elseif ($realIp !== '') {
            $external = $realIp;
        }

        if ($external === '') {
            $external = $remote;
        }

        return ($external !== '' && $external !== $remote)
            ? "{$external} ({$remote})"
            : $remote;
    }
}
