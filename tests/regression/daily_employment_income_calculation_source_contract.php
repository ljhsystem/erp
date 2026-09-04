<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\DailyEmploymentIncomeCalculationSourceService;

$service = new DailyEmploymentIncomeCalculationSourceService();
$source = [
    'daily_employment_income_id' => 'header-1',
    'income_year_month' => '2026-08',
    'payment_sequence' => 1,
    'calculation_policy_version' => 'policy-1',
    'groups' => [[
        'id' => 'group-1', 'sort_no' => 1, 'business_unit' => 'CONSTRUCTION',
        'project_id' => 'project-1', 'work_team_id' => 'team-1',
        'items' => [[
            'id' => 'item-1', 'sort_no' => 1, 'worker_client_id' => 'worker-1',
            'work_type_code' => 'STONE', 'work_description' => '석공 작업',
            'workdays' => [[
                'id' => 'day-1', 'work_date' => '2026-08-03', 'actual_work_minutes' => 480,
                'daily_rate_amount' => '200000', 'social_insurance_workplace_id' => 'workplace-1',
                'insurance_resolver_revision' => 'resolver-1',
                'lines' => [
                    ['line_type_code' => 'PAY', 'line_code' => 'BASE', 'taxability_code' => 'TAXABLE', 'final_amount' => 200000, 'statutory_standard_id' => 'standard-1'],
                    ['line_type_code' => 'PAY', 'line_code' => 'MEAL', 'taxability_code' => 'NON_TAXABLE', 'revision_status_code' => 'DRAFT', 'final_amount' => 10000, 'non_taxable_revision_id' => 'draft-1'],
                ],
            ]],
        ]],
    ]],
    'ui_collapsed' => true,
];
$same = $source;
$same['ui_collapsed'] = false;
$same['groups'][0]['items'][0]['workdays'][0]['lines'] = array_reverse($same['groups'][0]['items'][0]['workdays'][0]['lines']);
$changed = $source;
$changed['groups'][0]['items'][0]['workdays'][0]['actual_work_minutes'] = 481;
$confirmed = $source;
$confirmed['groups'][0]['items'][0]['workdays'][0]['lines'][1]['revision_status_code'] = 'CONFIRMED';

$hash = $service->hash($source);
$assertions = [
    'UI 상태와 Line 순서는 hash 제외' => hash_equals($hash, $service->hash($same)),
    '근로시간 변경은 hash 변경' => !hash_equals($hash, $service->hash($changed)),
    '확인된 비과세는 hash 포함' => !hash_equals($hash, $service->hash($confirmed)),
    'SHA-256 형식' => preg_match('/^[0-9a-f]{64}$/', $hash) === 1,
];
foreach ($assertions as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "OK: 기관계산 source_hash canonicalization 계약\n";
