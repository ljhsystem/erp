<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = Core\DbPdo::conn();
$resolver = new App\Services\System\StatutoryStandardResolver($db);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$activeTypeCount = (int) $db->query(
    "SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND is_active=1"
)->fetchColumn();
$assert($activeTypeCount === 13, '활성 법정기준 Type은 13개여야 합니다.');

$templateStatement = $db->prepare(
    "SELECT extra_data FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE' AND code='EMPLOYMENT_INSURANCE'"
    . " AND is_active=1"
);
$templateStatement->execute();
$employmentTemplate = json_decode((string) $templateStatement->fetchColumn(), true);
$employmentFields = array_column((array) ($employmentTemplate['fields'] ?? []), null, 'code');
$assert(isset($employmentFields['additional_employer_rates']), '고용보험 사업규모별 추가 부담률 Matrix 계약이 필요합니다.');
$assert(!isset($employmentFields['additional_employer_rate']), '단일 사업주 추가 부담률 Legacy 필드는 없어야 합니다.');

$loadRows = static function (string $type) use ($db): array {
    $statement = $db->prepare(
        'SELECT id,effective_from,effective_to,value_data,updated_by FROM system_statutory_standards'
        . ' WHERE standard_type_code=:type ORDER BY effective_from'
    );
    $statement->execute([':type' => $type]);
    return array_map(static function (array $row): array {
        $row['value_data'] = json_decode((string) $row['value_data'], true);
        return $row;
    }, $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
};

$assertPeriods = static function (array $rows, array $expected, string $label) use ($assert): void {
    $actual = array_map(
        static fn(array $row): string => $row['effective_from'] . '~' . ($row['effective_to'] ?? 'NULL'),
        $rows
    );
    $assert($actual === $expected, $label . ' 적용기간이 공식 구간과 다릅니다.');
};

$employmentRows = $loadRows('EMPLOYMENT_INSURANCE');
$assertPeriods($employmentRows, [
    '2013-01-01~2013-06-30',
    '2013-07-01~2019-09-30',
    '2019-10-01~2022-06-30',
    '2022-07-01~NULL',
], '고용보험');
$expectedEmploymentRates = [[0.0055, 0.0055], [0.0065, 0.0065], [0.008, 0.008], [0.009, 0.009]];
foreach ($employmentRows as $index => $row) {
    $value = $row['value_data'];
    $assert((float) ($value['employee_rate'] ?? -1) === $expectedEmploymentRates[$index][0], '고용보험 근로자율 오류');
    $assert((float) ($value['employer_rate'] ?? -1) === $expectedEmploymentRates[$index][1], '고용보험 사업주 실업급여율 오류');
    $additionalRates = array_column((array) ($value['additional_employer_rates'] ?? []), 'employer_rate');
    $assert($additionalRates === [0.0025, 0.0045, 0.0065, 0.0085], '고용보험 사업규모별 추가 부담률 오류');
}

$incomeRows = $loadRows('EMPLOYMENT_INCOME_TAX_TABLE');
$assertPeriods($incomeRows, [
    '2012-09-04~2014-02-20',
    '2014-02-21~2017-02-02',
    '2017-02-03~2020-02-10',
    '2020-02-11~2023-02-27',
    '2023-02-28~NULL',
], '근로소득 간이세액표');
$expectedRuleCounts = [2, 2, 3, 5, 6];
foreach ($incomeRows as $index => $row) {
    $value = $row['value_data'];
    $tableRows = (array) ($value['table']['rows'] ?? []);
    $assert(count($tableRows) === 647, '간이세액표 원표 행 수 오류: ' . $row['effective_from']);
    $assert(($value['table']['dependent_counts'] ?? []) === array_map('strval', range(1, 11)),
        '간이세액표 가족수 차원 오류: ' . $row['effective_from']);
    $assert(count((array) ($value['excess_rules'] ?? [])) === $expectedRuleCounts[$index],
        '간이세액표 상한초과 규칙 수 오류: ' . $row['effective_from']);
}
$currentIncome = $incomeRows[array_key_last($incomeRows)]['value_data'];
$assert(count((array) ($currentIncome['adjustment_rules'] ?? [])) === 3, '현행 자녀수별 세액 조정규칙은 3구간이어야 합니다.');
$assert((float) ($currentIncome['excess_rules'][5]['fixed_addition'] ?? -1) === 31034600.0,
    '현행 8,700만원 초과 고정가산액이 공식 원표와 다릅니다.');

$industrialRows = $loadRows('INDUSTRIAL_ACCIDENT');
$expectedIndustrialPeriods = [];
for ($year = 2013; $year <= 2026; $year++) {
    $expectedIndustrialPeriods[] = $year . '-01-01~' . ($year === 2026 ? 'NULL' : $year . '-12-31');
}
$assertPeriods($industrialRows, $expectedIndustrialPeriods, '산재보험 건설업');
$expectedIndustrialRates = [0.037, 0.038, 0.038, 0.038, 0.039, 0.0405, 0.0375,
    0.0373, 0.037, 0.037, 0.037, 0.0356, 0.0356, 0.0356];
foreach ($industrialRows as $index => $row) {
    $value = $row['value_data'];
    $assert(($value['industry_rates'][0]['industry_name'] ?? '') === '건설업', '산재보험 사업종류명 오류');
    $assert((float) ($value['industry_rates'][0]['employer_rate'] ?? -1) === $expectedIndustrialRates[$index],
        '산재보험 건설업 요율 오류: ' . $row['effective_from']);
}

$sourceStatement = $db->query(
    "SELECT COUNT(*) AS total,"
    . "SUM(CASE WHEN COALESCE(source_name,'')='' OR COALESCE(organization_name,'')=''"
    . " OR COALESCE(law_name,'')='' OR COALESCE(source_url,'')='' OR COALESCE(note,'')='' THEN 1 ELSE 0 END) AS core_missing"
    . ' FROM system_statutory_standard_sources'
);
$sourceQuality = $sourceStatement->fetch(PDO::FETCH_ASSOC) ?: [];
$assert((int) ($sourceQuality['total'] ?? 0) === 92, '공식 Source 총수는 92건이어야 합니다.');
$assert((int) ($sourceQuality['core_missing'] ?? 0) === 0, '공식 Source 핵심 메타데이터가 비어 있습니다.');

$reportTypeCount = (int) $db->query(
    "SELECT COUNT(*) FROM system_codes WHERE code_group='STATUTORY_STANDARD_TYPE'"
    . " AND code='REPORT_ROUNDING_RULE' AND is_active=1"
)->fetchColumn();
$assert($reportTypeCount === 0, 'REPORT_ROUNDING_RULE은 복원되면 안 됩니다.');

$allStandards = $db->query(
    'SELECT id,standard_type_code,effective_from,effective_to FROM system_statutory_standards'
    . ' ORDER BY standard_type_code,effective_from'
)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($allStandards as $standard) {
    foreach (array_filter([$standard['effective_from'], $standard['effective_to']]) as $boundaryDate) {
        try {
            $resolved = $resolver->resolve((string) $standard['standard_type_code'], (string) $boundaryDate);
            $assert(($resolved['id'] ?? '') === $standard['id'], 'Resolver 경계일 결과 오류: '
                . $standard['standard_type_code'] . ' ' . $boundaryDate);
        } catch (Throwable $exception) {
            $failures[] = 'Resolver 경계일 조회 실패: ' . $standard['standard_type_code'] . ' ' . $boundaryDate;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo json_encode([
    'success' => true,
    'active_type_count' => $activeTypeCount,
    'employment_period_count' => count($employmentRows),
    'income_tax_table_period_count' => count($incomeRows),
    'industrial_accident_period_count' => count($industrialRows),
    'source_count' => (int) $sourceQuality['total'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
