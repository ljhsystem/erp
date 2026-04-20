<?php
// 경로: PROJECT_ROOT/app/Services/Auth/PermissionService.php
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\UserModel;
use App\Models\Auth\PermissionModel;
use App\Models\Auth\RolePermissionModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\LoggerFactory;

class PermissionService
{
    private readonly PDO $pdo;
    private $permModel;
    private $rolePermModel;
    private $userModel;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo           = $pdo;
        $this->permModel     = new PermissionModel($pdo);
        $this->rolePermModel = new RolePermissionModel($pdo);
        $this->userModel     = new UserModel($pdo);
        $this->logger        = LoggerFactory::getLogger('service-auth.PermissionService');
    }


    /* ---------------------------------------------------------------
     * 권한 전체 조회
     * --------------------------------------------------------------- */
    public function getAll(array $filters = []): array
    {
        return $this->permModel->getAll($filters);
    }

    public function getList(array $filters = []): array
    {
        return $this->getAll($filters);
    }

    /* ---------------------------------------------------------------
     * 권한 생성 (UUID + CodeHelper 생성 책임)
     * --------------------------------------------------------------- */
    public function create(array $data): array
    {
        if (empty($data['permission_key']) || empty($data['permission_name'])) {
            return ['success' => false, 'message' => '필수값 누락'];
        }

        // 중복 체크
        if ($this->permModel->existsKey($data['permission_key'])) {
            return ['success' => false, 'message' => 'duplicate'];
        }

        // ⭐ UUID 생성
        $data['id'] = UuidHelper::generate();

        // ⭐ 권한 코드 생성
        $data['code'] = CodeHelper::generatePermissionCode($this->pdo);

        // 생성자 정보
        $data['created_by'] = $data['created_by'] ?? ($_SESSION['user']['id'] ?? null);

        $ok = $this->permModel->create($data);

        return ['success' => $ok, 'id' => $data['id'], 'code' => $data['code']];
    }

    /* ---------------------------------------------------------------
     * 권한 수정
     * --------------------------------------------------------------- */
    public function update(string $id, array $data): array
    {
        if (!$id) return ['success' => false, 'message' => 'ID 없음'];

        // 중복 체크
        if (!empty($data['permission_key'])) {
            if ($this->permModel->existsKey($data['permission_key'], $id)) {
                return ['success' => false, 'message' => 'duplicate'];
            }
        }

        $data['updated_by'] = $data['updated_by'] ?? ($_SESSION['user']['id'] ?? null);

        return ['success' => $this->permModel->update($id, $data)];
    }

    /* ---------------------------------------------------------------
     * 권한 삭제
     * --------------------------------------------------------------- */
    public function delete(string $id): array
    {
        if (!$id) return ['success' => false];

        return ['success' => $this->permModel->delete($id)];
    }

    /* ---------------------------------------------------------------
     * 활성/비활성 토글
     * --------------------------------------------------------------- */
    public function toggleActive(string $id, int $active): bool
    {
        return $this->permModel->toggleActive($id, $active);
    }

    /* ---------------------------------------------------------------
     * 특정 사용자 권한 보유 여부 체크
     * --------------------------------------------------------------- */
    private array $cache = [];

    public function hasPermission(string $userId, string $permissionKey): bool
    {
        $permissionKey = strtolower(trim($permissionKey)); // 🔥 필수

        $cacheKey = $userId . ':' . $permissionKey;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $user = $this->getUser($userId); // 🔥 캐싱

            if (!$user || empty($user['role_id'])) {
                return $this->cache[$cacheKey] = false;
            }

            // 🔥 super admin
            if (!empty($user['is_super_admin'])) {
                return $this->cache[$cacheKey] = true;
            }

            $result = $this->rolePermModel->roleHasPermission(
                $user['role_id'],
                $permissionKey
            );

            return $this->cache[$cacheKey] = $result;

        } catch (\Throwable $e) {
            $this->logger->error('hasPermission Error', [
                'user_id' => $userId,
                'permission_key' => $permissionKey,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    private array $userCache = [];

    private function getUser(string $userId)
    {
        if (!isset($this->userCache[$userId])) {
            $this->userCache[$userId] = $this->userModel->getById($userId);
        }
        return $this->userCache[$userId];
    }

    public function reorder(array $changes): bool
    {
        if (empty($changes)) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newCode'])) {
                    throw new \Exception('reorder 데이터 오류');
                }
            }

            foreach ($changes as $row) {
                $this->permModel->updateCode($row['id'], (int)$row['newCode'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->permModel->updateCode($row['id'], (int)$row['newCode']);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
