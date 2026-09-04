<?php

namespace App\Repositories\Ledger;

use PDO;

class JournalCandidateRepository
{
    private array $requestCache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function companyIds(): array
    {
        return array_map('strval', $this->pdo->query('SELECT id FROM system_company ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function rules(array $context, int $limit = 20): array
    {
        if (!$this->tableExists('ledger_journal_rules')) {
            return [];
        }
        $cacheKey = $this->cacheKey('rules', $context, $limit);
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        if (($context['source_type'] ?? '') !== '' && ($context['source_line_type'] ?? '') !== '') {
            $stmt = $this->pdo->prepare("SELECT
                    MIN(CASE WHEN accounting_role_code='EXPENSE' AND debit_credit='DEBIT' THEN id END) AS id,
                    MIN(CASE WHEN accounting_role_code='EXPENSE' AND debit_credit='DEBIT' THEN id END) AS debit_rule_id,
                    MAX(CASE WHEN accounting_role_code='EXPENSE' AND debit_credit='DEBIT' THEN revision_no END) AS debit_rule_revision_no,
                    MIN(CASE WHEN accounting_role_code='EMPLOYEE_ACCRUED_EXPENSE' AND debit_credit='CREDIT' THEN id END) AS credit_rule_id,
                    MAX(CASE WHEN accounting_role_code='EMPLOYEE_ACCRUED_EXPENSE' AND debit_credit='CREDIT' THEN revision_no END) AS credit_rule_revision_no,
                    MAX(CASE WHEN accounting_role_code='EXPENSE' AND debit_credit='DEBIT' THEN account_id END) AS debit_account_id,
                    MAX(CASE WHEN accounting_role_code='EMPLOYEE_ACCRUED_EXPENSE' AND debit_credit='CREDIT' THEN account_id END) AS credit_account_id,
                    NULL AS vat_account_id,MAX(usage_count) AS usage_count,MAX(last_used_at) AS last_used_at,
                    MAX(confidence_score) AS confidence_score,MAX(client_type) AS client_type
                FROM ledger_journal_rules
                WHERE company_id=:company_id AND deleted_at IS NULL AND is_active=1 AND rule_status='ACTIVE'
                  AND business_unit=:business_unit AND operation_type=:operation_type AND transaction_direction=:transaction_direction
                  AND import_type=:import_type AND source_type=:source_type AND source_line_type=:source_line_type
                  AND (effective_from IS NULL OR effective_from<=:effective_from_date) AND (effective_to IS NULL OR effective_to>=:effective_to_date)
                  AND ((accounting_role_code='EXPENSE' AND debit_credit='DEBIT' AND item_code=:item_code)
                    OR (accounting_role_code='EMPLOYEE_ACCRUED_EXPENSE' AND debit_credit='CREDIT' AND item_code IS NULL))
                HAVING debit_account_id IS NOT NULL AND credit_account_id IS NOT NULL LIMIT 1");
            $stmt->execute([
                ':company_id'=>$context['company_id'], ':business_unit'=>$context['business_unit'],
                ':operation_type'=>$context['operation_type'], ':transaction_direction'=>$context['transaction_direction'],
                ':import_type'=>$context['import_type'], ':source_type'=>$context['source_type'],
                ':source_line_type'=>$context['source_line_type'], ':item_code'=>$context['item_code'],
                ':effective_from_date'=>$context['base_date'], ':effective_to_date'=>$context['base_date'],
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            return $this->requestCache[$cacheKey] = ($row ? [$row] : []);
        }
        $stmt = $this->pdo->prepare("
            SELECT id, debit_account_id, credit_account_id, vat_account_id,
                   business_unit, operation_type, transaction_direction, client_type, import_type,
                   usage_count, last_used_at, confidence_score, sort_no
            FROM ledger_journal_rules
            WHERE deleted_at IS NULL AND is_active = 1
              AND business_unit = :business_unit
              AND operation_type = :operation_type
              AND transaction_direction = :transaction_direction
              AND import_type = :import_type
              AND (client_type = :client_type OR client_type IS NULL OR client_type = '')
            ORDER BY CASE WHEN client_type = :client_type_order THEN 0 ELSE 1 END,
                     confidence_score DESC, usage_count DESC, last_used_at DESC, sort_no ASC
            LIMIT " . max(1, min($limit, 100))
        );
        $stmt->execute([
            ':business_unit' => $context['business_unit'],
            ':operation_type' => $context['operation_type'],
            ':transaction_direction' => $context['transaction_direction'],
            ':import_type' => $context['import_type'],
            ':client_type' => $context['client_type'],
            ':client_type_order' => $context['client_type'],
        ]);
        return $this->requestCache[$cacheKey] = ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function clientPatterns(array $context, int $limit = 20): array
    {
        if (!$this->tableExists('ledger_journal_client_account_patterns') || $context['client_id'] === '') {
            return [];
        }
        $cacheKey = $this->cacheKey('client-patterns', $context, $limit);
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        [$clientSql, $clientParams] = $this->clientIdentityCondition('client_id', $context['client_id'], 'client_pattern');
        $stmt = $this->pdo->prepare("
            SELECT id, line_type, account_id, usage_count, recent_score, last_used_at
            FROM ledger_journal_client_account_patterns
            WHERE {$clientSql} AND transaction_direction = :transaction_direction
            ORDER BY recent_score DESC, usage_count DESC, last_used_at DESC
            LIMIT " . max(1, min($limit, 100))
        );
        $stmt->execute($clientParams + [':transaction_direction' => $context['transaction_direction']]);
        return $this->requestCache[$cacheKey] = ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function clientDefaultAccount(array $context): ?array
    {
        if (($context['client_id'] ?? '') === '' || !$this->tableExists('system_clients')) {
            return null;
        }
        $cacheKey = $this->cacheKey('client-default', $context);
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        [$clientSql, $params] = $this->clientIdentityCondition('id', $context['client_id'], 'default_account');
        $stmt = $this->pdo->prepare(
            "SELECT id AS client_id, default_account_id FROM system_clients"
            . " WHERE {$clientSql} AND deleted_at IS NULL AND default_account_id IS NOT NULL"
            . ' ORDER BY CASE WHEN id = :selected_client_id THEN 0 ELSE 1 END LIMIT 1'
        );
        $params[':selected_client_id'] = $context['client_id'];
        $stmt->execute($params);
        return $this->requestCache[$cacheKey] = ($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
    }

    public function accountReferencePolicies(array $accountIds): array
    {
        $accountIds = array_values(array_unique(array_filter(array_map('strval', $accountIds))));
        if ($accountIds === [] || !$this->tableExists('ledger_accounts_sub')) return [];
        sort($accountIds);
        $cacheKey = 'account-policies:' . hash('sha256', implode('|', $accountIds));
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        $placeholders = [];
        $params = [];
        foreach ($accountIds as $index => $accountId) {
            $placeholder = ':policy_account_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $accountId;
        }
        $stmt = $this->pdo->prepare(
            'SELECT account_id, ref_target, is_required FROM ledger_accounts_sub'
            . ' WHERE account_id IN (' . implode(', ', $placeholders) . ') ORDER BY account_id, sort_no'
        );
        $stmt->execute($params);
        $policies = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $policies[(string) $row['account_id']][] = $row;
        }
        return $this->requestCache[$cacheKey] = $policies;
    }

    public function usableAccountIds(array $accountIds): array
    {
        $accountIds = array_values(array_unique(array_filter(array_map('strval', $accountIds))));
        if ($accountIds === []) return [];
        sort($accountIds);
        $cacheKey = 'usable-accounts:' . hash('sha256', implode('|', $accountIds));
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        $placeholders = implode(', ', array_fill(0, count($accountIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM ledger_accounts WHERE id IN ({$placeholders}) AND deleted_at IS NULL AND is_active = 1 AND COALESCE(is_posting, 1) = 1");
        $stmt->execute($accountIds);
        return $this->requestCache[$cacheKey] = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function recentPatterns(array $context, int $limit = 30): array
    {
        if (!$this->tableExists('ledger_journal_recent_patterns')) {
            return [];
        }
        $cacheKey = $this->cacheKey('recent-patterns', $context, $limit);
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        $where = ['transaction_direction = :transaction_direction'];
        $params = [':transaction_direction' => $context['transaction_direction']];
        if ($context['client_id'] !== '') {
            [$clientSql, $clientParams] = $this->clientIdentityCondition('client_id', $context['client_id'], 'recent');
            $where[] = "({$clientSql} OR client_id IS NULL)";
            $params += $clientParams;
        }
        if ($context['project_id'] !== '') {
            $where[] = '(project_id = :project_id OR project_id IS NULL)';
            $params[':project_id'] = $context['project_id'];
        }
        $stmt = $this->pdo->prepare("
            SELECT id, pattern_hash, client_id, project_id, debit_account_id, credit_account_id,
                   vat_account_id, usage_count, last_used_at
            FROM ledger_journal_recent_patterns
            WHERE " . implode(' AND ', $where) . "
            ORDER BY usage_count DESC, last_used_at DESC
            LIMIT " . max(1, min($limit, 100))
        );
        $stmt->execute($params);
        return $this->requestCache[$cacheKey] = ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function learningPatterns(array $context, int $limit = 30): array
    {
        if (!$this->tableExists('ledger_journal_learning_events')) {
            return [];
        }
        $cacheKey = $this->cacheKey('learning-patterns', $context, $limit);
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        $where = [
            "event.event_type = 'POSTED_CONFIRMATION'",
            'event.transaction_direction = :transaction_direction',
            'event.import_type = :import_type',
            "(event.failure_type IS NULL OR event.failure_type = '')",
            "voucher.status IN ('POSTED', 'CLOSED')",
            'voucher.is_reversal = 0',
            'voucher.deleted_at IS NULL',
        ];
        $params = [
            ':transaction_direction' => $context['transaction_direction'],
            ':import_type' => $context['import_type'],
        ];
        $clientId = trim((string) ($context['client_id'] ?? ''));
        if ($clientId !== '') {
            [$clientSql, $clientParams] = $this->clientIdentityCondition('event.client_id', $clientId, 'learning');
            $where[] = "({$clientSql} OR event.client_id IS NULL)";
            $params += $clientParams;
        }
        $stmt = $this->pdo->prepare("
            SELECT event.voucher_id,
                   SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN event.final_line_type = 'DEBIT' THEN event.final_account_id END ORDER BY event.final_amount DESC), ',', 1) AS debit_account_id,
                   SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN event.final_line_type = 'CREDIT' THEN event.final_account_id END ORDER BY event.final_amount DESC), ',', 1) AS credit_account_id,
                   CASE
                        WHEN event.transaction_direction = 'OUT' AND SUM(event.final_line_type = 'DEBIT') > 1
                            THEN SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN event.final_line_type = 'DEBIT' THEN event.final_account_id END ORDER BY event.final_amount ASC), ',', 1)
                        WHEN event.transaction_direction = 'IN' AND SUM(event.final_line_type = 'CREDIT') > 1
                            THEN SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN event.final_line_type = 'CREDIT' THEN event.final_account_id END ORDER BY event.final_amount ASC), ',', 1)
                        ELSE NULL
                    END AS vat_account_id,
                   MAX(event.journal_rule_id) AS journal_rule_id,
                   COUNT(*) AS event_count,
                   SUM(CASE WHEN event.is_user_modified = 0 THEN 1 ELSE 0 END) AS accepted_count,
                   SUM(CASE WHEN event.is_user_modified = 1 THEN 1 ELSE 0 END) AS modified_count,
                   MAX(event.created_at) AS last_used_at
             FROM ledger_journal_learning_events event
             INNER JOIN ledger_vouchers voucher
                     ON voucher.id COLLATE utf8mb4_general_ci = event.voucher_id COLLATE utf8mb4_general_ci
             WHERE " . implode(' AND ', $where) . "
             GROUP BY event.voucher_id
            HAVING debit_account_id IS NOT NULL AND credit_account_id IS NOT NULL
            ORDER BY accepted_count DESC, event_count DESC, last_used_at DESC
            LIMIT " . max(1, min($limit, 100))
        );
        $stmt->execute($params);
        return $this->requestCache[$cacheKey] = ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function clientIdentityCondition(string $column, string $clientId, string $prefix): array
    {
        $placeholders = [];
        $params = [];
        foreach ($this->equivalentClientIds($clientId) as $index => $id) {
            $placeholder = ':' . $prefix . '_client_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }
        return [sprintf('%s IN (%s)', $column, implode(', ', $placeholders)), $params];
    }

    private function equivalentClientIds(string $clientId): array
    {
        return [$clientId];
    }

    private function tableExists(string $table): bool
    {
        $cacheKey = 'table:' . $table;
        if (array_key_exists($cacheKey, $this->requestCache)) return $this->requestCache[$cacheKey];
        $stmt = $this->pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
        $stmt->execute([':table' => $table]);
        return $this->requestCache[$cacheKey] = (bool) $stmt->fetchColumn();
    }

    private function cacheKey(string $scope, array $context, ?int $limit = null): string
    {
        $key = $scope . ':' . hash('sha256', serialize($context));
        return $limit === null ? $key : $key . ':' . $limit;
    }
}
