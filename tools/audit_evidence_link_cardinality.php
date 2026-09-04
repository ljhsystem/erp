<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';
$db=Core\DbPdo::conn();
$create=$db->query('SHOW CREATE TABLE ledger_evidence_links')->fetch(PDO::FETCH_NUM);
$summary=$db->query("SELECT COUNT(*) total,COALESCE(SUM(deleted_at IS NULL),0) active,COALESCE(SUM(deleted_at IS NOT NULL),0) deleted FROM ledger_evidence_links")->fetch(PDO::FETCH_ASSOC);
$duplicatePairs=$db->query("SELECT COUNT(*) FROM (SELECT evidence_type,evidence_id,target_id,COUNT(*) c FROM ledger_evidence_links WHERE target_type='TRANSACTION' AND deleted_at IS NULL GROUP BY evidence_type,evidence_id,target_id HAVING c>1) d")->fetchColumn();
$orphan=$db->query("SELECT COUNT(*) FROM ledger_evidence_links l LEFT JOIN ledger_transactions t ON l.target_type='TRANSACTION' AND t.id=l.target_id WHERE l.target_type='TRANSACTION' AND t.id IS NULL")->fetchColumn();
$byType=$db->query("SELECT evidence_type,COUNT(*) total,SUM(deleted_at IS NULL) active,COUNT(DISTINCT target_id) targets FROM ledger_evidence_links WHERE target_type='TRANSACTION' GROUP BY evidence_type ORDER BY evidence_type")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['create_table'=>$create[1]??'','summary'=>$summary,'duplicate_active_pairs'=>(int)$duplicatePairs,'transaction_orphans'=>(int)$orphan,'by_type'=>$byType],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
