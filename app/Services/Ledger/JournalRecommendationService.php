<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionItemModel;
use App\Models\Ledger\TransactionModel;
use PDO;

class JournalRecommendationService
{
    private TransactionModel $transactionModel;
    private TransactionItemModel $transactionItemModel;
    /** @var array<string,bool> */
    private array $tableCache = [];
    /** @var array<string,bool> */
    private array $columnCache = [];

    public function __construct(private readonly PDO $pdo)
    {
        $this->transactionModel = new TransactionModel($pdo);
        $this->transactionItemModel = new TransactionItemModel($pdo);
    }

    public function recommendForTransaction(string $transactionId): array
    {
        $transaction = $this->transactionModel->getById($transactionId);
        if (!$transaction || !empty($transaction['deleted_at'])) {
            throw new \InvalidArgumentException('거래를 찾을 수 없습니다.');
        }

        $items = $this->transactionItemModel->getByTransactionId($transactionId);
        $context = $this->transactionContext($transaction);
        $amounts = $this->transactionAmounts($transaction, $items);
        $rule = $this->findJournalRule($context);
        $clientDefault = $this->clientDefaultAccount((string) ($transaction['client_id'] ?? ''));

        $mainDebitAccount = null;
        $mainCreditAccount = null;
        $debitSource = null;
        $creditSource = null;

        if ($clientDefault !== null) {
            $side = $this->sideForClientDefault($context['transaction_direction'], $clientDefault);
            if ($side === 'DEBIT') {
                $mainDebitAccount = $clientDefault;
                $debitSource = ['source' => 'CLIENT_DEFAULT', 'confidence' => 100, 'reason' => '거래처 기본계정'];
            } else {
                $mainCreditAccount = $clientDefault;
                $creditSource = ['source' => 'CLIENT_DEFAULT', 'confidence' => 100, 'reason' => '거래처 기본계정'];
            }
        }

        if ($rule !== null) {
            if ($mainDebitAccount === null && !empty($rule['debit_account_id'])) {
                $mainDebitAccount = $this->accountById((string) $rule['debit_account_id']);
                $debitSource = ['source' => 'JOURNAL_RULE', 'confidence' => 90, 'reason' => '분개규칙 매칭', 'rule_id' => (string) $rule['id']];
            }
            if ($mainCreditAccount === null && !empty($rule['credit_account_id'])) {
                $mainCreditAccount = $this->accountById((string) $rule['credit_account_id']);
                $creditSource = ['source' => 'JOURNAL_RULE', 'confidence' => 90, 'reason' => '분개규칙 매칭', 'rule_id' => (string) $rule['id']];
            }
        }

        if ($mainDebitAccount === null) {
            $recent = $this->recentPatternAccount($transaction, $context, 'DEBIT');
            if ($recent !== null) {
                $mainDebitAccount = $recent;
                $debitSource = ['source' => 'RECENT_PATTERN', 'confidence' => 70, 'reason' => '최근 사용 패턴'];
            }
        }
        if ($mainCreditAccount === null) {
            $recent = $this->recentPatternAccount($transaction, $context, 'CREDIT');
            if ($recent !== null) {
                $mainCreditAccount = $recent;
                $creditSource = ['source' => 'RECENT_PATTERN', 'confidence' => 70, 'reason' => '최근 사용 패턴'];
            }
        }

        if ($mainDebitAccount === null) {
            $mainDebitAccount = $this->fallbackAccount($context['transaction_direction'], 'DEBIT');
            if ($mainDebitAccount !== null) {
                $debitSource = ['source' => 'JOURNAL_RULE', 'confidence' => 60, 'reason' => '기본 추천 계정'];
            }
        }
        if ($mainCreditAccount === null) {
            $mainCreditAccount = $this->fallbackAccount($context['transaction_direction'], 'CREDIT');
            if ($mainCreditAccount !== null) {
                $creditSource = ['source' => 'JOURNAL_RULE', 'confidence' => 60, 'reason' => '기본 추천 계정'];
            }
        }

        $recommendations = [];
        $direction = $context['transaction_direction'];
        $summary = (string) ($transaction['description'] ?? '');

        if (in_array($direction, ['PURCHASE', 'OUT'], true)) {
            if ($mainDebitAccount !== null && $amounts['supply'] > 0) {
                $recommendations[] = $this->line('DEBIT', $mainDebitAccount, $amounts['supply'], $debitSource, $summary);
            }
            $vatAccount = $this->vatAccount($direction, $rule);
            if ($vatAccount !== null && $amounts['vat'] > 0) {
                $recommendations[] = $this->line('DEBIT', $vatAccount, $amounts['vat'], [
                    'source' => !empty($rule['vat_account_id']) ? 'JOURNAL_RULE' : 'VAT_RULE',
                    'confidence' => 100,
                    'reason' => !empty($rule['vat_account_id']) ? '분개규칙 부가세계정' : '거래라인 부가세',
                    'rule_id' => (string) ($rule['id'] ?? ''),
                ], $summary);
            }
            if ($mainCreditAccount !== null && $amounts['total'] > 0) {
                $recommendations[] = $this->line('CREDIT', $mainCreditAccount, $amounts['total'], $creditSource, $summary);
            }
        } else {
            if ($mainDebitAccount !== null && $amounts['total'] > 0) {
                $recommendations[] = $this->line('DEBIT', $mainDebitAccount, $amounts['total'], $debitSource, $summary);
            }
            if ($mainCreditAccount !== null && $amounts['supply'] > 0) {
                $recommendations[] = $this->line('CREDIT', $mainCreditAccount, $amounts['supply'], $creditSource, $summary);
            }
            $vatAccount = $this->vatAccount($direction, $rule);
            if ($vatAccount !== null && $amounts['vat'] > 0) {
                $recommendations[] = $this->line('CREDIT', $vatAccount, $amounts['vat'], [
                    'source' => !empty($rule['vat_account_id']) ? 'JOURNAL_RULE' : 'VAT_RULE',
                    'confidence' => 100,
                    'reason' => !empty($rule['vat_account_id']) ? '분개규칙 부가세계정' : '거래라인 부가세',
                    'rule_id' => (string) ($rule['id'] ?? ''),
                ], $summary);
            }
        }

        $recommendationSet = [
            'set_id' => 'SET-1',
            'set_name' => '규칙 기반 추천 분개세트',
            'line_count' => count($recommendations),
            'balanced' => $this->isBalanced($recommendations),
        ];
        foreach ($recommendations as $index => $line) {
            $recommendations[$index]['set_id'] = $recommendationSet['set_id'];
        }

        return [
            'transaction_id' => $transactionId,
            'context' => $context,
            'journal_rule_id' => $rule['id'] ?? null,
            'recommendation_sets' => [$recommendationSet],
            'recommendations' => $recommendations,
            'balanced' => $recommendationSet['balanced'],
        ];
    }

