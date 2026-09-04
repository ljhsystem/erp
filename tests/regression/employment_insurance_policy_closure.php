<?php

declare(strict_types=1);

use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$resolver = new StatutoryStandardResolver($db);
$boundaries = [
    '2013-06-30' => 0.0055,
    '2013-07-01' => 0.0065,
    '2013-08-31' => 0.0065,
    '2013-09-11' => 0.0065,
    '2019-09-30' => 0.0065,
    '2019-10-01' => 0.008,
    '2022-06-30' => 0.008,
    '2022-07-01' => 0.009,
    '2026-08-24' => 0.009,
];
$checks = [];
foreach ($boundaries as $date => $rate) {
    $standard = $resolver->resolve('EMPLOYMENT_INSURANCE', $date);
    $policy = $standard['value_data']['calculation_policy'] ?? [];
    $checks['boundary_' . $date] = abs((float) ($standard['value_data']['employee_rate'] ?? 0) - $rate) < 0.0000001;
    $checks['policy_' . $date] = ($policy['method'] ?? '') === 'TRUNCATE'
        && (int) ($policy['discard_below_unit'] ?? 0) === 10
        && ($policy['stage'] ?? '') === 'AFTER_RATE_APPLICATION'
        && ($policy['base_value_code'] ?? '') === 'INSURABLE_REMUNERATION'
        && ($policy['aggregation_unit'] ?? '') === 'INSURED_PERSON_PAYMENT';
}

$truncate = static fn(float $value): float => floor($value / 10) * 10;
$checks['2013_actual_taxable_base'] = 1088890 - 100000 === 988890;
$checks['2013_actual_before_rounding'] = abs(988890 * 0.0065 - 6427.785) < 0.000001;
$checks['2013_actual_final'] = $truncate(988890 * 0.0065) === 6420.0;
$checks['2026_same_consumer_policy'] = $truncate(988890 * 0.009) === 8900.0;
$checks['rounding_6409_99'] = $truncate(6409.99) === 6400.0;
$checks['rounding_6410_00'] = $truncate(6410.00) === 6410.0;
$checks['rounding_6419_99'] = $truncate(6419.99) === 6410.0;

$failed = array_keys(array_filter($checks, static fn(bool $value): bool => !$value));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($failed === [] ? 0 : 1);
