<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$source = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$schema = 'codex_comment_checksum_' . getmypid();
$db->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
try {
    $db->exec("CREATE TABLE `{$schema}`.`ledger_evidence_links` LIKE `{$source}`.`ledger_evidence_links`");
    $columnStatement = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='ledger_evidence_links' AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION");
    $columnStatement->execute([$source]);
    $columnList = implode(',', array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $columnStatement->fetchAll(PDO::FETCH_COLUMN)));
    $db->exec("INSERT INTO `{$schema}`.`ledger_evidence_links` ({$columnList}) SELECT {$columnList} FROM `{$source}`.`ledger_evidence_links`");
    $current = $db->query("CHECKSUM TABLE `{$schema}`.`ledger_evidence_links`")->fetch(PDO::FETCH_ASSOC);
    $logicalBefore = hash('sha256', serialize($db->query("SELECT * FROM `{$schema}`.`ledger_evidence_links` ORDER BY id")->fetchAll(PDO::FETCH_NUM)));
    $db->exec("ALTER TABLE `{$schema}`.`ledger_evidence_links` MODIFY COLUMN `id` varchar(36) NOT NULL COMMENT 'ID', ALGORITHM=INSTANT, LOCK=NONE");
    $legacy = $db->query("CHECKSUM TABLE `{$schema}`.`ledger_evidence_links`")->fetch(PDO::FETCH_ASSOC);
    $logicalAfter = hash('sha256', serialize($db->query("SELECT * FROM `{$schema}`.`ledger_evidence_links` ORDER BY id")->fetchAll(PDO::FETCH_NUM)));
    $result = ['success'=>$logicalBefore===$logicalAfter && (string)$current['Checksum']!==(string)$legacy['Checksum'],
        'row_count'=>(int)$db->query("SELECT COUNT(*) FROM `{$schema}`.`ledger_evidence_links`")->fetchColumn(),
        'current_comment_checksum'=>(string)$current['Checksum'],'legacy_comment_checksum'=>(string)$legacy['Checksum'],
        'logical_data_hash_before'=>$logicalBefore,'logical_data_hash_after'=>$logicalAfter,'logical_data_unchanged'=>$logicalBefore===$logicalAfter];
} finally {
    $db->exec("DROP DATABASE IF EXISTS `{$schema}`");
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($result['success'] ? 0 : 1);
