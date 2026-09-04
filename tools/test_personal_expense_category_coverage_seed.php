<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\JournalRuleEvaluationService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;

$pdo=DbPdo::conn();
$source=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$test='codex_personal_coverage_'.bin2hex(random_bytes(5));
if(!preg_match('/^codex_personal_coverage_[0-9a-f]{10}$/',$test)) throw new RuntimeException('격리 DB 이름이 안전하지 않습니다.');
$execute=static function(PDO $db,string $file):void{$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($file))as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$m)){$delimiter=$m[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer,"\r\n");if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$db->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('SQL 구분자가 닫히지 않았습니다.');};
$up=PROJECT_ROOT.'/app/migrations/20260825_03_seed_personal_expense_category_coverage_rules.up.sql';
$down=PROJECT_ROOT.'/app/migrations/20260825_03_seed_personal_expense_category_coverage_rules.down.sql';
$pdo->exec("CREATE DATABASE `{$test}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try{
    foreach(['system_company','system_codes','system_settings_config','ledger_accounts','ledger_journal_rules','ledger_journal_rule_revisions']as$table){$pdo->exec("CREATE TABLE `{$test}`.`{$table}` LIKE `{$source}`.`{$table}`");$pdo->exec("INSERT INTO `{$test}`.`{$table}` SELECT * FROM `{$source}`.`{$table}`");}
    $pdo->exec("USE `{$test}`");
    $pdo->exec('SET @personal_expense_category_coverage_seed_actor='.$pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_CATEGORY_COVERAGE_SEED_TEST')));
    $execute($pdo,$up);
    $first=['rules'=>(int)$pdo->query("SELECT COUNT(*) FROM ledger_journal_rules WHERE operation_type='PERSONAL_EXPENSE'")->fetchColumn(),'revisions'=>(int)$pdo->query("SELECT COUNT(*) FROM ledger_journal_rule_revisions")->fetchColumn(),'debit_categories'=>(int)$pdo->query("SELECT COUNT(DISTINCT item_code) FROM ledger_journal_rules WHERE operation_type='PERSONAL_EXPENSE' AND accounting_role_code='EXPENSE' AND debit_credit='DEBIT' AND rule_status='ACTIVE'")->fetchColumn(),'legacy_null'=>(int)$pdo->query("SELECT COUNT(*) FROM ledger_journal_rules WHERE operation_type='PERSONAL_EXPENSE' AND debit_account_id IS NULL AND credit_account_id IS NULL AND vat_account_id IS NULL")->fetchColumn()];
    if($first!==['rules'=>16,'revisions'=>16,'debit_categories'=>15,'legacy_null'=>16])throw new RuntimeException('Coverage 최초 Up 결과가 다릅니다: '.json_encode($first));
    $execute($pdo,$up);
    if((int)$pdo->query("SELECT COUNT(*) FROM ledger_journal_rules WHERE operation_type='PERSONAL_EXPENSE'")->fetchColumn()!==16||(int)$pdo->query('SELECT COUNT(*) FROM ledger_journal_rule_revisions')->fetchColumn()!==16)throw new RuntimeException('동일 Payload 재실행 멱등성이 실패했습니다.');
    $companyId=(string)$pdo->query('SELECT id FROM system_company')->fetchColumn();
    $resolver=new JournalRuleEvaluationService($pdo);
    $categories=$pdo->query("SELECT code FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' AND is_active=1 ORDER BY sort_no,code")->fetchAll(PDO::FETCH_COLUMN);
    $coverage=[];
    foreach($categories as$category){$context=['company_id'=>$companyId,'business_unit'=>'CONSTRUCTION','operation_type'=>'PERSONAL_EXPENSE','transaction_direction'=>'OUT','client_type'=>'','import_type'=>'EMPLOYEE_EXPENSE_PERSONAL','source_type'=>'PERSONAL_EXPENSE_ITEM','source_line_type'=>'ITEM','item_code'=>$category,'base_date'=>'2013-07-19'];$result=$resolver->evaluate($context);$debit=$result['resolved']['EXPENSE|DEBIT']??null;$credit=$result['resolved']['EMPLOYEE_ACCRUED_EXPENSE|CREDIT']??null;if(!$debit||!$credit)throw new RuntimeException("{$category} 기본 차변 또는 공통 대변이 선택되지 않았습니다.");$coverage[$category]=[$debit['rule_code'],$credit['rule_code']];}
    $beforeBoundary=['company_id'=>$companyId,'business_unit'=>'CONSTRUCTION','operation_type'=>'PERSONAL_EXPENSE','transaction_direction'=>'OUT','client_type'=>'','import_type'=>'EMPLOYEE_EXPENSE_PERSONAL','source_type'=>'PERSONAL_EXPENSE_ITEM','source_line_type'=>'ITEM','item_code'=>'OTHER','base_date'=>'2013-07-18'];
    if(($resolver->evaluate($beforeBoundary)['resolved']??[])!==[])throw new RuntimeException('적용일 전 규칙이 선택됐습니다.');
    $wrongSource=$beforeBoundary;$wrongSource['base_date']='2013-07-19';$wrongSource['source_type']='UNMATCHED';if(($resolver->evaluate($wrongSource)['resolved']??[])!==[])throw new RuntimeException('Source 불일치 규칙이 선택됐습니다.');
    $missingCategory=$beforeBoundary;$missingCategory['base_date']='2013-07-19';$missingCategory['item_code']='';$missingResolved=$resolver->evaluate($missingCategory)['resolved']??[];if(isset($missingResolved['EXPENSE|DEBIT']))throw new RuntimeException('OTHER가 NULL 비용분류의 범용 Fallback으로 선택됐습니다.');
    $unknownCategory=$missingCategory;$unknownCategory['item_code']='UNREGISTERED';$unknownResolved=$resolver->evaluate($unknownCategory)['resolved']??[];if(isset($unknownResolved['EXPENSE|DEBIT']))throw new RuntimeException('OTHER가 미등록 비용분류의 범용 Fallback으로 선택됐습니다.');
    $downBlocked=false;try{$execute($pdo,$down);}catch(PDOException){$downBlocked=true;}if(!$downBlocked)throw new RuntimeException('forward-only Down이 차단되지 않았습니다.');
    echo json_encode(['success'=>true,'database'=>$test,'first_up'=>$first,'same_payload_idempotent'=>true,'coverage'=>$coverage,'boundary_blocked'=>true,'source_mismatch_blocked'=>true,'other_not_generic_fallback'=>true,'down_forward_only_blocked'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}finally{$pdo->exec("USE `{$source}`");$pdo->exec("DROP DATABASE IF EXISTS `{$test}`");}
