<?php

namespace App\Models\Approval;

use PDO;

class PersonalExpenseClassificationCorrectionModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function resolveCompanyId(): string
    {
        $rows = $this->pdo->query('SELECT id FROM system_company ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($rows) !== 1) {
            throw new \RuntimeException('회계분류 정정 회사 범위를 확정할 수 없습니다.');
        }
        return (string) $rows[0];
    }

    public function lockDocument(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM approval_personal_expenses WHERE id=:id AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function lockItem(string $documentId, string $itemId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM approval_personal_expense_items WHERE id=:id AND personal_expense_id=:document_id AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([':id' => $itemId, ':document_id' => $documentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function approvalRequest(string $requestId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_approval_requests WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $requestId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function lockEvidence(string $evidenceId, string $itemId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ledger_evidence_employee_personal_expense WHERE id=:id AND source_personal_expense_item_id=:item_id FOR UPDATE');
        $stmt->execute([':id' => $evidenceId, ':item_id' => $itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function activeCategoryExists(string $category): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' AND code=:code AND is_active=1");
        $stmt->execute([':code' => $category]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function latestForItem(string $itemId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM approval_personal_expense_item_classification_corrections WHERE personal_expense_item_id=:item_id ORDER BY revision_no DESC LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':item_id' => $itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByRequestKey(string $companyId, string $requestKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM approval_personal_expense_item_classification_corrections WHERE company_id=:company_id AND request_key=:request_key LIMIT 1');
        $stmt->execute([':company_id' => $companyId, ':request_key' => $requestKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $row): void
    {
        $columns = ['id','company_id','personal_expense_id','personal_expense_item_id','approval_request_id','evidence_id','revision_no','previous_correction_id','original_category','previous_effective_category','corrected_category','amount_snapshot','correction_reason','correction_batch_key','request_key','processed_at','processed_by'];
        $stmt = $this->pdo->prepare('INSERT INTO approval_personal_expense_item_classification_corrections (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(static fn (string $column): string => ':' . $column, $columns)) . ')');
        $params = [];
        foreach ($columns as $column) {
            $params[':' . $column] = $row[$column] ?? null;
        }
        $stmt->execute($params);
    }

    public function listEffectiveForDocument(string $documentId): array
    {
        $hasCorrectionTable = $this->correctionTableExists();
        $correctionJoin = $hasCorrectionTable
            ? "LEFT JOIN approval_personal_expense_item_classification_corrections correction
                 ON correction.personal_expense_item_id=item.id
                AND correction.revision_no=(SELECT MAX(latest.revision_no)
                    FROM approval_personal_expense_item_classification_corrections latest
                    WHERE latest.personal_expense_item_id=item.id)"
            : '';
        $correctedColumn = $hasCorrectionTable ? 'correction.corrected_category' : 'NULL';
        $revisionColumn = $hasCorrectionTable ? 'correction.revision_no' : 'NULL';
        $correctedAtColumn = $hasCorrectionTable ? 'correction.processed_at' : 'NULL';
        $correctedByColumn = $hasCorrectionTable ? 'correction.processed_by' : 'NULL';
        $reasonColumn = $hasCorrectionTable ? 'correction.correction_reason' : 'NULL';
        $stmt = $this->pdo->prepare("SELECT item.id AS personal_expense_item_id,
                evidence.raw_expense_category,
                item.expense_category AS original_expense_category,
                {$correctedColumn} AS corrected_expense_category,
                COALESCE({$correctedColumn},item.expense_category) AS effective_expense_category,
                {$revisionColumn} AS classification_revision_no,
                {$correctedAtColumn} AS classification_corrected_at,
                {$correctedByColumn} AS classification_corrected_by,
                {$reasonColumn} AS classification_correction_reason
            FROM approval_personal_expense_items item
            LEFT JOIN ledger_evidence_employee_personal_expense evidence
              ON evidence.source_personal_expense_item_id=item.id
            {$correctionJoin}
            WHERE item.personal_expense_id=:document_id AND item.deleted_at IS NULL
            ORDER BY item.sort_no,item.id");
        $stmt->execute([':document_id' => $documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function effectiveForItemIds(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('strval', $itemIds))));
        if ($itemIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $hasCorrectionTable = $this->correctionTableExists();
        $correctionJoin = $hasCorrectionTable
            ? "LEFT JOIN approval_personal_expense_item_classification_corrections correction
                 ON correction.personal_expense_item_id=item.id
                AND correction.revision_no=(SELECT MAX(latest.revision_no)
                    FROM approval_personal_expense_item_classification_corrections latest
                    WHERE latest.personal_expense_item_id=item.id)"
            : '';
        $correctedColumn = $hasCorrectionTable ? 'correction.corrected_category' : 'NULL';
        $stmt = $this->pdo->prepare("SELECT item.id AS personal_expense_item_id,
                item.expense_category AS original_expense_category,
                {$correctedColumn} AS corrected_expense_category,
                COALESCE({$correctedColumn},item.expense_category) AS effective_expense_category
            FROM approval_personal_expense_items item
            {$correctionJoin}
            WHERE item.id IN ({$placeholders}) AND item.deleted_at IS NULL");
        $stmt->execute($itemIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function correctionTableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema=DATABASE() AND table_name='approval_personal_expense_item_classification_corrections'");
        return (int) $stmt->fetchColumn() === 1;
    }
}
