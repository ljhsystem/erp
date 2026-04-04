<?php
// PROJECT_ROOT. '/app/Services/System/CompanyService.php'
namespace App\Services\System;

use PDO;
use App\Models\System\CompanyModel;
use Core\Helpers\UuidHelper;

class CompanyService
{
    private readonly PDO $pdo;
    private CompanyModel $model;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;
        $this->model = new CompanyModel($pdo);
    }

    public function get(): ?array
    {
        return $this->model->getOne();
    }

    public function save(array $data, string $userId): array
    {
        $this->pdo->beginTransaction();

        try {
            $exists = $this->model->getOne();

            if ($exists) {
                $data['updated_by'] = $userId;
                $this->model->updateById($exists['id'], $data);
            } else {
                $data['id']         = UuidHelper::generate();
                $data['created_by'] = $userId;
                $this->model->create($data);
            }

            $this->pdo->commit();
            return ['success' => true];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
