<?php

namespace App\Models\Ledger;

use App\Services\Ledger\BodyTableSchemaService;
use App\Services\Ledger\EvidenceProcessingPolicyService;
use Core\Helpers\ActorHelper;
use PDO;

class CashReceiptEvidenceReadModel
{
    public function __construct(
        private PDO $pdo,
        private BodyTableSchemaService $schemaService,
        private EvidenceProcessingPolicyService $processingPolicyService
    ) {
    }

    public function findList(string $status = '', string $requestedId = ''): array
    {
        if (!$this->processingPolicyService->filteredStatusHasRows($status)) {
            return [];
        }

        $cashEvidenceTypeList = "'CASH_RECEIPT'";
        $cashTable = 'ledger_evidence_cash_receipt';
        $cashSortNoExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['sort_no'], '0');
        $cashEvidenceSortNoExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['evidence_sort_no', 'sort_no'], '0');
        $cashSourceKeyExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['external_key', 'source_key', 'approval_number'], "''");
        $cashEvidenceDateExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['evidence_date', 'transaction_date', 'purchase_date', 'write_date', 'created_at'], 'NULL');
        $cashPurchaseDateTimeExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['raw_purchase_datetime', 'write_date', 'purchase_datetime', 'purchase_at', 'transaction_datetime', 'evidence_date', 'created_at'], 'NULL');
        $cashClientIdExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['client_id'], "''");
        $cashProjectIdExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['project_id'], "''");
        $cashClientNameExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['raw_client_name', 'client_name', 'merchant_company_name'], "''");
        $cashMerchantNameExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['merchant_company_name', 'raw_client_name', 'client_name'], "''");
        $cashUpdatedAtExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['updated_at', 'created_at'], 'NULL');
        $cashCreatedAtExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['created_at', 'updated_at'], 'NULL');
        $cashDeletedAtExpr = $this->schemaService->firstExistingColumnExpr($cashTable, 'body', ['deleted_at'], 'NULL');
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }

        if ($this->schemaService->columnExists($cashTable, 'transaction_direction')) {
            $where[] = "UPPER(COALESCE(body.transaction_direction, '')) COLLATE utf8mb4_general_ci <> 'INCOME'";
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
                'HOMETAX' AS source_type,
                'CASH_RECEIPT' AS import_type,
                '' AS source_type_name,
                '' AS import_type_name,
                {$cashSortNoExpr} AS sort_no,
                {$cashEvidenceSortNoExpr} AS evidence_sort_no,
                0 AS row_no,
                " . $this->schemaService->sourceFormatIdSelect($cashTable) . " AS format_id,
                " . $this->schemaService->sourceRawJsonSelect($cashTable) . " AS raw_json,
                " . $this->schemaService->sourceParsedJsonSelect($cashTable) . " AS parsed_json,
                {$cashSourceKeyExpr} AS source_key,
                {$cashEvidenceDateExpr} AS evidence_date,
                {$cashEvidenceDateExpr} AS transaction_date,
                {$cashPurchaseDateTimeExpr} AS purchase_datetime,
                {$cashClientIdExpr} AS client_id,
                {$cashProjectIdExpr} AS project_id,
                '' AS employee_id,
                '' AS bank_account_id,
                '' AS card_id,
                '' AS team_id,
                COALESCE({$cashClientNameExpr}, {$cashMerchantNameExpr}, '') AS client_name,
                '' AS project_name,
                '' AS employee_name,
                '' AS bank_account_name,
                '' AS card_name,
                '' AS team_name,
                body.evidence_status AS evidence_status,
                " . $this->processingPolicyService->statusSelect('READY') . " AS transaction_status,
                CASE WHEN vx.target_id IS NULL THEN 'WAITING' ELSE 'LINKED' END AS voucher_status,
                " . $this->processingPolicyService->reviewStatusSelect('NORMAL') . " AS review_status,
                CASE
                    WHEN " . $this->processingPolicyService->statusSelect('READY') . " IN ('ERROR', 'DUPLICATED', 'PROCESSING', 'PROCESSED') THEN " . $this->processingPolicyService->statusSelect('READY') . "
                    WHEN tx.target_id IS NOT NULL THEN 'PROCESSED'
                    ELSE " . $this->processingPolicyService->statusSelect('READY') . "
                END AS process_status,
                CASE
                    WHEN " . $this->processingPolicyService->statusSelect('READY') . " IN ('ERROR', 'DUPLICATED', 'PROCESSING', 'PROCESSED') THEN " . $this->processingPolicyService->statusSelect('READY') . "
                    WHEN tx.target_id IS NOT NULL THEN 'PROCESSED'
                    ELSE " . $this->processingPolicyService->statusSelect('READY') . "
                END AS status,
                " . $this->processingPolicyService->errorMessageSelect() . " AS error_message,
                tx.target_id AS transaction_id,
                {$cashUpdatedAtExpr} AS processed_at,
                body.created_by AS created_by_name,
                body.updated_by AS updated_by_name,
                body.deleted_by AS deleted_by_name,
                {$cashCreatedAtExpr} AS created_at,
                {$cashUpdatedAtExpr} AS updated_at,
                {$cashDeletedAtExpr} AS deleted_at,
                '' AS file_name,
                '' AS format_name
            FROM {$cashTable} body
            " . $this->processingPolicyService->joinForBody('body', '(' . $cashEvidenceTypeList . ')') . "
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci IN ({$cashEvidenceTypeList})
               AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
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
        ]);
    }

    public function findCount(string $status = '', string $requestedId = ''): int
    {
        if (!$this->processingPolicyService->filteredStatusHasRows($status)) {
            return 0;
        }

        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

        if ($requestedId !== '') {
            $where[] = 'body.id COLLATE utf8mb4_general_ci = :requested_id COLLATE utf8mb4_general_ci';
            $params[':requested_id'] = $requestedId;
        }

        if ($this->schemaService->columnExists('ledger_evidence_cash_receipt', 'transaction_direction')) {
            $where[] = "UPPER(COALESCE(body.transaction_direction, '')) COLLATE utf8mb4_general_ci <> 'INCOME'";
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
            SELECT COUNT(DISTINCT body.id)
            FROM ledger_evidence_cash_receipt body
            " . $this->processingPolicyService->joinForBody('body', "('CASH_RECEIPT')") . "
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findById(string $id, string $status = ''): ?array
    {
        $rows = $this->findList($status, $id);

        return $rows[0] ?? null;
    }
}
