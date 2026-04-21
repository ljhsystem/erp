<?php

namespace App\Services\Ledger;

use App\Models\Ledger\SubAccountPolicyModel;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use PDOException;

class SubAccountPolicyService
{
    private const ALLOWED_TYPES = ['partner', 'project', 'custom'];

    private SubAccountPolicyModel $model;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new SubAccountPolicyModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.SubAccountPolicyService');
    }

    public function getByAccountId(string $accountId): array
    {
        if (!$this->tableExists('ledger_account_sub_policies')) {
            return [];
        }

        try {
            return $this->model->getByAccountId($accountId);
        } catch (\Throwable $e) {
            $this->logger->error('getByAccountId failed', [
                'account_id' => $accountId,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function replacePolicies(string $accountId, array $policies, ?string $actor = null): array
    {
        if (!$this->tableExists('ledger_account_sub_policies')) {
            return [
                'success' => true,
                'data' => [],
            ];
        }

        try {
            $normalized = $this->normalizePolicies($policies);

            $this->model->deleteByAccountId($accountId);

            foreach ($normalized as $index => $policy) {
                $this->model->create([
                    'id' => UuidHelper::generate(),
                    'account_id' => $accountId,
                    'sub_account_type' => $policy['sub_account_type'],
                    'is_required' => $policy['is_required'],
                    'is_multiple' => $policy['is_multiple'],
                    'sort_order' => $index + 1,
                    'custom_group_code' => $policy['custom_group_code'] ?? null,
                    'note' => $policy['note'] ?? null,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);
            }

            return [
                'success' => true,
                'data' => $normalized,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('replacePolicies failed', [
                'account_id' => $accountId,
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
        if (!$this->tableExists('ledger_account_sub_policies')) {
            return 0;
        }

        return $this->model->countByAccountId($accountId);
    }

    public function normalizePolicies(array $policies): array
    {
        $normalized = [];
        $seen = [];

        foreach ($policies as $policy) {
            $type = strtolower(trim((string) ($policy['sub_account_type'] ?? '')));

            if ($type === '') {
                continue;
            }

            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                throw new \InvalidArgumentException('허용되지 않는 보조계정 타입입니다.');
            }

            $customGroupCode = trim((string) ($policy['custom_group_code'] ?? ''));
            if ($type !== 'custom') {
                $customGroupCode = '';
            }

            $policyKey = $type . '|' . $customGroupCode;
            if (isset($seen[$policyKey])) {
                throw new \InvalidArgumentException('중복된 보조정책 타입이 있습니다.');
            }
            $seen[$policyKey] = true;

            $normalized[] = [
                'sub_account_type' => $type,
                'is_required' => !empty($policy['is_required']) ? 1 : 0,
                'is_multiple' => !empty($policy['is_multiple']) ? 1 : 0,
                'custom_group_code' => $customGroupCode !== '' ? $customGroupCode : null,
                'note' => isset($policy['note']) ? trim((string) $policy['note']) : null,
            ];
        }

        return $normalized;
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException) {
            return false;
        }
    }
}
