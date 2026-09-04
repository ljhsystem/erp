<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'preflight'));
if (!in_array($mode, ['preflight','up','verify'], true)) throw new InvalidArgumentException('사용법: php tools/apply_regular_income_accounting_generation_recovery.php [preflight|up|verify]');
$db = DbPdo::conn();
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$migration = '20260825_05_resume_regular_income_accounting_generation_identity';
$file = PROJECT_ROOT . '/app/migrations/' . $migration . '.up.sql';
$tracked = ['ledger_transaction_items','ledger_transaction_settlements','institution_regular_employment_income_accounting_links','institution_regular_income_accounting_schedules'];
$dataTables = ['institution_regular_employment_income_accounting_links','ledger_transactions','ledger_transaction_items','ledger_transaction_settlements','ledger_evidence_salary_report','ledger_payment_schedules'];

$counts = static function () use ($db, $dataTables): array {
    $result=[];
    foreach ($dataTables as $table) $result[$table]=(int)$db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    return $result;
};
$schema = static function () use ($db, $tracked): array {
    $result=[];
    foreach ($tracked as $table) {
        $exists=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".$db->quote($table))->fetchColumn();
        if (!$exists) { $result[$table]=null; continue; }
        $row=$db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $result[$table]=$row[1]??null;
    }
    return $result;
};
$state = static function () use ($db): array {
    return [
        'item_columns'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_items' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')")->fetchColumn(),
        'settlement_columns'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_settlements' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id')")->fetchColumn(),
        'registry_columns'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('generation_role','aggregation_key','approval_request_id','attribution_month','recognition_date','payload_hash')")->fetchColumn(),
        'schedule_table'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules'")->fetchColumn(),
        'baseline_uk'=>(int)$db->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='uk_regular_income_accounting_detail' AND SEQ_IN_INDEX=1 AND NON_UNIQUE=0")->fetchColumn(),
        'support_index'=>(int)$db->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='idx_regular_income_accounting_detail' AND SEQ_IN_INDEX=1 AND NON_UNIQUE=1")->fetchColumn(),
        'detail_fk'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_regular_income_accounting_detail'")->fetchColumn(),
        'identity_uk'=>(int)$db->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='uk_regular_income_accounting_identity'")->fetchColumn(),
        'checks'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK' AND CONSTRAINT_NAME IN ('chk_regular_income_accounting_role','chk_regular_income_accounting_month','chk_regular_income_accounting_payload_hash','chk_regular_income_accounting_role_fields','chk_regular_income_accounting_schedule_role')")->fetchColumn(),
    ];
};
$removeStaleMigrationProcedure = static function () use ($db): void {
    $name = 'migrate_20260825_04_regular_income_generation';
    $tableStatement = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name');
    $tableStatement->execute([':name' => $name]);
    if ((int) $tableStatement->fetchColumn() !== 0) {
        throw new RuntimeException('잔존 Migration PROCEDURE 이름으로 TABLE 또는 VIEW가 존재합니다.');
    }
    $statement = $db->prepare('SELECT ROUTINE_TYPE,DEFINER,SHA2(ROUTINE_DEFINITION,256) body_sha256 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME=:name');
    $statement->execute([':name' => $name]);
    $routine = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($routine === null) return;
    if (($routine['ROUTINE_TYPE'] ?? null) !== 'PROCEDURE'
        || ($routine['DEFINER'] ?? null) !== 'sukhyang@%'
        || ($routine['body_sha256'] ?? null) !== '08f4fb84fc054bff064906508266e3f393378c152551e8419b45e8c1e8741224') {
        throw new RuntimeException('잔존 Migration PROCEDURE의 종류, DEFINER 또는 본문 해시가 기준선과 다릅니다.');
    }
    $db->exec("DROP PROCEDURE `{$name}`");
};
$execute = static function () use ($db, $file): void {
    $delimiter=';'; $buffer='';
    foreach (preg_split('/\r\n|\n|\r/', (string)file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter=$match[1]; continue; }
        $buffer.=$line."\n"; $trimmed=rtrim($buffer,"\r\n");
        if (!str_ends_with($trimmed,$delimiter)) continue;
        $statement=trim(substr($trimmed,0,-strlen($delimiter)));
        if ($statement!=='') $db->exec($statement);
        $buffer='';
    }
    if (trim($buffer)!=='') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};

$beforeCounts=$counts(); $beforeSchema=$schema(); $beforeState=$state();
$partialReady=$beforeCounts['institution_regular_employment_income_accounting_links']===0
    && $beforeState['item_columns']===3 && $beforeState['settlement_columns']===3
    && $beforeState['registry_columns']===0 && $beforeState['schedule_table']===0
    && $beforeState['baseline_uk']===1 && $beforeState['support_index']===0 && $beforeState['detail_fk']===1;
$snapshotPath=null; $historyPath=null;
if ($mode==='up') {
    if (!$partialReady) throw new RuntimeException('승인된 Migration 04 부분 적용 상태가 아니므로 05 실행을 차단했습니다.');
    $directory=PROJECT_ROOT.'/storage/db_backup';
    if (!is_dir($directory)) throw new RuntimeException('스키마 Snapshot 저장경로가 없습니다.');
    $stamp=date('Ymd_His');
    $snapshotPath=$directory.'/'.$migration.'_schema_before_'.$stamp.'.json';
    file_put_contents($snapshotPath,json_encode(['migration'=>$migration,'captured_at'=>date(DATE_ATOM),'sql_sha256'=>hash_file('sha256',$file),'counts'=>$beforeCounts,'state'=>$beforeState,'schema'=>$beforeSchema],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    $execute();
    $historyPath=$directory.'/'.$migration.'_application_'.$stamp.'.json';
}
$afterCounts=$counts(); $afterSchema=$schema(); $afterState=$state();
$applied=$afterState['item_columns']===3 && $afterState['settlement_columns']===3 && $afterState['registry_columns']===6
    && $afterState['schedule_table']===1 && $afterState['baseline_uk']===0 && $afterState['support_index']===1
    && $afterState['detail_fk']===1 && $afterState['identity_uk']===1 && $afterState['checks']===5;
if (in_array($mode,['up','verify'],true) && (!$applied || $beforeCounts!==$afterCounts)) throw new RuntimeException('Migration 05 최종 구조 또는 데이터 불변 검증에 실패했습니다.');
if ($mode === 'up' && $applied && $beforeCounts === $afterCounts) $removeStaleMigrationProcedure();
$migrationTables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%migration%' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN)?:[];
$report=['success'=>true,'mode'=>$mode,'migration'=>$migration,'sql_sha256'=>hash_file('sha256',$file),'central_migration_history_tables'=>$migrationTables,'automatic_runner'=>false,'partial_ready'=>$partialReady,'applied'=>$applied,'snapshot_path'=>$snapshotPath,'application_history_path'=>$historyPath,'before_counts'=>$beforeCounts,'after_counts'=>$afterCounts,'data_counts_unchanged'=>$beforeCounts===$afterCounts,'before_state'=>$beforeState,'after_state'=>$afterState,'schema'=>$afterSchema];
if ($historyPath!==null) file_put_contents($historyPath,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
