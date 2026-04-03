<?php
// 경로: PROJECT_ROOT . '/app/services/user/ProfileService.php';
namespace App\Services\User;

use PDO;
use App\Models\Auth\AuthUserModel;
use App\Models\User\UserProfileModel;
use App\Services\File\FileService;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\Helpers\ActorHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;

class ProfileService
{
    private readonly PDO $pdo;
    private $users;
    private $profiles;
    private $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->users       = new AuthUserModel($pdo);
        $this->profiles    = new UserProfileModel($pdo);
        $this->fileService = new FileService($pdo);
        $this->logger      = LoggerFactory::getLogger("service-user.ProfileService");

        $this->logger->info("ProfileService::__construct", [
            'pdo' => is_object($pdo) ? get_class($pdo) : gettype($pdo)
        ]);
    }

    /* ============================================================
     * 직원 전체 조회
     * ============================================================ */
    public function getList(array $filters = []): array
    {
        $this->logger->info("ProfileService::getAllProfiles START", [
            'actor'   => ActorHelper::resolve('USER'),
            'filters' => $filters
        ]);
    
        try {
            $this->logger->info("ProfileService::getAllProfiles DB QUERY", [
                'fn'      => 'UserProfileModel::getAllProfiles',
                'filters' => $filters
            ]);
    
            $list = $this->profiles->getList($filters);
    
            $this->logger->info("ProfileService::getAllProfiles END", [
                'count' => is_array($list) ? count($list) : 0
            ]);
    
            return $list;
    
        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::getAllProfiles EXCEPTION", [
                'filters' => $filters,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /* ============================================================
     * 사용자 기본 정보 가져오기
     * ============================================================ */
    public function getDetail(string $userId): ?array
    {
        $this->logger->info('getDetail 호출', [
            'user_id' => $userId
        ]);
    
        // 1. Users
        $user = $this->users->getById($userId);
    
        if (!$user) {
            return null;
        }
    
        // 2. Profile
        $profile = $this->profiles->getByUserId($userId);
    
        // 3. 병합
        $result = array_merge(
            $user,
            $profile ?? []
        );
    
        return $result;
    }

    /* ============================================================
     * 유저 + 프로필 조회(UserId기준)
     * ============================================================ */
    public function getByUserId(string $userId): ?array
    {
        $this->logger->info("ProfileService::getProfile START", [
            'userId' => $userId,
            'actor'  => ActorHelper::resolve('USER')
        ]);

        try {
            $this->logger->info("ProfileService::getProfile DB QUERY", [
                'fn' => 'UserProfileModel::getUserWithProfile',
                'userId' => $userId
            ]);

            $row = $this->profiles->getUserWithProfile($userId);

            $this->logger->info("ProfileService::getProfile END", [
                'userId' => $userId,
                'found'  => (bool)$row
            ]);

            return $row ?: null;

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::getProfile EXCEPTION", [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return null;
        }
    }



    // ============================================================
    // 직원 검색 (Select2용)
    // ============================================================
    public function search(string $q = '', int $limit = 20): array
    {
        $this->logger->info("ProfileService::searchEmployees START", [
            'actor' => ActorHelper::resolve('USER'),
            'q'     => $q,
            'limit' => $limit
        ]);

        try {
            $limit = max(1, min(100, (int)$limit));

            $this->logger->info("ProfileService::searchEmployees DB QUERY", [
                'fn'    => 'UserProfileModel::searchEmployees',
                'q'     => $q,
                'limit' => $limit
            ]);

            $rows = $this->profiles->search($q, $limit);

            $this->logger->info("ProfileService::searchEmployees END", [
                'count' => is_array($rows) ? count($rows) : 0
            ]);

            return $rows;

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::searchEmployees EXCEPTION", [
                'q'     => $q,
                'limit' => $limit,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [];
        }
    }



    public function save(array $data, array $files = []): array
    {
        $actor  = ActorHelper::resolve('USER');
        $userId = trim((string)($data['id'] ?? ''));
        $isCreate = ($userId === '');

        $this->logger->info("ProfileService::save START", [
            'actor'     => $actor,
            'userId'    => $userId,
            'isCreate'  => $isCreate,
            'data_keys' => array_keys($data),
            'file_keys' => array_keys($files),
        ]);

        $uploadedNewFiles = [];
        $deleteAfterCommit = [];

        try {
            /* ============================================================
            * 1) 공통 기본값 / 검증
            * ============================================================ */
            $username = trim((string)($data['username'] ?? ''));
            $password = (string)($data['password'] ?? '');
            $employeeName = trim((string)($data['employee_name'] ?? ''));
            
            /* 🔥 신규 생성일 때만 username 체크 */
            if ($isCreate && $username === '') {
                return ['success' => false, 'message' => '아이디는 필수입니다.'];
            }

            if ($employeeName === '') {
                return ['success' => false, 'message' => '직원명은 필수입니다.'];
            }

            if ($isCreate && $password === '') {
                return ['success' => false, 'message' => '비밀번호는 필수입니다.'];
            }

            /* ============================================================
            * 2) username 중복 체크
            *    - 신규: 있으면 실패
            *    - 수정: 본인 아이디면 허용
            * ============================================================ */
            $exists = $this->users->existsByUsername($username);

            if ($exists) {
                if ($isCreate) {
                    return ['success' => false, 'message' => '이미 사용중인 아이디입니다.'];
                }

                $currentUsername = $this->users->getUsername($userId);
                if ($currentUsername !== $username) {
                    return ['success' => false, 'message' => '이미 사용중인 아이디입니다.'];
                }
            }

            /* ============================================================
            * 3) AUTH 데이터 구성
            * ============================================================ */
            $authData = [];

            /* username - 값 있을 때만 */
            if (isset($data['username']) && trim($data['username']) !== '') {
                $authData['username'] = trim($data['username']);
            }
            
            /* email */
            if (array_key_exists('email', $data)) {
                $authData['email'] = trim((string)$data['email']);
            }
            
            /* role_id - 🔥 핵심 */
            if (array_key_exists('role_id', $data)) {
                if ($data['role_id'] !== '') {
                    $authData['role_id'] = $data['role_id'];
                }
                // ❌ 빈값이면 아예 넣지 않는다 (NULL 덮어쓰기 방지)
            }
            
            /* flags */
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

            /* ============================================================
            * 4) PROFILE 데이터 구성
            * ============================================================ */
            $profileData = [];

            $profileFields = [
                'employee_name','phone','address','address_detail',
                'department_id','position_id',
                'certificate_name','note','memo',
                'doc_hire_date','real_hire_date','doc_retire_date','real_retire_date',
                'emergency_phone'
            ];

            foreach ($profileFields as $field) {
                if (array_key_exists($field, $data)) {
                    $profileData[$field] = ($data[$field] === '') ? null : $data[$field];
                }
            }

            /* ============================================================
            * 5) 주민번호 처리
            *    - 빈값이면 기존 유지
            *    - 마스킹값은 저장 금지
            * ============================================================ */
            $rrnInput = trim((string)($data['rrn'] ?? ''));

            if (strpos($rrnInput, '*') !== false) {
                return ['success' => false, 'message' => '마스킹된 주민번호는 저장할 수 없습니다.'];
            }

            $rrnRaw = preg_replace('/\D+/', '', $rrnInput);
            if ($rrnRaw !== '') {
                $crypto = new Crypto();
                $profileData['rrn'] = $crypto->encryptResidentNumber($rrnRaw);
            }

            /* ============================================================
            * 6) 수정 시 현재 프로필 조회
            * ============================================================ */
            $currentProfile = null;
            if (!$isCreate) {
                $currentProfile = $this->profiles->getByUserId($userId);
            }

            /* ============================================================
            * 7) 파일 업로드 처리
            *    - save는 "파일 업로드 + DB 반영용 path 세팅"만 한다
            *    - 직접 DB update 하지 않는다
            * ============================================================ */

            // 7-1. 프로필 이미지
            if (!empty($files['profile_image']['name'])) {
                $upload = $this->fileService->uploadProfile($files['profile_image']);

                if (empty($upload['success']) || empty($upload['db_path'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '프로필 이미지 업로드 실패'];
                }

                $profileData['profile_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($currentProfile['profile_image'])) {
                    $deleteAfterCommit[] = $currentProfile['profile_image'];
                }
            }

            // 7-2. 주민번호 이미지
            if (!empty($files['rrn_image']['name'])) {
                $upload = $this->fileService->uploadPrivateIdDoc($files['rrn_image']);

                if (empty($upload['success']) || empty($upload['db_path'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '신분증 이미지 업로드 실패'];
                }

                $profileData['rrn_image'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($currentProfile['rrn_image'])) {
                    $deleteAfterCommit[] = $currentProfile['rrn_image'];
                }
            }

            // 7-3. 자격증 파일
            if (!empty($files['certificate_file']['name'])) {
                $upload = $this->fileService->uploadCertificate($files['certificate_file']);

                if (empty($upload['success']) || empty($upload['db_path'])) {
                    return ['success' => false, 'message' => $upload['message'] ?? '자격증 파일 업로드 실패'];
                }

                $profileData['certificate_file'] = $upload['db_path'];
                $uploadedNewFiles[] = $upload['db_path'];

                if (!$isCreate && !empty($currentProfile['certificate_file'])) {
                    $deleteAfterCommit[] = $currentProfile['certificate_file'];
                }
            }

            /* ============================================================
            * 8) 삭제 플래그 처리
            *    - save 안에서만 "null 세팅 + 커밋 후 파일 삭제" 처리
            * ============================================================ */
            if (!$isCreate) {
                if (!empty($data['profile_image_delete']) && $data['profile_image_delete'] == "1") {
                    if (empty($profileData['profile_image']) && !empty($currentProfile['profile_image'])) {
                        $profileData['profile_image'] = null;
                        $deleteAfterCommit[] = $currentProfile['profile_image'];
                    }
                }

                if (!empty($data['rrn_image_delete']) && $data['rrn_image_delete'] == "1") {
                    if (empty($profileData['rrn_image']) && !empty($currentProfile['rrn_image'])) {
                        $profileData['rrn_image'] = null;
                        $deleteAfterCommit[] = $currentProfile['rrn_image'];
                    }
                }

                if (!empty($data['certificate_file_delete']) && $data['certificate_file_delete'] == "1") {
                    if (empty($profileData['certificate_file']) && !empty($currentProfile['certificate_file'])) {
                        $profileData['certificate_file'] = null;
                        $deleteAfterCommit[] = $currentProfile['certificate_file'];
                    }
                }
            }

            /* ============================================================
            * 9) 신규 / 수정 저장
            * ============================================================ */
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

                    $profileData['user_id'] = $newUserId;
                    $profileData['created_by'] = $actor;

                    $profileResult = $this->profiles->create($profileData);
                    if (empty($profileResult['success'])) {
                        throw new \Exception($profileResult['message'] ?? '프로필 생성 실패');
                    }

                    $this->pdo->commit();

                    foreach (array_unique($deleteAfterCommit) as $path) {
                        $this->deleteFile($path);
                    }

                    return [
                        'success' => true,
                        'id'      => $newUserId,
                        'code'    => $profileResult['code'] ?? null,
                        'message' => '저장 완료'
                    ];
                }

                /* 1️⃣ users 업데이트 */
                if (!empty($authData)) {
                    $okUser = $this->users->updateUserDirect($userId, $authData);

                    if ($okUser === false) {
                        throw new \Exception('사용자 정보 수정 실패');
                    }
                }

                /* 2️⃣ profiles 업데이트 */
                $result = $this->profiles->updateByUserId($userId, $profileData);

                if ($result === false) {
                    throw new \Exception('수정 실패');
                }

                $this->pdo->commit();

                foreach (array_unique($deleteAfterCommit) as $path) {
                    $this->deleteFile($path);
                }

                return [
                    'success' => true,
                    'id'      => $userId,
                    'message' => '저장 완료'
                ];

            } catch (\Throwable $e) {
                $this->pdo->rollBack();

                // 롤백 시 새로 업로드한 파일은 정리
                foreach (array_unique($uploadedNewFiles) as $path) {
                    $this->deleteFile($path);
                }

                throw $e;
            }

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::save EXCEPTION", [
                'userId'    => $userId,
                'isCreate'  => $isCreate,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => '저장 실패',
                'error'   => $e->getMessage()
            ];
        }
    }

/* ============================================================
 * 직원 삭제 (영구 삭제)
 * ============================================================ */
public function delete(string $userId): array
{
    $actor = ActorHelper::resolve('USER');

    $this->logger->info("ProfileService::delete START", [
        'actor'  => $actor,
        'userId' => $userId
    ]);

    try {
        if ($userId === '') {
            return ['success' => false, 'message' => 'user_id 누락'];
        }

        $this->pdo->beginTransaction();

        try {
            // 1. 프로필 조회 (파일 정리용)
            $profile = $this->profiles->getByUserId($userId);

            // 2. DB 삭제
            $profileOk = $this->profiles->hardDeleteByUserId($userId);
            $userOk    = $this->users->hardDeleteByUserId($userId);

            if (!$profileOk || !$userOk) {
                throw new \Exception('삭제 실패');
            }

            $this->pdo->commit();

            // 3. 파일 삭제 (커밋 이후)
            if (!empty($profile['profile_image'])) {
                $this->deleteFile($profile['profile_image']);
            }

            if (!empty($profile['rrn_image'])) {
                $this->deleteFile($profile['rrn_image']);
            }

            if (!empty($profile['certificate_file'])) {
                $this->deleteFile($profile['certificate_file']);
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
        $this->logger->error("ProfileService::delete EXCEPTION", [
            'userId' => $userId,
            'error'  => $e->getMessage()
        ]);

        return [
            'success' => false,
            'message' => '삭제 실패',
            'error'   => $e->getMessage()
        ];
    }
}









/* ============================================================
 * 직원 상태 변경 (활성/비활성)
 * ============================================================ */
public function updateStatus(string $userId, bool $isActive): array
{
    $actor = ActorHelper::resolve('USER');

    $this->logger->info("ProfileService::updateStatus START", [
        'actor'    => $actor,
        'userId'   => $userId,
        'isActive' => $isActive
    ]);

    try {
        if ($userId === '') {
            return [
                'success' => false,
                'message' => 'user_id 누락'
            ];
        }

        $data = [
            'is_active' => $isActive ? 1 : 0
        ];

        if ($isActive) {
            // 활성화
            $data['deleted_at'] = null;
            $data['deleted_by'] = null;
        } else {
            // 비활성화
            $data['deleted_at'] = date('Y-m-d H:i:s');
            $data['deleted_by'] = $actor;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $actor;

        $ok = $this->profiles->updateStatus($userId, $data);

        $this->logger->info("ProfileService::updateStatus END", [
            'userId'  => $userId,
            'success' => $ok
        ]);

        return [
            'success' => (bool)$ok,
            'message' => $isActive ? '계정이 활성화되었습니다.' : '계정이 비활성화되었습니다.'
        ];

    } catch (\Throwable $e) {
        $this->logger->error("ProfileService::updateStatus EXCEPTION", [
            'userId' => $userId,
            'error'  => $e->getMessage(),
            'trace'  => $e->getTraceAsString()
        ]);

        return [
            'success' => false,
            'message' => '상태 변경 실패',
            'error'   => $e->getMessage()
        ];
    }
}




public function reorder(array $changes): void
{
    $this->pdo->beginTransaction();

    try {

        // 1️⃣ 임시값
        foreach ($changes as $c) {
            $this->profiles->updateCode(
                $c['id'],
                $c['newCode'] + 10000
            );
        }

        // 2️⃣ 실제값
        foreach ($changes as $c) {
            $this->profiles->updateCode(
                $c['id'],
                $c['newCode']
            );
        }

        $this->pdo->commit();

    } catch (\Throwable $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}


public function findByName(string $name): ?array
{
    return $this->profiles->findByName($name);
}


























    /* ============================================================
     * 6) 프로필 이미지 업데이트 (기존 파일 삭제 포함)
     * ============================================================ */
    public function updateProfileImage(string $userId, array $file): array
    {
        $this->logger->info("ProfileService::updateProfileImage START", [
            'actor' => ActorHelper::resolve('USER'),
            'userId' => $userId,
            'file_keys' => array_keys($file)
        ]);

        try {
            $this->logger->info("ProfileService::updateProfileImage LOAD CURRENT", [
                'fn' => 'UserProfileModel::getProfileByUserId',
                'userId' => $userId
            ]);

            $current  = $this->profiles->getByUserId($userId);
            $oldImage = $current['profile_image'] ?? null;

            $this->logger->info("ProfileService::updateProfileImage CURRENT", [
                'userId' => $userId,
                'oldImage' => $oldImage
            ]);

            $this->logger->info("ProfileService::updateProfileImage UPLOAD START", [
                'fn' => 'FileService::uploadProfile'
            ]);

            $upload = $this->fileService->uploadProfile($file);

            $this->logger->info("ProfileService::updateProfileImage UPLOAD RESULT", [
                'success' => $upload['success'] ?? null,
                'db_path' => $upload['db_path'] ?? null,
                'message' => $upload['message'] ?? null
            ]);

            if (empty($upload['success']) || empty($upload['db_path'])) {
                return ['success' => false, 'message' => $upload['message'] ?? '업로드 실패'];
            }

            $newPath   = $upload['db_path'];
            $updatedBy = ActorHelper::resolve('USER');

            $this->logger->info("ProfileService::updateProfileImage DB UPDATE", [
                'fn' => 'UserProfileModel::updateProfileImage',
                'userId' => $userId,
                'newPath' => $newPath,
                'updatedBy' => $updatedBy
            ]);

            $ok = $this->profiles->updateProfileImage($userId, $newPath, $updatedBy);

            $this->logger->info("ProfileService::updateProfileImage DB UPDATE RESULT", [
                'userId' => $userId,
                'success' => $ok
            ]);

            if ($ok && $oldImage) {
                $this->logger->info("ProfileService::updateProfileImage DELETE OLD FILE", [
                    'fn' => 'FileService::delete',
                    'oldImage' => $oldImage
                ]);
                $this->fileService->delete($oldImage);
            }

            $this->logger->info("ProfileService::updateProfileImage END", [
                'userId' => $userId,
                'success' => $ok,
                'profile_image' => $newPath
            ]);

            return [
                'success'       => $ok,
                'profile_image' => $newPath
            ];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::updateProfileImage EXCEPTION", [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => '예외 발생'];
        }
    }

    /* ============================================================
     * 7) 자격증 파일 업로드 + 기존 삭제
     * ============================================================ */
    public function updateCertificateFile(string $userId, array $file): array
    {
        $this->logger->info("ProfileService::updateCertificateFile START", [
            'actor' => ActorHelper::resolve('USER'),
            'userId' => $userId,
            'file_keys' => array_keys($file)
        ]);

        try {
            $this->logger->info("ProfileService::updateCertificateFile LOAD CURRENT", [
                'fn' => 'UserProfileModel::getProfileByUserId',
                'userId' => $userId
            ]);

            $current = $this->profiles->getByUserId($userId);
            $old     = $current['certificate_file'] ?? null;

            $this->logger->info("ProfileService::updateCertificateFile CURRENT", [
                'userId' => $userId,
                'old' => $old
            ]);

            $this->logger->info("ProfileService::updateCertificateFile UPLOAD START", [
                'fn' => 'FileService::uploadCertificate'
            ]);

            $upload = $this->fileService->uploadCertificate($file);

            $this->logger->info("ProfileService::updateCertificateFile UPLOAD RESULT", [
                'success' => $upload['success'] ?? null,
                'db_path' => $upload['db_path'] ?? null,
                'message' => $upload['message'] ?? null
            ]);

            if (empty($upload['success'])) {
                $msg = '자격증 파일 업로드 실패';
                $this->logger->warning("ProfileService::updateCertificateFile upload fail", [
                    'userId'  => $userId,
                    'message' => $msg
                ]);
                return ['success' => false, 'message' => $msg];
            }

            $new      = $upload['db_path'];
            $payload  = [
                'certificate_file' => $new,
                'updated_by'       => ActorHelper::resolve('USER'),
            ];

            $this->logger->info("ProfileService::updateCertificateFile DB UPDATE", [
                'fn' => 'UserProfileModel::updateProfile',
                'userId' => $userId,
                'payload' => $payload
            ]);

            $ok = $this->profiles->updateProfile($userId, $payload);

            $this->logger->info("ProfileService::updateCertificateFile DB UPDATE RESULT", [
                'userId' => $userId,
                'success' => $ok
            ]);

            if ($ok && $old) {
                $this->logger->info("ProfileService::updateCertificateFile DELETE OLD FILE", [
                    'fn' => 'FileService::delete',
                    'old' => $old
                ]);
                $this->fileService->delete($old);
            }

            $this->logger->info("ProfileService::updateCertificateFile END", [
                'userId' => $userId,
                'success' => $ok,
                'certificate_file' => $new
            ]);

            return ['success' => $ok, 'certificate_file' => $new];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::updateCertificateFile EXCEPTION", [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => '예외 발생'];
        }
    }

    /* ============================================================
     * 8) 신분증 이미지 업로드
     * ============================================================ */
    public function updateIdDocument(string $userId, array $file): array
    {
        $this->logger->info("ProfileService::updateIdDocument START", [
            'actor' => ActorHelper::resolve('USER'),
            'userId' => $userId,
            'file_keys' => array_keys($file)
        ]);

        try {
            $this->logger->info("ProfileService::updateIdDocument LOAD CURRENT", [
                'fn' => 'UserProfileModel::getProfileByUserId',
                'userId' => $userId
            ]);

            $current = $this->profiles->getByUserId($userId);
            $old     = $current['rrn_image'] ?? null;

            $this->logger->info("ProfileService::updateIdDocument CURRENT", [
                'userId' => $userId,
                'old' => $old
            ]);

            $this->logger->info("ProfileService::updateIdDocument UPLOAD START", [
                'fn' => 'FileService::uploadPrivateIdDoc'
            ]);

            $upload = $this->fileService->uploadPrivateIdDoc($file);

            $this->logger->info("ProfileService::updateIdDocument UPLOAD RESULT", [
                'success' => $upload['success'] ?? null,
                'db_path' => $upload['db_path'] ?? null,
                'message' => $upload['message'] ?? null
            ]);

            if (empty($upload['success'])) {
                $msg = '신분증 업로드 실패';
                $this->logger->warning("ProfileService::updateIdDocument upload fail", [
                    'userId'  => $userId,
                    'message' => $msg
                ]);
                return ['success' => false, 'message' => $msg];
            }

            $new     = $upload['db_path'];
            $payload = [
                'rrn_image'  => $new,
                'updated_by' => ActorHelper::resolve('USER'),
            ];

            $this->logger->info("ProfileService::updateIdDocument DB UPDATE", [
                'fn' => 'UserProfileModel::updateProfile',
                'userId' => $userId,
                'payload' => $payload
            ]);

            $ok = $this->profiles->updateProfile($userId, $payload);

            $this->logger->info("ProfileService::updateIdDocument DB UPDATE RESULT", [
                'userId' => $userId,
                'success' => $ok
            ]);

            if ($ok && $old) {
                $this->logger->info("ProfileService::updateIdDocument DELETE OLD FILE", [
                    'fn' => 'FileService::delete',
                    'old' => $old
                ]);
                $this->fileService->delete($old);
            }

            $this->logger->info("ProfileService::updateIdDocument END", [
                'userId' => $userId,
                'success' => $ok,
                'rrn_image' => $new
            ]);

            return ['success' => $ok, 'rrn_image' => $new];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::updateIdDocument EXCEPTION", [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => '예외 발생'];
        }
    }

    /* ============================================================
     * 10) 2단계 인증 활성/비활성 업데이트
     * ============================================================ */
    public function updateTwoFactorEnabled(string $userId, int $enabled): array
    {
        $this->logger->info("ProfileService::updateTwoFactorEnabled START", [
            'actor' => ActorHelper::resolve('USER'),
            'userId' => $userId,
            'enabled' => $enabled
        ]);

        try {
            $enabled = ($enabled === 1) ? 1 : 0;

            $this->logger->info("ProfileService::updateTwoFactorEnabled DB UPDATE", [
                'fn' => 'AuthUserModel::update2FA',
                'userId' => $userId,
                'enabled' => $enabled
            ]);

            $ok = $this->users->update2FA($userId, $enabled);

            $this->logger->info("ProfileService::updateTwoFactorEnabled END", [
                'userId' => $userId,
                'success' => $ok
            ]);

            return [
                'success' => $ok,
                'message' => $ok ? '2단계 인증 설정이 변경되었습니다.' : '변경 실패'
            ];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::updateTwoFactorEnabled EXCEPTION", [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => '예외 발생'];
        }
    }

    /* ============================================================
     * 11) 파일 삭제 Wrapper
     * ============================================================ */
    public function deleteFile(?string $dbPath): bool
    {
        $this->logger->info("ProfileService::deleteFile START", [
            'actor' => ActorHelper::resolve('USER'),
            'dbPath' => $dbPath
        ]);

        try {
            $ok = $this->fileService->delete($dbPath);

            $this->logger->info("ProfileService::deleteFile END", [
                'dbPath' => $dbPath,
                'success' => (bool)$ok
            ]);

            return (bool)$ok;

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::deleteFile EXCEPTION", [
                'dbPath' => $dbPath,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return false;
        }
    }








    

}
