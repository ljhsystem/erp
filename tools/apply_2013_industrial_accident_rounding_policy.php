<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$mode = $argv[1] ?? 'audit';
if (!in_array($mode, ['audit', 'apply'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_2013_industrial_accident_rounding_policy.php [audit|apply]');
}

$db = DbPdo::conn();
$load = static function () use ($db): array {
    $statement = $db->query(
        "SELECT id, standard_type_code, effective_from, effective_to, value_data, note, updated_at, updated_by
           FROM system_statutory_standards
          WHERE standard_type_code = 'INDUSTRIAL_ACCIDENT'
            AND effective_from = '2013-01-01'
            AND effective_to = '2013-12-31'
          ORDER BY id"
    );
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
};

$rows = $load();
if (count($rows) !== 1) {
    throw new RuntimeException('2013년 산재보험 법정기준이 정확히 1건이 아닙니다.');
}

$before = $rows[0];
$valueData = json_decode((string) $before['value_data'], true, 512, JSON_THROW_ON_ERROR);
$rates = array_values((array) ($valueData['industry_rates'] ?? []));
if (
    count($rates) !== 1
    || trim((string) ($rates[0]['industry_name'] ?? '')) !== '건설업'
    || abs((float) ($rates[0]['employer_rate'] ?? 0) - 0.037) > 0.00000001
) {
    throw new RuntimeException('승인된 2013년 건설업 3.7% 법정기준과 일치하지 않습니다.');
}

$targetPolicy = [
    'method' => 'TRUNCATE',
    'discard_below_unit' => 10,
    'stage' => 'AFTER_RATE_APPLICATION',
    'base_value_code' => 'INSURABLE_REMUNERATION',
    'aggregation_unit' => 'INSURED_PERSON_PAYMENT',
    'application_order' => 1,
];
$currentPolicy = (array) ($valueData['calculation_policy'] ?? []);

if ($mode === 'audit') {
    echo json_encode([
        'success' => true,
        'mode' => $mode,
        'standard' => $before,
        'current_policy' => $currentPolicy,
        'target_policy' => $targetPolicy,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit(0);
}

if ($currentPolicy !== [] && $currentPolicy !== $targetPolicy) {
    throw new RuntimeException('기존 산재보험 계산정책이 승인 내용과 달라 적용을 중단합니다.');
}

$valueData['calculation_policy'] = $targetPolicy;
$valueData['_schema']['calculation_policy']['fields'] = [
    ['code' => 'method', 'name' => '끝수 처리방법', 'type' => 'rounding', 'required' => true],
    ['code' => 'discard_below_unit', 'name' => '버림 기준 단위', 'type' => 'number', 'required' => true, 'min' => 1, 'unit_label' => '원'],
    [
        'code' => 'stage',
        'name' => '계산정책 적용단계',
        'type' => 'select',
        'required' => true,
        'options' => [['value' => 'AFTER_RATE_APPLICATION', 'label' => '보수에 보험료율을 적용한 후']],
    ],
    [
        'code' => 'base_value_code',
        'name' => '계산 기초값',
        'type' => 'select',
        'required' => true,
        'options' => [['value' => 'INSURABLE_REMUNERATION', 'label' => '비과세 근로소득을 제외한 보수']],
    ],
    [
        'code' => 'aggregation_unit',
        'name' => '계산 집계단위',
        'type' => 'select',
        'required' => true,
        'options' => [['value' => 'INSURED_PERSON_PAYMENT', 'label' => '피보험자별 보수 지급 건']],
    ],
    ['code' => 'application_order', 'name' => '정책 적용순서', 'type' => 'number', 'required' => true, 'min' => 1],
];

$actor = ActorHelper::system('STATUTORY_STANDARD_POLICY_CORRECTION');
$db->beginTransaction();
try {
    $update = $db->prepare(
        'UPDATE system_statutory_standards
            SET value_data = :value_data, updated_at = NOW(), updated_by = :updated_by
          WHERE id = :id AND value_data = :before_value_data'
    );
    $update->execute([
        'value_data' => json_encode($valueData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'updated_by' => $actor,
        'id' => $before['id'],
        'before_value_data' => $before['value_data'],
    ]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException('법정기준 수정 행 수가 정확히 1건이 아닙니다.');
    }

    $afterRows = $load();
    $after = $afterRows[0] ?? null;
    $afterData = $after
        ? json_decode((string) $after['value_data'], true, 512, JSON_THROW_ON_ERROR)
        : [];
    if (count($afterRows) !== 1 || ($afterData['calculation_policy'] ?? null) !== $targetPolicy) {
        throw new RuntimeException('산재보험 계산정책 적용 후 검증에 실패했습니다.');
    }

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

echo json_encode([
    'success' => true,
    'mode' => $mode,
    'affected' => 1,
    'actor' => $actor,
    'before' => $before,
    'after' => $after,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
