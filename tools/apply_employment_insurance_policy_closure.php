<?php

declare(strict_types=1);

use App\Services\System\StatutoryStandardResolver;
use App\Services\System\StatutoryStandardTemplateService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = strtolower((string) ($argv[1] ?? 'verify'));
if (!in_array($direction, ['up', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_employment_insurance_policy_closure.php [up|verify]');
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$countRevisions = static fn(): int => (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE'"
)->fetchColumn();
$before = $countRevisions();

if ($direction === 'up') {
    $db->beginTransaction();
    try {
        $db->exec((string) file_get_contents(
            PROJECT_ROOT . '/app/migrations/20260824_01_close_employment_insurance_calculation_policy.up.sql'
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
$template = (new StatutoryStandardTemplateService($db))->find('EMPLOYMENT_INSURANCE');
$boundaries = [
    '2013-06-30' => 0.0055,
    '2013-07-01' => 0.0065,
    '2013-08-31' => 0.0065,
    '2013-09-11' => 0.0065,
    '2019-09-30' => 0.0065,
    '2019-10-01' => 0.008,
    '2022-06-30' => 0.008,
    '2022-07-01' => 0.009,
    date('Y-m-d') => 0.009,
];
$resolved = [];
foreach ($boundaries as $date => $expectedRate) {
    $standard = $resolver->resolve('EMPLOYMENT_INSURANCE', $date);
    $policy = $standard['value_data']['calculation_policy'] ?? [];
    $resolved[$date] = [
        'revision_id' => $standard['id'],
        'employee_rate' => $standard['value_data']['employee_rate'] ?? null,
        'policy' => $policy,
    ];
    if (abs((float) $resolved[$date]['employee_rate'] - $expectedRate) > 0.0000001
        || ($policy['method'] ?? '') !== 'TRUNCATE'
        || (int) ($policy['discard_below_unit'] ?? 0) !== 10
        || ($policy['base_value_code'] ?? '') !== 'INSURABLE_REMUNERATION') {
        throw new RuntimeException($date . ' 고용보험 법정기준 또는 계산정책 검증에 실패했습니다.');
    }
}

$gapOrOverlap = (int) $db->query(
    "SELECT COUNT(*) FROM (
       SELECT effective_from,LAG(effective_to) OVER (ORDER BY effective_from,id) previous_to
       FROM system_statutory_standards WHERE standard_type_code='EMPLOYMENT_INSURANCE'
     ) revisions
     WHERE previous_to IS NOT NULL AND effective_from<>DATE_ADD(previous_to,INTERVAL 1 DAY)"
)->fetchColumn();
$policyRows = (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standards
     WHERE standard_type_code='EMPLOYMENT_INSURANCE'
       AND JSON_UNQUOTE(JSON_EXTRACT(value_data,'$.calculation_policy.method'))='TRUNCATE'
       AND JSON_EXTRACT(value_data,'$.calculation_policy.discard_below_unit')=10"
)->fetchColumn();
$sourceRows = (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standard_sources WHERE id LIKE 'a8240001-%'"
)->fetchColumn();
$duplicatePeriods = (int) $db->query(
    "SELECT COUNT(*) FROM (
       SELECT effective_from,COALESCE(effective_to,'9999-12-31'),COUNT(*) row_count
       FROM system_statutory_standards
       WHERE standard_type_code='EMPLOYMENT_INSURANCE'
       GROUP BY effective_from,COALESCE(effective_to,'9999-12-31') HAVING COUNT(*)>1
     ) duplicate_periods"
)->fetchColumn();
$activeLeaves = (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standards
     WHERE standard_type_code='EMPLOYMENT_INSURANCE' AND effective_to IS NULL"
)->fetchColumn();
$sourceOrphans = (int) $db->query(
    "SELECT COUNT(*) FROM system_statutory_standard_sources source_row
     LEFT JOIN system_statutory_standards standard_row ON standard_row.id=source_row.standard_id
     WHERE standard_row.id IS NULL"
)->fetchColumn();
$fieldCodes = array_column($template['calculation_policy']['fields'] ?? [], 'code');
$requiredFields = ['method','discard_below_unit','stage','base_value_code','aggregation_unit','application_order','qualification_rule_code'];

$checks = [
    'revision_count_preserved' => $before === $countRevisions() && $countRevisions() === 4,
    'gap_or_overlap_count' => $gapOrOverlap,
    'policy_revision_count' => $policyRows,
    'official_manual_source_count' => $sourceRows,
    'duplicate_period_count' => $duplicatePeriods,
    'active_leaf_count' => $activeLeaves,
    'source_orphan_count' => $sourceOrphans,
    'template_fields_complete' => array_diff($requiredFields, $fieldCodes) === [],
    'boundaries' => $resolved,
];
if (!$checks['revision_count_preserved'] || $gapOrOverlap !== 0 || $policyRows !== 4
    || $sourceRows !== 4 || $duplicatePeriods !== 0 || $activeLeaves !== 1 || $sourceOrphans !== 0
    || !$checks['template_fields_complete']) {
    throw new RuntimeException('고용보험 계산정책 Closure 무결성 검증에 실패했습니다.');
}

echo json_encode(['success' => true, 'direction' => $direction, 'checks' => $checks],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
