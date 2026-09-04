<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = (string)file_get_contents($root . '/public/assets/js/pages/main/settings/statutory-standards/index.js');
$settings = (string)file_get_contents($root . '/public/assets/js/common/datatable/dataTableSettings.js');
$service = (string)file_get_contents($root . '/app/Services/System/StatutoryStandardService.php');
$templateService = (string)file_get_contents($root . '/app/Services/System/StatutoryStandardTemplateService.php');
$codeOptionService = (string)file_get_contents($root . '/app/Services/System/SystemCodeOptionService.php');

$checks = [
    '목록 API Summary 생성' => str_contains($service, '$this->valueSummary->project('),
    '목록 Summary 필드 단일 조회' => str_contains($service, '$summaryFields = $this->templates->summaryFields();'),
    '행별 상세 Template 재조회 제거' => !str_contains($service, '$rowTemplate = $this->templates->find(')
        && !str_contains($service, '$this->templates->findFrom('),
    '경량 Summary 필드 계약' => str_contains($templateService, 'public function summaryFields(): array'),
    '동일 코드옵션 요청 캐시' => str_contains($codeOptionService, 'private array $optionsCache = []')
        && str_contains($codeOptionService, "array_key_exists(\$cacheKey, \$this->optionsCache)"),
    'DataTable 기준값 직접 바인딩' => str_contains($page, "data: 'value_summary'"),
    '기준값 API 필드명 유지' => str_contains($page, "name: 'value_summary'"),
    '구 value_data 설정키 채택 선언' => str_contains($page, "settingsAliases: ['value_data']"),
    '공용 TABLE 설정키 채택 처리' => str_contains($settings, 'buildPersistedColumnKeyAliasMap')
        && str_contains($settings, 'parsed.visibleColumns = canonicalizeKeys(parsed.visibleColumns)')
        && str_contains($settings, 'parsed.columnOrder = canonicalizeKeys(parsed.columnOrder)'),
    '빈 값 대체 표시' => str_contains($page, "render: data => escapeHtml(data ?? '-')"),
    '숫자 Formatter 미사용' => !str_contains($page, "data: 'value_summary', type: 'number'"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        throw new RuntimeException($label . ' 계약이 누락됐습니다.');
    }
}

echo json_encode(['success' => true, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
