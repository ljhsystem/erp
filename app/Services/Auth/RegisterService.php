<?php
// 경로: PROJECT_ROOT . '/app/services/auth/RegisterService.php'
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\AuthUserModel;
use App\Models\User\UserProfileModel;
use App\Services\Auth\AuthService;
use App\Services\Mail\MailService;
use App\Models\Auth\AuthLogModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\LoggerFactory;

class RegisterService
{
    private readonly PDO $pdo;
    private $usersModel;
    private $profileModel;
    private $userService;
    private $mailService;
    private $authLogs;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo          = $pdo;
        $this->usersModel     = new AuthUserModel($pdo);
        $this->profileModel  = new UserProfileModel($pdo);
        $this->userService   = new AuthService($pdo);
        $this->mailService   = new MailService();
        $this->authLogs      = new AuthLogModel($pdo); // ✅ DB 로그용
        $this->logger        = LoggerFactory::getLogger('service-auth.RegisterService');
    }

    /* ============================================================
     * 1) 회원가입 처리 (+ 프로필 생성 + 코드생성 + 관리자 승인요청 메일 발송)
     * ============================================================ */
    public function register(array $data): array
    {
        $username = trim($data['username'] ?? '');
        $password = trim($data['password'] ?? '');
        $email    = trim($data['email'] ?? '');
        $name     = trim($data['employee_name'] ?? '');
        $img      = $data['profile_image'] ?? '';

        /* ------------------------------------------------------------
         * 1️⃣ UUID + CODE 생성 (🔥 핵심)
         * ------------------------------------------------------------ */
        $userId        = UuidHelper::generate();                    // 🔥 auth_users.id
        $userCode      = CodeHelper::generateUserCode($this->pdo);  // 🔥 auth_users.code
        $employeeCode  = CodeHelper::generateEmployeeCode($this->pdo); // 🔥 user_profiles.code

        // 3. 회원가입 시도 로그
        $this->logger->info('register 시도', [
            'username'       => $username,
            'employee_name'  => $name,
            'email'          => $email
        ]);

        /* ------------------------------------------------------------
         * 2️⃣ 기본 검증
         * ------------------------------------------------------------ */
        // ✅ 직원 이름( employee_name ) 필수 검증 추가
        if ($name === '') {
            $this->logger->warning('register 실패 - 직원 이름 누락', [
                'username' => $username
            ]);
            $this->authLogs->write([
                'id'            => UuidHelper::generate(), // ✅ 추가 (에러 해결)
                'log_type'      => 'auth',
                'action_type'   => 'register',
                'action_detail' => '회원가입요청:직원이름누락',
                'username'      => $username,
                'success'       => 0,
                'ref_table'     => 'auth_users',
                'ref_id'        => null,
                'user_id'       => null,
                'created_by'    => null,
            ]);
            return ['success' => false, 'message' => '직원 이름을 입력해 주세요.'];
        }

        // ✅ 아이디 중복 체크 추가
        if ($this->usersModel->existsByUsername($username)) {
            $this->logger->warning('register 실패 - 아이디 중복', [
                'username' => $username
            ]);
            // DB 로그: 회원가입 실패 (아이디 중복)
            $this->authLogs->write([
                'id'            => UuidHelper::generate(),
                'log_type'      => 'auth',
                'action_type'   => 'register',
                'action_detail' => '회원가입요청:아이디중복',
                'username'      => $username,
                'success'       => 0,
                'ref_table'     => 'auth_users',
                'ref_id'        => null,
                'user_id'       => null,
                'created_by'    => null,
            ]);
            return ['success' => false, 'message' => '이미 사용 중인 아이디입니다.'];
        }

        // ✅ 직원 이름 중복 체크 추가
        if ($this->profileModel->existsByEmployeeName($name)) {
            $this->logger->warning('register 실패 - 직원 이름 중복', [
                'username'       => $username,
                'employee_name'  => $name
            ]);

            $this->authLogs->write([
                'id'            => UuidHelper::generate(),
                'log_type'      => 'auth',
                'action_type'   => 'register',
                'action_detail' => '회원가입요청:직원이름중복',
                'username'      => $username,
                'success'       => 0,
                'ref_table'     => 'auth_users',
                'ref_id'        => null,
                'user_id'       => null,
                'created_by'    => null,
            ]);

            return ['success' => false, 'message' => '이미 사용 중인 이름입니다.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        /* ------------------------------------------------------------
         * 3️⃣ 트랜잭션 시작
         * ------------------------------------------------------------ */

        try {
            // 4. 트랜잭션 시작
            $this->pdo->beginTransaction();

            // 4-1) auth_users 생성
            $okUser = $this->usersModel->createUser([
                'id'         => $userId,
                'code'       => $userCode,
                'username'   => $username,
                'password'   => $hash,
                'email'      => $email,
                'role_id'    => $this->getDefaultRoleId(),
                'approved'   => 0,
                'is_active'  => 1,
                'created_by' => null
            ]);            

            if (!$okUser) {
                $this->logger->error('register 실패 - createUser 실패', [
                    'username' => $username
                ]);
                $this->pdo->rollBack();
                // DB 로그: 회원가입 실패 (DB 오류)
                $this->authLogs->write([
                    'id'            => UuidHelper::generate(),
                    'log_type'     => 'auth',
                    'action_type'  => 'register',
                    'action_detail' => '회원가입요청:DB오류',
                    'username'     => $username,
                    'success'      => 0,
                ]);
                return ['success' => false, 'message' => 'DB 오류로 회원가입 실패'];
            }

            // 4-2) 방금 생성된 유저 조회 (id 확보용)
            $user = $this->usersModel->getByUsername($username);
            if (!$user || empty($user['id'])) {
                $this->logger->error('register 실패 - 생성된 사용자 조회 실패', [
                    'username' => $username
                ]);
                $this->pdo->rollBack();
                $this->authLogs->write([
                    'id'            => UuidHelper::generate(),
                    'log_type'     => 'auth',
                    'action_type'  => 'register',
                    'action_detail' => '회원가입요청:생성후조회실패',
                    'username'     => $username,
                    'success'      => 0,
                ]);
                return ['success' => false, 'message' => '회원 정보 조회에 실패했습니다.'];
            }

            $userId = $user['id'];

            // ✅ 방금 생성한 사용자를 자기 자신이 생성한 것으로 기록
            $this->usersModel->setCreatedBySelf($userId);

            // 4-3) user_profiles 생성
            $okProfile = $this->profileModel->createProfile([
                'id'              => UuidHelper::generate(),  
                'code'            => $employeeCode,
                'user_id'         => $userId,
                'employee_name'   => $name,
                'phone'           => null,
                'address'         => null,
                'address_detail'  => null,
                'department_id'   => null,
                'position_id'     => null,
                'doc_hire_date'   => null,
                'real_hire_date'  => null,
                'doc_retire_date' => null,
                'real_retire_date' => null,
                'rrn'             => null,
                'rrn_image'       => null,
                'emergency_phone' => null,
                'client_id'       => null,
                'profile_image'   => $img ?: null,
                'certificate_name' => null,
                'certificate_file' => null,
                'note'            => null,
                'memo'            => null,
                // ✅ 프로필 생성자도 본인 id 로 기록
                'created_by'      => $userId,
            ]);

            if (!$okProfile) {
                $this->logger->error('register 실패 - createProfile 실패', [
                    'username' => $username,
                    'user_id'  => $userId
                ]);
                $this->pdo->rollBack();
                $this->authLogs->write([
                    'id'            => UuidHelper::generate(),
                    'log_type'     => 'auth',
                    'action_type'  => 'register',
                    'action_detail' => '회원가입요청:프로필생성실패',
                    'user_id'      => $userId,
                    'username'     => $username,
                    'success'      => 0,
                ]);
                return ['success' => false, 'message' => '프로필 생성 중 오류가 발생했습니다.'];
            }

            // 4-4) commit
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->logger->error('register 예외 - 트랜잭션 롤백', [
                'username' => $username,
                'error'    => $e->getMessage()
            ]);
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->authLogs->write([
                'id'            => UuidHelper::generate(),
                'log_type'     => 'auth',
                'action_type'  => 'register',
                'action_detail' => '회원가입요청:예외',
                'username'     => $username,
                'success'      => 0,
            ]);
            return ['success' => false, 'message' => '회원가입 처리 중 예외가 발생했습니다.'];
        }

        // ✅ 회원가입 성공 로그: created_by 에도 본인 userId 기록
        $this->authLogs->write([
            'id'            => UuidHelper::generate(),
            'log_type'      => 'auth',
            'action_type'   => 'register',
            'action_detail' => '회원가입요청',
            'user_id'       => $userId,
            'username'      => $username,
            'success'       => 1,
            'ref_table'     => 'auth_users',
            'ref_id'        => $userId,
            'created_by'    => $userId,    // 🔹 여기 추가
        ]);

        // 5. 회원가입 + 프로필 + 코드 생성 성공 로그
        $this->logger->info('register 성공 (auth_users + user_profiles + codes)', [
            'username'      => $username,
            'user_code'     => $userCode,
            'employee_code' => $employeeCode
        ]);

        // 6. 여기서 관리자 승인 요청 메일 발송 처리
        // ✅ 메일에도 실제 userCode(code 컬럼) 를 넘김
        $this->sendAdminApprovalMailFromService($username, $name, $email, $userCode);

        return [
            'success'   => true,
            // ✅ 컨트롤러로도 username 이 아니라 실제 code 를 넘김
            'user_code' => $userCode,
        ];
    }

    // 6. 서비스 내부에서 관리자 승인요청 메일 발송
    private function sendAdminApprovalMailFromService(
        string $username,
        string $employeeName,
        string $userEmail,
        string $userCode
    ): void {
        try {
            // MailService + AdminApprovalMail 에서 사용할 데이터 구성
            $data = [
                'username'      => $username,
                'employee_name' => $employeeName,
                'email'         => $userEmail,
                // ✅ 여기에도 실제 code 값
                'user_code'     => $userCode,
                'host'          => $_SERVER['HTTP_HOST'] ?? 'localhost',
            ];

            $this->mailService->sendAdminApprovalMail($data);

            $this->logger->info('관리자 승인요청 메일 발송 성공', [
                'username'  => $username,
                'user_code' => $userCode,
            ]);
        } catch (\Throwable $e) {
            // 메일 발송 실패는 회원가입 실패로 처리하지 않고, 로그만 남김
            $this->logger->error('RegisterService 메일 발송 실패', [
                'username' => $username,
                'error'    => $e->getMessage()
            ]);
        }
    }
    
    /* ============================================================
     * 공통 로그 기록
     * ============================================================ */
    private function writeLog(string $detail, string $username, int $success): void
    {
        $this->authLogs->write([
            'id'            => UuidHelper::generate(),
            'log_type'      => 'auth',
            'action_type'   => 'register',
            'action_detail' => $detail,
            'username'      => $username,
            'success'       => $success,
        ]);
    }

    private function getDefaultRoleId(): ?string
    {
        $stmt = $this->pdo->prepare("SELECT id FROM auth_roles WHERE role_key = 'user' LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn() ?: null;
    }

}
