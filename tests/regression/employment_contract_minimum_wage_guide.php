<?php
declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$service = new App\Services\Institution\EmploymentContractService(Core\DbPdo::conn());
$guide = $service->minimumWageGuide('2013-08-01');
if (($guide['status'] ?? '') !== 'READY' || (float) ($guide['hourly_wage'] ?? 0) !== 4860.0) {
    throw new RuntimeException('2013년 8월 적용 최저임금 안내가 법정기준 SSOT와 일치하지 않습니다.');
}
if (($guide['effective_from'] ?? '') !== '2013-01-01') {
    throw new RuntimeException('최저임금 적용 시작일이 올바르지 않습니다.');
}

echo "employment contract minimum wage guide: OK\n";
