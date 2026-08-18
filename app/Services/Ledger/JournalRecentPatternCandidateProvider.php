<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\JournalCandidateRepository;

class JournalRecentPatternCandidateProvider implements JournalCandidateProviderInterface
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
            'source' => 'RECENT_PATTERN',
            'source_id' => (string) ($row['id'] ?? ''),
            'metrics' => [
                'usage_count' => (int) ($row['usage_count'] ?? 0),
                'last_used_at' => $row['last_used_at'] ?? null,
                'client_exact' => $context['client_id'] !== '' && $context['client_id'] === (string) ($row['client_id'] ?? ''),
                'project_exact' => $context['project_id'] !== '' && $context['project_id'] === (string) ($row['project_id'] ?? ''),
            ],
        ], $this->repository->recentPatterns($context));
    }
}
