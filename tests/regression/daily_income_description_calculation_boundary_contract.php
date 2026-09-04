<?php

declare(strict_types=1);

$service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/pages/institution/daily-employment-income/index.js');

$checks = [
    '작업자 설명은 엄격 저장 경계에서만 필수' => str_contains($service, 'if ($requireDecisionReason && $workDescription === \'\')'),
    '그룹 설명은 엄격 저장 경계에서만 필수' => str_contains($service, "if(\$requireDecisionReason&&\$calculatedGroups[\$index]['work_description']==='')"),
    '계산 누락값과 문서 저장 누락값 분리' => str_contains($js, 'function calculationMissingInputs()') && str_contains($js, 'function documentMissingInputs()'),
    '자동계산 조건은 계산 누락값만 사용' => str_contains($js, '&& calculationMissingInputs().length === 0'),
    '저장 상태는 문서 누락값을 사용' => str_contains($js, 'const missingInputBlocked = documentMissingInputs().length > 0;'),
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
echo json_encode(['success' => $failed === [], 'checks' => $checks, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed === [] ? 0 : 1);
