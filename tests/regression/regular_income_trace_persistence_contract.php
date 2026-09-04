<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';

use App\Services\Institution\RegularEmploymentIncomeCalculationService;

function traceContractAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$columns = RegularEmploymentIncomeCalculationService::TRACE_COLUMNS;
traceContractAssert(count($columns) === 9 && count(array_unique($columns)) === 9, '계산 추적 컬럼 계약은 중복 없는 9개여야 합니다.');

$service = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeService.php');
$calculation = (string) file_get_contents(PROJECT_ROOT . '/app/Services/Institution/RegularEmploymentIncomeCalculationService.php');
$model = (string) file_get_contents(PROJECT_ROOT . '/app/Models/Institution/RegularEmploymentIncomeModel.php');
$view = (string) file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js');

traceContractAssert(substr_count($service, 'RegularEmploymentIncomeCalculationService::TRACE_COLUMNS') === 1, '저장 Service는 공통 계산 추적 계약을 한 번만 참조해야 합니다.');
traceContractAssert(str_contains($calculation, 'array_flip(self::TRACE_COLUMNS)'), '계산 파생 Line도 공통 계산 추적 계약을 사용해야 합니다.');
traceContractAssert(str_contains($service, 'array_intersect_key($line,$allowed)'), '일반 저장경로 Persistence Payload 필터가 없습니다.');
traceContractAssert(str_contains($service, 'array_intersect_key($line, $lineColumns)'), '재계산 저장경로 Persistence Payload 필터가 없습니다.');
traceContractAssert(str_contains($model, 'SELECT line_row.*')
    && str_contains($model, 'FROM institution_regular_employment_income_line_items line_row'), '재조회 SELECT가 전체 Line 추적정보를 반환하지 않습니다.');
traceContractAssert(str_contains($view, 'line.calculation_basis_amount') && str_contains($view, 'line.calculation_before_rounding'), '화면 계산근거 Projection이 없습니다.');

foreach ($columns as $column) {
    traceContractAssert(substr_count($service, "'{$column}'") === 0, "저장 Service에 계산 추적 컬럼이 중복 선언됐습니다: {$column}");
}

echo "regular_income_trace_persistence_contract: OK\n";
