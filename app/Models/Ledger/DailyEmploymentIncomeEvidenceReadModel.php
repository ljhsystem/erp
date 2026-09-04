<?php

declare(strict_types=1);

namespace App\Models\Ledger;

use Core\Helpers\ActorHelper;
use PDO;

final class DailyEmploymentIncomeEvidenceReadModel
{
    public const EVIDENCE_TYPE = 'DAILY_EMPLOYMENT_INCOME';

    public function __construct(private readonly PDO $pdo, private readonly EvidenceSchemaModel $schema) {}

    public function findList(string $status = '', string $requestedId = ''): array
    {
        if (in_array($status, ['ERROR', 'DUPLICATED', 'DELETED'], true)) {
            return [];
        }
        $where = ['1=1'];
        $params = [];
        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci=:requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }
        if ($status === 'READY') {
            $where[] = 'transaction_link.target_id IS NULL';
        } elseif ($status === 'PROCESSED') {
            $where[] = 'transaction_link.target_id IS NOT NULL';
        }

        $businessUnit = $this->fallback('business_unit', 'income_group.business_unit');
        $direction = $this->fallback('transaction_direction', "'EXPENSE'", true);
        $operationType = $this->fallback('operation_type', "'DAILY_WORKER'", true);
        $incomeYearMonth = $this->fallback('raw_income_year_month', 'body.income_year_month', true);
        $workDate = "COALESCE((SELECT MAX(workday.work_date) FROM institution_daily_employment_income_workdays workday WHERE workday.daily_employment_income_item_id=body.daily_employment_income_item_id),transaction_row.transaction_date)";
        $workDayCount = $this->fallback('raw_work_day_count', 'body.total_work_days');
        $gross = $this->fallback('raw_gross_payment_amount', 'body.total_gross_amount');
        $deduction = $this->fallback('raw_worker_deduction_amount', 'body.total_deduction_amount');
        $net = $this->fallback('raw_net_payment_amount', 'body.total_net_payment_amount');
        $employerBurden = $this->fallback('raw_employer_burden_amount', 'body.total_employer_burden_amount');
        $evidenceStatus = $this->fallback('evidence_status', 'body.evidence_status_code', true);

