<?php

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$model = file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$meta = file_get_contents($root . '/app/Services/System/DataTableColumnMetaService.php');
$page = file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/index.js');
$migration = file_get_contents($root . '/app/migrations/20260827_06_add_daily_employment_income_counts.up.sql');

$checks = [
    str_contains($migration, "ADD COLUMN worker_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '작업자 수(거래처)'"),
    str_contains($migration, "ADD COLUMN work_team_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '소속팀 수(팀)'"),
    str_contains($migration, 'COUNT(DISTINCT i.worker_client_id)'),
    str_contains($migration, 'COUNT(DISTINCT i.work_team_id)'),
    str_contains($service, "'worker_count' => count(\$workerIds)"),
    str_contains($service, "'work_team_count' => count(\$workTeamIds)"),
    str_contains($model, "'h.worker_count', 'h.work_team_count'"),
    str_contains($page, "{ data: 'worker_count', title: '작업자 수'"),
    str_contains($page, "{ data: 'work_team_count', title: '소속팀 수'"),
    str_contains($meta, "return \$this->columnsForTable('institution_daily_employment_incomes', \$domain);"),
    !str_contains($meta, "['worker_count', '일용근로자 수', 'int', 'projection'"),
    !str_contains($meta, "['work_team_count', '작업팀 수', 'int', 'projection'"),
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "FAIL: 일용근로소득 물리 집계 컬럼 계약이 일치하지 않습니다.\n");
    exit(1);
}

echo "PASS: 일용근로소득 작업자·작업팀 수는 Item 원본 기반 Header 물리 집계입니다.\n";
