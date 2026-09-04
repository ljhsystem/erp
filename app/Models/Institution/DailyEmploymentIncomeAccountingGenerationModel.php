<?php

declare(strict_types=1);

namespace App\Models\Institution;

use PDO;

final class DailyEmploymentIncomeAccountingGenerationModel
{
    private array $tableColumns = [];

    public function __construct(private readonly PDO $db) {}

    public function approvalContextByStep(string $stepId, bool $lock = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT step_row.*,request_row.id approval_request_id,request_row.document_id,request_row.document_type,'
            . 'request_row.status request_status,request_row.current_step '
            . 'FROM user_approval_request_steps step_row '
            . 'JOIN user_approval_requests request_row ON request_row.id=step_row.request_id '
            . 'WHERE step_row.id=:step_id LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([':step_id' => $stepId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function closure(string $documentId, string $approvalId, bool $lock = false): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM institution_daily_employment_income_closures '
            . 'WHERE daily_employment_income_id=:document_id AND approval_request_id=:approval_id LIMIT 1'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([':document_id' => $documentId, ':approval_id' => $approvalId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function accountingLinks(string $closureId, bool $lock = false): array
    {
        $grossExpression = $this->hasColumn('ledger_evidence_daily_employment_income', 'raw_gross_payment_amount')
            ? 'COALESCE(evidence.raw_gross_payment_amount,evidence.total_gross_amount)'
            : 'evidence.total_gross_amount';
        $deductionExpression = $this->hasColumn('ledger_evidence_daily_employment_income', 'raw_worker_deduction_amount')
            ? 'COALESCE(evidence.raw_worker_deduction_amount,evidence.total_deduction_amount)'
            : 'evidence.total_deduction_amount';
        $netExpression = $this->hasColumn('ledger_evidence_daily_employment_income', 'raw_net_payment_amount')
            ? 'COALESCE(evidence.raw_net_payment_amount,evidence.total_net_payment_amount)'
            : 'evidence.total_net_payment_amount';
        $statement = $this->db->prepare(
            'SELECT accounting_link.*,evidence.approval_request_id evidence_approval_request_id,evidence.source_hash evidence_source_hash,'
            . 'evidence.calculation_revision_id evidence_calculation_revision_id,'
            . $grossExpression . ' evidence_gross_payment_amount,'
            . $deductionExpression . ' evidence_worker_deduction_amount,'
            . $netExpression . ' evidence_net_payment_amount,ledger_link.id evidence_link_id '
            . 'FROM institution_daily_employment_income_accounting_links accounting_link '
            . 'JOIN ledger_evidence_daily_employment_income evidence ON evidence.id=accounting_link.evidence_id '
            . "LEFT JOIN ledger_evidence_links ledger_link ON ledger_link.evidence_type='DAILY_EMPLOYMENT_INCOME' "
            . "AND ledger_link.evidence_id=accounting_link.evidence_id AND ledger_link.target_type='TRANSACTION' "
            . 'AND ledger_link.target_id=accounting_link.transaction_id AND ledger_link.deleted_at IS NULL '
            . 'WHERE accounting_link.closure_id=:closure_id '
            . 'ORDER BY accounting_link.daily_employment_income_group_id,accounting_link.daily_employment_income_item_id,accounting_link.artifact_role'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([':closure_id' => $closureId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertClosure(array $row): void { $this->insert('institution_daily_employment_income_closures', $row); }

    public function insertEvidence(array $row): void
    {
        $supported = array_flip($this->columns('ledger_evidence_daily_employment_income'));
        $this->insert('ledger_evidence_daily_employment_income', array_intersect_key($row, $supported));
    }

    public function insertEvidenceRawLine(array $row): void
    {
        $this->insert('ledger_evidence_daily_employment_income_lines', $row);
    }

    public function evidenceRawLines(string $evidenceId, bool $lock = false): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM ledger_evidence_daily_employment_income_lines '
            . 'WHERE evidence_id=:evidence_id ORDER BY sort_no,source_calculation_line_id'
            . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute([':evidence_id' => $evidenceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertAccountingLink(array $row): void { $this->insert('institution_daily_employment_income_accounting_links', $row); }

    public function transactionComposition(string $transactionId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT transaction_row.id,transaction_row.operation_type,transaction_row.status,"
            . "transaction_row.transaction_supply_amount,transaction_row.transaction_settlement_amount,transaction_row.transaction_final_amount,transaction_row.transaction_memo,"
            . "(SELECT COUNT(*) FROM ledger_transaction_items item WHERE item.transaction_id=transaction_row.id) item_count,"
            . "COALESCE((SELECT SUM(item.item_supply_amount) FROM ledger_transaction_items item WHERE item.transaction_id=transaction_row.id),0) item_total,"
            . "COALESCE((SELECT SUM(CASE settlement.amount_sign WHEN 'MINUS' THEN -settlement.amount ELSE settlement.amount END) FROM ledger_transaction_settlements settlement WHERE settlement.transaction_id=transaction_row.id),0) settlement_total,"
            . "(SELECT COUNT(*) FROM ledger_evidence_links evidence_link WHERE evidence_link.evidence_type='DAILY_EMPLOYMENT_INCOME' AND evidence_link.target_type='TRANSACTION' AND evidence_link.target_id=transaction_row.id AND evidence_link.deleted_at IS NULL) evidence_link_count,"
            . "(SELECT COUNT(*) FROM ledger_evidence_links evidence_link WHERE evidence_link.evidence_type IN ('DAILY_EMPLOYMENT_INCOME','DAILY_WORK_REPORT','PAYROLL_WITHHOLDING') AND evidence_link.target_type='TRANSACTION' AND evidence_link.target_id=transaction_row.id AND evidence_link.deleted_at IS NULL) daily_evidence_link_count "
            . 'FROM ledger_transactions transaction_row WHERE transaction_row.id=:transaction_id LIMIT 1'
        );
        $statement->execute([':transaction_id' => $transactionId]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function transactionSettlements(string $transactionId): array
    {
        $statement = $this->db->prepare(
            'SELECT settlement_type,amount_sign,amount,meta_json '
            . 'FROM ledger_transaction_settlements WHERE transaction_id=:transaction_id ORDER BY sort_no,id'
        );
        $statement->execute([':transaction_id' => $transactionId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function completeClosure(string $id, string $actor): void
    {
        $statement = $this->db->prepare(
            "UPDATE institution_daily_employment_income_closures SET status_code='COMPLETED',completed_at=NOW(),updated_by=:actor WHERE id=:id AND status_code='PROCESSING'"
        );
        $statement->execute([':id' => $id, ':actor' => $actor]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('일용근로소득 Closure 완료상태를 저장하지 못했습니다.');
        }
    }

    private function insert(string $table, array $row): void
    {
        $columns = array_keys($row);
        $statement = $this->db->prepare(
            'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (:' . implode(',:', $columns) . ')'
        );
        $statement->execute(array_combine(
            array_map(static fn(string $column): string => ':' . $column, $columns),
            array_values($row)
        ));
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function columns(string $table): array
    {
        if (!isset($this->tableColumns[$table])) {
            $statement = $this->db->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name ORDER BY ORDINAL_POSITION'
            );
            $statement->execute([':table_name' => $table]);
            $this->tableColumns[$table] = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        return $this->tableColumns[$table];
    }
}
