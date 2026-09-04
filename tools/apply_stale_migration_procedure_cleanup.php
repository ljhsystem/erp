<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Database.php';
require PROJECT_ROOT . '/core/DbPdo.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Backup\DatabaseBackupService;
use Core\DbPdo;

$mode = strtolower((string) ($argv[1] ?? 'preflight'));
if (!in_array($mode, ['preflight', 'apply', 'verify'], true)) {
    throw new InvalidArgumentException('사용법: php tools/apply_stale_migration_procedure_cleanup.php [preflight|apply|verify]');
}

$pdo = DbPdo::conn();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$migration = '20260826_04_remove_stale_migration_procedures';
$migrationFile = PROJECT_ROOT . '/app/migrations/' . $migration . '.up.sql';
$targets = [
    'migrate_20260824_05_extend_journal_rule_learning_ssot' => '21be8dfaffb4c55ceb356fcbb13ed3a39f1af8a49317ef68dcd93d04af21512f',
    'migrate_20260825_04_regular_income_generation' => '08f4fb84fc054bff064906508266e3f393378c152551e8419b45e8c1e8741224',
];
$relatedTables = [
    'ledger_journal_rules', 'ledger_journal_learning_events', 'ledger_journal_rule_revisions',
    'ledger_voucher_line_source_refs', 'ledger_transaction_items', 'ledger_transaction_settlements',
    'institution_regular_employment_income_accounting_links', 'institution_regular_employment_incomes',
    'user_approval_requests', 'user_approval_request_steps', 'ledger_evidence_salary_report',
    'ledger_transactions', 'ledger_evidence_links', 'ledger_payment_schedules',
    'ledger_payment_schedule_histories', 'ledger_vouchers', 'ledger_voucher_lines',
];
$requestId = 'e7f37bc9-82d7-4113-bb64-c5b01cf9e0f1';
$documentId = '4d9f970e-c6c5-4a7c-88ff-8a5cfdb47fb6';

$fetchRoutines = static function () use ($pdo): array {
    return $pdo->query("SELECT ROUTINE_NAME,ROUTINE_TYPE,DEFINER,SECURITY_TYPE,CREATED,LAST_ALTERED,SHA2(ROUTINE_DEFINITION,256) body_sha256 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_TYPE,ROUTINE_NAME")->fetchAll(PDO::FETCH_ASSOC) ?: [];
};
$targetState = static function () use ($pdo, $targets): array {
    $result = [];
    foreach ($targets as $name => $expectedHash) {
        $table = $pdo->prepare('SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name');
        $table->execute([':name' => $name]);
        $routine = $pdo->prepare('SELECT ROUTINE_TYPE,DEFINER,SECURITY_TYPE,CREATED,LAST_ALTERED,SHA2(ROUTINE_DEFINITION,256) body_sha256 FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_NAME=:name');
        $routine->execute([':name' => $name]);
        $routineRow = $routine->fetch(PDO::FETCH_ASSOC) ?: null;
        $result[$name] = ['table_type' => $table->fetchColumn() ?: null, 'routine' => $routineRow, 'expected_body_sha256' => $expectedHash];
    }
    return $result;
};
$snapshot = static function () use ($pdo, $relatedTables, $fetchRoutines, $requestId, $documentId): array {
    $tables = [];
    foreach ($relatedTables as $table) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND TABLE_TYPE=\'BASE TABLE\'');
        $exists->execute([':table' => $table]);
        if ((int) $exists->fetchColumn() === 0) {
            $tables[$table] = null;
            continue;
        }
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $tables[$table] = ['rows' => (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(), 'ddl_sha256' => hash('sha256', (string) ($create[1] ?? ''))];
    }
    $request = $pdo->prepare('SELECT id,status,current_step,updated_at FROM user_approval_requests WHERE id=:id');
    $request->execute([':id' => $requestId]);
    $document = $pdo->prepare('SELECT id,document_status,updated_at FROM institution_regular_employment_incomes WHERE id=:id');
    $document->execute([':id' => $documentId]);
    $counts = [];
    $queries = [
        'document_evidence' => 'SELECT COUNT(*) FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:document_id',
        'document_links' => "SELECT COUNT(*) FROM ledger_evidence_links WHERE evidence_type='PAYROLL_REPORT' AND evidence_id IN (SELECT id FROM ledger_evidence_salary_report WHERE source_regular_employment_income_id=:document_id)",
        'document_registry' => 'SELECT COUNT(*) FROM institution_regular_employment_income_accounting_links WHERE regular_employment_income_id=:document_id',
    ];
    foreach ($queries as $key => $sql) {
        $statement = $pdo->prepare($sql);
        $statement->execute([':document_id' => $documentId]);
        $counts[$key] = (int) $statement->fetchColumn();
    }
    return ['tables' => $tables, 'routines' => $fetchRoutines(), 'request' => $request->fetch(PDO::FETCH_ASSOC) ?: null, 'document' => $document->fetch(PDO::FETCH_ASSOC) ?: null, 'closure_counts' => $counts];
};
$assertExpected = static function (array $state, bool $allowAbsent) use ($targets): void {
    foreach ($targets as $name => $hash) {
        $item = $state[$name];
        if ($item['table_type'] !== null) throw new RuntimeException("{$name} 이름으로 TABLE 또는 VIEW가 존재합니다.");
        if ($item['routine'] === null) {
            if ($allowAbsent) continue;
            throw new RuntimeException("{$name} PROCEDURE가 없습니다.");
        }
        if ($item['routine']['ROUTINE_TYPE'] !== 'PROCEDURE' || $item['routine']['DEFINER'] !== 'sukhyang@%' || $item['routine']['body_sha256'] !== $hash) {
            throw new RuntimeException("{$name}의 종류, DEFINER 또는 본문 해시가 기준선과 다릅니다.");
        }
    }
};
$executeSqlFile = static function (string $file) use ($pdo): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) { $delimiter = $match[1]; continue; }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer, "\r\n");
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $pdo->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};

