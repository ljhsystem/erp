<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'regular_view' => $root . '/app/views/institution/regular-employment-income/index.php',
    'daily_view' => $root . '/app/views/institution/daily-employment-income/index.php',
    'business_view' => $root . '/app/views/institution/business-income/index.php',
    'regular_js' => $root . '/public/assets/js/pages/institution/regular-employment-income/index.js',
    'daily_js' => $root . '/public/assets/js/pages/institution/daily-employment-income/index.js',
    'business_js' => $root . '/public/assets/js/pages/institution/business-income/index.js',
];

$sources = [];
foreach ($files as $key => $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "FAIL: {$file} 파일을 읽을 수 없습니다.\n");
        exit(1);
    }
    $sources[$key] = $content;
}

$checks = [
    '일용 안내영역은 문서 헤더 다음에 배치' => preg_match('/income-document-card[\s\S]*?<\/section>\s*<div id="dailyIncomeCalculationReadiness"/', $sources['daily_view']) === 1,
    '사업소득 안내영역은 문서 헤더 다음에 배치' => preg_match('/income-document-card[\s\S]*?<\/section>\s*<div id="businessIncomeCalculationGuidance"/', $sources['business_view']) === 1,
    '상용 안내영역은 문서 헤더 다음에 동적 배치' => str_contains($sources['regular_js'], "form.querySelector('.income-document-card').after(calculationGuidance)"),
    '상용 계산 확인사항은 직원별 사유를 표시' => str_contains($sources['regular_js'], 'function renderCalculationGuidance()')
        && str_contains($sources['regular_js'], 'item.calculation_message'),
    '일용 자동계산 오류는 상단 안내영역에 표시' => str_contains($sources['daily_js'], 'const calculationErrors = workGroups.flatMap')
        && str_contains($sources['daily_js'], 'renderCalculationReadiness();'),
    '사업소득 계산 누락사유를 항목별 표시' => str_contains($sources['business_js'], 'function calculationMissingReasons()')
        && str_contains($sources['business_js'], '자동계산 오류:'),
    '사업소득 하단 집계에서 안내문 분리' => !str_contains($sources['business_js'], "const notice=isIncomeWithholdingDate"),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed === [] ? 0 : 1);
