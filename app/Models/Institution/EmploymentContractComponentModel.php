<?php

namespace App\Models\Institution;

use Core\Helpers\UuidHelper;
use PDO;

class EmploymentContractComponentModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function activeForContract(string $contractId, bool $forUpdate = false): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.component_name AS master_component_name
             FROM institution_employment_contracts_components c
             JOIN institution_employment_contracts_pay_components p ON p.id = c.pay_component_id
             WHERE c.contract_id = :contract_id AND c.deleted_at IS NULL
             ORDER BY c.sort_no ASC, c.id ASC' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([':contract_id' => $contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function replace(string $contractId, array $rows, string $actor): void
    {
        $this->db->prepare(
            'DELETE FROM institution_employment_contracts_components WHERE contract_id = :contract_id'
        )->execute([':contract_id' => $contractId]);

        $stmt = $this->db->prepare(
            'INSERT INTO institution_employment_contracts_components
             (id, sort_no, contract_id, pay_component_id,
              component_type, component_code, component_name, calculation_type,
              amount, rate, quantity, base_component_code, work_type,
              premium_rate, excess_payment_policy, agreement_basis,
              tax_type, tax_policy_code, payment_cycle, is_fixed,
              minimum_wage_treatment, ordinary_wage_treatment, average_wage_treatment,
              wage_treatment_basis, note, created_at, created_by, updated_at, updated_by)
             VALUES
             (:id, :sort_no, :contract_id, :pay_component_id,
              :component_type, :component_code, :component_name, :calculation_type,
              :amount, :rate, :quantity, :base_component_code, :work_type,
              :premium_rate, :excess_payment_policy, :agreement_basis,
              :tax_type, :tax_policy_code, :payment_cycle, :is_fixed,
              :minimum_wage_treatment, :ordinary_wage_treatment, :average_wage_treatment,
              :wage_treatment_basis, :note, NOW(), :created_actor, NOW(), :updated_actor)'
        );
        foreach ($rows as $index => $row) {
            $stmt->execute([
                ':id' => UuidHelper::generate(),
                ':sort_no' => $index + 1,
                ':contract_id' => $contractId,
                ':pay_component_id' => $row['pay_component_id'],
                ':component_type' => $row['component_type'],
                ':component_code' => $row['component_code'],
                ':component_name' => $row['component_name'],
                ':calculation_type' => $row['calculation_type'],
                ':amount' => $row['amount'],
                ':rate' => $row['rate'],
                ':quantity' => $row['quantity'],
                ':base_component_code' => $row['base_component_code'],
                ':work_type' => $row['work_type'],
                ':premium_rate' => $row['premium_rate'],
                ':excess_payment_policy' => $row['excess_payment_policy'],
                ':agreement_basis' => $row['agreement_basis'],
                ':tax_type' => $row['tax_type'],
                ':tax_policy_code' => $row['tax_policy_code'],
                ':payment_cycle' => $row['payment_cycle'],
                ':is_fixed' => $row['is_fixed'],
                ':minimum_wage_treatment' => $row['minimum_wage_treatment'],
                ':ordinary_wage_treatment' => $row['ordinary_wage_treatment'],
                ':average_wage_treatment' => $row['average_wage_treatment'],
                ':wage_treatment_basis' => $row['wage_treatment_basis'],
                ':note' => $row['note'],
                ':created_actor' => $actor,
                ':updated_actor' => $actor,
            ]);
        }
    }

    public function countForContract(string $contractId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM institution_employment_contracts_components
             WHERE contract_id = :contract_id'
        );
        $stmt->execute([':contract_id' => $contractId]);
        return (int) $stmt->fetchColumn();
    }

    public function purgeForContract(string $contractId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM institution_employment_contracts_components WHERE contract_id = :contract_id'
        );
        $stmt->execute([':contract_id' => $contractId]);
    }
}
