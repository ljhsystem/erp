<?php
// 경로: PROJECT_ROOT . '/app/Services/System/EmployeeService.php'
// 설명:
//  - 직원(Employee) 관리 서비스
//  - UUID / Code 생성은 Service 책임
//  - DB 처리: UserPrlfileModel
//  - 모든 주요 흐름 LoggerFactory 적용
namespace App\Services\System;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\ActorHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;

class EmployeeService
{
    private readonly PDO $pdo;
    private UserModel $users;
    private EmployeeModel $employees;
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo        = $pdo;
        $this->users      = new UserModel($pdo);
        $this->employees  = new EmployeeModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger     = LoggerFactory::getLogger('service-system.EmployeeService');

        $this->logger->info('EmployeeService initialized');
    }

    /* =========================================================
     * 1. 직원 목록
     * ========================================================= */
    public function getList(array $filters = []): array
    {
        $this->logger->info('getList() called', [
            'filters' => $filters
        ]);

        try {
            $rows = $this->employees->getList($filters);

            $this->logger->info('getList() success', [
                'count' => count($rows)
            ]);

            return $rows;

        } catch (\Throwable $e) {
            $this->logger->error('getList() failed', [
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function search(string $q = '', array $filters = [], int $limit = 50): array
    {
        $this->logger->info('EmployeeService.search() called', [
            'q'       => $q,
            'filters' => $filters,
            'limit'   => $limit
        ]);
    
        try {
            $rows = $this->employees->search($q, $filters, $limit);
    
            $this->logger->info('EmployeeService.search() result', [
                'count' => count($rows)
            ]);
    
            return $rows;
    
        } catch (\Throwable $e) {
            $this->logger->error('EmployeeService.search() failed', [
                'q'         => $q,
                'filters'   => $filters,
                'limit'     => $limit,
                'exception' => $e->getMessage()
            ]);
    
            throw $e;
        }
    }
    /* =========================================================
     * 직원 검색 (Select2)
     * ========================================================= */
    public function searchPicker(string $q = '', int $limit = 20): array
    {
        $this->logger->info('search() called', [
            'q'     => $q,
            'limit' => $limit
        ]);

        try {
            return $this->employees->searchPicker($q, $limit);

        } catch (\Throwable $e) {
            $this->logger->error('search() failed', [
                'q'         => $q,
                'limit'     => $limit,
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }

    /* =========================================================
     * 3. 직원 저장 (신규/수정)
     * ========================================================= */
    public function save(array $data, array $files = []): array
    {
        $actor = ActorHelper::resolve('USER');
    
        // 공용 모달 신규/수정 판별
        // 프론트 수정모달은 hidden input name="id" 에 user_id를 담아 전송한다.
        // 따라서 id 우선, 하위호환을 위해 user_id도 함께 허용한다.
        $userId = trim((string)($data['id'] ?? $data['user_id'] ?? ''));
        $isCreate = ($userId === '');
    
        $this->logger->info('save() called', [
            'mode'   => $isCreate ? 'INSERT' : 'UPDATE',
            'userId' => $userId
        ]);

        $uploadedNewFiles = [];
        $deleteAfterCommit = [];

        try {
            $username     = trim((string)($data['username'] ?? ''));
            $password     = (string)($data['password'] ?? '');
            $employeeName = trim((string)($data['employee_name'] ?? ''));

            if ($isCreate && $username === '') {
                return ['success' => false, 'message' => '아이디는 필수입니다.'];
            }

            if ($employeeName === '') {
                return ['success' => false, 'message' => '직원명은 필수입니다.'];
            }

            if ($isCreate && $password === '') {
                return ['success' => false, 'message' => '비밀번호는 필수입니다.'];
            }

           if ($isCreate) {

                // 신규 → 그냥 존재하면 끝
                if ($this->employees->existsByUsername($username)) {
                    return ['success' => false, 'message' => '이미 사용중인 아이디입니다.'];
                }

            } else {

                // 수정 → 자기 자신 제외하고 체크
                if ($this->employees->existsByUsernameExceptId($username, $userId)) {
                    return ['success' => false, 'message' => '이미 사용중인 아이디입니다.'];
                }

            }

            /* -------------------------
             * AUTH 데이터
             * ------------------------- */
            $authData = [];

            if (isset($data['username']) && trim((string)$data['username']) !== '') {
                $authData['username'] = trim((string)$data['username']);
            }

            if (array_key_exists('email', $data)) {
                $authData['email'] = trim((string)$data['email']);
            }

            if (array_key_exists('role_id', $data) && $data['role_id'] !== '') {
                $authData['role_id'] = $data['role_id'];
            }

            if (array_key_exists('two_factor_enabled', $data)) {
                $authData['two_factor_enabled'] = ($data['two_factor_enabled'] == '1') ? 1 : 0;
            }

            if (array_key_exists('email_notify', $data)) {
                $authData['email_notify'] = ($data['email_notify'] == '1') ? 1 : 0;
            }

            if (array_key_exists('sms_notify', $data)) {
                $authData['sms_notify'] = ($data['sms_notify'] == '1') ? 1 : 0;
            }

            if ($password !== '') {
                $authData['password'] = password_hash($password, PASSWORD_DEFAULT);
                $authData['password_updated_at'] = date('Y-m-d H:i:s');
                $authData['password_updated_by'] = $actor;
            }

            /* -------------------------
             * EMPLOYEE 데이터
             * ------------------------- */
            $employeeData = [];

            $employeeFields = [
                'employee_name', 'phone', 'address', 'address_detail',
                'department_id', 'position_id',
                'certificate_name', 'note', 'memo',
                'doc_hire_date', 'real_hire_date',
                'doc_retire_date', 'real_retire_date',
                'emergency_phone',
                'bank_name', 'account_number', 'account_holder'
            ];

            foreach ($employeeFields as $field) {
                if (array_key_exists($field, $data)) {
                    $employeeData[$field] = ($data[$field] === '') ? null : $data[$field];
                }
            }

            /* -------------------------
             * 주민번호
             * ------------------------- */
            $rrnInput = trim((string)($data['rrn'] ?? ''));

            if (strpos($rrnInput, '*') !== false) {
                return ['success' => false, 'message' => '마스킹된 주민번호는 저장할 수 없습니다.'];
            }

            $rrnRaw = preg_replace('/\D+/', '', $rrnInput);
            if ($rrnRaw !== '') {
                $crypto = new Crypto();
                $employeeData['rrn'] = $crypto->encryptResidentNumber($rrnRaw);
            }

            /* -------------------------
             * 현재 데이터 조회
             * ------------------------- */
            $current = null;
            if (!$isCreate) {
                $current = $this->employees->getByUserId($userId);
            }

            /* -------------------------
             * 파일 처리
             * ------------------------- */
            if (!empty($files['profile_image']['name'])) {
                $upload = $this->fileService->uploadProfile($files['profile_image']);

                if (empty($upload['success']) || empty($upload['db_path'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '프로필 이미지 업로드 실패'];
                }

                $employeeData['profile_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['profile_image'])) {
                    $deleteAfterCommit[] = $current['profile_image'];
                }
            }

            if (!empty($files['rrn_image']['name'])) {
                $upload = $this->fileService->uploadPrivateIdDoc($files['rrn_image']);

                if (empty($upload['success']) || empty($upload['db_path'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '신분증 이미지 업로드 실패'];
                }

                $employeeData['rrn_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['rrn_image'])) {
                    $deleteAfterCommit[] = $current['rrn_image'];
                }
            }

            if (!empty($files['certificate_file']['name'])) {
                $upload = $this->fileService->uploadCertificate($files['certificate_file']);

                if (empty($upload['success']) || empty($upload['db_path'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '자격증 파일 업로드 실패'];
                }

                $employeeData['certificate_file'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['certificate_file'])) {
                    $deleteAfterCommit[] = $current['certificate_file'];
                }
            }
            /* 🔥 통장사본 */
            if (!empty($files['bank_file']['name'])) {
                $upload = $bank = $this->fileService->uploadBankCopy($files['bank_file']);

                if (empty($upload['success']) || empty($upload['db_path'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '통장사본 업로드 실패'];
                }

                $employeeData['bank_file'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['bank_file'])) {
                    $deleteAfterCommit[] = $current['bank_file'];
                }
            }
            if (!$isCreate) {
                if (!empty($data['profile_image_delete']) && $data['profile_image_delete'] == '1') {
                    if (empty($employeeData['profile_image']) && !empty($current['profile_image'])) {
                        $employeeData['profile_image'] = null;
                        $deleteAfterCommit[] = $current['profile_image'];
                    }
                }

                if (!empty($data['rrn_image_delete']) && $data['rrn_image_delete'] == '1') {
                    if (empty($employeeData['rrn_image']) && !empty($current['rrn_image'])) {
                        $employeeData['rrn_image'] = null;
                        $deleteAfterCommit[] = $current['rrn_image'];
                    }
                }

                if (!empty($data['certificate_file_delete']) && $data['certificate_file_delete'] == '1') {
                    if (empty($employeeData['certificate_file']) && !empty($current['certificate_file'])) {
                        $employeeData['certificate_file'] = null;
                        $deleteAfterCommit[] = $current['certificate_file'];
                    }
                }
                if (!empty($data['bank_file_delete']) && $data['bank_file_delete'] == '1') {
                    if (empty($employeeData['bank_file']) && !empty($current['bank_file'])) {
                        $employeeData['bank_file'] = null;
                        $deleteAfterCommit[] = $current['bank_file'];
                    }
                }
            }

            /* -------------------------
             * 저장
             * ------------------------- */
            $this->pdo->beginTransaction();

            try {
                if ($isCreate) {
                    $newUserId = UuidHelper::generate();

                    $authData['id'] = $newUserId;
                    $authData['created_by'] = $actor;

                    $userOk = $this->users->createUser($authData);
                    if (!$userOk) {
                        throw new \Exception('사용자 생성 실패');
                    }

                    $employeeData['id'] = UuidHelper::generate();
                    $employeeData['code'] = $this->nextEmployeeCode();
                    $employeeData['user_id'] = $newUserId;
                    $employeeData['created_by'] = $actor;

                    $employeeOk = $this->employees->create($employeeData);
                    if (!$employeeOk) {
                        throw new \Exception('직원 생성 실패');
                    }

                    $this->pdo->commit();

                    foreach (array_unique($deleteAfterCommit) as $path) {
                        $this->fileService->delete($path);
                    }

                    return [
                        'success' => true,
                        'id'      => $newUserId,
                        'message' => '저장 완료'
                    ];
                }

                if (!empty($authData)) {
                    $okUser = $this->users->updateUserDirect($userId, $authData);
                    if ($okUser === false) {
                        throw new \Exception('사용자 정보 수정 실패');
                    }
                }

                $okEmployee = $this->employees->updateByUserId($userId, $employeeData);
                if ($okEmployee === false) {
                    throw new \Exception('직원 정보 수정 실패');
                }

                $this->pdo->commit();

                foreach (array_unique($deleteAfterCommit) as $path) {
                    $this->fileService->delete($path);
                }

                return [
                    'success' => true,
                    'id'      => $userId,
                    'message' => '저장 완료'
                ];

            } catch (\Throwable $e) {
                $this->pdo->rollBack();

                foreach (array_unique($uploadedNewFiles) as $path) {
                    $this->fileService->delete($path);
                }

                throw $e;
            }

        } catch (\Throwable $e) {
            $this->logger->error('save() failed', [
                'exception' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '저장 실패',
                'error'   => $e->getMessage()
            ];
        }
    }

    /* =========================================================
     * 4. 직원 삭제
     * ========================================================= */
    public function delete(string $userId): array
    {
        $actor = ActorHelper::resolve('USER');

        $this->logger->info('delete() called', [
            'userId' => $userId,
            'actor'  => $actor
        ]);

        try {
            if ($userId === '') {
                return ['success' => false, 'message' => 'user_id 누락'];
            }

            $this->pdo->beginTransaction();

            try {
                $employee = $this->employees->getByUserId($userId);

                $employeeOk = $this->employees->hardDeleteByUserId($userId);
                $userOk     = $this->users->hardDeleteByUserId($userId);

                if (!$employeeOk || !$userOk) {
                    throw new \Exception('삭제 실패');
                }

                $this->pdo->commit();

                if (!empty($employee['profile_image'])) {
                    $this->fileService->delete($employee['profile_image']);
                }

                if (!empty($employee['rrn_image'])) {
                    $this->fileService->delete($employee['rrn_image']);
                }

                if (!empty($employee['certificate_file'])) {
                    $this->fileService->delete($employee['certificate_file']);
                }
                if (!empty($employee['bank_file'])) {
                    $this->fileService->delete($employee['bank_file']);
                }
                return [
                    'success' => true,
                    'message' => '직원 삭제 완료'
                ];

            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => '삭제 실패',
                'error'   => $e->getMessage()
            ];
        }
    }

    /* =========================================================
     * 5. 직원 상태 변경
     * ========================================================= */
    public function updateStatus(string $userId, bool $isActive): array
    {
        $actor = ActorHelper::resolve('USER');

        try {
            if ($userId === '') {
                return [
                    'success' => false,
                    'message' => 'user_id 누락'
                ];
            }

            $data = [
                'is_active'  => $isActive ? 1 : 0,
                'deleted_at' => $isActive ? null : date('Y-m-d H:i:s'),
                'deleted_by' => $isActive ? null : $actor,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ];

            $ok = $this->employees->updateStatus($userId, $data);

            return [
                'success' => (bool)$ok,
                'message' => $isActive ? '계정이 활성화되었습니다.' : '계정이 비활성화되었습니다.'
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => '상태 변경 실패',
                'error'   => $e->getMessage()
            ];
        }
    }

    /* =========================================================
     * 6. 직원 순서 변경
     * ========================================================= */
    public function reorder(array $changes): void
    {
        $this->pdo->beginTransaction();

        try {
            foreach ($changes as $row) {
                $this->employees->updateCode($row['id'], $row['newCode'] + 10000);
            }

            foreach ($changes as $row) {
                $this->employees->updateCode($row['id'], $row['newCode']);
            }

            $this->pdo->commit();

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /* =========================================================
     * 7. 직원 이름 검색
     * ========================================================= */
    public function findByName(string $name): ?array
    {
        return $this->employees->findByName($name);
    }

    private function nextEmployeeCode(): int
    {
        $stmt = $this->pdo->query("SELECT COALESCE(MAX(code), 0) + 1 FROM user_employees");
        return (int)$stmt->fetchColumn();
    }
}