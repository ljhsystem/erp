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
            $this->authLogService->loginFail($username, '嚥≪뮄??紐꾨뼄????낆젾?袁⑥뵭');
            return ['success' => false, 'message' => '?袁⑹뵠?遺? ??쑬?甕곕뜇?뉒몴???낆젾??雅뚯눘苑??'];
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

            $this->authLogService->loginFail($username, "嚥≪뮄??紐꾨뼄????쑬?甕곕뜇???살첒-{$count}", [
                'user_id'   => $userId,
                'ref_table' => 'auth_users',
                'ref_id'    => $userId,
            ]);

            $message = '?袁⑹뵠???癒?뮉 ??쑬?甕곕뜇?뉐첎? ??而?몴?? ??녿뮸??덈뼄.';
            if ($count < $max) {
                $message .= " ({$left}????λ릭??щ빍??)";
            } else {
                $message .= ' (?④쑴????醫됯펷??щ빍??)';
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
                'message'  => '??쑬?甕곕뜇??癰궰野껋럩???袁⑹뒄??몃빍??',
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
                return ['success' => false, 'message' => '2??ｍ??紐꾩쵄 ?꾨뗀諭?獄쏆뮇??餓???살첒揶쎛 獄쏆뮇源??됰뮸??덈뼄.'];
            }

            return [
                'success'  => true,
                'message'  => '2??ｍ??紐꾩쵄 ?꾨뗀諭뜹첎? 獄쏆뮇???뤿???щ빍??',
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
            return ['success' => false, 'message' => '?紐꾩쵄 ?꾨뗀諭띄몴???낆젾??雅뚯눘苑??'];
        }

        $pending = $this->authSessionService->getPendingTwoFactor();
        if (!$this->authSessionService->hasStatus(AuthSessionService::STATUS_TWO_FACTOR_PENDING) || !$pending) {
            return ['success' => false, 'message' => '?紐꾩쵄 ?紐꾨????곷뮸??덈뼄. ??쇰뻻 嚥≪뮄??紐낅퉸 雅뚯눘苑??', 'redirect' => '/login'];
        }

        if (($pending['expires_at'] ?? 0) < time()) {
            $this->authSessionService->clearPendingTwoFactor();
            return ['success' => false, 'message' => '?紐꾩쵄 ?꾨뗀諭뜹첎? 筌띾슢利??뤿???щ빍??', 'redirect' => '/login'];
        }

        $attempts = (int)($pending['attempts'] ?? 0);
        $maxAttempts = (int)($pending['max_attempts'] ?? $this->twoFactorService->getMaxAttempts());
        if ($attempts >= $maxAttempts) {
            $this->authSessionService->clearPendingTwoFactor();
            return ['success' => false, 'message' => '?紐꾩쵄 ??뺣즲 ??쏅땾???λ뜃???됰뮸??덈뼄. ??쇰뻻 嚥≪뮄??紐낅퉸 雅뚯눘苑??', 'redirect' => '/login'];
        }

        $currentAttempts = $this->authSessionService->incrementPendingTwoFactorAttempts();

        if (!$this->twoFactorService->matches($code, (string)($pending['code_hash'] ?? ''))) {
            $userId = $pending['user']['id'] ?? null;
            if ($userId) {
                $this->authLogService->twoFactorFail((string)$userId);
            }

            if ($currentAttempts >= $maxAttempts) {
                $this->authSessionService->clearPendingTwoFactor();
                return ['success' => false, 'message' => '?紐꾩쵄 ??뺣즲 ??쏅땾???λ뜃???됰뮸??덈뼄. ??쇰뻻 嚥≪뮄??紐낅퉸 雅뚯눘苑??', 'redirect' => '/login'];
            }

            return ['success' => false, 'message' => '?紐꾩쵄 ?꾨뗀諭뜹첎? ??而?몴?? ??녿뮸??덈뼄.'];
        }

        $user = $pending['user'] ?? null;
        if (!is_array($user) || empty($user['id'])) {
            $this->authSessionService->clearPendingTwoFactor();
            return ['success' => false, 'message' => '??????類ｋ궖??筌≪뼚??????곷뮸??덈뼄.', 'redirect' => '/login'];
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
            $this->authSessionService->setFlash('two_factor_message', '?紐꾩쵄???袁⑹뒄??몃빍?? ??쇰뻻 嚥≪뮄??紐낅릭?紐꾩뒄.');
            return ['allowed' => false, 'redirect' => '/login'];
        }

        $reasonLabels = [
            'force_2fa'      => '癰귣똻釉??類ㅼ퐠???怨뺤뵬 ??筌욊낯??2??ｍ??紐꾩쵄???袁⑹뒄??몃빍??',
            'user_2fa'       => '?④쑴???2??ｍ??紐꾩쵄????뽮쉐?遺얜┷????됰뮸??덈뼄.',
            'new_device_2fa' => '??덉쨮??疫꿸퀗由?癒?퐣 嚥≪뮄?????뺣즲揶쎛 揶쏅Ŋ???뤿???щ빍??',
            'time_window'    => '??됱뒠??? ??? ??볦퍢????嚥≪뮄?????뺣즲??낅빍??',
            'inactive_guard' => '?觀由겼첎?沃섎챷沅???④쑴??癰귣똾?뉒몴??袁る퉸 ?곕떽? ?紐꾩쵄???袁⑹뒄??몃빍??',
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
            return ['success' => false, 'message' => '嚥≪뮄??紐꾩뵠 ?袁⑹뒄??몃빍??', 'status' => 401];
        }

        $newPassword = trim((string)($data['new_password'] ?? ''));
        $confirmPassword = trim((string)($data['confirm_password'] ?? ''));
        $currentPassword = trim((string)($data['current_password'] ?? ''));
        $isForceChange = $this->authSessionService->hasStatus(AuthSessionService::STATUS_PASSWORD_EXPIRED);

        if ($newPassword === '' || $confirmPassword === '') {
            return ['success' => false, 'message' => '????쑬?甕곕뜇?뉒몴???낆젾??雅뚯눘苑??'];
        }

        if (!$isForceChange && $currentPassword === '') {
            return ['success' => false, 'message' => '?袁⑹삺 ??쑬?甕곕뜇?뉒몴???낆젾??雅뚯눘苑??'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => '????쑬?甕곕뜇?뉐첎? ??깊뒄??? ??녿뮸??덈뼄.'];
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
            return ['success' => false, 'message' => '??쑬?甕곕뜇??筌띾슢利??怨밴묶揶쎛 ?袁⑤뻸??덈뼄.', 'status' => 400];
        }

        $userId = $this->authSessionService->getCurrentUserId();
        if (!$userId) {
            return ['success' => false, 'message' => '??????類ｋ궖??筌≪뼚??????곷뮸??덈뼄.', 'status' => 401];
        }

        $user = $this->authUserModel->getById($userId);
        if (!$user) {
            return ['success' => false, 'message' => '??????類ｋ궖??筌≪뼚??????곷뮸??덈뼄.', 'status' => 404];
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
            return ['success' => false, 'message' => '??????類ｋ궖??筌≪뼚??????곷뮸??덈뼄.'];
        }

        $isForceChange = $this->authSessionService->hasStatus(AuthSessionService::STATUS_PASSWORD_EXPIRED);
        if (!$isForceChange) {
            if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => '?袁⑹삺 ??쑬?甕곕뜇?뉐첎? ??而?몴?? ??녿뮸??덈뼄.'];
            }
        }

        $check = $this->validateNewPassword($userId, $newPassword);
        if (!$check['success']) {
            return $check;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $ok = $this->authUserModel->updatePassword($userId, $hash, $userId);

        if (!$ok) {
            return ['success' => false, 'message' => '??쑬?甕곕뜇??癰궰野껋럩肉???쎈솭??됰뮸??덈뼄.'];
        }

        return ['success' => true, 'message' => '??쑬?甕곕뜇?뉐첎? 癰궰野껋럥由??됰뮸??덈뼄.'];
    }

    public function validateNewPassword(string $userId, string $newPassword): array
    {
        if ($newPassword === '') {
            return ['success' => true];
        }

        if ($this->authUserModel->isSamePassword($userId, $newPassword)) {
            return ['success' => false, 'message' => '疫꿸퀣????쑬?甕곕뜇?????쇰뻻 ?????????곷뮸??덈뼄.'];
        }

        return ['success' => true];
    }

    public function updateUserPassword(string $userId, string $newPlainPassword, string $updatedBy): array
    {
        $hash = password_hash($newPlainPassword, PASSWORD_DEFAULT);
        $ok = $this->authUserModel->updatePassword($userId, $hash, $updatedBy);

        if ($ok) {
            return ['success' => true, 'message' => '??쑬?甕곕뜇?뉐첎? 癰궰野껋럥由??됰뮸??덈뼄.'];
        }

        return ['success' => false, 'message' => '??쑬?甕곕뜇??癰궰野???쎈솭'];
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
            $errors[] = '?袁⑹뵠??username) ?袁⑥뵭';
        }
        if ($password === '') {
            $errors[] = '??쑬?甕곕뜇??password) ?袁⑥뵭';
        }
        if ($employeeName === '') {
            $errors[] = '筌욊낯?앾쭗?employee_name) ?袁⑥뵭';
        }

        if ($errors) {
            return ['success' => false, 'message' => '?袁⑸땾揶??袁⑥뵭', 'errors' => $errors];
        }

        if ($this->authUserModel->getByUsername($username)) {
            return ['success' => false, 'message' => '餓λ쵎????살첒', 'errors' => ['??? ????餓λ쵐???袁⑹뵠?遺우뿯??덈뼄']];
        }

        $userId = UuidHelper::generate();
        $userCode = SequenceHelper::next('auth_users');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();

        try {
            $this->authUserModel->createUser([
                'id'                 => $userId,
                'code'               => $userCode,
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
                'message' => '筌욊낯????밴쉐 ?袁⑥┷',
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => '筌욊낯????밴쉐 ??쎈솭',
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
