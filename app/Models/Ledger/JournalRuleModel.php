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
                case 'operation_type':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.operation_type LIKE {$codeKey} OR ot.code_name LIKE {$nameKey})";
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
                case 'item_code':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.item_code LIKE {$codeKey} OR pec.code_name LIKE {$nameKey})";
                    break;
                case 'accounting_role_code':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(r.accounting_role_code LIKE {$codeKey} OR role.code_name LIKE {$nameKey})";
                    break;
                case 'result_account_name':
                    [$codeKey, $nameKey] = $this->addLikePair($params, $value);
                    $where[] = "(ra.account_code LIKE {$codeKey} OR ra.account_name LIKE {$nameKey})";
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
              AND r.operation_type = :operation_type
              AND r.transaction_direction = :transaction_direction
              AND r.client_type = :client_type
              AND r.import_type = :import_type
            ORDER BY r.sort_no ASC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':business_unit' => strtoupper(trim((string) ($criteria['business_unit'] ?? ''))),
            ':operation_type' => strtoupper(trim((string) ($criteria['operation_type'] ?? 'GENERAL'))) ?: 'GENERAL',
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

    public function activeCodeExists(string $group, string $code): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM system_codes WHERE code_group = :code_group AND code = :code AND is_active = 1');
        $stmt->execute([':code_group' => $group, ':code' => $code]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function usableAccountExists(string $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ledger_accounts WHERE id = :id AND deleted_at IS NULL AND is_active = 1 AND COALESCE(is_posting, 1) = 1");
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function resolveCompanyId(): string
    {
        $ids = $this->db->query('SELECT id FROM system_company ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($ids) !== 1) {
            throw new \RuntimeException('분개규칙 회사 범위를 확정할 수 없습니다.');
        }
        return (string) $ids[0];
    }

    public function supportsRoleOnlyWrite(): bool
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_journal_rules' AND COLUMN_NAME IN ('credit_account_id','debit_account_id','vat_account_id') AND IS_NULLABLE='NO'");
        return (int) $stmt->fetchColumn() === 0;
    }

    public function roleConditionConflict(string $companyId, string $conditionHash, string $roleCode, string $side, ?string $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM ledger_journal_rules WHERE company_id=:company_id AND condition_hash=:condition_hash AND accounting_role_code=:role_code AND debit_credit=:side AND deleted_at IS NULL';
        $params = [':company_id' => $companyId, ':condition_hash' => $conditionHash, ':role_code' => $roleCode, ':side' => $side];
        if ($excludeId !== null && $excludeId !== '') {
            $sql .= ' AND id<>:exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createRoleRule(array $row): void
    {
        $columns = ['id','company_id','sort_no','rule_code','rule_name','business_unit','operation_type','transaction_direction','client_type','import_type','source_type','source_line_type','item_code','condition_hash','origin_code','rule_status','accounting_role_code','debit_credit','account_id','amount_policy_code','is_locked','auto_apply_enabled','effective_from','effective_to','priority_no','revision_no','debit_account_id','credit_account_id','vat_account_id','description','is_active','created_by','updated_by'];
        $stmt = $this->db->prepare('INSERT INTO ledger_journal_rules (' . implode(',', $columns) . ') VALUES (' . implode(',', array_map(static fn (string $column): string => ':' . $column, $columns)) . ')');
        $params = [];
        foreach ($columns as $column) {
            $params[':' . $column] = $row[$column] ?? null;
        }
        $stmt->execute($params);
    }

    public function updateRoleRule(string $id, string $companyId, array $row): void
    {
        $columns = ['rule_code','rule_name','business_unit','operation_type','transaction_direction','client_type','import_type','source_type','source_line_type','item_code','condition_hash','origin_code','rule_status','accounting_role_code','debit_credit','account_id','amount_policy_code','is_locked','auto_apply_enabled','effective_from','effective_to','priority_no','debit_account_id','credit_account_id','vat_account_id','description','is_active','updated_by'];
        $sets = array_map(static fn (string $column): string => $column . '=:' . $column, $columns);
        $stmt = $this->db->prepare('UPDATE ledger_journal_rules SET ' . implode(',', $sets) . ',updated_at=NOW() WHERE id=:id AND company_id=:company_id AND deleted_at IS NULL');
        $params = [':id' => $id, ':company_id' => $companyId];
        foreach ($columns as $column) {
            $params[':' . $column] = $row[$column] ?? null;
        }
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('수정할 역할형 분개규칙을 찾을 수 없습니다.');
        }
    }

    public function conditionConflict(array $data, ?string $excludeId = null): ?array
    {
        $sql = "SELECT id, debit_account_id, credit_account_id, vat_account_id
                FROM ledger_journal_rules
                WHERE deleted_at IS NULL
                  AND business_unit = :business_unit
                  AND operation_type = :operation_type
                  AND transaction_direction = :transaction_direction
                  AND import_type = :import_type
                  AND COALESCE(client_type, '') = COALESCE(:client_type, '')";
        $params = [
            ':business_unit' => $data[':business_unit'],
            ':operation_type' => $data[':operation_type'],
            ':transaction_direction' => $data[':transaction_direction'],
            ':import_type' => $data[':import_type'],
            ':client_type' => $data[':client_type'],
        ];
        if ($excludeId !== null && $excludeId !== '') {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function referencedRuleIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) return [];
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT DISTINCT journal_rule_id FROM ledger_voucher_lines WHERE journal_rule_id IN ({$placeholders})");
        $stmt->execute($ids);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function recordConfirmedUsage(string $voucherId, string $actor): int
    {
        $stmt = $this->db->prepare("UPDATE ledger_journal_rules r
            INNER JOIN (
                SELECT DISTINCT l.journal_rule_id
                FROM ledger_voucher_lines l
                INNER JOIN ledger_vouchers v ON v.id = l.voucher_id
                WHERE l.voucher_id = :voucher_id
                  AND COALESCE(v.is_reversal, 0) = 0
                  AND l.journal_rule_id IS NOT NULL
                  AND l.journal_rule_id <> ''
                  AND l.is_user_modified = 0
            ) used ON used.journal_rule_id = r.id
            SET r.usage_count = r.usage_count + 1,
                r.last_used_at = NOW(),
                r.updated_at = NOW(),
                r.updated_by = :actor
            WHERE r.deleted_at IS NULL AND r.is_active = 1");
        $stmt->execute([':voucher_id' => $voucherId, ':actor' => $actor]);
        return $stmt->rowCount();
    }

    public function confirmedUsageRuleIds(string $voucherId): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT l.journal_rule_id
            FROM ledger_voucher_lines l
            INNER JOIN ledger_vouchers v ON v.id = l.voucher_id
            INNER JOIN ledger_journal_rules r ON r.id = l.journal_rule_id
            WHERE l.voucher_id = :voucher_id
              AND COALESCE(v.is_reversal, 0) = 0
              AND l.journal_rule_id IS NOT NULL
              AND l.journal_rule_id <> ''
              AND l.is_user_modified = 0
              AND r.deleted_at IS NULL
              AND r.is_active = 1");
        $stmt->execute([':voucher_id' => $voucherId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO ledger_journal_rules (
                id, sort_no, rule_code, rule_name, business_unit,
                operation_type, transaction_direction, client_type, import_type, debit_account_id,
                credit_account_id, vat_account_id, description, is_active, created_by, updated_by
            ) VALUES (
                :id, :sort_no, :rule_code, :rule_name, :business_unit,
                :operation_type, :transaction_direction, :client_type, :import_type, :debit_account_id,
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
                operation_type = :operation_type,
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
                deleted_by = :deleted_actor,
                updated_by = :updated_actor
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        return $stmt->execute([
            ':id' => $id,
            ':deleted_actor' => $actor,
            ':updated_actor' => $actor,
        ]) && $stmt->rowCount() > 0;
    }

    public function softDeleteByIds(array $ids, string $actor): int
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = [];
        $params = [
            ':deleted_actor' => $actor,
            ':updated_actor' => $actor,
        ];
        foreach ($ids as $index => $id) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET is_active = 0,
                deleted_at = NOW(),
                deleted_by = :deleted_actor,
                updated_by = :updated_actor
            WHERE id IN (" . implode(', ', $placeholders) . ")
              AND deleted_at IS NULL
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
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
        return $stmt->execute([':id' => $id, ':actor' => $actor]) && $stmt->rowCount() > 0;
    }

    public function restoreByIds(array $ids, string $actor): int
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = [];
        $params = [':actor' => $actor];
        foreach ($ids as $index => $id) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE id IN (" . implode(', ', $placeholders) . ")
              AND deleted_at IS NOT NULL
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function restoreAllDeleted(string $actor): int
    {
        $stmt = $this->db->prepare("
            UPDATE ledger_journal_rules
            SET deleted_at = NULL,
                deleted_by = NULL,
                updated_by = :actor
            WHERE deleted_at IS NOT NULL
        ");
        $stmt->execute([':actor' => $actor]);

        return $stmt->rowCount();
    }

    public function hardDelete(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ledger_journal_rules WHERE id = :id');
        return $stmt->execute([':id' => $id]) && $stmt->rowCount() > 0;
    }

    public function hardDeleteByIds(array $ids): int
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $stmt = $this->db->prepare('
            DELETE FROM ledger_journal_rules
            WHERE id IN (' . implode(', ', $placeholders) . ')
        ');
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function hardDeleteAllDeleted(): int
    {
        $stmt = $this->db->prepare('DELETE FROM ledger_journal_rules WHERE deleted_at IS NOT NULL');
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function baseSelectSql(): string
    {
        return "
            SELECT
                r.*,
                bu.code_name AS business_unit_name,
                ot.code_name AS operation_type_name,
                td.code_name AS transaction_direction_name,
                clt.code_name AS client_type_name,
                it.code_name AS import_type_name,
                st.code_name AS source_type_name,
                slt.code_name AS source_line_type_name,
                pec.code_name AS item_code_name,
                role.code_name AS accounting_role_name,
                ra.account_code AS result_account_code,
                ra.account_name AS result_account_name,
                rv.id AS latest_revision_id,
                rv.action_code AS latest_revision_action,
                da.account_code AS debit_account_code,
                da.account_name AS debit_account_name,
                ca.account_code AS credit_account_code,
                ca.account_name AS credit_account_name,
                va.account_code AS vat_account_code,
                va.account_name AS vat_account_name
            FROM ledger_journal_rules r
            LEFT JOIN system_codes bu ON bu.is_active = 1 AND bu.code_group = 'BUSINESS_UNIT' AND bu.code = r.business_unit
            LEFT JOIN system_codes ot ON ot.is_active = 1 AND ot.code_group = 'OPERATION_TYPE' AND ot.code = r.operation_type
            LEFT JOIN system_codes td ON td.is_active = 1 AND td.code_group = 'TRANSACTION_DIRECTION' AND td.code = r.transaction_direction
            LEFT JOIN system_codes clt ON clt.is_active = 1 AND clt.code_group = 'CLIENT_TYPE' AND clt.code = r.client_type
            LEFT JOIN system_codes it ON it.is_active = 1 AND it.code_group = 'IMPORT_TYPE' AND it.code = r.import_type
            LEFT JOIN system_codes st ON st.is_active = 1 AND st.code_group = 'SOURCE_TYPE' AND st.code = r.source_type
            LEFT JOIN system_codes slt ON slt.is_active = 1 AND slt.code_group = 'SOURCE_LINE_TYPE' AND slt.code = r.source_line_type
            LEFT JOIN system_codes pec ON pec.is_active = 1 AND pec.code_group = 'PERSONAL_EXPENSE_CATEGORY' AND pec.code = r.item_code
            LEFT JOIN system_codes role ON role.is_active = 1 AND role.code_group = 'JOURNAL_ACCOUNTING_ROLE' AND role.code = r.accounting_role_code
            LEFT JOIN ledger_accounts ra ON ra.id = r.account_id
            LEFT JOIN ledger_journal_rule_revisions rv ON rv.rule_id = r.id AND rv.revision_no = r.revision_no
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
