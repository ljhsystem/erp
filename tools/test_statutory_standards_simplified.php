<?php

declare(strict_types=1);

use App\Services\Auth\AuthSessionService;
use App\Services\System\StatutoryStandardService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$auth = new AuthSessionService();
$auth->getAuthState();
$userId = (string) $db->query('SELECT id FROM auth_users ORDER BY created_at LIMIT 1')->fetchColumn();
if ($userId === '') {
    throw new RuntimeException('테스트 Actor로 사용할 사용자가 없습니다.');
}
$_SESSION['user'] = ['id' => $userId, 'username' => 'statutory-test'];
$_SESSION['auth_state'] = ['user_id' => $userId, 'status' => AuthSessionService::STATUS_NORMAL];

$service = new StatutoryStandardService($db);
$options = $service->options()['data'];
$types = array_column($options['standardTypes'], 'code');
if (!in_array('MINIMUM_WAGE', $types, true) || !in_array('EMPLOYMENT_INCOME_TAX_TABLE', $types, true)) {
    throw new RuntimeException('법정기준 핵심 종류 계약이 올바르지 않습니다.');
}
$minimumWageTemplate = null;
$industrialAccidentTemplate = null;
$incomeTaxTableTemplate = null;
$dailyWorkerIncomeTaxTemplate = null;
$corporateTaxTemplate = null;
$corporateLocalIncomeTaxTemplate = null;
$nationalPensionTemplate = null;
$longTermCareTemplate = null;
$localIncomeTaxTemplate = null;
foreach ($options['standardTypes'] as $template) {
    if (($template['code'] ?? '') === 'MINIMUM_WAGE') {
        $minimumWageTemplate = $template;
    }
    if (($template['code'] ?? '') === 'INDUSTRIAL_ACCIDENT') {
        $industrialAccidentTemplate = $template;
    }
    if (($template['code'] ?? '') === 'EMPLOYMENT_INCOME_TAX_TABLE') {
        $incomeTaxTableTemplate = $template;
    }
    if (($template['code'] ?? '') === 'DAILY_WORKER_INCOME_TAX') {
        $dailyWorkerIncomeTaxTemplate = $template;
    }
    if (($template['code'] ?? '') === 'CORPORATE_TAX') {
        $corporateTaxTemplate = $template;
    }
    if (($template['code'] ?? '') === 'CORPORATE_LOCAL_INCOME_TAX') {
        $corporateLocalIncomeTaxTemplate = $template;
    }
    if (($template['code'] ?? '') === 'NATIONAL_PENSION') $nationalPensionTemplate = $template;
    if (($template['code'] ?? '') === 'LONG_TERM_CARE') $longTermCareTemplate = $template;
    if (($template['code'] ?? '') === 'LOCAL_INCOME_TAX_WITHHOLDING') $localIncomeTaxTemplate = $template;
}
foreach ([$corporateTaxTemplate, $corporateLocalIncomeTaxTemplate] as $corporateBracketTemplate) {
    $field = $corporateBracketTemplate['fields'][0] ?? [];
    if (($field['code'] ?? '') !== 'tax_brackets'
        || ($field['type'] ?? '') !== 'bracket'
        || ($field['ui']['allow_paste'] ?? true) !== false
        || empty($field['ui']['collapsible'])
        || empty($field['ui']['default_expanded'])) {
        throw new RuntimeException('법인세 계열 구간 카드 계약이 올바르지 않습니다.');
    }
}
if (($incomeTaxTableTemplate['fields'][0]['code'] ?? '') !== 'table'
    || ($incomeTaxTableTemplate['fields'][0]['type'] ?? '') !== 'matrix'
    || ($incomeTaxTableTemplate['fields'][0]['dynamic_dimension']['key'] ?? '') !== 'dependent_counts'
    || ($incomeTaxTableTemplate['fields'][0]['dynamic_dimension']['row_map_key'] ?? '') !== 'tax_by_dependents'
    || ($incomeTaxTableTemplate['fields'][0]['object_storage']['rows_key'] ?? '') !== 'rows'
    || empty($incomeTaxTableTemplate['fields'][0]['dynamic_dimension']['column']['dash_as_zero'])
    || empty(array_column($incomeTaxTableTemplate['fields'][0]['columns'] ?? [], null, 'code')['salary_to']['nullable'])
    || empty($incomeTaxTableTemplate['fields'][0]['ui']['default_expanded'])
    || empty($incomeTaxTableTemplate['fields'][0]['ui']['allow_paste'])
    || ($incomeTaxTableTemplate['fields'][1]['code'] ?? '') !== 'excess_rules'
    || !empty($incomeTaxTableTemplate['fields'][1]['required'])
    || ($incomeTaxTableTemplate['fields'][1]['ui']['allow_paste'] ?? true) !== false
    || !empty($incomeTaxTableTemplate['fields'][1]['ui']['default_expanded'])
    || empty(array_column($incomeTaxTableTemplate['fields'][1]['columns'] ?? [], null, 'code')['base_tax_reference']['hidden'])
    || !empty($incomeTaxTableTemplate['fields'][2]['required'])
    || ($incomeTaxTableTemplate['fields'][2]['ui']['allow_paste'] ?? true) !== false
    || empty(array_column($incomeTaxTableTemplate['fields'][2]['columns'] ?? [], null, 'code')['rule_type']['hidden'])) {
    throw new RuntimeException('EMPLOYMENT_INCOME_TAX_TABLE Matrix 템플릿 계약이 올바르지 않습니다.');
}
$excessColumns = array_column($incomeTaxTableTemplate['fields'][1]['columns'] ?? [], null, 'code');
foreach (['excess_base_rate', 'tax_rate'] as $rateColumn) {
    if (($excessColumns[$rateColumn]['type'] ?? '') !== 'rate'
        || (float) ($excessColumns[$rateColumn]['min'] ?? -1) !== 0.0
        || (float) ($excessColumns[$rateColumn]['max'] ?? -1) !== 1.0) {
        throw new RuntimeException('초과계산기준 rate 저장단위 Metadata 계약이 올바르지 않습니다.');
    }
}
if (($industrialAccidentTemplate['fields'][0]['code'] ?? '') !== 'industry_rates'
    || ($industrialAccidentTemplate['fields'][0]['type'] ?? '') !== 'matrix'
    || array_column($industrialAccidentTemplate['fields'][0]['columns'] ?? [], 'code') !== ['industry_name', 'employer_rate']
    || empty($industrialAccidentTemplate['fields'][0]['columns'][0]['key_part'])
    || ($industrialAccidentTemplate['fields'][0]['columns'][1]['type'] ?? '') !== 'rate'
    || empty($industrialAccidentTemplate['preserve_schema_in_value'])) {
    throw new RuntimeException('INDUSTRIAL_ACCIDENT 사업종류별 보험료율 템플릿 계약이 올바르지 않습니다.');
}
if (array_column($dailyWorkerIncomeTaxTemplate['fields'] ?? [], 'code') !== [
        'daily_income_deduction', 'daily_income_tax_rate', 'daily_income_tax_credit_rate',
    ]
    || array_column($dailyWorkerIncomeTaxTemplate['fields'] ?? [], 'name') !== [
        '1일 공제액', '원천징수세율', '근로소득 세액공제율',
    ]
    || array_column($dailyWorkerIncomeTaxTemplate['calculation_policy']['fields'] ?? [], 'code') !== [
        'method', 'discard_below_unit', 'stage', 'base_value_code', 'aggregation_unit',
        'threshold', 'threshold_comparison', 'workplace_scope', 'application_order',
    ]
    || ($dailyWorkerIncomeTaxTemplate['calculation_policy']['fields'][1]['type'] ?? '') !== 'number'
    || ($dailyWorkerIncomeTaxTemplate['calculation_policy']['fields'][1]['unit_label'] ?? '') !== '원'
    ) {
    throw new RuntimeException('DAILY_WORKER_INCOME_TAX 템플릿 계약이 올바르지 않습니다.');
}
$policyTemplateCodes = array_map(static fn(?array $template): array =>
    array_column($template['calculation_policy']['fields'] ?? [], 'code'),
    [$nationalPensionTemplate, $incomeTaxTableTemplate, $longTermCareTemplate, $localIncomeTaxTemplate]
);
if ($policyTemplateCodes[0] !== ['method','discard_below_unit','stage','base_value_code','aggregation_unit','application_order']
    || !in_array('threshold', $policyTemplateCodes[1], true)
    || $policyTemplateCodes[2] !== ['stage','base_value_code','aggregation_unit','application_order']
    || !in_array('base_value_code', $policyTemplateCodes[3], true)) {
    throw new RuntimeException('법정기준 계산단계·집계단위 공용계약이 올바르지 않습니다.');
}
if (($minimumWageTemplate['fields'][0]['code'] ?? '') !== 'hourly_wage'
    || ($minimumWageTemplate['fields'][0]['name'] ?? '') !== '시간급 최저임금 금액'
    || empty($minimumWageTemplate['fields'][0]['required'])
    || ($minimumWageTemplate['calculation_policy']['fields'] ?? null) !== []) {
    throw new RuntimeException('MINIMUM_WAGE 입력 템플릿 조회·파싱 계약이 올바르지 않습니다.');
}
$standardMeta = array_column($options['standardColumns'], null, 'key');
$sourceMeta = array_column($options['sourceColumns'], null, 'key');
$publishedAtTables = $db->query(
    "SELECT TABLE_NAME FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='published_at'"
    . " AND TABLE_NAME IN ('system_statutory_standards','system_statutory_standard_sources')"
)->fetchAll(PDO::FETCH_COLUMN) ?: [];
$standardColumns = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards'"
)->fetchAll(PDO::FETCH_COLUMN) ?: [];
$standardMetadataOrder = array_column($options['standardColumns'], 'key');
$standardIndexes = $db->query(
    "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS"
    . " WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='system_statutory_standards'"
)->fetchAll(PDO::FETCH_COLUMN) ?: [];
if (isset($standardMeta['published_at'])
    || !isset($sourceMeta['published_at'])
    || in_array('system_statutory_standards', $publishedAtTables, true)
    || !in_array('system_statutory_standard_sources', $publishedAtTables, true)
    || in_array('scope_data', $standardColumns, true)
    || $standardMetadataOrder !== $standardColumns
    || in_array('uk_statutory_standard_period', $standardIndexes, true)
    || !in_array('idx_statutory_standard_resolve', $standardIndexes, true)
    || ($sourceMeta['published_at']['label'] ?? '') !== '공표일'
    || !empty($sourceMeta['published_at']['required'])
    || ($standardMeta['effective_from']['label'] ?? '') !== '적용시작일'
    || empty($standardMeta['effective_from']['required'])
    || !empty($standardMeta['effective_to']['required'])
    || ($sourceMeta['source_name']['label'] ?? '') !== '자료명'
    || empty($sourceMeta['source_name']['required'])
    || !empty($sourceMeta['organization_name']['required'])) {
    throw new RuntimeException('DB Metadata의 label/required 계약이 올바르지 않습니다.');
}

