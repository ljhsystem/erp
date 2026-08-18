<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\JournalCandidateRepository;

class JournalClientPatternCandidateProvider implements JournalCandidateProviderInterface
{
    public function __construct(private readonly JournalCandidateRepository $repository)
    {
    }

    public function provide(array $context): array
    {
        $bySide = ['DEBIT' => [], 'CREDIT' => []];
        foreach ($this->repository->clientPatterns($context) as $row) {
            $side = strtoupper(trim((string) ($row['line_type'] ?? '')));
            if (isset($bySide[$side])) {
                $bySide[$side][] = $row;
            }
        }
        $candidates = [];
        foreach (array_slice($bySide['DEBIT'], 0, 3) as $debit) {
            foreach (array_slice($bySide['CREDIT'], 0, 3) as $credit) {
                $candidates[] = [
                    'accounts' => ['debit' => (string) $debit['account_id'], 'credit' => (string) $credit['account_id'], 'vat' => ''],
                    'source' => 'CLIENT_PATTERN',
                    'source_id' => (string) $debit['id'] . ':' . (string) $credit['id'],
                    'metrics' => [
                        'usage_count' => min((int) $debit['usage_count'], (int) $credit['usage_count']),
                        'recent_score' => min((float) $debit['recent_score'], (float) $credit['recent_score']),
                        'last_used_at' => max((string) ($debit['last_used_at'] ?? ''), (string) ($credit['last_used_at'] ?? '')),
                    ],
                ];
            }
        }
        $defaultAccount = $this->repository->clientDefaultAccount($context);
        $defaultAccountId = trim((string) ($defaultAccount['default_account_id'] ?? ''));
        if ($defaultAccountId !== '') {
            $out = ($context['transaction_direction'] ?? '') === 'OUT';
            foreach (array_slice($this->repository->recentPatterns($context), 0, 3) as $recent) {
                $debit = $out ? $defaultAccountId : trim((string) ($recent['debit_account_id'] ?? ''));
                $credit = $out ? trim((string) ($recent['credit_account_id'] ?? '')) : $defaultAccountId;
                if ($debit === '' || $credit === '') continue;
                $candidates[] = [
                    'accounts' => ['debit' => $debit, 'credit' => $credit, 'vat' => (string) ($recent['vat_account_id'] ?? '')],
                    'source' => 'CLIENT_DEFAULT_ACCOUNT',
                    'source_id' => (string) ($defaultAccount['client_id'] ?? ''),
                    'metrics' => [
                        'usage_count' => (int) ($recent['usage_count'] ?? 0),
                        'last_used_at' => $recent['last_used_at'] ?? null,
                    ],
                ];
            }
        }
        return $candidates;
    }
}
