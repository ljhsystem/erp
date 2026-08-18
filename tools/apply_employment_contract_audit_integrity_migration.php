<?php

declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$direction=$argv[1]??'verify';
if(!in_array($direction,['up','down','verify'],true)){fwrite(STDERR,"사용법: php tools/apply_employment_contract_audit_integrity_migration.php [up|down|verify]\n");exit(1);}
$pdo=DbPdo::conn();
$scalar=static function(string$sql)use($pdo):int{return(int)$pdo->query($sql)->fetchColumn();};
$preflight=['null_contract_refs'=>$scalar('SELECT COUNT(*) FROM institution_regular_employment_income_items WHERE employment_contract_id IS NULL'),
    'orphan_contract_refs'=>$scalar('SELECT COUNT(*) FROM institution_regular_employment_income_items i LEFT JOIN institution_employment_contracts c ON c.id=i.employment_contract_id WHERE i.employment_contract_id IS NOT NULL AND c.id IS NULL'),
    'deleted_contract_refs'=>$scalar('SELECT COUNT(*) FROM institution_regular_employment_income_items i JOIN institution_employment_contracts c ON c.id=i.employment_contract_id WHERE c.deleted_at IS NOT NULL')];
if($direction==='up'&&($preflight['orphan_contract_refs']>0||$preflight['deleted_contract_refs']>0))throw new RuntimeException('근로소득 계약 참조 정합성 오류가 있어 Migration을 중단했습니다.');
if($direction!=='verify'){$file=PROJECT_ROOT.'/app/migrations/20260818_01_employment_contract_audit_integrity.'.$direction.'.sql';$sql=file_get_contents($file);if($sql===false)throw new RuntimeException('Migration SQL을 읽을 수 없습니다.');$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);$pdo->exec($sql);}
$tableExists=$scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_employment_contracts_audits'")===1;
$fk=$pdo->query("SELECT k.CONSTRAINT_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='institution_regular_employment_income_items' AND k.COLUMN_NAME='employment_contract_id'")->fetch(PDO::FETCH_ASSOC)?:null;
$codes=$pdo->query("SELECT code FROM system_codes WHERE code_group='EMPLOYMENT_CONTRACT_STATUS' ORDER BY sort_no")->fetchAll(PDO::FETCH_COLUMN)?:[];
$auditStructure=$tableExists?$pdo->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_employment_contracts_audits' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC):[];
$auditIndexes=$tableExists?$pdo->query("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_in_order FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_employment_contracts_audits' GROUP BY INDEX_NAME,NON_UNIQUE ORDER BY INDEX_NAME")->fetchAll(PDO::FETCH_ASSOC):[];
$auditFks=$tableExists?$pdo->query("SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='institution_employment_contracts_audits'")->fetchAll(PDO::FETCH_ASSOC):[];
$indexes=$pdo->query("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) columns_in_order FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_employment_contracts' GROUP BY INDEX_NAME ORDER BY INDEX_NAME")->fetchAll(PDO::FETCH_ASSOC)?:[];
$employee=(string)($pdo->query('SELECT employee_id FROM institution_employment_contracts LIMIT 1')->fetchColumn()?:'');
$explain=[];if($employee!==''){$stmt=$pdo->prepare("EXPLAIN SELECT * FROM institution_employment_contracts WHERE employee_id=:employee AND deleted_at IS NULL AND contract_status IN ('APPROVAL_PENDING','APPROVED','TERMINATED') AND contract_start_date<='2026-12-31' AND COALESCE(contract_end_date,DATE(terminated_at),'9999-12-31')>='2026-01-01' FOR UPDATE");$stmt->execute([':employee'=>$employee]);$explain=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];}
echo json_encode(['direction'=>$direction,'preflight'=>$preflight,'audit_table'=>$tableExists,'audit_structure'=>$auditStructure,'audit_indexes'=>$auditIndexes,'audit_fks'=>$auditFks,'regular_income_fk'=>$fk,'status_codes'=>$codes,'contract_indexes'=>$indexes,'overlap_explain'=>$explain],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),"\n";
