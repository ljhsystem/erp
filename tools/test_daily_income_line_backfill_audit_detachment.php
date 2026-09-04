<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/core/Storage.php';
require PROJECT_ROOT . '/core/Database.php';

use App\Models\Institution\DailyEmploymentIncomeModel;

$documentId = 'e8650425-ef60-4bbb-bd5e-88deeeff7f48';
$token = bin2hex(random_bytes(6));
$schema = 'tmp_daily_income_audit_fk_' . $token;
$sourceSchema = 'sukhyang';
$created = false;
$db = null;
$cleanupResult = 'NOT_CREATED';

$load = static function (string $path): ?array {
    if (!is_file($path)) return null;
    $value = require $path;
    return is_array($value) ? $value : null;
};
$topology = $load(PROJECT_ROOT . '/../secure-config/db_replication.php');
$legacy = $load(PROJECT_ROOT . '/../secure-config/db_config.php');
$target = strtolower((string) ($topology['active_target'] ?? ''));
$node = is_array($topology[$target] ?? null) ? $topology[$target] : $legacy;
if (!is_array($node)) throw new RuntimeException('MariaDB 연결설정을 찾을 수 없습니다.');
$config = [
    'host' => (string) ($node['host'] ?? ''),
    'port' => (int) ($node['port'] ?? 3306),
    'user' => (string) ($node['user'] ?? ''),
    'pass' => (string) ($node['pass'] ?? ''),
];
$server = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
    $config['user'],
    $config['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$executeSql = static function (PDO $connection, string $path): void {
    $delimiter = ';';
    $buffer = '';
    foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^DELIMITER\s+(.+)$/i', trim($line), $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        $trimmed = rtrim($buffer);
        if (!str_ends_with($trimmed, $delimiter)) continue;
        $statement = trim(substr($trimmed, 0, -strlen($delimiter)));
        if ($statement !== '') $connection->exec($statement);
        $buffer = '';
    }
    if (trim($buffer) !== '') throw new RuntimeException('Migration SQL 구분자가 닫히지 않았습니다.');
};
$hashRows = static function (PDO $connection, string $sql): string {
    $rows = $connection->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
};
$guard = static function (PDO $connection) use ($schema): void {
    if ($connection->query('SELECT DATABASE()')->fetchColumn() !== $schema
        || !preg_match('/^tmp_daily_income_audit_fk_[a-f0-9]{12}$/', $schema)) {
        throw new RuntimeException('격리 Schema 소유권 Guard가 실행을 차단했습니다.');
    }
    $marker = $connection->query('SELECT unique_token FROM _test_schema_ownership LIMIT 1')->fetchColumn();
    if ($marker !== substr($schema, -12)) throw new RuntimeException('격리 Schema Marker가 일치하지 않습니다.');
};

