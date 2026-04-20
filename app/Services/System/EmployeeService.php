<?php
// 寃쎈줈: PROJECT_ROOT . '/app/Services/System/EmployeeService.php'
// ?ㅻ챸:
//  - 吏곸썝(Employee) 愿由??쒕퉬??
//  - UUID / Code ?앹꽦? Service 梨낆엫
//  - DB 泥섎━: UserPrlfileModel
//  - 紐⑤뱺 二쇱슂 ?먮쫫 LoggerFactory ?곸슜
namespace App\Services\System;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
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
    * 吏곸썝 紐⑸줉
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
            * ?뵦 二쇰?踰덊샇 蹂듯샇??(Service 梨낆엫)
            * ========================================================= */
            if (!empty($rows)) {

                $crypto = new Crypto();

                foreach ($rows as &$row) {

                    if (!empty($row['rrn'])) {

                        $rrn = $crypto->decryptResidentNumber($row['rrn']);

                        // ?뵦 ?レ옄留??④?
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
    * 吏곸썝 ?④굔 議고쉶 (user_employees.id 湲곗?)
    * ========================================================= */
    public function getById(string $id): ?array
    {
        $this->logger->info('getById() called', ['id' => $id]);

        try {

            // ?뵦 諛섎뱶??employees 紐⑤뜽 ?ъ슜
            $row = $this->model->getById($id);

            if (!$row) {
                $this->logger->warning('getById() not found', ['id' => $id]);
                return null;
            }

            /* =========================================================
            * ?뵦 二쇰?踰덊샇 蹂듯샇??(Service 梨낆엫)
            * ========================================================= */
            if (!empty($row['rrn'])) {

                $crypto = new \Core\Security\Crypto();

                $rrn = $crypto->decryptResidentNumber($row['rrn']);

                // ?レ옄留??④?
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
    * 吏곸썝 寃??(Select2)
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
            * ?뵦 Select2 ?щ㎎ 蹂??
            * ========================================================= */
            $results = [];

            foreach ($rows as $row) {

                $text = $row['employee_name'] ?? '';

                // ?뵦 遺?쒕챸 ?덉쑝硫?媛숈씠 ?쒖떆
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
    * 吏곸썝 ???(?좉퇋/?섏젙) - ?뚯씪泥섎━ ?꾩껜?섏젙蹂?
    * 湲곗?: user_employees.id
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
            * ?꾩닔媛?
            * ========================================================= */
            $username     = trim((string)($data['username'] ?? ''));
            $password     = (string)($data['password'] ?? '');
            $employeeName = trim((string)($data['employee_name'] ?? ''));

            if ($isCreate && $username === '') {
                return ['success' => false, 'message' => '?꾩씠?붾뒗 ?꾩닔?낅땲??'];
            }

            if ($employeeName === '') {
                return ['success' => false, 'message' => '吏곸썝紐낆? ?꾩닔?낅땲??'];
            }

            if ($isCreate && $password === '') {
                return ['success' => false, 'message' => '鍮꾨?踰덊샇???꾩닔?낅땲??'];
            }

            /* =========================================================
            * ?섏젙??湲곗〈 ?곗씠??議고쉶
            * ========================================================= */
            $current = null;
            $userId  = null;

            if (!$isCreate) {
                $current = $this->model->getById($employeeId);

                if (!$current) {
                    throw new \Exception('吏곸썝 ?뺣낫 ?놁쓬');
                }

                if (empty($current['user_id'])) {
                    throw new \Exception('?ъ슜???뺣낫 ?놁쓬');
                }

                $userId = $current['user_id'];

                $currentUser = $this->users->getById($userId);
                if (!$currentUser) {
                    throw new \Exception('?ъ슜???뺣낫 ?놁쓬');
                }

                // username 蹂寃쎌떆 DB unique??留↔?
                if ($username !== '' && $currentUser['username'] !== $username) {
                    // no-op
                }
            }

            /* =========================================================
            * AUTH ?곗씠??
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
            * EMPLOYEE ?곗씠??
            * ========================================================= */
            $employeeData = [];

            $fields = [
                'employee_name', 'phone', 'address', 'address_detail',
                'department_id', 'position_id',
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
            * 二쇰?踰덊샇 ?뷀샇??
            * ========================================================= */
            $rrnInput = trim((string)($data['rrn'] ?? ''));

            if (strpos($rrnInput, '*') !== false) {
                return ['success' => false, 'message' => '留덉뒪?밸맂 二쇰?踰덊샇????ν븷 ???놁뒿?덈떎.'];
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
            * ?뚯씪 ??젣 ?뚮옒洹?
            * ========================================================= */
            $deleteProfile      = ((string)($data['profile_image_delete'] ?? '0') === '1');
            $deleteRrnImage     = ((string)($data['rrn_image_delete'] ?? '0') === '1');
            $deleteCertificate  = ((string)($data['certificate_file_delete'] ?? '0') === '1');
            $deleteBankFile     = ((string)($data['bank_file_delete'] ?? '0') === '1');

            /* =========================================================
            * 湲곕낯媛? ?섏젙?쒕뒗 湲곗〈 ?뚯씪 ?좎?
            * ========================================================= */
            if ($isCreate) {

                $employeeData['profile_image']    = null;
                $employeeData['rrn_image']        = null;
                $employeeData['certificate_file'] = null;
                $employeeData['bank_file']        = null;
            
            } else {
            
                // ?뵦 ??젣 ?뚮옒洹?諛섏쁺?댁꽌 湲곕낯媛??명똿
                $employeeData['profile_image']    = $deleteProfile ? null : ($current['profile_image'] ?? null);
                $employeeData['rrn_image']        = $deleteRrnImage ? null : ($current['rrn_image'] ?? null);
                $employeeData['certificate_file'] = $deleteCertificate ? null : ($current['certificate_file'] ?? null);
                $employeeData['bank_file']        = $deleteBankFile ? null : ($current['bank_file'] ?? null);
            }

            /* =========================================================
            * ??젣 ?뚮옒洹?諛섏쁺
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
            * ?뚯씪 ?낅줈??泥섎━ (FileService 湲곗? 理쒖쥌蹂?
            * ========================================================= */

            // 1) ?꾨줈???ъ쭊
            $file = $files['profile_image'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadProfile($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '?꾨줈???대?吏 ?낅줈???ㅽ뙣'];
                }

                $employeeData['profile_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['profile_image']) && !$deleteProfile) {
                    $deleteAfterCommit[] = $current['profile_image'];
                }
            }


            // 2) ?좊텇利??대?吏 (?뵦 uploadPrivateIdDoc ?ъ슜)
            $file = $files['rrn_image'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadPrivateIdDoc($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '?좊텇利??낅줈???ㅽ뙣'];
                }

                $employeeData['rrn_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['rrn_image']) && !$deleteRrnImage) {
                    $deleteAfterCommit[] = $current['rrn_image'];
                }
            }


            // 3) ?먭꺽利??뚯씪 (?뵦 uploadCertificate ?ъ슜)
            $file = $files['certificate_file'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadCertificate($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '?먭꺽利??뚯씪 ?낅줈???ㅽ뙣'];
                }

                $employeeData['certificate_file'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['certificate_file']) && !$deleteCertificate) {
                    $deleteAfterCommit[] = $current['certificate_file'];
                }
            }


            // 4) ?듭옣?щ낯 (?뵦 uploadBankCopy ?좎?)
            $file = $files['bank_file'] ?? null;

            if ($file && $file['error'] === UPLOAD_ERR_OK) {

                $upload = $this->fileService->uploadBankCopy($file);

                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '?듭옣?щ낯 ?낅줈???ㅽ뙣'];
                }

                $employeeData['bank_file'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($current['bank_file']) && !$deleteBankFile) {
                    $deleteAfterCommit[] = $current['bank_file'];
                }
            }
            /* =========================================================
            * ????쒖옉
            * ========================================================= */
            $this->pdo->beginTransaction();

            try {
                if ($isCreate) {
                    $newUserId = UuidHelper::generate();

                    $authData['id'] = $newUserId;
                    $authData['created_by'] = $actor;

                    if (!$this->users->createUser($authData)) {
                        throw new \Exception('?ъ슜???앹꽦 ?ㅽ뙣');
                    }

                    $newEmployeeId = UuidHelper::generate();

                    $employeeData['id'] = $newEmployeeId;
                    $employeeData['code'] = CodeHelper::generateEmployeeCode($this->pdo);
                    $employeeData['user_id'] = $newUserId;
                    $employeeData['created_by'] = $actor;

                    if (!$this->model->create($employeeData)) {
                        throw new \Exception('吏곸썝 ?앹꽦 ?ㅽ뙣');
                    }

                    $this->pdo->commit();

                    foreach (array_unique($deleteAfterCommit) as $path) {
                        $this->fileService->delete($path);
                    }

                    return [
                        'success' => true,
                        'id'      => $newEmployeeId,
                        'code'    => $employeeData['code'],
                        'message' => '????꾨즺'
                    ];
                }

                if (!empty($authData)) {
                    if (!$this->users->updateUserDirect($userId, $authData)) {
                        throw new \Exception('?ъ슜???섏젙 ?ㅽ뙣');
                    }
                }

                if (!$this->model->updateById($employeeId, $employeeData)) {
                    throw new \Exception('吏곸썝 ?섏젙 ?ㅽ뙣');
                }

                $this->pdo->commit();

                foreach (array_unique($deleteAfterCommit) as $path) {
                    $this->fileService->delete($path);
                }

                return [
                    'success' => true,
                    'id'      => $employeeId,
                    'message' => '????꾨즺'
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
    * 吏곸썝 ?곹깭 蹂寃?(?쒖꽦/鍮꾪솢??
    * 湲곗?: user_employees.id ??auth_users ?낅뜲?댄듃
    * ========================================================= */
    public function updateStatus(string $employeeId, bool $isActive): array
    {
        $actor = ActorHelper::resolve('USER');

        try {

            if ($employeeId === '') {
                return [
                    'success' => false,
                    'message' => '吏곸썝 ?꾩씠???꾨씫'
                ];
            }

            /* =========================================================
            * ?뵦 user_id 議고쉶 (getById ?⑥씪??
            * ========================================================= */
            $employee = $this->model->getById($employeeId);

            if (!$employee || empty($employee['user_id'])) {
                return [
                    'success' => false,
                    'message' => '?ъ슜???뺣낫 ?놁쓬'
                ];
            }

            $userId = $employee['user_id'];

            /* =========================================================
            * ?뵦 auth_users ?곹깭 ?낅뜲?댄듃
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
                throw new \Exception('?곹깭 ?낅뜲?댄듃 ?ㅽ뙣');
            }

            return [
                'success' => true,
                'message' => $isActive
                    ? '怨꾩젙???쒖꽦?붾릺?덉뒿?덈떎.'
                    : '怨꾩젙??鍮꾪솢?깊솕?섏뿀?듬땲??'
            ];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => '?곹깭 蹂寃??ㅽ뙣',
                'error'   => $e->getMessage()
            ];
        }
    }

    /* =========================================================
    * 吏곸썝 ?꾩쟾??젣 (理쒖쥌 ?덉젙??踰꾩쟾)
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
                'message' => '吏곸썝 ?꾩씠???꾨씫'
            ];
        }

        try {

            /* =========================
            * 1截뤴깵 吏곸썝 議고쉶 (??1踰?
            * ========================= */
            $employee = $this->model->getById($employeeId);

            if (!$employee) {
                return [
                    'success' => false,
                    'message' => '議댁옱?섏? ?딅뒗 吏곸썝?낅땲??'
                ];
            }

            if (empty($employee['user_id'])) {
                return [
                    'success' => false,
                    'message' => '?ъ슜???뺣낫 ?놁쓬'
                ];
            }

            $userId = $employee['user_id'];

            /* =========================
            * 2截뤴깵 ??젣???뚯씪 紐⑸줉 ?뺣낫
            * ========================= */
            $deleteAfterCommit = [];

            foreach (['profile_image','rrn_image','certificate_file','bank_file'] as $field) {
                if (!empty($employee[$field])) {
                    $deleteAfterCommit[] = $employee[$field];
                }
            }

            /* =========================
            * 3截뤴깵 DB ??젣 (?듭떖)
            * ========================= */
            $this->pdo->beginTransaction();

            try {
                $employeeDeleted = $this->model->hardDeleteById($employeeId);

                if (!$employeeDeleted) {
                    throw new \Exception('직원 삭제 실패');
                }

                $ok = $this->users->hardDeleteById($userId);

                if (!$ok) {
                    throw new \Exception('?ъ슜????젣 ?ㅽ뙣');
                }
                $this->pdo->commit();

            } catch (\Throwable $e) {

                $this->pdo->rollBack();
                throw $e;
            }

            /* =========================
            * 4截뤴깵 ?뚯씪 ??젣 (commit ?댄썑)
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
                'message' => '吏곸썝 ??젣 ?ㅽ뙣'
            ];
        }
    }
    
    /* ============================================================
    * 肄붾뱶 ?쒖꽌 蹂寃?(RowReorder)
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

            /* 1截뤴깵 ?낅젰媛?寃利?*/
            foreach ($changes as $row) {

                if (
                    empty($row['id']) ||
                    !isset($row['newCode'])
                ) {
                    throw new \Exception('reorder ?곗씠???ㅻ쪟');
                }
            }

            /* 2截뤴깵 temp ?대룞 (異⑸룎 諛⑹?) */
            foreach ($changes as $row) {

                // ?몛 ?됰꼮?섍쾶 (?덈? 異⑸룎 ?덈굹寃?
                $tempCode = (int)$row['newCode'] + 1000000;

                $this->model->updateCode(
                    $row['id'],
                    $tempCode
                );
            }

            /* 3截뤴깵 ?ㅼ젣 肄붾뱶 ?곸슜 */
            foreach ($changes as $row) {

                $this->model->updateCode(
                    $row['id'],
                    (int)$row['newCode']
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