        $statement = $this->pdo->prepare("SELECT
                body.id evidence_id,body.id,body.source_daily_employment_income_id,body.daily_employment_income_group_id,
                body.daily_employment_income_item_id,body.approval_request_id,body.calculation_revision_id,
                body.sort_no,body.external_key,body.worker_client_id,body.work_scope_code,body.project_id,
                body.employee_id,body.bank_account_id,body.card_id,
                body.raw_business_unit,body.raw_project_id,body.raw_work_team_id,
                {$incomeYearMonth} raw_income_year_month,
                {$incomeYearMonth} income_year_month,
                {$businessUnit} business_unit,{$workDayCount} raw_work_day_count,
                {$gross} raw_gross_payment_amount,{$deduction} raw_worker_deduction_amount,
                {$net} raw_net_payment_amount,{$employerBurden} raw_employer_burden_amount,
                {$workDayCount} work_day_count,{$gross} gross_payment_amount,
                {$deduction} worker_deduction_amount,{$net} net_payment_amount,
                {$employerBurden} employer_burden_amount,
                {$workDayCount} total_work_days,{$gross} total_gross_amount,
                {$deduction} total_deduction_amount,{$net} total_net_payment_amount,
                {$employerBurden} total_employer_burden_amount,
                COALESCE((SELECT SUM(workday.actual_work_minutes)
                    FROM institution_daily_employment_income_workdays workday
                    WHERE workday.daily_employment_income_item_id=body.daily_employment_income_item_id),0) total_work_minutes,
                {$evidenceStatus} evidence_status,
                body.approved_at,body.approved_by,body.created_at,body.created_by,body.updated_at,body.updated_by,
                body.deleted_at,body.deleted_by,
                body.source_type,body.import_type,'DATA' evidence_type,
                '승인문서' source_type_name,'일용직(신고)' import_type_name,
                {$workDate} evidence_date,{$workDate} standard_date,{$workDate} transaction_date,
                {$gross} pre_tax_amount,{$net} post_tax_amount,{$net} display_amount,
                COALESCE(NULLIF(header.document_title,''),CONCAT(header.income_year_month,' 일용근로소득')) description,
                body.client_id,body.work_team_id,
                COALESCE(NULLIF(worker.client_name,''),'') client_name,
                COALESCE(NULLIF(worker.client_name,''),'') worker_client_name,
                COALESCE(NULLIF(item.worker_name_snapshot,''),NULLIF(worker.client_name,''),'') worker_name,
                COALESCE(NULLIF(project.project_name,''),'') project_name,
                COALESCE(NULLIF(work_team.team_name,''),'') work_team_name,
                COALESCE(NULLIF(work_team.team_name,''),'') team_name,
                COALESCE(NULLIF(business_unit_code.code_name,''),{$businessUnit},'') business_unit_name,
                COALESCE(NULLIF(header.document_title,''),'') source_daily_employment_income_name,
                COALESCE(NULLIF(income_group.work_description,''),'') daily_employment_income_group_name,
                CASE WHEN approval_request.sort_no IS NULL THEN '' ELSE CONCAT('#',approval_request.sort_no) END approval_request_name,
                CASE WHEN calculation_revision.revision_no IS NULL THEN '' ELSE CONCAT('#',calculation_revision.revision_no) END calculation_revision_name,
                transaction_link.id evidence_link_id,transaction_link.target_id transaction_id,
                CASE WHEN transaction_link.id IS NULL THEN 'NOT_LINKED' ELSE 'LINKED' END transaction_link_status,
                CASE WHEN transaction_link.target_id IS NULL THEN 'READY' ELSE 'PROCESSED' END transaction_status,
                CASE WHEN transaction_link.target_id IS NULL THEN 'READY' ELSE 'PROCESSED' END process_status,
                CASE WHEN transaction_link.target_id IS NULL THEN 'READY' ELSE 'PROCESSED' END status,
                {$direction} transaction_direction,{$operationType} operation_type,operation_code.code_name operation_type_name,
                transaction_row.transaction_supply_amount,transaction_row.transaction_settlement_amount,
                transaction_row.transaction_final_amount,
                body.created_by created_by_name,body.updated_by updated_by_name,body.approved_by approved_by_name
            FROM ledger_evidence_daily_employment_income body
            JOIN institution_daily_employment_incomes header ON header.id=body.source_daily_employment_income_id
            JOIN institution_daily_employment_income_groups income_group ON income_group.id=body.daily_employment_income_group_id
            JOIN institution_daily_employment_income_items item ON item.id=body.daily_employment_income_item_id
            JOIN system_clients worker ON worker.id=body.worker_client_id
            LEFT JOIN system_projects project ON project.id=body.project_id
            LEFT JOIN system_work_teams work_team ON work_team.id=body.work_team_id
            LEFT JOIN system_codes business_unit_code
              ON business_unit_code.code_group='BUSINESS_UNIT' AND business_unit_code.code={$businessUnit}
            LEFT JOIN user_approval_requests approval_request ON approval_request.id=body.approval_request_id
            LEFT JOIN institution_daily_employment_income_calculation_revisions calculation_revision
              ON calculation_revision.id=body.calculation_revision_id
            LEFT JOIN ledger_evidence_links transaction_link
              ON transaction_link.id=(
                  SELECT candidate.id FROM ledger_evidence_links candidate
                  WHERE candidate.evidence_id=body.id AND candidate.target_type='TRANSACTION'
                    AND candidate.deleted_at IS NULL
                    AND candidate.evidence_type IN ('DAILY_EMPLOYMENT_INCOME','DAILY_WORK_REPORT','PAYROLL_WITHHOLDING')
                  ORDER BY CASE candidate.evidence_type WHEN 'DAILY_EMPLOYMENT_INCOME' THEN 0 ELSE 1 END,candidate.created_at,candidate.id
                  LIMIT 1
              )
            LEFT JOIN ledger_transactions transaction_row ON transaction_row.id=transaction_link.target_id
            LEFT JOIN system_codes operation_code
              ON operation_code.code_group='OPERATION_TYPE' AND operation_code.code={$operationType}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$workDate} DESC,body.created_at DESC,body.id");
        $statement->execute($params);
        return ActorHelper::enrichActorNames($statement->fetchAll(PDO::FETCH_ASSOC) ?: [], [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'approved_by_name' => 'approved_by',
        ]);
    }

    public function findCount(string $status = '', string $requestedId = ''): int
    {
        if (in_array($status, ['ERROR', 'DUPLICATED', 'DELETED'], true)) {
            return 0;
        }
        $where = ['1=1'];
        $params = [];
        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci=:requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }
        $linkExists = "EXISTS (SELECT 1 FROM ledger_evidence_links evidence_link
            WHERE evidence_link.evidence_id=body.id AND evidence_link.target_type='TRANSACTION'
              AND evidence_link.deleted_at IS NULL
              AND evidence_link.evidence_type IN ('DAILY_EMPLOYMENT_INCOME','DAILY_WORK_REPORT','PAYROLL_WITHHOLDING'))";
        if ($status === 'READY') {
            $where[] = 'NOT ' . $linkExists;
        } elseif ($status === 'PROCESSED') {
            $where[] = $linkExists;
        }
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ledger_evidence_daily_employment_income body WHERE ' . implode(' AND ', $where)
        );
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }

    public function findById(string $id): ?array
    {
        $row = $this->findList('', $id)[0] ?? null;
        if ($row === null) return null;
        $row['raw_lines'] = $this->rawLines($id);
        return $row;
    }

    private function rawLines(string $evidenceId): array
    {
        if (!$this->schema->tableExists('ledger_evidence_daily_employment_income_lines')) return [];
        $statement = $this->pdo->prepare(
            'SELECT id,sort_no,source_calculation_line_id,calculation_revision_id,line_type_code,line_code,'
            . 'line_name_snapshot,burden_subject_code,application_status_code,taxability_code,'
            . 'raw_calculation_basis_amount,raw_calculation_rate,raw_calculation_before_rounding,'
            . 'raw_calculated_amount,raw_adjustment_amount,raw_final_amount,rounding_method_code,rounding_unit,'
            . 'statutory_standard_id,coverage_id,social_insurance_workplace_id '
            . 'FROM ledger_evidence_daily_employment_income_lines WHERE evidence_id=:evidence_id '
            . 'ORDER BY sort_no,source_calculation_line_id'
        );
        $statement->execute([':evidence_id' => $evidenceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fallback(string $newColumn, string $legacyExpression, bool $emptyStringFallback = false): string
    {
        if (!$this->schema->columnExists('ledger_evidence_daily_employment_income', $newColumn)) {
            return $legacyExpression;
        }
        return $emptyStringFallback
            ? "COALESCE(NULLIF(body.{$newColumn},''),{$legacyExpression})"
            : "COALESCE(body.{$newColumn},{$legacyExpression})";
    }
}
