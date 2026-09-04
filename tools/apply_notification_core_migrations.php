<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$files = [
    PROJECT_ROOT . '/app/migrations/20260821_06_create_notification_core.up.sql',
    PROJECT_ROOT . '/app/migrations/20260821_07_backfill_notification_core.up.sql',
    PROJECT_ROOT . '/app/migrations/20260821_08_add_notification_feed_index.up.sql',
    PROJECT_ROOT . '/app/migrations/20260821_09_sync_notification_center_registry.up.sql',
    PROJECT_ROOT . '/app/migrations/20260821_10_normalize_notification_action_targets.up.sql',
    PROJECT_ROOT . '/app/migrations/20260821_11_correct_notification_center_registry.up.sql',
];
$result = [];
foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException('Notification Migration 파일을 읽을 수 없습니다.');
    $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])));
    foreach ($statements as $statement) $db->exec($statement);
    $result[basename($file)] = count($statements);
}
echo json_encode(['success' => true, 'migrations' => $result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
