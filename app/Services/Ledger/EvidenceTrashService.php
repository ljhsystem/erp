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
 * @param callable(array,string,?string):void $softDeleteBody
     * @param callable(array,string):void $syncBankSoftDelete
     * @param callable(array,string):void $restoreProcessing
 * @param callable(array,string,?string):void $restoreBody
     * @param callable(array,string):void $syncBankRestore
 * @param callable(array,?string):int $purgeRows
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

    public function delete(array $ids, string $actor, ?string $evidenceType = null): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return ['success' => false, 'message' => $this->msg('7IKt7KCc7ZWgIOymneu5meybkOuzuOydhCDshKDtg53tlbQg7KO87IS47JqULg=='), 'status' => 400];
        }

        $actor = $this->auditActor($actor);

        try {
            $this->pdo->beginTransaction();
            $this->releaseSeedRowsWithoutActiveOutputs($ids);

            $deletableIds = $this->deletableSeedRowIds($ids, (string) ($evidenceType ?? ''));
            $alreadyDeletedIds = $this->alreadyDeletedSeedRowIds($ids, (string) ($evidenceType ?? ''));
            $blockedIds = array_values(array_diff($ids, array_merge($deletableIds, $alreadyDeletedIds)));
            $alreadyDeletedCount = count($alreadyDeletedIds);
            if ($deletableIds === []) {
                $blocked = $this->seedRowDeleteBlockSummary($blockedIds, (string) ($evidenceType ?? ''));
                if ($alreadyDeletedCount > 0) {
                    $blocked .= $blocked === '' ? '' : ' ';
                    $blocked .= '(이미 삭제된 항목 ' . number_format($alreadyDeletedCount) . '건 제외)';
                }
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => true,
                    'message' => $blocked !== '' ? $this->msg('7IKt7KCc7ZWgIOyImCDsl4bripQg7ZWt66qp7J20IOyeiOyKteuLiOuLpC4g') . $blocked : $this->msg('7IKt7KCc7ZWgIOyImCDsl4bripQg7ZWt66qp7J20IOyeiOyKteuLiOuLpC4='),
                    'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)],
                ];
            }

            [$inSql, $params] = ($this->placeholderBuilder)($deletableIds, 'seed_id');
            $params[':deleted_by'] = $actor;
            $params[':updated_by'] = $actor;
            $stmt = $this->pdo->prepare("
                UPDATE ledger_evidence_payloads p
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
                   AND pr.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
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

            $deletedCount = max($stmt->rowCount(), count($deletableIds));
            ($this->softDeleteProcessing)($deletableIds, $actor);
            ($this->softDeleteLinks)($deletableIds);
            ($this->softDeleteBody)($deletableIds, $actor, $evidenceType);
            ($this->syncBankSoftDelete)($deletableIds, $actor);
            if ($deletedCount === 0) {
                    $blocked = $this->seedRowDeleteBlockSummary($blockedIds, (string) ($evidenceType ?? ''));
                if ($alreadyDeletedCount > 0) {
                    $blocked .= $blocked === '' ? '' : ' ';
                    $blocked .= '(이미 삭제된 항목 ' . number_format($alreadyDeletedCount) . '건 제외)';
                }
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return [
                    'success' => true,
                    'message' => $blocked !== '' ? $this->msg('7IKt7KCc7ZWgIOyImCDsl4bripQg7ZWt66qp7J20IOyeiOyKteuLiOuLpC4g') . $blocked : $this->msg('7IKt7KCc7ZWgIOyImCDsl4bripQg7ZWt66qp7J20IOyeiOyKteuLiOuLpC4='),
                    'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)],
                ];
            }

            $this->pdo->commit();
            $skippedIds = array_values(array_diff($ids, $deletableIds));
            $skippedCount = max(0, count($ids) - $deletedCount);
            $blocked = $skippedCount > 0 ? $this->seedRowDeleteBlockSummary($blockedIds, (string) ($evidenceType ?? '')) : '';
            if ($alreadyDeletedCount > 0) {
                $blocked .= $blocked === '' ? '' : ' ';
                $blocked .= '(이미 삭제된 항목 ' . number_format($alreadyDeletedCount) . '건 제외)';
            }
            $message = $this->msg('7Kad67mZ7JuQ67O4IA==') . $deletedCount . $this->msg('6rG07J2EIO2ctOyngO2GteycvOuhnCDsnbTrj5ntlojsirXri4jri6Qu');
            if ($skippedCount > 0) {
                $message .= $blocked !== ''
                    ? $this->msg('IOyCreygnOuQmOyngCDslYrsnYAg') . $skippedCount . $this->msg('6rG0OiA=') . $blocked
                    : $this->msg('IOyCreygnOuQmOyngCDslYrsnYAg') . $skippedCount . $this->msg('6rG07J20IOyeiOyKteuLiOuLpC4=');
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
            return ['success' => false, 'message' => $this->msg('7IKt7KCcIOykkSDsmKTrpZjqsIAg67Cc7IOd7ZaI7Iq164uI64ukLg=='), 'status' => 500];
        }
    }

    public function restore(array $ids, string $actor, ?string $evidenceType = null): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return ['success' => false, 'message' => $this->msg('67O16rWs7ZWgIOymneu5meybkOuzuOydhCDshKDtg53tlbQg7KO87IS47JqULg=='), 'status' => 400];
        }

        $actor = $this->auditActor($actor);

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
        ($this->restoreBody)($ids, $actor, $evidenceType);
        ($this->syncBankRestore)($ids, $actor);

        return [
            'success' => true,
            'message' => $this->msg('7Kad67mZ7JuQ67O4IA==') . $restoredCount . $this->msg('6rG07J2EIOuzteq1rO2WiOyKteuLiOuLpC4='),
            'data' => ['restored_count' => $restoredCount, 'skipped_count' => max(0, count($ids) - $restoredCount)],
        ];
    }

    public function restoreAll(string $importType, string $actor): array
    {
        $actor = $this->auditActor($actor);
        $ids = $this->deletedSeedRowIds($importType);
        if ($ids === []) {
            return ['success' => true, 'message' => $this->msg('7Zy07KeA7Ya1IOymneu5meybkOuzuCAw6rG07J2EIOuzteq1rO2WiOyKteuLiOuLpC4='), 'data' => ['restored_count' => 0]];
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'restore_all_seed_id');
        $stmt = $this->pdo->prepare("
            UPDATE ledger_evidence_payloads
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_at = NOW(),
                updated_by = :actor
            WHERE evidence_id IN ({$inSql})
              AND deleted_at IS NOT NULL
        ");
        $stmt->execute([':actor' => $actor] + $params);
        $restoredCount = $stmt->rowCount();
        ($this->restoreProcessing)($ids, $actor);
        ($this->restoreBody)($ids, $actor, $importType);
        ($this->syncBankRestore)($ids, $actor);

        return ['success' => true, 'message' => $this->msg('7Zy07KeA7Ya1IOymneu5meybkOuzuCA=') . $restoredCount . $this->msg('6rG07J2EIOuzteq1rO2WiOyKteuLiOuLpC4='), 'data' => ['restored_count' => $restoredCount]];
    }

    public function purge(array $ids, ?string $evidenceType = null): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return ['success' => false, 'message' => $this->msg('7JiB6rWs7IKt7KCc7ZWgIOymneu5meybkOuzuOydhCDshKDtg53tlbQg7KO87IS47JqULg=='), 'status' => 400];
        }

        $purgeableIds = $this->purgeableSeedRowIds($ids, (string) ($evidenceType ?? ''));
        if ($purgeableIds === []) {
            return ['success' => true, 'message' => $this->msg('7JiB6rWs7IKt7KCcIOqwgOuKpe2VnCDtla3rqqnsnbQg7JeG7Iq164uI64ukLiDsnbTrr7gg7IKt7KCc65CY7JeI6rGw64KYIOyhsOqxtOyXkCDrp57sp4Ag7JWK7Iq164uI64ukLg=='), 'data' => ['deleted_count' => 0, 'skipped_count' => count($ids)]];
        }

        $deletedCount = ($this->purgeRows)($purgeableIds, $evidenceType);
        return [
            'success' => true,
            'message' => $this->msg('7Kad67mZ7JuQ67O4IA==') . $deletedCount . $this->msg('6rG07J2EIOyYgeq1rOyCreygnO2WiOyKteuLiOuLpC4='),
            'data' => ['deleted_count' => $deletedCount, 'skipped_count' => max(0, count($ids) - $deletedCount)],
        ];
    }

    public function purgeAll(string $importType): array
    {
        $selection = $this->deletedSeedRowSelection([], $importType);
        $purgeableIds = $selection['ids'];
        $this->logPurgeAllDiagnostics($importType, $selection);
        if ($purgeableIds === []) {
            $this->logPurgeAllCountComparison($selection);
            return ['success' => true, 'message' => $this->msg('7JiB6rWs7IKt7KCc7ZWgIO2ctOyngO2GtSDspp3ruZnsm5Drs7jsnbQg7JeG7Iq164uI64ukLg=='), 'data' => ['deleted_count' => 0]];
        }

        $deletedCount = ($this->purgeRows)($purgeableIds, $importType);
        error_log('[EvidenceTrashService] purge_all_delete_result=' . json_encode([
            'delete_table' => 'ledger_evidence_payloads',
            'delete_target_count' => count($purgeableIds),
            'delete_target_id_sample' => array_slice($purgeableIds, 0, 5),
            'delete_affected_rows' => $deletedCount,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return ['success' => true, 'message' => $this->msg('7Zy07KeA7Ya1IOymneu5meybkOuzuCA=') . $deletedCount . $this->msg('6rG07J2EIOyYgeq1rOyCreygnO2WiOyKteuLiOuLpC4='), 'data' => ['deleted_count' => $deletedCount]];
    }

    private function releaseSeedRowsWithoutActiveOutputs(array $ids): void
    {
        $ids = $this->normalizeIdList($ids);
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
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND tx.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
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

    private function deletableSeedRowIds(array $ids, string $evidenceType = ''): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return [];
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'deletable_seed_id');
        $stmt = $this->pdo->prepare("
            SELECT p.evidence_id
            FROM ledger_evidence_payloads p
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
               AND pr.deleted_at IS NULL
            WHERE p.evidence_id IN ({$inSql})
              AND COALESCE(pr.processing_status, 'READY') IN ('NONE', 'READY', 'REVIEW_REQUIRED', 'ERROR', 'DUPLICATED')
              AND p.deleted_at IS NULL
        ");
        $stmt->execute($params);

        $deletableIds = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        $remainingIds = array_values(array_diff($ids, $deletableIds));
        if ($remainingIds === []) {
            return $deletableIds;
        }

        $bodyIds = $this->bodyRowIdsByDeleteStatus(
            $remainingIds,
            $evidenceType,
            'active',
            'deletable_body_seed_id'
        );

        return array_values(array_unique(array_merge($deletableIds, $bodyIds)));
    }

    private function alreadyDeletedSeedRowIds(array $ids, string $evidenceType = ''): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return [];
        }

        [$inSql, $params] = ($this->placeholderBuilder)($ids, 'already_deleted_seed_id');
        $stmt = $this->pdo->prepare("
            SELECT p.evidence_id AS id
            FROM ledger_evidence_payloads p
            WHERE p.evidence_id IN ({$inSql})
              AND p.deleted_at IS NOT NULL
        ");
        $stmt->execute($params);
        $deletedIds = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));

        $remainingIds = array_values(array_diff($ids, $deletedIds));
        if ($remainingIds === []) {
            return $deletedIds;
        }

        $bodyDeletedIds = $this->bodyRowIdsByDeleteStatus(
            $remainingIds,
            $evidenceType,
            'deleted',
            'already_deleted_body_seed_id'
        );

        return array_values(array_unique(array_merge($deletedIds, $bodyDeletedIds)));
    }

    private function seedRowDeleteBlockSummary(array $ids, string $evidenceType = ''): string
    {
        $ids = $this->normalizeIdList($ids);
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
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
               AND pr.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links tx
                ON tx.evidence_type COLLATE utf8mb4_unicode_ci = p.evidence_type COLLATE utf8mb4_unicode_ci
               AND tx.evidence_id COLLATE utf8mb4_unicode_ci = p.evidence_id COLLATE utf8mb4_unicode_ci
               AND tx.target_type = 'TRANSACTION'
               AND tx.deleted_at IS NULL
            WHERE p.evidence_id IN ({$inSql})
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $resolvedIds = array_values(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['id'] ?? '')),
            $rows
        )));
        $remainingIds = array_values(array_diff($ids, $resolvedIds));
        if ($remainingIds !== []) {
            $rows = array_merge($rows, $this->bodyRowsForDeleteBlockSummary(
                $remainingIds,
                $evidenceType,
                'delete_block_body_id'
            ));
        }
        if ($rows === []) {
            return $this->msg('7IKt7KCcIOuMgOyDgSDrjbDsnbTthLDrpbwg7LC+7J2EIOyImCDsl4bsirXri4jri6Qu');
        }

        $counts = ['deleted' => 0, 'generated' => 0, 'status' => 0];
        $samples = [];
        foreach ($rows as $row) {
            $reason = '';
            if (!empty($row['deleted_at'])) {
                $counts['deleted']++;
                $reason = $this->msg('7J2066+4IOyCreygnOuQqA==');
            } elseif (($this->hasActiveOutput)($row)) {
                $counts['generated']++;
                $reason = $this->msg('7Jew6rKw65CcIOqxsOuemCDrmJDripQg7KCE7ZGc6rCAIOyhtOyerO2VqA==');
            } elseif (!in_array((string) ($row['transaction_status'] ?? ''), ['NONE', 'ERROR', 'DUPLICATED'], true)) {
                $counts['status']++;
                $reason = $this->msg('7LKY66as7IOB7YOcIA==') . (string) ($row['transaction_status'] ?? '-');
            }

            if ($reason !== '' && count($samples) < 3) {
                $samples[] = $this->seedRowDeleteBlockLabel($row) . ' - ' . $reason;
            }
        }

        $parts = [];
        if ($counts['generated'] > 0) {
            $parts[] = $this->msg('7Jew6rKw65CcIOqxsOuemCDrmJDripQg7KCE7ZGc6rCAIOyeiOuKlCDtla3rqqkg') . $counts['generated'] . $this->msg('6rG0');
        }
        if ($counts['deleted'] > 0) {
            $parts[] = $this->msg('7J2066+4IOyCreygnOuQnCDtla3rqqkg') . $counts['deleted'] . $this->msg('6rG0');
        }
        if ($counts['status'] > 0) {
            $parts[] = $this->msg('7LKY66asIOyDge2DnOuhnCDsgq3soJztlaAg7IiYIOyXhuuKlCDtla3rqqkg') . $counts['status'] . $this->msg('6rG0');
        }

        if ($parts === []) {
            return $this->msg('7IKt7KCc7ZWgIOyImCDsl4bripQg7ZWt66qp7J20IOyeiOyKteuLiOuLpC4=');
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
        return $this->deletedSeedRowSelection($ids, $importType)['ids'];
    }

    private function deletedSeedRowIds(string $importType = ''): array
    {
        return $this->deletedSeedRowSelection([], $importType)['ids'];
    }

    private function deletedSeedRowSelection(array $ids = [], string $importType = ''): array
    {
        $ids = $this->normalizeIdList($ids);
        $importType = ($this->dataTypeNormalizer)($importType);
        $resolvedEvidenceTypes = $importType !== '' ? [$importType] : [];
        $where = [];
        $params = [];

        if ($ids !== []) {
            [$inSql, $params] = ($this->placeholderBuilder)($ids, 'deleted_seed_id');
            $where[] = "r.evidence_id IN ({$inSql})";
        }

        if ($importType !== '') {
            $where[] = 'r.evidence_type COLLATE utf8mb4_unicode_ci = :import_type COLLATE utf8mb4_unicode_ci';
            $params[':import_type'] = $importType;
        }

        $where[] = 'r.deleted_at IS NOT NULL';
        $whereSql = implode(' AND ', $where);

        $idsSql = "
            SELECT r.evidence_id
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = r.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = r.evidence_id COLLATE utf8mb4_unicode_ci
            WHERE {$whereSql}
        ";
        $stmt = $this->pdo->prepare($idsSql);
        $stmt->execute($params);
        $selectedIds = array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        $remainingIds = array_values(array_diff($ids, $selectedIds));

        $bodyTables = $this->deletedBodyTablesForImportType($importType);
        foreach ($bodyTables as $bodyIndex => $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $bodyWhere = ['body.deleted_at IS NOT NULL'];
            $bodyParams = [];
            $bodyRemainingIds = array_values(array_diff($ids, $selectedIds));
            if ($bodyRemainingIds !== []) {
                [$bodyInSql, $bodyParams] = ($this->placeholderBuilder)($bodyRemainingIds, 'deleted_body_' . $bodyIndex . '_id');
                $bodyWhere[] = "body.id IN ({$bodyInSql})";
            } elseif ($ids !== []) {
                continue;
            }

            $bodyStmt = $this->pdo->prepare("
                SELECT body.id
                FROM {$table} body
                WHERE " . implode(' AND ', $bodyWhere)
            );
            $bodyStmt->execute($bodyParams);
            $bodyIds = array_values(array_filter(array_map('strval', $bodyStmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
            $selectedIds = array_values(array_unique(array_merge($selectedIds, $bodyIds)));
        }

        return [
            'request_import_type' => $importType,
            'resolved_evidence_types' => $resolvedEvidenceTypes,
            'where_sql' => $whereSql,
            'binding_params' => $params,
            'ids' => $selectedIds,
        ];
    }

    private function deletedSeedRowListCount(array $selection): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = r.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = r.evidence_id COLLATE utf8mb4_unicode_ci
            WHERE {$selection['where_sql']}
        ");
        $stmt->execute($selection['binding_params']);

        return (int) $stmt->fetchColumn();
    }

    private function deletedBodyTablesForImportType(string $importType = ''): array
    {
        return match ($importType) {
            'BANK_TRANSACTION' => ['ledger_evidence_bank_transaction'],
            'TAX_INVOICE' => ['ledger_evidence_tax_invoice'],
            'TAX_INVOICE_MANUAL' => ['ledger_evidence_tax_invoice_manual'],
            'CASH_RECEIPT' => ['ledger_evidence_cash_receipt'],
            'CARD_HOMETAX' => ['ledger_evidence_card_hometax'],
            'CARD', 'CARD_STATEMENT', 'CARD_APPROVAL' => ['ledger_evidence_card_statement'],
            'EMPLOYEE_EXPENSE' => ['ledger_evidence_employee_expense'],
            'PAYROLL', 'PAYROLL_WITHHOLDING' => ['ledger_evidence_payroll'],
            'BUSINESS_INCOME' => ['ledger_evidence_business_income'],
            'BUSINESS_DATA' => ['ledger_evidence_cash_sales'],
            'CONSTRUCTION' => ['ledger_evidence_daily_worker'],
            'SHOPPING_ORDER', 'IMPORT_INVOICE' => ['ledger_evidence_business_data'],
            default => [
                'ledger_evidence_bank_transaction',
                'ledger_evidence_tax_invoice',
                'ledger_evidence_tax_invoice_manual',
                'ledger_evidence_cash_receipt',
                'ledger_evidence_card_hometax',
                'ledger_evidence_card_statement',
                'ledger_evidence_card_sales',
                'ledger_evidence_employee_expense',
                'ledger_evidence_payroll',
                'ledger_evidence_daily_worker',
                'ledger_evidence_business_income',
                'ledger_evidence_cash_sales',
            ],
        };
    }

    private function activeBodyTablesForImportType(string $importType = ''): array
    {
        return $this->deletedBodyTablesForImportType($importType);
    }

    private function bodyRowIdsByDeleteStatus(array $ids, string $evidenceType, string $status, string $prefix): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return [];
        }

        $tables = $status === 'deleted'
            ? $this->deletedBodyTablesForImportType(($this->dataTypeNormalizer)($evidenceType))
            : $this->activeBodyTablesForImportType(($this->dataTypeNormalizer)($evidenceType));

        $selectedIds = [];
        foreach ($tables as $index => $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            [$inSql, $params] = ($this->placeholderBuilder)($ids, $prefix . '_' . $index);
            $deletedClause = $status === 'deleted' ? 'IS NOT NULL' : 'IS NULL';
            $stmt = $this->pdo->prepare("
                SELECT body.id
                FROM {$table} body
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_id = body.id
                   AND pr.deleted_at IS NULL
                WHERE body.id IN ({$inSql})
                  AND body.deleted_at {$deletedClause}
                  AND COALESCE(pr.processing_status, 'READY') IN ('NONE', 'READY', 'REVIEW_REQUIRED', 'ERROR', 'DUPLICATED')
            ");
            $stmt->execute($params);
            $selectedIds = array_merge($selectedIds, array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []))));
        }

        return array_values(array_unique($selectedIds));
    }

    private function bodyRowsForDeleteBlockSummary(array $ids, string $evidenceType, string $prefix): array
    {
        $ids = $this->normalizeIdList($ids);
        if ($ids === []) {
            return [];
        }

        $evidenceType = ($this->dataTypeNormalizer)($evidenceType);
        $tables = $this->activeBodyTablesForImportType($evidenceType);
        $rows = [];

        foreach ($tables as $index => $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            [$inSql, $params] = ($this->placeholderBuilder)($ids, $prefix . '_' . $index);
            $sourceType = $evidenceType !== '' ? $evidenceType : strtoupper(trim(str_replace('ledger_evidence_', '', $table)));
            $sourceKeyExpr = $this->tableColumnExists($table, 'external_key')
                ? 'body.external_key'
                : ($this->tableColumnExists($table, 'source_key') ? 'body.source_key' : "''");
            $evidenceDateExpr = $this->tableColumnExists($table, 'raw_transaction_datetime')
                ? 'body.raw_transaction_datetime'
                : ($this->tableColumnExists($table, 'transaction_date') ? 'body.transaction_date' : ($this->tableColumnExists($table, 'evidence_date') ? 'body.evidence_date' : 'NULL'));

            $stmt = $this->pdo->prepare("
                SELECT
                    body.id AS id,
                    :source_type AS source_type,
                    {$sourceKeyExpr} AS source_key,
                    {$evidenceDateExpr} AS evidence_date,
                    COALESCE(pr.processing_status, 'READY') AS transaction_status,
                    body.deleted_at,
                    NULL AS mapped_payload_json,
                    tx.target_id AS transaction_id
                FROM {$table} body
                LEFT JOIN ledger_evidence_processing pr
                    ON pr.evidence_id = body.id
                   AND pr.deleted_at IS NULL
                LEFT JOIN ledger_evidence_links tx
                    ON tx.evidence_id = body.id
                   AND tx.target_type = 'TRANSACTION'
                   AND tx.deleted_at IS NULL
                WHERE body.id IN ({$inSql})
            ");
            $stmt->execute([':source_type' => $sourceType] + $params);
            $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        return $rows;
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
                LIMIT 1
            ");
            $stmt->execute([
                ':table' => $table,
                ':column' => $column,
            ]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function logPurgeAllDiagnostics(string $importType, array $selection): void
    {
        error_log('[EvidenceTrashService] purge_all_query=' . json_encode([
            'request_import_type' => $importType,
            'resolved_evidence_types' => $selection['resolved_evidence_types'],
            'list_table' => 'ledger_evidence_payloads',
            'delete_table' => 'ledger_evidence_payloads',
            'where_sql' => $selection['where_sql'],
            'binding_params' => $selection['binding_params'],
            'delete_target_count' => count($selection['ids']),
            'delete_target_id_sample' => array_slice($selection['ids'], 0, 5),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $sampleStmt = $this->pdo->prepare("
            SELECT
                r.*,
                r.evidence_type AS debug_import_type,
                r.evidence_type AS debug_evidence_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.data_type')) AS debug_data_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.import_type')) AS debug_payload_import_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.source_type')) AS debug_source_type,
                COALESCE(pr.processing_status, 'READY') AS debug_status,
                CASE WHEN r.deleted_at IS NULL THEN 0 ELSE 1 END AS debug_is_deleted
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = r.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = r.evidence_id COLLATE utf8mb4_unicode_ci
               AND pr.deleted_at IS NULL
            WHERE {$selection['where_sql']}
            ORDER BY r.deleted_at DESC, r.updated_at DESC, r.created_at DESC
            LIMIT 1
        ");
        $sampleStmt->execute($selection['binding_params']);
        $sampleRow = $sampleStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        error_log('[EvidenceTrashService] purge_all_sample_row=' . json_encode([
            'delete_table' => 'ledger_evidence_payloads',
            'sample_row' => $sampleRow,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function logPurgeAllCountComparison(array $selection): void
    {
        error_log('[EvidenceTrashService] purge_all_count_compare=' . json_encode([
            'list_row_count' => $this->deletedSeedRowListCount($selection),
            'purge_target_count' => count($selection['ids']),
            'list_table' => 'ledger_evidence_payloads',
            'delete_table' => 'ledger_evidence_payloads',
            'where_sql' => $selection['where_sql'],
            'binding_params' => $selection['binding_params'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    private function normalizeIdList(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn($id): string => trim((string) $id),
            $ids
        ))));
    }

    private function tableExists(string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
        ");
        $stmt->execute([':table_name' => $tableName]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function msg(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        return $decoded !== false ? $decoded : '';
    }
}
