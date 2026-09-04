<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ChartAccountModel;
use App\Models\Ledger\SubChartAccountModel;
use App\Models\System\CodeModel;
use App\Services\Concerns\LogsServiceOperations;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class CustomSubAccountService
{
    use LogsServiceOperations;
    private const REF_TARGET_GROUP = 'REF_TARGET';

    private SubChartAccountModel $model;
    private ChartAccountModel $accountModel;
    private CodeModel $codeModel;
    private AccountContextRefPolicyService $contextPolicyService;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new SubChartAccountModel($pdo);
        $this->accountModel = new ChartAccountModel($pdo);
        $this->codeModel = new CodeModel($pdo);
        $this->contextPolicyService = new AccountContextRefPolicyService($pdo);
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
            $this->logger->error('보조계정 목록 조회에 실패했습니다.', [
                'event_code' => 'CUSTOM_SUB_ACCOUNT_LIST_FAILED',
                'result' => 'FAILED',
                'error_code' => get_class($e),
                'error' => $e,
            ]);

            return [];
        }
    }

    public function create(array $data): array
    {
        return $this->executeMutation('보조계정 생성', 'CUSTOM_SUB_ACCOUNT_CREATE', 'create', fn(): array => $this->createInternal($data));
    }

    private function createInternal(array $data): array
    {
        try {
            $accountId = trim((string) ($data['account_id'] ?? ''));
            $subCode = $this->normalizeSubCode($data['sub_code'] ?? $data['ref_target'] ?? $data['sub_name'] ?? '');
            $codeRow = $this->resolveRefTarget($subCode);
            $subName = (string) ($codeRow['code_name'] ?? $subCode);

            if ($accountId === '') {
                return ['success' => false, 'message' => '계정과목을 선택하세요.'];
            }
            if (!$this->accountModel->getById($accountId)) {
                return ['success' => false, 'message' => '사용 가능한 계정과목을 찾을 수 없습니다.'];
            }

            if ($this->model->findByAccountAndSubCode($accountId, $subCode)) {
                return ['success' => false, 'message' => '이미 추가된 보조계정입니다.'];
            }

            $actor = ActorHelper::user();
            $id = UuidHelper::generate();

            $ok = $this->model->create([
                'id' => $id,
                'account_id' => $accountId,
                'sort_no' => $this->model->nextSortNo($accountId),
                'ref_target' => $subCode,
                'sub_code' => $subCode,
                'sub_name' => $subName,
                'custom_group_code' => self::REF_TARGET_GROUP,
                'is_required' => !empty($data['is_required']) ? 1 : 0,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$ok) {
                return ['success' => false, 'message' => '보조계정 저장에 실패했습니다.'];
            }

            $this->accountModel->updateAllowSubAccount($accountId, 1);

            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e instanceof \InvalidArgumentException ? $e->getMessage() : '저장 중 오류가 발생했습니다.'];
        }
    }

    public function update(string $accountId, string $id, array $data): array
    {
        return $this->executeMutation('보조계정 수정', 'CUSTOM_SUB_ACCOUNT_UPDATE', 'update', fn(): array => $this->updateInternal($accountId, $id, $data));
    }

    private function updateInternal(string $accountId, string $id, array $data): array
    {
        try {
            $current = $this->model->getById($id);
            if (!$current) {
                return ['success' => false, 'message' => '보조계정을 찾을 수 없습니다.'];
            }
            if ((string) ($current['account_id'] ?? '') !== $accountId || !$this->accountModel->getById($accountId)) {
                return ['success' => false, 'message' => '보조계정의 소유 계정이 일치하지 않습니다.'];
            }

            $subCode = $this->normalizeSubCode($data['sub_code'] ?? $data['ref_target'] ?? $data['sub_name'] ?? $current['sub_code'] ?? '');
            if ($subCode === '') {
                $subCode = $this->normalizeSubCode($current['ref_target'] ?? '');
            }
            $codeRow = $this->resolveRefTarget($subCode);

            if ($this->model->findByAccountAndSubCode((string) $current['account_id'], $subCode, $id)) {
                return ['success' => false, 'message' => '이미 추가된 보조계정입니다.'];
            }

            $ok = $this->model->update($id, [
                'ref_target' => $subCode,
                'sub_code' => $subCode,
                'sub_name' => (string) ($codeRow['code_name'] ?? $subCode),
                'custom_group_code' => self::REF_TARGET_GROUP,
                'is_required' => !empty($data['is_required']) ? 1 : 0,
                'updated_by' => ActorHelper::user(),
            ]);

            return ['success' => $ok];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e instanceof \InvalidArgumentException ? $e->getMessage() : '수정 중 오류가 발생했습니다.'];
        }
    }

    public function delete(string $accountId, string $id): array
    {
        return $this->executeMutation('보조계정 삭제', 'CUSTOM_SUB_ACCOUNT_DELETE', 'delete', fn(): array => $this->deleteInternal($accountId, $id));
    }

    private function deleteInternal(string $accountId, string $id): array
    {
        try {
            $current = $this->model->getById($id);
            if (!$current) {
                return ['success' => false, 'message' => '대상을 찾을 수 없습니다.'];
            }
            if ((string) ($current['account_id'] ?? '') !== $accountId || !$this->accountModel->getById($accountId)) {
                return ['success' => false, 'message' => '보조계정의 소유 계정이 일치하지 않습니다.'];
            }

            $this->contextPolicyService->assertAllowedPoliciesDeletable([$id]);
            $ok = $this->model->delete($id);
            if (!$ok) {
                return ['success' => false, 'message' => '보조계정 삭제에 실패했습니다.'];
            }

            $hasCustom = $this->model->countByAccountId($accountId, 'custom') > 0;
            $this->accountModel->updateAllowSubAccount(
                $accountId,
                $hasCustom ? 1 : 0
            );

            return ['success' => true];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'];
        }
    }

    public function countByAccountId(string $accountId): int
    {
        return $this->model->countByAccountId($accountId, 'custom');
    }

    public function countByAccountIds(array $accountIds): array
    {
        return $this->model->countByAccountIds($accountIds, 'custom');
    }

    public function replaceForAccount(string $accountId, array $rows): array
    {
        return $this->executeMutation('보조계정 구성 저장', 'CUSTOM_SUB_ACCOUNT_REPLACE', 'replace', fn(): array => $this->replaceForAccountInternal($accountId, $rows));
    }

    private function replaceForAccountInternal(string $accountId, array $rows): array
    {
        $started = !$this->pdo->inTransaction();
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
                ];
            }

            if ($started) {
                $this->pdo->beginTransaction();
            }

            $currentRows = $this->model->getByAccountId($accountId, 'custom');
            $currentByCode = [];
            foreach ($currentRows as $current) {
                $currentByCode[$this->normalizeSubCode($current['sub_code'] ?? $current['ref_target'] ?? '')] = $current;
            }

            foreach ($normalized as $index => $row) {
                $current = $currentByCode[$row['sub_code']] ?? null;
                if ($current) {
                    unset($currentByCode[$row['sub_code']]);
                    $ok = $this->model->update((string) $current['id'], [
                        'ref_target' => $row['sub_code'],
                        'sub_code' => $row['sub_code'],
                        'sub_name' => $row['sub_name'],
                        'custom_group_code' => self::REF_TARGET_GROUP,
                        'is_required' => $row['is_required'],
                        'updated_by' => $actor,
                    ]) && $this->model->updateSortNo((string) $current['id'], $index + 1, $actor);
                } else {
                    $ok = $this->model->create([
                    'id' => UuidHelper::generate(),
                    'account_id' => $accountId,
                    'sort_no' => $index + 1,
                    'ref_target' => $row['sub_code'],
                    'sub_code' => $row['sub_code'],
                    'sub_name' => $row['sub_name'],
                    'custom_group_code' => self::REF_TARGET_GROUP,
                    'is_required' => $row['is_required'],
                    'created_by' => $actor,
                    'updated_by' => $actor,
                    ]);
                }

                if (!$ok) {
                    throw new \RuntimeException('보조계정 저장에 실패했습니다.');
                }
            }

            $removedIds = array_values(array_map(static fn (array $row): string => (string) $row['id'], $currentByCode));
            $this->contextPolicyService->assertAllowedPoliciesDeletable($removedIds);
            foreach ($removedIds as $removedId) {
                if (!$this->model->delete($removedId)) {
                    throw new \RuntimeException('기존 보조계정 정리에 실패했습니다.');
                }
            }

            $this->accountModel->updateAllowSubAccount(
                $accountId,
                count($normalized) > 0 ? 1 : 0
            );

            if ($started) {
                $this->pdo->commit();
            }
            return ['success' => true];
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e instanceof \InvalidArgumentException ? $e->getMessage() : '저장 중 오류가 발생했습니다.'];
        }
    }

    private function normalizeSubCode(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function executeMutation(string $label, string $eventCode, string $action, callable $operation): array
    {
        return $this->runLoggedOperation($this->logger, $label, $eventCode, $action, [], $operation, 'info', false,
            static fn(array $result): string => !empty($result['success']) ? 'SUCCESS' : (str_contains((string) ($result['message'] ?? ''), '오류') ? 'FAILED' : 'BLOCKED'));
    }

    private function normalizeRowForUi(array $row): array
    {
        $subCode = $this->normalizeSubCode($row['sub_code'] ?? $row['ref_target'] ?? '');
        $row['ref_target'] = $row['ref_target'] ?? $subCode;
        $row['sub_code'] = $subCode;

        if (empty($row['sub_name'])) {
            try {
                $codeRow = $subCode !== '' ? $this->resolveRefTarget($subCode, false) : [];
                $row['sub_name'] = (string) ($codeRow['code_name'] ?? $subCode);
            } catch (\Throwable) {
                $row['sub_name'] = $subCode;
            }
        }

        $row['is_required'] = (int) ($row['is_required'] ?? 0);

        return $row;
    }

    private function resolveRefTarget(string $code, bool $requireActive = true): array
    {
        if ($code === '') {
            throw new \InvalidArgumentException('보조계정 대상을 선택하세요.');
        }

        $row = $this->codeModel->getByGroupAndCode(self::REF_TARGET_GROUP, $code);
        if (!$row || ($requireActive && (int) ($row['is_active'] ?? 0) !== 1)) {
            throw new \InvalidArgumentException('REF_TARGET 기준정보에 등록된 보조계정만 사용할 수 있습니다.');
        }

        return $row;
    }
}
