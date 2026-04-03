<?php
// 경로: PROJECT_ROOT/app/services/user/DepartmentService.php
namespace App\Services\User;

// require_once PROJECT_ROOT . '/app/models/user/UserDepartmentModel.php';

use PDO;
use App\Models\User\UserDepartmentModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\LoggerFactory;

class DepartmentService
{
    private readonly PDO $pdo;
    private UserDepartmentModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new UserDepartmentModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.DepartmentService');
    }

    /* ============================================================
     * 1) 전체 조회
     * ============================================================ */
    public function getAll(): array
    {
        return $this->model->getAll();
    }

    /* ============================================================
     * 2) 단일 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    /* ============================================================
     * 3) 생성 (UUID + CODE + 중복검사)
     * ============================================================ */
    public function create(array $data)
    {
        if (empty($data['dept_name'])) {
            return false;
        }

        if ($this->model->existsByName($data['dept_name'])) {
            return "duplicate";
        }

        // UUID 생성
        $data['id'] = UuidHelper::generate();

        // 코드 생성
        $data['code'] = CodeHelper::generateDepartmentCode($this->pdo);

        // created_by 설정
        $data['created_by'] = $data['created_by'] ?? ($_SESSION['user']['id'] ?? null);

        return $this->model->create($data);
    }

    /* ============================================================
     * 4) 수정
     * ============================================================ */
    public function update(string $id, array $data)
    {
        if (empty($id)) return false;

        if (isset($data['manager_id']) && $data['manager_id'] === "undefined") {
            $data['manager_id'] = null;
        }

        if (!empty($data['dept_name'])) {
            if ($this->model->existsByName($data['dept_name'], $id)) {
                return "duplicate";
            }
        }

        $data['updated_by'] = $_SESSION['user']['id'] ?? null;

        return $this->model->update($id, $data);
    }

    /* ============================================================
     * 5) 삭제
     * ============================================================ */
    public function delete(string $id): bool
    {
        if (empty($id)) return false;
        return $this->model->delete($id);
    }

    /* ============================================================
     * 6) 부서장 지정
     * ============================================================ */
    public function assignManager(string $deptId, ?string $managerUserId): bool
    {
        if (empty($deptId)) return false;
        return $this->model->assignManager($deptId, $managerUserId);
    }
}
