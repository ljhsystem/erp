<?php

namespace App\Models\Institution;

use PDO;

final class RegularEmploymentIncomeAccountingGenerationModel
{
    public function __construct(private readonly PDO $db) {}

    public function findByRequestKey(string $requestKey, bool $lock = false): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_regular_employment_income_accounting_links WHERE request_key=:request_key LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':request_key' => $requestKey]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertRegistry(array $row): void
    {
        $this->insert('institution_regular_employment_income_accounting_links', $row);
    }

    public function approvalContextByStep(string $stepId, bool $lock = false): ?array
    {
        $statement = $this->db->prepare("SELECT step.*,request.document_id,request.document_type,
                request.status request_status,request.current_step,request.id approval_request_id
            FROM user_approval_request_steps step
            JOIN user_approval_requests request ON request.id=step.request_id
            WHERE step.id=:step_id LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':step_id' => $stepId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function approvalContextByRequest(string $requestId, bool $lock = false): ?array
    {
        $statement = $this->db->prepare('SELECT id,document_id,document_type,status,current_step FROM user_approval_requests WHERE id=:request_id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':request_id' => $requestId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function registriesForDocument(string $documentId, bool $lock = false): array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=:document_id ORDER BY generation_role,aggregation_key' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([':document_id' => $documentId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function basesForItem(string $itemId): array
    {
        $statement = $this->db->prepare('SELECT * FROM institution_regular_employment_income_calculation_bases WHERE regular_employment_income_item_id=:item_id ORDER BY basis_type_code,basis_code,id');
        $statement->execute([':item_id' => $itemId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function insert(string $table, array $row): void
    {
        $columns = array_keys($row);
        $statement = $this->db->prepare('INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')');
        $statement->execute(array_combine(array_map(static fn(string $column): string => ':' . $column, $columns), array_values($row)));
    }
}
