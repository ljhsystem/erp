<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\EmploymentContractService;
use Core\DbPdo;

$pdo = DbPdo::conn();
$service = new EmploymentContractService($pdo);
$rows = $pdo->query(
    "SELECT c.id, c.contract_no, c.contract_status, c.previous_contract_id,
            COUNT(DISTINCT weekly.id) AS schedule_count,
            COUNT(DISTINCT breaks.id) AS break_count
       FROM institution_employment_contracts c
       LEFT JOIN institution_employment_contracts_weekly_schedules weekly
         ON weekly.contract_id = c.id
       LEFT JOIN institution_employment_contracts_break_schedules breaks
         ON breaks.weekly_schedule_id = weekly.id
      WHERE c.deleted_at IS NULL
      GROUP BY c.id, c.contract_no, c.contract_status, c.previous_contract_id
      ORDER BY c.updated_at DESC"
)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

$pick = static function (callable $predicate) use ($rows): ?array {
    foreach ($rows as $row) {
        if ($predicate($row)) return $row;
    }
    return null;
};

$scenarios = [
    'approved_representative' => $pick(static fn(array $row): bool => in_array($row['contract_status'], ['APPROVED', 'EFFECTIVE'], true) && (int) $row['break_count'] === 0),
    'detailed_breaks' => $pick(static fn(array $row): bool => (int) $row['break_count'] > 0),
    'draft' => $pick(static fn(array $row): bool => $row['contract_status'] === 'DRAFT'),
    'revision_or_correction' => $pick(static fn(array $row): bool => !empty($row['previous_contract_id'])),
];

$benchmark = static function (callable $callback, int $iterations = 7): array {
    $samples = [];
    $payloadBytes = 0;
    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $startedAt = hrtime(true);
        $payload = $callback();
        $samples[] = (hrtime(true) - $startedAt) / 1_000_000;
        $payloadBytes = strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    sort($samples);
    return [
        'iterations' => $iterations,
        'median_ms' => round($samples[(int) floor(($iterations - 1) / 2)], 3),
        'p95_ms' => round($samples[(int) ceil(($iterations - 1) * 0.95)], 3),
        'payload_bytes' => $payloadBytes,
    ];
};

$results = [];
foreach ($scenarios as $name => $scenario) {
    $results[$name] = $scenario === null
        ? ['status' => 'NO_EXISTING_FIXTURE']
        : ['status' => 'MEASURED', 'contract' => $scenario] + $benchmark(
            static fn(): array => $service->detail((string) $scenario['id'])
        );
}
$results['form_options'] = $benchmark(static fn(): array => $service->formOptions());

$modalSource = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/employment-contract/modal-runtime.js') ?: '';
$performanceSource = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/employment-contract/modal-performance.js') ?: '';
$indexSource = file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/employment-contract/index.js') ?: '';
$checks = [
    'detail_and_options_parallel' => str_contains($modalSource, 'Promise.all([detailPromise, optionsPromise])'),
    'detail_single_component_hydration' => substr_count($modalSource, 'replaceComponentRows(basic.data.components || [])') === 1
        && !str_contains($modalSource, "replaceComponentRows([]);\n    replaceComponentRows(basic.data.components || [])"),
    'approved_skips_minimum_wage' => str_contains($modalSource, 'if (isContractFormEditable()) void loadMinimumWageGuide();'),
    'idle_runtime_preload' => str_contains($indexSource, 'requestIdleCallback')
        && str_contains($indexSource, 'runtime.preloadModalReferences()'),
    't0_t8_metric' => str_contains($performanceSource, 'T0_listAction')
        && str_contains($modalSource, 'T1_detailRequestStarted')
        && str_contains($modalSource, 'T2_detailResponse')
        && str_contains($modalSource, 'T3_modalDomShown')
        && str_contains($modalSource, 'T4_componentGridHydrated')
        && str_contains($modalSource, 'T5_weeklyBreakHydrated')
        && str_contains($modalSource, 'T6_pickerSelectReady')
        && str_contains($modalSource, 'T7_auxiliaryReady')
        && str_contains($performanceSource, 'T8_interactive'),
];
if (in_array(false, $checks, true)) {
    throw new \RuntimeException('근로계약 상세모달 성능 계약 검증에 실패했습니다.');
}

echo json_encode([
    'success' => true,
    'checks' => $checks,
    'benchmarks' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
