<?php

namespace App\Services\Ledger;

use PDO;

class EvidenceTrashService
{
    /**
     * @param callable(array,string):array $placeholderBuilder
     * @param callable(string):string $dataTypeNormalizer
     * @param callable(string):array $queryDataTypes
     * @param callable(array):bool $hasActiveOutput
     * @param callable(array,string):void $softDeleteProcessing
     * @param callable(array):void $softDeleteLinks
     * @param callable(array,string):void $softDeleteBody
     * @param callable(array,string):void $syncBankSoftDelete
     * @param callable(array,string):void $restoreProcessing
     * @param callable(array,string):void $restoreBody
     * @param callable(array,string):void $syncBankRestore
     * @param callable(array):int $purgeRows
     */
    public function __construct(
        private PDO $pdo,
        private $placeholderBuilder,
        private $dataTypeNormalizer,
        private $queryDataTypes,
        private $hasActiveOutput,
        private $softDeleteProcessing,
        private $softDeleteLinks,
        private $softDeleteBody,
        private $syncBankSoftDelete,
        private $restoreProcessing,
        private $restoreBody,
        private $syncBankRestore,
        private $purgeRows
    ) {
    }

    public function trashQueryParams(array $query): array
    {
        $query['status'] = 'DELETED';
        return $query;
    }

