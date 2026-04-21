<?php
// 경로: PROJECT_ROOT . '/app/Services/User/ProfileService.php';
namespace App\Services\User;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel;
use App\Services\File\FileService;
use Core\Helpers\ActorHelper;
use Core\Security\Crypto;
use Core\LoggerFactory;

class ProfileService
{
    private readonly PDO $pdo;
    private $users;
    private $employees;
    private $fileService;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->users       = new UserModel($pdo);
        $this->employees   = new EmployeeModel($pdo); // 🔥 변경
        $this->fileService = new FileService($pdo);
        $this->logger      = LoggerFactory::getLogger("service-user.ProfileService");
    }


    /* ============================================================
    * 내 프로필 조회 (getById 기반 수정)
    * ============================================================ */
    public function getById(string $userId): ?array
    {
        // 1️⃣ 사용자
        $user = $this->users->getById($userId);
        if (!$user) return null;

        // 2️⃣ employee_id 찾기 (최소 루프)
        $rows = $this->employees->getList();

        $employeeId = null;

        foreach ($rows as $row) {
            if (($row['user_id'] ?? null) === $userId) {
                $employeeId = $row['id'];
                break;
            }
        }
        
        if (!$employeeId) {
            throw new \Exception('employee_id 조회 실패');
        }

        // 3️⃣ 단건 조회 (🔥 핵심 변경)
        $employee = null;
        if ($employeeId) {
            $employee = $this->employees->getById($employeeId);
        }

        return array_merge($user, $employee ?? []);
    }

    public function getCurrentProfile(): ?array
    {
        $actor = ActorHelper::parse(ActorHelper::user());
        $userId = $actor['id'] ?? null;

        if (!$userId) {
            throw new \Exception('로그인 정보가 필요합니다.');
        }

        return $this->getById($userId);
    }

   /* ============================================================
    * 내 프로필 저장 (수정 ONLY)
    * ============================================================ */
    public function save(array $data, array $files = []): array
    {
        $actor  = ActorHelper::resolve('USER');
        $userId = $data['id'] ?? null;

        if (!$userId) {
            return ['success' => false, 'message' => 'user_id 없음'];
        }

        $deleteAfterCommit = [];

        try {
            /* ============================================================
            * 1) 직원 찾기
            * ============================================================ */
            $rows = $this->employees->getList();

            $employee = null;
            foreach ($rows as $row) {
                if (($row['user_id'] ?? null) === $userId) {
                    $employee = $row;
                    break;
                }
            }

            if (!$employee) {
                return ['success' => false, 'message' => '프로필 없음'];
            }

            $employeeId = $employee['id'];

            /* ============================================================
            * 2) auth_users 업데이트 데이터
            * ============================================================ */
            $authData = [];

            if (array_key_exists('email', $data)) {
                $authData['email'] = trim((string)$data['email']);
            }

            if (!empty($data['new_password'])) {
                if (empty($data['current_password'])) {
                    throw new \Exception('현재 비밀번호 필요');
                }

                if ($data['new_password'] !== ($data['confirm_password'] ?? '')) {
                    throw new \Exception('비밀번호 확인 불일치');
                }

                $authData['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
                $authData['password_updated_at'] = date('Y-m-d H:i:s');
                $authData['password_updated_by'] = $actor;
            }

            if (isset($data['two_factor_enabled'])) {
                $authData['two_factor_enabled'] = (int)$data['two_factor_enabled'];
            }

            if (isset($data['email_notify'])) {
                $authData['email_notify'] = (int)$data['email_notify'];
            }

            if (isset($data['sms_notify'])) {
                $authData['sms_notify'] = (int)$data['sms_notify'];
            }

            if (!empty($authData)) {
                $authData['updated_by'] = $actor;
            }

            /* ============================================================
            * 3) user_employees 업데이트 데이터
            * ============================================================ */
            $profileData = [];

            $fields = [
                'employee_name',
                'phone',
                'address',
                'address_detail',      
                'note',
                'memo',
                'emergency_phone',
                'certificate_name'
            ];

            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $profileData[$f] = $data[$f] === '' ? null : $data[$f];
                }
            }

            if (!empty($data['rrn']) && strpos((string)$data['rrn'], '*') === false) {
                $crypto = new Crypto();
                $profileData['rrn'] = $crypto->encryptResidentNumber(
                    preg_replace('/\D+/', '', (string)$data['rrn'])
                );
            }

            /* ============================================================
            * 4) 파일 처리
            *    - 프로필에서는 파일 삭제 금지
            *    - 새 파일 업로드 시에만 교체
            *    - 기존 파일 삭제는 commit 이후
            * ============================================================ */
            $currentProfile = $employee['profile_image'] ?? null;
            $currentCert    = $employee['certificate_file'] ?? null;

            // 프로필 이미지
            if (!empty($files['profile_image']['name'])) {
                $upload = $this->fileService->uploadProfile($files['profile_image']);

                if (empty($upload['success'])) {
                    return [
                        'success' => false,
                        'message' => $upload['message'] ?? '프로필 이미지 업로드 실패'
                    ];
                }

                $profileData['profile_image'] = $upload['db_path'];

                if (!empty($currentProfile) && $currentProfile !== $profileData['profile_image']) {
                    $deleteAfterCommit[] = $currentProfile;
                }
            } else {
                $profileData['profile_image'] = $currentProfile;
            }

            // 자격증 파일
            if (!empty($files['certificate_file']['name'])) {
                $upload = $this->fileService->uploadCertificate($files['certificate_file']);

                if (empty($upload['success'])) {
                    return [
                        'success' => false,
                        'message' => $upload['message'] ?? '자격증 파일 업로드 실패'
                    ];
                }

                $profileData['certificate_file'] = $upload['db_path'];

                if (!empty($currentCert) && $currentCert !== $profileData['certificate_file']) {
                    $deleteAfterCommit[] = $currentCert;
                }
            } else {
                $profileData['certificate_file'] = $currentCert;
            }

            // 안전장치
            $profileData['profile_image']    = $profileData['profile_image']    ?? $currentProfile;
            $profileData['certificate_file'] = $profileData['certificate_file'] ?? $currentCert;

            if (!empty($profileData)) {
                $profileData['updated_by'] = $actor;
            }

            /* ============================================================
            * 5) DB 저장
            * ============================================================ */
            $this->pdo->beginTransaction();

            if (!empty($authData)) {
                $this->users->updateUserDirect($userId, $authData);
            }

            if (!empty($profileData)) {

                // 🔥 1. 기존 데이터 조회
                $current = $this->employees->getById($employeeId);
            
                if (!$current) {
                    throw new \Exception('직원 데이터 없음');
                }
            
                // 🔥 2. 기존 데이터 + 변경 데이터 merge
                $updateData = array_merge($current, $profileData);
            
                // 🔥 3. 안전 필드 제거 (절대 덮으면 안되는 것)
                unset(
                    $updateData['id'],
                    $updateData['created_at'],
                    $updateData['created_by'],
                    $updateData['updated_at']
                );
            
                // 🔥 4. updated_by 강제
                $updateData['updated_by'] = $actor;
            
                // 🔥 5. FULL UPDATE 호출 (안전)
                $this->employees->updateById($employeeId, $updateData);
            }

            $this->pdo->commit();

            /* ============================================================
            * 6) commit 이후 기존 파일 삭제
            * ============================================================ */
            foreach ($deleteAfterCommit as $oldFile) {
                try {
                    $this->fileService->delete($oldFile);
                } catch (\Throwable $deleteError) {
                    $this->logger->warning('기존 파일 삭제 실패', [
                        'user_id' => $userId,
                        'file'    => $oldFile,
                        'error'   => $deleteError->getMessage(),
                    ]);
                }
            }

            return [
                'success'          => true,
                'message'          => '저장 완료',
                'profile_image'    => $profileData['profile_image'] ?? $currentProfile,
                'certificate_file' => $profileData['certificate_file'] ?? $currentCert,
            ];

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => '저장 실패',
                'error'   => $e->getMessage()
            ];
        }
    }

    public function saveCurrent(array $data, array $files = []): array
    {
        $actor = ActorHelper::parse(ActorHelper::user());
        $userId = $actor['id'] ?? null;

        if (!$userId) {
            throw new \Exception('로그인이 필요합니다.');
        }

        $data['id'] = $userId;

        return $this->save($data, $files);
    }
 

}
