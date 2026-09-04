<?php

namespace App\Services\Ledger;

use App\Models\Ledger\JournalRuleModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class JournalRuleService
{
    private JournalRuleModel $model;
    private LoggerInterface $logger;

    public function __construct(private PDO $pdo)
    {
        $this->model = new JournalRuleModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger-journal-rule');
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
        return $this->logged('JOURNAL_RULE_SAVED','save',['target_id'=>trim((string)($payload['id']??''))?:null],fn():array=>$this->saveInternal($payload));
    }

    private function saveInternal(array $payload): array
    {
        if (array_key_exists('accounting_role_code', $payload) || array_key_exists('account_id', $payload)) {
            return $this->saveRoleRule($payload);
        }
        throw new \InvalidArgumentException('신규 분개규칙은 역할형 저장계약을 사용해야 합니다.');
    }

    private function saveRoleRule(array $payload): array
    {
        if (!$this->model->supportsRoleOnlyWrite()) {
            throw new \RuntimeException('레거시 복합계정 컬럼의 NOT NULL 제약 때문에 역할형 규칙 저장을 시작할 수 없습니다.');
        }
        $id = trim((string) ($payload['id'] ?? ''));
        $companyId = $this->model->resolveCompanyId();
        if (isset($payload['company_id']) && trim((string) $payload['company_id']) !== '' && trim((string) $payload['company_id']) !== $companyId) {
            throw new \InvalidArgumentException('회사 범위가 올바르지 않습니다.');
        }
        if ($id !== '') {
            $existing = $this->model->getById($id);
            if ($existing === null) {
                throw new \InvalidArgumentException('수정할 분개규칙을 찾을 수 없습니다.');
            }
            if (strtoupper((string) ($existing['origin_code'] ?? '')) === 'SYSTEM') {
                throw new \InvalidArgumentException('SYSTEM 분개규칙은 일반 저장 API에서 수정할 수 없습니다.');
            }
        }
        $row = $this->normalizeRolePayload($payload, $companyId);
        if ($this->model->existsRuleCode((string) $row['rule_code'], $id !== '' ? $id : null)) {
            throw new \InvalidArgumentException('이미 사용 중인 규칙코드입니다.');
        }
        $conflict = $this->model->roleConditionConflict($companyId, (string) $row['condition_hash'], (string) $row['accounting_role_code'], (string) $row['debit_credit'], $id !== '' ? $id : null);
        if ($conflict !== null) {
            throw new \InvalidArgumentException('동일 조건과 회계역할의 분개규칙이 존재합니다.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $revisionService = new JournalRuleRevisionService($this->pdo);
            if ($id !== '') {
                $revisionService->change($companyId, $id, 'UPDATE', function () use ($id, $companyId, $row): void {
                    $this->model->updateRoleRule($id, $companyId, $row);
                }, trim((string) ($payload['change_reason'] ?? '')) ?: '역할형 분개규칙 수정');
            } else {
                $id = UuidHelper::generate();
                $row['id'] = $id;
                $row['sort_no'] = SequenceHelper::next('ledger_journal_rules', 'sort_no');
                $row['revision_no'] = 0;
                $row['created_by'] = ActorHelper::user();
                $this->model->createRoleRule($row);
                $revisionService->change($companyId, $id, 'CREATE', static function (): void {
                }, trim((string) ($payload['change_reason'] ?? '')) ?: '역할형 분개규칙 생성');
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return ['success' => true, 'id' => $id, 'message' => '분개규칙이 저장되었습니다.'];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function normalizeRolePayload(array $payload, string $companyId): array
    {
        $condition = [
            'company_id' => $companyId,
            'business_unit' => strtoupper(trim((string) ($payload['business_unit'] ?? 'CONSTRUCTION'))) ?: 'CONSTRUCTION',
            'operation_type' => strtoupper(trim((string) ($payload['operation_type'] ?? ''))),
            'transaction_direction' => strtoupper(trim((string) ($payload['transaction_direction'] ?? 'OUT'))) ?: 'OUT',
            'client_type' => strtoupper(trim((string) ($payload['client_type'] ?? ''))),
            'import_type' => strtoupper(trim((string) ($payload['import_type'] ?? ''))),
            'source_type' => strtoupper(trim((string) ($payload['source_type'] ?? ''))),
            'source_line_type' => strtoupper(trim((string) ($payload['source_line_type'] ?? ''))),
            'item_code' => strtoupper(trim((string) ($payload['item_code'] ?? ''))),
        ];
        $roleCode = strtoupper(trim((string) ($payload['accounting_role_code'] ?? '')));
        $side = strtoupper(trim((string) ($payload['debit_credit'] ?? '')));
        $accountId = trim((string) ($payload['account_id'] ?? ''));
        $ruleCode = strtoupper(trim((string) ($payload['rule_code'] ?? '')));
        $ruleName = trim((string) ($payload['rule_name'] ?? ''));
        if ($ruleCode === '' || $ruleName === '' || $condition['operation_type'] === '' || $condition['import_type'] === '' || $roleCode === '' || !in_array($side, ['DEBIT','CREDIT'], true) || $accountId === '') {
            throw new \InvalidArgumentException('규칙코드, 규칙명, 업무유형, 자료유형, 회계역할, 차대구분과 계정과목은 필수입니다.');
        }
        if (!$this->model->usableAccountExists($accountId)) {
            throw new \InvalidArgumentException('사용할 수 없는 계정과목입니다.');
        }
        if (!$this->model->activeCodeExists('JOURNAL_ACCOUNTING_ROLE', $roleCode)) {
            throw new \InvalidArgumentException('유효한 회계역할 코드가 아닙니다.');
        }
        foreach (['OPERATION_TYPE' => $condition['operation_type'], 'IMPORT_TYPE' => $condition['import_type']] as $group => $code) {
            if (!$this->model->activeCodeExists($group, $code)) {
                throw new \InvalidArgumentException('분개규칙 조건값이 올바르지 않습니다.');
            }
        }
        if ($condition['operation_type'] === 'PERSONAL_EXPENSE') {
            if ($condition['import_type'] !== 'EMPLOYEE_EXPENSE_PERSONAL' || $condition['source_type'] !== 'PERSONAL_EXPENSE_ITEM' || $condition['source_line_type'] !== 'ITEM') {
                throw new \InvalidArgumentException('개인경비 원천 조건이 공식 저장계약과 다릅니다.');
            }
            if ($condition['item_code'] !== '' && !$this->model->activeCodeExists('PERSONAL_EXPENSE_CATEGORY', $condition['item_code'])) {
                throw new \InvalidArgumentException('유효한 개인경비 분류 코드가 아닙니다.');
            }
        }
        $effectiveFrom = trim((string) ($payload['effective_from'] ?? '')) ?: null;
        $effectiveTo = trim((string) ($payload['effective_to'] ?? '')) ?: null;
        if ($effectiveFrom !== null && !$this->validDate($effectiveFrom) || $effectiveTo !== null && !$this->validDate($effectiveTo) || $effectiveFrom !== null && $effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw new \InvalidArgumentException('적용기간이 올바르지 않습니다.');
        }
        ksort($condition);
        $status = strtoupper(trim((string) ($payload['rule_status'] ?? 'INACTIVE')));
        if (!in_array($status, ['ACTIVE','CANDIDATE','INACTIVE','REJECTED'], true)) {
            throw new \InvalidArgumentException('분개규칙 상태가 올바르지 않습니다.');
        }
        $originCode = strtoupper(trim((string) ($payload['origin_code'] ?? 'USER'))) ?: 'USER';
        if (!in_array($originCode, ['USER', 'SYSTEM'], true)) {
            throw new \InvalidArgumentException('분개규칙 생성출처가 올바르지 않습니다.');
        }
        if ($originCode === 'SYSTEM') {
            throw new \InvalidArgumentException('SYSTEM 분개규칙은 일반 저장 API에서 생성하거나 수정할 수 없습니다.');
        }
        $amountPolicyCode = strtoupper(trim((string) ($payload['amount_policy_code'] ?? 'SOURCE_AMOUNT'))) ?: 'SOURCE_AMOUNT';
        if (!in_array($amountPolicyCode, ['SOURCE_AMOUNT', 'PLANNED_AMOUNT'], true)) {
            throw new \InvalidArgumentException('금액정책 코드가 올바르지 않습니다.');
        }
        $actor = ActorHelper::user();
        return [
            'company_id' => $companyId,
            'rule_code' => $ruleCode,
            'rule_name' => $ruleName,
            'business_unit' => $condition['business_unit'],
            'operation_type' => $condition['operation_type'],
            'transaction_direction' => $condition['transaction_direction'],
            'client_type' => $condition['client_type'] !== '' ? $condition['client_type'] : null,
            'import_type' => $condition['import_type'],
            'source_type' => $condition['source_type'] !== '' ? $condition['source_type'] : null,
            'source_line_type' => $condition['source_line_type'] !== '' ? $condition['source_line_type'] : null,
            'item_code' => $condition['item_code'] !== '' ? $condition['item_code'] : null,
            'condition_hash' => hash('sha256', json_encode($condition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'origin_code' => $originCode,
            'rule_status' => $status,
            'accounting_role_code' => $roleCode,
            'debit_credit' => $side,
            'account_id' => $accountId,
            'amount_policy_code' => $amountPolicyCode,
            'is_locked' => !empty($payload['user_locked']) || !empty($payload['is_locked']) ? 1 : 0,
            'auto_apply_enabled' => !empty($payload['auto_apply_enabled']) ? 1 : 0,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'priority_no' => max(1, (int) ($payload['priority_no'] ?? 100)),
            'description' => trim((string) ($payload['description'] ?? '')) ?: null,
            'is_active' => $status === 'ACTIVE' ? 1 : 0,
            'updated_by' => $actor,
            'debit_account_id' => null,
            'credit_account_id' => null,
            'vat_account_id' => null,
        ];
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function saveLegacy(array $payload): array
    {
        $id = trim((string) ($payload['id'] ?? ''));
        $data = $this->normalizePayload($payload);

        if ($this->model->existsRuleCode((string) $data[':rule_code'], $id !== '' ? $id : null)) {
            return ['success' => false, 'message' => '이미 사용 중인 규칙코드입니다.'];
        }

        $conflict = $this->model->conditionConflict($data, $id !== '' ? $id : null);
        if ($conflict !== null) {
            $sameAccounts = (string) $conflict['debit_account_id'] === (string) $data[':debit_account_id']
                && (string) $conflict['credit_account_id'] === (string) $data[':credit_account_id']
                && (string) ($conflict['vat_account_id'] ?? '') === (string) ($data[':vat_account_id'] ?? '');
            return ['success' => false, 'message' => $sameAccounts
                ? '동일한 조건과 계정 조합의 분개규칙이 존재합니다.'
                : '동일한 조건에서 다른 계정을 추천하는 분개규칙이 존재합니다.'];
        }

        if ($id !== '') {
            $ok = $this->model->update($id, $data);
            return [
                'success' => $ok,
                'id' => $id,
                'message' => $ok ? '분개규칙이 저장되었습니다.' : '수정 중 오류가 발생했습니다.',
            ];
        }

        $data[':id'] = UuidHelper::generate();
        $data[':sort_no'] = SequenceHelper::next('ledger_journal_rules', 'sort_no');
        $data[':created_by'] = ActorHelper::user();
        $ok = $this->model->create($data);

        return [
            'success' => $ok,
            'id' => $data[':id'],
            'message' => $ok ? '분개규칙이 생성되었습니다.' : '저장 중 오류가 발생했습니다.',
        ];
    }

    public function softDelete(string $id): array
    {
        return $this->logged('JOURNAL_RULE_DELETED','delete',['target_id'=>$id],fn():array=>$this->softDeleteInternal($id),true);
    }

    private function softDeleteInternal(string $id): array
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
        return $this->logged('JOURNAL_RULE_BULK_DELETED','delete-bulk',['requested_count'=>count($ids)],fn():array=>$this->softDeleteBulkInternal($ids),true);
    }

    private function softDeleteBulkInternal(array $ids): array
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
        return $this->logged('JOURNAL_RULE_STATUS_UPDATED','update-status',['target_id'=>$id,'is_active'=>$isActive],fn():array=>$this->updateStatusInternal($id,$isActive));
    }

    private function updateStatusInternal(string $id, int $isActive): array
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
        return $this->logged('JOURNAL_RULE_REORDERED','reorder',['requested_count'=>count($changes)],fn():array=>$this->reorderInternal($changes));
    }

    private function reorderInternal(array $changes): array
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
        return $this->logged('JOURNAL_RULE_RESTORED','restore',['target_id'=>$id],fn():array=>$this->restoreInternal($id));
    }

    private function restoreInternal(string $id): array
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
        return $this->logged('JOURNAL_RULE_BULK_RESTORED','restore-bulk',['requested_count'=>count($ids)],fn():array=>$this->restoreBulkInternal($ids));
    }

    private function restoreBulkInternal(array $ids): array
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
        return $this->logged('JOURNAL_RULE_PURGED','purge',['target_id'=>$id],fn():array=>$this->hardDeleteInternal($id),true);
    }

    private function hardDeleteInternal(string $id): array
    {
        if ($this->model->referencedRuleIds([$id]) !== []) {
            return ['success' => false, 'message' => '사용된 분개규칙은 영구삭제할 수 없습니다.', 'data' => ['deleted_count' => 0, 'skipped_count' => 1]];
        }
        $deleted = $this->model->hardDelete($id);

        return [
            'success' => $deleted,
            'message' => $deleted ? '분개규칙이 영구 삭제되었습니다.' : '영구 삭제할 분개규칙을 찾을 수 없습니다.',
            'data' => [
                'deleted_count' => $deleted ? 1 : 0,
                'skipped_count' => $deleted ? 0 : 1,
            ],
        ];
    }

    public function hardDeleteBulk(array $ids): array
    {
        return $this->logged('JOURNAL_RULE_BULK_PURGED','purge-bulk',['requested_count'=>count($ids)],fn():array=>$this->hardDeleteBulkInternal($ids),true);
    }

    private function hardDeleteBulkInternal(array $ids): array
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        $referenced = $this->model->referencedRuleIds($ids);
        $deletable = array_values(array_diff($ids, $referenced));
        $deletedCount = $this->model->hardDeleteByIds($deletable);

        return [
            'success' => true,
            'message' => $deletedCount > 0
                ? '선택한 분개규칙이 영구 삭제되었습니다.'
                : '영구 삭제할 분개규칙이 없습니다.',
            'data' => [
                'deleted_count' => $deletedCount,
                'skipped_count' => max(0, count($ids) - $deletedCount),
            ],
        ];
    }

    public function hardDeleteAll(): array
    {
        $trashIds = array_column($this->model->getList([], true), 'id');
        return $this->hardDeleteBulk($trashIds);
    }

    private function normalizePayload(array $payload): array
    {
        $ruleCode = strtoupper(trim((string) ($payload['rule_code'] ?? '')));
        $ruleName = trim((string) ($payload['rule_name'] ?? ''));
        $businessUnit = strtoupper(trim((string) ($payload['business_unit'] ?? 'CONSTRUCTION'))) ?: 'CONSTRUCTION';
        $operationType = strtoupper(trim((string) ($payload['operation_type'] ?? 'GENERAL'))) ?: 'GENERAL';
        $direction = strtoupper(trim((string) ($payload['transaction_direction'] ?? '')));
        $clientType = strtoupper(trim((string) ($payload['client_type'] ?? '')));
        $importType = strtoupper(trim((string) ($payload['import_type'] ?? '')));
        $debitAccountId = trim((string) ($payload['debit_account_id'] ?? ''));
        $creditAccountId = trim((string) ($payload['credit_account_id'] ?? ''));
        $vatAccountId = trim((string) ($payload['vat_account_id'] ?? ''));

        if ($ruleCode === '' || $ruleName === '' || $direction === '' || $importType === '' || $debitAccountId === '' || $creditAccountId === '') {
            throw new \InvalidArgumentException('규칙코드, 규칙명, 거래구분, 자료유형, 차변계정, 대변계정은 필수입니다.');
        }

        if (!in_array($direction, ['PURCHASE', 'SALES', 'IN', 'OUT'], true)) {
            throw new \InvalidArgumentException('거래구분 값이 올바르지 않습니다.');
        }

        foreach ([
            'BUSINESS_UNIT' => $businessUnit,
            'OPERATION_TYPE' => $operationType,
            'TRANSACTION_DIRECTION' => $direction,
            'IMPORT_TYPE' => $importType,
        ] as $group => $code) {
            if (!$this->model->activeCodeExists($group, $code)) {
                throw new \InvalidArgumentException('분개규칙 조건값이 올바르지 않습니다.');
            }
        }
        if ($clientType !== '' && !$this->model->activeCodeExists('CLIENT_TYPE', $clientType)) {
            throw new \InvalidArgumentException('거래처유형 값이 올바르지 않습니다.');
        }
        foreach (['차변계정' => $debitAccountId, '대변계정' => $creditAccountId, '부가세계정' => $vatAccountId] as $label => $accountId) {
            if ($accountId === '') continue;
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $accountId)) {
                throw new \InvalidArgumentException("{$label} ID가 올바르지 않습니다.");
            }
            if (!$this->model->usableAccountExists($accountId)) {
                throw new \InvalidArgumentException("사용할 수 없는 {$label}입니다.");
            }
        }

        return [
            ':rule_code' => $ruleCode,
            ':rule_name' => $ruleName,
            ':business_unit' => $businessUnit,
            ':operation_type' => $operationType,
            ':transaction_direction' => $direction,
            ':client_type' => $clientType !== '' ? $clientType : null,
            ':import_type' => $importType,
            ':debit_account_id' => $debitAccountId,
            ':credit_account_id' => $creditAccountId,
            ':vat_account_id' => $vatAccountId !== '' ? $vatAccountId : null,
            ':description' => trim((string) ($payload['description'] ?? '')),
            ':is_active' => !empty($payload['is_active']) ? 1 : 0,
            ':updated_by' => ActorHelper::user(),
        ];
    }

    private function logged(string$event,string$action,array$context,callable$operation,bool$warning=false):array
    {
        $base=['service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user()]+$context;try{$result=$operation();$payload=['event_code'=>$event,'result'=>'SUCCESS']+$base;if($warning)$this->logger->warning('분개규칙 업무 처리가 완료되었습니다.',$payload);else$this->logger->info('분개규칙 업무 처리가 완료되었습니다.',$payload);return$result;}catch(\PDOException$e){$this->logger->error('분개규칙 업무 처리에 실패했습니다.',['event_code'=>$event.'_FAILED','result'=>'FAILED','error_code'=>get_class($e),'error'=>$e]+$base);throw$e;}catch(\InvalidArgumentException|\DomainException|\RuntimeException$e){$this->logger->warning('분개규칙 업무 처리가 차단되었습니다.',['event_code'=>$event.'_BLOCKED','result'=>'BLOCKED','error_code'=>get_class($e),'error'=>$e]+$base);throw$e;}catch(\Throwable$e){$this->logger->error('분개규칙 업무 처리에 실패했습니다.',['event_code'=>$event.'_FAILED','result'=>'FAILED','error_code'=>get_class($e),'error'=>$e]+$base);throw$e;}
    }
}
