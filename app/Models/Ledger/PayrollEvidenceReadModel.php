<?php

namespace App\Models\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use Core\Helpers\ActorHelper;
use PDO;

class PayrollEvidenceReadModel
{
    public function __construct(
        private PDO $pdo,
        private EvidenceSchemaModel $schemaService,
        private EvidenceBodyStatusProjectionModel $processingPolicyService
    ) {
    }

    public function findList(string $importType, string $status = '', string $requestedId = ''): array
    {
        if (!$this->processingPolicyService->filteredStatusHasRows($status)) {
            return [];
        }

        $normalizedType = strtoupper(trim($importType));
        if ($normalizedType === 'PAYROLL_WITHHOLDING') {
            return $this->findDailyEmploymentIncomeList($status, $requestedId);
        }
        $tableName = $normalizedType === 'PAYROLL' ? 'ledger_evidence_salary_report' : 'ledger_evidence_payroll';
        $linkEvidenceType = $normalizedType === 'PAYROLL' ? 'PAYROLL_REPORT' : $normalizedType;
        $sourceTypeExpr = $this->schemaService->columnExists($tableName, 'source_type')
            ? "COALESCE(NULLIF(TRIM(body.source_type), ''), 'MANUAL')"
            : "'MANUAL'";
        $sortNoExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['sort_no'], '0');
        $sourceKeyExpr = $this->schemaService->coalesceExistingColumnExpr($tableName, 'body', ['external_key', 'source_key', 'approval_number', 'reference_no'], "''");
        $incomeMonthEndExpr = $normalizedType === 'PAYROLL' && $this->schemaService->columnExists($tableName, 'raw_income_year_month')
            ? "LAST_DAY(CONCAT(body.raw_income_year_month, '-01'))"
            : 'NULL';
        $evidenceDateExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['evidence_date', 'transaction_date', 'issue_date', 'write_date'], $incomeMonthEndExpr);
        $transactionDateExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['transaction_date', 'evidence_date', 'issue_date', 'write_date'], $incomeMonthEndExpr);
        $clientIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['client_id'], "''");
        $projectIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['project_id'], "''");
        $employeeIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['employee_id'], "''");
        $bankAccountIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['bank_account_id'], "''");
        $cardIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['card_id'], "''");
        $teamIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['work_team_id', 'team_id'], "''");
        $clientNameExpr = $this->schemaService->coalesceExistingColumnExpr($tableName, 'body', ['client_name', 'raw_client_name', 'description'], "''");
        $projectNameExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['project_name'], "''");
        $employeeNameExpr = $normalizedType === 'PAYROLL'
            && $this->schemaService->columnExists($tableName, 'regular_employment_income_item_id')
            ? "(SELECT item.employee_name_snapshot FROM institution_regular_employment_income_items item WHERE item.id=body.regular_employment_income_item_id LIMIT 1)"
            : $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['employee_name'], "''");
        $bankAccountNameExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['bank_account_name'], "''");
        $cardNameExpr = $this->schemaService->coalesceExistingColumnExpr($tableName, 'body', ['raw_card_name', 'card_name'], "''");
        $teamNameExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['team_name'], "''");
        $createdByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['created_by'], 'NULL');
        $updatedByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['updated_by'], 'NULL');
        $deletedByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['deleted_by'], 'NULL');
        $createdAtExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['created_at', 'updated_at'], 'NULL');
        $updatedAtExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['updated_at', 'created_at'], 'NULL');
        $deletedAtExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['deleted_at'], 'NULL');
        $approvedByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['approved_by'], 'NULL');
        $grossPaymentExpr = $this->schemaService->coalesceExistingColumnExpr(
            $tableName,
            'body',
            ['raw_gross_payment_amount', 'raw_gross_amount'],
            '0'
        );
        $workerDeductionExpr = $this->schemaService->coalesceExistingColumnExpr(
            $tableName,
            'body',
            ['raw_worker_deduction_amount', 'raw_deduction_amount'],
            '0'
        );
        $salaryReportJoins = '';
        $sourceIncomeDisplayExpr = "''";
        $approvalRequestDisplayExpr = "''";
        $incomeItemDisplayExpr = "''";
        if ($normalizedType === 'PAYROLL' && $this->schemaService->columnExists($tableName, 'regular_employment_income_item_id')) {
            $salaryReportJoins = "
            LEFT JOIN institution_regular_employment_incomes income_header
              ON income_header.id = body.source_regular_employment_income_id
            LEFT JOIN institution_regular_employment_income_items income_item
              ON income_item.id = body.regular_employment_income_item_id
            LEFT JOIN user_approval_requests approval_request
              ON approval_request.id = body.approval_request_id
            LEFT JOIN user_employees evidence_employee
              ON evidence_employee.id = body.employee_id";
            $sourceIncomeDisplayExpr = "COALESCE(NULLIF(income_header.title,''), '')";
            $approvalRequestDisplayExpr = "CASE WHEN approval_request.sort_no IS NULL THEN '' ELSE CONCAT('#', approval_request.sort_no) END";
            $incomeItemDisplayExpr = "COALESCE(NULLIF(income_item.employee_name_snapshot,''), '')";
            $employeeNameExpr = "COALESCE(NULLIF(evidence_employee.employee_name,''), NULLIF(income_item.employee_name_snapshot,''), '')";
        }
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($this->schemaService->columnExists($tableName, 'import_type')) {
            $where[] = "UPPER(COALESCE(NULLIF(TRIM(body.import_type), ''), '')) = " . $this->pdo->quote($linkEvidenceType);
        }

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }

        if ($status === 'READY') {
            $where[] = "COALESCE(pr.processing_status, 'READY') = 'READY'";
        } elseif ($status === 'PROCESSED') {
            $where[] = "COALESCE(pr.processing_status, '') = 'PROCESSED'";
        } elseif ($status === 'ERROR') {
            $where[] = "COALESCE(pr.processing_status, '') = 'ERROR'";
        } elseif ($status === 'DUPLICATED') {
            $where[] = "COALESCE(pr.processing_status, '') = 'DUPLICATED'";
        }

        $sql = "
            SELECT
                body.*,
                body.id AS id,
                {$sourceTypeExpr} AS source_type,
                '{$linkEvidenceType}' AS import_type,
                '' AS source_type_name,
                '' AS import_type_name,
                {$sortNoExpr} AS sort_no,
                {$grossPaymentExpr} AS gross_payment_amount,
                {$workerDeductionExpr} AS worker_deduction_amount,
                0 AS row_no,
                " . $this->schemaService->sourceFormatIdSelect($tableName) . " AS format_id,
                " . $this->schemaService->sourceRawJsonSelect($tableName) . " AS raw_json,
                " . $this->schemaService->sourceParsedJsonSelect($tableName) . " AS parsed_json,
                {$sourceKeyExpr} AS source_key,
                {$evidenceDateExpr} AS evidence_date,
                {$transactionDateExpr} AS transaction_date,
                {$clientIdExpr} AS client_id,
                {$projectIdExpr} AS project_id,
                {$employeeIdExpr} AS employee_id,
                {$bankAccountIdExpr} AS bank_account_id,
                {$cardIdExpr} AS card_id,
                {$teamIdExpr} AS work_team_id,
                COALESCE({$clientNameExpr}, '') AS client_name,
                {$projectNameExpr} AS project_name,
                {$employeeNameExpr} AS employee_name,
                {$bankAccountNameExpr} AS bank_account_name,
                {$cardNameExpr} AS card_name,
                {$teamNameExpr} AS team_name,
                {$sourceIncomeDisplayExpr} AS source_regular_employment_income_name,
                {$approvalRequestDisplayExpr} AS approval_request_name,
                {$incomeItemDisplayExpr} AS regular_employment_income_item_name,
                " . $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['evidence_status'], "'CORRECTION_REQUIRED'") . " AS evidence_status,
                " . $this->processingPolicyService->processingStatusSelect() . " AS transaction_status,
                CASE WHEN vx.target_id IS NULL THEN 'WAITING' ELSE 'LINKED' END AS voucher_status,
                " . $this->processingPolicyService->reviewStatusSelect('NORMAL') . " AS review_status,
                CASE
                    WHEN " . $this->processingPolicyService->processingStatusSelect() . " IN ('ERROR', 'DUPLICATED', 'PROCESSING', 'PROCESSED') THEN " . $this->processingPolicyService->processingStatusSelect() . "
                    WHEN tx.target_id IS NOT NULL THEN 'PROCESSED'
                    ELSE " . $this->processingPolicyService->processingStatusSelect() . "
                END AS process_status,
                CASE
                    WHEN " . $this->processingPolicyService->processingStatusSelect() . " IN ('ERROR', 'DUPLICATED', 'PROCESSING', 'PROCESSED') THEN " . $this->processingPolicyService->processingStatusSelect() . "
                    WHEN tx.target_id IS NOT NULL THEN 'PROCESSED'
                    ELSE " . $this->processingPolicyService->processingStatusSelect() . "
                END AS status,
                " . $this->processingPolicyService->errorMessageSelect() . " AS error_message,
                tx.target_id AS transaction_id,
                {$updatedAtExpr} AS processed_at,
                {$createdByExpr} AS created_by_name,
                {$updatedByExpr} AS updated_by_name,
                {$deletedByExpr} AS deleted_by_name,
                {$approvedByExpr} AS approved_by_name,
                {$createdAtExpr} AS created_at,
                {$updatedAtExpr} AS updated_at,
                {$deletedAtExpr} AS deleted_at,
                '' AS file_name,
                '' AS format_name
            FROM {$tableName} body
            " . $this->processingPolicyService->joinForBody('body', "'" . $linkEvidenceType . "'") . "
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci = '{$linkEvidenceType}' COLLATE utf8mb4_general_ci
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci = '{$linkEvidenceType}' COLLATE utf8mb4_general_ci
               AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            {$salaryReportJoins}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sort_no ASC, updated_at DESC, created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
            'approved_by_name' => 'approved_by',
        ]);
    }

    public function findCount(string $importType, string $status = '', string $requestedId = ''): int
    {
        if (!$this->processingPolicyService->filteredStatusHasRows($status)) {
            return 0;
        }

        $normalizedType = strtoupper(trim($importType));
        if ($normalizedType === 'PAYROLL_WITHHOLDING') {
            return $this->findDailyEmploymentIncomeCount($status, $requestedId);
        }
        $tableName = $normalizedType === 'PAYROLL' ? 'ledger_evidence_salary_report' : 'ledger_evidence_payroll';
        $linkEvidenceType = $normalizedType === 'PAYROLL' ? 'PAYROLL_REPORT' : $normalizedType;
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($this->schemaService->columnExists($tableName, 'import_type')) {
            $where[] = "UPPER(COALESCE(NULLIF(TRIM(body.import_type), ''), '')) = " . $this->pdo->quote($linkEvidenceType);
        }

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }

        if ($status === 'READY') {
            $where[] = "COALESCE(pr.processing_status, 'READY') = 'READY'";
        } elseif ($status === 'PROCESSED') {
            $where[] = "COALESCE(pr.processing_status, '') = 'PROCESSED'";
        } elseif ($status === 'ERROR') {
            $where[] = "COALESCE(pr.processing_status, '') = 'ERROR'";
        } elseif ($status === 'DUPLICATED') {
            $where[] = "COALESCE(pr.processing_status, '') = 'DUPLICATED'";
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM {$tableName} body
            " . $this->processingPolicyService->joinForBody('body', "'" . $linkEvidenceType . "'") . "
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findById(string $importType, string $id, string $status = ''): ?array
    {
        $rows = $this->findList($importType, $status, $id);
        $row = $rows[0] ?? null;
        if ($row !== null && strtoupper(trim($importType)) === 'PAYROLL') {
            $row['raw_lines'] = $this->findSalaryReportLines($id);
        }
        return $row;
    }

    public function findSalaryReportLines(string $evidenceId): array
    {
        if (!$this->schemaService->tableExists('ledger_evidence_salary_report_lines')) {
            return [];
        }
        $statement = $this->pdo->prepare("SELECT
                line.source_line_id AS regular_employment_income_line_item_id,
                evidence.regular_employment_income_item_id,
                evidence.employee_id,
                line.raw_item_type_code AS item_type_code,
                line.raw_item_code AS item_code,
                line.raw_item_name AS item_name_snapshot,
                line.raw_final_amount AS final_amount,
                line.raw_application_status_code AS application_status_code,
                line.raw_calculation_basis_amount AS calculation_basis_amount,
                line.raw_calculation_rate AS calculation_rate,
                line.raw_calculation_before_rounding AS calculation_before_rounding,
                line.raw_rounding_method_code AS rounding_method_code,
                line.raw_rounding_unit AS rounding_unit,
                line.raw_statutory_standard_id AS statutory_standard_id,
                NULL AS social_insurance_coverage_id,
                NULL AS workplace_size_period_id,
                CASE WHEN line.raw_item_type_code='EMPLOYER_BURDEN' THEN 'EMPLOYER'
                     WHEN line.raw_item_type_code='DEDUCTION' THEN 'EMPLOYEE' ELSE NULL END AS burden_subject,
                line.raw_item_code AS obligation_item_code,
                CASE WHEN line.raw_item_type_code='EMPLOYER_BURDEN' THEN 'EMPLOYER'
                     WHEN line.raw_item_type_code='DEDUCTION' THEN 'EMPLOYEE' ELSE NULL END AS responsible_party_type_code,
                standard.standard_type_code AS statutory_type_code,
                NULL AS institution_id,
                NULL AS institution_name
            FROM ledger_evidence_salary_report evidence
            JOIN ledger_evidence_salary_report_lines line ON line.evidence_id=evidence.id
            LEFT JOIN system_statutory_standards standard ON standard.id=line.raw_statutory_standard_id
            WHERE evidence.id=:evidence_id
            ORDER BY line.sort_no,line.id");
        $statement->execute([':evidence_id' => $evidenceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findDailyEmploymentIncomeList(string $status, string $requestedId): array
    {
        $where = ['1=1'];
        $params = [];
        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }
        if ($status === 'READY') {
            $where[] = 'transaction_link.target_id IS NULL';
        } elseif ($status === 'PROCESSED') {
            $where[] = 'transaction_link.target_id IS NOT NULL';
        } elseif (in_array($status, ['ERROR', 'DUPLICATED', 'DELETED'], true)) {
            return [];
        }

        $sql = "SELECT body.*,
                body.id AS id,
                'APPROVAL' AS source_type,
                'PAYROLL_WITHHOLDING' AS import_type,
                '승인문서' AS source_type_name,
                '일용직(신고)' AS import_type_name,
                0 AS sort_no,
                0 AS row_no,
                body.business_key_hash AS source_key,
                COALESCE(transaction_row.transaction_date,(SELECT MAX(workday.work_date) FROM institution_daily_employment_income_workdays workday WHERE workday.daily_employment_income_item_id=body.daily_employment_income_item_id)) AS evidence_date,
                COALESCE(transaction_row.transaction_date,(SELECT MAX(workday.work_date) FROM institution_daily_employment_income_workdays workday WHERE workday.daily_employment_income_item_id=body.daily_employment_income_item_id)) AS transaction_date,
                body.worker_client_id AS client_id,
                body.project_id,
                '' AS employee_id,
                '' AS bank_account_id,
                '' AS card_id,
                body.work_team_id AS team_id,
                COALESCE(NULLIF(worker.client_name,''), '') AS client_name,
                COALESCE(NULLIF(project.project_name,''), '') AS project_name,
                COALESCE(NULLIF(worker.client_name,''), '') AS employee_name,
                '' AS bank_account_name,
                '' AS card_name,
                COALESCE(NULLIF(work_team.team_name,''), '') AS team_name,
                COALESCE(NULLIF(header.document_title,''), '') AS document_title,
                COALESCE(NULLIF(header.document_title,''), '') AS source_daily_employment_income_name,
                COALESCE(NULLIF(income_group.work_description,''), '') AS work_description,
                COALESCE(NULLIF(income_group.work_description,''), '') AS daily_employment_income_group_name,
                COALESCE(NULLIF(income_group.business_unit,''), '') AS business_unit,
                CASE COALESCE(NULLIF(income_group.business_unit,''), '')
                    WHEN 'HQ' THEN '본사' WHEN 'ECOMMERCE' THEN '통신판매' WHEN 'CONSTRUCTION' THEN '건설' ELSE '' END AS business_unit_name,
                COALESCE(NULLIF(item.worker_name_snapshot,''), NULLIF(worker.client_name,''), '') AS worker_name,
                COALESCE(NULLIF(item.worker_name_snapshot,''), NULLIF(worker.client_name,''), '') AS daily_employment_income_item_name,
                COALESCE(NULLIF(worker.client_name,''), '') AS worker_client_name,
                COALESCE(NULLIF(work_team.team_name,''), '') AS work_team_name,
                COALESCE((SELECT SUM(workday.actual_work_minutes)
                    FROM institution_daily_employment_income_workdays workday
                    WHERE workday.daily_employment_income_item_id=body.daily_employment_income_item_id),0) AS total_work_minutes,
                CASE WHEN approval_request.sort_no IS NULL THEN '' ELSE CONCAT('#', approval_request.sort_no) END AS approval_request_name,
                body.evidence_status_code AS evidence_status,
                CASE WHEN transaction_link.target_id IS NULL THEN 'READY' ELSE 'PROCESSED' END AS transaction_status,
                CASE WHEN voucher_link.target_id IS NULL THEN 'WAITING' ELSE 'LINKED' END AS voucher_status,
                'NORMAL' AS review_status,
                CASE WHEN transaction_link.target_id IS NULL THEN 'READY' ELSE 'PROCESSED' END AS process_status,
                CASE WHEN transaction_link.target_id IS NULL THEN 'READY' ELSE 'PROCESSED' END AS status,
                '' AS error_message,
                transaction_link.target_id AS transaction_id,
                transaction_link.id AS evidence_link_id,
                CASE WHEN transaction_link.id IS NULL THEN 'NOT_LINKED' ELSE 'LINKED' END AS transaction_link_status,
                transaction_row.operation_type,
                operation_code.code_name AS operation_type_name,
                transaction_row.transaction_supply_amount,
                transaction_row.transaction_settlement_amount,
                transaction_row.transaction_final_amount,
                body.updated_at AS processed_at,
                body.created_by,
                body.updated_by,
                body.approved_by,
                body.created_at,
                body.updated_at,
                NULL AS deleted_at,
                NULL AS deleted_by,
                body.created_by AS created_by_name,
                body.updated_by AS updated_by_name,
                body.approved_by AS approved_by_name,
                NULL AS deleted_by_name,
                '' AS file_name,
                '' AS format_name,
                '' AS raw_json,
                body.snapshot_json AS parsed_json
            FROM ledger_evidence_daily_employment_income body
            JOIN institution_daily_employment_incomes header ON header.id=body.source_daily_employment_income_id
            JOIN institution_daily_employment_income_groups income_group ON income_group.id=body.daily_employment_income_group_id
            JOIN institution_daily_employment_income_items item ON item.id=body.daily_employment_income_item_id
            JOIN system_clients worker ON worker.id=body.worker_client_id
            LEFT JOIN system_projects project ON project.id=body.project_id
            LEFT JOIN system_work_teams work_team ON work_team.id=body.work_team_id
            LEFT JOIN user_approval_requests approval_request ON approval_request.id=body.approval_request_id
            LEFT JOIN ledger_evidence_links transaction_link
              ON transaction_link.evidence_type='DAILY_EMPLOYMENT_INCOME'
             AND transaction_link.evidence_id=body.id
             AND transaction_link.target_type='TRANSACTION'
             AND transaction_link.deleted_at IS NULL
            LEFT JOIN ledger_transactions transaction_row ON transaction_row.id=transaction_link.target_id
            LEFT JOIN system_codes operation_code
              ON operation_code.code_group='OPERATION_TYPE'
             AND operation_code.code=transaction_row.operation_type
            LEFT JOIN ledger_evidence_links voucher_link
              ON voucher_link.evidence_type='DAILY_EMPLOYMENT_INCOME'
             AND voucher_link.evidence_id=body.id
             AND voucher_link.target_type='VOUCHER'
             AND voucher_link.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
            ORDER BY body.created_at DESC,body.id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'approved_by_name' => 'approved_by',
        ]);
    }

    private function findDailyEmploymentIncomeCount(string $status, string $requestedId): int
    {
        if (in_array($status, ['ERROR', 'DUPLICATED', 'DELETED'], true)) {
            return 0;
        }
        $where = ['1=1'];
        $params = [];
        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }
        if ($status === 'READY') {
            $where[] = 'transaction_link.target_id IS NULL';
        } elseif ($status === 'PROCESSED') {
            $where[] = 'transaction_link.target_id IS NOT NULL';
        }
        $statement = $this->pdo->prepare("SELECT COUNT(DISTINCT body.id)
            FROM ledger_evidence_daily_employment_income body
            LEFT JOIN ledger_evidence_links transaction_link
              ON transaction_link.evidence_type='DAILY_EMPLOYMENT_INCOME'
             AND transaction_link.evidence_id=body.id
             AND transaction_link.target_type='TRANSACTION'
             AND transaction_link.deleted_at IS NULL
            WHERE " . implode(' AND ', $where));
        $statement->execute($params);
        return (int) $statement->fetchColumn();
    }
}
