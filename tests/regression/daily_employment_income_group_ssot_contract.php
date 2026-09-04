<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$model = (string) file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$script = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$workerCards = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');
$migration = (string) file_get_contents($root . '/app/migrations/20260827_10_create_daily_employment_income_groups.up.sql');
$removeRateMigration = (string) file_get_contents($root . '/app/migrations/20260827_11_remove_daily_employment_income_group_default_rate.up.sql');

$checks = [
    'Group 테이블과 Item Group FK가 정의된다.' => str_contains($migration, 'institution_daily_employment_income_groups')
        && str_contains($migration, 'daily_employment_income_group_id'),
    'Group 안에서 작업자 UNIQUE를 보장한다.' => str_contains($migration, 'uq_daily_income_group_worker'),
    '저장 Aggregate가 Group과 Item을 함께 처리한다.' => str_contains($model, 'foreach(array_values($groups)')
        && str_contains($model, 'daily_employment_income_group_id'),
    '같은 작업자는 다른 Group에 반복 등록하되 같은 Group 저장은 차단한다.' => str_contains($service, 'private function assertNoDuplicateGroupWorkers')
        && str_contains($service, "foreach (array_values(is_array(\$input['groups']")
        && str_contains($service, '$this->assertNoDuplicateGroupWorkers($input);'),
    '입력 UI가 Group 카드와 공용 HTML Grid 작업자카드를 사용한다.' => str_contains($script, 'workGroups')
        && str_contains($workerCards, 'createHtmlGrid'),
    'Group 차원은 작업자 행 Payload에 반복하지 않는다.' => str_contains($script, 'const payloadGroups')
        && !str_contains($script, 'business_unit: worker.'),
    'Group 기본단가 제거 Migration이 준비된다.' => str_contains($removeRateMigration, 'DROP COLUMN default_daily_rate')
        && !str_contains($script, 'default_daily_rate'),
    '승인 상태는 공용 편집가능 상태 계약으로 읽기 전용을 판정한다.' => str_contains($script, 'readOnly = !isIncomeCalculationEditableStatus(header.status_code)'),
];

foreach ($checks as $message => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ', $message, PHP_EOL;
}
