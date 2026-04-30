<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ChartAccountModel;
use App\Models\Ledger\SubChartAccountModel;
use App\Models\System\CodeModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class CustomSubAccountService
{
    private const REF_TARGET_GROUP = 'REF_TARGET';

    private SubChartAccountModel $model;
    private ChartAccountModel $accountModel;
    private SubAccountPolicyService $policyService;
    private CodeModel $codeModel;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new SubChartAccountModel($pdo);
        $this->accountModel = new ChartAccountModel($pdo);
        $this->policyService = new SubAccountPolicyService($pdo);
        $this->codeModel = new CodeModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.CustomSubAccountService');
    }

    public function getByAccountId(string $accountId): array
    {
        try {
            return array_map(
                fn (array $row): array => $this->normalizeRowForUi($row),
                $this->model->getByAccountId($accountId, 'custom')
            );
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
            $subCode = $this->normalizeSubCode($data['sub_code'] ?? $data['ref_type'] ?? $data['sub_name'] ?? '');
            $codeRow = $this->resolveRefTarget($subCode);
            $subName = (string) ($codeRow['code_name'] ?? $subCode);

            if ($accountId === '') {
                return ['success' => false, 'message' => '계정과목을 선택하세요.'];
            }

            if ($this->model->findByAccountAndSubCode($accountId, $subCode)) {
                return ['success' => false, 'message' => '이미 추가된 보조계정입니다.'];
            }

            $actor = ActorHelper::user();
            $id = UuidHelper::generate();

            $ok = $this->model->create([
                'id' => $id,
                'account_id' => $accountId,
                'ref_type' => $subCode,
                'sub_code' => $subCode,
                'sub_name' => $subName,
                'custom_group_code' => self::REF_TARGET_GROUP,
                'is_required' => !empty($data['is_required']) ? 1 : 0,
                'note' => $data['note'] ?? null,
                'memo' => $data['memo'] ?? null,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$ok) {
                return ['success' => false, 'message' => '보조계정 저장에 실패했습니다.'];
            }

            $this->accountModel->updateAllowSubAccount($accountId, 1);

            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            $this->logger->error('create failed', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(string $id, array $data): array
    {
        try {
            $current = $this->model->getById($id);
            if (!$current) {
                return ['success' => false, 'message' => '보조계정을 찾을 수 없습니다.'];
            }

            $subCode = $this->normalizeSubCode($data['sub_code'] ?? $data['ref_type'] ?? $data['sub_name'] ?? $current['sub_code'] ?? '');
            if ($subCode === '') {
                $subCode = $this->normalizeSubCode($current['ref_type'] ?? '');
            }
            $codeRow = $this->resolveRefTarget($subCode);

            if ($this->model->findByAccountAndSubCode((string) $current['account_id'], $subCode, $id)) {
                return ['success' => false, 'message' => '이미 추가된 보조계정입니다.'];
            }

            $ok = $this->model->update($id, [
                'ref_type' => $subCode,
                'sub_code' => $subCode,
                'sub_name' => (string) ($codeRow['code_name'] ?? $subCode),
                'custom_group_code' => self::REF_TARGET_GROUP,
                'is_required' => !empty($data['is_required']) ? 1 : 0,
                'note' => $data['note'] ?? null,
                'memo' => $data['memo'] ?? null,
                'updated_by' => ActorHelper::user(),
            ]);

            return ['success' => $ok];
        } catch (\Throwable $e) {
            $this->logger->error('update failed', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(string $id): array
    {
        try {
            $accountId = $this->model->getAccountIdById($id);
            if ($accountId === null) {
                return ['success' => false, 'message' => '대상을 찾을 수 없습니다.'];
            }

            $ok = $this->model->delete($id);
            if (!$ok) {
                return ['success' => false, 'message' => '보조계정 삭제에 실패했습니다.'];
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

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function countByAccountId(string $accountId): int
    {
        return $this->model->countByAccountId($accountId, 'custom');
    }

    public function replaceForAccount(string $accountId, array $rows): array
    {
        try {
            $actor = ActorHelper::user();
            $normalized = [];
            $seen = [];

            foreach ($rows as $row) {
                $subCode = $this->normalizeSubCode($row['sub_code'] ?? '');
                if ($subCode === '') {
                    continue;
                }

                if (isset($seen[$subCode])) {
                    return ['success' => false, 'message' => '중복된 보조계정이 있습니다.'];
                }
                $seen[$subCode] = true;

                $codeRow = $this->resolveRefTarget($subCode);
                $normalized[] = [
                    'sub_code' => $subCode,
                    'sub_name' => (string) ($codeRow['code_name'] ?? $subCode),
                    'is_required' => !empty($row['is_required']) ? 1 : 0,
                    'note' => $row['note'] ?? null,
                    'memo' => $row['memo'] ?? null,
                ];
            }

            if (!$this->model->deleteByAccountId($accountId, 'custom')) {
                return ['success' => false, 'message' => '기존 보조계정 정리에 실패했습니다.'];
            }

            foreach ($normalized as $row) {
                $ok = $this->model->create([
                    'id' => UuidHelper::generate(),
                    'account_id' => $accountId,
                    'ref_type' => $row['sub_code'],
                    'sub_code' => $row['sub_code'],
                    'sub_name' => $row['sub_name'],
                    'custom_group_code' => self::REF_TARGET_GROUP,
                    'is_required' => $row['is_required'],
                    'note' => $row['note'],
                    'memo' => $row['memo'],
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);

                if (!$ok) {
                    return ['success' => false, 'message' => '보조계정 저장에 실패했습니다.'];
                }
            }

            $hasPolicies = $this->policyService->countByAccountId($accountId) > 0;
            $this->accountModel->updateAllowSubAccount(
                $accountId,
                ($hasPolicies || count($normalized) > 0) ? 1 : 0
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->logger->error('replaceForAccount failed', [
                'account_id' => $accountId,
                'rows' => $rows,
                'exception' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function normalizeSubCode(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function normalizeRowForUi(array $row): array
    {
        $subCode = $this->normalizeSubCode($row['sub_code'] ?? $row['ref_type'] ?? '');
        $row['ref_type'] = $row['ref_type'] ?? $subCode;
        $row['sub_code'] = $subCode;

        if (empty($row['sub_name'])) {
            try {
                $codeRow = $subCode !== '' ? $this->resolveRefTarget($subCode) : [];
                $row['sub_name'] = (string) ($codeRow['code_name'] ?? $subCode);
            } catch (\Throwable) {
                $row['sub_name'] = $subCode;
            }
        }

        $row['is_required'] = (int) ($row['is_required'] ?? 0);

        return $row;
    }

    private function resolveRefTarget(string $code): array
    {
        if ($code === '') {
            throw new \InvalidArgumentException('보조계정명을 선택하세요.');
        }

        $row = $this->codeModel->getByGroupAndCode(self::REF_TARGET_GROUP, $code);
        if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
            throw new \InvalidArgumentException('REF_TARGET 기준정보에 등록된 보조계정만 사용할 수 있습니다.');
        }

        return $row;
    }
}
