<?php

namespace App\Models\Ledger;

use PDO;

class JournalRuleModel
{
    public function __construct(private PDO $db)
    {
    }

    public function getList(array $filters = [], bool $trash = false): array
    {
        $params = [];
        $where = [$trash ? 'r.deleted_at IS NOT NULL' : 'r.deleted_at IS NULL'];

        foreach ($filters as $filter) {
            $field = (string) ($filter['field'] ?? '');
            $value = $filter['value'] ?? '';
            if ($field === '' || $value === '' || $value === null) {
                continue;
            }

            if (is_array($value)) {
                $start = (string) ($value['start'] ?? '');
                $end = (string) ($value['end'] ?? '');
                if ($start !== '' && $end !== '' && in_array($field, ['created_at', 'updated_at', 'deleted_at'], true)) {
                    $where[] = "DATE(r.{$field}) BETWEEN :{$field}_start AND :{$field}_end";
                    $params[":{$field}_start"] = $start;
                    $params[":{$field}_end"] = $end;
                }
                continue;
            }

            $key = ':filter_' . count($params);
            switch ($field) {
                case 'id':
                    $where[] = "r.id = {$key}";
                    $params[$key] = (string) $value;
                    break;
                case 'rule_code':
                case 'rule_name':
                case 'description':
                    $where[] = "r.{$field} LIKE {$key}";
                    $params[$key] = '%' . $value . '%';
                    break;
                case 'business_unit':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.business_unit LIKE {$codeKey} OR bu.code_name LIKE {$nameKey})";
                    break;
                case 'transaction_type':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.transaction_type LIKE {$codeKey} OR tt.code_name LIKE {$nameKey})";
                    break;
                case 'import_type':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.import_type LIKE {$codeKey} OR it.code_name LIKE {$nameKey})";
                    break;
                case 'transaction_direction':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.transaction_direction LIKE {$codeKey} OR td.code_name LIKE {$nameKey})";
                    break;
                case 'client_type':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.client_type LIKE {$codeKey} OR clt.code_name LIKE {$nameKey})";
                    break;
                case 'debit_account_name':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(da.account_code LIKE {$codeKey} OR da.account_name LIKE {$nameKey})";
                    break;
                case 'credit_account_name':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(ca.account_code LIKE {$codeKey} OR ca.account_name LIKE {$nameKey})";
                    break;
                case 'vat_account_name':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(va.account_code LIKE {$codeKey} OR va.account_name LIKE {$nameKey})";
                    break;
                case 'is_active':
                    $where[] = 'r.is_active = ' . $key;
                    $params[$key] = $this->normalizeBoolean($value);
                    break;
            }
        }

        $sql = $this->baseSelectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY r.sort_no ASC, r.rule_code ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(string $id, bool $includeDeleted = false): ?array
    {
        $sql = $this->baseSelectSql() . ' WHERE r.id = :id';
        if (!$includeDeleted) {
            $sql .= ' AND r.deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findActiveRule(array $criteria): ?array
    {
        $sql = $this->baseSelectSql() . "
            WHERE r.deleted_at IS NULL
              AND r.is_active = 1
              AND r.business_unit = :business_unit
              AND r.transaction_type = :transaction_type
              AND r.transaction_direction = :transaction_direction
              AND r.client_type = :client_type
              AND r.import_type = :import_type
            ORDER BY r.sort_no ASC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':business_unit' => strtoupper(trim((string) ($criteria['business_unit'] ?? ''))),
            ':transaction_type' => strtoupper(trim((string) ($criteria['transaction_type'] ?? ''))),
            ':transaction_direction' => strtoupper(trim((string) ($criteria['transaction_direction'] ?? ''))),
            ':client_type' => strtoupper(trim((string) ($criteria['client_type'] ?? ''))),
            ':import_type' => strtoupper(trim((string) ($criteria['import_type'] ?? ''))),
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function existsRuleCode(string $ruleCode, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ledger_journal_rules WHERE rule_code = :rule_code';
        $params = [':rule_code' => $ruleCode];
        if ($excludeId) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO ledger_journal_rules (
                id, sort_no, rule_code, rule_name, business_unit, transaction_type,
                transaction_direction, client_type, import_type, debit_account_id,
                credit_account_id, vat_account_id, description, is_active, created_by, updated_by
            ) VALUES (
                :id, :sort_no, :rule_code, :rule_name, :business_unit, :transaction_type,
                :transaction_direction, :client_type, :import_type, :debit_account_id,
                :credit_account_id, :vat_account_id, :description, :is_active, :created_by, :updated_by
            )
        ");
        return $stmt->execute($data);
    }

    public function update(string $id, array $data): bool
    {
        $data[':id'] = $id;
        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET rule_code = :rule_code,
                rule_name = :rule_name,
                business_unit = :business_unit,
                transaction_type = :transaction_type,
                transaction_direction = :transaction_direction,
                client_type = :client_type,
                import_type = :import_type,
                debit_account_id = :debit_account_id,
                credit_account_id = :credit_account_id,
                vat_account_id = :vat_account_id,
                description = :description,
                is_active = :is_active,
                updated_by = :updated_by
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        return $stmt->execute($data);
    }

    public function softDelete(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :actor,
                updated_by = :actor
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        return $stmt->execute([':id' => $id, ':actor' => $actor]);
    }

    public function updateStatus(string $id, int $isActive, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET is_active = :is_active,
                updated_by = :actor
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            ':id' => $id,
            ':is_active' => $isActive,
            ':actor' => $actor,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateOrder(string $id, int $sortNo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET sort_no = :sort_no
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id' => $id,
            ':sort_no' => $sortNo,
        ]);
    }

    public function restore(string $id, string $actor): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id = :id
              AND deleted_at IS NOT NULL
        ");
        return $stmt->execute([':id' => $id, ':actor' => $actor]);
    }

    public function hardDelete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ledger_journal_rules WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function nextSortNo(): int
    {
        return (int) $this->db->query('SELECT COALESCE(MAX(sort_no), 0) + 1 FROM ledger_journal_rules')->fetchColumn();
    }

    private function baseSelectSql(): string
    {
        return "
            SELECT
                r.*,
                bu.code_name AS business_unit_name,
                tt.code_name AS transaction_type_name,
                td.code_name AS transaction_direction_name,
                clt.code_name AS client_type_name,
                it.code_name AS import_type_name,
                da.account_code AS debit_account_code,
                da.account_name AS debit_account_name,
                ca.account_code AS credit_account_code,
                ca.account_name AS credit_account_name,
                va.account_code AS vat_account_code,
                va.account_name AS vat_account_name
            FROM ledger_journal_rules r
            LEFT JOIN system_codes bu ON bu.deleted_at IS NULL AND bu.is_active = 1 AND bu.code_group = 'BUSINESS_UNIT' AND bu.code = r.business_unit
            LEFT JOIN system_codes tt ON tt.deleted_at IS NULL AND tt.is_active = 1 AND tt.code_group = 'TRANSACTION_TYPE' AND tt.code = r.transaction_type
            LEFT JOIN system_codes td ON td.deleted_at IS NULL AND td.is_active = 1 AND td.code_group = 'TRANSACTION_DIRECTION' AND td.code = r.transaction_direction
            LEFT JOIN system_codes clt ON clt.deleted_at IS NULL AND clt.is_active = 1 AND clt.code_group = 'CLIENT_TYPE' AND clt.code = r.client_type
            LEFT JOIN system_codes it ON it.deleted_at IS NULL AND it.is_active = 1 AND it.code_group = 'IMPORT_TYPE' AND it.code = r.import_type
            LEFT JOIN ledger_accounts da ON da.id = r.debit_account_id
            LEFT JOIN ledger_accounts ca ON ca.id = r.credit_account_id
            LEFT JOIN ledger_accounts va ON va.id = r.vat_account_id
        ";
    }

    private function normalizeBoolean(mixed $value): int
    {
        $text = strtolower(trim((string) $value));
        return in_array($text, ['1', 'true', 'yes', 'y', '사용', '활성'], true) ? 1 : 0;
    }

    private function addLikePair(array &$params, mixed $value): array
    {
        $codeKey = ':filter_' . count($params);
        $params[$codeKey] = '%' . $value . '%';

        $nameKey = ':filter_' . count($params);
        $params[$nameKey] = '%' . $value . '%';

        return [$codeKey, $nameKey];
    }
}
