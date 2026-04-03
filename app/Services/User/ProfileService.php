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
     * 1) 유저 + 프로필 조회
     * ============================================================ */
    public function getProfile(string $userId): ?array
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

    /* ============================================================
     * 2) 직원 전체 조회
     * ============================================================ */
    public function getAllProfiles(array $filters = []): array
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
    
            $list = $this->profiles->getAllProfiles($filters);
    
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
     * 3) 프로필 생성 (user_id 반드시 존재해야 함)
     *  - UUID는 여기서 생성됨
     *  - 직원코드(code) 자동 생성
     * ============================================================ */
    public function createProfile(array $data): array
    {
        $this->logger->info("ProfileService::createProfile START", [
            'actor'         => ActorHelper::resolve('USER'),
            'user_id'       => $data['user_id'] ?? null,
            'employee_name' => $data['employee_name'] ?? null,
            'keys'          => array_keys($data)
        ]);

        try {
            if (empty($data['user_id'])) {
                $this->logger->warning("ProfileService::createProfile VALIDATION FAIL", [
                    'reason' => 'user_id 누락',
                    'data'   => $data,
                    'debug' => $data   // 🔥 추가
                ]);
                return ['success' => false, 'message' => 'user_id 누락'];
            }
            if (empty($data['employee_name'])) {
                $this->logger->warning("ProfileService::createProfile VALIDATION FAIL", [
                    'reason' => '직원 이름 누락',
                    'data'   => $data
                ]);
                return ['success' => false, 'message' => '직원 이름 누락'];
            }

            // ⭐ UUID 생성 (PK)
            $data['id'] = UuidHelper::generate();
            $this->logger->info("ProfileService::createProfile UUID GENERATED", [
                'id' => $data['id']
            ]);

            // ⭐ 직원 코드 자동 생성
            $data['code'] = CodeHelper::generateEmployeeCode($this->pdo);
            $this->logger->info("ProfileService::createProfile CODE GENERATED", [
                'code' => $data['code']
            ]);

            // 생성자 기록
            $data['created_by'] = $data['created_by'] ?? ActorHelper::resolve('USER');
            $this->logger->info("ProfileService::createProfile CREATED_BY", [
                'created_by' => $data['created_by']
            ]);

            // DB 저장
            $this->logger->info("ProfileService::createProfile DB INSERT", [
                'fn' => 'UserProfileModel::createProfile',
                'user_id' => $data['user_id'],
                'id'      => $data['id'],
                'code'    => $data['code']
            ]);

            $ok = $this->profiles->createProfile($data);

            $this->logger->info("ProfileService::createProfile END", [
                'success' => $ok,
                'user_id' => $data['user_id'],
                'id'      => $data['id'],
                'code'    => $data['code']
            ]);

            return [
                'success' => $ok,
                'id'      => $data['id'],
                'code'    => $data['code']
            ];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::createProfile EXCEPTION", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data'  => $data
            ]);
            return ['success' => false, 'message' => '예외 발생'];
        }
    }

    /* ============================================================
     * 4) 프로필 수정 (화이트리스트 + updated_by)
     * ============================================================ */
    public function updateProfile(string $userId, array $data): array
    {
        $this->logger->info("ProfileService::updateProfile START", [
            'actor' => ActorHelper::resolve('USER'),
            'userId' => $userId,
            'input_keys' => array_keys($data)
        ]);

        try {
            $allowed = [
                'employee_name','phone','address','address_detail',
                'department_id','position_id',
                'certificate_name','certificate_file',
                'profile_image','rrn_image',
                'note','memo',
                'doc_hire_date','real_hire_date','doc_retire_date','real_retire_date',
                'emergency_phone','rrn'
            ];

            $payload = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $payload[$f] = $data[$f];
                }
            }

            $this->logger->info("ProfileService::updateProfile FILTERED", [
                'userId' => $userId,
                'payload_keys' => array_keys($payload)
            ]);

            // 실제 변경할 필드가 없으면 "성공"으로 처리 (auth_users만 변경되는 경우 대비)
            if (empty($payload)) {
                $this->logger->info("ProfileService::updateProfile EMPTY PAYLOAD (SKIP)", [
                    'userId' => $userId
                ]);
                return ['success' => true];
            }

            // 변경자 기록
            $payload['updated_by'] = ActorHelper::resolve('USER');
            $this->logger->info("ProfileService::updateProfile UPDATED_BY", [
                'userId' => $userId,
                'updated_by' => $payload['updated_by']
            ]);

            $this->logger->info("ProfileService::updateProfile DB UPDATE", [
                'fn' => 'UserProfileModel::updateProfile',
                'userId' => $userId,
                'payload_keys' => array_keys($payload)
            ]);

            $ok = $this->profiles->updateProfile($userId, $payload);

            $this->logger->info("ProfileService::updateProfile END", [
                'userId' => $userId,
                'success' => $ok
            ]);

            return ['success' => $ok];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::updateProfile EXCEPTION", [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => '예외 발생'];
        }
    }

    /* ============================================================
     * 5) 기본 정보 수정 (Basic Tab 전용)
     * ============================================================ */
    public function updateBasicInfo(string $userId, array $data): array
    {
        $this->logger->info("ProfileService::updateBasicInfo START", [
            'actor' => ActorHelper::resolve('USER'),
            'userId' => $userId,
            'input_keys' => array_keys($data)
        ]);

        try {
            $allowed = [
                'employee_name','phone','address','address_detail',
                'department_id','position_id',
                'certificate_name','certificate_file',
                'profile_image','rrn_image',
                'note','memo',
                'doc_hire_date','real_hire_date','doc_retire_date','real_retire_date',
                'emergency_phone','rrn'
            ];

            $payload = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $payload[$f] = $data[$f];
                }
            }

            $this->logger->info("ProfileService::updateBasicInfo FILTERED", [
                'userId' => $userId,
                'payload_keys' => array_keys($payload)
            ]);

            if (empty($payload)) {
                $msg = '수정할 데이터가 없습니다.';
                $this->logger->warning("ProfileService::updateBasicInfo EMPTY PAYLOAD", [
                    'userId' => $userId,
                    'message' => $msg
                ]);
                return ['success' => false, 'message' => $msg];
            }

            // 변경자 기록
            $payload['updated_by'] = ActorHelper::resolve('USER');

            $this->logger->info("ProfileService::updateBasicInfo DB UPDATE", [
                'fn' => 'UserProfileModel::updateProfile',
                'userId' => $userId,
                'payload_keys' => array_keys($payload),
                'updated_by' => $payload['updated_by']
            ]);

            $ok = $this->profiles->updateProfile($userId, $payload);

            $this->logger->info("ProfileService::updateBasicInfo END", [
                'userId' => $userId,
                'success' => $ok
            ]);

            return [
                'success' => $ok,
                'message' => $ok ? '프로필이 수정되었습니다.' : '프로필 수정 실패'
            ];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::updateBasicInfo EXCEPTION", [
                'userId' => $userId,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString()
            ]);
            return ['success' => false, 'message' => '예외 발생'];
        }
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

            $current  = $this->profiles->getProfileByUserId($userId);
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

            $current = $this->profiles->getProfileByUserId($userId);
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

            $current = $this->profiles->getProfileByUserId($userId);
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
     * 9) auth_users + user_profiles 동시 수정
     * ============================================================ */
    public function updateFullProfile(string $userId, array $authData, array $profileData): array
    {
        $this->logger->info("ProfileService::updateFullProfile START", [
            'actor' => ActorHelper::resolve('USER'),
            'userId' => $userId,
            'auth_keys' => array_keys($authData),
            'profile_keys' => array_keys($profileData)
        ]);

        try {
            $currentUserId = ActorHelper::resolve('USER');

            // auth_users 쪽 updated_by 세팅
            if (!empty($authData)) {
                $authData['updated_by'] = $authData['updated_by'] ?? $currentUserId;
            }

            // profile 쪽 updated_by 세팅
            if (!empty($profileData)) {
                $profileData['updated_by'] = $profileData['updated_by'] ?? $currentUserId;
            }

            $this->logger->info("ProfileService::updateFullProfile PREPARED", [
                'userId' => $userId,
                'authData_updated_by' => $authData['updated_by'] ?? null,
                'profileData_updated_by' => $profileData['updated_by'] ?? null
            ]);

            $authOK = true;
            if (!empty($authData)) {
                $this->logger->info("ProfileService::updateFullProfile AUTH UPDATE", [
                    'fn' => 'AuthUserModel::updateUserDirect',
                    'userId' => $userId,
                    'auth_keys' => array_keys($authData)
                ]);
                $authOK = $this->users->updateUserDirect($userId, $authData);
            }

            $profileOK = true;
            if (!empty($profileData)) {
                $this->logger->info("ProfileService::updateFullProfile PROFILE UPDATE", [
                    'fn' => 'ProfileService::updateProfile',
                    'userId' => $userId,
                    'profile_keys' => array_keys($profileData)
                ]);
                $profileOK = $this->updateProfile($userId, $profileData)['success'];
            }

            $success = ($authOK && $profileOK);

            $this->logger->info("ProfileService::updateFullProfile END", [
                'userId' => $userId,
                'authOK' => $authOK,
                'profileOK' => $profileOK,
                'success' => $success
            ]);

            return ['success' => $success];

        } catch (\Throwable $e) {
            $this->logger->error("ProfileService::updateFullProfile EXCEPTION", [
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


    /* ============================================================
     * 7) 사용자 기본 정보 가져오기
     * ============================================================ */
    public function getUserById(string $id): ?array
    {
        $this->logger->info('getUserById 호출', [
            'user_id' => $id
        ]);

        $user = $this->users->getById($id);

        $this->logger->info('getUserById 호출', [
            'user_id' => $id,
            'found'   => $user !== null
        ]);

        return $user;
    }

    // ============================================================
    // 직원 검색 (Select2용)
    // ============================================================
    public function searchEmployees(string $q = '', int $limit = 20): array
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

            $rows = $this->profiles->searchEmployees($q, $limit);

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

    public function reorderProfiles(array $changes): void
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


    public function updateStatus(string $userId, string $action): array
    {
        $actor = ActorHelper::resolve('USER');

        try {

            $isActive = ($action === 'activate') ? 1 : 0;

            $ok = $this->users->updateUserDirect($userId, [
                'is_active' => $isActive,
                'updated_by' => $actor
            ]);

            return [
                'success' => $ok,
                'message' => $ok
                    ? ($isActive ? '계정이 활성화되었습니다.' : '계정이 비활성화되었습니다.')
                    : '상태 변경 실패'
            ];

        } catch (\Throwable $e) {

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function findByName(string $name): ?array
    {
        return $this->profiles->findByName($name);
    }
    

}
