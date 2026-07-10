<?php

namespace App\Services\Ledger;

use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use PDO;

class JournalLearningService
{
    /** @var array<string,bool> */
    private array $tableCache = [];

    /** @var array<string,bool> */
    private array $columnCache = [];
    /** @var array<string,array|null> */
    private array $accountCache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordVoucherDraft(array $transaction, string $voucherId, array $lines, string $actor): void
    {
        if ($voucherId === '' || $lines === []) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $context = $this->context($transaction);

        $this->recordLearningEvents($context, $voucherId, $lines, $actor, $timestamp);
        $this->recordRecentPattern($context, $lines, $timestamp);
        $this->autoSetMissingClientDefaultAccount($context, $lines);
        $this->recordClientAccountPatterns($context, $lines, $timestamp);
        $this->upsertSystemJournalRule($context, $lines, $actor, $timestamp);
        $this->reinforceJournalRules($lines, $timestamp);
    }

    private function recordLearningEvents(array $context, string $voucherId, array $lines, string $actor, string $timestamp): void
    {
        if (!$this->tableExists('ledger_journal_learning_events')) {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_journal_learning_events (
                id, transaction_id, voucher_id, voucher_line_id, client_id, project_id,
                business_unit, transaction_direction, import_type, client_type,
                line_no, line_type, recommended_line_type, final_line_type,
                recommended_account_id, final_account_id, recommended_amount, final_amount,
                recommend_source, recommend_confidence, journal_rule_id, recommend_reason,
                is_user_modified, failure_type, source_payload, created_at, created_by
            ) VALUES (
                :id, :transaction_id, :voucher_id, :voucher_line_id, :client_id, :project_id,
                :business_unit, :transaction_direction, :import_type, :client_type,
                :line_no, :line_type, :recommended_line_type, :final_line_type,
                :recommended_account_id, :final_account_id, :recommended_amount, :final_amount,
                :recommend_source, :recommend_confidence, :journal_rule_id, :recommend_reason,
                :is_user_modified, :failure_type, :source_payload, :created_at, :created_by
            )
        ");

        foreach ($lines as $index => $line) {
            $finalLineType = (string) ($line['line_type'] ?? '');
            $finalAccountId = (string) ($line['account_id'] ?? '');
            $finalAmount = (float) ($line['amount'] ?? 0);
            $recommendedLineType = (string) ($line['recommended_line_type'] ?? $finalLineType);
            $recommendedAccountId = (string) ($line['recommended_account_id'] ?? $finalAccountId);
            $recommendedAmount = (float) ($line['recommended_amount'] ?? $finalAmount);
            $isModified = !empty($line['is_user_modified'])
                || $recommendedLineType !== $finalLineType
                || $recommendedAccountId !== $finalAccountId
                || round($recommendedAmount, 2) !== round($finalAmount, 2);

            $stmt->execute([
                ':id' => UuidHelper::generate(),
                ':transaction_id' => $context['transaction_id'],
                ':voucher_id' => $voucherId,
                ':voucher_line_id' => $line['voucher_line_id'] ?? null,
                ':client_id' => $context['client_id'],
                ':project_id' => $line['project_id'] ?? $context['project_id'],
                ':business_unit' => $context['business_unit'],
                ':transaction_direction' => $context['transaction_direction'],
                ':import_type' => $context['import_type'],
                ':client_type' => $context['client_type'],
                ':line_no' => $index + 1,
                ':line_type' => $finalLineType,
                ':recommended_line_type' => $recommendedLineType,
                ':final_line_type' => $finalLineType,
                ':recommended_account_id' => $recommendedAccountId !== '' ? $recommendedAccountId : null,
                ':final_account_id' => $finalAccountId !== '' ? $finalAccountId : null,
                ':recommended_amount' => number_format($recommendedAmount, 2, '.', ''),
                ':final_amount' => number_format($finalAmount, 2, '.', ''),
                ':recommend_source' => $line['source'] ?? null,
                ':recommend_confidence' => $line['confidence'] ?? null,
                ':journal_rule_id' => $line['journal_rule_id'] ?? null,
                ':recommend_reason' => $line['reason'] ?? null,
                ':is_user_modified' => $isModified ? 1 : 0,
                ':failure_type' => $isModified ? $this->failureType($recommendedLineType, $finalLineType, $recommendedAccountId, $finalAccountId, $recommendedAmount, $finalAmount) : null,
                ':source_payload' => json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':created_at' => $timestamp,
                ':created_by' => $actor,
            ]);
        }
    }

    private function recordRecentPattern(array $context, array $lines, string $timestamp): void
    {
        if (!$this->tableExists('ledger_journal_recent_patterns')) {
            return;
        }

        $debit = $this->firstMainAccount($lines, 'DEBIT');
        $credit = $this->firstMainAccount($lines, 'CREDIT');
        $vat = $this->firstVatAccount($lines);

        if ($debit === null || $credit === null) {
            return;
        }

        $projectId = $context['project_id'] ?: null;
        $hash = sha1(implode('|', [
            $context['client_id'] ?: '',
            $context['transaction_direction'],
            $debit,
            $credit,
            $vat ?: '',
            $projectId ?: '',
        ]));

        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_journal_recent_patterns (
                id, pattern_hash, client_id, transaction_direction, debit_account_id,
                credit_account_id, vat_account_id, project_id, usage_count, last_used_at,
                created_at, updated_at
            ) VALUES (
                :id, :pattern_hash, :client_id, :transaction_direction, :debit_account_id,
                :credit_account_id, :vat_account_id, :project_id, 1, :last_used_at,
                :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                usage_count = usage_count + 1,
                last_used_at = VALUES(last_used_at),
                updated_at = VALUES(updated_at)
        ");

        $stmt->execute([
            ':id' => UuidHelper::generate(),
            ':pattern_hash' => $hash,
            ':client_id' => $context['client_id'] ?: null,
            ':transaction_direction' => $context['transaction_direction'],
            ':debit_account_id' => $debit,
            ':credit_account_id' => $credit,
            ':vat_account_id' => $vat,
            ':project_id' => $projectId,
            ':last_used_at' => $timestamp,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }

    private function recordClientAccountPatterns(array $context, array $lines, string $timestamp): void
    {
        if (!$this->tableExists('ledger_journal_client_account_patterns') || $context['client_id'] === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_journal_client_account_patterns (
                id, client_id, transaction_direction, line_type, account_id,
                usage_count, recent_score, last_used_at, created_at, updated_at
            ) VALUES (
                :id, :client_id, :transaction_direction, :line_type, :account_id,
                1, 1.0000, :last_used_at, :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                usage_count = usage_count + 1,
                recent_score = LEAST(999999.9999, recent_score + 1),
                last_used_at = VALUES(last_used_at),
                updated_at = VALUES(updated_at)
        ");

        foreach ($lines as $line) {
            $accountId = (string) ($line['account_id'] ?? '');
            $lineType = (string) ($line['line_type'] ?? '');
            if (
                $accountId === ''
                || !in_array($lineType, ['DEBIT', 'CREDIT'], true)
                || $this->isVatLine($line)
                || $this->isPaymentLikeAccount($accountId)
            ) {
                continue;
            }

            $stmt->execute([
                ':id' => UuidHelper::generate(),
                ':client_id' => $context['client_id'],
                ':transaction_direction' => $context['transaction_direction'],
                ':line_type' => $lineType,
                ':account_id' => $accountId,
                ':last_used_at' => $timestamp,
                ':created_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);
        }

        $this->maybeUpdateClientDefaultAccount($context['client_id']);
    }

    private function autoSetMissingClientDefaultAccount(array $context, array $lines): void
    {
        $clientId = trim((string) ($context['client_id'] ?? ''));
        if ($clientId === '') {
            return;
        }

        $currentStmt = $this->pdo->prepare("SELECT default_account_id FROM system_clients WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $currentStmt->execute([':id' => $clientId]);
        if (trim((string) ($currentStmt->fetchColumn() ?: '')) !== '') {
            return;
        }

        $accountId = $this->defaultAccountCandidate($context, $lines);
        if ($accountId === null) {
            return;
        }

        $update = $this->pdo->prepare("
            UPDATE system_clients
            SET default_account_id = :account_id,
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
              AND (default_account_id IS NULL OR default_account_id = '')
        ");
        $update->execute([
            ':id' => $clientId,
            ':account_id' => $accountId,
        ]);
    }

    private function defaultAccountCandidate(array $context, array $lines): ?string
    {
        $direction = strtoupper(trim((string) ($context['transaction_direction'] ?? '')));
        $preferredSide = match ($direction) {
            'OUT', 'PURCHASE' => 'DEBIT',
            'IN', 'SALES' => 'CREDIT',
            default => '',
        };

        if ($preferredSide !== '') {
            $candidate = $this->firstBusinessAccount($lines, $preferredSide);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return $this->firstBusinessAccount($lines, 'DEBIT')
            ?? $this->firstBusinessAccount($lines, 'CREDIT');
    }

    private function firstBusinessAccount(array $lines, string $lineType): ?string
    {
        foreach ($lines as $line) {
            $accountId = (string) ($line['account_id'] ?? '');
            if (($line['line_type'] ?? '') === $lineType
                && $accountId !== ''
                && !$this->isVatLine($line)
                && !$this->isPaymentLikeAccount($accountId)) {
                return $accountId;
            }
        }

        return null;
    }

    private function reinforceJournalRules(array $lines, string $timestamp): void
    {
        if (!$this->tableExists('ledger_journal_rules')
            || !$this->columnExists('ledger_journal_rules', 'usage_count')
            || !$this->columnExists('ledger_journal_rules', 'last_used_at')
            || !$this->columnExists('ledger_journal_rules', 'confidence_score')) {
            return;
        }

        $ruleIds = [];
        foreach ($lines as $line) {
            $ruleId = trim((string) ($line['journal_rule_id'] ?? ''));
            if ($ruleId !== '') {
                $ruleIds[$ruleId] = true;
            }
        }
        if ($ruleIds === []) {
            return;
        }

        $stmt = $this->pdo->prepare("
            UPDATE ledger_journal_rules
            SET usage_count = usage_count + 1,
                last_used_at = :last_used_at,
                confidence_score = LEAST(100.00, confidence_score + 1.00)
            WHERE id = :id
              AND deleted_at IS NULL
        ");

        foreach (array_keys($ruleIds) as $ruleId) {
            $stmt->execute([
                ':id' => $ruleId,
                ':last_used_at' => $timestamp,
            ]);
        }
    }

    private function upsertSystemJournalRule(array $context, array $lines, string $actor, string $timestamp): void
    {
        if (!$this->tableExists('ledger_journal_rules')) {
            return;
        }

        $debit = $this->firstMainAccount($lines, 'DEBIT');
        $credit = $this->firstMainAccount($lines, 'CREDIT');
        if ($debit === null || $credit === null) {
            return;
        }

        $vat = $this->firstVatAccount($lines);
        $hash = substr(sha1(implode('|', [
            $context['business_unit'],
            $context['transaction_direction'],
            $context['client_type'],
            $context['import_type'],
        ])), 0, 12);
        $ruleCode = 'SYS-' . strtoupper($hash);

        $find = $this->pdo->prepare("
            SELECT id
            FROM ledger_journal_rules
            WHERE rule_code = :rule_code
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $find->execute([':rule_code' => $ruleCode]);
        $existingId = (string) ($find->fetchColumn() ?: '');

        if ($existingId !== '') {
            $usageSql = $this->columnExists('ledger_journal_rules', 'usage_count') ? ', usage_count = usage_count + 1' : '';
            $lastUsedSql = $this->columnExists('ledger_journal_rules', 'last_used_at') ? ', last_used_at = :last_used_at' : '';
            $confidenceSql = $this->columnExists('ledger_journal_rules', 'confidence_score') ? ', confidence_score = LEAST(100.00, confidence_score + 0.50)' : '';
            $update = $this->pdo->prepare("
                UPDATE ledger_journal_rules
                SET debit_account_id = :debit_account_id,
                    credit_account_id = :credit_account_id,
                    vat_account_id = :vat_account_id,
                    is_active = 1,
                    description = :description,
                    updated_by = :updated_by
                    {$usageSql}
                    {$lastUsedSql}
                    {$confidenceSql}
                WHERE id = :id
                  AND deleted_at IS NULL
            ");
            $params = [
                ':id' => $existingId,
                ':debit_account_id' => $debit,
                ':credit_account_id' => $credit,
                ':vat_account_id' => $vat,
                ':description' => '시스템 학습으로 갱신된 분개규칙',
                ':updated_by' => $actor,
            ];
            if ($lastUsedSql !== '') {
                $params[':last_used_at'] = $timestamp;
            }
            $update->execute($params);
            return;
        }

        $columns = [
            'id', 'sort_no', 'rule_code', 'rule_name', 'business_unit',
            'operation_type', 'transaction_direction', 'client_type', 'import_type', 'debit_account_id',
            'credit_account_id', 'vat_account_id', 'description', 'is_active', 'created_by', 'updated_by',
        ];
        $values = [
            ':id', ':sort_no', ':rule_code', ':rule_name', ':business_unit',
            ':operation_type', ':transaction_direction', ':client_type', ':import_type', ':debit_account_id',
            ':credit_account_id', ':vat_account_id', ':description', ':is_active', ':created_by', ':updated_by',
        ];
        $params = [
            ':id' => UuidHelper::generate(),
            ':sort_no' => SequenceHelper::next('ledger_journal_rules', 'sort_no'),
            ':rule_code' => $ruleCode,
            ':rule_name' => '시스템 학습 분개규칙',
            ':business_unit' => $context['business_unit'],
            ':operation_type' => strtoupper(trim((string) ($context['operation_type'] ?? 'GENERAL'))) ?: 'GENERAL',
            ':transaction_direction' => $context['transaction_direction'],
            ':client_type' => $context['client_type'],
            ':import_type' => $context['import_type'],
            ':debit_account_id' => $debit,
            ':credit_account_id' => $credit,
            ':vat_account_id' => $vat,
            ':description' => '전표 확정 이력에서 자동 생성된 분개규칙',
            ':is_active' => 1,
            ':created_by' => 'SYSTEM:journal-learning',
            ':updated_by' => $actor,
        ];

        if ($this->columnExists('ledger_journal_rules', 'usage_count')) {
            $columns[] = 'usage_count';
            $values[] = ':usage_count';
            $params[':usage_count'] = 1;
        }
        if ($this->columnExists('ledger_journal_rules', 'last_used_at')) {
            $columns[] = 'last_used_at';
            $values[] = ':last_used_at';
            $params[':last_used_at'] = $timestamp;
        }
        if ($this->columnExists('ledger_journal_rules', 'confidence_score')) {
            $columns[] = 'confidence_score';
            $values[] = ':confidence_score';
            $params[':confidence_score'] = 50.00;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ledger_journal_rules (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $values) . ")
        ");
        $stmt->execute($params);
    }

    private function maybeUpdateClientDefaultAccount(string $clientId): void
    {
        if ($clientId === '') {
            return;
        }

        $totalStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(usage_count), 0)
            FROM ledger_journal_client_account_patterns
            WHERE client_id = :client_id
        ");
        $totalStmt->execute([':client_id' => $clientId]);
        $totalUsage = (int) $totalStmt->fetchColumn();
        if ($totalUsage <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT account_id, usage_count
            FROM ledger_journal_client_account_patterns
            WHERE client_id = :client_id
            ORDER BY usage_count DESC, last_used_at DESC
            LIMIT 1
        ");
        $stmt->execute([':client_id' => $clientId]);
        $top = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($top === null) {
            return;
        }

        $usageCount = (int) ($top['usage_count'] ?? 0);
        $ratio = $usageCount / $totalUsage;
        if ($usageCount < 3 || $ratio < 0.70) {
            return;
        }

        $currentStmt = $this->pdo->prepare("SELECT default_account_id FROM system_clients WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $currentStmt->execute([':id' => $clientId]);
        $currentAccountId = (string) ($currentStmt->fetchColumn() ?: '');

        if ($currentAccountId !== '' && ($currentAccountId === (string) $top['account_id'] || $usageCount < 5 || $ratio < 0.85)) {
            return;
        }

        $update = $this->pdo->prepare("
            UPDATE system_clients
            SET default_account_id = :account_id,
                updated_at = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ");
        $update->execute([
            ':id' => $clientId,
            ':account_id' => $top['account_id'],
        ]);
    }

    private function context(array $transaction): array
    {
        $clientId = trim((string) ($transaction['client_id'] ?? ''));

        return [
            'transaction_id' => trim((string) ($transaction['id'] ?? '')),
            'client_id' => $clientId,
            'project_id' => trim((string) ($transaction['project_id'] ?? '')),
            'business_unit' => strtoupper(trim((string) ($transaction['business_unit'] ?? 'HQ'))) ?: 'HQ',
            'transaction_direction' => strtoupper(trim((string) ($transaction['transaction_direction'] ?? 'GENERAL'))) ?: 'GENERAL',
            'import_type' => strtoupper(trim((string) ($transaction['import_type'] ?? 'ETC'))) ?: 'ETC',
            'client_type' => $this->clientType($clientId),
        ];
    }

    private function clientType(string $clientId): string
    {
        if ($clientId === '') {
            return 'CLIENT';
        }

        $stmt = $this->pdo->prepare("SELECT client_type FROM system_clients WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $clientId]);
        $type = strtoupper(trim((string) ($stmt->fetchColumn() ?: '')));

        return $type !== '' ? $type : 'CLIENT';
    }

    private function firstMainAccount(array $lines, string $lineType): ?string
    {
        foreach ($lines as $line) {
            if (($line['line_type'] ?? '') === $lineType && !$this->isVatLine($line) && !empty($line['account_id'])) {
                return (string) $line['account_id'];
            }
        }

        return null;
    }

    private function firstVatAccount(array $lines): ?string
    {
        foreach ($lines as $line) {
            if ($this->isVatLine($line) && !empty($line['account_id'])) {
                return (string) $line['account_id'];
            }
        }

        return null;
    }

    private function isPaymentLikeAccount(string $accountId): bool
    {
        $account = $this->accountById($accountId);
        if ($account === null) {
            return false;
        }

        $code = (string) ($account['account_code'] ?? '');
        $name = (string) ($account['account_name'] ?? '');

        return in_array($code, ['111100', '111200'], true)
            || str_contains($name, '현금')
            || str_contains($name, '예금');
    }

    private function accountById(string $accountId): ?array
    {
        if ($accountId === '') {
            return null;
        }
        if (array_key_exists($accountId, $this->accountCache)) {
            return $this->accountCache[$accountId];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, account_code, account_name
            FROM ledger_accounts
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $this->accountCache[$accountId] = $account;
    }

    private function isVatLine(array $line): bool
    {
        $source = strtoupper((string) ($line['source'] ?? ''));
        $reason = strtoupper((string) ($line['reason'] ?? ''));

        return $source === 'VAT_RULE' || str_contains($reason, 'VAT') || str_contains($reason, 'VAT RULE');
    }

    private function failureType(string $recommendedLineType, string $finalLineType, string $recommendedAccountId, string $finalAccountId, float $recommendedAmount, float $finalAmount): string
    {
        $types = [];
        if ($recommendedLineType !== $finalLineType) {
            $types[] = 'SIDE_CHANGED';
        }
        if ($recommendedAccountId !== $finalAccountId) {
            $types[] = 'ACCOUNT_CHANGED';
        }
        if (round($recommendedAmount, 2) !== round($finalAmount, 2)) {
            $types[] = 'AMOUNT_CHANGED';
        }

        return $types !== [] ? implode(',', $types) : 'USER_MARKED';
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
        ");
        $stmt->execute([':table' => $table]);

        return $this->tableCache[$table] = ((int) $stmt->fetchColumn() > 0);
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return $this->columnCache[$key] = ((int) $stmt->fetchColumn() > 0);
    }
}
