<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';

use Core\DbPdo;

$source = DbPdo::conn();
$source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$source->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$sourceDatabase = (string) $source->query('SELECT DATABASE()')->fetchColumn();
$database = 'tmp_stale_routine_' . bin2hex(random_bytes(5));
$definitions = [];
foreach (['migrate_20260824_05_extend_journal_rule_learning_ssot', 'migrate_20260825_04_regular_income_generation'] as $name) {
    $row = $source->query("SHOW CREATE PROCEDURE `{$name}`")->fetch(PDO::FETCH_ASSOC) ?: [];
    $definitions[$name] = (string) ($row['Create Procedure'] ?? '');
}
$source->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$executeFile = static function (PDO $pdo, string $file): void {
    $delimiter = ';'; $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n"; $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $sql = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($sql !== '') $pdo->exec($sql);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('테스트 Migration SQL 구분자가 닫히지 않았습니다.');
};
$expectFailure = static function (callable $callback): string {
    try { $callback(); } catch (Throwable $exception) { return $exception->getMessage(); }
    throw new RuntimeException('예상한 Guard 실패가 발생하지 않았습니다.');
};

try {
    $pdo = $source;
    $pdo->exec("USE `{$database}`");
    $names = ['migrate_20260824_05_extend_journal_rule_learning_ssot', 'migrate_20260825_04_regular_income_generation'];
    foreach ($names as $name) {
        $pdo->exec($definitions[$name]);
    }
    $pdo->exec('CREATE TABLE business_guard (id INT PRIMARY KEY, value_text VARCHAR(30))');
    $pdo->exec("INSERT INTO business_guard VALUES (1,'unchanged')");
    $pdo->exec('CREATE PROCEDURE preserved_procedure() SELECT 1');
    $pdo->exec('CREATE FUNCTION preserved_function() RETURNS INT DETERMINISTIC RETURN 1');
    $pdo->exec('CREATE VIEW preserved_view AS SELECT id,value_text FROM business_guard');
    $beforeTable = $pdo->query('SHOW CREATE TABLE business_guard')->fetch(PDO::FETCH_NUM)[1] ?? '';
    $beforeData = $pdo->query('SELECT * FROM business_guard ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $file = PROJECT_ROOT . '/app/migrations/20260826_04_remove_stale_migration_procedures.up.sql';
    $executeFile($pdo, $file);
    $remainingTargets = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME IN ('" . implode("','", $names) . "')")->fetchColumn();
    $executeFile($pdo, $file);
    $otherObjects = [
        'procedure' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='preserved_procedure' AND ROUTINE_TYPE='PROCEDURE'")->fetchColumn(),
        'function' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='preserved_function' AND ROUTINE_TYPE='FUNCTION'")->fetchColumn(),
        'view' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='preserved_view' AND TABLE_TYPE='VIEW'")->fetchColumn(),
    ];
    $afterTable = $pdo->query('SHOW CREATE TABLE business_guard')->fetch(PDO::FETCH_NUM)[1] ?? '';
    $afterData = $pdo->query('SELECT * FROM business_guard ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $downError = $expectFailure(static fn() => $pdo->exec((string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260826_04_remove_stale_migration_procedures.down.sql')));

    $pdo->exec("CREATE VIEW `{$names[0]}` AS SELECT id FROM business_guard");
    $viewGuardError = $expectFailure(static fn() => $executeFile($pdo, $file));
    $pdo->exec('DROP PROCEDURE IF EXISTS cleanup_20260826_04_stale_migration_procedures');
    $pdo->exec("DROP VIEW `{$names[0]}`");

    $pdo->exec("CREATE PROCEDURE `{$names[0]}`() SELECT 999");
    $hashGuardError = $expectFailure(static fn() => $executeFile($pdo, $file));
    $changedProcedurePreserved = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME='{$names[0]}' AND ROUTINE_TYPE='PROCEDURE'")->fetchColumn();
    $pdo->exec('DROP PROCEDURE IF EXISTS cleanup_20260826_04_stale_migration_procedures');

    $journalTool = (string) file_get_contents(PROJECT_ROOT . '/tools/apply_journal_learning_ssot_migrations.php');
    $regularTool = (string) file_get_contents(PROJECT_ROOT . '/tools/apply_regular_income_accounting_generation_recovery.php');
    $journalMigration = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260824_05_extend_journal_rule_learning_ssot.up.sql');
    $regularMigration = (string) file_get_contents(PROJECT_ROOT . '/app/migrations/20260825_04_extend_regular_income_accounting_generation_identity.up.sql');
    $normalContracts = [
        'journal_create_call_drop' => preg_match('/CREATE PROCEDURE `?migrate_20260824_05_extend_journal_rule_learning_ssot`?/', $journalMigration) === 1 && preg_match('/CALL `?migrate_20260824_05_extend_journal_rule_learning_ssot`?\(\)/', $journalMigration) === 1 && preg_match('/DROP PROCEDURE `?migrate_20260824_05_extend_journal_rule_learning_ssot`?/', $journalMigration) === 1,
        'regular_create_call_drop' => preg_match('/CREATE PROCEDURE `?migrate_20260825_04_regular_income_generation`?/', $regularMigration) === 1 && preg_match('/CALL `?migrate_20260825_04_regular_income_generation`?\(\)/', $regularMigration) === 1 && preg_match('/DROP PROCEDURE `?migrate_20260825_04_regular_income_generation`?/', $regularMigration) === 1,
        'journal_recovery_guarded_cleanup' => str_contains($journalTool, '$resumeStructureComplete') && str_contains($journalTool, 'DROP PROCEDURE') && !str_contains($journalTool, 'CALL migrate_20260824_05'),
        'regular_recovery_guarded_cleanup' => str_contains($regularTool, '$mode === \'up\' && $applied') && str_contains($regularTool, 'DROP PROCEDURE') && !str_contains($regularTool, 'CALL migrate_20260825_04'),
    ];
    $success = $remainingTargets === 0 && !in_array(0, $otherObjects, true) && $beforeTable === $afterTable && $beforeData === $afterData && $changedProcedurePreserved === 1 && !in_array(false, $normalContracts, true);
    echo json_encode(['success' => $success, 'database' => $database, 'targets_after_up' => $remainingTargets, 'up_reexecution' => true, 'other_objects' => $otherObjects, 'business_table_ddl_unchanged' => $beforeTable === $afterTable, 'business_data_unchanged' => $beforeData === $afterData, 'down_blocked' => $downError, 'view_guard_blocked' => $viewGuardError, 'hash_guard_blocked' => $hashGuardError, 'changed_procedure_preserved' => $changedProcedurePreserved === 1, 'contracts' => $normalContracts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($success ? 0 : 1);
} finally {
    $source->exec("USE `{$sourceDatabase}`");
    $source->exec("DROP DATABASE IF EXISTS `{$database}`");
}
