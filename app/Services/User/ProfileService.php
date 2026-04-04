<?php
// 경로: PROJECT_ROOT . '/app/Services/User/ProfileService.php';
namespace App\Services\User;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\User\EmployeeModel; // 🔥 변경
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
     * 내 프로필 조회
     * ============================================================ */
    public function getDetail(string $userId): ?array
    {
        $user = $this->users->getById($userId);
        if (!$user) return null;

        $profile = $this->employees->getByUserId($userId);

        return array_merge($user, $profile ?? []);
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

        try {

            /* =========================
             * AUTH
             * ========================= */
            $authData = [];

            if (!empty($data['email'])) {
                $authData['email'] = trim($data['email']);
            }

            if (!empty($data['password'])) {
                $authData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $authData['password_updated_at'] = date('Y-m-d H:i:s');
                $authData['password_updated_by'] = $actor;
            }

            /* =========================
             * PROFILE
             * ========================= */
            $profileData = [];

            $fields = [
                'employee_name','phone','address','address_detail',
                'department_id','position_id',
                'note','memo','emergency_phone'
            ];

            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $profileData[$f] = $data[$f] === '' ? null : $data[$f];
                }
            }

            /* =========================
             * 주민번호
             * ========================= */
            if (!empty($data['rrn']) && strpos($data['rrn'], '*') === false) {
                $crypto = new Crypto();
                $profileData['rrn'] = $crypto->encryptResidentNumber(
                    preg_replace('/\D+/', '', $data['rrn'])
                );
            }

            /* =========================
             * 파일
             * ========================= */
            if (!empty($files['profile_image']['name'])) {
                $upload = $this->fileService->uploadProfile($files['profile_image']);
                if (empty($upload['success'])) {
                    return ['success' => false, 'message' => '이미지 업로드 실패'];
                }
                $profileData['profile_image'] = $upload['db_path'];
            }

            /* =========================
             * DB 저장
             * ========================= */
            $this->pdo->beginTransaction();

            if (!empty($authData)) {
                $this->users->updateUserDirect($userId, $authData);
            }

            $this->employees->updateByUserId($userId, $profileData);

            $this->pdo->commit();

            return ['success' => true, 'message' => '저장 완료'];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return [
                'success' => false,
                'message' => '저장 실패',
                'error'   => $e->getMessage()
            ];
        }
    }

    /* ============================================================
     * 2FA
     * ============================================================ */
    public function updateTwoFactorEnabled(string $userId, int $enabled): array
    {
        $ok = $this->users->update2FA($userId, $enabled ? 1 : 0);

        return [
            'success' => $ok,
            'message' => $ok ? '변경 완료' : '실패'
        ];
    }

    /* ============================================================
     * 파일 삭제
     * ============================================================ */
    public function deleteFile(?string $path): bool
    {
        return $this->fileService->delete($path);
    }
}