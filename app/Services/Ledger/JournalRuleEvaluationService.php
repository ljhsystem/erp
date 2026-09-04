<?php

namespace App\Services\Ledger;

use PDO;

class JournalRuleEvaluationService
{
    private JournalRecommendationGuardService $guard;

    public function __construct(private readonly PDO $pdo)
    {
        $this->guard = new JournalRecommendationGuardService($pdo);
    }

    public function evaluate(array $context): array
    {
        $this->guard->assertRecommendationAllowed([[
            'company_id' => $context['company_id'] ?? '',
            'operation_type' => $context['operation_type'] ?? '',
            'import_type' => $context['import_type'] ?? '',
        ]], $context);
        $normalized = $this->normalizedConditions($context);
        $conditionHash = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $generic = $normalized;
        $generic['item_code'] = '';
        $genericConditionHash = hash('sha256', json_encode($generic, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $rules = $this->activeRules((string) $context['company_id'], array_values(array_unique([$conditionHash, $genericConditionHash])), (string) ($context['base_date'] ?? date('Y-m-d')));
        $byRole = [];
        foreach ($rules as $rule) {
            $key = $rule['accounting_role_code'] . '|' . $rule['debit_credit'];
            $byRole[$key][] = $rule;
        }
        $resolved = [];
        $conflicts = [];
        foreach ($byRole as $key => $candidates) {
            $accountIds = array_values(array_unique(array_column($candidates, 'account_id')));
            if (count($accountIds) === 1) {
                $resolved[$key] = $candidates[0];
            } else {
                $conflicts[$key] = $candidates;
            }
        }
        return ['condition_hash' => $conditionHash, 'resolved' => $resolved, 'conflicts' => $conflicts, 'auto_applicable' => $conflicts === [] && $resolved !== []];
    }

    private function activeRules(string $companyId, array $conditionHashes, string $baseDate): array
    {
        $stmt = $this->pdo->prepare("SELECT r.* FROM ledger_journal_rules r INNER JOIN ledger_accounts a ON a.id=r.account_id
            WHERE r.company_id=:company_id AND r.condition_hash IN (:condition_hash,:generic_condition_hash) AND r.rule_status='ACTIVE' AND r.deleted_at IS NULL
              AND r.is_active=1 AND a.deleted_at IS NULL AND a.is_active=1 AND COALESCE(a.is_posting,1)=1
              AND (r.effective_from IS NULL OR r.effective_from<=:effective_from_date)
              AND (r.effective_to IS NULL OR r.effective_to>=:effective_to_date)
            ORDER BY CASE r.origin_code WHEN 'USER' THEN 0 ELSE 1 END,r.priority_no,r.sort_no,r.id");
        $stmt->execute([':company_id' => $companyId, ':condition_hash' => $conditionHashes[0], ':generic_condition_hash' => $conditionHashes[1] ?? $conditionHashes[0], ':effective_from_date' => $baseDate, ':effective_to_date' => $baseDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizedConditions(array $context): array
    {
        $fields = ['company_id','business_unit','operation_type','transaction_direction','import_type','client_type','source_type','source_line_type','item_code'];
        $normalized = [];
        foreach ($fields as $field) {
            $value = trim((string) ($context[$field] ?? ''));
            $normalized[$field] = $field === 'company_id' ? $value : strtoupper($value);
        }
        ksort($normalized);
        return $normalized;
    }
}
