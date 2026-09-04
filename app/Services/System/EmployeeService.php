<?php
namespace App\Services\System;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use App\Models\Institution\QualificationModel;
use App\Models\Institution\EducationModel;
use App\Services\Institution\EmployeeHrBaselineService;
use App\Services\Institution\SocialInsuranceService;
use App\Services\File\FileService;
use App\Services\Concerns\LogsServiceOperations;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\ActorHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;

class EmployeeService
{
    use LogsServiceOperations;
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

    }

    public function getList(array $filters = []): array
    {
        try {

            $rows = $this->model->getList($filters);

            $this->logger->info('직원 목록을 조회했습니다.', [
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

            $this->logger->error('직원 목록 조회에 실패했습니다.', [
                'filter_keys' => array_keys($filters),
                'error_code' => get_class($e),
                'error' => $e
            ]);

            return [];
        }
    }

    public function getById(string $id): ?array
    {
        $this->logger->info('직원 상세를 조회합니다.', ['id' => $id]);

        try {

            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('직원을 찾을 수 없습니다.', ['id' => $id]);
                return null;
            }

            if (!empty($row['rrn'])) {

                $crypto = new \Core\Security\Crypto();

                $rrn = $crypto->decryptResidentNumber($row['rrn']);

                $row['rrn'] = preg_replace('/\D+/', '', $rrn);

            } else {

                $row['rrn'] = '';

            }

            $row['qualification_count'] = $this->qualifications->countByEmployee($id);
            $row['education_count'] = $this->educations->countByEmployee($id);
            $row['social_insurance_summary']=(new SocialInsuranceService($this->pdo))->currentSummary($id);

            return $row;

        } catch (\Throwable $e) {

            $this->logger->error('직원 상세 조회 중 예외가 발생했습니다.', [
                'id'        => $id,
                'error_code' => get_class($e),
                'error' => $e
            ]);

            return null;
        }
    }

    public function searchPicker(string $q = '', int $limit = 20, bool $includeInactive = false): array
    {
        $this->logger->info('직원 선택목록을 조회합니다.', [
            'q'     => $q,
            'limit' => $limit
        ]);

        try {

            $rows = $this->model->searchPicker($q, $limit, $includeInactive);

            if (empty($rows)) {
                return [];
            }

            $results = [];

            foreach ($rows as $row) {

                $text = $row['employee_name'] ?? '';

                if (!empty($row['department_name'])) {
                    $text .= ' (' . $row['department_name'] . ')';
                }
                if (!(int) ($row['is_active'] ?? 0)) {
                    $text .= ' · 비활성';
                }

                $results[] = [
                    'id' => $row['id'],   // user_employees.id
                    'text' => $text,
                    'sort_no' => (int) ($row['sort_no'] ?? 0),
                    'position_name' => trim((string) ($row['position_name'] ?? '')),
                ];
            }

            return $results;

        } catch (\Throwable $e) {

            $this->logger->error('직원 선택목록 조회에 실패했습니다.', [
                'q'         => $q,
                'limit'     => $limit,
                'error_code' => get_class($e),
                'error' => $e
            ]);

            return [];
        }
    }

    public function representativeQualifications(string $employeeId): array
    {
        return $this->qualifications->eligibleRepresentativeQualifications($employeeId);
    }

    public function save(array $data, string $actorType = 'USER', array $files = []): array
    {
        return $this->loggedEmployeeMutation('직원 저장','EMPLOYEE_SAVE','save',fn():array=>$this->saveInternal($data,$actorType,$files));
    }

    private function saveInternal(array $data, string $actorType = 'USER', array $files = []): array
    {
        $actor = ActorHelper::resolve($actorType);

        $employeeId = trim((string)($data['id'] ?? ''));
        $isCreate   = ($employeeId === '');

        $this->logger->info('직원 저장을 시작합니다.', [
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
            $representativeQualificationId = trim((string)($data['representative_qualification_id'] ?? ''));
            if ($isCreate && $representativeQualificationId !== '') {
                return ['success' => false, 'message' => '직원 등록 후 검증 완료된 자격을 대표자격으로 지정해 주세요.'];
            }
            if (!$isCreate && $representativeQualificationId !== ''
                && !$this->qualifications->eligibleRepresentativeQualification($employeeId, $representativeQualificationId)) {
                return ['success' => false, 'message' => '해당 직원의 검증 완료된 유효 자격만 대표자격으로 지정할 수 있습니다.'];
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

                $this->model->updateRepresentativeQualificationId(
                    $employeeId,
                    $representativeQualificationId !== '' ? $representativeQualificationId : null
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
            $this->logger->error('직원 저장에 실패했습니다.', [
                'employeeId' => $employeeId,
                'error_code' => get_class($e),
                'error' => $e
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function updateStatus(string $employeeId, bool $isActive): array
    {
        return $this->loggedEmployeeMutation('직원 상태 변경','EMPLOYEE_STATUS_UPDATE','update-status',fn():array=>$this->updateStatusInternal($employeeId,$isActive));
    }

    private function updateStatusInternal(string $employeeId, bool $isActive): array
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
        return $this->loggedEmployeeMutation('직원 영구삭제','EMPLOYEE_PURGE','purge',fn():array=>$this->purgeInternal($employeeId,$actorType));
    }

    private function purgeInternal(string $employeeId, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        $this->logger->info('직원 영구삭제를 시작합니다.', [
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

            }

            return [
                'success' => true
            ];

        } catch (\Throwable $e) {

            $this->logger->error('직원 영구삭제에 실패했습니다.', [
                'employeeId' => $employeeId,
                'error_code' => get_class($e),
                'error' => $e
            ]);

            return [
                'success' => false,
                'message' => '직원 삭제 실패'
            ];
        }
    }

    public function reorder(array $changes): bool
    {
        return $this->runLoggedOperation($this->logger,'직원 정렬 저장','EMPLOYEE_REORDER','reorder',['change_count'=>count($changes)],fn():bool=>$this->reorderInternal($changes),'info',false,static fn(bool $result):string=>$result?'SUCCESS':'BLOCKED');
    }

    private function reorderInternal(array $changes): bool
    {
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

            $this->logger->info('직원 정렬을 저장했습니다.');

            return true;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('직원 정렬 저장에 실패했습니다.', [
                'error_code' => get_class($e),
                'error' => $e,
                'change_count' => count($changes)
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
                'representative_qualification_id' => trim((string)($data['representative_qualification_id'] ?? '')) !== '',
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

    private function loggedEmployeeMutation(string $label,string $eventCode,string $action,callable $operation): array
    {
        return $this->runLoggedOperation($this->logger,$label,$eventCode,$action,[],$operation,'info',false,
            static fn(array $result):string=>!empty($result['success'])?'SUCCESS':(str_contains((string)($result['message']??''),'오류')?'FAILED':'BLOCKED'));
    }


}