$beforeTargets = $targetState();
if ($mode === 'preflight') {
    $assertExpected($beforeTargets, true);
    echo json_encode(['success' => true, 'mode' => $mode, 'migration' => $migration, 'targets' => $beforeTargets, 'snapshot' => $snapshot()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}
if ($mode === 'verify') {
    foreach ($beforeTargets as $name => $state) {
        if ($state['table_type'] !== null || $state['routine'] !== null) throw new RuntimeException("{$name} 제거 검증에 실패했습니다.");
    }
    echo json_encode(['success' => true, 'mode' => $mode, 'migration' => $migration, 'targets' => $beforeTargets, 'snapshot' => $snapshot()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$assertExpected($beforeTargets, false);
$before = $snapshot();
$backupService = new DatabaseBackupService($pdo);
$backup = $backupService->backupDatabase();
if (empty($backup['success'])) throw new RuntimeException('운영 DB 전체 백업에 실패했습니다.');
$backupPath = rtrim($backupService->getBackupDirectory(), '/\\') . DIRECTORY_SEPARATOR . $backup['filename'];
$backup['sha256'] = hash_file('sha256', $backupPath);
$backup['path'] = $backupPath;
$auditDir = PROJECT_ROOT . '/storage/db_backup';
$stamp = date('Ymd_His');
$showCreate = [];
foreach (array_keys($targets) as $name) {
    $row = $pdo->query("SHOW CREATE PROCEDURE `{$name}`")->fetch(PDO::FETCH_ASSOC) ?: [];
    $definition = (string) ($row['Create Procedure'] ?? '');
    $showCreate[$name] = ['sql' => $definition, 'sha256' => hash('sha256', $definition)];
}
$auditPath = $auditDir . '/' . $migration . '_before_' . $stamp . '.json';
file_put_contents($auditPath, json_encode(['captured_at' => date(DATE_ATOM), 'migration' => $migration, 'migration_sha256' => hash_file('sha256', $migrationFile), 'backup' => $backup, 'targets' => $beforeTargets, 'show_create' => $showCreate, 'snapshot' => $before], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
$executeSqlFile($migrationFile);
$afterTargets = $targetState();
foreach ($afterTargets as $name => $state) {
    if ($state['table_type'] !== null || $state['routine'] !== null) throw new RuntimeException("{$name} 제거 후 검증에 실패했습니다.");
}
$after = $snapshot();
$beforeOtherRoutines = array_values(array_filter($before['routines'], static fn(array $row): bool => !array_key_exists($row['ROUTINE_NAME'], $targets)));
$afterOtherRoutines = array_values(array_filter($after['routines'], static fn(array $row): bool => !array_key_exists($row['ROUTINE_NAME'], $targets)));
if ($before['tables'] !== $after['tables'] || $before['request'] !== $after['request'] || $before['document'] !== $after['document'] || $before['closure_counts'] !== $after['closure_counts'] || $beforeOtherRoutines !== $afterOtherRoutines) {
    throw new RuntimeException('대상 외 스키마, 업무자료 또는 Routine 불변 검증에 실패했습니다.');
}
$result = ['success' => true, 'mode' => $mode, 'migration' => $migration, 'backup' => $backup, 'audit_path' => $auditPath, 'show_create_hashes' => array_map(static fn(array $row): string => $row['sha256'], $showCreate), 'before_targets' => $beforeTargets, 'after_targets' => $afterTargets, 'other_routines_unchanged' => true, 'tables_unchanged' => true, 'business_data_unchanged' => true, 'before' => $before, 'after' => $after];
$resultPath = $auditDir . '/' . $migration . '_result_' . $stamp . '.json';
file_put_contents($resultPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
$result['result_path'] = $resultPath;
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
