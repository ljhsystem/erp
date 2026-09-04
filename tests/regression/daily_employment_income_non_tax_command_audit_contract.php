<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$up = file_get_contents($root . '/app/migrations/20260827_21_add_daily_income_non_tax_command_audit.up.sql');
$down = file_get_contents($root . '/app/migrations/20260827_21_add_daily_income_non_tax_command_audit.down.sql');

$assertions = [
    '기존 Command 원장 확장' => str_contains($up, 'ALTER TABLE institution_daily_employment_income_commands'),
    '비과세 명령 CHECK' => str_contains($up, "'NON_TAX_CREATE','NON_TAX_CONFIRM','NON_TAX_CORRECT'"),
    '대상 Revision FK' => str_contains($up, 'target_revision_id') && str_contains($up, 'fk_daily_income_command_target_revision'),
    '결과 Revision FK' => str_contains($up, 'result_revision_id') && str_contains($up, 'fk_daily_income_command_result_revision'),
    'Revision 대체상태 계약' => str_contains($up, "'REJECTED','SUPERSEDED'") && str_contains($up, 'ck_daily_non_tax_revision_status'),
    '전용 Audit' => str_contains($up, 'institution_daily_employment_income_non_taxable_audits'),
    'Audit Snapshot JSON 검증' => str_contains($up, 'JSON_VALID(previous_snapshot)') && str_contains($up, 'JSON_VALID(new_snapshot)'),
    'Down 자료보존 차단' => str_contains($down, '비과세 Command 또는 Audit 자료가 있어 Down할 수 없습니다.'),
    'Calculation 임시 STALE 테이블 없음' => !str_contains($up, 'CREATE TABLE institution_daily_employment_income_calculation'),
];

foreach ($assertions as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

echo "OK: 비과세 Command·Audit Migration 계약\n";
