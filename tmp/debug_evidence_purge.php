<?php

if (!function_exists('debugEvidencePurgeLog')) {
    function debugEvidencePurgeLog(string $tag, array $context): void
    {
        error_log('[debug_evidence_purge] ' . $tag . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('debugEvidencePurgeLogSampleRow')) {
    function debugEvidencePurgeLogSampleRow(\PDO $pdo, string $whereSql, array $params, string $tag): void
    {
        $sql = "
            SELECT
                r.*,
                r.evidence_type AS debug_import_type,
                r.evidence_type AS debug_evidence_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.data_type')) AS debug_payload_data_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.import_type')) AS debug_payload_import_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.source_type')) AS debug_payload_source_type,
                JSON_UNQUOTE(JSON_EXTRACT(r.mapped_payload_json, '$.evidence_type')) AS debug_payload_evidence_type,
                COALESCE(pr.processing_status, 'READY') AS debug_status,
                CASE WHEN r.deleted_at IS NULL THEN 0 ELSE 1 END AS debug_is_deleted
            FROM ledger_evidence_payloads r
            LEFT JOIN ledger_evidence_processing pr
                ON pr.evidence_type COLLATE utf8mb4_unicode_ci = r.evidence_type COLLATE utf8mb4_unicode_ci
               AND pr.evidence_id COLLATE utf8mb4_unicode_ci = r.evidence_id COLLATE utf8mb4_unicode_ci
               AND pr.deleted_at IS NULL
            WHERE {$whereSql}
            ORDER BY r.deleted_at DESC, r.updated_at DESC, r.created_at DESC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        debugEvidencePurgeLog($tag, [
            'sample_row' => $row,
        ]);
    }
}
