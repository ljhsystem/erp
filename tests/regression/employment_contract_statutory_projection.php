<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$service = new App\Services\Institution\EmploymentContractStatutoryProjectionService(Core\DbPdo::conn());
$projection = $service->project('a07417ab-3385-46a6-9c6c-e5127b8c4d98');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert($projection['basis_date'] === '2013-08-01', '계약 시작일이 검증 기준일이 아닙니다.');
$assert($projection['minimum_wage'] === 4860.0, '2013년 최저임금 Resolver 결과가 다릅니다.');
$assert($projection['contract_calculation_rate'] === 3124.4545, '기본급 계약 계산단가가 다릅니다.');
$assert($projection['difference'] === -1735.5455, '최저임금 차이가 다릅니다.');
$assert($projection['status'] === 'WARNING', '2013 과거계약이 미달 가능성 WARNING이 아닙니다.');
$assert($projection['historical_snapshot'] === true, '과거 실제계약으로 분류되지 않았습니다.');
$assert($projection['approval_blocked'] === false, '과거 실제계약이 결재 차단되었습니다.');
$assert(count($projection['standard']['sources'] ?? []) === 1, '공식 Source가 Projection에 연결되지 않았습니다.');
foreach (['overtime', 'break_time', 'weekly_working_hours', 'probation'] as $key) {
    $assert(($projection['checks'][$key]['status'] ?? '') === 'NOT_VERIFIABLE', $key . '가 검증 가능으로 위장되었습니다.');
}

$currentContract = [
    'contract_start_date' => '2013-08-01',
    'created_at' => '2013-08-01 09:00:00',
    'contract_status' => 'DRAFT',
];
$compliant = $service->evaluate($currentContract, [[
    'component_type' => 'BASE_PAY', 'component_code' => 'BASE', 'component_name' => '기본급',
    'rate' => 5000, 'minimum_wage_treatment' => 'INCLUDED',
]], []);
$assert($compliant['status'] === 'COMPLIANT' && !$compliant['approval_blocked'], 'Scenario A 충족 신규계약 판정 실패');

$blocked = $service->evaluate($currentContract, [[
    'component_type' => 'BASE_PAY', 'component_code' => 'BASE', 'component_name' => '기본급',
    'rate' => 3000, 'minimum_wage_treatment' => 'INCLUDED',
]], []);
$assert($blocked['status'] === 'WARNING' && $blocked['approval_blocked'], 'Scenario B 명백한 미달 신규계약 차단 실패');

$unverifiable = $service->evaluate([
    'contract_start_date' => '1900-01-01', 'created_at' => '1900-01-01 09:00:00', 'contract_status' => 'DRAFT',
], [[
    'component_type' => 'BASE_PAY', 'component_code' => 'BASE', 'component_name' => '기본급',
    'rate' => 5000, 'minimum_wage_treatment' => 'INCLUDED',
]], []);
$assert($unverifiable['status'] === 'NOT_VERIFIABLE', 'Scenario D 기준 누락이 정상으로 위장되었습니다.');
$assert(($projection['standard']['effective_from'] ?? '') === '2013-01-01', 'Scenario E 계약 기준일 Revision 선택 실패');

echo "employment contract statutory projection: OK\n";
