<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use Core\DbPdo;

$mode = $argv[1] ?? '';
if (!in_array($mode, ['--isolated', '--operating'], true)) {
    fwrite(STDERR, "Usage: php tools/apply_database_comment_closure.php --isolated|--operating\n");
    exit(2);
}
$db = DbPdo::conn();
$sourceSchema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
$migrationPath = PROJECT_ROOT . '/app/migrations/20260903_14_complete_database_korean_comments.up.sql';
$sql = (string)file_get_contents($migrationPath);
if (preg_match('/^\s*(?:INSERT|UPDATE|DELETE|DROP|CREATE\s+(?:TRIGGER|PROCEDURE|FUNCTION|EVENT))\b/im', preg_replace('/--[^\n]*/', '', $sql))) {
    throw new RuntimeException('Comment Migration에 금지된 DML 또는 DB 객체 구문이 있습니다.');
}
$executableSql = preg_replace('/^--[^\r\n]*(?:\r?\n|$)/m', '', $sql);
$statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', (string)$executableSql) ?: []), static fn(string $value): bool => $value !== ''));
$tables = [];
preg_match_all('/ALTER\s+TABLE\s+`([^`]+)`/i', $sql, $matches);
$tables = array_values(array_unique($matches[1] ?? []));
$protected = ['main_calendar_events', 'main_calendar_list', 'main_calendar_tasks'];
if (array_intersect($tables, $protected) !== []) throw new RuntimeException('캘린더 보호 테이블이 Migration에 포함됐습니다.');

