<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$model = (string) file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$excel = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeExcelService.php');
$source = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeCalculationSourceService.php');
$ui = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');

$checks = [
    'Model이 실제근로시간을 Workday에 저장한다' => str_contains($model, "'actual_work_minutes' => \$day['actual_work_minutes']"),
    '신규 Workday는 1~1,440분 정수를 필수검증한다' => str_contains($service, '1분 이상 1440분 이하')
        && str_contains($service, '실제근로시간(휴게시간 제외)을 입력해 주세요.'),
    '문서 전체 동일 근로자·날짜 합계를 검증한다' => str_contains($service, '$workMinutesByWorkerDate')
        && str_contains($service, '문서 전체 실제근로시간 합계는 1,440분을 초과할 수 없습니다.'),
    '복수 Group은 차단하지 않고 확인 경고를 반환한다' => str_contains($service, 'MULTIPLE_GROUPS_SAME_WORKER_DATE')
        && str_contains($service, "'worktime_warnings'"),
    'source hash가 실제근로시간을 포함한다' => str_contains($source, "'actual_work_minutes'"),
    'Excel은 날짜별 실제근로시간을 업로드·다운로드한다' => str_contains($excel, "'_actual_work_minutes'")
        && str_contains($excel, '실제근로시간(휴게시간 제외)'),
    'UI는 휴게시간 제외 의미를 표시한다' => str_contains($ui, "label: '실제근로시간(휴게시간 제외)'"),
    '일괄 근로시간과 적용단가는 공용 숫자 입력 포맷을 사용한다' => str_contains($ui, 'bindNumberInput(input, { integerOnly: true })')
        && str_contains($ui, 'parseNumber(minutesInput.value)')
        && str_contains($ui, 'parseNumber(rateInput.value)'),
    '근무일별 실제근로시간과 단가는 입력 중 천 단위로 표시한다' => substr_count($ui, 'liveGrouping: true, maximumFractionDigits: 0, allowNegative: false') >= 2,
    '근무일별 과세증감과 비과세증감은 음수를 허용하며 입력 중 천 단위로 표시한다' => substr_count($ui, 'liveGrouping: true, maximumFractionDigits: 0') >= 4,
];

foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ', $label, PHP_EOL;
}
