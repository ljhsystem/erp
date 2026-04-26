<?php
// 경로: PROJECT_ROOT . '/app/Services/Auth/RolePermissionService.php'
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\RolePermissionModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class RolePermissionService
{
    private readonly PDO $pdo;
    private RolePermissionModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new RolePermissionModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.RolePermissionService');
    }

    /* ---------------------------------------------------------------
     * 1. 특정 역할의 모든 권한 조회
     * --------------------------------------------------------------- */
    public function getPermissionsForRole(string $roleId): array
    {
        return $this->model->getPermissionsForRole($roleId);
    }

    /* ---------------------------------------------------------------
     * 2. 특정 권한을 가진 모든 역할 조회
     * --------------------------------------------------------------- */
    public function getRolesForPermission(string $permissionId): array
    {
        return $this->model->getRolesForPermission($permissionId);
    }

    /* ---------------------------------------------------------------
     * 3. 역할에 권한 부여
     * --------------------------------------------------------------- */
    public function assign(string $roleId, string $permissionId): bool
    {
        // 중복 체크
        if ($this->model->exists($roleId, $permissionId)) {
            return true; // 이미 있으므로 성공 처리
        }

        $data = [
            'id'            => UuidHelper::generate(),
            'sort_no'       => SequenceHelper::next('auth_role_permissions', 'sort_no'),
            'role_id'       => $roleId,
            'permission_id' => $permissionId,
            'created_by'    => ActorHelper::user()
        ];

        return $this->model->insertMapping($data);
    }

    /* ---------------------------------------------------------------
     * 4. 역할에서 특정 권한 제거
     * --------------------------------------------------------------- */
    public function remove(string $roleId, string $permissionId): bool
    {
        return $this->model->remove($roleId, $permissionId);
    }

    /* ---------------------------------------------------------------
     * 5. 특정 역할의 모든 권한 제거
     * --------------------------------------------------------------- */
    public function clearRole(string $roleId): bool
    {
        return $this->model->clearRole($roleId);
    }

    /* ---------------------------------------------------------------
     * 6. 특정 권한을 가진 모든 역할 제거
     * --------------------------------------------------------------- */
    public function clearPermission(string $permissionId): bool
    {
        return $this->model->clearPermission($permissionId);
    }

    /* ---------------------------------------------------------------
     * 7. 역할이 특정 permission_key를 가지고 있는지 여부
     * --------------------------------------------------------------- */
    public function roleHasPermission(string $roleId, string $permissionKey): bool
    {
        return $this->model->roleHasPermission($roleId, $permissionKey);
    }
}
