<?php
// 경로: PROJECT_ROOT . '/app/Services/System/EmployeeService.php'
// 설명:
//  - 직원(Employee) 관리 서비스
//  - UUID / sort_no 생성은 Service 책임
//  - DB 처리: UserProfileModel
//  - 모든 주요 흐름에 LoggerFactory 적용
namespace App\Services\System;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
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
    private FileService $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo        = $pdo;
        $this->users      = new UserModel($pdo);
        $this->model  = new EmployeeModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger     = LoggerFactory::getLogger('service-system.EmployeeService');

        $this->logger->info('EmployeeService initialized');
    }

   /* =========================================================
    * 직원 목록
    * ========================================================= */
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

            /* =========================================================
            * 주민등록번호 복호화(Service 책임)
            * ========================================================= */
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

    /* =========================================================
    * 직원 단건 조회 (user_employees.id 기준)
    * ========================================================= */
    public function getById(string $id): ?array
    {
        $this->logger->info('getById() called', ['id' => $id]);

        try {

            // 반드시 employees 모델 사용
            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', ['id' => $id]);
                return null;
            }

            /* =========================================================
            * 주민등록번호 복호화(Service 책임)
            * ========================================================= */
            if (!empty($row['rrn'])) {

                $crypto = new \Core\Security\Crypto();

                $rrn = $crypto->decryptResidentNumber($row['rrn']);

                // 숫자만 전달
                $row['rrn'] = preg_replace('/\D+/', '', $rrn);

                $this->logger->info('rrn decrypted', [
                    'employee_id' => $id
                ]);

            } else {

                $row['rrn'] = '';

            }

            return $row;

        } catch (\Throwable $e) {

            $this->logger->error('getById() exception', [
                'id'        => $id,
                'exception' => $e->getMessage()
            ]);

            return null;
        }
    }


    /* =========================================================
    * 직원 검색(Select2)
    * ========================================================= */
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

            /* =========================================================
            * Select2 포맷 변환
            * ========================================================= */
            $results = [];

            foreach ($rows as $row) {

                $text = $row['employee_name'] ?? '';

                // 부서명이 있으면 같이 표시
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
    /* =========================================================
    * 직원 저장(신규/수정) - 파일처리 포함 전체 수정 정보
    * 기준: user_employees.id
    * ========================================================= */
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

            /* =========================================================
            * 필수값
            * ========================================================= */
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

            /* =========================================================
            * 수정 시 기존 데이터 조회
            * ========================================================= */
            $current = null;
            $userId  = null;

            if (!$isCreate) {
                $current = $this->model->getById($employeeId);

                if (!$current) {
                    throw new \Exception('직원 정보 없음');
                }

                if (empty($current['user_id'])) {
                    throw new \Exception('사용자 정보 없음');
                }

                $userId = $current['user_id'];

                $currentUser = $this->users->getById($userId);
                if (!$currentUser) {
                    throw new \Exception('사용자 정보 없음');
                }

                // username 변경 시 DB unique 제약이 처리합니다.
                if ($username !== '' && $currentUser['username'] !== $username) {
                    // no-op
                }
            }

            /* =========================================================
            * AUTH 데이터
            * ========================================================= */
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

            /* =========================================================
            * EMPLOYEE 데이터
            * ========================================================= */
            $employeeData = [];

            $fields = [
                'employee_name', 'phone', 'address', 'address_detail',
                'department_id', 'position_id', 'client_id',
                'certificate_name', 'note', 'memo',
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

            $employeeData['updated_by'] = $actor;

            /* =========================================================
            * 주민등록번호 암호화
            * ========================================================= */
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

            /* =========================================================
            * 파일 삭제 플래그
            * ========================================================= */
            $deleteProfile      = ((string)($data['profile_image_delete'] ?? '0') === '1');
            $deleteRrnImage     = ((string)($data['rrn_image_delete'] ?? '0') === '1');
            $deleteCertificate  = ((string)($data['certificate_file_delete'] ?? '0') === '1');
            $deleteBankFile     = ((string)($data['bank_file_delete'] ?? '0') === '1');

            /* =========================================================
            * 기본값 설정 또는 기존 파일 유지
            * ========================================================= */
            if ($isCreate) {

                $employeeData['profile_image']    = null;
                $employeeData['rrn_image']        = null;
                $employeeData['certificate_file'] = null;
                $employeeData['bank_file']        = null;
            
            } else {
            
                // 삭제 플래그를 반영해서 기본값 세팅
                $employeeData['profile_image']    = $deleteProfile ? null : ($current['profile_image'] ?? null);
                $employeeData['rrn_image']        = $deleteRrnImage ? null : ($current['rrn_image'] ?? null);
                $employeeData['certificate_file'] = $deleteCertificate ? null : ($current['certificate_file'] ?? null);
                $employeeData['bank_file']        = $deleteBankFile ? null : ($current['bank_file'] ?? null);
            }

            /* =========================================================
            * 삭제 플래그 반영
            * ========================================================= */
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

            if ($deleteCertificate) {
                if (!$isCreate && !empty($current['certificate_file'])) {
                    $deleteAfterCommit[] = $current['certificate_file'];
                }
                $employeeData['certificate_file'] = null;
                $employeeData['certificate_name'] = null;
            }

            if ($deleteBankFile && !$isCreate && !empty($current['bank_file'])) {

                // ?뵦 DB 媛?癒쇱? NULL 泥섎━
                $employeeData['bank_file'] = null;
            
                // ?뵦 ??젣 ????깅줉
                $deleteAfterCommit[] = $current['bank_file'];
            }

            /* =========================================================
            * 파일 업로드 처리 (FileService 기준 최종본)
            * ========================================================= */

            // 1) 프로필 사진
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


            // 2) 신분증 이미지
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


            // 3) 자격증 파일
            $file = $files['certificate_file'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadCertificate($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '자격증 파일 업로드 실패'];
                }

                $employeeData['certificate_file'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['certificate_file']) && !$deleteCertificate) {
                    $deleteAfterCommit[] = $current['certificate_file'];
                }
            }


            // 4) 통장사본
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
            /* =========================================================
            * 저장 시작
            * ========================================================= */
            $this->pdo->beginTransaction();

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
                    $employeeData['created_by'] = $actor;

                    if (!$this->model->create($employeeData)) {
                        throw new \Exception('직원 생성 실패');
                    }

                    $this->pdo->commit();

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

                $this->pdo->commit();

                foreach (array_unique($deleteAfterCommit) as $path) {
                    $this->fileService->delete($path);
                }

                return [
                    'success' => true,
                    'id'      => $employeeId,
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
                'employeeId' => $employeeId,
                'error'      => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    

    /* =========================================================
    * 직원 상태 변경(활성/비활성)
    * 기준: user_employees.id 및 auth_users 업데이트
    * ========================================================= */
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

            /* =========================================================
            * user_id 조회
            * ========================================================= */
            $employee = $this->model->getById($employeeId);

            if (!$employee || empty($employee['user_id'])) {
                return [
                    'success' => false,
                    'message' => '사용자 정보 없음'
                ];
            }

            $userId = $employee['user_id'];

            /* =========================================================
            * auth_users 상태 업데이트
            * ========================================================= */
            $data = [
                'is_active'  => $isActive ? 1 : 0,
                'deleted_at' => $isActive ? null : date('Y-m-d H:i:s'),
                'deleted_by' => $isActive ? null : $actor,
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

    /* =========================================================
    * 직원 영구 삭제
    * ========================================================= */
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

            /* =========================
            * 1단계 직원 조회
            * ========================= */
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

            /* =========================
            * 2단계 삭제할 파일 목록 확보
            * ========================= */
            $deleteAfterCommit = [];

            foreach (['profile_image','rrn_image','certificate_file','bank_file'] as $field) {
                if (!empty($employee[$field])) {
                    $deleteAfterCommit[] = $employee[$field];
                }
            }

            /* =========================
            * 3단계 DB 삭제
            * ========================= */
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

            /* =========================
            * 4단계 파일 삭제(commit 이후)
            * ========================= */
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
    
    /* ============================================================
    * 순번 변경(RowReorder)
    * ============================================================ */
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

            /* 1단계 입력값 검증 */
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

            /* 2단계 temp 이동 (충돌 방지) */
            foreach ($changes as $row) {

                // 먼저 충분히 큰 값으로 이동해 중복 충돌을 피합니다.
                $tempSortNo = $row['_sort_no'] + 1000000;

                $this->model->updateSortNo(
                    $row['id'],
                    $tempSortNo
                );
            }

            /* 3단계 실제 순번 적용 */
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


}
