<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$db = DbPdo::conn();
$source = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$fixture = 'sukhyang_comment_algorithm_fixture_' . date('YmdHis') . '_' . random_int(1000, 9999);
try {
    $db->exec("CREATE DATABASE `{$fixture}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $db->exec("CREATE TABLE `{$fixture}`.`auth_logs` LIKE `{$source}`.`auth_logs`");
    $db->exec("INSERT INTO `{$fixture}`.`auth_logs` SELECT * FROM `{$source}`.`auth_logs`");
    $db->exec("USE `{$fixture}`");
    $before = (string)$db->query('SHOW CREATE TABLE auth_logs')->fetch(PDO::FETCH_NUM)[1];
    $started = microtime(true);
    $result = ['instant_supported'=>true,'error'=>null];
    try {
        $db->exec("ALTER TABLE `auth_logs` MODIFY COLUMN `id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '인증로그 고유번호', ALGORITHM=INSTANT, LOCK=NONE");
    } catch (Throwable $exception) {
        $result = ['instant_supported'=>false,'error'=>$exception->getCode()];
    }
    $result['elapsed_ms'] = round((microtime(true)-$started)*1000, 2);
    $result['row_count'] = (int)$db->query('SELECT COUNT(*) FROM auth_logs')->fetchColumn();
    $result['before_hash'] = hash('sha256', preg_replace("/ COMMENT '[^']*'/", '', $before));
    $after = (string)$db->query('SHOW CREATE TABLE auth_logs')->fetch(PDO::FETCH_NUM)[1];
    $result['after_hash'] = hash('sha256', preg_replace("/ COMMENT '[^']*'/", '', $after));
    echo json_encode($result + ['fixture_removed'=>true], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), PHP_EOL;
} finally {
    try { $db->exec("USE `{$source}`"); $db->exec("DROP DATABASE IF EXISTS `{$fixture}`"); } catch (Throwable) {}
}
