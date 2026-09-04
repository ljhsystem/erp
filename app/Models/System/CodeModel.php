<?php
namespace App\Models\System;

use Core\Helpers\ActorHelper;
use Core\Database;
use PDO;

class CodeModel
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function getList(array $filters = []): array
    {
        $sql = "
            SELECT
                c.*,
                c.created_by AS created_by_name,
                c.updated_by AS updated_by_name
            FROM system_codes c
            WHERE 1 = 1
        ";

        $params = [];

        $fieldMap = [
            'sort_no' => ['col' => 'c.sort_no', 'type' => 'exact'],
            'code_group' => ['col' => 'c.code_group', 'type' => 'exact'],
            'group_name' => ['col' => 'c.group_name', 'type' => 'like'],
            'code' => ['col' => 'c.code', 'type' => 'like'],
            'code_name' => ['col' => 'c.code_name', 'type' => 'like'],
            'note' => ['col' => 'c.note', 'type' => 'like'],
            'memo' => ['col' => 'c.memo', 'type' => 'like'],
            'is_active' => ['col' => 'c.is_active', 'type' => 'exact'],
            'created_at' => ['col' => 'c.created_at', 'type' => 'date'],
            'created_by' => ['col' => 'c.created_by', 'type' => 'like'],
            'created_by_name' => ['col' => 'c.created_by', 'type' => 'like'],
            'updated_at' => ['col' => 'c.updated_at', 'type' => 'date'],
            'updated_by' => ['col' => 'c.updated_by', 'type' => 'like'],
            'updated_by_name' => ['col' => 'c.updated_by', 'type' => 'like'],
        ];

        $globalSearch = [];

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? '';
            $value = $filter['value'] ?? '';

            if ($value === '' || $value === null) {
                continue;
            }

            if ($field === '') {
                $globalSearch[] = $value;
                continue;
            }

            if (!isset($fieldMap[$field])) {
                continue;
            }

            $col = $fieldMap[$field]['col'];
            $type = $fieldMap[$field]['type'];

            if ($type === 'date') {
                if (is_array($value)) {
                    $sql .= " AND DATE({$col}) BETWEEN ? AND ?";
                    $params[] = $value['start'];
                    $params[] = $value['end'];
                } else {
                    $sql .= " AND DATE({$col}) = ?";
                    $params[] = $value;
                }
                continue;
            }

            if ($type === 'like') {
                $sql .= " AND {$col} LIKE ?";
                $params[] = "%{$value}%";
                continue;
            }

            $sql .= " AND {$col} = ?";
            $params[] = $value;
        }

        if (!empty($globalSearch)) {
            $searchCols = [
                'c.code_group',
                'c.group_name',
                'c.code',
                'c.code_name',
                'c.note',
                'c.memo',
                'c.created_by',
                'c.updated_by',
                'c.created_by',
                'c.updated_by'
            ];
            $sql .= " AND (";

            $groups = [];
            foreach ($globalSearch as $keyword) {
                $parts = [];
                foreach ($searchCols as $col) {
                    $parts[] = "{$col} LIKE ?";
                    $params[] = "%{$keyword}%";
                }
                $groups[] = '(' . implode(' OR ', $parts) . ')';
            }

            $sql .= implode(' OR ', $groups) . ")";
        }

        $sql .= " ORDER BY c.group_name ASC, c.code_group ASC, c.sort_no ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ActorHelper::enrichActorNames($rows, [
            'created_by_name' => 'created_by_name',
            'updated_by_name' => 'updated_by_name',
        ]);
    }

    public function getById(string $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                c.created_by AS created_by_name,
                c.updated_by AS updated_by_name
            FROM system_codes c
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return ActorHelper::enrichActorNamesRow($row, [
            'created_by_name' => 'created_by_name',
            'updated_by_name' => 'updated_by_name',
        ]);
    }

    public function getGroups(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                UPPER(TRIM(code_group)) AS code_group,
                MIN(TRIM(group_name)) AS group_name
            FROM system_codes
            WHERE code_group IS NOT NULL
              AND TRIM(code_group) <> ''
            GROUP BY UPPER(TRIM(code_group))
            ORDER BY group_name ASC, code_group ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByGroup(string $codeGroup, bool $activeOnly = true): array
    {
        $sql = "
            SELECT *
            FROM system_codes
            WHERE code_group = :code_group
        ";

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY sort_no ASC, code_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code_group' => $codeGroup]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOptionsByGroup(string $codeGroup): array
    {
        $stmt = $this->db->prepare("
            SELECT code, code_name, group_name, note, memo
            FROM system_codes
            WHERE code_group = :code_group
              AND is_active = 1
            ORDER BY sort_no ASC, code_name ASC
        ");
        $stmt->execute([':code_group' => $codeGroup]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getByGroupAndCode(string $codeGroup, string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_codes
            WHERE code_group = :code_group
              AND code = :code
            LIMIT 1
        ");
        $stmt->execute([
            ':code_group' => $codeGroup,
            ':code' => $code,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findActiveName(string $codeGroup, string $code): string
    {
        $stmt = $this->db->prepare("
            SELECT code_name FROM system_codes
            WHERE code_group = :code_group AND code = :code
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':code_group' => $codeGroup, ':code' => $code]);
        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    public function resolveActiveCode(string $codeGroup, string $code, string $name): ?string
    {
        $stmt = $this->db->prepare("SELECT code FROM system_codes WHERE is_active = 1 AND code_group = :code_group AND (UPPER(code) = :code OR code_name = :name) LIMIT 1");
        $stmt->execute([':code_group' => $codeGroup, ':code' => strtoupper($code), ':name' => $name]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string) $value : null;
    }

    public function getActiveCodesByGroup(string $codeGroup): array
    {
        $stmt = $this->db->prepare("
            SELECT code, code_name FROM system_codes
            WHERE is_active = 1 AND code_group = :code_group
            ORDER BY sort_no ASC, code ASC
        ");
        $stmt->execute([':code_group' => $codeGroup]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAllowedOptions(string $codeGroup, array $allowedCodes, bool $includeInactive = false): array
    {
        if ($allowedCodes === []) return [];
        $placeholders = implode(',', array_fill(0, count($allowedCodes), '?'));
        $statement = $this->db->prepare(
            "SELECT code value,code_name label,is_active FROM system_codes WHERE code_group=?"
            . " AND code IN ({$placeholders})" . ($includeInactive ? '' : ' AND is_active=1')
            . ' ORDER BY sort_no,code_name,id'
        );
        $statement->execute([$codeGroup, ...$allowedCodes]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function isActiveCode(string $codeGroup, string $code): bool
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM system_codes WHERE code_group=:code_group AND code=:code AND is_active=1'
        );
        $statement->execute([':code_group' => $codeGroup, ':code' => $code]);
        return (int) $statement->fetchColumn() === 1;
    }

    public function getStatutoryStandardTemplates(bool $includeNameAndNote = true): array
    {
        $columns = $includeNameAndNote ? 'code,code_name,note,extra_data' : 'code,extra_data';
        $statement = $this->db->query(
            "SELECT {$columns} FROM system_codes"
            . " WHERE code_group='STATUTORY_STANDARD_TYPE' AND is_active=1 ORDER BY sort_no"
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function existsByGroupAndCode(string $codeGroup, string $code, ?string $excludeId = null): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM system_codes
            WHERE code_group = :code_group
              AND code = :code
        ";

        $params = [
            ':code_group' => $codeGroup,
            ':code' => $code,
        ];

        if ($excludeId !== null && $excludeId !== '') {
            $sql .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function getGroupNameByCodeGroup(string $codeGroup, ?string $excludeId = null): ?string
    {
        $sql = "
            SELECT group_name
            FROM system_codes
            WHERE code_group = :code_group
              AND TRIM(group_name) <> ''
        ";

        $params = [':code_group' => $codeGroup];

        if ($excludeId !== null && $excludeId !== '') {
            $sql .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " ORDER BY updated_at DESC, created_at DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $value = $stmt->fetchColumn();
        return $value === false ? null : trim((string)$value);
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO system_codes (
                id, sort_no, code_group, group_name, code, code_name, note, memo,
                is_active, created_by, updated_by, extra_data
            ) VALUES (
                :id, :sort_no, :code_group, :group_name, :code, :code_name, :note, :memo,
                :is_active, :created_by, :updated_by, :extra_data
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $data['id'],
            ':sort_no' => (int)($data['sort_no'] ?? 0),
            ':code_group' => trim((string)($data['code_group'] ?? '')),
            ':group_name' => trim((string)($data['group_name'] ?? '')),
            ':code' => trim((string)($data['code'] ?? '')),
            ':code_name' => trim((string)($data['code_name'] ?? '')),
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? ($data['created_by'] ?? null),
            ':extra_data' => $data['extra_data'] ?? null,
        ]);
    }

    public function updateById(string $id, array $data): bool
    {
        $sql = "
            UPDATE system_codes SET
                sort_no = :sort_no,
                code_group = :code_group,
                group_name = :group_name,
                code = :code,
                code_name = :code_name,
                note = :note,
                memo = :memo,
                is_active = :is_active,
                updated_by = :updated_by,
                extra_data = :extra_data
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':sort_no' => (int)($data['sort_no'] ?? 0),
            ':code_group' => trim((string)($data['code_group'] ?? '')),
            ':group_name' => trim((string)($data['group_name'] ?? '')),
            ':code' => trim((string)($data['code'] ?? '')),
            ':code_name' => trim((string)($data['code_name'] ?? '')),
            ':note' => $data['note'] ?? null,
            ':memo' => $data['memo'] ?? null,
            ':is_active' => (int)($data['is_active'] ?? 1),
            ':updated_by' => $data['updated_by'] ?? null,
            ':extra_data' => $data['extra_data'] ?? null,
        ]);
    }

    public function updateGroupNameByCodeGroup(string $codeGroup, string $groupName, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_codes
            SET group_name = :group_name,
                updated_by = :updated_by
            WHERE code_group = :code_group
        ");

        return $stmt->execute([
            ':code_group' => $codeGroup,
            ':group_name' => $groupName,
            ':updated_by' => $actor,
        ]);
    }

    public function deleteById(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM system_codes WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function tableExists(string $table): bool
    {
        if (!$this->isSafeIdentifier($table)) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
        ");
        $stmt->execute([':table' => $table]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function columnExists(string $table, string $column): bool
    {
        if (!$this->isSafeIdentifier($table) || !$this->isSafeIdentifier($column)) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function countValueReferences(string $table, string $column, string $value): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return 0;
        }

        $quotedTable = $this->quoteIdentifier($table);
        $quotedColumn = $this->quoteIdentifier($column);
        $deletedFilter = $this->columnExists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM {$quotedTable}
            WHERE {$quotedColumn} = :value
            {$deletedFilter}
        ");
        $stmt->execute([':value' => $value]);

        return (int)$stmt->fetchColumn();
    }

    public function countJsonValueReferences(string $table, string $column, string $jsonKey, string $value): int
    {
        if (
            !$this->tableExists($table)
            || !$this->columnExists($table, $column)
            || !$this->isSafeJsonPathKey($jsonKey)
        ) {
            return 0;
        }

        $quotedTable = $this->quoteIdentifier($table);
        $quotedColumn = $this->quoteIdentifier($column);
        $jsonPath = '$."' . str_replace('"', '\"', $jsonKey) . '"';
        $deletedFilter = $this->columnExists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM {$quotedTable}
            WHERE JSON_VALID({$quotedColumn})
              AND JSON_UNQUOTE(JSON_EXTRACT({$quotedColumn}, :json_path)) = :value
              {$deletedFilter}
        ");
        $stmt->execute([
            ':json_path' => $jsonPath,
            ':value' => $value,
        ]);

        return (int)$stmt->fetchColumn();
    }

    public function countJsonReferences(string $table, string $column, string $value): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return 0;
        }

        $quotedTable = $this->quoteIdentifier($table);
        $quotedColumn = $this->quoteIdentifier($column);
        $deletedFilter = $this->columnExists($table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM {$quotedTable}
            WHERE JSON_VALID({$quotedColumn})
              AND JSON_SEARCH({$quotedColumn}, 'one', :value) IS NOT NULL
              {$deletedFilter}
        ");
        $stmt->execute([':value' => $value]);
        return (int) $stmt->fetchColumn();
    }

    public function updateSortNo(string $id, string $newSortNo, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE system_codes
            SET sort_no = :sort_no,
                updated_at = NOW(),
                updated_by = :updated_by
            WHERE id = :id
        ");

        return $stmt->execute([
            ':sort_no' => (int)$newSortNo,
            ':updated_by' => $actor,
            ':id' => $id,
        ]);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!$this->isSafeIdentifier($identifier)) {
            throw new \InvalidArgumentException('Unsafe SQL identifier.');
        }

        return '`' . $identifier . '`';
    }

    private function isSafeIdentifier(string $identifier): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
    }

    private function isSafeJsonPathKey(string $key): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $key) === 1;
    }
}
