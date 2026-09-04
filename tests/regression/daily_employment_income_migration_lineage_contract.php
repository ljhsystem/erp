<?php

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$m01Up = $read('app/migrations/20260827_01_complete_daily_employment_income_closure.up.sql');
$m01Down = $read('app/migrations/20260827_01_complete_daily_employment_income_closure.down.sql');
$m04Up = $read('app/migrations/20260827_04_create_daily_employment_income_commands.up.sql');
$m04Down = $read('app/migrations/20260827_04_create_daily_employment_income_commands.down.sql');
$m07Up = $read('app/migrations/20260827_07_create_daily_employment_income_closure_registry.up.sql');
$m07Down = $read('app/migrations/20260827_07_create_daily_employment_income_closure_registry.down.sql');

$checks = [
    !str_contains($m01Up, 'CREATE TABLE institution_daily_employment_income_commands'),
    !str_contains($m01Up, 'CREATE TABLE institution_daily_employment_income_closures'),
    !str_contains($m01Down, 'DROP TABLE institution_daily_employment_income_commands'),
    str_contains($m04Up, 'CREATE TABLE institution_daily_employment_income_commands'),
    str_contains($m04Down, 'DROP TABLE institution_daily_employment_income_commands'),
    !str_contains($m04Up, 'institution_daily_employment_income_closures'),
    str_contains($m07Up, 'CREATE TABLE institution_daily_employment_income_closures'),
    str_contains($m07Up, 'CREATE TABLE institution_daily_employment_income_accounting_links'),
    !str_contains($m07Up, 'CREATE TABLE institution_daily_employment_income_commands'),
    str_contains($m07Down, 'DROP TABLE institution_daily_employment_income_accounting_links'),
    str_contains($m07Down, 'DROP TABLE institution_daily_employment_income_closures'),
    !str_contains($m07Down, 'DROP TABLE institution_daily_employment_income_commands'),
];
if (in_array(false, $checks, true)) {
    fwrite(STDERR, "FAIL: 일용근로소득 Migration 소유권 계약이 충돌합니다.\n");
    exit(1);
}
echo "PASS: Command는 04, Closure·Accounting Registry는 07이 단독 소유합니다.\n";
