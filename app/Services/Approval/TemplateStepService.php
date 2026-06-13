<?php
namespace App\Services\Approval;

use PDO;
use App\Models\User\ApprovalTemplateStepModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;

class TemplateStepService
{
    private readonly PDO $pdo;
    private $model;
    private $logger;

    public function __construct(\PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new ApprovalTemplateStepModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval.ApprovalTemplateStepService');
    }

    public function getSteps(string $templateId): array
    {
        try {
            $rows = $this->model->getSteps($templateId);

            $this->logger->info('StepService::getSteps', [
                'template_id' => $templateId,
                'count'       => count($rows)
            ]);

            return $rows;

        } catch (\Throwable $e) {
            $this->logger->error("StepService::getSteps 실패", [
                'template_id' => $templateId,
                'error'       => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            $row = $this->model->getById($id);

            return $row;

        } catch (\Throwable $e) {
            $this->logger->error('StepService::getById 실패', [
                'id'    => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function create(array $data): array
    {
        try {
            if ($this->model->existsStepName($data['template_id'], $data['step_name'])) {
                return [
                    'success' => false,
                    'message' => '이미 동일한 스텝명이 존재합니다.'
                ];
            }

            $data['id'] = UuidHelper::generate();

            $data['sort_no'] = SequenceHelper::next('user_approval_template_steps', 'sort_no');

            $data['is_active'] = $data['is_active'] ?? 1;
            $data['created_by'] = ActorHelper::user();
            $data['updated_by'] = $data['created_by'];

            $ok = $this->model->create($data);

            return [
                'success'  => $ok,
                'id'       => $data['id'],
                'sort_no' => $data['sort_no']
            ];

        } catch (\Throwable $e) {
            $this->logger->error("StepService::create 실패", [
                'data'  => $data,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => 'error'];
        }
    }

    public function update(string $id, array $data): array
    {
        try {
            $data['updated_by'] = ActorHelper::user();
            $existing = $this->getById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'not_found'];
            }

            if ($this->model->existsStepName($data['template_id'], $data['step_name'], $id)) {
                return [
                    'success' => false,
                    'message' => '이미 동일한 스텝명이 존재합니다.'
                ];
            }

            $merged = array_merge($existing, $data);

            $ok = $this->model->update($id, $merged);

            return ['success' => $ok];

        } catch (\Throwable $e) {
            $this->logger->error("StepService::update 실패", [
                'id'    => $id,
                'data'  => $data,
                'error' => $e->getMessage()
            ]);
            return ['success' => false];
        }
    }

    public function delete(string $id): bool
    {
        try {
            return $this->model->delete($id);

        } catch (\Throwable $e) {
            $this->logger->error("StepService::delete 실패", [
                'id'    => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
