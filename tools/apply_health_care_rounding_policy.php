<?php

declare(strict_types=1);

use App\Services\System\StatutoryStandardResolver;
use App\Services\System\StatutoryStandardTemplateService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = $argv[1] ?? 'verify';
if (!in_array($direction, ['up', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_health_care_rounding_policy.php [up|verify]\n");
    exit(1);
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$count = static fn(string $type): int => (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code=" . $db->quote($type)
)->fetchColumn();
$before = array_map($count, ['HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'NATIONAL_PENSION']);

if ($direction === 'up') {
    $db->beginTransaction();
    try {
        foreach ([
            '20260822_06_add_health_care_rounding_policy.up.sql',
            '20260822_07_correct_health_rounding_policy_parent.up.sql',
        ] as $migration) {
            $db->exec((string) file_get_contents(PROJECT_ROOT . '/app/migrations/' . $migration));
        }
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
$health = $resolver->resolve('HEALTH_INSURANCE', '2026-08-01');
$care = $resolver->resolve('LONG_TERM_CARE', '2026-08-01');
$employment = $resolver->resolve('EMPLOYMENT_INSURANCE', '2026-08-01');
$pension = $resolver->resolve('NATIONAL_PENSION', '2026-08-01');
$after = array_map($count, ['HEALTH_INSURANCE', 'LONG_TERM_CARE', 'EMPLOYMENT_INSURANCE', 'NATIONAL_PENSION']);

$policyFields = static fn(string $type): array => array_column(
    $templates[$type]['calculation_policy']['fields'] ?? [], 'code'
);
$checks = [
    'revision_counts_preserved' => $before === $after,
    'health_policy_fields' => array_diff(['method', 'discard_below_unit'], $policyFields('HEALTH_INSURANCE')) === [],
    'care_policy_fields' => array_diff(['method', 'discard_below_unit'], $policyFields('LONG_TERM_CARE')) === [],
    'health_policy' => $health['value_data']['calculation_policy'] ?? [],
    'care_policy' => $care['value_data']['calculation_policy'] ?? [],
    'health_result_stage_unresolved' => !array_key_exists('result_limit_application_stage', $health['value_data']),
    'employment_unchanged_blocked' => empty($employment['value_data']['calculation_policy']['method'])
        && empty($employment['value_data']['qualification_month_rule_code']),
    'pension_policy_preserved' => $pension['value_data']['calculation_policy'] ?? [],
    'rounding_sources_added' => (int) $db->query(
        "SELECT COUNT(*) FROM system_statutory_standard_sources WHERE id LIKE 'a8202206-%'"
    )->fetchColumn() === 5,
    'prior_health_sources_preserved' => (int) $db->query(
        "SELECT COUNT(*) FROM system_statutory_standard_sources WHERE id LIKE 'a8202205-%'"
    )->fetchColumn() === 3,
];

foreach (['health_policy', 'care_policy'] as $key) {
    if (($checks[$key]['method'] ?? '') !== 'TRUNCATE'
        || ($checks[$key]['discard_below_unit'] ?? null) !== 10) {
        throw new RuntimeException('건강보험·장기요양 끝수처리 계약 검증에 실패했습니다.');
    }
}
if (!$checks['revision_counts_preserved'] || !$checks['health_policy_fields']
    || !$checks['care_policy_fields'] || !$checks['health_result_stage_unresolved']
    || !$checks['employment_unchanged_blocked'] || !$checks['rounding_sources_added']
    || !$checks['prior_health_sources_preserved']) {
    throw new RuntimeException('법정기준 Revision 불변 또는 BLOCKED 계약 검증에 실패했습니다.');
}

echo json_encode(['success' => true, 'direction' => $direction, 'checks' => $checks],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
