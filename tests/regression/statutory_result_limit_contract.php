<?php

declare(strict_types=1);

use App\Services\System\StatutoryStandardResolver;
use App\Services\System\StatutoryStandardTemplateService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$template = (new StatutoryStandardTemplateService($db))->find('HEALTH_INSURANCE');
$health = (new StatutoryStandardResolver($db))->resolve('HEALTH_INSURANCE', '2026-08-01');
$pension = (new StatutoryStandardResolver($db))->resolve('NATIONAL_PENSION', '2026-08-01');
$care = (new StatutoryStandardResolver($db))->resolve('LONG_TERM_CARE', '2026-08-01');
$employment = (new StatutoryStandardResolver($db))->resolve('EMPLOYMENT_INSURANCE', '2026-08-01');
$values = $health['value_data'];
$codes = array_column($template['fields'], 'code');

$checks = [
    'new_fields_in_template' => array_diff([
        'minimum_result_amount', 'maximum_result_amount',
        'result_limit_application_stage', 'qualification_month_rule_code',
    ], $codes) === [],
    'minimum_result_amount' => ($values['minimum_result_amount'] ?? null) === 20160,
    'maximum_result_amount' => ($values['maximum_result_amount'] ?? null) === 9183480,
    'qualification_rule' => ($values['qualification_month_rule_code'] ?? '')
        === 'FIRST_DAY_CHANGE_USES_NEW_STATUS_OTHERWISE_PREVIOUS_STATUS',
    'result_limit_stage_not_guessed' => !array_key_exists('result_limit_application_stage', $values),
    'health_rounding_resolved' => ($values['calculation_policy']['method'] ?? '') === 'TRUNCATE'
        && ($values['calculation_policy']['discard_below_unit'] ?? null) === 10,
    'base_limit_semantics_preserved' => array_key_exists('minimum_base_amount', $values)
        && array_key_exists('maximum_base_amount', $values)
        && $values['minimum_base_amount'] === '' && $values['maximum_base_amount'] === '',
    'pension_policy_preserved' => ($pension['value_data']['calculation_policy']['method'] ?? '') === 'TRUNCATE'
        && ($pension['value_data']['calculation_policy']['discard_below_unit'] ?? null) === 1000,
    'long_term_care_rounding_resolved' => ($care['value_data']['calculation_policy']['method'] ?? '') === 'TRUNCATE'
        && ($care['value_data']['calculation_policy']['discard_below_unit'] ?? null) === 10,
    'employment_rounding_resolved' => ($employment['value_data']['calculation_policy']['method'] ?? '') === 'TRUNCATE'
        && ($employment['value_data']['calculation_policy']['discard_below_unit'] ?? null) === 10,
    'employment_base_resolved' => ($employment['value_data']['calculation_policy']['base_value_code'] ?? '')
        === 'INSURABLE_REMUNERATION',
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(1);
}

echo json_encode(['success' => true, 'checks' => $checks],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
