<?php
namespace App\Services\Auth;

use App\Models\Auth\PermissionModel;
use App\Models\Auth\RolePermissionModel;
use App\Models\Auth\UserModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\ConfigHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class PermissionService
{
    private readonly PDO $pdo;
    private PermissionModel $permModel;
    private RolePermissionModel $rolePermModel;
    private UserModel $userModel;
    private $logger;
    private array $cache = [];
    private array $userCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->permModel = new PermissionModel($pdo);
        $this->rolePermModel = new RolePermissionModel($pdo);
        $this->userModel = new UserModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.PermissionService');
    }

    public function getAll(array $filters = []): array
    {
        return $this->permModel->getAll($filters);
    }

    public function getList(array $filters = []): array
    {
        return $this->getAll($filters);
    }

    public function create(array $data): array
    {
        if (empty($data['permission_key']) || empty($data['permission_name'])) {
            return ['success' => false, 'message' => 'required'];
        }

        if ($this->permModel->existsKey($data['permission_key'])) {
            return ['success' => false, 'message' => 'duplicate'];
        }

        $data['id'] = UuidHelper::generate();
        $data['sort_no'] = SequenceHelper::next('auth_permissions', 'sort_no');
        $data['created_by'] = ActorHelper::user();
        $data['updated_by'] = ActorHelper::user();

        $ok = $this->permModel->create($data);

        return ['success' => $ok, 'id' => $data['id'], 'sort_no' => $data['sort_no']];
    }

    public function update(string $id, array $data): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'id required'];
        }

        if (!empty($data['permission_key']) && $this->permModel->existsKey($data['permission_key'], $id)) {
            return ['success' => false, 'message' => 'duplicate'];
        }

        $data['updated_by'] = ActorHelper::user();

        return ['success' => $this->permModel->update($id, $data)];
    }

    public function delete(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'permission id required'];
        }

        try {
            $this->pdo->beginTransaction();

            $mappingCountStmt = $this->pdo->prepare('SELECT COUNT(*) FROM auth_role_permissions WHERE permission_id = ?');
            $mappingCountStmt->execute([$id]);
            $deletedRolePermissionCount = (int) $mappingCountStmt->fetchColumn();

            if (!$this->rolePermModel->clearPermission($id)) {
                throw new \RuntimeException('role permission mapping delete failed');
            }

            if (!$this->permModel->delete($id)) {
                throw new \RuntimeException('permission delete failed');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'permission deleted',
                'data' => [
                    'deleted_count' => 1,
                    'deleted_role_permission_count' => $deletedRolePermissionCount,
                ],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'message' => $e->getMessage() ?: 'permission delete failed',
            ];
        }
    }

    public function toggleActive(string $id, int $active): bool
    {
        return $this->permModel->toggleActive($id, $active);
    }

    public function hasPermission(string $userId, string $permissionKey): bool
    {
        $permissionKey = strtolower(trim($permissionKey));
        $cacheKey = $userId . ':' . $permissionKey;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        if (ConfigHelper::get('IsDevelopment') === true) {
            return $this->cache[$cacheKey] = true;
        }

        try {
            $user = $this->getUser($userId);

            if (!$user || empty($user['role_id'])) {
                return $this->cache[$cacheKey] = false;
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
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

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

            foreach ($changes as &$row) {
                $sortNo = $row['newSortNo'] ?? $row['sort_no'] ?? null;

                if (empty($row['id']) || $sortNo === null) {
                    throw new \Exception('reorder payload is invalid');
                }

                $row['_sort_no'] = (int) $sortNo;
            }
            unset($row);

            foreach ($changes as $row) {
                $this->permModel->updateSortNo($row['id'], $row['_sort_no'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->permModel->updateSortNo($row['id'], $row['_sort_no']);
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
