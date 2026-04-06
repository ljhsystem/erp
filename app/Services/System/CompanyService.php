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

    public function get(): array
    {
        return $this->model->getOne() ?? [];
    }
    
    public function save(array $data, string $userId): array
    {
        $this->pdo->beginTransaction();
    
        try {
    
            $exists = $this->model->getOne();
    
            if ($exists) {
    
                $data['updated_by'] = $userId;
    
                $ok = $this->model->updateById($exists['id'], $data);
    
                if (!$ok) {
                    throw new \Exception('수정 실패');
                }
    
                $message = '수정 완료';
    
            } else {
    
                $data['id']         = UuidHelper::generate();
                $data['created_by'] = $userId;
    
                $ok = $this->model->create($data);
    
                if (!$ok) {
                    throw new \Exception('등록 실패');
                }
    
                $message = '등록 완료';
            }
    
            $this->pdo->commit();
    
            return [
                'success' => true,
                'message' => $message
            ];
    
        } catch (\Throwable $e) {
    
            $this->pdo->rollBack();
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
