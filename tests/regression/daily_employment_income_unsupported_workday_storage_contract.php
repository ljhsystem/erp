<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$model = (string) file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$excel = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeExcelService.php');
$ui = (string) file_get_contents($root . '/public/assets/js/pages/institution/daily-employment-income/worker-cards.js');
$migration = (string) file_get_contents($root . '/app/migrations/20260828_02_add_daily_income_non_taxable_reason.up.sql');
$design = (string) file_get_contents($root . '/docs/projects/DailyEmploymentIncomeWorkdayNonTaxableStorageContract20260827.md');

$checks = [
    '비과세증감은 적용사유만 필수검증한다' => str_contains($service, '비과세증감에는 비과세 적용사유가 필요합니다.')
        && str_contains($model, "'non_taxable_reason' => \$day['non_taxable_reason'] ?? null"),
    '비과세 근거자료 문자열은 UI와 Excel 계약에서 제거한다' => !str_contains($ui, "key: 'non_taxable_evidence'")
        && !str_contains($excel, 'evidence_reference')
        && !str_contains($excel, 'non_taxable_evidence'),
    '비과세 적용사유 물리컬럼과 필수 CHECK를 추가한다' => str_contains($migration, 'non_taxable_reason VARCHAR(500) NULL')
        && str_contains($migration, 'ck_daily_workday_non_taxable_reason_required'),
    '실제 근로시간은 분 단위 정수 SSOT로 설계한다' => str_contains($design, 'actual_work_minutes SMALLINT UNSIGNED NULL'),
    '실제근로시간 Migration 계약을 명시한다' => str_contains($design, '`_18_add_daily_income_actual_work_minutes`'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ', $label, PHP_EOL;
}
