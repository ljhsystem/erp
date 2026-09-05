<?php
declare(strict_types=1);
define('PROJECT_ROOT',dirname(__DIR__,2));
require PROJECT_ROOT.'/vendor/autoload.php';
require PROJECT_ROOT.'/core/DbPdo.php';
use App\Services\Ledger\BookService;
use Core\DbPdo;
$db=DbPdo::conn();$service=new BookService($db);$request=['start'=>0,'length'=>500,'order'=>[['column'=>0,'dir'=>'asc']],'columns'=>[['data'=>'voucher_date']]];
$page=$service->getGeneralLedgerPage($request,[]);
$rows=$page['rows']??[];$checks=['official aggregate returned'=>isset($page['summary']['period_debit']),'balanced summary'=>abs((float)$page['summary']['period_debit']-(float)$page['summary']['period_credit'])<0.0001,'debit credit fields numeric'=>count(array_filter($rows,static fn(array $row):bool=>(isset($row['debit'])&&!is_numeric($row['debit']))||(isset($row['credit'])&&!is_numeric($row['credit']))))===0];$failed=array_keys(array_filter($checks,static fn(bool $passed):bool=>!$passed));echo json_encode(['passed'=>$failed===[],'checks'=>$checks,'summary'=>$page['summary']??[],'failed'=>$failed],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($failed===[]?0:1);
