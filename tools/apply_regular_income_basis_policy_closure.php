<?php

declare(strict_types=1);

use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\System\StatutoryStandardResolver;
use App\Services\System\StatutoryStandardTemplateService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($direction, ['up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_regular_income_basis_policy_closure.php [up|verify]');
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$count = static fn(string $type): int => (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code=" . $db->quote($type)
)->fetchColumn();
$before = ['pension' => $count('NATIONAL_PENSION'), 'health' => $count('HEALTH_INSURANCE')];

if ($direction === 'up') {
    $db->beginTransaction();
    try {
        $db->exec((string) file_get_contents(
            PROJECT_ROOT . '/app/migrations/20260824_02_close_regular_income_insurance_basis_policy.up.sql'
        ));
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

$resolver = new StatutoryStandardResolver($db);
$templates = array_column((new StatutoryStandardTemplateService($db))->all(), null, 'code');
$pension = $resolver->resolve('NATIONAL_PENSION', '2013-08-31')['value_data'];
$health = $resolver->resolve('HEALTH_INSURANCE', '2013-08-31')['value_data'];
$templateFields = static fn(string $type): array => array_column(
    $templates[$type]['calculation_policy']['fields'] ?? [], 'code'
);
$after = ['pension' => $count('NATIONAL_PENSION'), 'health' => $count('HEALTH_INSURANCE')];
$sourceCount = (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standard_sources WHERE id LIKE 'a8240002-%' OR id LIKE 'a8240003-%'"
)->fetchColumn();
$expectedSources = $after['pension'] + $after['health'];

$checks = [
    'revision_counts_preserved' => $before === $after,
    'pension_policy' => ($pension['calculation_policy']['stage'] ?? '') === 'ASSESSMENT_BASE'
        && ($pension['calculation_policy']['automatic_fallback_base_value_code'] ?? '') === 'TAXABLE_PAY_ITEM_FINAL_AMOUNT'
        && ($pension['calculation_policy']['pay_item_basis_rule_code'] ?? '') === 'EXCLUDE_NON_TAXABLE_EMPLOYMENT_INCOME',
    'health_policy' => ($health['calculation_policy']['stage'] ?? '') === 'AFTER_RATE_APPLICATION'
        && ($health['calculation_policy']['base_value_code'] ?? '') === 'MONTHLY_REMUNERATION'
        && ($health['calculation_policy']['automatic_fallback_base_value_code'] ?? '') === 'TAXABLE_PAY_ITEM_FINAL_AMOUNT',
    'pension_template' => array_diff(['automatic_fallback_base_value_code','pay_item_basis_rule_code'], $templateFields('NATIONAL_PENSION')) === [],
    'health_template' => array_diff(['stage','base_value_code','automatic_fallback_base_value_code','pay_item_basis_rule_code','aggregation_unit','application_order'], $templateFields('HEALTH_INSURANCE')) === [],
    'official_source_count' => $sourceCount,
    'expected_source_count' => $expectedSources,
];
if (in_array(false, array_intersect_key($checks, array_flip([
    'revision_counts_preserved','pension_policy','health_policy','pension_template','health_template',
])), true) || $sourceCount !== $expectedSources) {
    throw new RuntimeException('보험별 계산기초 정책 Closure 검증에 실패했습니다.');
}

echo json_encode(['success' => true, 'direction' => $direction, 'checks' => $checks],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
