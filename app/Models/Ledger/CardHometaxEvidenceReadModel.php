<?php

namespace App\Models\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use Core\Helpers\ActorHelper;
use PDO;

class CardHometaxEvidenceReadModel
{
    public function __construct(
        private PDO $pdo,
        private EvidenceSchemaModel $schemaService,
        private EvidenceBodyStatusProjectionModel $processingPolicyService
    ) {
    }

    public function findList(string $status = '', string $requestedId = ''): array
    {
        if (!$this->processingPolicyService->filteredStatusHasRows($status)) {
            return [];
        }

        $cardTable = 'ledger_evidence_card_hometax';
        $where = [$status === 'DELETED' ? 'body.deleted_at IS NOT NULL' : 'body.deleted_at IS NULL'];
        $params = [];

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

        $cardSortNoExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['sort_no'], '0');
        $cardSourceKeyExpr = $this->schemaService->coalesceExistingColumnExpr($cardTable, 'body', ['external_key', 'source_key', 'approval_number', 'approval_no', 'raw_approval_no', 'raw_approval_number'], "''");
        $cardEvidenceDateExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['approval_date', 'billing_date', 'evidence_date', 'purchase_datetime', 'approved_at', 'created_at'], 'NULL');
        $cardPurchaseDateTimeExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['purchase_datetime', 'approval_datetime', 'approved_at', 'approval_date', 'approved_date', 'transaction_datetime', 'evidence_date', 'created_at'], 'NULL');
        $cardClientIdExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['client_id'], "''");
        $cardProjectIdExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['project_id'], "''");
        $cardEmployeeIdExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['employee_id'], "''");
        $cardBankAccountIdExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['bank_account_id'], "''");
        $cardCardIdExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['card_id'], "''");
        $cardTeamIdExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['team_id'], "''");
        $cardClientNameExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['raw_client_name', 'client_name', 'merchant_company_name'], "''");
        $cardMerchantNameExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['merchant_company_name', 'raw_client_name', 'client_name'], "''");
        $cardCreatedAtExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['created_at', 'updated_at'], 'NULL');
        $cardUpdatedAtExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['updated_at', 'created_at'], 'NULL');
        $cardDeletedAtExpr = $this->schemaService->firstExistingColumnExpr($cardTable, 'body', ['deleted_at'], 'NULL');

        $sql = "
            SELECT
                body.*,
                body.id AS id,
                'HOMETAX' AS source_type,
                'CARD_HOMETAX' AS import_type,
                '' AS source_type_name,
                '' AS import_type_name,
                {$cardSortNoExpr} AS sort_no,
                0 AS row_no,
                " . $this->schemaService->sourceFormatIdSelect($cardTable) . " AS format_id,
                " . $this->schemaService->sourceRawJsonSelect($cardTable) . " AS raw_json,
                " . $this->schemaService->sourceParsedJsonSelect($cardTable) . " AS parsed_json,
                {$cardSourceKeyExpr} AS source_key,
                {$cardEvidenceDateExpr} AS evidence_date,
                {$cardPurchaseDateTimeExpr} AS purchase_datetime,
                {$cardClientIdExpr} AS client_id,
                {$cardProjectIdExpr} AS project_id,
                {$cardEmployeeIdExpr} AS employee_id,
                {$cardBankAccountIdExpr} AS bank_account_id,
                {$cardCardIdExpr} AS card_id,
                {$cardTeamIdExpr} AS team_id,
                COALESCE({$cardClientNameExpr}, {$cardMerchantNameExpr}, '') AS client_name,
                '' AS project_name,
                '' AS employee_name,
                '' AS bank_account_name,
                '' AS card_name,
                '' AS team_name,
                body.evidence_status AS evidence_status,
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
                {$cardUpdatedAtExpr} AS processed_at,
                body.created_by AS created_by_name,
                body.updated_by AS updated_by_name,
                body.deleted_by AS deleted_by_name,
                {$cardCreatedAtExpr} AS created_at,
                {$cardUpdatedAtExpr} AS updated_at,
                {$cardDeletedAtExpr} AS deleted_at,
                '' AS file_name,
                '' AS format_name
            FROM {$cardTable} body
            " . $this->processingPolicyService->joinForBody('body', "'CARD_HOMETAX'") . "
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci = 'CARD_HOMETAX' COLLATE utf8mb4_general_ci
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci = 'CARD_HOMETAX' COLLATE utf8mb4_general_ci
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
            FROM ledger_evidence_card_hometax body
            " . $this->processingPolicyService->joinForBody('body', "'CARD_HOMETAX'") . "
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