try {
    $server->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $created = true;
    $db = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $schema),
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $db->exec('CREATE TABLE _test_schema_ownership (unique_token CHAR(12) NOT NULL, source_schema VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB');
    $marker = $db->prepare('INSERT INTO _test_schema_ownership VALUES (:token,:source,NOW())');
    $marker->execute(['token' => $token, 'source' => $sourceSchema]);
    $guard($db);

    $tables = [
        'institution_daily_employment_incomes',
        'institution_daily_employment_income_groups',
        'institution_daily_employment_income_items',
        'institution_daily_employment_income_workdays',
        'institution_daily_employment_income_lines',
        'institution_daily_employment_income_line_backfill_audits',
    ];
    foreach ($tables as $table) {
        $db->exec("CREATE TABLE `{$table}` LIKE `{$sourceSchema}`.`{$table}`");
        $columnStatement = $server->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME=:table AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION"
        );
        $columnStatement->execute(['schema' => $sourceSchema, 'table' => $table]);
        $columns = $columnStatement->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $columnList = implode(',', array_map(static fn(string $column): string => "`{$column}`", $columns));
        $db->exec("INSERT INTO `{$table}` ({$columnList}) SELECT {$columnList} FROM `{$sourceSchema}`.`{$table}`");
    }
    $db->exec('ALTER TABLE institution_daily_employment_income_line_backfill_audits ADD CONSTRAINT fk_daily_income_line_backfill_line FOREIGN KEY (daily_employment_income_line_id) REFERENCES institution_daily_employment_income_lines(id) ON DELETE RESTRICT ON UPDATE CASCADE');

    $auditSql = 'SELECT id,migration_id,daily_employment_income_line_id,previous_snapshot,new_snapshot,decision_rule_code,decision_basis_id,payload_hash,verification_status_code,executed_at,executed_by FROM institution_daily_employment_income_line_backfill_audits ORDER BY id';
    $lineSql = 'SELECT id,daily_employment_income_item_id,daily_employment_income_workday_id,line_type_code,line_code,calculated_amount,final_amount,application_status_code FROM institution_daily_employment_income_lines ORDER BY id';
    $auditCountBefore = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits')->fetchColumn();
    $lineCountBefore = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_lines')->fetchColumn();
    $auditHashBefore = $hashRows($db, $auditSql);
    $lineHashBefore = $hashRows($db, $lineSql);
    if ($auditCountBefore !== 32 || $lineCountBefore !== 32) throw new RuntimeException('격리 Baseline 건수가 운영 기준과 다릅니다.');

    $model = new DailyEmploymentIncomeModel($db);
    $restrictReproduced = false;
    $db->beginTransaction();
    try {
        $model->replaceAggregate($documentId, [], 'SYSTEM:TMP_AUDIT_FK_TEST');
    } catch (PDOException $exception) {
        $restrictReproduced = str_contains($exception->getMessage(), 'fk_daily_income_line_backfill_line');
    } finally {
        if ($db->inTransaction()) $db->rollBack();
    }
    if (!$restrictReproduced) throw new RuntimeException('Audit FK의 Aggregate Line 교체 차단을 재현하지 못했습니다.');

    $guard($db);
    $executeSql($db, PROJECT_ROOT . '/app/migrations/20260829_09_detach_daily_income_line_backfill_audit_from_mutable_lines.up.sql');
    if ($auditHashBefore !== $hashRows($db, $auditSql) || $lineHashBefore !== $hashRows($db, $lineSql)) {
        throw new RuntimeException('Forward Migration이 Audit 또는 Line 업무자료를 변경했습니다.');
    }

    $db->beginTransaction();
    $model->replaceAggregate($documentId, [], 'SYSTEM:TMP_AUDIT_FK_TEST');
    $db->commit();
    $auditCountAfter = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits')->fetchColumn();
    $auditHashAfter = $hashRows($db, $auditSql);
    $orphanCount = (int) $db->query('SELECT COUNT(*) FROM institution_daily_employment_income_line_backfill_audits a LEFT JOIN institution_daily_employment_income_lines l ON l.id=a.daily_employment_income_line_id WHERE l.id IS NULL')->fetchColumn();
    if ($auditCountAfter !== 32 || $auditHashAfter !== $auditHashBefore || $orphanCount !== 32) {
        throw new RuntimeException('Aggregate 교체 후 Audit 독립 Snapshot 보존 검증에 실패했습니다.');
    }

    $downBlocked = false;
    try {
        $executeSql($db, PROJECT_ROOT . '/app/migrations/20260829_09_detach_daily_income_line_backfill_audit_from_mutable_lines.down.sql');
    } catch (PDOException $exception) {
        $downBlocked = (string) $exception->getCode() === '45000';
    }
    if (!$downBlocked) throw new RuntimeException('원 Line 삭제 후 Down 차단을 확인하지 못했습니다.');

    echo json_encode([
        'success' => true,
        'schema' => $schema,
        'marker' => $token,
        'restrict_reproduced' => true,
        'forward_migration' => 'PASS',
        'official_aggregate_replace' => 'PASS',
        'audit_count_before' => $auditCountBefore,
        'audit_count_after' => $auditCountAfter,
        'audit_hash_before' => $auditHashBefore,
        'audit_hash_after' => $auditHashAfter,
        'original_line_id_snapshot_count' => $orphanCount,
        'down_guard' => 'PASS',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    if ($created) {
        if ($db instanceof PDO) $guard($db);
        $db = null;
        $server->exec("DROP DATABASE `{$schema}`");
        $cleanupResult = (int) $server->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=" . $server->quote($schema))->fetchColumn() === 0
            ? 'DROPPED'
            : 'RESIDUAL';
    }
    echo json_encode(['cleanup' => $cleanupResult, 'schema' => $schema], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
