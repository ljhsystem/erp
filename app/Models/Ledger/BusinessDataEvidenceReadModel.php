<?php

namespace App\Models\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use Core\Helpers\ActorHelper;
use PDO;

class BusinessDataEvidenceReadModel
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
        $tableName = match ($normalizedType) {
            'BUSINESS_DATA' => 'ledger_evidence_cash_sales',
            'SHOPPING_ORDER', 'IMPORT_INVOICE' => 'ledger_evidence_business_data',
            'BUSINESS_INCOME_REPORT' => 'ledger_evidence_business_income',
            'EMPLOYEE_EXPENSE' => 'ledger_evidence_employee_expense',
            default => '',
        };

        if ($tableName === '') {
            return [];
        }

        $sourceTypeExpr = $this->schemaService->columnExists($tableName, 'source_type')
            ? "COALESCE(NULLIF(TRIM(body.source_type), ''), 'MANUAL')"
            : "'MANUAL'";
        $sortNoExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['sort_no'], '0');
        $sourceKeyExpr = $this->schemaService->coalesceExistingColumnExpr($tableName, 'body', ['external_key', 'source_key', 'approval_number', 'reference_no'], "''");
        $evidenceDateExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['evidence_date', 'transaction_date', 'issue_date', 'write_date', 'created_at'], 'NULL');
        $transactionDateExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['transaction_date', 'evidence_date', 'issue_date', 'write_date', 'created_at'], 'NULL');
        $clientIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['client_id'], "''");
        $projectIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['project_id'], "''");
        $employeeIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['employee_id'], "''");
        $bankAccountIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['bank_account_id'], "''");
        $cardIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['card_id'], "''");
        $teamIdExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['work_team_id', 'team_id'], "''");
        $clientNameExpr = $this->schemaService->coalesceExistingColumnExpr($tableName, 'body', ['client_name', 'raw_client_name', 'client_company_name', 'merchant_company_name', 'supplier_company_name', 'customer_company_name', 'description'], "''");
        $projectNameExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['project_name'], "''");
        $employeeNameExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['employee_name'], "''");
        $bankAccountNameExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['bank_account_name'], "''");
        $cardNameExpr = $this->schemaService->coalesceExistingColumnExpr($tableName, 'body', ['raw_card_name', 'card_name'], "''");
        $teamNameExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['team_name'], "''");
        $createdByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['created_by'], 'NULL');
        $updatedByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['updated_by'], 'NULL');
        $deletedByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['deleted_by'], 'NULL');
        $approvedByExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['approved_by'], 'NULL');
        $createdAtExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['created_at', 'updated_at'], 'NULL');
        $updatedAtExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['updated_at', 'created_at'], 'NULL');
        $deletedAtExpr = $this->schemaService->firstExistingColumnExpr($tableName, 'body', ['deleted_at'], 'NULL');
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if (in_array($normalizedType, ['SHOPPING_ORDER', 'IMPORT_INVOICE'], true) && $this->schemaService->columnExists($tableName, 'source_type')) {
            $where[] = "UPPER(COALESCE(NULLIF(TRIM(body.source_type), ''), '')) = " . $this->pdo->quote($normalizedType);
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
                '{$normalizedType}' AS import_type,
                '' AS source_type_name,
                '' AS import_type_name,
                {$sortNoExpr} AS sort_no,
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
                {$teamIdExpr} AS team_id,
                COALESCE({$clientNameExpr}, '') AS client_name,
                {$projectNameExpr} AS project_name,
                {$employeeNameExpr} AS employee_name,
                {$bankAccountNameExpr} AS bank_account_name,
                {$cardNameExpr} AS card_name,
                {$teamNameExpr} AS team_name,
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
            " . $this->processingPolicyService->joinForBody('body', "'" . $normalizedType . "'") . "
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci = '{$normalizedType}' COLLATE utf8mb4_general_ci
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci = '{$normalizedType}' COLLATE utf8mb4_general_ci
               AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sort_no ASC, updated_at DESC, created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($normalizedType === 'BUSINESS_INCOME_REPORT' && $requestedId !== '' && $rows !== []) {
            $rows[0]['work_lines'] = $this->businessIncomeDetailLines(
                'ledger_evidence_business_income_work_lines',
                $requestedId,
                'raw_sort_no'
            );
            $rows[0]['raw_lines'] = $this->businessIncomeDetailLines(
                'ledger_evidence_business_income_raw_lines',
                $requestedId,
                'raw_sort_no'
            );
        }

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by',
            'updated_by_name' => 'updated_by',
            'deleted_by_name' => 'deleted_by',
            'approved_by_name' => 'approved_by',
        ]);
    }

    private function businessIncomeDetailLines(string $table, string $evidenceId, string $sortColumn): array
    {
        if (!$this->schemaService->tableExists($table)) return [];

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE evidence_id = :evidence_id ORDER BY `{$sortColumn}` ASC, id ASC");
        $stmt->execute([':evidence_id' => $evidenceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCount(string $importType, string $status = '', string $requestedId = ''): int
    {
        if (!$this->processingPolicyService->filteredStatusHasRows($status)) {
            return 0;
        }

        $normalizedType = strtoupper(trim($importType));
        $tableName = match ($normalizedType) {
            'BUSINESS_DATA' => 'ledger_evidence_cash_sales',
            'SHOPPING_ORDER', 'IMPORT_INVOICE' => 'ledger_evidence_business_data',
            'BUSINESS_INCOME_REPORT' => 'ledger_evidence_business_income',
            'EMPLOYEE_EXPENSE' => 'ledger_evidence_employee_expense',
            default => '',
        };

        if ($tableName === '') {
            return 0;
        }

        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if (in_array($normalizedType, ['SHOPPING_ORDER', 'IMPORT_INVOICE'], true) && $this->schemaService->columnExists($tableName, 'source_type')) {
            $where[] = "UPPER(COALESCE(NULLIF(TRIM(body.source_type), ''), '')) = " . $this->pdo->quote($normalizedType);
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
            " . $this->processingPolicyService->joinForBody('body', "'" . $normalizedType . "'") . "
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findById(string $importType, string $id, string $status = ''): ?array
    {
        $rows = $this->findList($importType, $status, $id);

        return $rows[0] ?? null;
    }
}
