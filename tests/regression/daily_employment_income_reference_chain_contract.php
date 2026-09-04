<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = file_get_contents($root . '/app/migrations/20260827_08_add_daily_income_business_unit_chain.up.sql');
$model = file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$service = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$page = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$grid = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');

$assertions = [
    '팀은 사업구분을 소유한다' => str_contains($migration, 'ALTER TABLE system_work_teams') && str_contains($migration, 'ADD COLUMN business_unit'),
    '기존 팀은 전문건설업으로 백필한다' => str_contains($migration, "SET business_unit = 'CONSTRUCTION'"),
    'Group은 사업구분을 보존한다' => str_contains($page, 'business_unit: group.business_unit'),
    '서버는 사업구분과 프로젝트 팀의 일치를 검증한다' => str_contains($service, 'assertGroupReferences'),
    '활성 프로젝트와 팀 전체 목록을 제공한다' => str_contains($model, "'projects' =>")
        && str_contains($model, "'work_teams' =>"),
    '거래처 유형으로 작업자를 누락시키지 않는다' => !str_contains($model, "client_type='DAILY_WORKER'"),
    '화면 입력은 사업구분 프로젝트 팀 그룹 작업내용을 항상 순서대로 표시한다' => strpos($page, "addField('사업구분 *'") < strpos($page, "addField(policy?.requires_project")
        && strpos($page, "addField(policy?.requires_project") < strpos($page, "addField(policy?.requires_work_team")
        && strpos($page, "addField(policy?.requires_work_team") < strpos($page, "addField('그룹 작업내용 *'")
        && !str_contains($page, 'projectField.hidden') && !str_contains($page, 'teamField.hidden')
        && str_contains($grid, "'작업자 *'"),
    '고정 근무범위 UI를 제거했다' => !str_contains($page, '<span>근무범위</span>'),
];

$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo "사업구분 → 프로젝트 → 팀 → 그룹 작업내용 참조계약이 적용되었습니다.\n";
