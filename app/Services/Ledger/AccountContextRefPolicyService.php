<?php

namespace App\Services\Ledger;

use App\Models\Ledger\AccountContextRefPolicyModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class AccountContextRefPolicyService
{
    private AccountContextRefPolicyModel $model;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new AccountContextRefPolicyModel($pdo);
    }

    public function requiredRefTargets(array $context): array
    {
        $normalized = $this->normalizeContext($context);
        $rows = $this->model->matching($normalized);
        $targets = [];
        foreach ($rows as $row) $targets[] = strtoupper((string) $row['ref_target']);
        return array_values(array_unique($targets));
    }

    public function create(array $input): array
    {
        $data = $this->normalizePolicy($input);
        $allowed = $this->model->allowedPolicy($data['account_sub_policy_id']);
        if (!$allowed || (int) $allowed['account_active'] !== 1 || (int) $allowed['is_posting'] !== 1 || $allowed['account_deleted_at'] !== null) {
            throw new \InvalidArgumentException('사용 가능한 계정 보조계정 허용정책이 아닙니다.');
        }
        $this->assertCode('OPERATION_TYPE', $data['operation_type']);
        $this->assertCode('JOURNAL_ACCOUNTING_ROLE', $data['accounting_role_code']);

        $started = !$this->pdo->inTransaction();
        if ($started) $this->pdo->beginTransaction();
        try {
            if ($this->model->overlapping($data, null, true) !== []) {
                throw new \InvalidArgumentException('같은 조건의 적용기간이 중복됩니다.');
            }
            $actor = ActorHelper::user();
            $id = UuidHelper::generate();
            $this->model->create([
                ':id' => $id, ':company_id' => $data['company_id'], ':account_sub_policy_id' => $data['account_sub_policy_id'],
                ':operation_type' => $data['operation_type'], ':accounting_role_code' => $data['accounting_role_code'],
                ':effective_from' => $data['effective_from'], ':effective_to' => $data['effective_to'],
                ':is_active' => $data['is_active'], ':sort_no' => $data['sort_no'], ':created_by' => $actor, ':updated_by' => $actor,
            ]);
            if ($started) $this->pdo->commit();
            return ['success' => true, 'id' => $id];
        } catch (\Throwable $e) {
            if ($started && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function assertAllowedPoliciesDeletable(array $ids): void
    {
        if ($this->model->referencedAllowedPolicyIds($ids) !== []) {
            throw new \InvalidArgumentException('전표 조건부 보조계정 정책에서 사용 중이므로 삭제할 수 없습니다.');
        }
    }

    private function normalizeContext(array $context): array
    {
        $required = ['company_id','account_id','operation_type','accounting_role_code','base_date'];
        foreach ($required as $field) if (trim((string) ($context[$field] ?? '')) === '') throw new \InvalidArgumentException('조건부 보조계정 평가 Context가 완전하지 않습니다.');
        return [
            'company_id' => trim((string) $context['company_id']), 'account_id' => trim((string) $context['account_id']),
            'operation_type' => strtoupper(trim((string) $context['operation_type'])), 'accounting_role_code' => strtoupper(trim((string) $context['accounting_role_code'])),
            'base_date' => substr(trim((string) $context['base_date']), 0, 10),
        ];
    }

    private function normalizePolicy(array $input): array
    {
        $from = substr(trim((string) ($input['effective_from'] ?? '')), 0, 10);
        $to = substr(trim((string) ($input['effective_to'] ?? '')), 0, 10) ?: null;
        $companyId = trim((string) ($input['company_id'] ?? ''));
        $allowedPolicyId = trim((string) ($input['account_sub_policy_id'] ?? ''));
        $operationType = strtoupper(trim((string) ($input['operation_type'] ?? '')));
        $roleCode = strtoupper(trim((string) ($input['accounting_role_code'] ?? '')));
        if ($companyId === '' || $allowedPolicyId === '' || $operationType === '' || $roleCode === '') {
            throw new \InvalidArgumentException('조건부 보조계정 정책의 필수값이 누락되었습니다.');
        }
        if ($from === '' || ($to !== null && $to < $from)) throw new \InvalidArgumentException('정책 적용기간이 올바르지 않습니다.');
        return [
            'company_id' => $companyId, 'account_sub_policy_id' => $allowedPolicyId,
            'operation_type' => $operationType, 'accounting_role_code' => $roleCode,
            'effective_from' => $from, 'effective_to' => $to,
            'is_active' => !empty($input['is_active']) ? 1 : 0, 'sort_no' => max(0, (int) ($input['sort_no'] ?? 0)),
        ];
    }

    private function assertCode(string $group, string $code): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM system_codes WHERE code_group=:group_name AND code=:code AND is_active=1 LIMIT 1');
        $stmt->execute([':group_name' => $group, ':code' => $code]);
        if (!$stmt->fetchColumn()) throw new \InvalidArgumentException('공식 기준코드에 등록되지 않은 정책값입니다.');
    }
}
