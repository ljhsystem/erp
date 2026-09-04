<?php

namespace App\Services\Ledger;

use App\Repositories\Ledger\JournalCandidateRepository;
use PDO;

class JournalCandidateEngineService
{
    /** @var JournalCandidateProviderInterface[] */
    private array $providers;
    private JournalCandidateRepository $repository;

    public function __construct(PDO $pdo, ?array $providers = null)
    {
        $repository = new JournalCandidateRepository($pdo);
        $this->repository = $repository;
        $this->providers = $providers ?? [
            new JournalRuleCandidateProvider($repository),
            new JournalClientPatternCandidateProvider($repository),
            new JournalLearningCandidateProvider($repository),
        ];
    }

    public function topCandidates(array $input, int $limit = 3): array
    {
        $context = $this->normalizeContext($input);
        $merged = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->provide($context) as $candidate) {
                $accounts = $candidate['accounts'] ?? [];
                $debit = trim((string) ($accounts['debit'] ?? ''));
                $credit = trim((string) ($accounts['credit'] ?? ''));
                if ($debit === '' || $credit === '') {
                    continue;
                }
                $key = sha1($debit . '|' . $credit . '|' . trim((string) ($accounts['vat'] ?? '')));
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'candidate_id' => $key,
                        'accounts' => ['debit' => $debit, 'credit' => $credit, 'vat' => trim((string) ($accounts['vat'] ?? ''))],
                        'signals' => [],
                        'rule_bindings' => [],
                    ];
                }
                if (($candidate['source'] ?? '') === 'JOURNAL_RULE' && is_array($candidate['rule_bindings'] ?? null)) {
                    $merged[$key]['rule_bindings'] = $candidate['rule_bindings'];
                }
                $merged[$key]['signals'][] = [
                    'source' => (string) ($candidate['source'] ?? ''),
                    'source_id' => (string) ($candidate['source_id'] ?? ''),
                    'metrics' => is_array($candidate['metrics'] ?? null) ? $candidate['metrics'] : [],
                ];
            }
        }

        $accountIds = [];
        foreach ($merged as $candidate) {
            $accountIds = array_merge($accountIds, array_values($candidate['accounts']));
        }
        $usableAccountIds = array_flip($this->repository->usableAccountIds($accountIds));

        $ranked = [];
        foreach ($merged as $candidate) {
            $hasOfficialRule = in_array('JOURNAL_RULE', array_column($candidate['signals'], 'source'), true);
            if (!$hasOfficialRule) {
                continue;
            }
            $candidateAccountIds = array_values(array_filter($candidate['accounts']));
            if (array_diff($candidateAccountIds, array_keys($usableAccountIds)) !== []) {
                continue;
            }
            [$score, $components, $reasons] = $this->score($candidate['signals']);
            if ((float) $context['vat_amount'] > 0 && $candidate['accounts']['vat'] !== '') {
                $score += 20.0;
                $components['VAT_ACCOUNT'] = 20.0;
                $reasons[] = '증빙의 부가세 금액과 VAT 계정 구성이 일치합니다.';
            }
            $candidate['score'] = round(min(100, max(0, $score)), 2);
            $candidate['score_components'] = $components;
            $candidate['reasons'] = $reasons;
            $candidate['source_types'] = array_values(array_unique(array_column($candidate['signals'], 'source')));
            $candidate['lines'] = $this->lines($candidate['accounts'], $context, $candidate['rule_bindings'] ?? []);
            $candidate['balanced'] = $this->balanced($candidate['lines']);
            if ($candidate['balanced']) {
                $ranked[] = $candidate;
            }
        }
        usort($ranked, static fn(array $a, array $b): int => ($b['score'] <=> $a['score'])
            ?: strcmp((string) $a['candidate_id'], (string) $b['candidate_id']));
        $ranked = array_slice($ranked, 0, max(1, min($limit, 10)));
        foreach ($ranked as $index => &$candidate) {
            $candidate['rank'] = $index + 1;
            $candidate['confidence'] = $candidate['score'] >= 85 ? 'HIGH' : ($candidate['score'] >= 70 ? 'MEDIUM' : 'LOW');
        }
        unset($candidate);

        return [
            'context' => $context,
            'candidates' => $ranked,
            'candidate_count' => count($ranked),
            'engine_version' => 'journal-candidate-v1',
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function score(array $signals): array
    {
        $score = 0.0;
        $components = [];
        $reasons = [];
        foreach ($signals as $signal) {
            $source = (string) ($signal['source'] ?? '');
            $metrics = $signal['metrics'] ?? [];
            $value = match ($source) {
                'JOURNAL_RULE' => 25.0 + min(8, log(1 + (int) ($metrics['usage_count'] ?? 0)) * 2)
                    + min(7, (float) ($metrics['confidence'] ?? 0) * 0.07),
                'CLIENT_PATTERN' => 18.0 + min(8, log(1 + (int) ($metrics['usage_count'] ?? 0)) * 2)
                    + min(4, (float) ($metrics['recent_score'] ?? 0)),
                'CLIENT_DEFAULT_ACCOUNT' => 22.0 + min(6, log(1 + (int) ($metrics['usage_count'] ?? 0)) * 2),
                'RECENT_PATTERN' => 14.0 + min(7, log(1 + (int) ($metrics['usage_count'] ?? 0)) * 2)
                    + (!empty($metrics['client_exact']) ? 4 : 0) + (!empty($metrics['project_exact']) ? 3 : 0),
                'LEARNING_EVENT' => 12.0 + min(8, (int) ($metrics['accepted_count'] ?? 0) * 1.5)
                    - min(10, (int) ($metrics['modified_count'] ?? 0) * 2),
                default => 0.0,
            };
            $value += $this->recency((string) ($metrics['last_used_at'] ?? ''));
            $components[$source] = round(($components[$source] ?? 0) + $value, 2);
            $score += $value;
            if ($value > 0) {
                $reasons[] = $this->reason($source, $metrics);
            }
        }
        return [$score, $components, array_values(array_unique(array_filter($reasons)))];
    }

    private function recency(string $lastUsedAt): float
    {
        $timestamp = strtotime($lastUsedAt);
        if ($timestamp === false) {
            return 0.0;
        }
        $days = max(0, (time() - $timestamp) / 86400);
        return 5.0 * exp(-$days / 90);
    }

    private function reason(string $source, array $metrics): string
    {
        return match ($source) {
            'JOURNAL_RULE' => '현재 업무조건과 일치하는 분개규칙입니다.',
            'CLIENT_PATTERN' => '이 거래처에서 반복 사용한 계정 조합입니다.',
            'CLIENT_DEFAULT_ACCOUNT' => '거래처 기준계정과 최근 상대계정 사용 이력을 조합했습니다.',
            'RECENT_PATTERN' => '최근 확정 분개에서 사용한 계정 조합입니다.',
            'LEARNING_EVENT' => (int) ($metrics['modified_count'] ?? 0) > 0
                ? '사용자 수정 이력을 반영한 확정 계정 조합입니다.'
                : '추천 후 수정 없이 확정된 계정 조합입니다.',
            default => '',
        };
    }

    private function lines(array $accounts, array $context, array $ruleBindings = []): array
    {
        $total = abs((float) $context['total_amount']);
        $vat = $accounts['vat'] !== '' ? min($total, abs((float) $context['vat_amount'])) : 0.0;
        $supply = max(0, $total - $vat);
        $out = in_array($context['transaction_direction'], ['OUT', 'PURCHASE'], true);
        $lines = [];
        if ($out) {
            $lines[] = $this->line('DEBIT', $accounts['debit'], $supply ?: $total, $context, $ruleBindings['DEBIT'] ?? []);
            if ($vat > 0 && $accounts['vat'] !== '') {
                $lines[] = $this->line('DEBIT', $accounts['vat'], $vat, $context, $ruleBindings['VAT'] ?? []);
            }
            $lines[] = $this->line('CREDIT', $accounts['credit'], $total, $context, $ruleBindings['CREDIT'] ?? []);
        } else {
            $lines[] = $this->line('DEBIT', $accounts['debit'], $total, $context, $ruleBindings['DEBIT'] ?? []);
            $lines[] = $this->line('CREDIT', $accounts['credit'], $supply ?: $total, $context, $ruleBindings['CREDIT'] ?? []);
            if ($vat > 0 && $accounts['vat'] !== '') {
                $lines[] = $this->line('CREDIT', $accounts['vat'], $vat, $context, $ruleBindings['VAT'] ?? []);
            }
        }
        return $lines;
    }

    private function line(string $side, string $accountId, float $amount, array $context, array $ruleBinding = []): array
    {
        return [
            'line_type' => $side,
            'account_id' => $accountId,
            'debit' => $side === 'DEBIT' ? round($amount, 2) : 0,
            'credit' => $side === 'CREDIT' ? round($amount, 2) : 0,
            'summary' => $context['summary'],
            'accounting_role_code' => (string) ($ruleBinding['accounting_role_code'] ?? ''),
            'journal_rule_id' => (string) ($ruleBinding['id'] ?? ''),
            'journal_rule_revision_no' => (int) ($ruleBinding['revision_no'] ?? 0),
        ];
    }

    private function balanced(array $lines): bool
    {
        $debit = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));
        return abs($debit - $credit) < 0.01 && $debit > 0;
    }

    private function normalizeContext(array $input): array
    {
        $direction = strtoupper(trim((string) ($input['transaction_direction'] ?? 'GENERAL'))) ?: 'GENERAL';
        $direction = match ($direction) {
            'EXPENSE', 'PURCHASE', 'WITHDRAW', 'WITHDRAWAL' => 'OUT',
            'INCOME', 'SALES', 'DEPOSIT' => 'IN',
            default => $direction,
        };
        return [
            'company_id' => trim((string) ($input['company_id'] ?? '')),
            'business_unit' => strtoupper(trim((string) ($input['business_unit'] ?? 'HQ'))) ?: 'HQ',
            'operation_type' => strtoupper(trim((string) ($input['operation_type'] ?? 'GENERAL'))) ?: 'GENERAL',
            'transaction_direction' => $direction,
            'import_type' => strtoupper(trim((string) ($input['import_type'] ?? $input['source_type'] ?? ''))),
            'source_type' => strtoupper(trim((string) ($input['source_type'] ?? ''))),
            'source_line_type' => strtoupper(trim((string) ($input['source_line_type'] ?? ''))),
            'item_code' => strtoupper(trim((string) ($input['item_code'] ?? ''))),
            'base_date' => trim((string) ($input['base_date'] ?? date('Y-m-d'))),
            'client_type' => strtoupper(trim((string) ($input['client_type'] ?? ''))),
            'client_id' => trim((string) ($input['client_id'] ?? '')),
            'project_id' => trim((string) ($input['project_id'] ?? '')),
            'total_amount' => (float) ($input['total_amount'] ?? $input['amount'] ?? 0),
            'vat_amount' => (float) ($input['vat_amount'] ?? 0),
            'summary' => trim((string) ($input['summary'] ?? $input['description'] ?? '')),
        ];
    }
}
