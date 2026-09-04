<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db = DbPdo::conn();
$migration = PROJECT_ROOT . '/app/migrations/20260821_05_create_education_sessions.up.sql';
$sql = file_get_contents($migration);
if ($sql === false) {
    throw new RuntimeException('교육 Session Migration 파일을 읽을 수 없습니다.');
}

$statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])));
foreach ($statements as $statement) {
    $db->exec($statement);
}

echo json_encode(['success' => true, 'statements' => count($statements)], JSON_UNESCAPED_UNICODE) . PHP_EOL;