    public function delete(array $ids, string $actor): array
    {
        if ($ids === []) {
            return ['success' => false, 'message' => '??? Seed Data? ?????.', 'status' => 400];
        }

        try {
            $this->pdo->beginTransaction();
            $this->releaseSeedRowsWithoutActiveOutputs($ids);

            $deletableIds = $this->deletableSeedRowIds($ids);
            if ($deletableIds === []) {
                $blocked = $this->seedRowDeleteBlockSummary($ids);
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => true,
                    'message' => $blocked !== '' ? '??? ??? ????. ' . $blocked : '??? ??? ????.',
                    'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)],
                ];
            }

            [$inSql, $params] = ($this->placeholderBuilder)($deletableIds, 'seed_id');
            $params[':deleted_by'] = $actor;
            $params[':updated_by'] = $actor;
            $stmt = $this->pdo->prepare("
                UPDATE ledger_evidence_payloads p
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_type = p.evidence_type
                   AND pr.evidence_id = p.evidence_id
                   AND pr.deleted_at IS NULL
                SET p.deleted_at = NOW(),
                    p.deleted_by = :deleted_by,
                    p.updated_at = NOW(),
                    p.updated_by = :updated_by
                WHERE p.evidence_id IN ({$inSql})
                  AND COALESCE(pr.processing_status, 'READY') IN ('NONE', 'READY', 'REVIEW_REQUIRED', 'ERROR', 'DUPLICATED')
                  AND p.deleted_at IS NULL
            ");
            $stmt->execute($params);

            $deletedCount = $stmt->rowCount();
            ($this->softDeleteProcessing)($deletableIds, $actor);
            ($this->softDeleteLinks)($deletableIds);
            ($this->softDeleteBody)($deletableIds, $actor);
            ($this->syncBankSoftDelete)($deletableIds, $actor);
            if ($deletedCount === 0) {
                $blocked = $this->seedRowDeleteBlockSummary($ids);
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => true,
                    'message' => $blocked !== '' ? '??? ??? ????. ' . $blocked : '??? ??? ????.',
                    'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)],
                ];
            }

            $this->pdo->commit();
            $skippedIds = array_values(array_diff($ids, $deletableIds));
            $skippedCount = max(0, count($ids) - $deletedCount);
            $blocked = $skippedCount > 0 ? $this->seedRowDeleteBlockSummary($skippedIds) : '';
            $message = "?? Seed Data {$deletedCount}?? ???????.";
            if ($skippedCount > 0) {
                $message .= $blocked !== ''
                    ? " ?? {$skippedCount}?: {$blocked}"
                    : " ??? ? ?? {$skippedCount}?? ???????.";
            }

            return [
                'success' => true,
                'message' => $message,
                'data' => ['deleted_count' => $deletedCount, 'skipped_count' => $skippedCount],
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[EvidenceTrashService] delete failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '?? ?? ? ??? ??????: ' . $e->getMessage(), 'status' => 500];
        }
    }

    public function restore(array $ids, string $actor): array
    {
        if ($ids === []) {
            return ['success' => false, 'message' => '??? Seed Data? ?????.', 'status' => 400];
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'seed_id');
        $params[':actor'] = $actor;
        $stmt = $this->pdo->prepare("
            UPDATE ledger_evidence_payloads
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $restoredCount = $stmt->rowCount();
        ($this->restoreProcessing)($ids, $actor);
        ($this->restoreBody)($ids, $actor);
        ($this->syncBankRestore)($ids, $actor);

        return [
            'success' => true,
            'message' => "?? Seed Data {$restoredCount}?? ???????.",
            'data' => ['restored_count' => $restoredCount, 'skipped_count' => max(0, count($ids) - $restoredCount)],
        ];
    }

    public function restoreAll(string $importType, string $actor): array
    {
        $ids = $this->deletedSeedRowIds($importType);
        [$scopeSql, $scopeParams] = $this->seedRowImportTypeSqlScope($importType, 'restore_all_type', 'evidence_type');
        $stmt = $this->pdo->prepare("
            UPDATE ledger_evidence_payloads
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE deleted_at IS NOT NULL
            {$scopeSql}
        ");
        $stmt->execute([':actor' => $actor] + $scopeParams);
        $restoredCount = $stmt->rowCount();
        ($this->restoreProcessing)($ids, $actor);
        ($this->restoreBody)($ids, $actor);
        ($this->syncBankRestore)($ids, $actor);

        return ['success' => true, 'message' => "??? Seed Data {$restoredCount}?? ???????.", 'data' => ['restored_count' => $restoredCount]];
    }

    public function purge(array $ids): array
    {
        if ($ids === []) {
            return ['success' => false, 'message' => '?? ??? Seed Data? ?????.', 'status' => 400];
        }

        $purgeableIds = $this->purgeableSeedRowIds($ids);
        if ($purgeableIds === []) {
            return ['success' => true, 'message' => '?? ??? ??? ????. ???? ?? ??? ?????.', 'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)]];
        }

        $deletedCount = ($this->purgeRows)($purgeableIds);
        return [
            'success' => true,
            'message' => "?? Seed Data {$deletedCount}?? ?? ???????.",
            'data' => ['deleted_count' => $deletedCount, 'skipped_count' => max(0, count($ids) - $deletedCount)],
        ];
    }

    public function purgeAll(string $importType): array
    {
        $purgeableIds = $this->purgeableSeedRowIds([], $importType);
        if ($purgeableIds === []) {
            return ['success' => true, 'message' => '?? ??? ??? Seed Data? ????.', 'data' => ['deleted_count' => 0]];
        }

        $deletedCount = ($this->purgeRows)($purgeableIds);
        return ['success' => true, 'message' => "??? Seed Data {$deletedCount}?? ?? ???????.", 'data' => ['deleted_count' => $deletedCount]];
    }

    private function releaseSeedRowsWithoutActiveOutputs(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return;
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'release_seed_id');
        $stmt = $this->pdo->prepare("
            SELECT
                p.evidence_id AS id,
                p.evidence_type AS source_type,
                p.mapped_payload_json,
                pr.processing_status AS transaction_status,
                tx.target_id AS transaction_id
            FROM ledger_evidence_payloads p
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = p.evidence_type
               AND pr.evidence_id = p.evidence_id
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type = p.evidence_type
               AND tx.evidence_id = p.evidence_id
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            WHERE p.evidence_id IN ({$inSql})
              AND p.deleted_at IS NULL
              AND COALESCE(pr.processing_status, 'READY') NOT IN ('NONE', 'READY', 'REVIEW_REQUIRED', 'ERROR', 'DUPLICATED')
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $releaseIds = [];
        foreach ($rows as $row) {
            if (!($this->hasActiveOutput)($row)) {
                $releaseIds[] = (string) ($row['id'] ?? '');
            }
        }

        $releaseIds = array_values(array_unique(array_filter($releaseIds)));
        if ($releaseIds === []) {
            return;
        }

        [$releaseInSql, $releaseParams] = ($this->placeholderBuilder)($releaseIds, 'release_id');
        $this->pdo->prepare("
            UPDATE ledger_evidence_processing
            SET processing_status = 'READY',
                last_error_message = NULL,
                updated_at = NOW(),
                deleted_at = NULL
            WHERE evidence_id IN ({$releaseInSql})
              AND deleted_at IS NULL
        ")->execute($releaseParams);
    }

    private function deletableSeedRowIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return [];
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'deletable_seed_id');
        $stmt = $this->pdo->prepare("
            SELECT p.evidence_id
            FROM ledger_evidence_payloads p
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = p.evidence_type
               AND pr.evidence_id = p.evidence_id
               AND pr.deleted_at IS NULL
            WHERE p.evidence_id IN ({$inSql})
              AND COALESCE(pr.processing_status, 'READY') IN ('NONE', 'READY', 'REVIEW_REQUIRED', 'ERROR', 'DUPLICATED')
              AND p.deleted_at IS NULL
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function seedRowDeleteBlockSummary(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return '';
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'delete_block_id');
        $stmt = $this->pdo->prepare("
            SELECT
                p.evidence_id AS id,
                p.evidence_type AS source_type,
                p.source_key,
                NULL AS evidence_date,
                COALESCE(pr.processing_status, 'READY') AS transaction_status,
                p.deleted_at,
                p.mapped_payload_json,
                tx.target_id AS transaction_id
            FROM ledger_evidence_payloads p
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type = p.evidence_type
               AND pr.evidence_id = p.evidence_id
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type = p.evidence_type
               AND tx.evidence_id = p.evidence_id
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            WHERE p.evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return '??? ??? ?? ? ????.';
        }

        $counts = ['deleted' => 0, 'generated' => 0, 'status' => 0];
        $samples = [];
        foreach ($rows as $row) {
            $reason = '';
            if (!empty($row['deleted_at'])) {
                $counts['deleted']++;
                $reason = '?? ???';
            } elseif (($this->hasActiveOutput)($row)) {
                $counts['generated']++;
                $reason = '??/?? ?? ?? ??';
            } elseif (!in_array((string) ($row['transaction_status'] ?? ''), ['NONE', 'ERROR', 'DUPLICATED'], true)) {
                $counts['status']++;
                $reason = '?????? ' . (string) ($row['transaction_status'] ?? '-');
            }

            if ($reason !== '' && count($samples) < 3) {
                $samples[] = $this->seedRowDeleteBlockLabel($row) . ' - ' . $reason;
            }
        }

        $parts = [];
        if ($counts['generated'] > 0) {
            $parts[] = '??/?? ?? ??? ???? ?? ' . $counts['generated'] . '?';
        }
        if ($counts['deleted'] > 0) {
            $parts[] = '?? ??? ?? ' . $counts['deleted'] . '?';
        }
        if ($counts['status'] > 0) {
            $parts[] = '?? ?? ??? ?? ?? ' . $counts['status'] . '?';
        }

        if ($parts === []) {
            return '?? ??? ?? ??? ?? ????.';
        }

        return implode(', ', $parts) . ($samples !== [] ? ' (' . implode(' / ', $samples) . ')' : '') . '.';
    }

    private function seedRowDeleteBlockLabel(array $row): string
    {
        $mapped = json_decode((string) ($row['mapped_payload_json'] ?? ''), true);
        $mapped = is_array($mapped) ? $mapped : [];
        $rowNo = $mapped['_status_sort_no'] ?? $mapped['_create_sort_no'] ?? '';
        $sourceKey = trim((string) ($row['source_key'] ?? ''));
        $date = trim((string) ($row['evidence_date'] ?? $mapped['transaction_date'] ?? $mapped['evidence_date'] ?? ''));
        $label = trim(implode(' ', array_filter([$rowNo !== '' ? '#' . $rowNo : '', $date, $sourceKey])));

        return $label !== '' ? $label : (string) ($row['id'] ?? 'Seed Data');
    }

    private function purgeableSeedRowIds(array $ids = [], string $importType = ''): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        $params = [];
        $where = ['deleted_at IS NOT NULL'];
        if ($ids !== []) {
            [$inSql, $params] = ($this->placeholderBuilder)($ids, 'purge_seed_id');
            $where[] = "evidence_id IN ({$inSql})";
        }
        [$scopeSql, $scopeParams] = $this->seedRowImportTypeSqlScope($importType, 'purge_type', 'evidence_type');
        if ($scopeSql !== '') {
            $where[] = trim(preg_replace('/^AND\s+/i', '', $scopeSql));
            $params += $scopeParams;
        }

        $stmt = $this->pdo->prepare('SELECT evidence_id FROM ledger_evidence_payloads WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function deletedSeedRowIds(string $importType = ''): array
    {
        [$scopeSql, $params] = $this->seedRowImportTypeSqlScope($importType, 'deleted_type', 'evidence_type');
        $stmt = $this->pdo->prepare("
            SELECT evidence_id
            FROM ledger_evidence_payloads
            WHERE deleted_at IS NOT NULL
            {$scopeSql}
        ");
        $stmt->execute($params);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function seedRowImportTypeSqlScope(string $importType, string $prefix, string $column = 'source_type'): array
    {
        $importType = ($this->dataTypeNormalizer)($importType);
        if ($importType === '') {
            return ['', []];
        }

        $placeholders = [];
        $params = [];
        foreach (($this->queryDataTypes)($importType) as $index => $type) {
            $key = ':' . $prefix . '_' . $index;
            $placeholders[] = $key;
            $params[$key] = $type;
        }
        if ($placeholders === []) {
            return ['', []];
        }

        return [' AND ' . $column . ' IN (' . implode(', ', $placeholders) . ')', $params];
    }
}
