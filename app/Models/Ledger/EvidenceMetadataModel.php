<?php

namespace App\Models\Ledger;

use PDO;

class EvidenceMetadataModel
{
    private const TABLE = 'ledger_evidence_metadata';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getList(array $filters = [], bool $deleted = false): array
    {
        $where = [$deleted ? 'm.deleted_at IS NOT NULL' : 'm.deleted_at IS NULL'];
        $params = [];

        foreach ($filters as $index => $filter) {
            $field = trim((string) ($filter['field'] ?? ''));
            $value = $filter['value'] ?? '';
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }

            $key = ':filter_' . $index;
            if (in_array($field, ['import_type', 'source_table', 'evidence_type'], true)) {
                $where[] = "m.{$field} LIKE {$key}";
                $params[$key] = '%' . trim((string) $value) . '%';
            }
        }

        $sql = 'SELECT m.*, COALESCE(mc.mapping_count, 0) AS mapping_count,
                COALESCE(mc.base_date_count, 0) AS base_date_count,
                COALESCE(mc.description_count, 0) AS description_count,
                COALESCE(mc.data_amount_count, 0) AS data_amount_count,
                COALESCE(mc.fund_amount_count, 0) AS fund_amount_count,
                COALESCE(mc.invalid_adjustment_count, 0) AS invalid_adjustment_count
            FROM ' . self::TABLE . ' m
            LEFT JOIN (
                SELECT metadata_id, COUNT(*) AS mapping_count,
                    SUM(semantic_key = \'BASE_DATE\') AS base_date_count,
                    SUM(semantic_key = \'DESCRIPTION\') AS description_count,
                    SUM(semantic_key IN (\'PRE_TAX_AMOUNT\', \'POST_TAX_AMOUNT\', \'SUPPLY_AMOUNT\', \'VAT_AMOUNT\')) AS data_amount_count,
                    SUM(semantic_key IN (\'IN_AMOUNT\', \'OUT_AMOUNT\')) AS fund_amount_count,
                    SUM((semantic_key = \'ADJUST_AMOUNT\' AND adjustment_direction NOT IN (\'ADD\', \'DEDUCT\'))
                        OR (semantic_key <> \'ADJUST_AMOUNT\' AND adjustment_direction IS NOT NULL)) AS invalid_adjustment_count
                FROM ledger_evidence_metadata_columns
                GROUP BY metadata_id
            ) mc ON mc.metadata_id = m.id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.sort_no ASC, m.import_type ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE id = :id';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getByIds(array $ids, bool $includeDeleted = false): array
    {
        [$placeholders, $params] = $this->idParameters($ids);
        if ($placeholders === []) {
            return [];
        }
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE id IN (' . implode(', ', $placeholders) . ')';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByImportType(string $importType): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE
            . ' WHERE import_type = :import_type AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':import_type' => strtoupper(trim($importType))]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function importTypeExists(string $importType, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE import_type = :import_type';
        $params = [':import_type' => $importType];
        if ($excludeId !== null && $excludeId !== '') {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE
            . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        return $stmt->execute($this->parameters($data));
    }

    public function update(string $id, array $data): bool
    {
        $assignments = array_map(static fn(string $column): string => "{$column} = :{$column}", array_keys($data));
        $params = $this->parameters($data);
        $params[':id'] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $assignments) . ' WHERE id = :id'
        );
        return $stmt->execute($params);
    }

    public function delete(string $id, string $actor): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE
            . ' SET deleted_at = NOW(), deleted_by = :deleted_by, updated_at = NOW(), updated_by = :updated_by'
            . ' WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id, ':deleted_by' => $actor, ':updated_by' => $actor]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByIds(array $ids, string $actor): int
    {
        return $this->updateDeletedStateByIds($ids, true, $actor);
    }

    public function restore(string $id, string $actor): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE
            . ' SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :updated_by'
            . ' WHERE id = :id AND deleted_at IS NOT NULL'
        );
        $stmt->execute([':id' => $id, ':updated_by' => $actor]);
        return $stmt->rowCount() > 0;
    }

    public function restoreByIds(array $ids, string $actor): int
    {
        return $this->updateDeletedStateByIds($ids, false, $actor);
    }

    public function restoreAll(string $actor): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE
            . ' SET deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :updated_by'
            . ' WHERE deleted_at IS NOT NULL'
        );
        $stmt->execute([':updated_by' => $actor]);
        return $stmt->rowCount();
    }

    public function purge(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id AND deleted_at IS NOT NULL');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function purgeByIds(array $ids): int
    {
        [$placeholders, $params] = $this->idParameters($ids);
        if ($placeholders === []) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . self::TABLE
            . ' WHERE deleted_at IS NOT NULL AND id IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function purgeAll(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE deleted_at IS NOT NULL');
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function updateOrder(string $id, int $sortNo, string $actor): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE
            . ' SET sort_no = :sort_no, updated_at = NOW(), updated_by = :updated_by'
            . ' WHERE id = :id AND deleted_at IS NULL'
        );
        return $stmt->execute([':id' => $id, ':sort_no' => $sortNo, ':updated_by' => $actor]);
    }

    private function updateDeletedStateByIds(array $ids, bool $delete, string $actor): int
    {
        [$placeholders, $params] = $this->idParameters($ids);
        if ($placeholders === []) {
            return 0;
        }
        $params[':updated_by'] = $actor;
        $set = $delete
            ? 'deleted_at = NOW(), deleted_by = :updated_by, updated_at = NOW(), updated_by = :updated_by'
            : 'deleted_at = NULL, deleted_by = NULL, updated_at = NOW(), updated_by = :updated_by';
        $condition = $delete ? 'deleted_at IS NULL' : 'deleted_at IS NOT NULL';
        $stmt = $this->pdo->prepare(
            'UPDATE ' . self::TABLE . ' SET ' . $set
            . ' WHERE ' . $condition . ' AND id IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function idParameters(array $ids): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values(array_unique(array_filter(array_map('strval', $ids)))) as $index => $id) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        return [$placeholders, $params];
    }

    private function parameters(array $data): array
    {
        $params = [];
        foreach ($data as $column => $value) {
            $params[':' . $column] = $value;
        }
        return $params;
    }
}
