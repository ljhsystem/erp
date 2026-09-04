<?php

namespace App\Services\Ledger;

use App\Services\System\SettingService;
use PDO;

class JournalLearningPolicyService
{
    public const BASELINE_KEY = 'journal_learning_policy.default';

    private SettingService $settings;

    public function __construct(PDO $pdo)
    {
        $this->settings = new SettingService($pdo);
    }

    public function policy(string $companyId): array
    {
        $baseline = $this->settings->getJson(self::BASELINE_KEY, $this->baseline());
        $override = $this->settings->getJson(self::companyKey($companyId), []);
        return array_replace_recursive($baseline, $override);
    }

    public function saveCompanyOverride(string $companyId, array $override): bool
    {
        $policy = array_replace_recursive($this->policy($companyId), $override);
        $policy['policy_revision'] = max(1, (int) ($policy['policy_revision'] ?? 0) + 1);
        return $this->settings->save(
            self::companyKey($companyId),
            json_encode($policy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'JOURNAL_LEARNING',
            null,
            '회사별 분개추천 지속학습 정책 Override'
        );
    }

    public function snapshot(string $companyId): array
    {
        $policy = $this->policy($companyId);
        return ['company_id' => $companyId, 'policy_revision' => (int) ($policy['policy_revision'] ?? 1), 'policy' => $policy];
    }

    public function isGuardEnabled(string $companyId, string $scope, string $code): bool
    {
        $policy = $this->policy($companyId);
        $scope = strtolower(trim($scope));
        $code = strtoupper(trim($code));
        if ($code === '') {
            return true;
        }
        if (array_key_exists($code, (array) ($policy['guards'][$scope] ?? []))) {
            return (bool) $policy['guards'][$scope][$code];
        }
        return true;
    }

    private function companyKey(string $companyId): string
    {
        return 'journal_learning_policy.' . trim($companyId);
    }

    private function baseline(): array
    {
        return [
            'policy_revision' => 1, 'minimum_sample_count' => 3, 'minimum_agreement_ratio' => 1.0,
            'maximum_user_modified_ratio' => 0.0, 'maximum_conflict_count' => 0, 'recency_days' => 365,
            'minimum_successful_apply_count' => 3, 'auto_promotion_enabled' => false,
            'guards' => [
                'operation_type' => ['PERSONAL_EXPENSE' => false, 'PAYROLL' => false],
                'import_type' => ['CARD_HOMETAX' => false, 'CARD_STATEMENT' => false, 'PAYROLL_REPORT' => false, 'PAYROLL_WITHHOLDING' => false],
                'workflow' => ['CARD_PAYMENT' => false],
            ],
        ];
    }
}
