<?php
declare(strict_types=1);
define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$pdo=DbPdo::conn();
if (($argv[1] ?? 'verify') === 'up') {
    $sql=(string) file_get_contents(PROJECT_ROOT.'/app/migrations/20260814_06_add_user_permission_column_comments.up.sql');
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql))) as $statement) $pdo->exec($statement);
}
$statement=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_COMMENT FROM information_schema.columns
 WHERE table_schema=DATABASE() AND table_name IN ('auth_user_permission_profiles','auth_user_permissions','auth_user_permission_audits')
 ORDER BY TABLE_NAME,ORDINAL_POSITION");
$rows=$statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
echo json_encode(['column_count'=>count($rows),'empty_comment_count'=>count(array_filter($rows,static fn(array $row):bool=>trim((string)$row['COLUMN_COMMENT'])==='')),'columns'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
