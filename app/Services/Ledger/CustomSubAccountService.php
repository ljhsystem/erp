<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ChartAccountModel;
use App\Models\Ledger\SubChartAccountModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class CustomSubAccountService
{
    private SubChartAccountModel $model;
    private ChartAccountModel $accountModel;
    private SubAccountPolicyService $policyService;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new SubChartAccountModel($pdo);
        $this->accountModel = new ChartAccountModel($pdo);
        $this->policyService = new SubAccountPolicyService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.CustomSubAccountService');
    }

    public function getByAccountId(string $accountId): array
    {
        try {
            $rows = $this->model->getByAccountId($accountId, 'custom');

            $this->logger->info('getByAccountId returned', [
                'account_id' => $accountId,
                'count' => count($rows),
            ]);

            return $rows;
        } catch (\Throwable $e) {
            $this->logger->error('getByAccountId failed', [
                'account_id' => $accountId,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function create(array $data): array
    {
        try {
            $accountId = trim((string) ($data['account_id'] ?? ''));
            $subName = trim((string) ($data['sub_name'] ?? ''));

            if ($accountId === '' || $subName === '') {
                return [
                    'success' => false,
                    'message' => '보조계정 생성 정보가 부족합니다.',
                ];
            }

            $exists = $this->model->findByAccountAndName($accountId, $subName, 'custom');
            if ($exists) {
                return [
                    'success' => true,
                    'id' => $exists['id'],
                    'message' => '이미 존재하는 보조계정입니다.',
                ];
            }

            $id = UuidHelper::generate();
            $subCode = $this->model->getNextSubCode($accountId);

            $actor = ActorHelper::user();

            $ok = $this->model->create([
                'id' => $id,
                'account_id' => $accountId,
                'sub_code' => $subCode,
                'sub_name' => $subName,
                'sub_type' => 'custom',
                'ref_type' => null,
                'ref_id' => null,
                'note' => $data['note'] ?? null,
                'memo' => $data['memo'] ?? null,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$ok) {
                return [
                    'success' => false,
                    'message' => '보조계정 저장에 실패했습니다.',
                ];
            }

            $this->accountModel->updateAllowSubAccount($accountId, 1);

            return [
                'success' => true,
                'id' => $id,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('create failed', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function update(string $id, array $data): array
    {
        try {
            $data['updated_by'] = ActorHelper::user();
            $ok = $this->model->update($id, $data);

            return ['success' => $ok];
        } catch (\Throwable $e) {
            $this->logger->error('update failed', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function delete(string $id): array
    {
        try {
            $accountId = $this->model->getAccountIdById($id);
            if ($accountId === null) {
                return [
                    'success' => false,
                    'message' => '대상을 찾을 수 없습니다.',
                ];
            }

            $ok = $this->model->delete($id);
            if (!$ok) {
                return [
                    'success' => false,
                    'message' => '보조계정 삭제에 실패했습니다.',
                ];
            }

            $hasCustom = $this->model->countByAccountId($accountId, 'custom') > 0;
            $hasPolicies = $this->policyService->countByAccountId($accountId) > 0;

            $this->accountModel->updateAllowSubAccount(
                $accountId,
                ($hasCustom || $hasPolicies) ? 1 : 0
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->logger->error('delete failed', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function countByAccountId(string $accountId): int
    {
        return $this->model->countByAccountId($accountId, 'custom');
    }
}
