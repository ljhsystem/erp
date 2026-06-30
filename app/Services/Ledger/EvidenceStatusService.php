<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceStatusService
{
    /**
     * @param callable(array,string):array $placeholderBuilder
     * @param callable(array):string $jsonEncoder
     * @param callable(string):string $dataTypeNormalizer
     * @param callable(string):array $queryDataTypes
     * @param callable(string):bool $tableExists
     * @param callable(string,string):bool $tableColumnExists
     * @param callable(array,string):int $payloadSortNo
     */
    public function __construct(
        private PDO $pdo,
        private $placeholderBuilder,
        private $jsonEncoder,
        private $dataTypeNormalizer,
        private $queryDataTypes,
        private $tableExists,
        private $tableColumnExists,
        private $payloadSortNo
    ) {
    }

    public function updateStatus(array $ids, string $status): array
    {
        $status = strtoupper(trim($status));
        if ($ids === [] || !in_array($status, ['READY', 'ERROR', 'DUPLICATED'], true)) {
            return ['success' => false, 'message' => '상태를 변경할 증빙원본을 선택해 주세요.', 'status' => 400];
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'seed_id');
        $processingStatus = $status === 'READY' ? 'READY' : $status;
        $params[':status'] = $processingStatus;
        $stmt = $this->pdo->prepare("
            UPDATE ledger_evidence_processing
            SET processing_status = :status,
                last_error_message = CASE WHEN :status = 'READY' THEN NULL ELSE last_error_message END,
                updated_at = NOW()
            WHERE evidence_id IN ({$inSql})
              AND processing_status IN ('READY', 'ERROR', 'DUPLICATED')
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        return ['success' => true, 'message' => '증빙원본 상태가 변경되었습니다.'];
    }

    public function reorder(array $payload, string $actor): array
    {
        $actor = $this->auditActor($actor);
        $changes = $payload['changes'] ?? [];
        if (is_string($changes)) {
            $decoded = json_decode($changes, true);
            $changes = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($changes) || $changes === []) {
            return ['success' => false, 'message' => '정렬 변경 대상이 없습니다.', 'status' => 400];
        }

        $rows = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $id = trim((string) ($change['id'] ?? ''));
            $rowNo = (int) ($change['newSortNo'] ?? $change['row_no'] ?? $change['sort_no'] ?? 0);
            if ($id === '' || $rowNo < 1) {
                continue;
            }
            $rows[$id] = $rowNo;
        }
        if ($rows === []) {
            return ['success' => false, 'message' => '정렬 저장 대상이 올바르지 않습니다.', 'status' => 400];
        }

        $firstChange = is_array(reset($changes)) ? reset($changes) : [];
        $scope = strtolower(trim((string) ($payload['scope'] ?? $payload['sort_scope'] ?? $firstChange['scope'] ?? $firstChange['sort_scope'] ?? 'create')));
        $sortKey = $scope === 'status' ? '_status_sort_no' : '_create_sort_no';
        $importType = ($this->dataTypeNormalizer)((string) ($payload['import_type'] ?? $payload['data_type'] ?? $firstChange['import_type'] ?? $firstChange['data_type'] ?? ''));

        if ($importType === 'BANK_TRANSACTION') {
            return $this->reorderBankRows($rows, $sortKey, $actor);
        }
        if ($this->isCashReceiptType($importType)) {
            return $this->reorderCashReceiptRows($rows, $sortKey, $actor, $importType);
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            [$inSql, $params] = ($this->placeholderBuilder)(array_keys($rows), 'reorder_id');
            $sql = "
                SELECT
                    evidence_id AS id,
                    evidence_type,
                    mapped_payload_json,
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$.{$sortKey}')) AS UNSIGNED) AS current_sort_no
                FROM ledger_evidence_payloads
                WHERE evidence_id IN ({$inSql})
                  AND deleted_at IS NULL
            ";
            if ($scope === 'status') {
                if ($importType === '') {
                    if ($ownsTransaction && $this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    return ['success' => false, 'message' => '정렬할 자료유형을 확인할 수 없습니다.', 'status' => 400];
                }
                $typePlaceholders = [];
                foreach (($this->queryDataTypes)($importType) as $index => $type) {
                    $key = ':import_type_' . $index;
                    $typePlaceholders[] = $key;
                    $params[$key] = $type;
                }
                $sql .= ' AND evidence_type IN (' . implode(', ', $typePlaceholders) . ')';
            }
            $select = $this->pdo->prepare($sql);
            $select->execute($params);
            $storedRows = $select->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $update = $this->pdo->prepare("
                UPDATE ledger_evidence_payloads
                SET mapped_payload_json = :mapped_payload_json,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE evidence_id = :id
            ");
            $bodyUpdates = [];
            foreach ($storedRows as $storedRow) {
                $id = (string) ($storedRow['id'] ?? '');
                if ($id === '' || !isset($rows[$id])) {
                    continue;
                }
                $mappedPayload = json_decode((string) ($storedRow['mapped_payload_json'] ?? ''), true);
                $mappedPayload = is_array($mappedPayload) ? $mappedPayload : [];
                $currentColumnSortNo = is_numeric($storedRow['current_sort_no'] ?? null) ? max(0, (int) $storedRow['current_sort_no']) : 0;
                $currentPayloadSortNo = ($this->payloadSortNo)(['mapped_payload' => $mappedPayload], $sortKey);
                if ($currentColumnSortNo === $rows[$id] && $currentPayloadSortNo === $rows[$id]) {
                    continue;
                }
                $mappedPayload[$sortKey] = $rows[$id];
                $update->execute([
                    ':id' => $id,
                    ':mapped_payload_json' => ($this->jsonEncoder)($mappedPayload),
                    ':actor' => $actor,
                ]);
                $tableName = $this->evidenceTableForType((string) ($storedRow['evidence_type'] ?? $importType));
                if ($tableName !== '') {
                    if (!isset($bodyUpdates[$tableName])) {
                        $bodyUpdates[$tableName] = $this->prepareSortNoUpdateStatement($tableName);
                    }
                    $bodyUpdates[$tableName]->execute($this->sortNoUpdateParams($tableName, $id, $rows[$id], $actor));
                }
                if ($scope === 'status') {
                    $this->syncProcessingItemDisplayPathsForEvidence($id, $rows[$id], $actor);
                }
            }
            if ($scope === 'status') {
                $this->normalizeEvidenceStatusSortNoForType($importType, $actor, $rows);
            }

            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $throwable) {
            error_log('[EvidenceStatusService] reorder failed: ' . $throwable->getMessage());
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => '정렬 저장 중 오류가 발생했습니다.', 'status' => 500];
        }

        return ['success' => true, 'message' => '정렬이 저장되었습니다.'];
    }

    private function reorderBankRows(array $rows, string $sortKey, string $actor): array
    {
        $actor = $this->auditActor($actor);
        $ownsTransaction = !$this->pdo->inTransaction();

        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            $maxSortNo = (int) $this->pdo->query("
                SELECT COALESCE(MAX(sort_no), 0)
                FROM ledger_evidence_bank_transaction
            ")->fetchColumn();
            $tempBase = max($maxSortNo, 0) + 1000;

            $tempUpdateBody = $this->prepareSortNoUpdateStatement('ledger_evidence_bank_transaction');
            $updateBody = $this->prepareSortNoUpdateStatement('ledger_evidence_bank_transaction');

            $updatePayload = $this->pdo->prepare("
                UPDATE ledger_evidence_payloads
                SET mapped_payload_json = :mapped_payload_json,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE evidence_id = :id
                  AND evidence_type = 'BANK_TRANSACTION'
            ");

            [$inSql, $params] = ($this->placeholderBuilder)(array_keys($rows), 'bank_reorder_id');
            $payloadSelect = $this->pdo->prepare("
                SELECT evidence_id AS id, mapped_payload_json
                FROM ledger_evidence_payloads
                WHERE evidence_id IN ({$inSql})
                  AND evidence_type = 'BANK_TRANSACTION'
                  AND deleted_at IS NULL
            ");
            $payloadSelect->execute($params);

            $payloadRows = [];
            foreach (($payloadSelect->fetchAll(PDO::FETCH_ASSOC) ?: []) as $payloadRow) {
                $payloadId = trim((string) ($payloadRow['id'] ?? ''));
                if ($payloadId !== '') {
                    $payloadRows[$payloadId] = $payloadRow;
                }
            }

            $tempSortNos = [];
            $position = 0;
            foreach (array_keys($rows) as $id) {
                $position++;
                $tempSortNo = $tempBase + $position;
                $tempSortNos[$id] = $tempSortNo;
                $tempUpdateBody->execute($this->sortNoUpdateParams('ledger_evidence_bank_transaction', $id, $tempSortNo, $actor));
            }

            foreach ($rows as $id => $sortNo) {
                $updateBody->execute($this->sortNoUpdateParams('ledger_evidence_bank_transaction', $id, $sortNo, $actor));

                if (!isset($payloadRows[$id])) {
                    continue;
                }

                $mappedPayload = json_decode((string) ($payloadRows[$id]['mapped_payload_json'] ?? ''), true);
                $mappedPayload = is_array($mappedPayload) ? $mappedPayload : [];
                $mappedPayload[$sortKey] = $sortNo;
                $updatePayload->execute([
                    ':id' => $id,
                    ':mapped_payload_json' => ($this->jsonEncoder)($mappedPayload),
                    ':actor' => $actor,
                ]);
            }

            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $throwable) {
            error_log('[EvidenceStatusService] reorderBankRows failed: ' . $throwable->getMessage());
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['success' => false, 'message' => '정렬 저장 중 오류가 발생했습니다.', 'status' => 500];
        }

        return ['success' => true, 'message' => '정렬이 저장되었습니다.'];
    }

    private function evidenceTableForType(string $dataType): string
    {
        $normalized = strtoupper(trim($dataType));

        return match ($normalized) {
            'BANK_TRANSACTION' => 'ledger_evidence_bank_transaction',
            'TAX_INVOICE' => 'ledger_evidence_tax_invoice',
            'TAX_INVOICE_MANUAL' => 'ledger_evidence_tax_invoice_manual',
            'CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES' => 'ledger_evidence_cash_receipt',
            'CARD_HOMETAX' => 'ledger_evidence_card_hometax',
            'CARD', 'CARD_STATEMENT', 'CARD_APPROVAL' => 'ledger_evidence_card_statement',
            default => '',
        };
    }

    private function reorderCashReceiptRows(array $rows, string $sortKey, string $actor, string $importType): array
    {
        $actor = $this->auditActor($actor);
        $tableName = 'ledger_evidence_cash_receipt';
        if (!($this->tableColumnExists)($tableName, 'sort_no')) {
            return ['success' => false, 'message' => '정렬 저장 중 오류가 발생했습니다.', 'status' => 500];
        }

        $ownsTransaction = !$this->pdo->inTransaction();

        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }

            $maxSortNo = (int) $this->pdo->query("
                SELECT COALESCE(MAX(sort_no), 0)
                FROM {$tableName}
            ")->fetchColumn();
            $tempBase = max($maxSortNo, 0) + 1000;

            $tempUpdateBody = $this->prepareSortNoUpdateStatement($tableName);
            $updateBody = $this->prepareSortNoUpdateStatement($tableName);

            $evidenceTypes = array_values(array_unique(array_filter(
                ($this->queryDataTypes)($importType),
                static fn($type): bool => trim((string) $type) !== ''
            )));
            if ($evidenceTypes === []) {
                $evidenceTypes = [$importType];
            }

            $typePlaceholders = [];
            $typeParams = [];
            foreach ($evidenceTypes as $index => $type) {
                $key = ':cash_type_' . $index;
                $typePlaceholders[] = $key;
                $typeParams[$key] = $type;
            }
            $typeSql = implode(', ', $typePlaceholders);

            [$inSql, $params] = ($this->placeholderBuilder)(array_keys($rows), 'cash_reorder_id');
            $payloadSelect = $this->pdo->prepare("
                SELECT evidence_id AS id, mapped_payload_json
                FROM ledger_evidence_payloads
                WHERE evidence_id IN ({$inSql})
                  AND evidence_type IN ({$typeSql})
                  AND deleted_at IS NULL
            ");
            $payloadSelect->execute($params + $typeParams);

            $payloadRows = [];
            foreach (($payloadSelect->fetchAll(PDO::FETCH_ASSOC) ?: []) as $payloadRow) {
                $payloadId = trim((string) ($payloadRow['id'] ?? ''));
                if ($payloadId !== '') {
                    $payloadRows[$payloadId] = $payloadRow;
                }
            }

            $updatePayload = $this->pdo->prepare("
                UPDATE ledger_evidence_payloads
                SET mapped_payload_json = :mapped_payload_json,
                    updated_at = NOW(),
                    updated_by = :actor
                WHERE evidence_id = :id
                  AND evidence_type IN ({$typeSql})
            ");

            $position = 0;
            foreach (array_keys($rows) as $id) {
                $position++;
                $tempSortNo = $tempBase + $position;
                $tempUpdateBody->execute($this->sortNoUpdateParams($tableName, $id, $tempSortNo, $actor));
            }

            foreach ($rows as $id => $sortNo) {
                $updateBody->execute($this->sortNoUpdateParams($tableName, $id, $sortNo, $actor));

                if (!isset($payloadRows[$id])) {
                    continue;
                }

                $mappedPayload = json_decode((string) ($payloadRows[$id]['mapped_payload_json'] ?? ''), true);
                $mappedPayload = is_array($mappedPayload) ? $mappedPayload : [];
                $mappedPayload[$sortKey] = $sortNo;
                $updatePayload->execute([
                    ':id' => $id,
                    ':mapped_payload_json' => ($this->jsonEncoder)($mappedPayload),
                    ':actor' => $actor,
                ] + $typeParams);
            }

            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $throwable) {
            error_log('[EvidenceStatusService] reorderCashReceiptRows failed: ' . $throwable->getMessage());
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['success' => false, 'message' => '정렬 저장 중 오류가 발생했습니다.', 'status' => 500];
        }

        return ['success' => true, 'message' => '정렬이 저장되었습니다.'];
    }

    private function isCashReceiptType(string $dataType): bool
    {
        return in_array($dataType, ['CASH_RECEIPT', 'CASH_RECEIPT_PURCHASE', 'CASH_RECEIPT_SALES'], true);
    }

    private function prepareSortNoUpdateStatement(string $tableName): \PDOStatement
    {
        $sets = ['sort_no = :sort_no'];
        if (($this->tableColumnExists)($tableName, 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }
        if (($this->tableColumnExists)($tableName, 'updated_by')) {
            $sets[] = 'updated_by = :actor';
        }

        return $this->pdo->prepare("
            UPDATE {$tableName}
            SET " . implode(",\n                ", $sets) . "
            WHERE id = :id
        ");
    }

    private function sortNoUpdateParams(string $tableName, string $id, int $sortNo, string $actor): array
    {
        $params = [
            ':id' => $id,
            ':sort_no' => $sortNo,
        ];
        if (($this->tableColumnExists)($tableName, 'updated_by')) {
            $params[':actor'] = $actor;
        }

        return $params;
    }

    private function auditActor(string $actor): string
    {
        $actor = trim($actor);
        if ($actor === '') {
            return 'SYSTEM';
        }

        if (str_starts_with($actor, 'USER:')) {
            $userId = substr($actor, 5);
            return $userId !== '' ? substr($userId, 0, 36) : 'SYSTEM';
        }

        return substr($actor, 0, 36);
    }

    private function syncProcessingItemDisplayPathsForEvidence(string $evidenceId, int $rowNo, string $actor): void
    {
        if ($evidenceId === '' || $rowNo < 1 || !($this->tableExists)('ledger_processing_items')) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, parent_item_id, sort_no, display_path, created_at
            FROM ledger_processing_items
            WHERE source_table = 'ledger_data_evidences'
              AND source_id = :source_id
              AND deleted_at IS NULL
            ORDER BY parent_item_id ASC, sort_no ASC, created_at ASC
        ");
        $stmt->execute([':source_id' => $evidenceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($items === []) {
            return;
        }

        $childrenByParent = [];
        $roots = [];
        foreach ($items as $item) {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $parentId = trim((string) ($item['parent_item_id'] ?? ''));
            if ($parentId === '') {
                $roots[] = $item;
            } else {
                $childrenByParent[$parentId][] = $item;
            }
        }

        $sortItems = static function (array &$rows): void {
            usort($rows, static function (array $a, array $b): int {
                $sortA = (int) ($a['sort_no'] ?? 0);
                $sortB = (int) ($b['sort_no'] ?? 0);
                if ($sortA !== $sortB) {
                    return $sortA <=> $sortB;
                }
                return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
            });
        };
        $sortItems($roots);
        foreach ($childrenByParent as &$children) {
            $sortItems($children);
        }
        unset($children);

        $update = $this->pdo->prepare("
            UPDATE ledger_processing_items
            SET sort_no = :sort_no,
                display_path = :display_path,
                updated_at = NOW(),
                updated_by = :actor
            WHERE id = :id
        ");

        $walk = function (array $item, string $displayPath, int $sortNo) use (&$walk, $childrenByParent, $update, $actor): void {
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                return;
            }

            $update->execute([
                ':id' => $id,
                ':sort_no' => $sortNo,
                ':display_path' => $displayPath,
                ':actor' => $actor,
            ]);

            $children = $childrenByParent[$id] ?? [];
            foreach (array_values($children) as $index => $child) {
                $childSortNo = $index + 1;
                $walk($child, $displayPath . '-' . $childSortNo, $childSortNo);
            }
        };

        foreach (array_values($roots) as $index => $root) {
            $rootDisplayPath = (string) ($index === 0 ? $rowNo : ($rowNo . '-' . ($index + 1)));
            $rootSortNo = $index === 0 ? $rowNo : $index + 1;
            $walk($root, $rootDisplayPath, $rootSortNo);
        }
    }

    private function normalizeEvidenceStatusSortNoForType(string $importType, string $actor, array $priorityRows = []): void
    {
        $importType = ($this->dataTypeNormalizer)($importType);
        if ($importType === '') {
            return;
        }

        $params = [];
        $placeholders = [];
        foreach (($this->queryDataTypes)($importType) as $index => $type) {
            $key = ':normalize_type_' . $index;
            $placeholders[] = $key;
            $params[$key] = $type;
        }
        if ($placeholders === []) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                evidence_id AS id,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$._status_sort_no')) AS UNSIGNED) AS status_sort_no,
                mapped_payload_json
            FROM ledger_evidence_payloads
            WHERE deleted_at IS NULL
              AND evidence_type IN (" . implode(', ', $placeholders) . ")
            ORDER BY
                CASE
                    WHEN CAST(JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$._status_sort_no')) AS UNSIGNED) IS NULL
                      OR CAST(JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$._status_sort_no')) AS UNSIGNED) < 1 THEN 1
                    ELSE 0
                END ASC,
                CAST(JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$._status_sort_no')) AS UNSIGNED) ASC,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$.evidence_date')),
                    JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$.transaction_date')),
                    DATE(latest_imported_at),
                    DATE(created_at)
                ) DESC,
                latest_imported_at DESC,
                created_at DESC,
                evidence_id ASC
        ");
        $stmt->execute($params);
        $storedRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($storedRows === []) {
            return;
        }
        $normalizedPriorityRows = [];
        foreach ($priorityRows as $id => $sortNo) {
            $id = trim((string) $id);
            $sortNo = (int) $sortNo;
            if ($id !== '' && $sortNo > 0) {
                $normalizedPriorityRows[$id] = $sortNo;
            }
        }
        $priorityRows = $normalizedPriorityRows;
        if ($priorityRows !== []) {
            usort($storedRows, static function (array $left, array $right) use ($priorityRows): int {
                $leftId = (string) ($left['id'] ?? '');
                $rightId = (string) ($right['id'] ?? '');
                $leftPriority = $priorityRows[$leftId] ?? null;
                $rightPriority = $priorityRows[$rightId] ?? null;
                if ($leftPriority !== null || $rightPriority !== null) {
                    if ($leftPriority === null) {
                        return 1;
                    }
                    if ($rightPriority === null) {
                        return -1;
                    }
                    return $leftPriority <=> $rightPriority;
                }

                $leftSortNo = is_numeric($left['status_sort_no'] ?? null) ? (int) $left['status_sort_no'] : PHP_INT_MAX;
                $rightSortNo = is_numeric($right['status_sort_no'] ?? null) ? (int) $right['status_sort_no'] : PHP_INT_MAX;
                if ($leftSortNo !== $rightSortNo) {
                    return $leftSortNo <=> $rightSortNo;
                }

                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            });
        }

        $update = $this->pdo->prepare("
            UPDATE ledger_evidence_payloads
            SET mapped_payload_json = :mapped_payload_json,
                updated_at = NOW(),
                updated_by = :actor
            WHERE evidence_id = :id
        ");
        foreach ($storedRows as $index => $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $nextSortNo = $index + 1;
            $currentSortNo = is_numeric($row['status_sort_no'] ?? null) ? (int) $row['status_sort_no'] : 0;
            $mappedPayload = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
            $mappedPayload = is_array($mappedPayload) ? $mappedPayload : [];
            $currentPayloadSortNo = (int) ($mappedPayload['_status_sort_no'] ?? 0);
            if ($currentSortNo === $nextSortNo && $currentPayloadSortNo === $nextSortNo) {
                continue;
            }

            $mappedPayload['_status_sort_no'] = $nextSortNo;
            $update->execute([
                ':id' => $id,
                ':mapped_payload_json' => ($this->jsonEncoder)($mappedPayload),
                ':actor' => $actor,
            ]);
            $this->syncProcessingItemDisplayPathsForEvidence($id, $nextSortNo, $actor);
        }
    }
}
