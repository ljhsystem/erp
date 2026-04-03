<?php
// 경로: PROJECT_ROOT/app/services/user/PositionService.php
namespace App\Services\User;

use PDO;
use App\Models\User\UserPositionModel;
use Core\Helpers\UuidHelper;
use Core\Helpers\CodeHelper;
use Core\LoggerFactory;

class PositionService
{
    private readonly PDO $pdo;
    private UserPositionModel $model;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model  = new UserPositionModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-user.PositionService');
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
        $data['code'] = CodeHelper::generatePositionCode($this->pdo);

        $data['created_by'] = $_SESSION['user']['id'] ?? null;

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

        $data['updated_by'] = $_SESSION['user']['id'] ?? null;

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
}