    public function recommendForPayload(array $payload): array
    {
        $withdraw = $this->amountOrNull($payload['withdraw_amount'] ?? null);
        $deposit = $this->amountOrNull($payload['deposit_amount'] ?? null);
        $amount = abs((float) (
            $withdraw
            ?? $deposit
            ?? $this->amountOrNull($payload['total_amount'] ?? null)
            ?? $this->amountOrNull($payload['amount'] ?? null)
            ?? 0
        ));
        $direction = strtoupper(trim((string) ($payload['transaction_direction'] ?? $payload['bank_direction'] ?? '')));
        if ($direction === '') {
            $direction = ($withdraw !== null && abs($withdraw) > 0) ? 'OUT' : (($deposit !== null && abs($deposit) > 0) ? 'IN' : 'GENERAL');
        }

        $vat = abs((float) ($this->amountOrNull($payload['vat_amount'] ?? null) ?? 0));
        $supply = abs((float) ($this->amountOrNull($payload['supply_amount'] ?? null) ?? 0));
        if ($supply <= 0 && $amount > 0) {
            $supply = max(0.0, $amount - $vat);
        }

        $transaction = [
            'id' => '',
            'client_id' => $this->clientIdFromPayload($payload),
            'project_id' => trim((string) ($payload['project_id'] ?? '')),
            'business_unit' => strtoupper(trim((string) ($payload['business_unit'] ?? 'HQ'))) ?: 'HQ',
            'transaction_direction' => $direction,
            'import_type' => strtoupper(trim((string) ($payload['import_type'] ?? $payload['source_type'] ?? 'BANK_TRANSACTION'))) ?: 'BANK_TRANSACTION',
            'description' => trim((string) ($payload['description'] ?? $payload['summary_text'] ?? $payload['voucher_summary_text'] ?? '')),
        ];
        $context = $this->transactionContext($transaction);
        $amounts = [
            'supply' => round($supply, 2),
            'vat' => round($vat, 2),
            'total' => round($amount ?: ($supply + $vat), 2),
        ];

        $rule = $this->findJournalRule($context);
        $clientDefault = $this->clientDefaultAccount((string) ($transaction['client_id'] ?? ''));
        $mainDebitAccount = null;
        $mainCreditAccount = null;
        $debitSource = null;
        $creditSource = null;

        if ($clientDefault !== null) {
            $side = $this->sideForClientDefault($context['transaction_direction'], $clientDefault);
            if ($side === 'DEBIT') {
                $mainDebitAccount = $clientDefault;
                $debitSource = ['source' => 'CLIENT_DEFAULT', 'confidence' => 100, 'reason' => '거래처 기본계정'];
            } else {
                $mainCreditAccount = $clientDefault;
                $creditSource = ['source' => 'CLIENT_DEFAULT', 'confidence' => 100, 'reason' => '거래처 기본계정'];
            }
        }

        if ($rule !== null) {
            if ($mainDebitAccount === null && !empty($rule['debit_account_id'])) {
                $mainDebitAccount = $this->accountById((string) $rule['debit_account_id']);
                $debitSource = ['source' => 'JOURNAL_RULE', 'confidence' => 90, 'reason' => '분개규칙 매칭', 'rule_id' => (string) $rule['id']];
            }
            if ($mainCreditAccount === null && !empty($rule['credit_account_id'])) {
                $mainCreditAccount = $this->accountById((string) $rule['credit_account_id']);
                $creditSource = ['source' => 'JOURNAL_RULE', 'confidence' => 90, 'reason' => '분개규칙 매칭', 'rule_id' => (string) $rule['id']];
            }
        }

        if ($mainDebitAccount === null || $mainCreditAccount === null) {
            $recentPair = $this->recentPatternPair($transaction, $context);
            if ($mainDebitAccount === null && !empty($recentPair['debit_account'])) {
                $mainDebitAccount = $recentPair['debit_account'];
                $debitSource = ['source' => 'RECENT_PATTERN', 'confidence' => 75, 'reason' => '최근 확정 분개 패턴'];
            }
            if ($mainCreditAccount === null && !empty($recentPair['credit_account'])) {
                $mainCreditAccount = $recentPair['credit_account'];
                $creditSource = ['source' => 'RECENT_PATTERN', 'confidence' => 75, 'reason' => '최근 확정 분개 패턴'];
            }
        }

        if ($mainDebitAccount === null) {
            $mainDebitAccount = $this->fallbackAccount($context['transaction_direction'], 'DEBIT');
            if ($mainDebitAccount !== null) {
                $debitSource = ['source' => 'SYSTEM_FALLBACK', 'confidence' => 60, 'reason' => '시스템 기본 추천'];
            }
        }
        if ($mainCreditAccount === null) {
            $mainCreditAccount = $this->fallbackAccount($context['transaction_direction'], 'CREDIT');
            if ($mainCreditAccount !== null) {
                $creditSource = ['source' => 'SYSTEM_FALLBACK', 'confidence' => 60, 'reason' => '시스템 기본 추천'];
            }
        }

        $recommendations = [];
        $summary = (string) ($transaction['description'] ?? '');
        if (in_array($context['transaction_direction'], ['PURCHASE', 'OUT'], true)) {
            if ($mainDebitAccount !== null && $amounts['supply'] > 0) {
                $recommendations[] = $this->line('DEBIT', $mainDebitAccount, $amounts['supply'], $debitSource, $summary);
            }
            $vatAccount = $this->vatAccount($context['transaction_direction'], $rule);
            if ($vatAccount !== null && $amounts['vat'] > 0) {
                $recommendations[] = $this->line('DEBIT', $vatAccount, $amounts['vat'], [
                    'source' => !empty($rule['vat_account_id']) ? 'JOURNAL_RULE' : 'VAT_RULE',
                    'confidence' => 100,
                    'reason' => !empty($rule['vat_account_id']) ? '분개규칙 부가세계정' : '거래라인 부가세',
                    'rule_id' => (string) ($rule['id'] ?? ''),
                ], $summary);
            }
            if ($mainCreditAccount !== null && $amounts['total'] > 0) {
                $recommendations[] = $this->line('CREDIT', $mainCreditAccount, $amounts['total'], $creditSource, $summary);
            }
        } else {
            if ($mainDebitAccount !== null && $amounts['total'] > 0) {
                $recommendations[] = $this->line('DEBIT', $mainDebitAccount, $amounts['total'], $debitSource, $summary);
            }
            if ($mainCreditAccount !== null && $amounts['supply'] > 0) {
                $recommendations[] = $this->line('CREDIT', $mainCreditAccount, $amounts['supply'], $creditSource, $summary);
            }
            $vatAccount = $this->vatAccount($context['transaction_direction'], $rule);
            if ($vatAccount !== null && $amounts['vat'] > 0) {
                $recommendations[] = $this->line('CREDIT', $vatAccount, $amounts['vat'], [
                    'source' => !empty($rule['vat_account_id']) ? 'JOURNAL_RULE' : 'VAT_RULE',
                    'confidence' => 100,
                    'reason' => !empty($rule['vat_account_id']) ? '분개규칙 부가세계정' : '거래라인 부가세',
                    'rule_id' => (string) ($rule['id'] ?? ''),
                ], $summary);
            }
        }

        $recommendationSet = [
            'set_id' => 'SET-1',
            'set_name' => '학습 기반 추천 분개세트',
            'line_count' => count($recommendations),
            'balanced' => $this->isBalanced($recommendations),
        ];
        foreach ($recommendations as $index => $line) {
            $recommendations[$index]['set_id'] = $recommendationSet['set_id'];
        }

        return [
            'transaction_id' => '',
            'context' => $context,
            'journal_rule_id' => $rule['id'] ?? null,
            'recommendation_sets' => [$recommendationSet],
            'recommendations' => $recommendations,
            'balanced' => $recommendationSet['balanced'],
        ];
    }

