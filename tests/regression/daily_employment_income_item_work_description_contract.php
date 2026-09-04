<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = (string) file_get_contents($root . '/app/migrations/20260827_12_move_daily_income_work_description_to_item.up.sql');
$model = (string) file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$view = (string) file_get_contents($root . '/app/views/institution/daily-employment-income/index.php');
$page = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$grid = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');
$restoreMigration = (string) file_get_contents($root . '/app/migrations/20260827_14_restore_daily_income_group_description.up.sql');

$checks = [
    'Item에 공종과 작업내용을 추가한다.' => str_contains($migration, 'ADD COLUMN work_type_code') && str_contains($migration, 'ADD COLUMN work_description'),
    'Group 작업내용만 신규 Migration으로 복원한다.' => str_contains($restoreMigration, 'ADD COLUMN work_description') && !str_contains($restoreMigration, 'document_number'),
    '저장 Model은 Item에 공종과 작업내용을 기록한다.' => str_contains($model, "'work_type_code' => \$item['work_type_code']") && str_contains($model, "'work_description' => \$item['work_description']"),
    'Service는 Group과 작업자 작업내용을 별도로 검증한다.' => str_contains($service, "\$item['work_description']") && str_contains($service, "\$group['work_description']"),
    '작업자카드에서 공종 다음에 작업내용을 표시한다.' => strpos($grid, "'공종 *'") < strpos($grid, "'<span>작업내용 *</span>'"),
    'Modal과 Payload에 문서번호가 없다.' => !str_contains($view, 'document_number') && !str_contains($view, 'dailyIncomeDocumentNumber') && !str_contains($view, '문서번호') && !str_contains($page, 'document_number') && !str_contains($page, 'dailyIncomeDocumentNumber'),
];

foreach ($checks as $message => $passed) {
    if (!$passed) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ', $message, PHP_EOL;
}
