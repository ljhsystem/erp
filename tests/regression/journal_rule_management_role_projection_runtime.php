<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__,2));
require PROJECT_ROOT.'/vendor/autoload.php';
require_once PROJECT_ROOT.'/core/Storage.php';

use App\Models\Ledger\JournalRuleModel;
use Core\DbPdo;

$rows=array_values(array_filter((new JournalRuleModel(DbPdo::conn()))->getList(),static fn(array$row):bool=>($row['operation_type']??'')==='PERSONAL_EXPENSE'));
if(count($rows)!==16)throw new RuntimeException('분개규칙 관리 Projection의 개인경비 Rule이 16건이 아닙니다.');
foreach($rows as$row){foreach(['accounting_role_code','debit_credit','account_id','result_account_code','result_account_name','condition_hash','revision_no','latest_revision_id','latest_revision_action']as$field){if(trim((string)($row[$field]??''))==='')throw new RuntimeException(($row['rule_code']??'')."의 {$field}가 관리 Projection에서 누락됐습니다.");}}
echo json_encode(['success'=>true,'row_count'=>count($rows),'other'=>array_values(array_filter($rows,static fn(array$row):bool=>($row['item_code']??'')==='OTHER'))],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
