<?php

namespace App\Services\Auth;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use App\Models\Auth\LogModel;
use App\Models\Auth\RoleModel;
use App\Services\File\FileService;
use App\Services\Mail\MailService;
use App\Services\System\SettingService;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class RegisterService
{
    private readonly PDO $pdo;
    private UserModel $usersModel;
    private EmployeeModel $employeeModel;
    private LogModel $authLogs;
    private RoleModel $roleModel;
    private MailService $mailService;
    private FileService $fileService;
    private SettingService $settingService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->usersModel = new UserModel($pdo);
        $this->employeeModel = new EmployeeModel($pdo);
        $this->authLogs = new LogModel($pdo);
        $this->roleModel = new RoleModel($pdo);
        $this->mailService = new MailService();
        $this->fileService = new FileService($pdo);
        $this->settingService = new SettingService($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.RegisterService');
    }

    public function register(array $data, array $files = []): array
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = trim((string) ($data['password'] ?? ''));
        $confirm = trim((string) ($data['confirm_password'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $name = trim((string) ($data['employee_name'] ?? ''));

        if ($username === '' || $password === '' || $confirm === '' || $email === '' || $name === '') {
            return ['success' => false, 'message' => '필수 항목을 모두 입력해주세요.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '올바른 이메일 형식이 아닙니다.'];
        }

        if ($password !== $confirm) {
            return ['success' => false, 'message' => '비밀번호와 비밀번호 확인이 일치하지 않습니다.'];
        }

        $policyCheck = $this->validatePasswordPolicy($password);
        if (!$policyCheck['success']) {
            return $policyCheck;
        }

        $user = $this->employeeModel->getByUsername($username);
        if ($user !== null) {
            $this->writeLog('REGISTER_DUPLICATE_USERNAME', $username, 0);
            return ['success' => false, 'message' => '이미 사용 중인 아이디입니다.'];
        }

        $emailUser = $this->employeeModel->getByEmail($email);
        if ($emailUser !== null) {
            $this->writeLog('REGISTER_DUPLICATE_EMAIL', $username, 0);
            return ['success' => false, 'message' => '이미 사용 중인 이메일입니다.'];
        }

        $profileImage = $this->handleProfileUpload($files);
        if (!$profileImage['success']) {
            return $profileImage;
        }

        $userId = UuidHelper::generate();
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $this->pdo->beginTransaction();

            $okUser = $this->usersModel->createUser([
                'id'         => $userId,
                'username'   => $username,
                'password'   => $passwordHash,
                'email'      => $email,
                'role_id'    => $this->getDefaultRoleId(),
                'approved'   => 0,
                'is_active'  => 1,
                'created_by' => null,
            ]);

            if (!$okUser) {
                throw new \RuntimeException('auth_users insert failed');
            }

            $this->usersModel->setCreatedBySelf($userId);

            $okProfile = $this->employeeModel->create([
                'id'               => UuidHelper::generate(),
                'sort_no'          => SequenceHelper::next('user_employees', 'sort_no'),
                'user_id'          => $userId,
                'employee_name'    => $name,
                'phone'            => null,
                'address'          => null,
                'address_detail'   => null,
                'department_id'    => null,
                'position_id'      => null,
                'doc_hire_date'    => null,
                'real_hire_date'   => null,
                'doc_retire_date'  => null,
                'real_retire_date' => null,
                'rrn'              => null,
                'rrn_image'        => null,
                'emergency_phone'  => null,
                'client_id'        => null,
                'profile_image'    => $profileImage['db_path'],
                'note'             => null,
                'memo'             => null,
                'created_by'       => $userId,
            ]);

            if (!$okProfile) {
                throw new \RuntimeException('user_employees insert failed');
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('register failed', [
                'username' => $username,
                'error'    => $e->getMessage(),
            ]);
            $this->writeLog('회원가입 처리 오류', $username, 0);

            return ['success' => false, 'message' => '회원가입 중 오류가 발생했습니다.'];
        }

        $this->authLogs->write([
            'id'            => UuidHelper::generate(),
            'log_type'      => 'auth',
            'action_type'   => 'register',
            'action_detail' => '회원가입 요청',
            'user_id'       => $userId,
            'username'      => $username,
            'success'       => 1,
            'ref_table'     => 'auth_users',
            'ref_id'        => $userId,
            'created_by'    => $userId,
        ]);

        $this->sendAdminApprovalMail($username, $name, $email, $userId);

        return [
            'success' => true,
            'message' => '회원가입이 완료되었습니다. 관리자 승인 후 로그인할 수 있습니다.',
        ];
    }

    private function validatePasswordPolicy(string $password): array
    {
        try {
            $rows = $this->settingService->getByCategory('SECURITY');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '비밀번호 정책을 확인하는 중 오류가 발생했습니다.'];
        }

        $policy = [];
        foreach ($rows as $row) {
            $policy[$row['config_key']] = $row['config_value'];
        }

        if (($policy['security_password_policy_enabled'] ?? '0') !== '1') {
            return ['success' => true];
        }

        $min = (int) ($policy['security_password_min'] ?? 0);
        if ($min > 0 && mb_strlen($password) < $min) {
            return ['success' => false, 'message' => "비밀번호는 {$min}자 이상 입력해야 합니다."];
        }

        if (($policy['security_pw_upper'] ?? '0') === '1' && !preg_match('/[A-Z]/', $password)) {
            return ['success' => false, 'message' => '비밀번호에는 영문 대문자를 1자 이상 포함해야 합니다.'];
        }

        if (($policy['security_pw_number'] ?? '0') === '1' && !preg_match('/\d/', $password)) {
            return ['success' => false, 'message' => '비밀번호에는 숫자를 1자 이상 포함해야 합니다.'];
        }

        if (($policy['security_pw_special'] ?? '0') === '1' && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return ['success' => false, 'message' => '비밀번호에는 특수문자를 1자 이상 포함해야 합니다.'];
        }

        return ['success' => true];
    }

    private function handleProfileUpload(array $files): array
    {
        $file = $files['profile_image'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => true, 'db_path' => ''];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => '프로필 이미지 업로드 중 오류가 발생했습니다.'];
        }

        $upload = $this->fileService->uploadProfile($file);
        if (empty($upload['success'])) {
            return ['success' => false, 'message' => $upload['message'] ?? '프로필 이미지 업로드 중 오류가 발생했습니다.'];
        }

        return ['success' => true, 'db_path' => $upload['db_path'] ?? ''];
    }

    private function sendAdminApprovalMail(string $username, string $employeeName, string $userEmail, string $userId): void
    {
        try {
            $this->mailService->sendAdminApprovalMail([
                'username'      => $username,
                'employee_name' => $employeeName,
                'email'         => $userEmail,
                'user_id'       => $userId,
                'host'          => $_SERVER['HTTP_HOST'] ?? 'localhost',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('admin approval mail failed', [
                'username' => $username,
                'error'    => $e->getMessage(),
            ]);
        }
    }

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
        return $this->roleModel->findIdByKey('staff');
    }
}
