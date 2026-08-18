<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\JournalCandidateRepository;

class JournalLearningCandidateProvider implements JournalCandidateProviderInterface
{
    public function __construct(private readonly JournalCandidateRepository $repository)
    {
    }

    public function provide(array $context): array
    {
        return array_map(static fn(array $row): array => [
            'accounts' => [
                'debit' => (string) ($row['debit_account_id'] ?? ''),
                'credit' => (string) ($row['credit_account_id'] ?? ''),
                'vat' => (string) ($row['vat_account_id'] ?? ''),
            ],
            'source' => 'LEARNING_EVENT',
            'source_id' => (string) ($row['voucher_id'] ?? ''),
            'metrics' => [
                'event_count' => (int) ($row['event_count'] ?? 0),
                'accepted_count' => (int) ($row['accepted_count'] ?? 0),
                'modified_count' => (int) ($row['modified_count'] ?? 0),
                'last_used_at' => $row['last_used_at'] ?? null,
                'journal_rule_id' => $row['journal_rule_id'] ?? null,
            ],
        ], $this->repository->learningPatterns($context));
    }
}
