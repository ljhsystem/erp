<?php
// 경로: PROJECT_ROOT . '/app/Services/Auth/RoleService.php'
namespace App\Services\Auth;

use PDO;
use App\Models\Auth\RoleModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\LoggerFactory;

class RoleService
{
    private readonly PDO $pdo;
    private RoleModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new RoleModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-auth.RoleService');
    }

    /* ---------------------------------------------------------------
     * 1) 전체 역할 조회
     * --------------------------------------------------------------- */
    public function getAll(array $filters = []): array
    {
        return $this->model->getAll($filters);
    }

    public function getList(array $filters = []): array
    {
        return $this->getAll($filters);
    }

    /* ---------------------------------------------------------------
     * 2) 역할 생성 (UUID/코드 생성 책임 서비스)
     * --------------------------------------------------------------- */
    public function create(array $data): array
    {
        if (empty($data['role_key']) || empty($data['role_name'])) {
            return [
                'success' => false,
                'message' => 'role_key 또는 role_name 이 누락되었습니다.'
            ];
        }

        if ($this->model->existsKey($data['role_key'])) {
            return [
                'success' => false,
                'message' => 'duplicate_key'
            ];
        }

        // ⭐ Service가 생성해야 하는 값들
        $data['id']         = UuidHelper::generate();
        $data['code']       = CodeHelper::generateRoleCode($this->pdo);
        $data['created_by'] = $_SESSION['user']['id'] ?? null;

        $res = $this->model->create($data);

        return [
            'success' => $res['success'] ?? false,
            'message' => ($res['success'] ?? false)
                ? '역할이 생성되었습니다.'
                : '역할 생성 실패'
        ];
    }

    /* ---------------------------------------------------------------
     * 3) 역할 수정
     * --------------------------------------------------------------- */
    public function update(string $id, array $data): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'ID 누락'];
        }

        if (isset($data['role_key']) &&
            $this->model->existsKey($data['role_key'], $id)) {

            return [
                'success' => false,
                'message' => 'duplicate_key'
            ];
        }

        $data['updated_by'] = $_SESSION['user']['id'] ?? null;

        $res = $this->model->update($id, $data);

        return [
            'success' => $res['success'] ?? false,
            'message' => ($res['success'] ?? false)
                ? '수정되었습니다.'
                : '수정 실패'
        ];
    }

    /* ---------------------------------------------------------------
     * 4) 역할 삭제
     * --------------------------------------------------------------- */
    public function delete(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'ID 누락'];
        }

        $ok = $this->model->delete($id);

        return [
            'success' => $ok,
            'message' => $ok ? '삭제되었습니다.' : '삭제 실패'
        ];
    }

    /* ---------------------------------------------------------------
     * 5) 활성화/비활성화
     * --------------------------------------------------------------- */
    public function toggleActive(string $id, int $active): bool
    {
        return $this->model->toggleActive($id, $active);
    }

    /* ---------------------------------------------------------------
     * 6) 역할 조회(id 또는 role_key)
     * --------------------------------------------------------------- */
    public function findByIdOrKey(string $value): ?array
    {
        return $this->model->findByIdOrKey($value);
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
                $this->model->updateCode($row['id'], (int)$row['newCode'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->model->updateCode($row['id'], (int)$row['newCode']);
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
