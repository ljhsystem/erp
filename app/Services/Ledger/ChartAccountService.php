<?php

namespace App\Services\Ledger;

use App\Models\Ledger\ChartAccountModel;
use App\Models\Ledger\SubChartAccountModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class ChartAccountService
{
    private ChartAccountModel $model;
    private SubChartAccountModel $subAccountModel;
    private ChartAccountReferenceGuardService $referenceGuard;
    private CustomSubAccountService $customSubAccountService;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new ChartAccountModel($pdo);
        $this->subAccountModel = new SubChartAccountModel($pdo);
        $this->referenceGuard = new ChartAccountReferenceGuardService($pdo);
        $this->customSubAccountService = new CustomSubAccountService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger.ChartAccountService');
        $this->logger->info('ChartAccountService initialized');
    }

    public function getAll(): array
    {
        try {
            return $this->model->getAll();
        } catch (\Throwable $e) {
            $this->logger->error('getAll failed', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getTree(): array
    {
        try {
            return $this->model->getTree();
        } catch (\Throwable $e) {
            $this->logger->error('getTree failed', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('getById failed', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getByAccountCode(string $accountCode): ?array
    {
        try {
            return $this->model->getByAccountCode($accountCode);
        } catch (\Throwable $e) {
            $this->logger->error('getByAccountCode failed', [
                'account_code' => $accountCode,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveOrCreateParentAccount(array $data, ?string $actor, ?string $currentId = null): array
    {
        $newParentCode = trim((string) ($data['new_parent_code'] ?? ''));
        if ($newParentCode === '') {
            return [
                'success' => true,
                'parent_id' => $data['parent_id'] ?? null,
            ];
        }

        if ($newParentCode === trim((string) ($data['account_code'] ?? ''))) {
            return [
                'success' => false,
                'message' => '상위계정 코드는 현재 계정코드와 다르게 입력해야 합니다.',
            ];
        }

        $existing = $this->model->getByAccountCode($newParentCode);
        if ($existing) {
            if ($currentId !== null && ($existing['id'] ?? null) === $currentId) {
                return [
                    'success' => false,
                    'message' => '자기 자신은 상위계정으로 지정할 수 없습니다.',
                ];
            }

            return [
                'success' => true,
                'parent_id' => $existing['id'],
            ];
        }

        $newParentId = UuidHelper::generate();
        $parentName = trim((string) ($data['new_parent_name'] ?? ''));
        $accountGroup = trim((string) ($data['account_group'] ?? ''));

        if ($accountGroup === '') {
            return [
                'success' => false,
                'message' => '신규 상위계정 생성 시 계정구분은 필수입니다.',
            ];
        }

        $created = $this->model->create([
            'id' => $newParentId,
            'account_code' => $newParentCode,
            'account_name' => $parentName !== '' ? $parentName : $newParentCode,
            'parent_id' => null,
            'account_group' => $accountGroup,
            'normal_balance' => $data['normal_balance'] ?? 'debit',
            'level' => 1,
            'is_posting' => 1,
            'allow_sub_account' => (int) ($data['allow_sub_account'] ?? 0),
            'note' => null,
            'memo' => null,
            'is_active' => 1,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        if (!$created) {
            return [
                'success' => false,
                'message' => '신규 상위계정 생성에 실패했습니다.',
            ];
        }

        return [
            'success' => true,
            'parent_id' => $newParentId,
        ];
    }

    public function create(array $data): array
    {
        try {
            $actor = ActorHelper::user();

            if ($this->model->getByAccountCode($data['account_code'])) {
                return [
                    'success' => false,
                    'message' => '이미 존재하는 계정코드입니다.',
                ];
            }

            if (!empty($data['parent_id']) && !$this->model->getById($data['parent_id'])) {
                return [
                    'success' => false,
                    'message' => '상위 계정을 찾을 수 없습니다.',
                ];
            }
            if (!empty($data['parent_id']) && (int) ($this->model->getById($data['parent_id'])['is_active'] ?? 0) !== 1) {
                return ['success' => false, 'message' => '비활성 계정은 신규 상위계정으로 선택할 수 없습니다.'];
            }

            $classificationValidation = $this->validateClassification($data);
            if (!($classificationValidation['success'] ?? false)) {
                return $classificationValidation;
            }

            $validation = $this->validateParent(null, $data['parent_id'] ?? null);
            if (!($validation['success'] ?? false)) {
                return $validation;
            }

            $this->pdo->beginTransaction();

            $parentResult = $this->resolveOrCreateParentAccount($data, $actor);
            if (!($parentResult['success'] ?? false)) {
                $this->pdo->rollBack();
                return $parentResult;
            }
            $data['parent_id'] = $parentResult['parent_id'] ?? ($data['parent_id'] ?? null);

            $data['id'] = UuidHelper::generate();
            $data['sort_no'] = SequenceHelper::next('ledger_accounts', 'sort_no');
            $data['level'] = $this->resolveLevel($data['parent_id'] ?? null);
            $data['account_level'] = $data['level'];
            $data['is_postable'] = $this->normalizePostable($data['is_postable'] ?? $data['is_posting'] ?? 1);
            $data['is_posting'] = $data['is_postable'] === 'Y' ? 1 : 0;
            $data['status'] = ((int) ($data['is_active'] ?? 1)) === 1 ? 'active' : 'inactive';
            $data['allow_sub_account'] = (int) ($data['allow_sub_account'] ?? 0);
            $subAccountValidation = $this->validateRequiredSubAccounts($data);
            if (!($subAccountValidation['success'] ?? false)) {
                $this->pdo->rollBack();
                return $subAccountValidation;
            }
            $data['created_by'] = $actor;
            $data['updated_by'] = $actor;

            if (!$this->model->create($data)) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '계정 생성에 실패했습니다.',
                ];
            }

            if (array_key_exists('sub_accounts', $data)) {
                $subAccountResult = $this->customSubAccountService->replaceForAccount(
                    $data['id'],
                    $data['sub_accounts'] ?? []
                );

                if (!$subAccountResult['success']) {
                    $this->pdo->rollBack();
                    return $subAccountResult;
                }
            }

            $this->model->refreshHierarchyMetadata();
            $this->model->refreshPostableFlags();

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $data['id'],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('create failed', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '저장 중 오류가 발생했습니다.',
            ];
        }
    }

    public function update(string $id, array $data): array
    {
        try {
            $data['updated_by'] = ActorHelper::user();
            $existing = $this->model->getById($id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => '계정을 찾을 수 없습니다.',
                ];
            }

            $data = array_merge($existing, $data);

            $exists = $this->model->getByAccountCode($data['account_code']);
            if ($exists && $exists['id'] !== $id) {
                return [
                    'success' => false,
                    'message' => '이미 존재하는 계정코드입니다.',
                ];
            }

            if (!empty($data['parent_id']) && $data['parent_id'] === $id) {
                return [
                    'success' => false,
                    'message' => '자기 자신을 상위계정으로 지정할 수 없습니다.',
                ];
            }

            if (!empty($data['parent_id']) && !$this->model->getById($data['parent_id'])) {
                return [
                    'success' => false,
                    'message' => '상위 계정을 찾을 수 없습니다.',
                ];
            }
            $parentChanged = (string) ($existing['parent_id'] ?? '') !== (string) ($data['parent_id'] ?? '');
            if ($parentChanged && !empty($data['parent_id']) && (int) ($this->model->getById($data['parent_id'])['is_active'] ?? 0) !== 1) {
                return ['success' => false, 'message' => '비활성 계정으로 계층을 이동할 수 없습니다.'];
            }

            $classificationValidation = $this->validateClassification($data);
            if (!($classificationValidation['success'] ?? false)) {
                return $classificationValidation;
            }

            $validation = $this->validateParent($id, $data['parent_id'] ?? null);
            if (!($validation['success'] ?? false)) {
                return $validation;
            }

            if ($this->normalizePostable($data['is_postable'] ?? $data['is_posting'] ?? 1) === 'Y' && $this->model->hasChildren($id)) {
                return [
                    'success' => false,
                    'message' => '자식 계정이 있는 그룹계정은 전표입력 가능으로 설정할 수 없습니다.',
                ];
            }

            $this->pdo->beginTransaction();

            $parentResult = $this->resolveOrCreateParentAccount($data, $data['updated_by'], $id);
            if (!($parentResult['success'] ?? false)) {
                $this->pdo->rollBack();
                return $parentResult;
            }
            $data['parent_id'] = $parentResult['parent_id'] ?? ($data['parent_id'] ?? null);

            $data['level'] = $this->resolveLevel($data['parent_id'] ?? null);
            $data['account_level'] = $data['level'];
            $data['is_postable'] = $this->normalizePostable($data['is_postable'] ?? $data['is_posting'] ?? 1);
            $data['is_posting'] = $data['is_postable'] === 'Y' ? 1 : 0;
            $data['status'] = ((int) ($data['is_active'] ?? 1)) === 1 ? 'active' : 'inactive';
            $data['allow_sub_account'] = (int) ($data['allow_sub_account'] ?? 0);
            $subAccountValidation = $this->validateRequiredSubAccounts($data);
            if (!($subAccountValidation['success'] ?? false)) {
                $this->pdo->rollBack();
                return $subAccountValidation;
            }

            if (!$this->model->update($id, $data)) {
                $this->pdo->rollBack();

                return [
                    'success' => false,
                    'message' => '계정 수정에 실패했습니다.',
                ];
            }

            if (array_key_exists('sub_accounts', $data)) {
                $subAccountResult = $this->customSubAccountService->replaceForAccount(
                    $id,
                    $data['sub_accounts'] ?? []
                );

                if (!$subAccountResult['success']) {
                    $this->pdo->rollBack();
                    return $subAccountResult;
                }
            }

            $this->model->refreshHierarchyMetadata();
            $this->model->refreshPostableFlags();

            $this->pdo->commit();

            return ['success' => true];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->logger->error('update failed', [
                'id' => $id,
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '계정 수정 오류가 발생했습니다.',
            ];
        }
    }

    public function updateStatus(string $id, int $isActive): array
    {
        try {
            if (!$this->model->getById($id)) {
                return [
                    'success' => false,
                    'message' => '계정을 찾을 수 없습니다.',
                ];
            }

            $normalized = $isActive === 1 ? 1 : 0;
            $ids = $this->model->getDescendantIds($id, true);
            if (empty($ids)) {
                $ids = [$id];
            }

            if (!$this->model->updateStatus($ids, $normalized, ActorHelper::user())) {
                return [
                    'success' => false,
                    'message' => '상태 변경에 실패했습니다.',
                ];
            }

            return [
                'success' => true,
                'is_active' => $normalized,
                'updated_ids' => $ids,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('updateStatus failed', [
                'id' => $id,
                'is_active' => $isActive,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '수정 중 오류가 발생했습니다.',
            ];
        }
    }

    public function softDelete(string $id): array
    {
        try {
            $actor = $this->currentActor();
            if ($this->model->hasChildren($id)) {
                return [
                    'success' => false,
                    'message' => '하위 계정이 존재하여 삭제할 수 없습니다.',
                ];
            }

            if ($this->referenceGuard->referencesFor($id) !== []) {
                return [
                    'success' => false,
                    'message' => '다른 업무에서 사용 중인 계정은 삭제할 수 없습니다.',
                ];
            }

            $success = $this->model->softDelete($id, $actor);
            if (!$success) {
                return [
                    'success' => false,
                    'message' => '삭제할 계정을 찾을 수 없습니다.',
                ];
            }

            $this->model->refreshHierarchyMetadata();
            $this->model->refreshPostableFlags();

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->logger->error('softDelete failed', [
                'id' => $id,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '계정과목 삭제 중 오류가 발생했습니다.',
            ];
        }
    }

    public function restore(string $id): array
    {
        $actor = $this->currentActor();
        $success = $this->model->restore($id, $actor);
        if ($success) {
            $this->model->refreshHierarchyMetadata();
            $this->model->refreshPostableFlags();
        }

        return ['success' => $success];
    }

    public function getTrashList(): array
    {
        return $this->model->getTrashList();
    }

    public function hardDelete(string $id): array
    {
        return $this->hardDeleteMany([$id]);
    }

    public function hasChildren(string $id): bool
    {
        return $this->model->hasChildren($id);
    }

    private function currentActor(): string
    {
        try {
            return ActorHelper::user();
        } catch (\Throwable) {
            return ActorHelper::system('LEDGER_ACCOUNT');
        }
    }

    public function getTreeStructured(): array
    {
        $rows = $this->model->getTree();
        $map = [];
        $tree = [];

        foreach ($rows as &$row) {
            $row['children'] = [];
            $map[$row['id']] = &$row;
        }

        foreach ($rows as &$row) {
            if (!empty($row['parent_id']) && isset($map[$row['parent_id']])) {
                $map[$row['parent_id']]['children'][] = &$row;
            } else {
                $tree[] = &$row;
            }
        }

        return $tree;
    }

    public function findByCode(string $code): ?array
    {
        return $this->model->findByCode($code);
    }

    public function sumDescendantVoucherLines(string $id): array
    {
        return $this->model->sumDescendantVoucherLines($id);
    }

    public function createSubAccount(array $data): array
    {
        return $this->customSubAccountService->create($data);
    }

    public function getList(array $filters = []): array
    {
        $rows = $this->model->getList($filters);

        foreach ($rows as &$row) {
            $allowSubAccount = (int) ($row['allow_sub_account'] ?? 0);
            $hasSubAccounts = (int) ($row['has_sub_account'] ?? 0) === 1;

            $row['sub_account_status'] = $hasSubAccounts
                ? '사용중'
                : ($allowSubAccount === 1 ? '가능' : '미사용');
        }
        unset($row);

        $this->logger->info('getList returned', [
            'count' => count($rows),
        ]);

        return $rows;
    }

    public function reorder(array $changes): void
    {
        foreach ($changes as $row) {
            $this->model->updateOrder(
                $row['id'],
                (int) $row['newSortNo']
            );
        }

        $this->model->refreshHierarchyMetadata();
    }

    public function getDetailByAccountCode(string $accountCode): ?array
    {
        $row = $this->model->getDetailByAccountCode($accountCode);

        if (!$row) {
            return null;
        }

        $hasSubAccounts = $this->customSubAccountService->countByAccountId($row['id']) > 0;
        $row['allow_sub_account_computed'] = (
            (int) ($row['allow_sub_account'] ?? 0) === 1
            || $hasSubAccounts
        ) ? 1 : 0;

        return $row;
    }

    public function restoreBulk(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $this->model->restoreByIds($ids);
        $this->model->refreshHierarchyMetadata();
        $this->model->refreshPostableFlags();
    }

    public function restoreAll(): void
    {
        $this->model->restoreAllDeleted();
        $this->model->refreshHierarchyMetadata();
        $this->model->refreshPostableFlags();
    }

    public function hardDeleteMany(array $ids): array
    {
        $validation = $this->referenceGuard->validatePurge($ids);
        if (!($validation['success'] ?? false)) {
            return $validation;
        }

        $targetIds = $validation['ids'];
        $this->pdo->beginTransaction();
        try {
            $this->subAccountModel->deleteByAccountIds($targetIds);
            $deleted = $this->model->hardDeleteByIds($targetIds);
            if ($deleted !== count($targetIds)) {
                throw new \RuntimeException('계정 영구삭제 처리 건수가 일치하지 않습니다.');
            }

            $this->pdo->commit();
            $this->model->refreshHierarchyMetadata();
            $this->model->refreshPostableFlags();
            return ['success' => true, 'deleted_count' => $deleted];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger->error('hardDeleteMany failed', [
                'ids' => $targetIds,
                'exception' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => '영구삭제 중 오류가 발생했습니다.'];
        }
    }

    public function hardDeleteAll(): array
    {
        return $this->hardDeleteMany([]);
    }

    private function validateParent(?string $id, ?string $parentId): array
    {
        if (!$parentId) {
            return ['success' => true];
        }

        if ($id !== null && $parentId === $id) {
            return [
                'success' => false,
                'message' => '자기 자신을 상위계정으로 지정할 수 없습니다.',
            ];
        }

        if (!$this->model->getById($parentId)) {
            return [
                'success' => false,
                'message' => '상위 계정을 찾을 수 없습니다.',
            ];
        }

        if ($id !== null && $this->model->isDescendantOf($parentId, $id)) {
            return [
                'success' => false,
                'message' => '하위 계정을 상위계정으로 지정할 수 없습니다.',
            ];
        }

        return ['success' => true];
    }

    private function validateClassification(array $data): array
    {
        $group = trim((string) ($data['account_group'] ?? ''));
        $balance = strtolower(trim((string) ($data['normal_balance'] ?? '')));
        $expected = match ($group) {
            '자산', '비용' => 'debit',
            '부채', '자본', '수익' => 'credit',
            default => null,
        };

        if ($expected === null) {
            return ['success' => false, 'message' => '유효한 계정구분을 선택하세요.'];
        }
        if ($balance !== $expected) {
            return ['success' => false, 'message' => '계정구분과 정상잔액의 조합이 올바르지 않습니다.'];
        }

        return ['success' => true];
    }

    private function normalizePostable(mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, ['Y', 'YES', 'TRUE', '1', '가능', '사용'], true)) {
            return 'Y';
        }

        return 'N';
    }

    private function resolveLevel(?string $parentId): int
    {
        if (!$parentId) {
            return 1;
        }

        $parent = $this->model->getById($parentId);

        return $parent ? ((int) ($parent['account_level'] ?? $parent['level'] ?? 0) + 1) : 1;
    }

    private function syncLegacyAllowSubAccountFlag(string $accountId): void
    {
        $hasCustom = $this->customSubAccountService->countByAccountId($accountId) > 0;

        $this->model->updateAllowSubAccount(
            $accountId,
            $hasCustom ? 1 : 0
        );
    }

    private function validateRequiredSubAccounts(array $data): array
    {
        if ((int) ($data['allow_sub_account'] ?? 0) !== 1) {
            return ['success' => true];
        }

        $rows = $data['sub_accounts'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $selectedRows = array_values(array_filter($rows, static function ($row): bool {
            return is_array($row) && trim((string) ($row['sub_code'] ?? '')) !== '';
        }));

        if (count($selectedRows) === 0) {
            return [
                'success' => false,
                'message' => '보조계정 사용 시 보조계정 대상을 1개 이상 선택해주세요.',
            ];
        }

        return ['success' => true];
    }

}
