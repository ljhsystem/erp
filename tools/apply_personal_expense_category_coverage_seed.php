<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;
use Core\Helpers\ActorHelper;

$mode=strtolower(trim((string)($argv[1]??'')));
if(!in_array($mode,['preflight','apply','verify'],true))throw new InvalidArgumentException('사용법: php tools/apply_personal_expense_category_coverage_seed.php [preflight|apply|verify]');
$pdo=DbPdo::conn();
$codes=['PE_DEBIT_FUEL','PE_DEBIT_PARKING','PE_DEBIT_TOLL','PE_DEBIT_ACCOMMODATION','PE_DEBIT_COMMUNICATION','PE_DEBIT_ENTERTAINMENT','PE_DEBIT_OFFICE_SUPPLIES','PE_DEBIT_FREIGHT','PE_DEBIT_EQUIPMENT_RENTAL','PE_DEBIT_OTHER'];
$quoted=implode(',',array_map([$pdo,'quote'],$codes));
$snapshot=static function()use($pdo,$quoted):array{$scalar=static fn(string$sql):mixed=>$pdo->query($sql)->fetchColumn();return['database'=>(string)$scalar('SELECT DATABASE()'),'version'=>(string)$scalar('SELECT VERSION()'),'rules_total'=>(int)$scalar('SELECT COUNT(*) FROM ledger_journal_rules'),'revisions_total'=>(int)$scalar('SELECT COUNT(*) FROM ledger_journal_rule_revisions'),'coverage_rules'=>(int)$scalar("SELECT COUNT(*) FROM ledger_journal_rules WHERE rule_code IN ({$quoted})"),'coverage_revisions'=>(int)$scalar("SELECT COUNT(*) FROM ledger_journal_rule_revisions rv JOIN ledger_journal_rules r ON r.id=rv.rule_id WHERE r.rule_code IN ({$quoted}) AND rv.action_code='CREATE'"),'active_categories'=>(int)$scalar("SELECT COUNT(*) FROM system_codes WHERE code_group='PERSONAL_EXPENSE_CATEGORY' AND is_active=1"),'evidence_links'=>(int)$scalar('SELECT COUNT(*) FROM ledger_evidence_links'),'transactions'=>(int)$scalar('SELECT COUNT(*) FROM ledger_transactions'),'vouchers'=>(int)$scalar('SELECT COUNT(*) FROM ledger_vouchers'),'personal_expense_items'=>(int)$scalar('SELECT COUNT(*) FROM approval_personal_expense_items')];};
$before=$snapshot();
$ready=$before['database']==='sukhyang'&&str_contains($before['version'],'10.11.11-MariaDB')&&$before['active_categories']===15&&$before['rules_total']===6&&$before['revisions_total']===6&&$before['coverage_rules']===0&&$before['coverage_revisions']===0;
if($mode==='preflight'){echo json_encode(['success'=>$ready,'state'=>$before],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($ready?0:2);}
if($mode==='apply'){
    if(!$ready)throw new RuntimeException('운영 Coverage Seed Preflight가 승인 기준과 다릅니다.');
    $pdo->exec('SET @personal_expense_category_coverage_seed_actor='.$pdo->quote(ActorHelper::system('PERSONAL_EXPENSE_CATEGORY_COVERAGE_SEED')));
    $file=PROJECT_ROOT.'/app/migrations/20260825_03_seed_personal_expense_category_coverage_rules.up.sql';$delimiter=';';$buffer='';foreach(preg_split('/\r\n|\n|\r/',(string)file_get_contents($file))as$line){if(preg_match('/^DELIMITER\s+(.+)$/i',trim($line),$m)){$delimiter=$m[1];continue;}$buffer.=$line."\n";$trimmed=rtrim($buffer,"\r\n");if(!str_ends_with($trimmed,$delimiter))continue;$statement=trim(substr($trimmed,0,-strlen($delimiter)));if($statement!=='')$pdo->exec($statement);$buffer='';}if(trim($buffer)!=='')throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
}
$after=$snapshot();$businessKeys=['evidence_links','transactions','vouchers','personal_expense_items'];$unchanged=array_intersect_key($before,array_flip($businessKeys))===array_intersect_key($after,array_flip($businessKeys));$success=$after['rules_total']===16&&$after['revisions_total']===16&&$after['coverage_rules']===10&&$after['coverage_revisions']===10&&$unchanged;echo json_encode(['success'=>$success,'before'=>$before,'after'=>$after,'business_data_unchanged'=>$unchanged],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;exit($success?0:3);
