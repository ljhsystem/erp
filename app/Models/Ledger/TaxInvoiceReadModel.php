<?php

namespace App\Models\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use Core\Helpers\ActorHelper;
use PDO;

class TaxInvoiceReadModel
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
        $isManualTaxInvoice = $normalizedType === 'TAX_INVOICE_MANUAL';
        $taxTable = $isManualTaxInvoice ? 'ledger_evidence_tax_invoice_manual' : 'ledger_evidence_tax_invoice';
        $taxEvidenceType = $isManualTaxInvoice ? 'TAX_INVOICE_MANUAL' : 'TAX_INVOICE';
        $taxSourceTypeFallback = $isManualTaxInvoice ? "'MANUAL'" : "'HOMETAX'";
        $taxEvidenceDateExpr = $this->schemaService->firstExistingColumnExpr($taxTable, 'body', ['transaction_date', 'issue_date', 'transmit_date'], 'NULL');
        $taxSourceKeyExpr = $this->schemaService->firstExistingColumnExpr($taxTable, 'body', ['external_key', 'source_key', 'approval_number'], "''");
        $taxClientIdExpr = $this->schemaService->firstExistingColumnExpr($taxTable, 'body', ['client_id'], "''");
        $taxProjectIdExpr = $this->schemaService->firstExistingColumnExpr($taxTable, 'body', ['project_id'], "''");
        $taxClientNameExpr = $this->schemaService->firstExistingColumnExpr($taxTable, 'body', ['raw_client_name', 'raw_customer_company_name', 'raw_supplier_company_name', 'customer_company_name', 'supplier_company_name', 'description', 'raw_note'], "''");
        $taxSourceTypeExpr = $this->schemaService->columnExists($taxTable, 'source_type')
            ? "CASE WHEN body.source_type LIKE '%MANUAL%' THEN 'MANUAL' ELSE 'HOMETAX' END"
            : $taxSourceTypeFallback;
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

        $sql = "
            SELECT
                body.*,
                body.id AS id,
                {$taxSourceTypeExpr} AS source_type,
                '{$taxEvidenceType}' AS import_type,
                '' AS source_type_name,
                '' AS import_type_name,
                body.sort_no AS sort_no,
                0 AS row_no,
                " . $this->schemaService->sourceFormatIdSelect($taxTable) . " AS format_id,
                " . $this->schemaService->sourceRawJsonSelect($taxTable) . " AS raw_json,
                " . $this->schemaService->sourceParsedJsonSelect($taxTable) . " AS parsed_json,
                {$taxSourceKeyExpr} AS source_key,
                {$taxEvidenceDateExpr} AS evidence_date,
                {$taxClientIdExpr} AS client_id,
                {$taxProjectIdExpr} AS project_id,
                '' AS employee_id,
                '' AS bank_account_id,
                '' AS card_id,
                '' AS team_id,
                COALESCE({$taxClientNameExpr}, '') AS client_name,
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
                body.updated_at AS processed_at,
                body.created_by AS created_by_name,
                body.updated_by AS updated_by_name,
                body.deleted_by AS deleted_by_name,
                body.created_at,
                body.updated_at,
                body.deleted_at,
                '' AS file_name,
                '' AS format_name
            FROM {$taxTable} body
            " . $this->processingPolicyService->joinForBody('body', "'{$taxEvidenceType}'") . "
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci = '{$taxEvidenceType}' COLLATE utf8mb4_general_ci
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci = '{$taxEvidenceType}' COLLATE utf8mb4_general_ci
               AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            WHERE " . implode(' AND ', $where) . "
            ORDER BY body.sort_no ASC, body.updated_at DESC, body.created_at DESC
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

    public function findCount(string $importType, string $status = '', string $requestedId = ''): int
    {
        if (!$this->processingPolicyService->filteredStatusHasRows($status)) {
            return 0;
        }

        $normalizedType = strtoupper(trim($importType));
        $isManualTaxInvoice = $normalizedType === 'TAX_INVOICE_MANUAL';
        $taxTable = $isManualTaxInvoice ? 'ledger_evidence_tax_invoice_manual' : 'ledger_evidence_tax_invoice';
        $taxEvidenceType = $isManualTaxInvoice ? 'TAX_INVOICE_MANUAL' : 'TAX_INVOICE';
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
            FROM {$taxTable} body
            " . $this->processingPolicyService->joinForBody('body', "'{$taxEvidenceType}'") . "
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
