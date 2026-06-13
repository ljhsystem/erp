<?php

namespace App\Services\Ledger;

class VoucherLearningService
{
    public function __construct(
        private JournalLearningService $journalLearningService,
        private VoucherPolicyService $voucherPolicyService,
        private EvidenceBusinessRefService $evidenceBusinessRefService,
        private array $callbacks = []
    ) {
    }

    public function recordBankVoucherLearning(string $transactionId, string $voucherId, array $evidence, array $lines, string $actor): void
    {
        if ($transactionId === '' || $voucherId === '' || $lines === []) {
            return;
        }

        try {
            [$direction] = $this->call('bankVoucherPaymentDirectionAndAmount', $evidence);
            $context = [
                'id' => $transactionId,
                'client_id' => $this->evidenceBusinessRefService->businessRefIdForStorage('CLIENT', $evidence) ?? '',
                'project_id' => $this->evidenceBusinessRefService->businessRefIdForStorage('PROJECT', $evidence) ?? '',
                'business_unit' => $this->call('businessUnitForUpload', $evidence, 'BANK_TRANSACTION'),
                'transaction_type' => strtoupper(trim((string) ($evidence['transaction_type'] ?? 'GENERAL'))) ?: 'GENERAL',
                'transaction_direction' => $direction ?: $this->call('transactionDirectionForStorage', (string) ($evidence['transaction_direction'] ?? ''), $evidence, 'BANK_TRANSACTION'),
                'import_type' => 'BANK_TRANSACTION',
            ];

            $learningLines = $this->bankVoucherLearningLines($lines, $evidence);
            if ($learningLines === []) {
                return;
            }

            $this->journalLearningService->recordVoucherDraft($context, $voucherId, $learningLines, $actor);
        } catch (\Throwable $e) {
            error_log('[VoucherLearningService] bank voucher learning skipped: ' . $e->getMessage());
        }
    }

    public function bankVoucherLearningLines(array $lines, array $evidence): array
    {
        $learningLines = [];
        $description = trim((string) ($evidence['description'] ?? $evidence['summary_text'] ?? ''));
        $sourceType = $this->call('normalizeDataType', (string) ($evidence['source_type'] ?? $evidence['import_type'] ?? 'BANK_TRANSACTION'));

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $amount = $debit > 0 ? $debit : $credit;
            if ($amount <= 0) {
                continue;
            }

            $finalAccountId = $this->voucherPolicyService->resolveLedgerAccountId((string) ($line['account_id'] ?? '')) ?? (string) ($line['account_id'] ?? '');
            $recommendedAccountId = $this->voucherPolicyService->resolveLedgerAccountId((string) ($line['recommended_account_id'] ?? '')) ?? $finalAccountId;
            $finalRefs = is_array($line['refs'] ?? null) ? $line['refs'] : [];
            $recommendedRefs = is_array($line['recommended_refs'] ?? null) ? $line['recommended_refs'] : [];
            $refsChanged = json_encode($this->normalizedRefPayload($recommendedRefs)) !== json_encode($this->normalizedRefPayload($finalRefs));

            $learningLines[] = [
                'line_type' => $debit > 0 ? 'DEBIT' : 'CREDIT',
                'account_id' => $finalAccountId,
                'amount' => $amount,
                'recommended_line_type' => $debit > 0 ? 'DEBIT' : 'CREDIT',
                'recommended_account_id' => $recommendedAccountId,
                'recommended_amount' => $amount,
                'source' => $line['recommend_source'] ?? null,
                'confidence' => is_numeric($line['recommend_confidence'] ?? null) ? (int) $line['recommend_confidence'] : null,
                'journal_rule_id' => $line['journal_rule_id'] ?? null,
                'reason' => $line['recommend_reason'] ?? null,
                'project_id' => $this->evidenceBusinessRefService->businessRefIdForStorage('PROJECT', $evidence) ?? null,
                'recommended_refs' => $recommendedRefs,
                'final_refs' => $finalRefs,
                'source_type' => $sourceType,
                'description' => $description,
                'amount_bucket' => $this->amountBucket($amount),
                'is_user_modified' => !empty($line['is_user_modified']) || $refsChanged ? 1 : 0,
            ];
        }

        return $learningLines;
    }

    public function normalizedRefPayload(array $refs): array
    {
        $normalized = [];
        foreach ($refs as $ref) {
            if (!is_array($ref)) {
                continue;
            }

            $type = $this->voucherPolicyService->normalizeVoucherRefType((string) ($ref['ref_type'] ?? $ref['line_ref_type'] ?? ''));
            $id = trim((string) ($ref['ref_id'] ?? $ref['line_ref_id'] ?? ''));
            if ($type === '' || $id === '') {
                continue;
            }

            $normalized[] = $type . ':' . $id;
        }
        sort($normalized, SORT_STRING);

        return $normalized;
    }

    public function amountBucket(float $amount): string
    {
        $amount = abs($amount);

        return match (true) {
            $amount < 10000 => 'LT_10K',
            $amount < 100000 => '10K_100K',
            $amount < 1000000 => '100K_1M',
            $amount < 10000000 => '1M_10M',
            $amount < 100000000 => '10M_100M',
            default => 'GE_100M',
        };
    }

    private function call(string $name, mixed ...$args): mixed
    {
        if (!isset($this->callbacks[$name])) {
            throw new \RuntimeException('Missing callback: ' . $name);
        }

        return ($this->callbacks[$name])(...$args);
    }
}
