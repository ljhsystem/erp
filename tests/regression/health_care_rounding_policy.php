<?php

declare(strict_types=1);

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$resolver = new StatutoryStandardResolver($db);
$health = $resolver->resolve('HEALTH_INSURANCE', '2026-08-01')['value_data'];
$care = $resolver->resolve('LONG_TERM_CARE', '2026-08-01')['value_data'];
$employment = $resolver->resolve('EMPLOYMENT_INSURANCE', '2026-08-01')['value_data'];
$pension = $resolver->resolve('NATIONAL_PENSION', '2026-08-01')['value_data'];
$service = new RegularEmploymentIncomeCalculationService($db);
$round = new ReflectionMethod($service, 'round');
$limitsReady = new ReflectionMethod($service, 'resultLimitsReady');
$finalize = new ReflectionMethod($service, 'finalizePremium');
$healthPolicy = $health['calculation_policy'];
$carePolicy = $care['calculation_policy'];

$beforeLimit = $round->invoke($service, 9183487.0, $healthPolicy);
$afterLimit = min($beforeLimit, (float) $health['maximum_result_amount']);
$limitBeforeRounding = $round->invoke(
    $service,
    min(9183487.0, (float) $health['maximum_result_amount']),
    $healthPolicy
);
$checks = [
    'health_resolver_policy' => ($healthPolicy['method'] ?? '') === 'TRUNCATE'
        && ($healthPolicy['discard_below_unit'] ?? null) === 10,
    'care_resolver_policy' => ($carePolicy['method'] ?? '') === 'TRUNCATE'
        && ($carePolicy['discard_below_unit'] ?? null) === 10,
    'removes_one_to_nine_won' => $round->invoke($service, 123457.0, $healthPolicy) === 123450.0,
    'keeps_ten_won_unit' => $round->invoke($service, 123450.0, $carePolicy) === 123450.0,
    'result_stage_unresolved' => !array_key_exists('result_limit_application_stage', $health),
    'result_limit_order_equivalent' => $afterLimit === $limitBeforeRounding
        && $limitsReady->invoke($service, $health, $healthPolicy) === true,
    'result_limit_applied' => $finalize->invoke($service, 9183487.0, $healthPolicy, $health) === 9183480.0,
    'care_base_stage_preserved' => ($carePolicy['stage'] ?? '') === 'AFTER_HEALTH_INSURANCE_PREMIUM'
        && ($carePolicy['base_value_code'] ?? '') === 'HEALTH_INSURANCE_PREMIUM',
    'employment_unchanged_blocked' => empty($employment['calculation_policy']['method'])
        && empty($employment['qualification_month_rule_code']),
    'pension_unchanged' => ($pension['calculation_policy']['method'] ?? '') === 'TRUNCATE'
        && ($pension['calculation_policy']['discard_below_unit'] ?? null) === 1000,
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($failed === [] ? 0 : 1);
