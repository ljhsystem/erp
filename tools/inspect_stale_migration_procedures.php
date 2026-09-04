<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$pdo = DbPdo::conn();
$names = [
    'migrate_20260824_05_extend_journal_rule_learning_ssot',
    'migrate_20260825_04_regular_income_generation',
];
$quotedNames = implode(',', array_map([$pdo, 'quote'], $names));
$sql = "SELECT ROUTINE_NAME, ROUTINE_TYPE, DEFINER, SECURITY_TYPE, CREATED, LAST_ALTERED,
               SHA2(ROUTINE_DEFINITION, 256) AS body_sha256,
               CHAR_LENGTH(ROUTINE_DEFINITION) AS body_length
          FROM information_schema.ROUTINES
         WHERE ROUTINE_SCHEMA = DATABASE()
           AND ROUTINE_NAME IN ({$quotedNames})
         ORDER BY ROUTINE_NAME";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rows as &$row) {
    $name = (string) $row['ROUTINE_NAME'];
    $create = $pdo->query("SHOW CREATE PROCEDURE `{$name}`")->fetch(PDO::FETCH_ASSOC) ?: [];
    $definition = (string) ($create['Create Procedure'] ?? '');
    $row['show_create_sha256'] = hash('sha256', $definition);
    $row['show_create_length'] = strlen($definition);
}
unset($row);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
