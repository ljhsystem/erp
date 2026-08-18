<?php
namespace App\Services\Auth;

use App\Models\Auth\PermissionModel;
use App\Models\Auth\RolePermissionModel;
use App\Models\Auth\UserModel;
use App\Repositories\Auth\UserPermissionRepository;
use Core\Helpers\ActorHelper;
use Core\Helpers\ConfigHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use Core\Database;
use PDO;

class PermissionService
{
    private readonly PDO $pdo;
    private PermissionModel $permModel;
    private RolePermissionModel $rolePermModel;
    private UserModel $userModel;
    private UserPermissionRepository $userPermissionRepository;
    private $logger;
    private array $cache = [];
    private array $userCache = [];
    private array $effectivePermissionKeyCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $pdo = $pdo ?? Database::getInstance()->getConnection();
        $this->pdo = $pdo;
        $this->permModel = new PermissionModel($pdo);
        $this->rolePermModel = new RolePermissionModel($pdo);
        $this->userModel = new UserModel($pdo);
        $this->userPermissionRepository = new UserPermissionRepository($pdo);
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

    public function supportsPageKey(): bool { return $this->permModel->supportsPageKey(); }
    public function getRegistrySyncRows(bool $withPageKey): array { return $this->permModel->getRegistrySyncRows($withPageKey); }
    public function insertRegistryPermission(array $data, bool $withPageKey): bool { return $this->permModel->insertRegistryPermission($data, $withPageKey); }
    public function updateRegistryPermission(string $id, array $changes): bool { return $this->permModel->updateRegistryPermission($id, $changes); }
    public function getRegistrySortRows(): array { return $this->permModel->getRegistrySortRows(); }
    public function offsetSortNumbers(array $ids, int $offset, string $actor): void { $this->permModel->offsetSortNumbers($ids, $offset, $actor); }
    public function applySortNumbers(array $changes, string $actor): void { $this->permModel->applySortNumbers($changes, $actor); }
    public function grantPermissionToRoleKey(string $permissionId, string $roleKey, string $actor): void
    {
        $this->rolePermModel->grantPermissionToRoleKey($permissionId, $roleKey, $actor);
    }

    public function create(array $data): array
    {
        if (empty($data['permission_key']) || empty($data['permission_name'])) {
            return ['success' => false, 'message' => '권한 키와 권한명은 필수입니다.'];
        }

        if ($this->permModel->existsKey($data['permission_key'])) {
            return ['success' => false, 'message' => '이미 사용 중인 권한 키입니다.'];
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
            return ['success' => false, 'message' => '권한 ID가 필요합니다.'];
        }

        if (!empty($data['permission_key']) && $this->permModel->existsKey($data['permission_key'], $id)) {
            return ['success' => false, 'message' => '이미 사용 중인 권한 키입니다.'];
        }

        $data['updated_by'] = ActorHelper::user();

        return ['success' => $this->permModel->update($id, $data)];
    }

    public function delete(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => '권한 ID가 필요합니다.'];
        }

        try {
            $this->pdo->beginTransaction();

            $deletedRolePermissionCount = $this->rolePermModel->countByPermission($id);

            if (!$this->rolePermModel->clearPermission($id)) {
                throw new \RuntimeException('삭제 중 오류가 발생했습니다.');
            }

            if (!$this->permModel->delete($id)) {
                throw new \RuntimeException('삭제 중 오류가 발생했습니다.');
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => '삭제되었습니다.',
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
                'message' => $e->getMessage() ?: '삭제 중 오류가 발생했습니다.',
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

        try {
            $user = $this->getUser($userId);

            if (!$user || (int) ($user['approved'] ?? 0) !== 1 || (int) ($user['is_active'] ?? 0) !== 1
                || empty($user['role_id'])) {
                return $this->cache[$cacheKey] = false;
            }

            if (!$this->rolePermModel->isRoleActive((string) $user['role_id'])) {
                return $this->cache[$cacheKey] = false;
            }

            if ($this->isDevelopmentBypassAllowed()) {
                return $this->cache[$cacheKey] = true;
            }

            $result = isset($this->getEffectivePermissionKeySet($userId)[$permissionKey]);

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

    public function resolvePermission(string $userId, string $permissionKey): array
    {
        $permissionKey = strtolower(trim($permissionKey));
        $user = $this->getUser($userId);
        $base = ['role_allowed' => false, 'user_allowed' => false, 'permission_mode' => null, 'effective_allowed' => false];
        if (!$user || (int) ($user['approved'] ?? 0) !== 1 || (int) ($user['is_active'] ?? 0) !== 1
            || empty($user['role_id']) || !$this->rolePermModel->isRoleActive((string) $user['role_id'])) return $base;
        $permission = $this->permModel->getByKey($permissionKey);
        if (!$permission || (int) ($permission['is_active'] ?? 0) !== 1) return $base;
        $roleAllowed = $this->rolePermModel->roleHasPermission((string) $user['role_id'], $permissionKey);
        $context = $this->userPermissionRepository->userContext($userId);
        $mode = (string) ($context['permission_mode'] ?? 'ROLE');
        $userAllowed = in_array((string) $permission['id'], $this->userPermissionRepository->userPermissionIds($userId), true);
        $effective = $mode === 'ROLE' ? $roleAllowed : ($mode === 'EXTEND' ? ($roleAllowed || $userAllowed) : ($mode === 'REPLACE' && $userAllowed));
        return [
            'role_allowed' => $roleAllowed,
            'user_allowed' => $userAllowed,
            'permission_mode' => $mode,
            'effective_allowed' => $effective,
        ];
    }

    public function getEffectivePermissionSet(string $userId): array
    {
        return $this->userPermissionRepository->effectivePermissionSet($userId);
    }

    private function getEffectivePermissionKeySet(string $userId): array
    {
        if (!isset($this->effectivePermissionKeyCache[$userId])) {
            $keys = array_values($this->getEffectivePermissionSet($userId));
            $this->effectivePermissionKeyCache[$userId] = array_fill_keys(array_map(
                static fn(string $key): string => strtolower(trim($key)),
                $keys
            ), true);
        }

        return $this->effectivePermissionKeyCache[$userId];
    }

    private function getUser(string $userId)
    {
        if (!isset($this->userCache[$userId])) {
            $this->userCache[$userId] = $this->userModel->getById($userId);
        }

        return $this->userCache[$userId];
    }

    private function isDevelopmentBypassAllowed(): bool
    {
        $environment = strtolower(trim((string) getenv('APP_ENV')));
        return ConfigHelper::get('IsDevelopment') === true && $environment === 'development';
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
                    throw new \Exception('정렬 데이터가 올바르지 않습니다.');
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
