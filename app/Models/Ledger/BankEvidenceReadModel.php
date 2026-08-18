<?php

namespace App\Models\Ledger;

use App\Models\Ledger\EvidenceSchemaModel;
use App\Models\Ledger\EvidenceBodyStatusProjectionModel;
use Core\Helpers\ActorHelper;
use PDO;

class BankEvidenceReadModel
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
                'BANK' AS source_type,
                'BANK_TRANSACTION' AS import_type,
                st.code_name AS source_type_name,
                it.code_name AS import_type_name,
                body.sort_no AS sort_no,
                0 AS row_no,
                " . $this->schemaService->sourceFormatIdSelect('ledger_evidence_bank_transaction') . " AS format_id,
                " . $this->schemaService->sourceRawJsonSelect('ledger_evidence_bank_transaction') . " AS raw_json,
                " . $this->schemaService->sourceParsedJsonSelect('ledger_evidence_bank_transaction') . " AS parsed_json,
                body.external_key AS source_key,
                body.raw_transaction_datetime AS evidence_date,
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
            FROM ledger_evidence_bank_transaction body
            " . $this->processingPolicyService->joinForBody('body', "'BANK_TRANSACTION'") . "
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_general_ci = 'BANK_TRANSACTION' COLLATE utf8mb4_general_ci
               AND tx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx
                ON vx.evidence_type COLLATE utf8mb4_general_ci = 'BANK_TRANSACTION' COLLATE utf8mb4_general_ci
               AND vx.evidence_id COLLATE utf8mb4_general_ci = body.id COLLATE utf8mb4_general_ci
               AND vx.target_type = 'VOUCHER'
               AND vx.deleted_at IS NULL
            LEFT JOIN system_codes st
                ON st.is_active = 1
               AND st.code_group IN ('IMPORT_SOURCE', 'SOURCE_TYPE')
               AND st.code = 'BANK'
            LEFT JOIN system_codes it
                ON it.is_active = 1
               AND it.code_group = 'IMPORT_TYPE'
               AND it.code = 'BANK_TRANSACTION'
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
            FROM ledger_evidence_bank_transaction body
            " . $this->processingPolicyService->joinForBody('body', "'BANK_TRANSACTION'") . "
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
