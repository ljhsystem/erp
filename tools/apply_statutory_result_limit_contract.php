<?php

declare(strict_types=1);

use App\Services\System\StatutoryStandardResolver;
use App\Services\System\StatutoryStandardTemplateService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$direction = $argv[1] ?? 'verify';
if (!in_array($direction, ['up', 'verify'], true)) {
    fwrite(STDERR, "사용법: php tools/apply_statutory_result_limit_contract.php [up|verify]\n");
    exit(1);
}

$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$countsBefore = [
    'standards' => (int) $db->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn(),
    'pension' => (int) $db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='NATIONAL_PENSION'")->fetchColumn(),
];

if ($direction === 'up') {
    $sql = (string) file_get_contents(
        PROJECT_ROOT . '/app/migrations/20260822_05_extend_statutory_result_limit_contract.up.sql'
    );
    $db->beginTransaction();
    try {
        $db->exec($sql);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

$templates = array_column((new StatutoryStandardTemplateService($db))->all(), null, 'code');
$health = (new StatutoryStandardResolver($db))->resolve('HEALTH_INSURANCE', '2026-06-30');
$values = $health['value_data'];
$fieldCodes = array_column($templates['HEALTH_INSURANCE']['fields'] ?? [], 'code');
$countsAfter = [
    'standards' => (int) $db->query('SELECT COUNT(*) FROM system_statutory_standards')->fetchColumn(),
    'pension' => (int) $db->query("SELECT COUNT(*) FROM system_statutory_standards WHERE standard_type_code='NATIONAL_PENSION'")->fetchColumn(),
];
$sourceStatement = $db->prepare('SELECT COUNT(*) FROM system_statutory_standard_sources WHERE standard_id=:id');
$sourceStatement->execute([':id' => $health['id']]);

$checks = [
    'row_counts_preserved' => $countsBefore === $countsAfter,
    'dynamic_fields_registered' => array_diff([
        'minimum_result_amount', 'maximum_result_amount',
        'result_limit_application_stage', 'qualification_month_rule_code',
    ], $fieldCodes) === [],
    'minimum_result_amount' => $values['minimum_result_amount'] ?? null,
    'maximum_result_amount' => $values['maximum_result_amount'] ?? null,
    'result_limit_stage_unresolved' => !array_key_exists('result_limit_application_stage', $values),
    'qualification_month_rule_code' => $values['qualification_month_rule_code'] ?? null,
    'base_limits_preserved' => array_key_exists('minimum_base_amount', $values)
        && array_key_exists('maximum_base_amount', $values),
    'rounding_blocked' => empty($values['calculation_policy']['method']),
    'source_count' => (int) $sourceStatement->fetchColumn(),
];

if (!$checks['row_counts_preserved'] || !$checks['dynamic_fields_registered']
    || $checks['minimum_result_amount'] !== 20160 || $checks['maximum_result_amount'] !== 9183480
    || !$checks['result_limit_stage_unresolved'] || !$checks['base_limits_preserved']
    || !$checks['rounding_blocked']) {
    throw new RuntimeException('법정기준 결과한도 JSON 계약 검증에 실패했습니다.');
}

echo json_encode([
    'success' => true,
    'direction' => $direction,
    'health_standard_id' => $health['id'],
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
