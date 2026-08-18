<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceBodyStorageModel
{
    private const IMPORT_TABLES = [
        'BANK_TRANSACTION' => 'ledger_evidence_bank_transaction', 'TAX_INVOICE' => 'ledger_evidence_tax_invoice',
        'TAX_INVOICE_MANUAL' => 'ledger_evidence_tax_invoice_manual', 'CASH_RECEIPT' => 'ledger_evidence_cash_receipt',
        'CARD_HOMETAX' => 'ledger_evidence_card_hometax', 'CARD' => 'ledger_evidence_card_statement',
        'CARD_STATEMENT' => 'ledger_evidence_card_statement', 'CARD_APPROVAL' => 'ledger_evidence_card_statement',
        'EMPLOYEE_EXPENSE' => 'ledger_evidence_employee_expense',
        'EMPLOYEE_EXPENSE_PERSONAL' => 'ledger_evidence_employee_personal_expense', 'PAYROLL' => 'ledger_evidence_payroll',
        'PAYROLL_WITHHOLDING' => 'ledger_evidence_payroll', 'BUSINESS_INCOME' => 'ledger_evidence_business_income',
        'BUSINESS_DATA' => 'ledger_evidence_cash_sales', 'CONSTRUCTION' => 'ledger_evidence_daily_worker',
        'SHOPPING_ORDER' => 'ledger_evidence_business_data', 'IMPORT_INVOICE' => 'ledger_evidence_business_data',
    ];
    private const TABLES = [
        'ledger_evidence_bank_transaction', 'ledger_evidence_tax_invoice', 'ledger_evidence_tax_invoice_manual',
        'ledger_evidence_cash_receipt', 'ledger_evidence_card_hometax', 'ledger_evidence_card_statement',
        'ledger_evidence_card_sales', 'ledger_evidence_employee_expense', 'ledger_evidence_employee_personal_expense', 'ledger_evidence_payroll',
        'ledger_evidence_daily_worker', 'ledger_evidence_business_income', 'ledger_evidence_cash_sales',
        'ledger_evidence_business_data',
    ];

    public function __construct(private PDO $db) {}

    public function tableExists(string $table): bool
    {
        $this->assertTable($table);
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1');
        $stmt->execute([':table_name' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    public function columns(string $table): array
    {
        $this->assertTable($table);
        $stmt = $this->db->prepare('SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
        $stmt->execute([':table_name' => $table]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function columnExists(string $table, string $column): bool
    {
        $this->assertTable($table);
        if (preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) throw new \InvalidArgumentException('Invalid evidence body column.');
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column LIMIT 1');
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (bool) $stmt->fetchColumn();
    }

    public function findUploadSeed(string $table, string $sourceKey, string $importType = ''): ?array
    {
        $this->assertTable($table);
        $key = $this->columnExists($table, 'external_key') ? 'external_key' : ($this->columnExists($table, 'source_key') ? 'source_key' : null);
        if ($key === null) return null;
        $sort = $this->columnExists($table, 'sort_no') ? 'body.sort_no' : 'NULL';
        $updated = $this->columnExists($table, 'updated_at') ? 'body.updated_at' : 'NULL';
        $created = $this->columnExists($table, 'created_at') ? 'body.created_at' : 'NULL';
        $join = '';
        $status = "'READY'";
        $linkType = strtoupper(trim($importType));
        $linkTypeCondition = $linkType !== '' ? ' AND tx.evidence_type = :transaction_import_type' : '';
        $voucherTypeCondition = $linkType !== '' ? ' AND vx.evidence_type = :voucher_import_type' : '';
        $stmt = $this->db->prepare("SELECT body.id, body.{$key} AS source_key, body.deleted_at, {$sort} AS sort_no,
            {$status} AS transaction_status,
            CASE WHEN vx.target_id IS NULL THEN 'WAITING' ELSE 'LINKED' END AS voucher_status, tx.target_id AS transaction_id
            FROM `{$table}` body {$join}
            LEFT JOIN ledger_evidence_links tx ON tx.evidence_id = body.id{$linkTypeCondition} AND tx.target_type = 'TRANSACTION' AND tx.deleted_at IS NULL
            LEFT JOIN ledger_evidence_links vx ON vx.evidence_id = body.id{$voucherTypeCondition} AND vx.target_type = 'VOUCHER' AND vx.deleted_at IS NULL
            WHERE body.{$key} = :source_key ORDER BY body.deleted_at IS NULL DESC, {$updated} DESC, {$created} DESC LIMIT 1");
        $params = [':source_key' => $sourceKey];
        if ($linkType !== '') {
            $params[':transaction_import_type'] = $linkType;
            $params[':voucher_import_type'] = $linkType;
        }
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findUploadSeedByImportType(string $importType, string $sourceKey): ?array
    {
        $table = self::IMPORT_TABLES[strtoupper(trim($importType))] ?? null;
        if ($table === null || !$this->tableExists($table)) return null;
        $row = $this->findUploadSeed($table, $sourceKey, $importType);
        if ($row === null) return null;
        $row['raw_json'] = '';
        $row['mapped_payload_json'] = '';
        return $row;
    }

    public function findUploadSeedsByImportType(string $importType, string $externalKey): array
    {
        $type = strtoupper(trim($importType));
        $table = self::IMPORT_TABLES[$type] ?? null;
        $externalKey = trim($externalKey);
        if ($table === null || $externalKey === '' || !$this->tableExists($table)) return [];
        $keyColumn = $this->columnExists($table, 'external_key') ? 'external_key'
            : ($this->columnExists($table, 'source_key') ? 'source_key' : null);
        if ($keyColumn === null) return [];

        $stmt = $this->db->prepare("SELECT body.*,
            body.{$keyColumn} AS source_key,
            EXISTS(SELECT 1 FROM ledger_evidence_links link
                WHERE link.evidence_type = :transaction_type AND link.evidence_id = body.id
                  AND link.target_type = 'TRANSACTION' AND link.deleted_at IS NULL) AS has_transaction_link,
            EXISTS(SELECT 1 FROM ledger_evidence_links link
                WHERE link.evidence_type = :voucher_type AND link.evidence_id = body.id
                  AND link.target_type = 'VOUCHER' AND link.deleted_at IS NULL) AS has_voucher_link
            FROM `{$table}` body
            WHERE body.{$keyColumn} = :external_key
            ORDER BY body.deleted_at IS NULL DESC, body.created_at ASC, body.id ASC");
        $stmt->execute([
            ':transaction_type' => $type,
            ':voucher_type' => $type,
            ':external_key' => $externalKey,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function uploadBatches(): array
    {
        $result = [];
        foreach (array_unique(self::IMPORT_TABLES) as $table) {
            if (!$this->tableExists($table) || !$this->columnExists($table, 'created_at')) continue;
            $type = array_search($table, self::IMPORT_TABLES, true);
            $typeExpression = $this->columnExists($table, 'import_type') ? 'import_type'
                : ($this->columnExists($table, 'source_type') ? 'source_type' : "'{$type}'");
            $updated = $this->columnExists($table, 'updated_at') ? 'MAX(updated_at)' : 'MAX(created_at)';
            $stmt = $this->db->query("SELECT DATE(created_at) imported_date, {$typeExpression} data_type,
                {$typeExpression} source_type, COUNT(*) total_rows,
                MAX(created_at) created_at, {$updated} updated_at FROM `{$table}`
                WHERE deleted_at IS NULL GROUP BY DATE(created_at), {$typeExpression}");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $row += ['format_id' => null, 'format_name' => '',
                    'ready_count' => $row['total_rows'], 'valid_count' => $row['total_rows'], 'warning_count' => 0,
                    'error_count' => 0, 'duplicate_count' => 0, 'created_count' => 0, 'processed_count' => 0];
                $result[] = $row;
            }
        }
        usort($result, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return array_slice($result, 0, 100);
    }

    public function findUploadRowsByImportType(string $importType): array
    {
        $table = self::IMPORT_TABLES[strtoupper(trim($importType))] ?? null;
        if ($table === null || !$this->tableExists($table)) return [];
        $stmt = $this->db->query("SELECT * FROM `{$table}` WHERE deleted_at IS NULL");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $key = (string) ($row['external_key'] ?? $row['source_key'] ?? '');
            $row['source_key'] = $key;
            $row['raw_json'] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $row['mapped_payload_json'] = $row['raw_json'];
            $row['evidence_status'] = 'ACTIVE';
            $row['transaction_status'] = 'READY';
            $row['voucher_status'] = 'WAITING';
            $row['transaction_id'] = null;
        }
        unset($row);
        return $rows;
    }

    public function findDeletableIdsByImportDate(string $importDate): array
    {
        $ids = [];
        foreach (self::IMPORT_TABLES as $importType => $table) {
            if (!$this->tableExists($table) || !$this->columnExists($table, 'created_at')) continue;
            $stmt = $this->db->prepare("SELECT body.id FROM `{$table}` body
                WHERE DATE(body.created_at) = :import_date AND body.deleted_at IS NULL
                AND NOT EXISTS (SELECT 1 FROM ledger_evidence_links link
                    WHERE link.evidence_type = :import_type AND link.evidence_id = body.id AND link.deleted_at IS NULL)");
            $stmt->execute([':import_date' => $importDate, ':import_type' => $importType]);
            array_push($ids, ...array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        }
        return array_values(array_unique($ids));
    }

    public function findSummaryPayloadRows(string $keyword): array
    {
        $keyword = mb_strtolower($keyword);
        $result = [];
        foreach (array_keys(self::IMPORT_TABLES) as $importType) {
            foreach ($this->findUploadRowsByImportType($importType) as $row) {
                $json = (string) ($row['mapped_payload_json'] ?? '');
                if ($keyword !== '' && !str_contains(mb_strtolower($json), $keyword)) continue;
                $result[] = ['mapped_payload_json' => $json, 'updated_at' => $row['updated_at'] ?? null,
                    'created_at' => $row['created_at'] ?? null];
            }
        }
        usort($result, static fn(array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? $b['created_at']), (string) ($a['updated_at'] ?? $a['created_at'])));
        return array_slice($result, 0, 1000);
    }

    public function findDownloadRows(string $importType, string $formatId = '', bool $withFormatId = false): array
    {
        $rows = $this->findUploadRowsByImportType($importType);
        if ($withFormatId && $formatId !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string) ($row['format_id'] ?? '') === $formatId));
        }
        return array_map(static fn(array $row): array => [
            'mapped_payload_json' => (string) ($row['mapped_payload_json'] ?? '{}'),
            'raw_json' => (string) ($row['raw_json'] ?? '{}'),
        ], $rows);
    }

    public function identitiesByIds(array $ids, string $importType = '', ?bool $deleted = null): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) return [];
        $types = $importType !== '' ? [strtoupper(trim($importType))] : array_keys(self::IMPORT_TABLES);
        $result = [];
        $seen = [];
        foreach ($types as $type) {
            $table = self::IMPORT_TABLES[$type] ?? null;
            if ($table === null || !$this->tableExists($table)) continue;
            [$marks, $params] = $this->placeholders($ids, 'identity_id');
            $deletedSql = $deleted === true ? 'AND deleted_at IS NOT NULL' : ($deleted === false ? 'AND deleted_at IS NULL' : '');
            $typeSql = $this->columnExists($table, 'import_type') ? 'AND import_type = :row_import_type' : '';
            $stmt = $this->db->prepare("SELECT *, :canonical_import_type AS canonical_import_type
                FROM `{$table}` WHERE id IN (" . implode(',', $marks) . ") {$deletedSql} {$typeSql}");
            $bind = [':canonical_import_type' => $type] + $params;
            if ($typeSql !== '') $bind[':row_import_type'] = $type;
            $stmt->execute($bind);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = $type . ':' . (string) ($row['id'] ?? '');
                if (!isset($seen[$key])) { $seen[$key] = true; $result[] = $row; }
            }
        }
        return $result;
    }

    public function identities(string $importType, ?bool $deleted = null): array
    {
        $table = $this->tableForImportType($importType);
        if ($table === null || !$this->tableExists($table)) return [];
        $deletedSql = $deleted === true ? 'WHERE deleted_at IS NOT NULL' : ($deleted === false ? 'WHERE deleted_at IS NULL' : '');
        $stmt = $this->db->prepare("SELECT *, :canonical_import_type AS canonical_import_type FROM `{$table}` {$deletedSql}");
        $stmt->execute([':canonical_import_type' => strtoupper(trim($importType))]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateDeletedState(string $importType, array $ids, bool $deleted, string $actor): int
    {
        $table = $this->tableForImportType($importType);
        $ids = $this->normalizeIds($ids);
        if ($table === null || $ids === [] || !$this->tableExists($table)) return 0;
        [$marks, $params] = $this->placeholders($ids, 'delete_state_id');
        $sets = [$deleted ? 'deleted_at = NOW()' : 'deleted_at = NULL', $deleted ? 'deleted_by = :actor' : 'deleted_by = NULL'];
        if ($this->columnExists($table, 'updated_at')) $sets[] = 'updated_at = NOW()';
        if ($this->columnExists($table, 'updated_by')) $sets[] = 'updated_by = :updated_actor';
        $where = $deleted ? 'deleted_at IS NULL' : 'deleted_at IS NOT NULL';
        $stmt = $this->db->prepare("UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE id IN (" . implode(',', $marks) . ") AND {$where}");
        $bind = $params;
        if ($deleted) $bind[':actor'] = $actor;
        if ($this->columnExists($table, 'updated_by')) $bind[':updated_actor'] = $actor;
        $stmt->execute($bind);
        return $stmt->rowCount();
    }

    public function updateEvidenceStatus(string $importType, array $ids, string $status, string $actor): int
    {
        $table = $this->tableForImportType($importType);
        $ids = $this->normalizeIds($ids);
        if ($table === null || $ids === [] || !$this->tableExists($table) || !$this->columnExists($table, 'evidence_status')) return 0;
        [$marks, $params] = $this->placeholders($ids, 'status_id');
        $stmt = $this->db->prepare("UPDATE `{$table}` SET evidence_status = :status, updated_at = NOW(), updated_by = :actor
            WHERE id IN (" . implode(',', $marks) . ") AND deleted_at IS NULL");
        $stmt->execute($params + [':status' => $status, ':actor' => $actor]);
        return $stmt->rowCount();
    }

    public function purge(string $importType, array $ids): int
    {
        $table = $this->tableForImportType($importType);
        $ids = $this->normalizeIds($ids);
        if ($table === null || $ids === [] || !$this->tableExists($table)) return 0;
        [$marks, $params] = $this->placeholders($ids, 'purge_id');
        $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE id IN (" . implode(',', $marks) . ") AND deleted_at IS NOT NULL");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function reorder(string $importType, array $rows, string $actor, string $column = 'sort_no'): int
    {
        $table = $this->tableForImportType($importType);
        if ($table === null || !$this->tableExists($table) || $column !== 'sort_no' || !$this->columnExists($table, $column)) return 0;
        $normalized = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? $row['evidence_id'] ?? ''));
            $sortNo = (int) ($row['sort_no'] ?? $row[$column] ?? 0);
            if ($id === '' || $sortNo < 1) continue;
            $normalized[$id] = $sortNo;
        }
        if ($normalized === []) return 0;

        $count = 0;
        foreach (array_chunk($normalized, 200, true) as $chunkIndex => $chunk) {
            $cases = [];
            $marks = [];
            $params = [':actor' => $actor];
            $position = 0;
            foreach ($chunk as $id => $sortNo) {
                $suffix = $chunkIndex . '_' . $position++;
                $caseId = ':case_id_' . $suffix;
                $sortKey = ':sort_no_' . $suffix;
                $whereId = ':where_id_' . $suffix;
                $cases[] = "WHEN {$caseId} THEN {$sortKey}";
                $marks[] = $whereId;
                $params[$caseId] = $id;
                $params[$sortKey] = $sortNo;
                $params[$whereId] = $id;
            }
            $stmt = $this->db->prepare(
                "UPDATE `{$table}` SET `{$column}` = CASE id " . implode(' ', $cases)
                . " ELSE `{$column}` END, updated_at = NOW(), updated_by = :actor WHERE id IN ("
                . implode(',', $marks) . ')'
            );
            $stmt->execute($params);
            $count += $stmt->rowCount();
        }
        return $count;
    }

    public function tableForImportType(string $importType): ?string
    {
        return self::IMPORT_TABLES[strtoupper(trim($importType))] ?? null;
    }

    public function importTypes(): array
    {
        return array_keys(self::IMPORT_TABLES);
    }

    public function uploadBatchRows(string $batchId): array
    {
        $rows = [];
        foreach (array_unique(self::IMPORT_TABLES) as $table) {
            if (!$this->tableExists($table) || !$this->columnExists($table, 'created_at')) continue;
            $type = array_search($table, self::IMPORT_TABLES, true);
            $typeExpression = $this->columnExists($table, 'import_type') ? 'import_type'
                : ($this->columnExists($table, 'source_type') ? 'source_type' : "'{$type}'");
            $raw = $this->columnExists($table, 'raw_json') ? 'raw_json' : "'{}'";
            $mapped = $this->columnExists($table, 'mapped_payload_json') ? 'mapped_payload_json' : "'{}'";
            $updated = $this->columnExists($table, 'updated_at') ? 'updated_at' : 'created_at';
            $stmt = $this->db->prepare("SELECT id, NULL batch_id, {$typeExpression} source_type, 0 row_no,
                {$raw} raw_payload, {$mapped} mapped_payload, 'READY' status, NULL error_message,
                NULL transaction_id, created_at processed_at, created_at, {$updated} updated_at FROM `{$table}`
                WHERE deleted_at IS NULL AND (:empty = '' OR DATE(created_at) = :batch_id)");
            $stmt->execute([':empty' => $batchId, ':batch_id' => $batchId]);
            array_push($rows, ...($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []));
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return $rows;
    }

    public function findById(string $table, string $id): ?array
    {
        $this->assertTable($table);
        $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existsById(string $table, string $id): bool
    {
        $this->assertTable($table);
        $stmt = $this->db->prepare("SELECT id FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    private function assertTable(string $table): void
    {
        if (!in_array($table, self::TABLES, true)) throw new \InvalidArgumentException('Unsupported evidence body table.');
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_filter(array_unique(array_map(static fn(mixed $id): string => trim((string) $id), $ids))));
    }

    private function placeholders(array $values, string $prefix): array
    {
        $marks = []; $params = [];
        foreach (array_values($values) as $index => $value) {
            $key = ':' . $prefix . '_' . $index;
            $marks[] = $key; $params[$key] = $value;
        }
        return [$marks, $params];
    }
}
