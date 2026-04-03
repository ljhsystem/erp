<?php
// 경로: PROJECT_ROOT . '/app/services/auth/AuthService.php'
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\AuthUserModel;
use App\Services\Auth\LogService;
use App\Services\User\ProfileService;
use App\Services\Auth\AccountLockService;
use App\Services\Auth\SecurityPolicyService;
use App\Services\Mail\TwoFactorMail;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ConfigHelper;
use Core\LoggerFactory;


class AuthService
{
    private readonly PDO $pdo;
    private $authUserModel;
    private $profileService;
    private $authLogService;
    private $accountLockService;
    private $securityPolicyService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->authUserModel  = new AuthUserModel($pdo);
        $this->profileService  = new ProfileService($pdo);
        $this->authLogService  = new LogService($pdo);
        $this->accountLockService = new AccountLockService($pdo);
        $this->securityPolicyService = new SecurityPolicyService($pdo); 
        $this->logger          = LoggerFactory::getLogger('service-auth.AuthService');
        $this->logger->info('AuthService::__construct', [
            'class' => __CLASS__
        ]);
     
    }
  
    /* ============================================================
     * 1) 로그인 가능 여부 체크 (잠금/승인/활성)
     * ============================================================ */
    public function canLogin(array $authUser): array
    {
        $this->logger->info('canLogin 호출', [
            'user_id' => $authUser['id'] ?? null
        ]);

        // 1. 잠금 여부 체크
        if (!empty($authUser['account_locked_until'])) {
            if (strtotime($authUser['account_locked_until']) > time()) {
                $this->logger->warning('로그인 불가 - 계정 잠금 상태', [
                    'user_id' => $authUser['id'] ?? null,
                    'until'   => $authUser['account_locked_until']
                ]);
                return [
                    'allowed' => false,
                    'reason'  => 'locked',
                    'until'   => $authUser['account_locked_until']
                ];
            }
        }

        // 2. 승인 여부 체크
        if ((int)$authUser['approved'] !== 1) {
            $this->logger->info('로그인 불가 - 승인되지 않은 계정', [
                'user_id' => $authUser['id'] ?? null
            ]);
            return [
                'allowed' => false,
                'reason'  => 'not_approved'
            ];
        }

        // 3. 활성 여부 체크
        if ((int)$authUser['is_active'] !== 1) {
            $this->logger->info('로그인 불가 - 비활성 계정', [
                'user_id' => $authUser['id'] ?? null
            ]);
            return [
                'allowed' => false,
                'reason'  => 'inactive'
            ];
        }

        // 4. 로그인 가능 로그
        $this->logger->info('로그인 가능 상태', [
            'user_id' => $authUser['id'] ?? null
        ]);

        return ['allowed' => true];
    }

    
    /* ============================================================
     * 2) 실제 로그인 처리 (로그인 + DB 로그 기록)
     * ============================================================ */
    public function login(array $data, TwoFactorService $twoFactor, \App\Services\Mail\MailService $mailer): array
    {      

        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');

        // 호출 시작 로그
        $this->logger->info('login() 호출', [
            'username' => $username,
            'has_password' => $password !== ''
        ]);
        /* ============================================================
        * 1. 입력 검증
        * ============================================================ */
        if ($username === '' || $password === '') {
            // 로그인 실패: 입력 누락
            $this->logger->warning('로그인 실패 - 입력 누락', [
                'username' => $username
            ]);

            $this->authLogService->loginFail($username, '로그인실패:입력누락');
            return ['success' => false, 'message' => '아이디와 비밀번호를 입력해 주세요.'];
        }

        /* ============================================================
        * 2. 사용자 조회
        * ============================================================ */
        $user = $this->authUserModel->getByUsername($username);
        if (!$user) {
            // 로그인 실패: 사용자 없음
            $this->authLogService->loginFail($username, '로그인실패:아이디없음');
            $this->logger->info('로그인 실패 - 존재하지 않는 아이디', ['username' => $username]);
            return ['success' => false, 'message' => '아이디 또는 비밀번호가 올바르지 않습니다.'];
        }
   

        $userId = $user['id'];

        $this->logger->info('login() 사용자 조회 완료', [
            'user_id'  => $userId,
            'username' => $username
        ]);

        /* ============================================================
        * 3. 비밀번호 검증
        * ============================================================ */
        if (!password_verify($password, $user['password'] ?? '')) {
            $this->logger->warning('로그인 실패 - 비밀번호 불일치', [
                'user_id'  => $userId,
                'username' => $username
            ]);

            $this->accountLockService->handleLoginFail($userId);

            // 실패 횟수 조회
            $count = $this->authUserModel->getFailCount($userId);
            $max = (int) ConfigHelper::system('security_login_fail_max', 5);
            $left = max(0, $max - $count);

            // 로그인 실패 로그: 실패횟수 + ref_table/ref_id 포함
            $detail = "로그인실패 실패횟수-{$count}";
            $this->authLogService->loginFail($username, $detail, [
                'user_id'   => $userId,
                'ref_table' => 'auth_users',
                'ref_id'    => $userId,
            ]);

            $msg = '아이디 또는 비밀번호가 올바르지 않습니다.';
            if ($count < $max) {
                $msg .= " (잠금까지 {$left}회 남았습니다)";
            } else {
                $msg .= ' (계정이 잠겼습니다)';
            }

            return ['success' => false, 'message' => $msg];
        }

        $this->logger->info('비밀번호 검증 성공', [
            'user_id'  => $userId,
            'username' => $username
        ]);

        /* ============================================================
        * 4. 계정 상태 체크
        * ============================================================ */
        $can = $this->canLogin($user);
        if (empty($can['allowed'])) {
            $reason = $can['reason'] ?? 'unknown';

            $this->logger->warning('로그인 불가 상태', [
                'user_id' => $userId,
                'reason'  => $reason,
                'until'   => $can['until'] ?? null
            ]);

            // 상태별 실패 로그: ref_table/ref_id 추가
            $extra = [
                'user_id'   => $userId,
                'ref_table' => 'auth_users',
                'ref_id'    => $userId,
            ];
            if ($reason === 'locked') {
                $this->authLogService->loginFail($username, '미승인/비활성계정:잠금', $extra);
            } elseif ($reason === 'not_approved') {
                $this->authLogService->loginFail($username, '미승인/비활성계정:승인대기', $extra);
            } elseif ($reason === 'inactive') {
                $this->authLogService->loginFail($username, '미승인/비활성계정:비활성', $extra);
            } else {
                $this->authLogService->loginFail($username, '로그인실패:상태이슈', $extra);
            }

            if ($reason === 'locked') {
                $until       = $can['until'] ?? null;
                $lockedUntil = $until ? strtotime($until) : 0;
                $minutesLeft = $lockedUntil > time()
                    ? (int)ceil(($lockedUntil - time()) / 60)
                    : 0;

                if ($minutesLeft > 0) {
                    $msg = "계정이 잠겼습니다. 약 {$minutesLeft}분 후 다시 로그인해 주세요.";
                } else {
                    $msg = '계정이 잠겨 있습니다. 잠시 후 다시 로그인해 주세요.';
                }

                return [
                    'success'      => false,
                    'message'      => $msg,
                    'locked_until' => $until,
                    'minutes_left' => $minutesLeft,
                ];
            }

            if ($reason === 'not_approved') {
                // ✅ 승인 대기 계정: 메시지 + waiting_approval 로 이동하도록 안내
                return [
                    'success'  => false,
                    'message'  => '관리자 승인 후 로그인 가능합니다.',
                    'redirect' => '/waiting_approval',
                ];
            }

            if ($reason === 'inactive') {
                return ['success' => false, 'message' => '비활성화된 계정입니다. 관리자에게 문의하세요.'];
            }

            return ['success' => false, 'message' => '로그인할 수 없는 계정입니다.'];
        }


    
        /* ============================================================
        * 5. 비밀번호 만료 체크
        * ============================================================ */
        if ($this->isPasswordExpired($user)) {

            // ✅ 로그인 성공 처리 (중요!)
            $internal = $_SERVER['REMOTE_ADDR'] ?? '';
            $external = $this->getExternalIp();
            
            $ip = $external
                ? "{$external} ({$internal})"
                : $internal;
            
            $this->handleLoginSuccess($userId, $ip);
        
            // ✅ 최소 로그인 세션 생성
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'username'  => $user['username'],
                'role_id'   => $user['role_id'] ?? null,
                'role_key'  => $user['role_key'] ?? null,
                'role_name' => $user['role_name'] ?? null,
                'email'     => $user['email'] ?? null,
            ];
        
            // ✅ “제한 로그인” 플래그
            $_SESSION['pw_change_required'] = true;
        
            // ✅ 세션 타임아웃도 동일하게
            $timeoutMinutes = (int) ConfigHelper::system('session_timeout', 30);
            $_SESSION['expire_time'] = time() + ($timeoutMinutes * 60);
        
            $this->logger->warning('비밀번호 만료 - 제한 로그인', [
                'user_id' => $userId,
                'password_updated_at' => $user['password_updated_at'] ?? null
            ]);
        
            return [
                'success'  => true,
                'reason'   => 'password_expired',
                'redirect' => '/password/change'
            ];
        }
        

        $this->logger->debug('DEBUG password expire check', [
            'policyEnabled' => ConfigHelper::system('security_password_policy_enabled'),
            'expireDays'    => ConfigHelper::system('security_password_expire'),
            'updated_at'    => $user['password_updated_at'],
            'expired'       => $this->isPasswordExpired($user),
        ]);   


        /* ============================================================
        * 6. 🔐 접근 보안 정책 판단 (결정 단계)
        * ============================================================ */
        // 접근 보안 강화 ON/OFF
        $securityPolicyEnabled = (int) ConfigHelper::system('security_access_policy_enabled', 0) === 1;




        /* ============================================================
        * 7. 로그인 허용 시간대 (⭐ 반드시 먼저)
        * ============================================================ */

        $timeOutside = $securityPolicyEnabled
        && $this->securityPolicyService->isOutsideAllowedTime();
    
        $timeMode = (string)ConfigHelper::system('security_login_time_mode', '2fa'); // '2fa' or 'block'
        
        if ($timeOutside && $timeMode === 'block') {
            return ['success' => false, 'message' => '현재 시간에는 로그인이 허용되지 않습니다.'];
        }


        /* ============================================================
        * 8. 장기 미접속 계정 보호
        * ============================================================ */

        $inactiveDays = 0;
        if (!empty($user['last_login'])) {
            $inactiveDays = (int) floor((time() - strtotime($user['last_login'])) / 86400);
        }
        
        // 설정값
        $inactive2faDays  = (int) ConfigHelper::system('security_inactive_2fa_days', 3);   // 추가인증
        $inactiveLockDays = (int) ConfigHelper::system('security_inactive_lock_days', 10); // 계정잠금
        
        // 🔴 장기 미접속 → 계정 잠금
        if ($inactiveLockDays > 0 && $inactiveDays >= $inactiveLockDays) {
        
            $this->accountLockService->lockAccount($userId, 30); // 30분 or 정책값
        
            return [
                'success' => false,
                'message' => '장기간 미접속으로 계정이 잠겼습니다. 관리자에게 문의하세요.'
            ];
        }

        // 🟡 장기 미접속 → 추가 인증
        $inactiveGuard2fa = ($inactive2faDays > 0 && $inactiveDays >= $inactive2faDays);


        $this->logger->info('장기 미접속 정책 체크', [
            'user_id'        => $userId,
            'inactive_days'  => $inactiveDays,
            '2fa_days'       => $inactive2faDays,
            'lock_days'      => $inactiveLockDays,
        ]);       

        
 
 

        /* ============================================================
        * 9. 2FA 판단
        * ============================================================ */
        // 전 직원 2FA 강제
        $force2fa = $securityPolicyEnabled
            && (int) ConfigHelper::system('security_force_2fa', 0) === 1;
        // 사용자 개별 2FA
        $user2fa = !empty($user['two_factor_enabled'])
            && (int) $user['two_factor_enabled'] === 1;

        $newDevice2fa = $securityPolicyEnabled
            && (int) ConfigHelper::system('security_new_device_2fa', 0) === 1
            && $this->securityPolicyService->isNewDevice($user);

            $need2fa =
            $force2fa ||
            $user2fa ||
            $newDevice2fa ||
            ($timeOutside && $timeMode === '2fa') ||
            $inactiveGuard2fa;


        $this->logger->info('2FA 정책 판단', [
            'user_id' => $userId,
            'reason'  => [
                'force_2fa'      => $force2fa,
                'user_2fa'       => $user2fa,
                'new_device_2fa' => $newDevice2fa,
            ],
            'final' => $need2fa
        ]);

        /* ============================================================
        * 10. 🔑 2FA 발생 사유 정리
        * ============================================================ */
        $reasons = [
            'force_2fa'      => $force2fa,
            'user_2fa'       => $user2fa,
            'new_device_2fa' => $newDevice2fa,
            'time_window'    => ($timeOutside && $timeMode === '2fa'),
            'inactive_guard' => $inactiveGuard2fa,
        ];

        /* ============================================================
        * 11. 🔐 2FA 필요 시 (로그인 미완료)
        * ============================================================ */
        if ($need2fa) {
            // 2FA 코드 생성 + pending_2fa 세션 생성
            $code = $twoFactor->createPendingSession([
                'id'         => $user['id'],
                'username'   => $user['username'],
                'email'      => $user['email'] ?? null,
                'role_id'    => $user['role_id'] ?? null,
                'role_key'   => $user['role_key'] ?? null,
                'role_name'  => $user['role_name'] ?? null,
                'reasons'   => $reasons,
            ]);

            $this->logger->info('2FA pending 세션 생성', [
                'user_id' => $userId,
                'username'=> $username
            ]);

            // TODO: 메일/SMS 발송 서비스와 연동 (여기서는 MailService)
            try {
                if (!empty($user['email'])) {
                    // ✅ TwoFactorMail 로 실제 메일 발송
                    $this->logger->info('2FA 코드 메일 발송 시도', [
                        'user_id' => $userId,
                        'email'   => $user['email']
                    ]);

                    $twoFactorMail = new TwoFactorMail(new \App\Services\Mail\Mailer());
                    $twoFactorMail->send([
                        'user' => [
                            'id'             => $user['id'],
                            'username'       => $user['username'],
                            'email'          => $user['email'],
                            'two_factor_code'=> $code,
                        ]
                    ]);

                    $this->logger->info('2FA 코드 메일 발송 성공', [
                        'user_id' => $userId
                    ]);

                } else {
                    $this->logger->warning('2FA 코드 발송 실패 - 이메일 없음', [
                        'user_id' => $userId,
                        'username'=> $username
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('2FA 코드 발송 실패', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage()
                ]);
                return ['success' => false, 'message' => '2단계 인증 코드 발송 중 오류가 발생했습니다.'];
            }

            // ✅ 아직 로그인 세션( $_SESSION['user'] )은 만들지 않고,
            //    2FA 화면으로 이동하도록 redirect 정보를 반환
            $this->logger->info('2FA 단계로 리다이렉트', [
                'user_id'  => $userId,
                'redirect' => '/2fa',
            ]);

            return [
                'success'  => true,
                'message'  => '2단계 인증 코드가 발송되었습니다.',
                'redirect' => '/2fa',
            ];
        }

        $this->logger->info('로그인 세션 설정 (2FA 미사용 계정)', [
            'user_id' => $userId
        ]);



        /* ============================================================
        * 12. ✅ 최종 로그인 성공
        * ============================================================ */
       
        $internal = $_SERVER['REMOTE_ADDR'] ?? '';
        $external = $this->getExternalIp();
        
        $ip = $external
            ? "{$external} ({$internal})"
            : $internal;
        
        $this->handleLoginSuccess($userId, $ip);

        $_SESSION['user'] = [
            'id'        => $user['id'],
            'username'  => $user['username'],
            'role_id'   => $user['role_id'] ?? null,
            'role_key'  => $user['role_key'] ?? null,
            'role_name' => $user['role_name'] ?? null,
            'email'     => $user['email'] ?? null,
        ];

        /* 🔥🔥🔥 핵심 추가 (없으면 지금 현상 발생) 🔥🔥🔥 */
        $timeoutMinutes = (int) ConfigHelper::system('session_timeout', 30);
        if ($timeoutMinutes <= 0) {
            $timeoutMinutes = 30;
        }
        $_SESSION['expire_time'] = time() + ($timeoutMinutes * 60);
        /* 🔥🔥🔥 여기까지 🔥🔥🔥 */

    
        // 로그인 성공 로그: action_detail + ref_table/ref_id 포함
        $this->authLogService->loginSuccess($userId, $username, '정상로그인성공', [
            'ref_table' => 'auth_users',
            'ref_id'    => $userId,
        ]);

        $this->logger->info('로그인 세션 부여 완료', [
            'user_id'  => $userId,
            'username' => $username,
            'redirect' => '/dashboard'
        ]);

        return [
            'success'  => true,
            'message'  => '로그인 성공',
            'redirect' => '/dashboard',
        ];
    }




    /* ============================================================
    * 3) 로그인 성공 처리
    * ============================================================ */
    public function handleLoginSuccess(string $userId, string $ip): void
    {
        $device = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $this->logger->info('handleLoginSuccess 호출', [
            'user_id' => $userId,
            'ip'      => $ip,
            'device'  => $device
        ]);

        // ⭐ 로그인 성공의 단일 진입점
        // - 실패 횟수 초기화
        // - 잠금 해제 효과
        // - 마지막 로그인/IP/디바이스 기록
        $this->authUserModel->updateLastLogin($userId, $ip, $device);

        $this->logger->info('로그인 성공 처리 완료', [
            'user_id' => $userId,
            'ip'      => $ip
        ]);
    }



   /* ============================================================
    * 4) 로그아웃 처리 + DB 로그 기록
    * ============================================================ */
    public function logout(?string $userId, ?string $username): void
    {
        try {
            if (!$userId || !$username) {
                return;
            }

            // ✅ DB 로그 (auth_logs)
            $this->authLogService->logout($userId, $username);

            // ✅ 파일/시스템 로그
            $this->logger->info('로그아웃 처리 완료', [
                'user_id'  => $userId,
                'username' => $username
            ]);

        } catch (\Throwable $e) {
            // ❗ 로그 실패는 로그인 흐름을 막지 않음
            $this->logger->error('로그아웃 로그 기록 실패', [
                'user_id' => $userId,
                'error'   => $e->getMessage()
            ]);
        }
    }



 /* ============================================================
    * 5) 비밀번호 변경 (검증 포함 통합)
    * ============================================================ */
    public function changePasswordWithVerify(
        string $userId,
        ?string $currentPassword,
        string $newPassword
    ): array
    {
        // 1️⃣ 사용자 조회
        $user = $this->authUserModel->getById($userId);
        if (!$user || empty($user['password'])) {
            return [
                'success' => false,
                'message' => '사용자 정보를 찾을 수 없습니다.'
            ];
        }
    
        // 🔥 강제 변경 여부
        $isForceChange = !empty($_SESSION['pw_change_required']);
    
        // 2️⃣ 일반 변경일 때만 현재 비밀번호 검증
        if (!$isForceChange) {
            if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                return [
                    'success' => false,
                    'message' => '현재 비밀번호가 올바르지 않습니다.'
                ];
            }
        }
    
        // 3️⃣ 기존 비밀번호 재사용 방지
        $check = $this->validateNewPassword($userId, $newPassword);
        if (!$check['success']) {
            return $check;
        }
    
        // 4️⃣ 변경 처리
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $ok   = $this->authUserModel->updatePassword($userId, $hash, $userId);
    
        if (!$ok) {
            return [
                'success' => false,
                'message' => '비밀번호 변경에 실패했습니다.'
            ];
        }
    
        // 5️⃣ 강제 변경 플래그 해제
        if ($isForceChange) {
            unset($_SESSION['pw_change_required']);
        }
    
        return [
            'success' => true,
            'message' => '비밀번호가 변경되었습니다.'
        ];
    }
    


     /* ============================================================
     * 6) 새 비밀번호가 기존 비밀번호와 동일한지 검사
     * ============================================================ */
    public function validateNewPassword(string $userId, string $newPassword): array
    {
        // 비밀번호를 아예 변경하지 않는 경우
        if ($newPassword === "" || $newPassword === null) {
            return ['success' => true];
        }
    
        // 기존 비밀번호와 동일한지 검사
        if ($this->authUserModel->isSamePassword($userId, $newPassword)) {
            return [
                'success' => false,
                'message' => '기존 비밀번호는 사용할 수 없습니다.'
            ];
        }
    
        return ['success' => true];
    }
     /* ============================================================
     * 7) 비밀번호 변경
     * ============================================================ */
    public function changePassword(string $userId, string $newPlainPassword, string $updatedBy): array
    {
        $hash = password_hash($newPlainPassword, PASSWORD_DEFAULT);

        $ok = $this->authUserModel->updatePassword($userId, $hash, $updatedBy);

        if ($ok) {
            return ['success' => true, 'message' => '비밀번호가 변경되었습니다.'];
        }
        return ['success' => false, 'message' => '비밀번호 변경 실패'];
    }
   


    /* ============================================================
    * 8) 비밀번호 만료 여부 체크 (password_updated_at 기준)
    * ============================================================ */
    public function isPasswordExpired(array $user): bool
    {
        // 1️⃣ 비밀번호 정책 ON/OFF
        $policyEnabled = (int) ConfigHelper::system('security_password_policy_enabled', 0);
        if ($policyEnabled !== 1) {
            return false; // 🔥 정책 OFF면 절대 만료 안 됨
        }
    
        // 2️⃣ 만료 일수
        $expireDays = (int) ConfigHelper::system('security_password_expire', 0);
        if ($expireDays <= 0) {
            return false; // 만료일 설정 안 됨
        }
    
        // 3️⃣ 변경 이력 없음 → 강제 만료
        if (empty($user['password_updated_at'])) {
            return true;
        }
    
        $updatedAt = strtotime($user['password_updated_at']);
        $expireAt  = strtotime("+{$expireDays} days", $updatedAt);
    
        return $expireAt < time();
    }

    /* ============================================================
    * 9) 직원(사용자) 추가
    * 기능: auth_users + user_profiles 동시에 생성
    * 역할:
    *   - UUID 발급
    *   - USER CODE 자동생성 (auth_users.code)
    *   - EMPLOYEE CODE 자동생성 (user_profiles.code)
    *   - 트랜잭션 관리
    * ============================================================ */
    public function createUserWithProfile(array $data): array
    {
        // -----------------------------
        // 1) 입력값 정리
        // -----------------------------
        $username      = trim($data['username'] ?? '');
        $password      = trim($data['password'] ?? '');
        $employeeName  = trim($data['employee_name'] ?? '');
        
        $errors = [];

        if (!$username) {
            $errors[] = '아이디(username) 누락';
        }
        
        if (!$password) {
            $errors[] = '비밀번호(password) 누락';
        }
        
        if (!$employeeName) {
            $errors[] = '직원명(employee_name) 누락';
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => '필수값 누락',
                'errors'  => $errors
            ];
        }
        
        // 🔥 여기로 내려야 맞다
        $exists = $this->authUserModel->getByUsername($username);
        
        if ($exists) {
            return [
                'success' => false,
                'message' => '중복 오류',
                'errors'  => ['이미 사용 중인 아이디입니다']
            ];
        }

        // -----------------------------
        // 2) 기본 값 생성 (UUID + CODE)
        // -----------------------------
        $userId       = UuidHelper::generate();                // UUID
        $userCode     = CodeHelper::generateUserCode($this->pdo);       // auth_users.code
        $employeeCode = CodeHelper::generateEmployeeCode($this->pdo);   // user_profiles.code

        $adminId      = $_SESSION['user']['id'] ?? null;
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // -----------------------------
        // 3) 트랜잭션 시작
        // -----------------------------
        $this->pdo->beginTransaction();

        try {
            /* ---------------------------------------------------
            * (1) auth_users INSERT
            * --------------------------------------------------- */
            $this->authUserModel->createUser([
                'id'                 => $userId,
                'code'               => $userCode,
                'username'           => $username,
                'password'           => $passwordHash,
                'role_id'            => $data['role_id'] ?? null,
                'is_active'          => 1,          // 🔥 새 사용자는 무조건 활성
                'approved'           => 0,          // 🔥 기본값: 미승인 (관리자 승인 필요 시)
                'email_notify'       => 1,
                'sms_notify'         => 0,
                'two_factor_enabled' => 0,
                'created_by'         => $adminId
            ]);

            /* ---------------------------------------------------
            * (2) user_profiles INSERT
            * --------------------------------------------------- */
            $this->profileService->createProfile([
                'user_id'       => $userId,
                'code'          => $employeeCode,
                'employee_name' => $employeeName,
                'department_id' => $data['department_id'] ?? null,
                'position_id'   => $data['position_id'] ?? null,
                'created_by'    => $adminId
            ]);

            // -----------------------------
            // 4) 커밋
            // -----------------------------
            $this->pdo->commit();

            return [
                'success' => true,
                'user_id' => $userId,
                'code'    => $userCode,
                'message' => '직원 생성 완료'
            ];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => '직원 생성 실패',
                'error'   => $e->getMessage()
            ];
        }
    }





    private function getClientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $realIp    = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    
        $external = '';
    
        if (!empty($forwarded)) {
            $list = explode(',', $forwarded);
            $external = trim($list[0]);
        } elseif (!empty($realIp)) {
            $external = $realIp;
        }
    
        if (empty($external)) {
            $external = $remote;
        }
    
        // 🔥 외부 (내부)
        return ($external !== $remote)
            ? "{$external} ({$remote})"
            : $remote;
    }


    private function getExternalIp(): string
    {
        try {
            return trim(file_get_contents("https://api.ipify.org"));
        } catch (\Throwable $e) {
            return '';
        }
    }




}

