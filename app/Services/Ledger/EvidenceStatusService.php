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
     * @param callable(array,string):int $payloadSortNo
     */
    public function __construct(
        private PDO $pdo,
        private $placeholderBuilder,
        private $jsonEncoder,
        private $dataTypeNormalizer,
        private $queryDataTypes,
        private $tableExists,
        private $payloadSortNo
    ) {
    }

    public function updateStatus(array $ids, string $status): array
    {
        $status = strtoupper(trim($status));
        if ($ids === [] || !in_array($status, ['READY', 'ERROR', 'DUPLICATED'], true)) {
            return ['success' => false, 'message' => '??? ??? Seed Data? ???? ?????.', 'status' => 400];
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

        return ['success' => true, 'message' => '?? Seed Data ??? ???????.'];
    }

    public function reorder(array $payload, string $actor): array
    {
        $changes = $payload['changes'] ?? [];
        if (is_string($changes)) {
            $decoded = json_decode($changes, true);
            $changes = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($changes) || $changes === []) {
            return ['success' => false, 'message' => '???? ???? ????.', 'status' => 400];
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
            return ['success' => false, 'message' => '???? ???? ???? ????.', 'status' => 400];
        }

        $firstChange = is_array(reset($changes)) ? reset($changes) : [];
        $scope = strtolower(trim((string) ($payload['scope'] ?? $payload['sort_scope'] ?? $firstChange['scope'] ?? $firstChange['sort_scope'] ?? 'create')));
        $sortKey = $scope === 'status' ? '_status_sort_no' : '_create_sort_no';
        $importType = ($this->dataTypeNormalizer)((string) ($payload['import_type'] ?? $payload['data_type'] ?? $firstChange['import_type'] ?? $firstChange['data_type'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            [$inSql, $params] = ($this->placeholderBuilder)(array_keys($rows), 'reorder_id');
            $sql = "
                SELECT
                    evidence_id AS id,
                    mapped_payload_json,
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(mapped_payload_json, '$.{$sortKey}')) AS UNSIGNED) AS current_sort_no
                FROM ledger_evidence_payloads
                WHERE evidence_id IN ({$inSql})
                  AND deleted_at IS NULL
            ";
            if ($scope === 'status') {
                if ($importType === '') {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => '???? ?? ???? ????? ?????.', 'status' => 400];
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
                if ($scope === 'status') {
                    $this->syncProcessingItemDisplayPathsForEvidence($id, $rows[$id], $actor);
                }
            }
            if ($scope === 'status') {
                $this->normalizeEvidenceStatusSortNoForType($importType, $actor, $rows);
            }

            $this->pdo->commit();
        } catch (\Throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => '???? ??? ??????.', 'status' => 500];
        }

        return ['success' => true, 'message' => '??? ???????.'];
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
