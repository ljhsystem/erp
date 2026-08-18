<?php

namespace App\Repositories\Ledger;

use Core\Helpers\UuidHelper;
use PDO;
use PDOException;

class JournalLearningFeedbackRepository
{
    public const EVENT_TYPE = 'POSTED_CONFIRMATION';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insertEvent(array $event): array
    {
        $event['id'] = UuidHelper::generate();
        $columns = array_keys($event);
        $sql = sprintf(
            'INSERT INTO ledger_journal_learning_events (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns))
        );
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(array_combine(array_map(static fn(string $column): string => ':' . $column, $columns), array_values($event)));
            return ['created' => true, 'id' => $event['id']];
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $existing = $this->pdo->prepare('SELECT id FROM ledger_journal_learning_events WHERE voucher_line_id = :line_id AND event_type = :event_type LIMIT 1');
            $existing->execute([':line_id' => $event['voucher_line_id'], ':event_type' => $event['event_type']]);
            $id = trim((string) ($existing->fetchColumn() ?: ''));
            if ($id === '') {
                throw $exception;
            }
            return ['created' => false, 'id' => $id];
        }
    }

    public function feedbackEvents(): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM ledger_journal_learning_events WHERE event_type = :event_type AND failure_type IS NULL ORDER BY created_at, id");
        $statement->execute([':event_type' => self::EVENT_TYPE]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertRecent(array $pattern): void
    {
        $statement = $this->pdo->prepare("INSERT INTO ledger_journal_recent_patterns
            (id, pattern_hash, client_id, transaction_direction, debit_account_id, credit_account_id, vat_account_id,
             project_id, usage_count, legacy_usage_count, last_used_at, created_at, updated_at)
            VALUES (:id, :pattern_hash, :client_id, :direction, :debit, :credit, :vat, :project_id,
                    :usage_count, 0, :last_used_at, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                client_id = VALUES(client_id), transaction_direction = VALUES(transaction_direction),
                debit_account_id = VALUES(debit_account_id), credit_account_id = VALUES(credit_account_id),
                vat_account_id = VALUES(vat_account_id), project_id = VALUES(project_id),
                usage_count = legacy_usage_count + VALUES(usage_count), last_used_at = VALUES(last_used_at), updated_at = NOW()");
        $statement->execute([
            ':id' => UuidHelper::generate(), ':pattern_hash' => $pattern['pattern_hash'], ':client_id' => $pattern['client_id'],
            ':direction' => $pattern['transaction_direction'], ':debit' => $pattern['debit_account_id'],
            ':credit' => $pattern['credit_account_id'], ':vat' => $pattern['vat_account_id'], ':project_id' => $pattern['project_id'],
            ':usage_count' => $pattern['usage_count'], ':last_used_at' => $pattern['last_used_at'],
        ]);
    }

    public function upsertClient(array $pattern): void
    {
        $statement = $this->pdo->prepare("INSERT INTO ledger_journal_client_account_patterns
            (id, client_id, transaction_direction, line_type, account_id, usage_count, legacy_usage_count,
             recent_score, legacy_recent_score, last_used_at, created_at, updated_at)
            VALUES (:id, :client_id, :direction, :line_type, :account_id, :usage_count, 0,
                    :recent_score, 0, :last_used_at, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                usage_count = legacy_usage_count + VALUES(usage_count),
                recent_score = legacy_recent_score + VALUES(recent_score),
                last_used_at = VALUES(last_used_at), updated_at = NOW()");
        $statement->execute([
            ':id' => UuidHelper::generate(), ':client_id' => $pattern['client_id'], ':direction' => $pattern['transaction_direction'],
            ':line_type' => $pattern['line_type'], ':account_id' => $pattern['account_id'],
            ':usage_count' => $pattern['usage_count'], ':recent_score' => $pattern['usage_count'], ':last_used_at' => $pattern['last_used_at'],
        ]);
    }
}
