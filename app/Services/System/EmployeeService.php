<?php
namespace App\Services\System;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use App\Models\Institution\QualificationModel;
use App\Models\Institution\EducationModel;
use App\Services\Institution\EmployeeHrBaselineService;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\ActorHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;

class EmployeeService
{
    private readonly PDO $pdo;
    private UserModel $users;
    private EmployeeModel $model;
    private QualificationModel $qualifications;
    private EducationModel $educations;
    private FileService $fileService;
    private UserSettingService $userSettings;
    private EmployeeHrBaselineService $hrBaseline;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo        = $pdo;
        $this->users      = new UserModel($pdo);
        $this->model  = new EmployeeModel($pdo);
        $this->qualifications = new QualificationModel($pdo);
        $this->educations = new EducationModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->userSettings = new UserSettingService($pdo);
        $this->hrBaseline = new EmployeeHrBaselineService($pdo);
        $this->logger     = LoggerFactory::getLogger('service-system.EmployeeService');

        $this->logger->info('EmployeeService initialized');
    }

    public function getList(array $filters = []): array
    {
        $this->logger->info('getList() called', [
            'filters' => $filters
        ]);

        try {

            $rows = $this->model->getList($filters);

            $this->logger->info('getList() success', [
                'count' => count($rows)
            ]);

            if (!empty($rows)) {

                $crypto = new Crypto();

                foreach ($rows as &$row) {

                    if (!empty($row['rrn'])) {

                        $rrn = $crypto->decryptResidentNumber($row['rrn']);

                        // 숫자만 전달
                        $row['rrn'] = preg_replace('/\D+/', '', $rrn);

                    } else {

                        $row['rrn'] = '';

                    }
                }

                unset($row);
            }

            return $rows;

        } catch (\Throwable $e) {

            $this->logger->error('getList() failed', [
                'filters'   => $filters,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function getById(string $id): ?array
    {
        $this->logger->info('getById() called', ['id' => $id]);

        try {

            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', ['id' => $id]);
                return null;
            }

            if (!empty($row['rrn'])) {

                $crypto = new \Core\Security\Crypto();

                $rrn = $crypto->decryptResidentNumber($row['rrn']);

                $row['rrn'] = preg_replace('/\D+/', '', $rrn);

                $this->logger->info('rrn decrypted', [
                    'employee_id' => $id
                ]);

            } else {

                $row['rrn'] = '';

            }

            $row['qualification_count'] = $this->qualifications->countByEmployee($id);
            $row['education_count'] = $this->educations->countByEmployee($id);

            return $row;

        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }

    public function searchPicker(string $q = '', int $limit = 20): array
    {
        $this->logger->info('searchPicker() called', [
            'q'     => $q,
            'limit' => $limit
        ]);

        try {

            $rows = $this->model->searchPicker($q, $limit);

            if (empty($rows)) {
                return [];
            }

            $results = [];

            foreach ($rows as $row) {

                $text = $row['employee_name'] ?? '';

                if (!empty($row['department_name'])) {
                    $text .= ' (' . $row['department_name'] . ')';
                }

                $results[] = [
                    'id'   => $row['id'],   // ?뵦 user_employees.id
                    'text' => $text
                ];
            }

            return $results;

        } catch (\Throwable $e) {

            $this->logger->error('searchPicker() failed', [
                'q'         => $q,
                'limit'     => $limit,
                'exception' => $e->getMessage()
            ]);

            return [];
        }
    }

    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);

        $employeeId = trim((string)($data['id'] ?? ''));
        $isCreate   = ($employeeId === '');

        $this->logger->info('save() called', [
            'mode'       => $isCreate ? 'CREATE' : 'UPDATE',
            'employeeId' => $employeeId,
            'actor'      => $actor
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

            $current = null;
            $currentRepresentativeQualification = null;
            $userId  = null;

            if (!$isCreate) {
                $current = $this->model->getById($employeeId);

                if (!$current) {
                    throw new \Exception('직원 정보 없음');
                }

                if (empty($current['user_id'])) {
                    throw new \Exception('사용자 정보 없음');
                }

                if (!empty($current['representative_qualification_id'])) {
                    $currentRepresentativeQualification = $this->qualifications->detail((string)$current['representative_qualification_id']);
                    if (!$currentRepresentativeQualification || (string)$currentRepresentativeQualification['employee_id'] !== $employeeId) {
                        throw new \Exception('대표 자격증 연결 정보가 올바르지 않습니다.');
                    }
                }

                $userId = $current['user_id'];

                $currentUser = $this->users->getById($userId);
                if (!$currentUser) {
                    throw new \Exception('사용자 정보 없음');
                }

                if ($username !== '' && $currentUser['username'] !== $username) {
                    // no-op
                }

                $protectedError = $this->validateProtectedHrFields($data, $current);
                if ($protectedError !== null) {
                    return ['success' => false, 'message' => $protectedError, 'status' => 400];
                }
            }

            $this->validateRequiredFieldPolicies(
                $data,
                $files,
                $current,
                $currentRepresentativeQualification
            );

            $authData = [];

            if ($username !== '') {
                $authData['username'] = $username;
            }

            if (array_key_exists('email', $data)) {
                $authData['email'] = trim((string)($data['email'] ?? ''));
            }

            if (array_key_exists('role_id', $data)) {
                $authData['role_id'] = ($data['role_id'] === '' ? null : $data['role_id']);
            }

            if (array_key_exists('two_factor_enabled', $data)) {
                $authData['two_factor_enabled'] = ((string)($data['two_factor_enabled'] ?? '0') === '1') ? 1 : 0;
            }

            if (array_key_exists('email_notify', $data)) {
                $authData['email_notify'] = ((string)($data['email_notify'] ?? '0') === '1') ? 1 : 0;
            }

            if (array_key_exists('sms_notify', $data)) {
                $authData['sms_notify'] = ((string)($data['sms_notify'] ?? '0') === '1') ? 1 : 0;
            }

            if ($password !== '') {
                $authData['password'] = password_hash($password, PASSWORD_DEFAULT);
                $authData['password_updated_at'] = date('Y-m-d H:i:s');
                $authData['password_updated_by'] = $actor;
            }

            $authData['updated_by'] = $actor;

            $employeeData = [];

            $fields = [
                'employee_name', 'phone', 'address', 'address_detail',
                'department_id', 'position_id', 'job_id', 'employment_status',
                'note', 'memo',
                'doc_hire_date', 'real_hire_date',
                'doc_retire_date', 'real_retire_date',
                'emergency_phone',
                'bank_name', 'account_number', 'account_holder'
            ];

            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $employeeData[$f] = ($data[$f] === '') ? null : $data[$f];
                }
            }

            if (!$isCreate) {
                foreach (['department_id', 'position_id', 'job_id', 'employment_status', 'doc_hire_date', 'real_hire_date', 'doc_retire_date', 'real_retire_date'] as $field) {
                    unset($employeeData[$field]);
                }
            }

            $rrnInput = trim((string)($data['rrn'] ?? ''));

            if (strpos($rrnInput, '*') !== false) {
                return ['success' => false, 'message' => '마스킹된 주민등록번호는 저장할 수 없습니다.'];
            }

            $rrnRaw = preg_replace('/\D+/', '', $rrnInput);

            if ($rrnRaw !== '') {
                $crypto = new Crypto();
                $employeeData['rrn'] = $crypto->encryptResidentNumber($rrnRaw);
            } elseif ($isCreate) {
                $employeeData['rrn'] = null;
            } elseif ($current) {
                $employeeData['rrn'] = $current['rrn'] ?? null;
            }

            $deleteProfile      = ((string)($data['profile_image_delete'] ?? '0') === '1');
            $deleteRrnImage     = ((string)($data['rrn_image_delete'] ?? '0') === '1');
            $deleteBankFile     = ((string)($data['bank_file_delete'] ?? '0') === '1');
            $deleteRepresentativeQualification = ((string)($data['representative_qualification_delete'] ?? '0') === '1');
            $representativeQualificationName = trim((string)($data['representative_qualification_name'] ?? ''));
            $representativeQualificationFile = $files['representative_qualification_file'] ?? null;
            $hasRepresentativeQualificationUpload = $representativeQualificationFile && ($representativeQualificationFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

            if ($deleteRepresentativeQualification && $hasRepresentativeQualificationUpload) {
                return ['success' => false, 'message' => '대표 자격증 삭제와 업로드를 동시에 처리할 수 없습니다.'];
            }
            if (!$deleteRepresentativeQualification && $representativeQualificationName === '' && ($hasRepresentativeQualificationUpload || $currentRepresentativeQualification)) {
                return ['success' => false, 'message' => '대표 자격증 이름을 입력해 주세요.'];
            }
            if (!$deleteRepresentativeQualification && !$currentRepresentativeQualification && $representativeQualificationName !== '' && !$hasRepresentativeQualificationUpload) {
                return ['success' => false, 'message' => '대표 자격증 파일을 선택해 주세요.'];
            }
            if (!$deleteRepresentativeQualification && !$currentRepresentativeQualification && $representativeQualificationName === '' && $hasRepresentativeQualificationUpload) {
                return ['success' => false, 'message' => '대표 자격증 이름을 입력해 주세요.'];
            }

            if ($isCreate) {

                $employeeData['profile_image']    = null;
                $employeeData['rrn_image']        = null;
                $employeeData['bank_file']        = null;

            } else {

                $employeeData['profile_image']    = $deleteProfile ? null : ($current['profile_image'] ?? null);
                $employeeData['rrn_image']        = $deleteRrnImage ? null : ($current['rrn_image'] ?? null);
                $employeeData['bank_file']        = $deleteBankFile ? null : ($current['bank_file'] ?? null);
            }

            if ($deleteProfile) {
                if (!$isCreate && !empty($current['profile_image'])) {
                    $deleteAfterCommit[] = $current['profile_image'];
                }
                $employeeData['profile_image'] = null;
            }

            if ($deleteRrnImage) {
                if (!$isCreate && !empty($current['rrn_image'])) {
                    $deleteAfterCommit[] = $current['rrn_image'];
                }
                $employeeData['rrn_image'] = null;
            }

            if ($deleteBankFile && !$isCreate && !empty($current['bank_file'])) {

                $employeeData['bank_file'] = null;

                $deleteAfterCommit[] = $current['bank_file'];
            }

            $file = $files['profile_image'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadProfile($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '프로필 이미지 업로드 실패'];
                }

                $employeeData['profile_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['profile_image']) && !$deleteProfile) {
                    $deleteAfterCommit[] = $current['profile_image'];
                }
            }

            $file = $files['rrn_image'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadPrivateIdDoc($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '신분증 업로드 실패'];
                }

                $employeeData['rrn_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['rrn_image']) && !$deleteRrnImage) {
                    $deleteAfterCommit[] = $current['rrn_image'];
                }
            }

            $file = $files['bank_file'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadBankCopy($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '통장사본 업로드 실패'];
                }

                $employeeData['bank_file'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['bank_file']) && !$deleteBankFile) {
                    $deleteAfterCommit[] = $current['bank_file'];
                }
            }

            $uploadedRepresentativeQualification = null;
            if ($hasRepresentativeQualificationUpload) {
                $uploadedRepresentativeQualification = $this->fileService->uploadCertificate($representativeQualificationFile);
                if (empty($uploadedRepresentativeQualification['success'])) {
                    foreach (array_unique($uploadedNewFiles) as $path) {
                        $this->fileService->delete($path);
                    }
                    return ['success' => false, 'message' => $uploadedRepresentativeQualification['message'] ?? '대표 자격증 업로드 실패'];
                }
                $uploadedNewFiles[] = $uploadedRepresentativeQualification['db_path'];
            }

            $ownsTransaction = !$this->pdo->inTransaction();
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }

            try {
                if ($isCreate) {
                    $newUserId = UuidHelper::generate();

                    $authData['id'] = $newUserId;
                    $authData['created_by'] = $actor;

                    if (!$this->users->createUser($authData)) {
                        throw new \Exception('사용자 생성 실패');
                    }

                    $newEmployeeId = UuidHelper::generate();

                    $employeeData['id'] = $newEmployeeId;
                    $employeeData['sort_no'] = SequenceHelper::next('user_employees', 'sort_no');
                    $employeeData['user_id'] = $newUserId;

                    if (!$this->model->create($employeeData)) {
                        throw new \Exception('직원 생성 실패');
                    }

                    $this->hrBaseline->create($newEmployeeId, $employeeData, $actor);

                    $this->persistRepresentativeQualification(
                        $newEmployeeId,
                        null,
                        $representativeQualificationName,
                        $uploadedRepresentativeQualification,
                        $representativeQualificationFile,
                        $deleteRepresentativeQualification,
                        $actor,
                        $deleteAfterCommit
                    );

                    if ($ownsTransaction) {
                        $this->pdo->commit();
                    }

                    foreach (array_unique($deleteAfterCommit) as $path) {
                        $this->fileService->delete($path);
                    }

                    return [
                        'success' => true,
                        'id'      => $newEmployeeId,
                        'sort_no'    => $employeeData['sort_no'],
                        'message' => '저장 완료'
                    ];
                }

                if (!empty($authData)) {
                    if (!$this->users->updateUserDirect($userId, $authData)) {
                        throw new \Exception('사용자 수정 실패');
                    }
                }

                if (!$this->model->updateById($employeeId, $employeeData)) {
                    throw new \Exception('직원 수정 실패');
                }

                $this->persistRepresentativeQualification(
                    $employeeId,
                    $currentRepresentativeQualification,
                    $representativeQualificationName,
                    $uploadedRepresentativeQualification,
                    $representativeQualificationFile,
                    $deleteRepresentativeQualification,
                    $actor,
                    $deleteAfterCommit
                );

                if ($ownsTransaction) {
                    $this->pdo->commit();
                }

                foreach (array_unique($deleteAfterCommit) as $path) {
                    $this->fileService->delete($path);
                }

                return [
                    'success' => true,
                    'id'      => $employeeId,
                    'message' => '저장 완료'
                ];

            } catch (\Throwable $e) {
                if ($ownsTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                foreach (array_unique($uploadedNewFiles) as $path) {
                    $this->fileService->delete($path);
                }

                throw $e;
            }

        } catch (\Throwable $e) {
            $this->logger->error('save() failed', [
                'employeeId' => $employeeId,
                'error'      => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function updateStatus(string $employeeId, bool $isActive): array
    {
        $actor = ActorHelper::resolve('USER');

        try {

            if ($employeeId === '') {
                return [
                    'success' => false,
                    'message' => '직원 ID 누락'
                ];
            }

            $employee = $this->model->getById($employeeId);

            if (!$employee || empty($employee['user_id'])) {
                return [
                    'success' => false,
                    'message' => '사용자 정보 없음'
                ];
            }

            $userId = $employee['user_id'];

            $data = [
                'is_active'  => $isActive ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ];

            $ok = $this->users->updateUserDirect($userId, $data);

            if ($ok === false) {
                throw new \Exception('상태 업데이트 실패');
            }

            return [
                'success' => true,
                'message' => $isActive
                    ? '계정이 활성화되었습니다.'
                    : '계정이 비활성화되었습니다.'
            ];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => '상태 변경 실패',
                'error'   => $e->getMessage()
            ];
        }
    }

    public function purge(string $employeeId, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('purge() called', [
            'employeeId' => $employeeId,
            'actor'      => $actor
        ]);

        if ($employeeId === '') {
            return [
                'success' => false,
                'message' => '직원 ID 누락'
            ];
        }

        try {

            $employee = $this->model->getById($employeeId);

            if (!$employee) {
                return [
                    'success' => false,
                    'message' => '존재하지 않는 직원입니다.'
                ];
            }

            if (empty($employee['user_id'])) {
                return [
                    'success' => false,
                    'message' => '사용자 정보 없음'
                ];
            }

            $userId = $employee['user_id'];

            $deleteAfterCommit = [];

            foreach (['profile_image','rrn_image','bank_file'] as $field) {
                if (!empty($employee[$field])) {
                    $deleteAfterCommit[] = $employee[$field];
                }
            }

            $this->pdo->beginTransaction();

            try {
                $employeeDeleted = $this->model->hardDeleteById($employeeId);

                if (!$employeeDeleted) {
                    throw new \Exception('직원 삭제 실패');
                }

                $ok = $this->users->hardDeleteById($userId);

                if (!$ok) {
                    throw new \Exception('사용자 삭제 실패');
                }
                $this->pdo->commit();

            } catch (\Throwable $e) {

                $this->pdo->rollBack();
                throw $e;
            }

            foreach (array_unique($deleteAfterCommit) as $path) {

                $this->fileService->delete($path);

                $this->logger->info('file deleted', [
                    'path' => $path
                ]);
            }

            return [
                'success' => true
            ];

        } catch (\Throwable $e) {

            $this->logger->error('purge() failed', [
                'employeeId' => $employeeId,
                'error'      => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '직원 삭제 실패'
            ];
        }
    }

    public function reorder(array $changes): bool
    {
        $this->logger->info('reorder() called', [
            'changes' => $changes
        ]);

        if (empty($changes)) {
            return true;
        }

        try {

            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as &$row) {
                $sortNo = $row['newSortNo'] ?? $row['sort_no'] ?? null;

                if (
                    empty($row['id']) ||
                    $sortNo === null
                ) {
                    throw new \Exception('reorder 데이터 오류');
                }

                $row['_sort_no'] = (int) $sortNo;
            }
            unset($row);

            foreach ($changes as $row) {

                $tempSortNo = $row['_sort_no'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            foreach ($changes as $row) {

                $this->model->updateSortNo(
                    $row['id'],
                    $row['_sort_no']
                );
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $this->logger->info('reorder() success');

            return true;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('reorder() failed', [
                'exception' => $e->getMessage(),
                'changes' => $changes
            ]);

            throw $e;
        }
    }

    private function validateProtectedHrFields(array $data, array $current): ?string
    {
        $labels = [
            'department_id' => '부서',
            'position_id' => '직위·직책',
            'job_id' => '직무',
            'employment_status' => '재직상태',
            'doc_hire_date' => '문서상 입사일',
            'real_hire_date' => '실입사일',
            'doc_retire_date' => '문서상 퇴사일',
            'real_retire_date' => '실퇴사일',
        ];
        foreach ($labels as $field => $label) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if ($this->normalizeProtectedValue($data[$field] ?? null) !== $this->normalizeProtectedValue($current[$field] ?? null)) {
                return $label . '은(는) 직원관리에서 수정할 수 없습니다. 인사발령관리에서 변경해 주세요.';
            }
        }
        return null;
    }

    private function normalizeProtectedValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function persistRepresentativeQualification(
        string $employeeId,
        ?array $current,
        string $name,
        ?array $uploaded,
        ?array $uploadedFile,
        bool $delete,
        string $actor,
        array &$deleteAfterCommit
    ): void {
        if ($delete) {
            if (!$current) {
                $this->model->updateRepresentativeQualificationId($employeeId, null);
                return;
            }

            $this->model->updateRepresentativeQualificationId($employeeId, null);
            $this->qualifications->softDelete((string)$current['id'], $actor);
            $this->qualifications->audit($this->representativeQualificationAudit($current, null, 'DELETE', $actor));
            if (!empty($current['attachment_path'])) {
                $deleteAfterCommit[] = $current['attachment_path'];
            }
            return;
        }

        if (!$current && $name === '' && !$uploaded) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        if (!$current) {
            $data = [
                'employee_id' => $employeeId,
                'qualification_type_code' => 'OTHER',
                'qualification_name' => $name,
                'status_code' => 'PENDING_VERIFICATION',
                'attachment_path' => $uploaded['db_path'],
                'attachment_name' => (string)($uploadedFile['name'] ?? ''),
                'note' => '직원관리 대표 자격증',
                'request_key' => 'EMPLOYEE-REPRESENTATIVE-QUALIFICATION-' . UuidHelper::generate(),
                'created_at' => $now,
                'created_by' => $actor,
                'updated_at' => $now,
                'updated_by' => $actor,
            ];
            $qualificationId = $this->qualifications->create($data);
            $after = $this->qualifications->detail($qualificationId);
            $this->qualifications->audit($this->representativeQualificationAudit(null, $after, 'CREATE', $actor));
            $this->model->updateRepresentativeQualificationId($employeeId, $qualificationId);
            return;
        }

        if ($name === (string)$current['qualification_name'] && !$uploaded) {
            return;
        }

        $update = [
            'qualification_name' => $name,
            'updated_at' => $now,
            'updated_by' => $actor,
        ];
        if ($uploaded) {
            $update['attachment_path'] = $uploaded['db_path'];
            $update['attachment_name'] = (string)($uploadedFile['name'] ?? '');
            if (!empty($current['attachment_path'])) {
                $deleteAfterCommit[] = $current['attachment_path'];
            }
        }
        $this->qualifications->update((string)$current['id'], $update);
        $after = $this->qualifications->detail((string)$current['id']);
        $this->qualifications->audit($this->representativeQualificationAudit($current, $after, 'UPDATE', $actor));
    }

    private function representativeQualificationAudit(?array $before, ?array $after, string $action, string $actor): array
    {
        $row = $after ?? $before;
        return [
            'target_id' => (string)$row['id'],
            'employee_id' => (string)$row['employee_id'],
            'action_type_code' => $action,
            'source_type_code' => 'ADMIN',
            'reason' => '직원관리 대표 자격증 ' . ($action === 'CREATE' ? '등록' : ($action === 'UPDATE' ? '수정' : '삭제')),
            'request_key' => 'EMPLOYEE-REPRESENTATIVE-QUALIFICATION-AUDIT-' . UuidHelper::generate(),
            'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'after_data' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'processed_by' => $actor,
            'processed_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function validateRequiredFieldPolicies(
        array $data,
        array $files,
        ?array $current,
        ?array $currentRepresentativeQualification
    ): void {
        $settings = $this->userSettings->detail('employee', 'TABLE')['settings_json'] ?? [];
        $policies = is_array($settings['columnRequirementPolicy'] ?? null)
            ? $settings['columnRequirementPolicy']
            : [];
        $displayNames = is_array($settings['columnDisplayName'] ?? null)
            ? $settings['columnDisplayName']
            : [];
        $fieldLabels = [
            'username' => '아이디', 'employee_name' => '직원명', 'phone' => '연락처',
            'emergency_phone' => '비상연락처', 'email' => '이메일', 'rrn' => '주민등록번호',
            'address' => '주소', 'address_detail' => '상세주소', 'password' => '비밀번호',
            'department_id' => '부서', 'position_id' => '직위·직책', 'job_id' => '직무',
            'employment_status' => '재직상태', 'role_id' => '역할',
            'doc_hire_date' => '서류입사일', 'real_hire_date' => '실입사일',
            'doc_retire_date' => '서류퇴사일', 'real_retire_date' => '실퇴사일',
            'profile_image' => '프로필사진', 'rrn_image' => '신분증파일',
            'representative_qualification_id' => '대표 자격증',
            'bank_name' => '은행명', 'account_number' => '계좌번호',
            'account_holder' => '예금주', 'bank_file' => '통장사본',
            'two_factor_enabled' => '2차인증', 'email_notify' => '이메일알림',
            'sms_notify' => 'SMS알림', 'note' => '노트', 'memo' => '메모',
        ];

        foreach ($fieldLabels as $key => $fallbackLabel) {
            if (strtolower(trim((string)($policies[$key] ?? 'none'))) !== 'required') {
                continue;
            }

            $hasValue = match ($key) {
                'profile_image' => $this->hasEmployeeUploadOrExisting(
                    $files['profile_image'] ?? null,
                    $current['profile_image'] ?? null,
                    (string)($data['profile_image_delete'] ?? '0') === '1'
                ),
                'rrn_image' => $this->hasEmployeeUploadOrExisting(
                    $files['rrn_image'] ?? null,
                    $current['rrn_image'] ?? null,
                    (string)($data['rrn_image_delete'] ?? '0') === '1'
                ),
                'bank_file' => $this->hasEmployeeUploadOrExisting(
                    $files['bank_file'] ?? null,
                    $current['bank_file'] ?? null,
                    (string)($data['bank_file_delete'] ?? '0') === '1'
                ),
                'representative_qualification_id' => $this->hasEmployeeUploadOrExisting(
                    $files['representative_qualification_file'] ?? null,
                    $currentRepresentativeQualification['attachment_path'] ?? null,
                    (string)($data['representative_qualification_delete'] ?? '0') === '1'
                ) && trim((string)($data['representative_qualification_name'] ?? $currentRepresentativeQualification['qualification_name'] ?? '')) !== '',
                default => trim((string)($data[$key] ?? '')) !== '',
            };

            if (!$hasValue) {
                $label = trim((string)($displayNames[$key] ?? '')) ?: $fallbackLabel;
                throw new \InvalidArgumentException($label . ' 항목은 필수입니다.');
            }
        }
    }

    private function hasEmployeeUploadOrExisting(?array $file, mixed $existing, bool $delete): bool
    {
        $hasUpload = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        return $hasUpload || (!$delete && trim((string)($existing ?? '')) !== '');
    }


}
