<?php

namespace App\Models\Institution;

use Core\Helpers\UuidHelper;
use PDO;

class EmploymentContractWorkSchedulePolicyModel
{
    public function __construct(private readonly PDO $db) {}

    public function forContract(string $contractId, bool $forUpdate = false): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM institution_employment_contracts_work_schedule_policies
             WHERE contract_id = :contract_id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':contract_id' => $contractId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function replace(string $contractId, ?array $policy, string $actor): void
    {
        if ($policy === null) {
            $this->db->prepare(
                'DELETE FROM institution_employment_contracts_work_schedule_policies WHERE contract_id = :contract_id'
            )->execute([':contract_id' => $contractId]);
            return;
        }
        $policy = $this->bindPolicy($policy);
        if ($this->forContract($contractId, true)) {
            $stmt = $this->db->prepare(
                'UPDATE institution_employment_contracts_work_schedule_policies SET
                 settlement_period_days = :settlement_period_days,
                 reference_weekly_hours = :reference_weekly_hours,
                 selectable_start_time = :selectable_start_time, selectable_end_time = :selectable_end_time,
                 core_start_time = :core_start_time, core_end_time = :core_end_time,
                 policy_detail = :policy_detail, updated_at = NOW(), updated_by = :updated_by
                 WHERE contract_id = :contract_id'
            );
            $stmt->execute($policy + [':contract_id' => $contractId, ':updated_by' => $actor]);
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO institution_employment_contracts_work_schedule_policies
             (id, contract_id, settlement_period_days, reference_weekly_hours,
              selectable_start_time, selectable_end_time, core_start_time, core_end_time,
              policy_detail, created_at, created_by, updated_at, updated_by)
             VALUES (:id, :contract_id, :settlement_period_days,
              :reference_weekly_hours, :selectable_start_time, :selectable_end_time,
              :core_start_time, :core_end_time, :policy_detail, NOW(), :created_by, NOW(), :updated_by)'
        );
        $stmt->execute($policy + [
            ':id' => UuidHelper::generate(), ':contract_id' => $contractId,
            ':created_by' => $actor, ':updated_by' => $actor,
        ]);
    }

    private function bindPolicy(array $policy): array
    {
        $result = [];
        foreach ([
            'settlement_period_days', 'reference_weekly_hours',
            'selectable_start_time', 'selectable_end_time', 'core_start_time',
            'core_end_time', 'policy_detail',
        ] as $column) {
            $result[':' . $column] = $policy[':' . $column] ?? $policy[$column] ?? null;
        }
        return $result;
    }
}
