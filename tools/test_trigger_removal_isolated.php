<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$source = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$fixture = 'sukhyang_trigger_removal_fixture_' . date('YmdHis') . '_' . random_int(1000, 9999);
if (!preg_match('/^sukhyang_trigger_removal_fixture_\d{14}_\d{4}$/', $fixture)) throw new RuntimeException('격리 DB 이름 검증에 실패했습니다.');
try {
    $db->exec("CREATE DATABASE `{$fixture}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $triggers = $db->query("SELECT TRIGGER_NAME,EVENT_OBJECT_TABLE,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=" . $db->quote($source) . ' ORDER BY TRIGGER_NAME')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($triggers) !== 10) throw new RuntimeException('운영 Baseline Trigger 수가 10이 아닙니다.');
    foreach (array_unique(array_column($triggers, 'EVENT_OBJECT_TABLE')) as $table) {
        $db->exec("CREATE TABLE `{$fixture}`.`{$table}` LIKE `{$source}`.`{$table}`");
    }
    $db->exec("USE `{$fixture}`");
    foreach ($triggers as $trigger) {
        $db->exec('CREATE TRIGGER `' . $trigger['TRIGGER_NAME'] . '` ' . $trigger['ACTION_TIMING'] . ' ' . $trigger['EVENT_MANIPULATION']
            . ' ON `' . $trigger['EVENT_OBJECT_TABLE'] . '` FOR EACH ROW ' . $trigger['ACTION_STATEMENT']);
    }
    $before = (int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
    foreach (file(PROJECT_ROOT . '/app/migrations/20260903_13_remove_statutory_and_business_income_triggers.up.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $sql) $db->exec($sql);
    $after = (int)$db->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn();
    if ($before !== 10 || $after !== 0) throw new RuntimeException('격리 DB Trigger 제거 검증에 실패했습니다.');
    echo json_encode(['success'=>true,'mariadb_version'=>$db->query('SELECT VERSION()')->fetchColumn(),'before'=>$before,'after'=>$after,'fixture_removed'=>true], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    try { $db->exec("USE `{$source}`"); $db->exec("DROP DATABASE IF EXISTS `{$fixture}`"); } catch (Throwable) {}
}
