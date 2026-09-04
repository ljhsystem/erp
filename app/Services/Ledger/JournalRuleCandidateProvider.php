<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\JournalCandidateRepository;

class JournalRuleCandidateProvider implements JournalCandidateProviderInterface
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
            'source' => 'JOURNAL_RULE',
            'source_id' => (string) ($row['id'] ?? ''),
            'rule_bindings' => [
                'DEBIT' => [
                    'id' => (string) ($row['debit_rule_id'] ?? ''),
                    'revision_no' => (int) ($row['debit_rule_revision_no'] ?? 0),
                    'accounting_role_code' => 'EXPENSE',
                ],
                'CREDIT' => [
                    'id' => (string) ($row['credit_rule_id'] ?? ''),
                    'revision_no' => (int) ($row['credit_rule_revision_no'] ?? 0),
                    'accounting_role_code' => 'EMPLOYEE_ACCRUED_EXPENSE',
                ],
            ],
            'metrics' => [
                'usage_count' => (int) ($row['usage_count'] ?? 0),
                'confidence' => (float) ($row['confidence_score'] ?? 0),
                'last_used_at' => $row['last_used_at'] ?? null,
                'specific_client_type' => trim((string) ($row['client_type'] ?? '')) !== '',
            ],
        ], $this->repository->rules($context));
    }
}
