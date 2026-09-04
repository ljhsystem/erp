<?php

namespace App\Services\Institution;

use App\Services\System\UserSettingService;
use PDO;

class EmploymentContractFieldPolicyService
{
    private const PAGE_KEY = 'institution.human_resources.employment_contracts';
    private const EDITABLE_FIELDS = [
        'employee_id', 'contract_type', 'contract_period_type', 'employment_category',
        'working_time_type', 'contract_date', 'contract_start_date', 'contract_end_date',
        'fixed_term_reason_code', 'fixed_term_reason_detail', 'work_location_type',
        'project_id', 'work_location_detail', 'job_title_snapshot', 'job_description',
        'work_schedule_type', 'salary_type', 'payment_day', 'payment_timing',
        'probation_start_date', 'probation_end_date', 'probation_rate', 'note',
    ];

    private UserSettingService $userSettings;
    private ?array $settingsCache = null;

    public function __construct(PDO $pdo)
    {
        $this->userSettings = new UserSettingService($pdo);
    }

    public function validateRequiredFields(array $input): void
    {
        $settings = $this->settings();
        $policies = is_array($settings['columnRequirementPolicy'] ?? null)
            ? $settings['columnRequirementPolicy'] : [];
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
