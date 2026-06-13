<?php

namespace App\Services\User;

use PDO;
use App\Models\User\PositionModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
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

    public function getAll(array $filters = []): array
    {
        return $this->model->getAll($filters);
    }

    public function getList(array $filters = []): array
    {
        return $this->getAll($filters);
    }

    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    public function create(array $data): array
    {
        $name = trim($data['position_name'] ?? '');

        if ($name === '') {
            return ['success' => false, 'message' => 'empty'];
        }

        if ($this->model->existsByName($name)) {
            return ['success' => false, 'message' => 'duplicate'];
        }

        $data['id'] = UuidHelper::generate();
        $data['sort_no'] = SequenceHelper::next('user_positions', 'sort_no');

        $data['created_by'] = ActorHelper::user();

        $ok = $this->model->create($data);

        return [
            'success' => $ok,
            'message' => $ok ? 'success' : 'fail'
        ];
    }

    public function update(string $id, array $data): array
    {
        if (!$id) {
            return ['success' => false, 'message' => 'invalid_id'];
        }

        $name = trim($data['position_name'] ?? '');

        if ($name === '') {
            return ['success' => false, 'message' => 'empty'];
        }

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
