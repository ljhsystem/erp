<?php
// 경로: PROJECT_ROOT/app/Services/Approval/TemplateStepService.php
namespace App\Services\Approval;

use PDO;
use App\Models\User\ApprovalTemplateStepModel;
use Core\Helpers\ActorHelper;
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

    /* ============================================================
     * 📌 스텝 리스트 조회
     * ============================================================ */
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

    /* ============================================================
     * 📌 단건 조회
     * ============================================================ */
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

    /* ============================================================
     * 📌 생성(Create)
     * ============================================================ */
    public function create(array $data): array
    {
        try {
            // 🔥 중복 스텝명 체크
            if ($this->model->existsStepName($data['template_id'], $data['step_name'])) {
                return [
                    'success' => false,
                    'message' => '이미 동일한 스텝명이 존재합니다.'
                ];
            }

            // 🔥 UUID 생성 (모델 금지)
            $data['id'] = UuidHelper::generate();

            // 🔥 sequence 계산
            $data['sequence'] = $this->model->getNextSequence($data['template_id']);

            // 🔥 기본값
            $data['is_active'] = $data['is_active'] ?? 1;
            $data['created_by'] = ActorHelper::user();

            $ok = $this->model->create($data);

            return [
                'success'  => $ok,
                'id'       => $data['id'],
                'sequence' => $data['sequence']
            ];

        } catch (\Throwable $e) {
            $this->logger->error("StepService::create 실패", [
                'data'  => $data,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => 'error'];
        }
    }

    /* ============================================================
     * 📌 수정(Update)
     * ============================================================ */
    public function update(string $id, array $data): array
    {
        try {
            $data['updated_by'] = ActorHelper::user();
            $existing = $this->getById($id);
            if (!$existing) {
                return ['success' => false, 'message' => 'not_found'];
            }

            // 🔥 중복 스텝명 체크 (자기 자신 제외)
            if ($this->model->existsStepName($data['template_id'], $data['step_name'], $id)) {
                return [
                    'success' => false,
                    'message' => '이미 동일한 스텝명이 존재합니다.'
                ];
            }

            // 기존 데이터 + 새로운 데이터 병합
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

    /* ============================================================
     * 📌 삭제(Delete)
     * ============================================================ */
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
