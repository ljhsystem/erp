<?php
declare(strict_types=1);
define('PROJECT_ROOT',dirname(__DIR__,2));
require PROJECT_ROOT.'/vendor/autoload.php';
require PROJECT_ROOT.'/core/DbPdo.php';
use App\Services\Ledger\BookService;
use Core\DbPdo;
$db=DbPdo::conn();$service=new BookService($db);$request=['start'=>0,'length'=>500,'order'=>[['column'=>0,'dir'=>'asc']],'columns'=>[['data'=>'voucher_date']]];
$accountId=(string)($db->query("SELECT l.account_id FROM ledger_voucher_lines l JOIN ledger_vouchers v ON v.id=l.voucher_id WHERE v.deleted_at IS NULL AND UPPER(v.status) IN ('POSTED','CLOSED') LIMIT 1")->fetchColumn()?:'');$page=$service->getAccountLedgerPage($request,['account_id'=>$accountId]);
$rows=$page['rows']??[];$checks=['official statuses only'=>count(array_filter($page['rows'],static fn(array $row):bool=>!in_array(strtoupper((string)($row['voucher_status']??'')),['POSTED','CLOSED'],true)))===0,'running balance provided'=>count(array_filter($page['rows'],static fn(array $row):bool=>!array_key_exists('running_balance',$row)))===0,'debit credit fields numeric'=>count(array_filter($rows,static fn(array $row):bool=>(isset($row['debit'])&&!is_numeric($row['debit']))||(isset($row['credit'])&&!is_numeric($row['credit']))))===0];$failed=array_keys(array_filter($checks,static fn(bool $passed):bool=>!$passed));echo json_encode(['passed'=>$failed===[],'checks'=>$checks,'summary'=>$page['summary']??[],'failed'=>$failed],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($failed===[]?0:1);