$columnSignature = static function (PDO $pdo, string $schema, array $targetTables): string {
    $quoted = implode(',', array_fill(0, count($targetTables), '?'));
    $statement = $pdo->prepare("SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,CHARACTER_SET_NAME,COLLATION_NAME,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,GENERATION_EXPRESSION,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ({$quoted}) ORDER BY TABLE_NAME,ORDINAL_POSITION");
    $statement->execute(array_merge([$schema], $targetTables));
    return hash('sha256', json_encode($statement->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
};
$indexSignature = static function (PDO $pdo, string $schema, array $targetTables): string {
    $quoted = implode(',', array_fill(0, count($targetTables), '?'));
    $statement = $pdo->prepare("SELECT TABLE_NAME,NON_UNIQUE,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,COLLATION,SUB_PART,PACKED,NULLABLE,INDEX_TYPE,IGNORED FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ({$quoted}) ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX");
    $statement->execute(array_merge([$schema], $targetTables));
    return hash('sha256', json_encode($statement->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION));
};
$commentIssueCount = static function (PDO $pdo, string $schema, array $targetTables): int {
    $quoted = implode(',', array_fill(0, count($targetTables), '?'));
    $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ({$quoted}) AND (TRIM(COLUMN_COMMENT)='' OR COLUMN_COMMENT NOT REGEXP '[가-힣]' OR COLUMN_COMMENT REGEXP '硫|媛|湲|�' OR LOCATE('?뚯',COLUMN_COMMENT)>0 OR LOCATE('?뱀',COLUMN_COMMENT)>0 OR LOCATE('?붿',COLUMN_COMMENT)>0 OR LOCATE('?꾩',COLUMN_COMMENT)>0 OR LOCATE('?대',COLUMN_COMMENT)>0 OR LOCATE('?놁',COLUMN_COMMENT)>0)");
    $statement->execute(array_merge([$schema], $targetTables));
    return (int)$statement->fetchColumn();
};
$snapshot = static function (PDO $pdo, string $schema, array $allTables) use ($columnSignature, $indexSignature): array {
    $schemaRows = [];
    foreach ([
        "SELECT TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME",
        "SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,CONSTRAINT_NAME",
        "SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION",
        "SELECT CONSTRAINT_NAME,CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? ORDER BY CONSTRAINT_NAME",
        "SELECT TABLE_NAME,VIEW_DEFINITION,CHECK_OPTION,IS_UPDATABLE,SECURITY_TYPE FROM information_schema.VIEWS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME",
    ] as $signatureSql) {
        $statement = $pdo->prepare($signatureSql); $statement->execute([$schema]);
        $schemaRows[] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    $result = ['schema'=>$schema,'column_signature'=>$columnSignature($pdo,$schema,$allTables),'index_signature'=>$indexSignature($pdo,$schema,$allTables),
        'schema_object_signature'=>hash('sha256',json_encode($schemaRows,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)),'trigger_count'=>0,'tables'=>[]];
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=?');
    $statement->execute([$schema]); $result['trigger_count'] = (int)$statement->fetchColumn();
    foreach ($allTables as $table) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$schema}`.`{$table}`")->fetchColumn();
        $checksum = $pdo->query("CHECKSUM TABLE `{$schema}`.`{$table}`")->fetch(PDO::FETCH_ASSOC);
        $result['tables'][$table] = ['row_count'=>$count,'checksum'=>(string)($checksum['Checksum'] ?? '')];
    }
    return $result;
};

if ($mode === '--isolated') {
    $testSchema = 'codex_comment_ssot_' . getmypid();
    $db->exec("CREATE DATABASE `{$testSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    try {
        foreach ($tables as $table) $db->exec("CREATE TABLE `{$testSchema}`.`{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        $beforeColumns = $columnSignature($db, $testSchema, $tables);
        $beforeIndexes = $indexSignature($db, $testSchema, $tables);
        $db->exec("USE `{$testSchema}`");
        $started = microtime(true);
        foreach ($statements as $index => $statement) {
            try { $db->exec($statement); }
            catch (Throwable $error) { throw new RuntimeException('격리 Migration 문장 ' . ($index + 1) . ' 실패: ' . substr($statement, 0, 100), 0, $error); }
        }
        $elapsed = round((microtime(true)-$started)*1000, 2);
        $result = ['success'=>$beforeColumns===$columnSignature($db,$testSchema,$tables) && $beforeIndexes===$indexSignature($db,$testSchema,$tables) && $commentIssueCount($db,$testSchema,$tables)===0,
            'mariadb_version'=>(string)$db->query('SELECT VERSION()')->fetchColumn(),'schema'=>$testSchema,'statement_count'=>count($statements),'table_count'=>count($tables),
            'elapsed_ms'=>$elapsed,'comment_issue_count'=>$commentIssueCount($db,$testSchema,$tables),'column_signature_unchanged'=>$beforeColumns===$columnSignature($db,$testSchema,$tables),
            'index_signature_unchanged'=>$beforeIndexes===$indexSignature($db,$testSchema,$tables),'trigger_count'=>(int)$db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='{$testSchema}'")->fetchColumn()];
    } finally {
        $db->exec("DROP DATABASE IF EXISTS `{$testSchema}`");
        $db->exec("USE `{$sourceSchema}`");
    }
    echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($result['success'] ? 0 : 1);
}

$allTables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$before = $snapshot($db, $sourceSchema, $allTables);
$calendarBefore = array_intersect_key($before['tables'], array_flip($protected));
$settingsBefore = (int)$db->query("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE'")->fetchColumn();
$started = microtime(true);
foreach ($statements as $statement) $db->exec($statement);
$elapsed = round((microtime(true)-$started)*1000, 2);
$after = $snapshot($db, $sourceSchema, $allTables);
$settingsAfter = (int)$db->query("SELECT COUNT(*) FROM system_user_settings WHERE setting_type='TABLE'")->fetchColumn();
$result = ['success'=>$before['column_signature']===$after['column_signature'] && $before['index_signature']===$after['index_signature'] && $before['schema_object_signature']===$after['schema_object_signature'] && $before['tables']===$after['tables'] && $before['trigger_count']===0 && $after['trigger_count']===0 && $settingsBefore===$settingsAfter && $commentIssueCount($db,$sourceSchema,$tables)===0,
    'schema'=>$sourceSchema,'statement_count'=>count($statements),'elapsed_ms'=>$elapsed,'comment_issue_count'=>$commentIssueCount($db,$sourceSchema,$tables),
    'comment_excluded_column_signature_unchanged'=>$before['column_signature']===$after['column_signature'],'index_signature_unchanged'=>$before['index_signature']===$after['index_signature'],
    'constraints_tables_views_signature_unchanged'=>$before['schema_object_signature']===$after['schema_object_signature'],
    'row_counts_and_checksums_unchanged'=>$before['tables']===$after['tables'],'trigger_before'=>$before['trigger_count'],'trigger_after'=>$after['trigger_count'],
    'calendar_unchanged'=>$calendarBefore===array_intersect_key($after['tables'],array_flip($protected)),'table_settings_before'=>$settingsBefore,'table_settings_after'=>$settingsAfter];
$backupPath = PROJECT_ROOT . '/storage/db_backup/database_comment_ssot_20260903_result.json';
file_put_contents($backupPath, json_encode(['before'=>$before,'after'=>$after,'result'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($result['success'] ? 0 : 1);
