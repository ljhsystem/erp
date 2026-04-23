<?php
// 경로: PROJECT_ROOT/app/Services/User/DepartmentService.php
namespace App\Services\User;

use PDO;
use App\Models\User\DepartmentModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class DepartmentService
{
    private readonly PDO $pdo;
    private DepartmentModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new DepartmentModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.DepartmentService');
    }

    /* ============================================================
     * 1) 전체 조회
     * ============================================================ */
    public function getAll(array $filters = []): array
    {
        return $this->model->getAll($filters);
    }

    public function getList(array $filters = []): array
    {
        return $this->getAll($filters);
    }

    /* ============================================================
     * 2) 단일 조회
     * ============================================================ */
    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    /* ============================================================
     * 3) 생성 (UUID + sort_no + 중복검사)
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
        $data['sort_no'] = null;

        // created_by 설정
        $data['created_by'] = ActorHelper::user();

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

        $data['updated_by'] = ActorHelper::user();

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
                    throw new \Exception('reorder 데이터 오류');
                }

                $row['_sort_no'] = (int) $sortNo;
            }
            unset($row);

            foreach ($changes as $row) {
                $this->model->updateSortNo($row['id'], $row['_sort_no'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo($row['id'], $row['_sort_no']);
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
