<?php

namespace App\Services\Institution;

use App\Services\System\UserSettingService;
use PDO;

final class DailyEmploymentIncomeFieldPolicyService
{
    private const PAGE_KEY = 'institution.income-data.daily-employment';
    private const EDITABLE_FIELDS = ['income_year_month', 'document_title'];

    private UserSettingService $userSettings;
    private ?array $settingsCache = null;

    public function __construct(PDO $pdo)
    {
        $this->userSettings = new UserSettingService($pdo);
    }

    public function validateRequiredFields(array $input): void
    {
        $policies = $this->settings()['columnRequirementPolicy'] ?? [];
        $policies = is_array($policies) ? $policies : [];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (strtolower(trim((string) ($policies[$field] ?? ''))) !== 'required'
                || trim((string) ($input[$field] ?? '')) !== '') {
                continue;
            }
            throw new \InvalidArgumentException($this->label($field, $field) . '은(는) 필수 입력입니다.');
        }
    }

    public function label(string $field, string $fallback): string
    {
        $displayNames = $this->settings()['columnDisplayName'] ?? [];
        $label = is_array($displayNames) ? trim((string) ($displayNames[$field] ?? '')) : '';
        return $label !== '' ? $label : $fallback;
    }

    private function settings(): array
    {
        if ($this->settingsCache !== null) return $this->settingsCache;
        $settings = $this->userSettings->detail(self::PAGE_KEY, 'TABLE')['settings_json'] ?? [];
        return $this->settingsCache = is_array($settings) ? $settings : [];
    }
}
