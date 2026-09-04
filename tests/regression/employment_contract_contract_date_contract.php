<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$service = new App\Services\Institution\EmploymentContractService(Core\DbPdo::conn());
$method = new ReflectionMethod($service, 'contractNo');
$number = $method->invoke($service, '2013-07-19');
if (!preg_match('/^EC-20130719-[A-F0-9]{6}$/', $number)) {
    throw new RuntimeException('계약번호가 계약일 기준 형식이 아닙니다: ' . $number);
}

$projection = new App\Services\Institution\EmploymentContractStatutoryProjectionService(Core\DbPdo::conn());
$result = $projection->evaluate([
    'contract_date' => '2013-07-19',
    'contract_start_date' => '2013-08-01',
    'created_at' => '2026-08-22 00:00:00',
], [[
    'component_type' => 'BASE_PAY',
    'component_code' => 'BASE_SALARY',
    'component_name' => '기본급',
    'rate' => 3124.4545,
    'minimum_wage_treatment' => 'INCLUDED',
]], []);
if ($result['basis_date'] !== '2013-08-01' || $result['minimum_wage'] !== 4860.0) {
    throw new RuntimeException('법정기준 검증이 계약일이 아닌 계약 시작일을 사용하지 않습니다.');
}

echo "employment contract contract date: OK\n";
