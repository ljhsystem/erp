<?php

namespace App\Services\Ledger;

use App\Models\Ledger\JournalRuleModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class JournalRuleService
{
    private JournalRuleModel $model;

    public function __construct(private PDO $pdo)
    {
        $this->model = new JournalRuleModel($pdo);
    }

    public function getList(array $filters = []): array
    {
        return $this->model->getList($filters);
    }

    public function getTrashList(): array
    {
        return $this->model->getList([], true);
    }

    public function getById(string $id, bool $includeDeleted = false): ?array
    {
        return $this->model->getById($id, $includeDeleted);
    }

    public function findActiveRule(array $criteria): ?array
    {
        return $this->model->findActiveRule($criteria);
    }

    public function save(array $payload): array
    {
        $id = trim((string) ($payload['id'] ?? ''));
        $data = $this->normalizePayload($payload);

        if ($this->model->existsRuleCode((string) $data[':rule_code'], $id !== '' ? $id : null)) {
            return ['success' => false, 'message' => '이미 사용 중인 규칙코드입니다.'];
        }

        if ($id !== '') {
            $ok = $this->model->update($id, $data);
            return ['success' => $ok, 'id' => $id, 'message' => $ok ? '분개규칙이 저장되었습니다.' : '분개규칙 저장에 실패했습니다.'];
        }

        $data[':id'] = UuidHelper::generate();
        $data[':sort_no'] = SequenceHelper::next('ledger_journal_rules', 'sort_no');
        $data[':created_by'] = ActorHelper::user();
        $ok = $this->model->create($data);

        return ['success' => $ok, 'id' => $data[':id'], 'message' => $ok ? '분개규칙이 생성되었습니다.' : '분개규칙 생성에 실패했습니다.'];
    }

    public function softDelete(string $id): array
    {
        $deleted = $this->model->softDelete($id, ActorHelper::user());

        return [
            'success' => $deleted,
            'message' => $deleted ? '분개규칙이 휴지통으로 이동되었습니다.' : '삭제할 분개규칙을 찾을 수 없습니다.',
            'data' => [
                'deleted_count' => $deleted ? 1 : 0,
                'skipped_count' => $deleted ? 0 : 1,
            ],
        ];
    }

    public function softDeleteBulk(array $ids): array
    {
        $actor = ActorHelper::user();
        $ids = array_values(array_filter(array_map('strval', $ids)));
        $deletedCount = $this->model->softDeleteByIds($ids, $actor);

        return [
            'success' => true,
            'message' => $deletedCount > 0
                ? '선택한 분개규칙이 휴지통으로 이동되었습니다.'
                : '삭제할 분개규칙이 없습니다.',
            'data' => [
                'deleted_count' => $deletedCount,
                'skipped_count' => max(0, count($ids) - $deletedCount),
            ],
        ];
    }

    public function updateStatus(string $id, int $isActive): array
    {
        $ok = $this->model->updateStatus($id, $isActive === 1 ? 1 : 0, ActorHelper::user());

        return [
            'success' => $ok,
            'id' => $id,
            'is_active' => $isActive === 1 ? 1 : 0,
            'message' => $ok ? '사용여부가 변경되었습니다.' : '분개규칙을 찾을 수 없습니다.',
        ];
    }

    public function reorder(array $changes): array
    {
        foreach ($changes as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $sortNo = (int) ($row['newSortNo'] ?? $row['sort_no'] ?? 0);

            if ($id === '' || $sortNo < 1) {
                continue;
            }

            $this->model->updateOrder($id, $sortNo);
        }

        return [
            'success' => true,
            'message' => '분개규칙 순서가 변경되었습니다.',
        ];
    }

    public function restore(string $id): array
    {
        $restored = $this->model->restore($id, ActorHelper::user());

        return [
            'success' => $restored,
            'message' => $restored ? '분개규칙이 복원되었습니다.' : '복원할 분개규칙을 찾을 수 없습니다.',
            'data' => [
                'restored_count' => $restored ? 1 : 0,
                'skipped_count' => $restored ? 0 : 1,
            ],
        ];
    }

    public function restoreBulk(array $ids): array
    {
        $actor = ActorHelper::user();
        $ids = array_values(array_filter(array_map('strval', $ids)));
        $restoredCount = $this->model->restoreByIds($ids, $actor);

        return [
            'success' => true,
            'message' => $restoredCount > 0
                ? '선택한 분개규칙이 복원되었습니다.'
                : '복원할 분개규칙이 없습니다.',
            'data' => [
                'restored_count' => $restoredCount,
                'skipped_count' => max(0, count($ids) - $restoredCount),
            ],
        ];
    }

    public function restoreAll(): array
    {
        $actor = ActorHelper::user();
        $restoredCount = $this->model->restoreAllDeleted($actor);

        return [
            'success' => true,
            'message' => $restoredCount > 0
                ? '휴지통의 분개규칙이 모두 복원되었습니다.'
                : '복원할 분개규칙이 없습니다.',
            'data' => [
                'restored_count' => $restoredCount,
            ],
        ];
    }

    public function hardDelete(string $id): array
    {
        $deleted = $this->model->hardDelete($id);

        return [
            'success' => $deleted,
            'message' => $deleted ? '분개규칙이 영구삭제되었습니다.' : '영구삭제할 분개규칙을 찾을 수 없습니다.',
            'data' => [
                'deleted_count' => $deleted ? 1 : 0,
                'skipped_count' => $deleted ? 0 : 1,
            ],
        ];
    }

    public function hardDeleteBulk(array $ids): array
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        $deletedCount = $this->model->hardDeleteByIds($ids);

        return [
            'success' => true,
            'message' => $deletedCount > 0
                ? '선택한 분개규칙이 영구삭제되었습니다.'
                : '영구삭제할 분개규칙이 없습니다.',
            'data' => [
                'deleted_count' => $deletedCount,
                'skipped_count' => max(0, count($ids) - $deletedCount),
            ],
        ];
    }

    public function hardDeleteAll(): array
    {
        $deletedCount = $this->model->hardDeleteAllDeleted();

        return [
            'success' => true,
            'message' => $deletedCount > 0
                ? '휴지통의 분개규칙이 모두 영구삭제되었습니다.'
                : '영구삭제할 분개규칙이 없습니다.',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ];
    }

    private function normalizePayload(array $payload): array
    {
        $ruleCode = strtoupper(trim((string) ($payload['rule_code'] ?? '')));
        $ruleName = trim((string) ($payload['rule_name'] ?? ''));
        $businessUnit = strtoupper(trim((string) ($payload['business_unit'] ?? '')));
        $direction = strtoupper(trim((string) ($payload['transaction_direction'] ?? '')));
        $clientType = strtoupper(trim((string) ($payload['client_type'] ?? '')));
        $importType = strtoupper(trim((string) ($payload['import_type'] ?? '')));
        $debitAccountId = trim((string) ($payload['debit_account_id'] ?? ''));
        $creditAccountId = trim((string) ($payload['credit_account_id'] ?? ''));
        $vatAccountId = trim((string) ($payload['vat_account_id'] ?? ''));

        if ($ruleCode === '' || $ruleName === '' || $businessUnit === '' || $direction === '' || $clientType === '' || $importType === '' || $debitAccountId === '' || $creditAccountId === '') {
            throw new \InvalidArgumentException('규칙코드, 규칙명, 사업구분, 거래구분, 거래처구분, 자료유형, 차변계정, 대변계정은 필수입니다.');
        }

        if (!in_array($direction, ['PURCHASE', 'SALES', 'IN', 'OUT'], true)) {
            throw new \InvalidArgumentException('거래구분 값이 올바르지 않습니다.');
        }

        return [
            ':rule_code' => $ruleCode,
            ':rule_name' => $ruleName,
            ':business_unit' => $businessUnit,
            ':transaction_direction' => $direction,
            ':client_type' => $clientType,
            ':import_type' => $importType,
            ':debit_account_id' => $debitAccountId,
            ':credit_account_id' => $creditAccountId,
            ':vat_account_id' => $vatAccountId !== '' ? $vatAccountId : null,
            ':description' => trim((string) ($payload['description'] ?? '')),
            ':is_active' => !empty($payload['is_active']) ? 1 : 0,
            ':updated_by' => ActorHelper::user(),
        ];
    }
}
