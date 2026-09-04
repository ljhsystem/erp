<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

$db = Core\Database::getInstance()->getConnection();
$model = new App\Models\System\StatutoryStandardModel($db);
$service = new App\Services\System\StatutoryStandardService($db);
$serviceOptions = $service->options()['data'] ?? [];
$valueDataAudit = $db->query(
    "SELECT COUNT(*) total_count,"
    . " SUM(CASE WHEN value_data IS NULL OR TRIM(value_data)='' THEN 1 ELSE 0 END) empty_count,"
    . " SUM(CASE WHEN NOT JSON_VALID(value_data) THEN 1 ELSE 0 END) invalid_json_count,"
    . " SUM(CASE WHEN JSON_TYPE(value_data)<>'OBJECT' THEN 1 ELSE 0 END) non_object_count"
    . ' FROM system_statutory_standards'
)->fetch(PDO::FETCH_ASSOC) ?: [];
$metaColumns = (new App\Services\System\DataTableColumnMetaService($db))->columnsForDomain('statutory-standard');
$metaByKey = [];
foreach ($metaColumns as $metaColumn) $metaByKey[(string)$metaColumn['key']] = $metaColumn;
$expectedLabels = [
    'policy_component_code'=>'정책 구성요소',
    'employment_type_code'=>'고용형태',
    'work_scope_code'=>'업무 Scope',
    'additional_dimension_data'=>'추가 차원정보',
    'additional_dimension_key'=>'추가 차원키',
    'value_summary'=>'기준값',
    'period_status'=>'적용상태',
];
foreach ($expectedLabels as $key=>$label) {
    if (($metaByKey[$key]['label'] ?? null) !== $label) throw new RuntimeException($key . ' 공용 metadata 표시명이 다릅니다.');
}
if (($metaByKey['period_status']['source_type'] ?? null) !== 'VIRTUAL') throw new RuntimeException('적용상태 가상컬럼 계약이 다릅니다.');
$page = $model->page([
    'start'=>0,
    'length'=>1,
    'filters'=>'[]',
    'search'=>['value'=>''],
]);
$row = $page['rows'][0] ?? [];
$servicePage = $service->list(['start'=>0,'length'=>200,'draw'=>1,'filters'=>'[]','search'=>['value'=>'']]);
$searchColumns = [
    ['data'=>'__select','name'=>''],
    ['data'=>'__reorder','name'=>''],
    ['data'=>'sort_no','name'=>'sort_no'],
    ['data'=>'standard_combination_name','name'=>'standard_type_code'],
    ['data'=>'effective_from','name'=>'effective_from'],
    ['data'=>'effective_to','name'=>'effective_to'],
    ['data'=>'value_summary','name'=>'value_summary'],
];
$searchResults = [];
foreach (['국민연금', 'NATIONAL_PENSION', '보험료'] as $searchValue) {
    $searchPage = $service->list([
        'start'=>0,
        'length'=>50,
        'draw'=>1,
        'filters'=>'[]',
        'search'=>['value'=>$searchValue],
        'columns'=>$searchColumns,
        'order'=>[['column'=>3,'dir'=>'asc']],
    ]);
    $searchResults[$searchValue] = [
        'success'=>$searchPage['success'] ?? false,
        'records_filtered'=>$searchPage['recordsFiltered'] ?? null,
        'row_count'=>count($searchPage['data'] ?? []),
    ];
}
$combinationNames = [];
$valueSummaries = [];
foreach ($servicePage['data'] as $serviceRow) {
    $valueSummaries[(string)$serviceRow['id']] = (string)($serviceRow['value_summary'] ?? '');
    if (($serviceRow['policy_component_code'] ?? null) !== null) {
        $combinationNames[(string)$serviceRow['id']] = (string)($serviceRow['standard_combination_name'] ?? '');
    }
}
$templateService = new App\Services\System\StatutoryStandardTemplateService($db);
$supportedDimensionTemplates = [];
foreach (['NATIONAL_PENSION', 'HEALTH_INSURANCE', 'LONG_TERM_CARE'] as $insuranceType) {
    foreach ([
        ['PREMIUM', 'ALL', 'ALL'],
        ['ELIGIBILITY', 'REGULAR', 'HEAD_OFFICE'],
        ['ELIGIBILITY', 'DAILY', 'HEAD_OFFICE'],
        ['ELIGIBILITY', 'DAILY', 'CONSTRUCTION_SITE'],
    ] as [$component, $employmentType, $workScope]) {
        $template = $templateService->find($insuranceType, $component, $employmentType, $workScope);
        $supportedDimensionTemplates[] = [
            'insurance_type' => $insuranceType,
            'component' => $component,
            'employment_type' => $employmentType,
            'work_scope' => $workScope,
            'field_count' => count((array)($template['fields'] ?? [])),
        ];
    }
}
$unsupportedBlocked = false;
try {
    $templateService->find(
        'NATIONAL_PENSION', 'ELIGIBILITY', 'REGULAR', 'CONSTRUCTION_SITE'
    );
} catch (InvalidArgumentException) {
    $unsupportedBlocked = true;
}
if (!$unsupportedBlocked || in_array('', $combinationNames, true)) {
    throw new RuntimeException('기관별 Header 조합 표시 또는 서버 차단 계약이 올바르지 않습니다.');
}
if ($valueSummaries === [] || in_array('', $valueSummaries, true)) {
    throw new RuntimeException('법정기준 목록 기준값 Projection이 누락됐습니다.');
}
echo json_encode([
    'read_only'=>true,
    'database'=>$db->query('SELECT DATABASE()')->fetchColumn(),
    'value_data_audit'=>$valueDataAudit,
    'rows'=>count($page['rows']),
    'total'=>$page['total'],
    'dimension_projection'=>[
        'policy_component_code'=>$row['policy_component_code'] ?? null,
        'employment_type_code'=>$row['employment_type_code'] ?? null,
        'work_scope_code'=>$row['work_scope_code'] ?? null,
    ],
    'insurance_combination_name_count'=>count($combinationNames),
    'insurance_combination_name_samples'=>array_slice(array_values(array_unique($combinationNames)), 0, 8),
    'value_summary_count'=>count($valueSummaries),
    'value_summary_samples'=>array_slice(array_values(array_unique($valueSummaries)), 0, 8),
    'global_search_results'=>$searchResults,
    'unsupported_combination_blocked'=>$unsupportedBlocked,
    'supported_dimension_templates'=>$supportedDimensionTemplates,
    'table_settings_labels'=>array_map(static fn(string $key): string => (string)$metaByKey[$key]['label'], array_keys($expectedLabels)),
    'period_status_source_type'=>$metaByKey['period_status']['source_type'],
    'period_status_options'=>$serviceOptions['periodStatuses'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
