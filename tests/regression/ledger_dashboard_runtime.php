<?php
declare(strict_types=1);
define('PROJECT_ROOT',dirname(__DIR__,2));
require PROJECT_ROOT.'/vendor/autoload.php';
require PROJECT_ROOT.'/core/DbPdo.php';
use App\Services\Ledger\LedgerDashboardService;
use Core\DbPdo;
$data=LedgerDashboardService::fromPdo(DbPdo::conn())->summary();
$read=static fn(string $path):string=>(string)file_get_contents(PROJECT_ROOT.'/'.$path);
$checks=[
 'web route connected'=>str_contains($read('routes/web/ledger.php'),"'/ledger', 'LedgerDashboardController@index'"),
 'api route connected'=>str_contains($read('routes/api/ledger.php'),"'/api/ledger/dashboard/summary', 'LedgerDashboardController@apiSummary'"),
 'no hardcoded sample amounts'=>!str_contains($read('app/views/ledger/index.php'),'12,500,000'),
 'dashboard api used'=>str_contains($read('public/assets/js/pages/ledger/index.js'),'/api/ledger/dashboard/summary'),
 'voucher workflow available'=>isset($data['vouchers']['DRAFT'],$data['vouchers']['POSTED'],$data['vouchers']['CLOSED']),
 'posted performance available'=>isset($data['performance']['voucher_count'],$data['performance']['debit_total'],$data['performance']['credit_total']),
 'twelve month trend available'=>count($data['monthly']??[])===12,
 'dashboard uses posted facts only'=>!str_contains($read('app/Repositories/Ledger/LedgerDashboardRepository.php'),"'REVIEWED','POSTED','CLOSED'"),
 'closing readiness available'=>count($data['readiness']??[])===5,
 'action alerts available'=>isset($data['alerts'])&&is_array($data['alerts']),
 'inventory movement available'=>isset($data['inventory']['opening_total'],$data['inventory']['closing_total']),
 'assets available'=>isset($data['assets']['active_count'],$data['assets']['book_value_total']),
 'recent rows official'=>count(array_filter($data['recent_vouchers'],static fn(array $row):bool=>!in_array(strtoupper((string)$row['status_code']),['POSTED','CLOSED'],true)))===0,
];
$failed=array_keys(array_filter($checks,static fn(bool $passed):bool=>!$passed));
echo json_encode(['passed'=>$failed===[],'checks'=>$checks,'data'=>$data,'failed'=>$failed],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
exit($failed===[]?0:1);
