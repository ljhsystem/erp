<?php

declare(strict_types=1);

define('PROJECT_ROOT',dirname(__DIR__));
require PROJECT_ROOT.'/vendor/autoload.php';

use Core\DbPdo;

$options=getopt('', ['execute']);
$db=DbPdo::conn();
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
if ((string)$db->query('SELECT DATABASE()')->fetchColumn()!=='sukhyang') throw new RuntimeException('OPERATING_SCHEMA_MISMATCH');
$file=PROJECT_ROOT.'/app/migrations/20260902_01_create_ledger_transaction_projection_repairs.up.sql';
if (!isset($options['execute'])) {
    echo json_encode(['status'=>'READY','database'=>'sukhyang','migration'=>basename($file)],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
    exit(0);
}
$sql=file_get_contents($file);
if ($sql===false) throw new RuntimeException('MIGRATION_FILE_NOT_READABLE');
$sql=preg_replace('/^DELIMITER \$\$\R|^DELIMITER ;\R?/m','',$sql);
$parts=preg_split('/\$\$\s*/',$sql,-1,PREG_SPLIT_NO_EMPTY);
foreach($parts as $part){$part=trim($part);if($part!=='')$db->exec($part);}
$columns=(int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs'")->fetchColumn();
$indexes=(int)$db->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs'")->fetchColumn();
$checks=(int)$db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ledger_transaction_projection_repairs' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
if($columns!==17||$indexes!==6||$checks!==5)throw new RuntimeException('MIGRATION_STRUCTURE_MISMATCH');
echo json_encode(['status'=>'COMPLETED','database'=>'sukhyang','columns'=>$columns,'indexes'=>$indexes,'checks'=>$checks],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