$db->beginTransaction();
try {
    foreach ([
        'EMPLOYMENT_INCOME_TAX_TABLE' => '2190-12-31',
        'INDUSTRIAL_ACCIDENT' => '2195-12-31',
    ] as $currentType => $testEndDate) {
        $currentStatement = $db->prepare(
            'SELECT id FROM system_statutory_standards WHERE standard_type_code=:type AND effective_to IS NULL LIMIT 1'
        );
        $currentStatement->execute([':type' => $currentType]);
        $currentId = (string) $currentStatement->fetchColumn();
        if ($currentId === '') {
            throw new RuntimeException($currentType . ' 현행 기준을 찾을 수 없습니다.');
        }
        $currentDetail = $service->detail($currentId)['data'];
        $service->save([
            'id' => $currentId,
            'standard_type_code' => $currentType,
            'effective_from' => $currentDetail['effective_from'],
            'effective_to' => $testEndDate,
            'value_data' => $currentDetail['value_data'],
            'note' => $currentDetail['note'] ?? '',
            'sources' => $currentDetail['sources'] ?? [],
        ]);
    }
    $created = $service->save([
        'standard_type_code' => 'MINIMUM_WAGE', 'effective_from' => '2197-01-01',
        'effective_to' => '2197-12-31',
        'value_data' => ['hourly_wage' => 4860], 'note' => '트랜잭션 테스트',
        'sources' => [['source_name' => '최저임금 고시', 'organization_name' => '고용노동부',
            'law_name' => '최저임금법', 'notice_no' => '테스트', 'published_at' => '2012-08-01',
            'source_url' => 'https://example.invalid/statutory-test', 'note' => '롤백 대상']],
    ])['data']['id'];
    $detail = $service->detail($created)['data'];
    if (array_key_exists('published_at', $detail)
        || (float) $detail['value_data']['hourly_wage'] !== 4860.0
        || count($detail['sources']) !== 1
        || ($detail['sources'][0]['published_at'] ?? null) !== '2012-08-01') {
        throw new RuntimeException('신규등록 또는 근거자료 등록 검증에 실패했습니다.');
    }
    $source = $detail['sources'][0];
    $service->save([
        'id' => $created, 'standard_type_code' => 'MINIMUM_WAGE', 'effective_from' => '2197-01-01',
        'effective_to' => '2197-12-31',
        'value_data' => ['hourly_wage' => 4860], 'note' => '수정 확인',
        'sources' => [['id' => $source['id'], 'source_name' => '최저임금 고시 수정',
            'organization_name' => '고용노동부', 'law_name' => '최저임금법', 'notice_no' => '테스트 수정',
            'published_at' => '2012-08-01', 'source_url' => 'https://example.invalid/statutory-test-updated',
            'note' => '수정 확인']],
    ]);
    $updated = $service->detail($created)['data'];
    if ($updated['note'] !== '수정 확인' || $updated['sources'][0]['source_name'] !== '최저임금 고시 수정') {
        throw new RuntimeException('수정 또는 근거자료 수정 검증에 실패했습니다.');
    }
    $resolved = $service->resolve(['standard_type_code' => 'MINIMUM_WAGE', 'date' => '2197-06-01'])['data'];
    if ((string) $resolved['id'] !== $created) {
        throw new RuntimeException('Resolver 검증에 실패했습니다.');
    }
    $overlapBlocked = false;
    try {
        $service->save(['standard_type_code' => 'MINIMUM_WAGE', 'effective_from' => '2197-06-01',
            'effective_to' => '2198-05-31', 'value_data' => ['hourly_wage' => 5210],
            'sources' => []]);
    } catch (InvalidArgumentException $exception) {
        $overlapBlocked = str_contains($exception->getMessage(), '중복됩니다');
    }
    if (!$overlapBlocked) {
        throw new RuntimeException('기간중복 Validation 검증에 실패했습니다.');
    }
    $industrialIds = [];
    $dailyWorkerId = $service->save([
        'standard_type_code' => 'DAILY_WORKER_INCOME_TAX',
        'effective_from' => '1900-01-01', 'effective_to' => '1900-12-31',
        'value_data' => ['daily_income_deduction' => 100000,
            'daily_income_tax_rate' => 0.06, 'daily_income_tax_credit_rate' => 0.55,
            'calculation_policy' => ['method' => 'TRUNCATE', 'discard_below_unit' => 1,
                'stage' => 'AFTER_TAX_CREDIT', 'base_value_code' => 'DAILY_TAX_AFTER_CREDIT',
                'aggregation_unit' => 'WITHHOLDING_AGENT_RECIPIENT_WORKDAY_PAYMENT', 'threshold' => 1000,
                'threshold_comparison' => 'LESS_THAN', 'workplace_scope' => 'EACH_WORKPLACE', 'application_order' => 1]],
        'sources' => [],
    ])['data']['id'];
    $dailyWorkerDetail = $service->detail($dailyWorkerId)['data'];
    if (($dailyWorkerDetail['value_data']['calculation_policy']['method'] ?? '') !== 'TRUNCATE'
        || (float) ($dailyWorkerDetail['value_data']['calculation_policy']['discard_below_unit'] ?? 0) !== 1.0
        || ($dailyWorkerDetail['value_data']['_schema']['calculation_policy']['fields'][0]['code'] ?? '') !== 'method') {
        throw new RuntimeException('일용근로소득 계산정책 계약이 올바르지 않습니다.');
    }
    $service->save([
        'id' => $dailyWorkerId,
        'standard_type_code' => 'DAILY_WORKER_INCOME_TAX',
        'effective_from' => '1900-01-01', 'effective_to' => '1900-12-31',
        'value_data' => $dailyWorkerDetail['value_data'],
        'sources' => [],
    ]);
    $dailyWorkerUpdated = $service->detail($dailyWorkerId)['data']['value_data'] ?? [];
    if (($dailyWorkerUpdated['calculation_policy']['threshold'] ?? null) !== 1000
        || ($dailyWorkerUpdated['_schema']['calculation_policy']['fields'][5]['code'] ?? '') !== 'threshold') {
        throw new RuntimeException('일용근로소득 계산정책 수정·재조회 또는 Schema 보존에 실패했습니다.');
    }
    $dailyWorkerResolved = $service->resolve([
        'standard_type_code' => 'DAILY_WORKER_INCOME_TAX', 'date' => '1900-06-01',
    ])['data'];
    if ((string) ($dailyWorkerResolved['id'] ?? '') !== $dailyWorkerId) {
        throw new RuntimeException('일용근로소득 기준 Resolver 검증에 실패했습니다.');
    }
    $matrixId = $service->save([
        'standard_type_code' => 'EMPLOYMENT_INCOME_TAX_TABLE',
        'effective_from' => '2195-01-01',
        'effective_to' => '2195-12-31',
        'value_data' => ['table' => ['salary_unit' => 'KRW', 'dependent_counts' => range(1, 11), 'rows' => [[
            'salary_from' => 9995000, 'salary_to' => 10000000,
            'tax_by_dependents' => array_combine(range(1, 11), range(100, 0, -10)),
        ], [
            'salary_from' => 10000000, 'salary_to' => null,
            'tax_by_dependents' => array_fill_keys(range(1, 11), 0),
        ]]], 'excess_rules' => [
            ['salary_from' => 10000000, 'salary_to' => 14000000, 'base_salary' => 10000000, 'excess_base_rate' => 0.98, 'tax_rate' => 0.35, 'fixed_addition' => 0],
            ['salary_from' => 14000000, 'salary_to' => 28000000, 'base_salary' => 14000000, 'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.98, 'tax_rate' => 0.38, 'fixed_addition' => 25000],
            ['salary_from' => 28000000, 'salary_to' => 30000000, 'base_salary' => 28000000, 'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.98, 'tax_rate' => 0.38, 'fixed_addition' => 5345000],
            ['salary_from' => 30000000, 'salary_to' => 45000000, 'base_salary' => 30000000, 'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.98, 'tax_rate' => 0.40, 'fixed_addition' => 6105000],
            ['salary_from' => 45000000, 'salary_to' => 87000000, 'base_salary' => 45000000, 'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.98, 'tax_rate' => 0.42, 'fixed_addition' => 12105000],
            ['salary_from' => 87000000, 'salary_to' => null, 'base_salary' => 87000000, 'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.98, 'tax_rate' => 0.45, 'fixed_addition' => 29745000],
        ], 'adjustment_rules' => [
            ['eligible_age_from' => 8, 'eligible_age_to' => 20,
                'child_count_from' => 1, 'child_count_to' => 1, 'fixed_deduction' => 12500, 'additional_per_child' => 0, 'minimum_tax' => 0],
            ['rule_type' => 'CHILD_COUNT_DEDUCTION', 'eligible_age_from' => 8, 'eligible_age_to' => 20,
                'child_count_from' => 2, 'child_count_to' => 2, 'fixed_deduction' => 29160, 'additional_per_child' => 0, 'minimum_tax' => 0],
            ['rule_type' => 'CHILD_COUNT_DEDUCTION', 'eligible_age_from' => 8, 'eligible_age_to' => 20,
                'child_count_from' => 3, 'child_count_to' => null, 'fixed_deduction' => 29160, 'additional_per_child' => 25000, 'minimum_tax' => 0],
        ]],
        'sources' => [],
    ])['data']['id'];
    $matrixValue = $service->detail($matrixId)['data']['value_data'] ?? [];
    $matrixDetail = $matrixValue['table']['rows'] ?? [];
    if (count($matrixDetail) !== 2 || count($matrixValue['excess_rules'] ?? []) !== 6
        || (float) ($matrixDetail[0]['tax_by_dependents']['11'] ?? -1) !== 0.0
        || !array_key_exists('salary_to', $matrixDetail[1]) || $matrixDetail[1]['salary_to'] !== null
        || ($matrixValue['excess_rules'][0]['base_tax_reference'] ?? '') !== 'TABLE'
        || ($matrixValue['adjustment_rules'][0]['rule_type'] ?? '') !== 'CHILD_COUNT_DEDUCTION'
        || ($matrixValue['_schema']['fields'][0]['dynamic_dimension']['key'] ?? '') !== 'dependent_counts') {
        throw new RuntimeException('Matrix 저장·재조회 검증에 실패했습니다.');
    }
    $emptyRulesMatrixId = $service->save([
        'standard_type_code' => 'EMPLOYMENT_INCOME_TAX_TABLE',
        'effective_from' => '2191-01-01', 'effective_to' => '2191-12-31',
        'value_data' => ['table' => ['salary_unit' => 'KRW', 'dependent_counts' => range(1, 11), 'rows' => [[
            'salary_from' => 0, 'salary_to' => null,
            'tax_by_dependents' => array_fill_keys(range(1, 11), 0),
        ]]], 'excess_rules' => [], 'adjustment_rules' => []],
        'sources' => [],
    ])['data']['id'];
    $emptyRulesValue = $service->detail($emptyRulesMatrixId)['data']['value_data'] ?? [];
    if (($emptyRulesValue['excess_rules'] ?? null) !== [] || ($emptyRulesValue['adjustment_rules'] ?? null) !== []) {
        throw new RuntimeException('선택 규칙 0건 저장·재조회 검증에 실패했습니다.');
    }
    $matrixInvalidBlocked = false;
    try {
        $service->save([
            'standard_type_code' => 'EMPLOYMENT_INCOME_TAX_TABLE',
            'effective_from' => '2194-01-01', 'effective_to' => '2194-12-31',
            'value_data' => ['table' => ['salary_unit' => 'KRW', 'dependent_counts' => range(1, 11), 'rows' => [[
                'salary_from' => 1020000, 'salary_to' => 1010000,
                'tax_by_dependents' => array_fill_keys(range(1, 11), 0),
            ]]],
                'excess_rules' => []], 'sources' => [],
        ]);
    } catch (InvalidArgumentException $exception) {
        $matrixInvalidBlocked = str_contains($exception->getMessage(), '시작값');
    }
    if (!$matrixInvalidBlocked) throw new RuntimeException('Matrix 구간 역전 Validation 검증에 실패했습니다.');
    $historicalMatrixId = $service->save([
        'standard_type_code' => 'EMPLOYMENT_INCOME_TAX_TABLE',
        'effective_from' => '2192-01-01', 'effective_to' => '2192-12-31',
        'value_data' => ['table' => ['salary_unit' => 'KRW', 'dependent_counts' => range(1, 11), 'rows' => [[
            'salary_from' => 9995000, 'salary_to' => 10000000,
            'tax_by_dependents' => array_fill_keys(range(1, 11), 0),
        ]]], 'excess_rules' => [
            ['salary_from' => 10000000, 'salary_to' => 28000000, 'base_salary' => 10000000,
                'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.95, 'tax_rate' => 0.35, 'fixed_addition' => 0],
            ['salary_from' => 28000000, 'salary_to' => null, 'base_salary' => 10000000,
                'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.95, 'tax_rate' => 0.38, 'fixed_addition' => 5985000],
        ], 'adjustment_rules' => []],
        'sources' => [],
    ])['data']['id'];
    $historicalRules = $service->detail($historicalMatrixId)['data']['value_data']['excess_rules'] ?? [];
    if (count($historicalRules) !== 2
        || (float) ($historicalRules[0]['excess_base_rate'] ?? -1) !== 0.95
        || (float) ($historicalRules[0]['tax_rate'] ?? -1) !== 0.35
        || (float) ($historicalRules[1]['tax_rate'] ?? -1) !== 0.38
        || !array_key_exists('salary_to', $historicalRules[1]) || $historicalRules[1]['salary_to'] !== null
        || (float) ($historicalRules[1]['base_salary'] ?? -1) !== 10000000.0) {
        throw new RuntimeException('과거형 초과구간 저장·재조회 검증에 실패했습니다.');
    }
    for ($repeat = 0; $repeat < 2; $repeat++) {
        $historicalDetail = $service->detail($historicalMatrixId)['data'];
        $service->save([
            'id' => $historicalMatrixId,
            'standard_type_code' => 'EMPLOYMENT_INCOME_TAX_TABLE',
            'effective_from' => '2192-01-01', 'effective_to' => '2192-12-31',
            'value_data' => $historicalDetail['value_data'],
            'sources' => [],
        ]);
        $reloadedRules = $service->detail($historicalMatrixId)['data']['value_data']['excess_rules'] ?? [];
        if ((float) ($reloadedRules[0]['excess_base_rate'] ?? -1) !== 0.95
            || (float) ($reloadedRules[0]['tax_rate'] ?? -1) !== 0.35
            || (float) ($reloadedRules[1]['excess_base_rate'] ?? -1) !== 0.95
            || (float) ($reloadedRules[1]['tax_rate'] ?? -1) !== 0.38
            || !array_key_exists('salary_to', $reloadedRules[1])
            || $reloadedRules[1]['salary_to'] !== null) {
            throw new RuntimeException('초과계산기준 반복 저장·재조회 단위 계약이 변질되었습니다.');
        }
    }
    $futureRules = [];
    for ($ruleIndex = 0; $ruleIndex < 7; $ruleIndex++) {
        $futureRules[] = [
            'salary_from' => 10000000 + ($ruleIndex * 1000000),
            'salary_to' => $ruleIndex === 6 ? null : 11000000 + ($ruleIndex * 1000000),
            'base_salary' => 10000000 + ($ruleIndex * 1000000),
            'base_tax_reference' => 'TABLE', 'excess_base_rate' => 0.97,
            'tax_rate' => 0.30 + ($ruleIndex * 0.01), 'fixed_addition' => $ruleIndex * 10000,
        ];
    }
    $futureMatrixId = $service->save([
        'standard_type_code' => 'EMPLOYMENT_INCOME_TAX_TABLE',
        'effective_from' => '2193-01-01', 'effective_to' => '2193-12-31',
        'value_data' => ['table' => ['salary_unit' => 'KRW', 'dependent_counts' => range(1, 12), 'rows' => [[
            'salary_from' => 9995000, 'salary_to' => 10000000,
            'tax_by_dependents' => array_fill_keys(range(1, 12), 0),
        ]]], 'excess_rules' => $futureRules, 'adjustment_rules' => []],
        'sources' => [],
    ])['data']['id'];
    $futureValue = $service->detail($futureMatrixId)['data']['value_data'];
    if (count($futureValue['table']['dependent_counts'] ?? []) !== 12
        || count($futureValue['excess_rules'] ?? []) !== 7
        || !array_key_exists('12', $futureValue['table']['rows'][0]['tax_by_dependents'] ?? [])) {
        throw new RuntimeException('가상 미래형 저장·재조회 검증에 실패했습니다.');
    }
    $industrialIds[] = $service->save([
        'standard_type_code' => 'INDUSTRIAL_ACCIDENT',
        'effective_from' => '2196-01-01',
        'effective_to' => '2196-12-31',
        'value_data' => ['industry_rates' => [
            ['industry_name' => '제조업', 'employer_rate' => 0.01],
            ['industry_name' => '건설업', 'employer_rate' => 0.037],
        ]],
        'sources' => [],
    ])['data']['id'];
    $industrialDetail = $service->detail($industrialIds[0])['data']['value_data'];
    if (count($industrialDetail['industry_rates'] ?? []) !== 2
        || (float) ($industrialDetail['industry_rates'][1]['employer_rate'] ?? 0) !== 0.037
        || ($industrialDetail['_schema']['fields'][0]['code'] ?? '') !== 'industry_rates') {
        throw new RuntimeException('산재보험 복수 사업종류 저장·재조회 검증에 실패했습니다.');
    }
    $industrialResolved = $service->resolve([
        'standard_type_code' => 'INDUSTRIAL_ACCIDENT',
        'date' => '2196-06-01',
    ])['data'];
    if ((string) $industrialResolved['id'] !== $industrialIds[0]) {
        throw new RuntimeException('산재보험 기준일 Resolver 검증에 실패했습니다.');
    }
    $industrialOverlapBlocked = false;
    try {
        $service->save([
            'standard_type_code' => 'INDUSTRIAL_ACCIDENT',
            'effective_from' => '2196-06-01',
            'effective_to' => '2197-05-31',
            'value_data' => ['industry_rates' => [['industry_name' => '건설업', 'employer_rate' => 0.04]]],
            'sources' => [],
        ]);
    } catch (InvalidArgumentException $exception) {
        $industrialOverlapBlocked = str_contains($exception->getMessage(), '중복됩니다');
    }
    if (!$industrialOverlapBlocked) {
        throw new RuntimeException('산재보험 종류·기간 중복 검증에 실패했습니다.');
    }
    $duplicateIndustryBlocked = false;
    try {
        $service->save([
            'standard_type_code' => 'INDUSTRIAL_ACCIDENT',
            'effective_from' => '2197-01-01',
            'effective_to' => '2197-12-31',
            'value_data' => ['industry_rates' => [
                ['industry_name' => '건설업', 'employer_rate' => 0.037],
                ['industry_name' => '건설업', 'employer_rate' => 0.04],
            ]],
            'sources' => [],
        ]);
    } catch (InvalidArgumentException $exception) {
        $duplicateIndustryBlocked = str_contains($exception->getMessage(), '중복되었습니다');
    }
    if (!$duplicateIndustryBlocked) {
        throw new RuntimeException('산재보험 동일 사업종류 중복 차단 검증에 실패했습니다.');
    }
    $service->save([
        'id' => $industrialIds[0],
        'standard_type_code' => 'INDUSTRIAL_ACCIDENT',
        'effective_from' => '2196-01-01',
        'effective_to' => '2196-12-31',
        'value_data' => ['industry_rates' => [
            ['industry_name' => '건설업', 'employer_rate' => 0.04],
            ['industry_name' => '서비스업', 'employer_rate' => 0.015],
        ]],
        'sources' => [],
    ]);
    if (($service->detail($industrialIds[0])['data']['value_data']['industry_rates'][1]['industry_name'] ?? '') !== '서비스업') {
        throw new RuntimeException('산재보험 사업종류별 보험료율 수정 검증에 실패했습니다.');
    }
    $periodIds = [];
    foreach ([['2198-01-01', '2198-12-31', 5210], ['2199-01-01', '2199-12-31', 5580]] as $period) {
        $periodIds[] = $service->save([
            'standard_type_code' => 'MINIMUM_WAGE', 'effective_from' => $period[0],
            'effective_to' => $period[1],
            'value_data' => ['hourly_wage' => $period[2]], 'sources' => [],
        ])['data']['id'];
    }
    foreach ([['2197-06-01', 4860], ['2198-06-01', 5210], ['2199-06-01', 5580]] as $expected) {
        $row = $service->resolve(['standard_type_code' => 'MINIMUM_WAGE', 'date' => $expected[0]])['data'];
        if ((float) $row['value_data']['hourly_wage'] !== (float) $expected[1]) {
            throw new RuntimeException('연속 적용기간 최저임금 Resolver 검증에 실패했습니다.');
        }
    }
    $service->reorder([
        ['id' => $periodIds[0], 'newSortNo' => 2],
        ['id' => $periodIds[1], 'newSortNo' => 1],
    ]);
    if ((int) $service->detail($periodIds[0])['data']['sort_no'] !== 2) {
        throw new RuntimeException('순서 저장 검증에 실패했습니다.');
    }
    $service->save(['id' => $created, 'standard_type_code' => 'MINIMUM_WAGE',
        'effective_from' => '2197-01-01', 'effective_to' => '2197-12-31',
        'value_data' => ['hourly_wage' => 4860], 'sources' => []]);
    if ($service->detail($created)['data']['sources'] !== []) {
        throw new RuntimeException('근거자료 삭제 검증에 실패했습니다.');
    }
    $service->delete($created);
    $service->deleteMany($periodIds);
    $service->deleteMany($industrialIds);
    $service->delete($dailyWorkerId);
    $service->delete($matrixId);
    $service->delete($emptyRulesMatrixId);
    $service->delete($historicalMatrixId);
    $service->delete($futureMatrixId);
    try {
        $service->detail($created);
        throw new RuntimeException('완전삭제 검증에 실패했습니다.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === '완전삭제 검증에 실패했습니다.') {
            throw $exception;
        }
    }
    $list = $service->list(['draw' => 1, 'start' => 0, 'length' => 10, 'filters' => '[]', 'search' => ['value' => '']]);
    $result = ['types' => count($types), 'minimum_wage_field' => $minimumWageTemplate['fields'][0]['code'],
        'rounding_methods' => count($options['roundingMethods']),
        'metadata_contract' => true, 'metadata_db_order' => true,
        'standards_published_at_absent' => true, 'condition_column_absent' => true,
        'obsolete_condition_index_absent' => true, 'resolver_index_present' => true,
        'sources_published_at_present' => true, 'create' => true, 'update' => true, 'delete' => true, 'source_crud' => true,
        'minimum_wage_final_value' => 5580, 'resolver_periods' => true,
        'reorder' => true, 'bulk_delete' => true,
        'overlap_validation' => true, 'industrial_rates_create' => true,
        'industrial_rates_update' => true, 'industrial_date_resolver' => true,
        'industrial_duplicate_name_blocked' => true,
        'daily_worker_resolver' => true,
        'matrix_template' => true, 'matrix_save_restore' => true, 'matrix_validation' => true,
        'matrix_optional_rules_empty' => true,
        'historical_schema_snapshot' => true,
        'rate_metadata_ratio_contract' => true,
        'income_tax_historical_two_rules' => true, 'income_tax_2023_six_rules' => true,
        'income_tax_rate_repeat_save' => true,
        'future_12_dependents_seven_rules' => true,
        'industrial_period_overlap_validation' => true, 'list' => $list['success']];
    $db->rollBack();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}
