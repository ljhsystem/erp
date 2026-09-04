<?php

namespace App\Services\Backup;

use DateTimeImmutable;
use DateTimeZone;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;
use function Core\storage_system_path;

class DatabaseRestoreService
{
    private const STATUS_STALE_SECONDS = 300;
    private const PROGRESS_INTERVAL = 50;
    private const TRACE_RECENT_SECONDS = 120;

    private readonly PDO $pdo;
    private readonly string $backupDir;
    private readonly DateTimeZone $timezone;
    private readonly LoggerInterface $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->timezone = new DateTimeZone('Asia/Seoul');
        $this->logger = LoggerFactory::getLogger('service-backup-database-restore');

        $backupPath = storage_system_path('db_backup');
        if (!$backupPath) {
            throw new \RuntimeException('DB backup storage path not configured.');
        }

        $this->backupDir = rtrim(str_replace('\\', '/', $backupPath), '/') . '/';
        $this->ensureBackupDir();
    }

    public function restoreBackupFileToActive(string $file, string $trigger = 'restore'): array
    {
        $startedAt = $this->now()->format('Y-m-d H:i:s');
        $dbPair = $this->resolveDatabasePair();
        $dbName = $this->getCurrentDatabaseName();
        $backupPath = $this->resolveBackupFilePath($file, $dbName);

        if ($this->isSyncRunning()) {
            $result = [
                'success' => false,
                'state' => 'failed',
                'message' => 'DB 동기화 진행 중에는 복원을 실행할 수 없습니다.',
                'started_at' => $startedAt,
                'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                'stage' => 'sync-running',
                'file' => $file,
                'statement_count' => 0,
            ];

            $this->appendDatabasePair($result, $dbPair);
            $this->writeRestoreStatus($result);
            $this->writeRestoreLog($result);

            return $result;
        }

        if ($backupPath === null) {
            $result = [
                'success' => false,
                'state' => 'failed',
                'message' => '선택한 백업 파일을 찾을 수 없습니다.',
                'started_at' => $startedAt,
                'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                'stage' => 'no-backup-file',
                'file' => $file,
                'statement_count' => 0,
            ];

            $this->appendDatabasePair($result, $dbPair);
            $this->writeRestoreStatus($result);
            $this->writeRestoreLog($result);

            return $result;
        }

        $selectedFile = basename($backupPath);
        $status = [
            'state' => 'running',
            'message' => '현재 Active DB 복원을 준비하고 있습니다.',
            'trigger' => $trigger,
            'file' => $selectedFile,
            'started_at' => $startedAt,
            'updated_at' => $startedAt,
            'stage' => 'starting',
            'statement_count' => 0,
        ];

        $this->appendDatabasePair($status, $dbPair);
        $this->writeRestoreStatus($status);
        $this->writeRestoreTrace('RUNNING', 'starting', $status['message'], $selectedFile);

        try {
            $this->writeProgress($selectedFile, $startedAt, 'validate-backup-file', '복원 대상 백업 파일을 확인하고 있습니다.', $dbPair);
            $this->writeProgress($selectedFile, $startedAt, 'prepare-active', '현재 Active DB의 기존 테이블을 정리하고 있습니다.', $dbPair);
            $this->dropAllTables($this->pdo);

            $this->writeProgress(
                $selectedFile,
                $startedAt,
                'apply-backup',
                '선택한 SQL 백업 파일을 현재 Active DB에 적용하고 있습니다.',
                $dbPair,
                ['statement_count' => 0]
            );

            $summary = $this->applyBackupFileToDatabase(
                $backupPath,
                $this->pdo,
                function (int $statementCount) use ($selectedFile, $startedAt, $dbPair): void {
                    $this->writeProgress(
                        $selectedFile,
                        $startedAt,
                        'apply-backup',
                        '선택한 SQL 백업 파일을 현재 Active DB에 적용하고 있습니다.',
                        $dbPair,
                        ['statement_count' => $statementCount]
                    );
                }
            );

            $finishedAt = $this->now()->format('Y-m-d H:i:s');
            $result = [
                'success' => true,
                'state' => 'success',
                'message' => 'DB 복원이 완료되었습니다.',
                'file' => $selectedFile,
                'trigger' => $trigger,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'updated_at' => $finishedAt,
                'stage' => 'completed',
                'restored_file' => $selectedFile,
                'restored_at' => $finishedAt,
                'restored_result' => 'success',
                'restored_source' => $trigger,
                'statement_count' => $summary['statement_count'],
            ];

            $this->appendDatabasePair($result, $dbPair);
            $this->writeRestoreStatus($result);
            $this->writeRestoreLog($result);
            $this->writeRestoreTrace('SUCCESS', 'completed', $result['message'], $selectedFile, [
                'statement_count' => $summary['statement_count'],
                'target' => $dbPair['active']['label'] ?? null,
            ]);

            return $result;
        } catch (Throwable $e) {
            $finishedAt = $this->now()->format('Y-m-d H:i:s');
            $failed = [
                'success' => false,
                'state' => 'failed',
                'message' => 'DB 복원에 실패했습니다.',
                'error' => $e->getMessage(),
                'file' => $selectedFile,
                'trigger' => $trigger,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'updated_at' => $finishedAt,
                'stage' => $this->readCurrentStage(),
                'statement_count' => (int) (($this->readRestoreStatus()['statement_count'] ?? 0)),
            ];

            $this->appendDatabasePair($failed, $dbPair);
            $this->writeRestoreStatus($failed);
            $this->writeRestoreLog($failed);
            $this->writeRestoreTrace('FAILED', (string) $failed['stage'], $failed['message'], $selectedFile, [
                'error' => $e->getMessage(),
                'target' => $dbPair['active']['label'] ?? null,
            ]);

            return $failed;
        }
    }

    public function getLatestRestoreInfo(): array
    {
        $status = $this->readRestoreStatus();
        if (!$status) {
            return $this->appendDatabasePair([
                'state' => 'idle',
                'message' => 'DB 복원 이력이 없습니다.',
                'statement_count' => 0,
                'runtime' => $this->inspectRestoreRuntime(),
            ]);
        }

        $status['runtime'] = $this->inspectRestoreRuntime();

        if (($status['state'] ?? '') === 'running' && $this->isStatusStale($status)) {
            if ($status['runtime']['trace_recent']) {
                $status['stale'] = false;
            } else {
                $status['state'] = 'stale_suspected';
                $status['message'] = '복원 상태 갱신이 멈춰 상태 확인이 필요합니다.';
                $status['stage'] = 'stale-suspected';
                $status['stale'] = true;
            }
        }

        return $this->appendDatabasePair($status);
    }

    private function resolveBackupFilePath(string $file, string $dbName): ?string
    {
        $name = basename(trim($file));
        if ($name === '' || !str_ends_with(strtolower($name), '.sql')) {
            return null;
        }

        if (!str_starts_with($name, $dbName . '_')) {
            return null;
        }

        $path = $this->backupDir . $name;
        return is_file($path) ? $path : null;
    }

    private function applyBackupFileToDatabase(string $path, PDO $pdo, ?callable $progressCallback = null): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new \RuntimeException('복원할 SQL 파일을 열 수 없습니다.');
        }

        $statementCount = 0;
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
                            $pdo->exec($statement);
                            $statementCount++;

                            if (($statementCount % self::PROGRESS_INTERVAL) === 0) {
                                $this->writeRestoreTrace('RUNNING', 'apply-backup', 'SQL 구문을 순차 적용하고 있습니다.', basename($path), [
                                    'statement_count' => $statementCount,
                                ]);

                                if ($progressCallback) {
                                    $progressCallback($statementCount);
                                }
                            }
                        }
                    }
                }
            }

            $tail = trim($buffer);
            if ($tail !== '') {
                $pdo->exec($tail);
                $statementCount++;
            }
        } finally {
            fclose($handle);
        }

        if ($progressCallback) {
            $progressCallback($statementCount);
        }

        return ['statement_count' => $statementCount];
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

    private function appendDatabasePair(array $status, ?array $dbPair = null): array
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

    private function readRestoreStatus(): ?array
    {
        $path = $this->getRestoreStatusPath();
        if (!is_file($path)) {
            return null;
        }

        $json = json_decode((string) @file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }

    private function writeRestoreStatus(array $status): void
    {
        if (!isset($status['updated_at'])) {
            $status['updated_at'] = $this->now()->format('Y-m-d H:i:s');
        }

        $json = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return;
        }

        @file_put_contents($this->getRestoreStatusPath(), $json);
    }

    private function getRestoreStatusPath(): string
    {
        return $this->backupDir . 'active_restore_status.json';
    }

    private function getRestoreTracePath(): string
    {
        return $this->getRestoreStatusPath();
    }

    private function getSyncStatusPath(): string
    {
        return $this->backupDir . 'secondary_restore_status.json';
    }

    private function getSyncTracePath(): string
    {
        return $this->getSyncStatusPath();
    }

    private function getSyncLockPath(): string
    {
        return $this->backupDir . 'secondary_restore_runner.lock';
    }

    private function writeProgress(string $file, string $startedAt, string $stage, string $message, ?array $dbPair = null, array $extra = []): void
    {
        $payload = array_merge([
            'state' => 'running',
            'file' => $file,
            'started_at' => $startedAt,
            'updated_at' => $this->now()->format('Y-m-d H:i:s'),
            'stage' => $stage,
            'message' => $message,
        ], $extra);

        $this->appendDatabasePair($payload, $dbPair);
        $this->writeRestoreStatus($payload);
        $this->writeRestoreTrace('RUNNING', $stage, $message, $file, [
            'statement_count' => $payload['statement_count'] ?? null,
        ]);
    }

    private function writeRestoreLog(array $result): void
    {
        $success = (bool) ($result['success'] ?? false);
        $context = [
            'event_code' => $success ? 'DATABASE_RESTORE_COMPLETED' : 'DATABASE_RESTORE_FAILED',
            'result' => $success ? 'SUCCESS' : 'FAILED',
            'backup_file' => basename((string) ($result['file'] ?? '')),
            'target_role' => $result['active_db']['label'] ?? null,
            'statement_count' => $result['statement_count'] ?? null,
        ];
        if ($success) {
            $this->logger->info('데이터베이스 복원을 완료했습니다.', $context);
            return;
        }
        $this->logger->error('데이터베이스 복원에 실패했습니다.', $context);
    }

    private function writeRestoreTrace(string $state, string $stage, string $message, ?string $file = null, array $context = []): void
    {
        $level = $state === 'FAILED' ? 'error' : ($state === 'SUCCESS' ? 'info' : 'debug');
        $this->logger->log($level, '활성 데이터베이스 복원 진행상태가 변경되었습니다.', [
            'event_code' => 'DATABASE_RESTORE_PROGRESS',
            'result' => $state,
            'stage' => $stage,
            'statement_count' => isset($context['statement_count']) ? (int) $context['statement_count'] : null,
        ]);
    }

    private function readCurrentStage(): string
    {
        $status = $this->readRestoreStatus();
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

    private function isSyncRunning(): bool
    {
        $statusPath = $this->getSyncStatusPath();
        if (!is_file($statusPath)) {
            return false;
        }

        $data = json_decode((string) @file_get_contents($statusPath), true);
        if (!is_array($data)) {
            return false;
        }

        if ((string) ($data['state'] ?? '') !== 'running') {
            return false;
        }

        if (!$this->isStatusStale($data)) {
            return true;
        }

        $runtime = $this->inspectSyncRuntime();
        return (bool) ($runtime['runner_active'] || $runtime['trace_recent']);
    }

    private function inspectRestoreRuntime(): array
    {
        return $this->inspectTraceRuntime($this->getRestoreTracePath());
    }

    private function inspectSyncRuntime(): array
    {
        return $this->inspectTraceRuntime($this->getSyncTracePath(), $this->getSyncLockPath());
    }

    private function inspectTraceRuntime(string $tracePath, ?string $lockPath = null): array
    {
        $traceUpdatedAt = is_file($tracePath) ? @filemtime($tracePath) : false;
        $traceRecent = is_int($traceUpdatedAt) && ((time() - $traceUpdatedAt) < self::TRACE_RECENT_SECONDS);

        $lockData = null;
        $runnerActive = false;

        if ($lockPath && is_file($lockPath)) {
            $decoded = json_decode((string) @file_get_contents($lockPath), true);
            if (is_array($decoded)) {
                $lockData = $decoded;
                $heartbeat = strtotime((string) ($decoded['heartbeat_at'] ?? $decoded['started_at'] ?? ''));
                if ($heartbeat) {
                    $runnerActive = (time() - $heartbeat) < self::STATUS_STALE_SECONDS;
                }
            }
        }

        return [
            'trace_updated_at' => is_int($traceUpdatedAt) ? date('Y-m-d H:i:s', $traceUpdatedAt) : null,
            'trace_recent' => $traceRecent,
            'lock_exists' => $lockPath ? is_file($lockPath) : false,
            'runner_active' => $runnerActive,
            'lock' => $lockData,
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