    private function transactionContext(array $transaction): array
    {
        $transactionId = (string) ($transaction['id'] ?? '');
        $direction = strtoupper(trim((string) ($transaction['transaction_direction'] ?? '')));
        $importType = strtoupper(trim((string) ($transaction['import_type'] ?? '')));
        if ($direction === '' || $importType === '') {
            $seed = $this->seedContext($transactionId);
            $direction = $direction !== '' ? $direction : (string) ($seed['transaction_direction'] ?? '');
            $importType = $importType !== '' ? $importType : (string) ($seed['source_type'] ?? '');
        }

        if ($direction === '') {
            $direction = 'GENERAL';
        }

        return [
            'business_unit' => strtoupper(trim((string) ($transaction['business_unit'] ?? 'HQ'))) ?: 'HQ',
            'transaction_direction' => $this->normalizeDirection($direction),
            'import_type' => $importType !== '' ? $importType : 'ETC',
            'client_type' => $this->clientType((string) ($transaction['client_id'] ?? '')),
        ];
    }

    private function seedContext(string $transactionId): array
    {
        if ($transactionId === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT e.source_type, p.mapped_payload_json AS parsed_json
            FROM ledger_data_evidences e
            JOIN ledger_evidence_payloads p
              ON p.evidence_type = e.source_type COLLATE utf8mb4_unicode_ci
             AND p.evidence_id = e.id COLLATE utf8mb4_unicode_ci
            WHERE e.transaction_id = :transaction_id
              AND e.deleted_at IS NULL
            ORDER BY e.latest_imported_at DESC, e.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':transaction_id' => $transactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $parsed = json_decode((string) ($row['parsed_json'] ?? ''), true);

        return [
            'source_type' => strtoupper(trim((string) ($row['source_type'] ?? ''))),
            'transaction_direction' => strtoupper(trim((string) ($parsed['transaction_direction'] ?? ''))),
        ];
    }

    private function findJournalRule(array $context): ?array
    {
        $ruleRankSql = $this->columnExists('ledger_journal_rules', 'confidence_score') && $this->columnExists('ledger_journal_rules', 'usage_count')
            ? "r.confidence_score DESC, r.usage_count DESC,"
            : "";

        $stmt = $this->pdo->prepare("
            SELECT r.*
            FROM ledger_journal_rules r
            WHERE r.deleted_at IS NULL
              AND r.is_active = 1
              AND r.business_unit = :business_unit
              AND r.transaction_direction = :transaction_direction
              AND r.import_type = :import_type
              AND (r.client_type = :client_type_filter OR r.client_type IS NULL OR r.client_type = '')
            ORDER BY
              CASE WHEN r.client_type = :client_type_order THEN 0 ELSE 1 END,
              {$ruleRankSql}
              r.sort_no ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':business_unit' => $context['business_unit'],
            ':transaction_direction' => $context['transaction_direction'],
            ':import_type' => $context['import_type'],
            ':client_type_filter' => $context['client_type'],
            ':client_type_order' => $context['client_type'],
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function clientDefaultAccount(string $clientId): ?array
    {
        if ($clientId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT a.*
            FROM system_clients c
            INNER JOIN ledger_accounts a
                ON a.id = c.default_account_id
               AND a.deleted_at IS NULL
            WHERE c.id = :client_id
              AND c.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':client_id' => $clientId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function recentPatternAccount(array $transaction, array $context, string $side): ?array
    {
        $clientId = trim((string) ($transaction['client_id'] ?? ''));
        if ($clientId === '') {
            return null;
        }

        if ($this->tableExists('ledger_client_account_patterns')) {
            $stmt = $this->pdo->prepare("
                SELECT a.*, p.usage_count AS pattern_usage_count
                FROM ledger_client_account_patterns p
                INNER JOIN ledger_accounts a
                    ON a.id = p.account_id
                   AND a.deleted_at IS NULL
                WHERE p.client_id = :client_id
                  AND p.transaction_direction = :transaction_direction
                  AND p.line_type = :line_type
                ORDER BY p.usage_count DESC, p.last_used_at DESC
                LIMIT 1
            ");
            $stmt->execute([
                ':client_id' => $clientId,
                ':transaction_direction' => $context['transaction_direction'],
                ':line_type' => $side,
            ]);
            $pattern = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($pattern !== null) {
                return $pattern;
            }
        }

        $amountColumn = $side === 'DEBIT' ? 'vl.debit' : 'vl.credit';
        $stmt = $this->pdo->prepare("
            SELECT a.*, COUNT(*) AS use_count
            FROM ledger_transaction_links tl
            INNER JOIN ledger_transactions t
                ON t.id = tl.transaction_id
               AND t.deleted_at IS NULL
            INNER JOIN ledger_voucher_lines vl
                ON vl.voucher_id = tl.voucher_id
            INNER JOIN ledger_accounts a
                ON a.id = vl.account_id
               AND a.deleted_at IS NULL
            WHERE tl.deleted_at IS NULL
              AND tl.is_active = 1
              AND t.client_id = :client_id
              AND COALESCE(t.transaction_direction, '') = :transaction_direction
              AND {$amountColumn} > 0
              AND (vl.recommend_source IS NULL OR vl.recommend_source <> 'VAT_RULE')
            GROUP BY a.id
            ORDER BY use_count DESC, MAX(vl.created_at) DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':client_id' => $clientId,
            ':transaction_direction' => $context['transaction_direction'],
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function recentPatternPair(array $transaction, array $context): array
    {
        if (!$this->tableExists('ledger_recent_journal_patterns')) {
            return [];
        }

        $clientId = trim((string) ($transaction['client_id'] ?? ''));
        $clientFilter = $clientId !== '' ? 'p.client_id = :client_id' : 'p.client_id IS NULL';
        $stmt = $this->pdo->prepare("
            SELECT
                da.id AS debit_id,
                da.account_code AS debit_code,
                da.account_name AS debit_name,
                da.account_group AS debit_group,
                ca.id AS credit_id,
                ca.account_code AS credit_code,
                ca.account_name AS credit_name,
                ca.account_group AS credit_group
            FROM ledger_recent_journal_patterns p
            INNER JOIN ledger_accounts da
                ON BINARY da.id = BINARY p.debit_account_id
               AND da.deleted_at IS NULL
            INNER JOIN ledger_accounts ca
                ON BINARY ca.id = BINARY p.credit_account_id
               AND ca.deleted_at IS NULL
            WHERE {$clientFilter}
              AND BINARY p.transaction_direction = BINARY :transaction_direction
            ORDER BY p.usage_count DESC, p.last_used_at DESC
            LIMIT 1
        ");
        $params = [':transaction_direction' => $context['transaction_direction']];
        if ($clientId !== '') {
            $params[':client_id'] = $clientId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row === null) {
            return [];
        }

        return [
            'debit_account' => [
                'id' => $row['debit_id'],
                'account_code' => $row['debit_code'],
                'account_name' => $row['debit_name'],
                'account_group' => $row['debit_group'],
            ],
            'credit_account' => [
                'id' => $row['credit_id'],
                'account_code' => $row['credit_code'],
                'account_name' => $row['credit_name'],
                'account_group' => $row['credit_group'],
            ],
        ];
    }

    private function vatAccount(string $direction, ?array $rule): ?array
    {
        if (!empty($rule['vat_account_id'])) {
            $account = $this->accountById((string) $rule['vat_account_id']);
            if ($account !== null) {
                return $account;
            }
        }

        $code = in_array($direction, ['PURCHASE', 'OUT'], true) ? '113600' : '215900';
        return $this->accountByCode($code);
    }

    private function fallbackAccount(string $direction, string $side): ?array
    {
        $codes = match ($direction . ':' . $side) {
            'SALES:DEBIT' => ['111400', '111200'],
            'SALES:CREDIT' => ['412100', '411100'],
            'PURCHASE:DEBIT' => ['511100', '510100', '112100'],
            'PURCHASE:CREDIT' => ['211100', '213400'],
            'IN:DEBIT' => ['111200', '111100'],
            'IN:CREDIT' => ['412100', '411100'],
            'OUT:DEBIT' => ['511100', '510100', '213400'],
            'OUT:CREDIT' => ['111200', '111100'],
            default => $side === 'DEBIT' ? ['111200'] : ['213400'],
        };

        foreach ($codes as $code) {
            $account = $this->accountByCode($code);
            if ($account !== null) {
                return $account;
            }
        }

        return $this->firstPostableAccountByBalance($side === 'DEBIT' ? 'debit' : 'credit');
    }

    private function firstPostableAccountByBalance(string $normalBalance): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM ledger_accounts
            WHERE deleted_at IS NULL
              AND is_posting = 1
              AND normal_balance = :normal_balance
            ORDER BY account_code ASC
            LIMIT 1
        ");
        $stmt->execute([':normal_balance' => $normalBalance]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function accountById(string $accountId): ?array
    {
        if ($accountId === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM ledger_accounts WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $accountId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function accountByCode(string $accountCode): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ledger_accounts WHERE account_code = :code AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':code' => $accountCode]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
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

    private function clientIdFromPayload(array $payload): string
    {
        $clientId = trim((string) ($payload['client_id'] ?? ''));
        if ($clientId !== '') {
            return $clientId;
        }

        $name = trim((string) (
            $payload['client_name']
            ?? $payload['client_company_name']
            ?? $payload['counterparty_name']
            ?? $payload['counterparty_account_holder_name']
            ?? $payload['counterparty_account_holder']
            ?? ''
        ));
        if ($name === '') {
            return '';
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM system_clients
            WHERE deleted_at IS NULL
              AND (
                  client_name = :name_exact_client
                  OR company_name = :name_exact_company
                  OR :name_like_client LIKE CONCAT('%', client_name, '%')
                  OR :name_like_company LIKE CONCAT('%', company_name, '%')
              )
            ORDER BY updated_at DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':name_exact_client' => $name,
            ':name_exact_company' => $name,
            ':name_like_client' => $name,
            ':name_like_company' => $name,
        ]);

        return (string) ($stmt->fetchColumn() ?: '');
    }

    private function amountOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        $normalized = preg_replace('/[,\s]/', '', trim((string) $value));
        if ($normalized === '' || $normalized === '-') {
            return null;
        }
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function transactionAmounts(array $transaction, array $items): array
    {
        $supply = 0.0;
        $vat = 0.0;
        $total = 0.0;
        foreach ($items as $item) {
            $supply += (float) ($item['supply_amount'] ?? 0);
            $vat += (float) ($item['vat_amount'] ?? 0);
            $total += (float) ($item['total_amount'] ?? 0);
        }
        if (abs($total) <= 0) {
            $supply = (float) ($transaction['supply_amount'] ?? 0);
            $vat = (float) ($transaction['vat_amount'] ?? 0);
            $total = (float) ($transaction['total_amount'] ?? ($supply + $vat));
        }

        return [
            'supply' => abs(round($supply, 2)),
            'vat' => abs(round($vat, 2)),
            'total' => abs(round($total ?: ($supply + $vat), 2)),
        ];
    }

    private function sideForClientDefault(string $direction, array $account): string
    {
        $group = strtoupper((string) ($account['account_group'] ?? ''));
        $category = strtoupper((string) ($account['account_category'] ?? ''));
        $name = (string) ($account['account_name'] ?? '');

        if ($direction === 'SALES') {
            return str_contains($group . $category, 'REVENUE') || str_contains($name, '매출') ? 'CREDIT' : 'DEBIT';
        }
        if ($direction === 'IN') {
            return str_contains($group . $category, 'ASSET') || str_contains($name, '예금') || str_contains($name, '현금') ? 'DEBIT' : 'CREDIT';
        }
        if ($direction === 'OUT') {
            return str_contains($group . $category, 'ASSET') || str_contains($name, '예금') || str_contains($name, '현금') ? 'CREDIT' : 'DEBIT';
        }

        return str_contains($group . $category, 'LIABILITY') ? 'CREDIT' : 'DEBIT';
    }

    private function normalizeDirection(string $direction): string
    {
        $direction = strtoupper(trim($direction));
        return match ($direction) {
            'SALES', 'SALE', 'SELL', 'OUT_SALE' => 'SALES',
            'PURCHASE', 'BUY', 'IN_PURCHASE' => 'PURCHASE',
            'IN', 'DEPOSIT', 'RECEIPT', 'BANK_IN' => 'IN',
            'OUT', 'WITHDRAWAL', 'PAYMENT', 'BANK_OUT' => 'OUT',
            default => $direction !== '' ? $direction : 'GENERAL',
        };
    }

    private function line(string $type, array $account, float $amount, ?array $source, string $summary): array
    {
        return [
            'line_type' => $type,
            'account_id' => (string) $account['id'],
            'account_code' => (string) ($account['account_code'] ?? ''),
            'account_name' => (string) ($account['account_name'] ?? ''),
            'amount' => number_format($amount, 2, '.', ''),
            'source' => (string) ($source['source'] ?? 'UNKNOWN'),
            'confidence' => (int) ($source['confidence'] ?? 0),
            'journal_rule_id' => trim((string) ($source['rule_id'] ?? '')) ?: null,
            'reason' => (string) ($source['reason'] ?? ''),
            'line_summary' => $summary !== '' ? $summary : null,
        ];
    }

    private function isBalanced(array $recommendations): bool
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($recommendations as $line) {
            if (($line['line_type'] ?? '') === 'DEBIT') {
                $debit += (float) ($line['amount'] ?? 0);
            } elseif (($line['line_type'] ?? '') === 'CREDIT') {
                $credit += (float) ($line['amount'] ?? 0);
            }
        }

        return $debit > 0 && round($debit, 2) === round($credit, 2);
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
