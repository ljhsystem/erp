<?php

namespace App\Services\Backup;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;
use function Core\storage_system_path;

class DatabaseSyncService
{
    private const STATUS_STALE_SECONDS = 300;
    private const PROGRESS_INTERVAL = 50;
    private const TRACE_RECENT_SECONDS = 120;

    private readonly PDO $pdo;
    private readonly string $backupDir;
    private readonly DateTimeZone $timezone;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->timezone = new DateTimeZone('Asia/Seoul');

        $backupPath = storage_system_path('db_backup');
        if (!$backupPath) {
            throw new \RuntimeException('DB backup storage path not configured.');
        }

        $this->backupDir = rtrim(str_replace('\\', '/', $backupPath), '/') . '/';
        $this->ensureBackupDir();
    }

    public function runLatestBackupSync(string $trigger = 'sync'): array
    {
        $startedAt = $this->now()->format('Y-m-d H:i:s');
        $latestPath = $this->findLatestBackupFile();

        if ($this->isRestoreRunning()) {
            return $this->finalizeImmediateFailure(
                $startedAt,
                'restore-running',
                'DB 복원 진행 중에는 동기화를 실행할 수 없습니다.'
            );
        }

        if (!$latestPath) {
            return $this->finalizeImmediateFailure(
                $startedAt,
                'no-backup-file',
                '동기화에 사용할 SQL 백업 파일이 없습니다.'
            );
        }

        $dbPair = $this->resolveDatabasePair();
        $dbName = $this->getCurrentDatabaseName();
        $latestFile = basename($latestPath);
        $standbyConfig = $dbPair['standby']['config'];
        $snapshotPath = null;
        $snapshotCreated = false;
        $dropCompleted = false;
        $standbyPdo = null;

        $status = [
            'success' => false,
            'state' => 'running',
            'message' => '대기 DB 동기화를 준비하고 있습니다.',
            'trigger' => $trigger,
            'file' => $latestFile,
            'started_at' => $startedAt,
            'updated_at' => $startedAt,
            'stage' => 'starting',
            'statement_count' => 0,
            'snapshot_created' => false,
            'snapshot_file' => null,
            'rollback_attempted' => false,
            'rollback_success' => false,
            'rollback_message' => null,
        ];

        $this->appendDatabasePair($status, $dbPair);
        $this->writeSyncStatus($status);
        $this->writeSyncTrace('RUNNING', 'starting', $status['message'], $latestFile);

        try {
            $this->writeProgress(
                $latestFile,
                $startedAt,
                'load-standby-config',
                '대기 DB 설정을 확인하고 있습니다.',
                $dbPair,
                ['statement_count' => 0]
            );

            $this->writeProgress(
                $latestFile,
                $startedAt,
                'connect-standby',
                '대기 DB 연결을 확인하고 있습니다.',
                $dbPair,
                ['statement_count' => 0]
            );

            $standbyPdo = $this->connectDatabasePdo($standbyConfig, $dbName);

            $this->writeProgress(
                $latestFile,
                $startedAt,
                'snapshot-standby',
                '대기 DB 스냅샷을 생성하고 있습니다.',
                $dbPair,
                ['statement_count' => 0]
            );

            $snapshotPath = $this->createStandbySnapshot($standbyPdo, $dbName);
            $snapshotCreated = true;
            $snapshotFile = basename($snapshotPath);

            $this->writeProgress(
                $latestFile,
                $startedAt,
                'prepare-standby',
                '대기 DB의 기존 테이블을 정리하고 있습니다.',
                $dbPair,
                [
                    'snapshot_created' => true,
                    'snapshot_file' => $snapshotFile,
                    'statement_count' => 0,
                ]
            );

            $this->dropAllTables($standbyPdo);
            $dropCompleted = true;

            $this->writeProgress(
                $latestFile,
                $startedAt,
                'apply-backup',
                '최신 SQL 백업 파일을 대기 DB에 적용하고 있습니다.',
                $dbPair,
                [
                    'snapshot_created' => true,
                    'snapshot_file' => $snapshotFile,
                    'statement_count' => 0,
                ]
            );

            $summary = $this->applyBackupFileToDatabase(
                $latestPath,
                $standbyPdo,
                function (int $statementCount) use ($latestFile, $startedAt, $dbPair, $snapshotFile): void {
                    $this->writeProgress(
                        $latestFile,
                        $startedAt,
                        'apply-backup',
                        '최신 SQL 백업 파일을 대기 DB에 적용하고 있습니다.',
                        $dbPair,
                        [
                            'snapshot_created' => true,
                            'snapshot_file' => $snapshotFile,
                            'statement_count' => $statementCount,
                        ]
                    );
                }
            );

            if ($snapshotPath && is_file($snapshotPath)) {
                @unlink($snapshotPath);
            }

            $finishedAt = $this->now()->format('Y-m-d H:i:s');
            $result = [
                'success' => true,
                'state' => 'success',
                'message' => 'DB 동기화가 완료되었습니다.',
                'file' => $latestFile,
                'trigger' => $trigger,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'updated_at' => $finishedAt,
                'stage' => 'completed',
                'applied_file' => $latestFile,
                'applied_at' => $finishedAt,
                'applied_result' => 'success',
                'applied_source' => $trigger,
                'latest_backup_file_at_apply' => $latestFile,
                'statement_count' => $summary['statement_count'],
                'snapshot_created' => true,
                'snapshot_file' => $snapshotFile,
                'rollback_attempted' => false,
                'rollback_success' => false,
                'rollback_message' => null,
            ];

            $this->appendDatabasePair($result, $dbPair);
            $this->writeSyncStatus($result);
            $this->writeSyncLog($result);
            $this->writeSyncTrace('SUCCESS', 'completed', $result['message'], $latestFile, [
                'statement_count' => $summary['statement_count'],
                'target' => $dbPair['standby']['label'] ?? null,
                'snapshot_file' => $snapshotFile,
            ]);

            return $result;
        } catch (Throwable $e) {
            $rollbackResult = [
                'attempted' => false,
                'success' => false,
                'message' => '롤백을 시도하지 않았습니다.',
            ];

            if ($dropCompleted && $snapshotCreated && $snapshotPath && $standbyPdo instanceof PDO) {
                $rollbackResult = $this->rollbackStandbyFromSnapshot(
                    $snapshotPath,
                    $standbyPdo,
                    $latestFile,
                    $startedAt,
                    $dbPair
                );
            }

            $finishedAt = $this->now()->format('Y-m-d H:i:s');
            $failed = [
                'success' => false,
                'state' => 'failed',
                'message' => 'DB 동기화에 실패했습니다.',
                'error' => $e->getMessage(),
                'file' => $latestFile,
                'trigger' => $trigger,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'updated_at' => $finishedAt,
                'stage' => $dropCompleted ? 'rollback-standby' : $this->readCurrentStage(),
                'snapshot_created' => $snapshotCreated,
                'snapshot_file' => $snapshotCreated && $snapshotPath ? basename($snapshotPath) : null,
                'rollback_attempted' => $rollbackResult['attempted'],
                'rollback_success' => $rollbackResult['success'],
                'rollback_message' => $rollbackResult['message'],
                'statement_count' => (int) (($this->readSyncStatus()['statement_count'] ?? 0)),
            ];

            $this->appendDatabasePair($failed, $dbPair);
            $this->writeSyncStatus($failed);
            $this->writeSyncLog($failed);
            $this->writeSyncTrace('FAILED', (string) $failed['stage'], $failed['message'], $latestFile, [
                'error' => $e->getMessage(),
                'target' => $dbPair['standby']['label'] ?? null,
                'rollback_attempted' => $rollbackResult['attempted'],
                'rollback_success' => $rollbackResult['success'],
                'rollback_message' => $rollbackResult['message'],
            ]);

            if ($snapshotCreated && $rollbackResult['success'] && $snapshotPath && is_file($snapshotPath)) {
                @unlink($snapshotPath);
            }

            return $failed;
        }
    }

    public function getLatestSyncInfo(): array
    {
        $status = $this->readSyncStatus();
        if (!$status) {
            return $this->appendSyncState([
                'state' => 'idle',
                'message' => 'DB 동기화 이력이 없습니다.',
                'statement_count' => 0,
                'snapshot_created' => false,
                'snapshot_file' => null,
                'rollback_attempted' => false,
                'rollback_success' => false,
                'rollback_message' => null,
                'runtime' => $this->inspectSyncRuntime(),
            ]);
        }

        $status = $this->normalizeAppliedStatus($status);
        $status['runtime'] = $this->inspectSyncRuntime();

        if (($status['state'] ?? '') === 'running' && $this->isStatusStale($status)) {
            if ($status['runtime']['runner_active'] || $status['runtime']['trace_recent']) {
                $status['stale'] = false;
            } else {
                $status['state'] = 'stale_suspected';
                $status['message'] = '동기화 상태 갱신이 멈춰 상태 확인이 필요합니다.';
                $status['stage'] = 'stale-suspected';
                $status['stale'] = true;
            }
        }

        return $this->appendSyncState($status);
    }

    private function finalizeImmediateFailure(string $startedAt, string $stage, string $message): array
    {
        $finishedAt = $this->now()->format('Y-m-d H:i:s');

        $result = [
            'success' => false,
            'state' => 'failed',
            'message' => $message,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'updated_at' => $finishedAt,
            'stage' => $stage,
            'statement_count' => 0,
            'snapshot_created' => false,
            'snapshot_file' => null,
            'rollback_attempted' => false,
            'rollback_success' => false,
            'rollback_message' => null,
        ];

        $this->appendDatabasePair($result);
        $this->writeSyncStatus($result);
        $this->writeSyncLog($result);

        return $result;
    }

    private function applyBackupFileToDatabase(string $path, PDO $pdo, ?callable $progressCallback = null): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('SQL 백업 파일을 찾을 수 없습니다.');
        }

        $statementCount = 0;
        foreach ($this->readStatementsFromDump($path) as $statement) {
            $pdo->exec($statement);
            $statementCount++;

            if (($statementCount % self::PROGRESS_INTERVAL) === 0) {
                $this->writeSyncTrace('RUNNING', 'apply-backup', 'SQL 구문을 순차 적용하고 있습니다.', basename($path), [
                    'statement_count' => $statementCount,
                ]);

                if ($progressCallback) {
                    $progressCallback($statementCount);
                }
            }
        }

        if ($progressCallback) {
            $progressCallback($statementCount);
        }

        return ['statement_count' => $statementCount];
    }

    private function readStatementsFromDump(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('SQL 백업 파일을 열 수 없습니다.');
        }

        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $escaped = false;

        try {
            while (($line = fgets($handle)) !== false) {
                if ($buffer === '' && $this->isIgnorableSqlLine($line)) {
                    continue;
                }

                $lineLength = strlen($line);
                for ($index = 0; $index < $lineLength; $index++) {
                    $char = $line[$index];
                    $buffer .= $char;

                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }

                    if ($inSingleQuote) {
                        if ($char === '\\') {
                            $escaped = true;
                        } elseif ($char === '\'') {
                            $inSingleQuote = false;
                        }
                        continue;
                    }

                    if ($inDoubleQuote) {
                        if ($char === '\\') {
                            $escaped = true;
                        } elseif ($char === '"') {
                            $inDoubleQuote = false;
                        }
                        continue;
                    }

                    if ($inBacktick) {
                        if ($char === '`') {
                            $inBacktick = false;
                        }
                        continue;
                    }

                    if ($char === '\'') {
                        $inSingleQuote = true;
                        continue;
                    }

                    if ($char === '"') {
                        $inDoubleQuote = true;
                        continue;
                    }

                    if ($char === '`') {
                        $inBacktick = true;
                        continue;
                    }

                    if ($char === ';') {
                        $statement = trim($buffer);
                        $buffer = '';

                        if ($statement !== '') {
                            yield $statement;
                        }
                    }
                }
            }

            $tail = trim($buffer);
            if ($tail !== '') {
                yield $tail;
            }
        } finally {
            fclose($handle);
        }
    }

    private function isIgnorableSqlLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return true;
        }

        if (str_starts_with($trimmed, '-- ')) {
            return true;
        }

        return str_starts_with($trimmed, '/*') && str_ends_with($trimmed, '*/');
    }

    private function ensureBackupDir(): void
    {
        if (is_dir($this->backupDir)) {
            return;
        }

        if (!@mkdir($this->backupDir, 0777, true) && !is_dir($this->backupDir)) {
            throw new \RuntimeException('DB backup directory could not be created.');
        }
    }

    private function getCurrentDatabaseName(): string
    {
        $this->pdo->exec('SET NAMES utf8mb4');
        $dbName = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            throw new \RuntimeException('현재 데이터베이스 이름을 확인할 수 없습니다.');
        }

        return $dbName;
    }

    private function findLatestBackupFile(): ?string
    {
        $dbName = $this->getCurrentDatabaseName();
        $files = glob($this->backupDir . $dbName . '_*.sql') ?: [];
        if (!$files) {
            return null;
        }

        usort($files, static fn(string $left, string $right): int => filemtime($right) <=> filemtime($left));
        return $files[0];
    }

    private function resolveDatabasePair(): array
    {
        $replication = $this->loadReplicationConfig();
        $activeConfig = $this->loadActiveConfig();

        $primary = $replication['primary'] ?? [];
        $secondary = $replication['secondary'] ?? [];

        $activeRole = $this->resolveTopologyRole($activeConfig, $primary, $secondary);
        if ($activeRole === 'PRIMARY') {
            return [
                'active' => $this->buildNodeInfo('ACTIVE', 'PRIMARY', $activeConfig, $primary),
                'standby' => $this->buildNodeInfo('STANDBY', 'SECONDARY', $secondary, $secondary),
            ];
        }

        return [
            'active' => $this->buildNodeInfo('ACTIVE', 'SECONDARY', $activeConfig, $secondary),
            'standby' => $this->buildNodeInfo('STANDBY', 'PRIMARY', $primary, $primary),
        ];
    }

    private function loadReplicationConfig(): array
    {
        $configPath = PROJECT_ROOT . '/../secure-config/db_replication.php';
        if (!is_file($configPath)) {
            throw new \RuntimeException('DB replication config file was not found.');
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new \RuntimeException('DB replication config format is invalid.');
        }

        return $config;
    }

    private function loadActiveConfig(): array
    {
        $replication = $this->loadReplicationConfig();
        $legacyPath = PROJECT_ROOT . '/../secure-config/db_config.php';
        $legacy = null;

        if (is_file($legacyPath)) {
            $legacyConfig = require $legacyPath;
            if (is_array($legacyConfig)) {
                $legacy = $legacyConfig;
            }
        }

        $target = strtolower((string) ($replication['active_target'] ?? ''));
        if (!in_array($target, ['primary', 'secondary'], true) && is_array($legacy)) {
            $host = (string) ($legacy['host'] ?? '');
            $port = (int) ($legacy['port'] ?? 3306);

            foreach (['primary', 'secondary'] as $candidate) {
                $node = $replication[$candidate] ?? null;
                if (!is_array($node)) {
                    continue;
                }

                if ($host === (string) ($node['host'] ?? '') && $port === (int) ($node['port'] ?? 3306)) {
                    $target = $candidate;
                    break;
                }
            }
        }

        if (in_array($target, ['primary', 'secondary'], true) && !empty($replication[$target]) && is_array($replication[$target])) {
            $node = $replication[$target];

            return [
                'host' => (string) ($node['host'] ?? ''),
                'port' => (int) ($node['port'] ?? ($target === 'primary' ? 3306 : 3307)),
                'user' => (string) ($node['user'] ?? ''),
                'pass' => (string) ($node['pass'] ?? ''),
                'dbname' => (string) ($node['dbname'] ?? $replication['dbname'] ?? $legacy['dbname'] ?? ''),
            ];
        }

        if (is_array($legacy)) {
            return $legacy;
        }

        throw new \RuntimeException('Active DB config file was not found.');
    }

    private function resolveTopologyRole(array $activeConfig, array $primaryConfig, array $secondaryConfig): string
    {
        $host = (string) ($activeConfig['host'] ?? '');
        $port = (int) ($activeConfig['port'] ?? 3306);

        if ($host === (string) ($primaryConfig['host'] ?? '') && $port === (int) ($primaryConfig['port'] ?? 3306)) {
            return 'PRIMARY';
        }

        if ($host === (string) ($secondaryConfig['host'] ?? '') && $port === (int) ($secondaryConfig['port'] ?? 3307)) {
            return 'SECONDARY';
        }

        throw new \RuntimeException('현재 Active DB를 판단할 수 없습니다.');
    }

    private function buildNodeInfo(string $role, string $topologyRole, array $config, array $source): array
    {
        $port = (int) ($source['port'] ?? $config['port'] ?? 0);

        return [
            'role' => $role,
            'topology_role' => $topologyRole,
            'host' => (string) ($source['host'] ?? $config['host'] ?? ''),
            'port' => $port,
            'dbname' => (string) ($config['dbname'] ?? ''),
            'label' => sprintf('%s (%d)', $role, $port),
            'config' => [
                'host' => (string) ($source['host'] ?? ''),
                'port' => $port,
                'user' => (string) ($source['user'] ?? ''),
                'pass' => (string) ($source['pass'] ?? ''),
            ],
        ];
    }

    private function connectDatabasePdo(array $config, string $dbName): PDO
    {
        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 3306);
        $user = (string) ($config['user'] ?? '');
        $pass = (string) ($config['pass'] ?? '');

        if ($host === '' || $user === '' || $dbName === '') {
            throw new \RuntimeException('대기 DB 연결 설정이 올바르지 않습니다.');
        }

        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    private function createStandbySnapshot(PDO $standbyPdo, string $dbName): string
    {
        $snapshotName = sprintf('standby_before_sync_%s.sql', $this->now()->format('Y-m-d_His'));
        $snapshotPath = $this->backupDir . $snapshotName;
        $dump = $this->buildDatabaseDump($standbyPdo, $dbName);

        if (@file_put_contents($snapshotPath, $dump) === false) {
            throw new \RuntimeException('대기 DB 스냅샷 파일 저장에 실패했습니다.');
        }

        return $snapshotPath;
    }

    private function buildDatabaseDump(PDO $pdo, string $dbName): string
    {
        $sqlDump = "-- Sukhyang ERP Standby Snapshot\n";
        $sqlDump .= "-- Database: {$dbName}\n";
        $sqlDump .= "-- Date: " . $this->now()->format('Y-m-d H:i:s') . "\n\n";
        $sqlDump .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $create['Create Table'] ?? '';

            $sqlDump .= "-- ----------------------------\n";
            $sqlDump .= "-- Table structure for `{$table}`\n";
            $sqlDump .= "-- ----------------------------\n";
            $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n";

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                $sqlDump .= "-- `{$table}` data empty\n\n";
                continue;
            }

            $sqlDump .= "-- Data for `{$table}`\n";
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(
                    static fn($value) => isset($value) ? $pdo->quote((string) $value) : 'NULL',
                    $row
                );

                $sqlDump .= "INSERT INTO `{$table}` (`"
                    . implode('`,`', $columns)
                    . '`) VALUES ('
                    . implode(',', $values)
                    . ");\n";
            }

            $sqlDump .= "\n";
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $sqlDump;
    }

    private function rollbackStandbyFromSnapshot(
        string $snapshotPath,
        PDO $standbyPdo,
        string $latestFile,
        string $startedAt,
        array $dbPair
    ): array {
        $result = [
            'attempted' => true,
            'success' => false,
            'message' => '롤백에 실패했습니다.',
        ];

        try {
            $this->writeProgress(
                $latestFile,
                $startedAt,
                'rollback-standby',
                '스냅샷 기준으로 대기 DB를 롤백하고 있습니다.',
                $dbPair,
                [
                    'snapshot_created' => true,
                    'snapshot_file' => basename($snapshotPath),
                    'rollback_attempted' => true,
                    'rollback_success' => false,
                    'statement_count' => 0,
                ]
            );

            $this->dropAllTables($standbyPdo);
            $summary = $this->applyBackupFileToDatabase(
                $snapshotPath,
                $standbyPdo,
                function (int $statementCount) use ($latestFile, $startedAt, $dbPair, $snapshotPath): void {
                    $this->writeProgress(
                        $latestFile,
                        $startedAt,
                        'rollback-standby',
                        '스냅샷 기준으로 대기 DB를 롤백하고 있습니다.',
                        $dbPair,
                        [
                            'snapshot_created' => true,
                            'snapshot_file' => basename($snapshotPath),
                            'rollback_attempted' => true,
                            'rollback_success' => false,
                            'statement_count' => $statementCount,
                        ]
                    );
                }
            );

            $result['success'] = true;
            $result['message'] = sprintf('대기 DB를 스냅샷 기준으로 복원했습니다. (%d statements)', $summary['statement_count']);
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function dropAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', str_replace('`', '``', (string) $table)));
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function appendSyncState(array $status): array
    {
        $status = $this->normalizeAppliedStatus($status);
        $latestBackupPath = $this->findLatestBackupFile();
        $latestBackupFile = $latestBackupPath ? basename($latestBackupPath) : null;
        $state = (string) ($status['state'] ?? '');
        $appliedFile = (string) ($status['applied_file'] ?? '');
        $appliedResult = (string) ($status['applied_result'] ?? '');

        if ($state === 'running') {
            $syncState = 'RUNNING';
        } elseif ($state === 'stale_suspected') {
            $syncState = 'STALE_SUSPECTED';
        } elseif ($state === 'failed') {
            $syncState = 'FAILED';
        } elseif ($latestBackupFile === null || $latestBackupFile === '' || $appliedFile === '' || $appliedResult !== 'success') {
            $syncState = 'UNKNOWN';
        } elseif ($latestBackupFile === $appliedFile) {
            $syncState = 'APPLIED';
        } else {
            $syncState = 'OUTDATED';
        }

        $status['latest_backup_file'] = $latestBackupFile;
        $status['sync_state'] = $syncState;
        $status['sync_state_label'] = match ($syncState) {
            'RUNNING' => '동기화 진행 중',
            'STALE_SUSPECTED' => '상태 확인 필요',
            'FAILED' => '동기화 실패',
            'APPLIED' => '최신 백업 적용 완료',
            'OUTDATED' => '최신 백업 미적용',
            default => '동기화 상태 확인 필요',
        };

        $status['statement_count'] = (int) ($status['statement_count'] ?? 0);
        $status['snapshot_created'] = (bool) ($status['snapshot_created'] ?? false);
        $status['rollback_attempted'] = (bool) ($status['rollback_attempted'] ?? false);
        $status['rollback_success'] = (bool) ($status['rollback_success'] ?? false);
        $status['runtime'] = is_array($status['runtime'] ?? null) ? $status['runtime'] : $this->inspectSyncRuntime();

        return $this->appendDatabasePair($status);
    }

    private function normalizeAppliedStatus(array $status): array
    {
        if (($status['state'] ?? '') === 'success' && !isset($status['applied_file']) && !empty($status['file'])) {
            $status['applied_file'] = $status['file'];
        }

        if (($status['state'] ?? '') === 'success' && !isset($status['applied_at']) && !empty($status['finished_at'])) {
            $status['applied_at'] = $status['finished_at'];
        }

        if (($status['state'] ?? '') === 'success' && !isset($status['applied_result'])) {
            $status['applied_result'] = 'success';
        }

        if (($status['state'] ?? '') === 'success' && !isset($status['applied_source']) && !empty($status['trigger'])) {
            $status['applied_source'] = $status['trigger'];
        }

        if (($status['state'] ?? '') === 'success' && !isset($status['latest_backup_file_at_apply']) && !empty($status['applied_file'])) {
            $status['latest_backup_file_at_apply'] = $status['applied_file'];
        }

        return $status;
    }

    private function readSyncStatus(): ?array
    {
        $path = $this->getSyncStatusPath();
        if (!is_file($path)) {
            return null;
        }

        $json = json_decode((string) @file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }

    private function writeSyncStatus(array $status): void
    {
        $status = $this->mergeAppliedFields($status);

        if (!isset($status['updated_at'])) {
            $status['updated_at'] = $this->now()->format('Y-m-d H:i:s');
        }

        $json = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return;
        }

        @file_put_contents($this->getSyncStatusPath(), $json);
    }

    private function mergeAppliedFields(array $status): array
    {
        $previous = $this->readSyncStatus();
        if (!$previous) {
            return $this->normalizeAppliedStatus($status);
        }

        $previous = $this->normalizeAppliedStatus($previous);
        foreach ([
            'applied_file',
            'applied_at',
            'applied_result',
            'applied_source',
            'latest_backup_file_at_apply',
            'active_db',
            'standby_db',
        ] as $field) {
            if (!array_key_exists($field, $status) && array_key_exists($field, $previous)) {
                $status[$field] = $previous[$field];
            }
        }

        return $this->normalizeAppliedStatus($status);
    }

    private function appendDatabasePair(array &$status, ?array $dbPair = null): array
    {
        try {
            $dbPair ??= $this->resolveDatabasePair();
            $status['active_db'] = $this->stripConfigFromNode($dbPair['active']);
            $status['standby_db'] = $this->stripConfigFromNode($dbPair['standby']);
        } catch (Throwable) {
        }

        return $status;
    }

    private function stripConfigFromNode(array $node): array
    {
        unset($node['config']);
        return $node;
    }

    private function writeProgress(
        string $file,
        string $startedAt,
        string $stage,
        string $message,
        ?array $dbPair = null,
        array $extra = []
    ): void {
        $payload = array_merge([
            'state' => 'running',
            'file' => $file,
            'started_at' => $startedAt,
            'updated_at' => $this->now()->format('Y-m-d H:i:s'),
            'stage' => $stage,
            'message' => $message,
        ], $extra);

        $this->appendDatabasePair($payload, $dbPair);
        $this->writeSyncStatus($payload);
        $this->writeSyncTrace('RUNNING', $stage, $message, $file, [
            'statement_count' => $payload['statement_count'] ?? null,
            'snapshot_file' => $payload['snapshot_file'] ?? null,
            'rollback_attempted' => $payload['rollback_attempted'] ?? false,
            'rollback_success' => $payload['rollback_success'] ?? false,
        ]);
    }

    private function writeSyncLog(array $result): void
    {
        $target = $result['standby_db']['label'] ?? '-';
        $line = sprintf(
            "[%s] %s: %s / target=%s%s\n",
            $this->now()->format('Y-m-d H:i:s'),
            ($result['success'] ?? false) ? 'SUCCESS' : 'FAILED',
            $result['file'] ?? '-',
            $target,
            !empty($result['message']) ? ' / ' . $result['message'] : ''
        );

        @file_put_contents($this->backupDir . 'secondary_restore_log.txt', $line, FILE_APPEND);
    }

    private function writeSyncTrace(string $state, string $stage, string $message, ?string $file = null, array $context = []): void
    {
        $payload = [
            'time' => $this->now()->format('Y-m-d H:i:s'),
            'state' => $state,
            'stage' => $stage,
            'file' => $file,
            'message' => $message,
            'context' => $context,
        ];

        @file_put_contents(
            $this->getSyncTracePath(),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND
        );
    }

    private function getSyncStatusPath(): string
    {
        return $this->backupDir . 'secondary_restore_status.json';
    }

    private function getSyncTracePath(): string
    {
        return $this->backupDir . 'secondary_restore_trace.log';
    }

    private function getSyncLockPath(): string
    {
        return $this->backupDir . 'secondary_restore_runner.lock';
    }

    private function readCurrentStage(): string
    {
        $status = $this->readSyncStatus();
        return (string) ($status['stage'] ?? 'unexpected-error');
    }

    private function isStatusStale(array $status): bool
    {
        $pivot = $status['updated_at'] ?? $status['started_at'] ?? null;
        if (!$pivot) {
            return false;
        }

        $timestamp = strtotime((string) $pivot);
        if (!$timestamp) {
            return false;
        }

        return (time() - $timestamp) >= self::STATUS_STALE_SECONDS;
    }

    private function inspectSyncRuntime(): array
    {
        $tracePath = $this->getSyncTracePath();
        $lockPath = $this->getSyncLockPath();

        $traceUpdatedAt = is_file($tracePath) ? @filemtime($tracePath) : false;
        $traceRecent = is_int($traceUpdatedAt) && ((time() - $traceUpdatedAt) < self::TRACE_RECENT_SECONDS);

        $lockData = null;
        if (is_file($lockPath)) {
            $decoded = json_decode((string) @file_get_contents($lockPath), true);
            if (is_array($decoded)) {
                $lockData = $decoded;
            }
        }

        $runnerActive = false;
        if (is_array($lockData)) {
            $heartbeat = strtotime((string) ($lockData['heartbeat_at'] ?? $lockData['started_at'] ?? ''));
            if ($heartbeat) {
                $runnerActive = (time() - $heartbeat) < self::STATUS_STALE_SECONDS;
            }
        }

        return [
            'trace_updated_at' => is_int($traceUpdatedAt) ? date('Y-m-d H:i:s', $traceUpdatedAt) : null,
            'trace_recent' => $traceRecent,
            'lock_exists' => is_file($lockPath),
            'runner_active' => $runnerActive,
            'lock' => $lockData,
        ];
    }

    private function isRestoreRunning(): bool
    {
        $statusPath = $this->backupDir . 'active_restore_status.json';
        if (!is_file($statusPath)) {
            return false;
        }

        $data = json_decode((string) @file_get_contents($statusPath), true);
        if (!is_array($data)) {
            return false;
        }

        return (string) ($data['state'] ?? '') === 'running' && !$this->isStatusStale($data);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
