<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = (string) file_get_contents($root . '/app/migrations/20260828_04_add_daily_income_line_adjustment_contract.up.sql');
$baselineMigration = (string) file_get_contents($root . '/app/migrations/20260827_23_create_daily_income_mariadb_compatible_baseline.up.sql');
$model = (string) file_get_contents($root . '/app/Models/Institution/DailyEmploymentIncomeModel.php');
$service = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeService.php');
$lineContract = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeLineContractService.php');
$scopeKeys = (string) file_get_contents($root . '/app/Services/Institution/DailyEmploymentIncomeScopeKeyService.php');
$codeService = (string) file_get_contents($root . '/app/Services/Institution/IncomeCalculationCodeService.php');
$checks = [
    'Scope Key와 최종 Grain UK는 Baseline이 단독 소유한다' => str_contains($baselineMigration, 'workday_scope_key VARCHAR(36) NOT NULL')
        && str_contains($baselineMigration, 'uq_daily_income_line_scope')
        && !str_contains($migration, 'ADD COLUMN workday_scope_key')
        && !str_contains($migration, 'ADD UNIQUE KEY uq_daily_income_line_scope'),
    '자동계산액과 nullable 실제 적용액을 분리한다' => str_contains($migration, 'calculated_amount DECIMAL(18,2) NULL')
        && str_contains($migration, 'final_amount DECIMAL(18,2) NULL DEFAULT NULL'),
    '조정액은 NULL 전파 Stored Generated 계약이다' => str_contains($migration, 'final_amount - calculated_amount')
        && str_contains($migration, ') STORED AFTER final_amount'),
    '차이가 있으면 Trim된 조정사유가 필요하다' => str_contains($migration, 'ck_daily_income_line_adjustment_reason_required')
        && str_contains($lineContract, '자동계산액과 다른 적용금액에는 조정사유가 필요합니다.'),
    '공제와 사용자부담 실제 적용액은 음수를 차단한다' => str_contains($migration, "line_type_code NOT IN ('DEDUCTION','EMPLOYER_BURDEN')"),
    'Generated 조정액은 Model INSERT 대상이 아니다' => !str_contains($model, "'adjustment_amount' => \$line['adjustment_amount']"),
    'Model은 Workday Scope와 원천코드 그룹을 검증한다' => str_contains($model, '$this->scopeKeys->lineKeys($itemId, $workdayId')
        && str_contains($scopeKeys, "'workday_scope_key' => \$workdayId ?? 'ITEM'")
        && str_contains($model, 'assertIdInGroup'),
    '계산 DTO는 자동계산액·원천·처리 Actor를 제공한다' => str_contains($service, "\$line['calculated_amount']")
        && str_contains($service, "\$line['processed_by'] = \$actor"),
    '공용 코드그룹은 단일 Service에서 소유한다' => str_contains($codeService, 'INCOME_STATUTORY_CALCULATION_SOURCE')
        && str_contains($codeService, 'INCOME_ACTUAL_APPLICATION_SOURCE'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('FAIL: ' . $label);
    echo 'PASS: ', $label, PHP_EOL;
}
