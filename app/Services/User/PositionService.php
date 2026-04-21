<?php
// 경로: PROJECT_ROOT/app/Services/User/PositionService.php
namespace App\Services\User;

use PDO;
use App\Models\User\PositionModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\LoggerFactory;

class PositionService
{
    private readonly PDO $pdo;
    private PositionModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new PositionModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.PositionService');
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
     * 3) 생성 (UUID + CODE + 검증)
     * ============================================================ */
    public function create(array $data): array
    {
        $name = trim($data['position_name'] ?? '');

        if ($name === '') {
            return ['success' => false, 'message' => 'empty'];
        }

        // 중복 검사
        if ($this->model->existsByName($name)) {
            return ['success' => false, 'message' => 'duplicate'];
        }

        // UUID + Code 생성
        $data['id'] = UuidHelper::generate();
        $data['code'] = CodeHelper::next('user_positions');

        $data['created_by'] = ActorHelper::user();

        $ok = $this->model->create($data);

        return [
            'success' => $ok,
            'message' => $ok ? 'success' : 'fail'
        ];
    }

    /* ============================================================
     * 4) 수정
     * ============================================================ */
    public function update(string $id, array $data): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        $name = trim($data['position_name'] ?? '');

        if ($name === '') {
            return ['success' => false, 'message' => 'empty'];
        }

        // 본인 제외 중복 검사
        if ($this->model->existsByName($name, $id)) {
            return ['success' => false, 'message' => 'duplicate'];
        }

        $data['updated_by'] = ActorHelper::user();

        $ok = $this->model->update($id, $data);

        return [
            'success' => $ok,
            'message' => $ok ? 'success' : 'fail'
        ];
    }

    /* ============================================================
     * 5) 삭제
     * ============================================================ */
    public function delete(string $id): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        $ok = $this->model->delete($id);

        return [
            'success' => $ok,
            'message' => $ok ? 'success' : 'fail'
        ];
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
