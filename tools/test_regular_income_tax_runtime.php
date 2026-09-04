<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Institution\EmploymentIncomeTaxTableService;
use App\Services\Institution\RegularEmploymentIncomeCalculationService;
use App\Services\Institution\RegularEmploymentIncomeService;
use App\Services\System\StatutoryStandardResolver;
use Core\DbPdo;

function fixtureAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = DbPdo::conn();
$resolver = new StatutoryStandardResolver($db);
$revision = $resolver->resolve('EMPLOYMENT_INCOME_TAX_TABLE', '2013-09-11');
$calculator = new EmploymentIncomeTaxTableService();
$one = $calculator->calculate(988890, '1', $revision['value_data']);
$eleven = $calculator->calculate(988890, 11, $revision['value_data']);
$zero = $calculator->calculate(700000, 1, $revision['value_data']);
$unsupported = null;
try {
    $calculator->calculate(988890, 12, $revision['value_data']);
} catch (InvalidArgumentException $exception) {
    $unsupported = $exception->getMessage();
}

fixtureAssert($revision['effective_from'] === '2012-09-04' && $revision['effective_to'] === '2014-02-20', '2013 지급일 Revision이 일치하지 않습니다.');
fixtureAssert($one['dependent_count'] === 1 && $one['dependent_column_key'] === '1', '가족수 1명 열 매핑에 실패했습니다.');
fixtureAssert($one['salary_from'] === 985000.0 && $one['salary_to'] === 990000.0, '988,890원 급여구간이 일치하지 않습니다.');
fixtureAssert(is_float($one['tax_amount']) || is_int($one['tax_amount']), '가족수 1명 세액이 숫자가 아닙니다.');
fixtureAssert($zero['tax_amount'] === 0.0, '0원 세액표 결과를 정상값으로 처리하지 못했습니다.');
fixtureAssert($eleven['dependent_count'] === 11, '가족수 11명 계산에 실패했습니다.');
fixtureAssert($unsupported === '해당 간이세액표는 공제대상 가족수 12명을 지원하지 않습니다.', '지원범위 오류문구가 정확하지 않습니다.');

$selection = (new RegularEmploymentIncomeService($db))->eligibleEmployees('2013-08')['data'];
$candidate = $selection['candidates'][0] ?? null;
fixtureAssert(is_array($candidate), '2013-08 계산 대상직원이 없습니다.');
$employeeId = (string) $candidate['employee_id'];
$preview = (new RegularEmploymentIncomeCalculationService($db))->preview('2013-08', '2013-09-11', [[
    'employee_id' => $employeeId,
    'dependent_count_snapshot' => '1',
]], 'SYSTEM:FIXTURE')['results'][0];
$lines = [];
foreach ($preview['line_items'] as $line) {
    $lines[$line['item_code']] = $line;
}
$incomeTax = $lines['EMPLOYMENT_INCOME_TAX'] ?? null;
$localTax = $lines['LOCAL_INCOME_TAX'] ?? null;
fixtureAssert(is_array($incomeTax) && $incomeTax['final_amount'] !== null, 'Runtime 근로소득세가 계산되지 않았습니다.');
fixtureAssert((int) $incomeTax['dependent_count'] === 1 && $incomeTax['dependent_column_key'] === '1', 'Runtime 가족수 값이 1로 유지되지 않았습니다.');
fixtureAssert(is_array($localTax) && $localTax['final_amount'] !== null, '지방소득세 연쇄계산에 실패했습니다.');

$ui = (string) file_get_contents(PROJECT_ROOT . '/public/assets/js/pages/institution/regular-employment-income/index.js');
fixtureAssert(!str_contains($ui, '실제값 적용') && !str_contains($ui, '실제 최종금액'), '레거시 실제값 용어가 남아 있습니다.');
fixtureAssert(str_contains($ui, '적용금액') && str_contains($ui, '조정사유 *'), '적용금액 UX 계약이 누락됐습니다.');
fixtureAssert(str_contains($ui, 'appliedAmount-calculated'), '조정금액 자동산출 계약이 누락됐습니다.');

echo json_encode([
    'success' => true,
    'revision' => ['id'=>$revision['id'],'effective_from'=>$revision['effective_from'],'effective_to'=>$revision['effective_to']],
    'scenario_a' => $one,
    'scenario_b_zero_tax' => $zero,
    'scenario_c_dependent_11' => $eleven,
    'scenario_d_unsupported' => $unsupported,
    'runtime' => [
        'employee_id' => $employeeId,
        'dependent_count_snapshot_input' => '1',
        'dependent_count_consumer' => $incomeTax['dependent_count'],
        'income_tax' => $incomeTax['final_amount'],
        'local_income_tax' => $localTax['final_amount'],
        'salary_from' => $incomeTax['tax_table_salary_from'],
        'salary_to' => $incomeTax['tax_table_salary_to'],
        'dependent_column_key' => $incomeTax['dependent_column_key'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
