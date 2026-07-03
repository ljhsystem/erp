<?php

namespace App\Services\Backup;

use App\Services\System\SettingService;
use Core\LoggerFactory;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;
use function Core\storage_system_path;

class DatabaseBackupService
{
    private const RESTORE_STALE_SECONDS = 900;

    private readonly PDO $pdo;
    private readonly SettingService $settings;
    private readonly string $backupDir;
    private readonly DateTimeZone $timezone;
    private $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->settings = new SettingService($pdo);
        $this->timezone = new DateTimeZone('Asia/Seoul');

        $backupPath = storage_system_path('db_backup');
        if (!$backupPath) {
            throw new \RuntimeException('DB backup storage path not configured');
        }

        $this->backupDir = rtrim(str_replace('\\', '/', $backupPath), '/') . '/';
        $this->logger = LoggerFactory::getLogger('service-backup.DatabaseBackupService');
    }

    public function backupDatabase(): array
    {
        try {
            $this->ensureBackupDir();
            $dbName = $this->getCurrentDatabaseName();
            $this->cleanupBackupsIfNeeded($dbName);

            $timestamp = $this->now()->format('Y-m-d_His');
            $filename = sprintf('%s_%s.sql', $dbName, $timestamp);
            $filepath = $this->backupDir . $filename;

            $dump = $this->buildDatabaseDump($this->pdo, $dbName);
            if (@file_put_contents($filepath, $dump) === false) {
                throw new \RuntimeException('Failed to save backup file.');
            }

            $size = (int) (@filesize($filepath) ?: 0);
            $time = $this->now()->format('Y-m-d H:i:s');

            $this->markBackupClean($filename, $time);
            $this->writeBackupLog(sprintf('[%s] BACKUP SUCCESS: %s (%d bytes)', $time, $filename, $size));
            $this->logger->info('[BACKUP] done', ['file' => $filename, 'size' => $size]);

            return [
                'success' => true,
                'message' => 'Primary DB backup completed.',
                'filename' => $filename,
                'time' => $time,
                'size' => $size,
            ];
        } catch (Throwable $e) {
            $this->logger->error('[BACKUP] failed', ['error' => $e->getMessage()]);
            $this->writeBackupLog(sprintf('[%s] BACKUP FAILED: %s', $this->now()->format('Y-m-d H:i:s'), $e->getMessage()));

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function runAutoBackup(): array
    {
        if (!$this->settings->getBool('backup_auto_enabled', false)) {
            return [
                'success' => false,
                'message' => 'Auto backup is disabled.',
                'skipped' => true,
            ];
        }

        $due = $this->getAutoBackupDueDecision();
        if (!$due['due']) {
            return [
                'success' => false,
                'message' => $due['message'],
                'skipped' => true,
                'trigger_mode' => $due['trigger_mode'],
                'min_interval_hours' => $due['min_interval_hours'],
            ];
        }

        $result = $this->backupDatabase();
        if (!$result['success']) {
            return $result;
        }

        $this->writeAutoBackupMeta([
            'last_run_at' => $this->now()->format('Y-m-d H:i:s'),
            'last_trigger_mode' => $due['trigger_mode'],
            'last_min_interval_hours' => $due['min_interval_hours'],
            'last_backup_file' => $result['filename'] ?? null,
        ]);

        return $result;
    }

    public function markBackupDirty(string $source = 'manual'): void
    {
        $this->writeAutoBackupMeta([
            'backup_dirty' => true,
            'dirty_marked_at' => $this->now()->format('Y-m-d H:i:s'),
            'dirty_source' => $source,
        ]);
    }


    public function getBackupDirectory(): string
    {
        return $this->backupDir;
    }

    public function getBackupDirectoryMasked(): string
    {
        return 'Backup path is masked in UI.';
    }

    public function getLatestBackupFile(): ?array
    {
        $latest = $this->findLatestBackupFile();
        if (!$latest) {
            return null;
        }

        return [
            'file' => basename($latest),
            'time' => $this->formatFileTime($latest),
            'size' => (int) (@filesize($latest) ?: 0),
        ];
    }

    public function getRecentBackupFiles(int $limit = 10): array
    {
        $dbName = $this->getCurrentDatabaseName();
        $files = glob($this->backupDir . $dbName . '_*.sql') ?: [];

        if (!$files) {
            return [];
        }

        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, max(1, $limit));

        return array_map(function (string $path): array {
            return [
                'file' => basename($path),
                'time' => $this->formatFileTime($path),
                'size' => (int) (@filesize($path) ?: 0),
            ];
        }, $files);
    }

    public function restoreLatestBackupToSecondary(string $trigger = 'manual'): array
    {
        $startedAt = $this->now()->format('Y-m-d H:i:s');
        $latestPath = $this->findLatestBackupFile();

        if (!$latestPath) {
            $result = [
                'success' => false,
                'state' => 'failed',
                'message' => 'No backup file is available for restore.',
                'started_at' => $startedAt,
                'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                'stage' => 'no-backup-file',
            ];
            $this->writeRestoreStatus($result);
            $this->writeSecondaryRestoreLog($result);
            return $result;
        }

        $latestFile = basename($latestPath);
        $status = [
            'state' => 'running',
            'message' => 'Secondary DB 蹂듦뎄瑜??쒖옉?덉뒿?덈떎.',
            'trigger' => $trigger,
            'file' => $latestFile,
            'started_at' => $startedAt,
            'updated_at' => $startedAt,
            'stage' => 'starting',
            'warning' => 'If restore fails, Secondary DB will be recovered from the snapshot when possible.',
        ];
        $this->writeRestoreStatus($status);
        $this->writeRestoreProgress(
            'running',
            $latestFile,
            $startedAt,
            'starting',
            'Secondary DB 복구를 준비하고 있습니다.'
        );

        try {
            $db = $this->getCurrentDatabaseName();

            $this->writeRestoreProgress(
                'running',
                $latestFile,
                $startedAt,
                'load-secondary-config',
                'Secondary DB 설정을 불러오고 있습니다.'
            );
            $secondaryConfig = $this->getSecondaryConfig();

            $this->writeRestoreProgress(
                'running',
                $latestFile,
                $startedAt,
                'connect-secondary',
                'Secondary DB 연결을 확인하고 있습니다.'
            );
            $secondaryPdo = $this->connectSecondaryPdo($secondaryConfig, $db);

            $this->writeRestoreProgress(
                'running',
                $latestFile,
                $startedAt,
                'snapshot-secondary',
                'Secondary DB 스냅샷을 생성하고 있습니다.'
            );
            $snapshotPath = $this->createSecondarySnapshot($secondaryPdo, $db);
            $dropCompleted = false;

            try {
                $this->writeRestoreProgress('running', $latestFile, $startedAt, 'drop-secondary-tables', 'Dropping existing Secondary DB tables.');
                $this->dropAllTables($secondaryPdo);
                $dropCompleted = true;

                $this->writeRestoreProgress('running', $latestFile, $startedAt, 'import-backup', 'Importing the latest backup file into Secondary DB.');
                $import = $this->importSqlFileToDatabase($latestPath, $secondaryConfig, $db);
                if (!$import['success']) {
                    throw new \RuntimeException($import['message']);
                }
            } catch (Throwable $restoreError) {
                $rollbackResult = [
                    'attempted' => false,
                    'success' => false,
                    'message' => '롤백을 수행하지 못했습니다.',
                ];

                if ($dropCompleted) {
                    $this->writeRestoreProgress(
                        'running',
                        $latestFile,
                        $startedAt,
                        'rollback-secondary',
                        '복구 실패로 Secondary DB 롤백을 진행하고 있습니다.'
                    );
                    $rollbackResult = $this->rollbackSecondaryFromSnapshot($snapshotPath, $secondaryConfig, $db);
                }

                $result = [
                    'success' => false,
                    'state' => 'failed',
                    'message' => 'Secondary DB 복구에 실패했습니다.',
                    'error' => $restoreError->getMessage(),
                    'file' => $latestFile,
                    'started_at' => $startedAt,
                    'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                    'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                    'stage' => $dropCompleted ? 'rollback-secondary' : 'import-backup',
                    'rollback_attempted' => $rollbackResult['attempted'],
                    'rollback_success' => $rollbackResult['success'],
                    'rollback_message' => $rollbackResult['message'],
                    'warning' => '복구 실패 후 롤백도 완료하지 못했습니다. Secondary DB 상태를 직접 확인해 주세요.',
                ];

                $this->writeRestoreStatus($result);
                $this->writeSecondaryRestoreLog($result);
                return $result;
            }

            $result = [
                'success' => true,
                'state' => 'success',
                'message' => 'Secondary DB restore completed.',
                'file' => $latestFile,
                'trigger' => $trigger,
                'started_at' => $startedAt,
                'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                'stage' => 'completed',
                'rollback_attempted' => false,
                'rollback_success' => false,
                'applied_file' => $latestFile,
                'applied_at' => $this->now()->format('Y-m-d H:i:s'),
                'applied_result' => 'success',
                'applied_source' => $trigger,
                'latest_backup_file_at_apply' => $latestFile,
            ];

            $this->writeRestoreStatus($result);
            $this->writeSecondaryRestoreLog($result);
            return $result;
        } catch (Throwable $e) {
            $result = [
                'success' => false,
                'state' => 'failed',
                'message' => 'An unexpected error occurred during Secondary DB restore.',
                'error' => $e->getMessage(),
                'file' => $latestFile,
                'started_at' => $startedAt,
                'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                'stage' => 'unexpected-error',
                'rollback_attempted' => false,
                'rollback_success' => false,
            ];

            $this->writeRestoreStatus($result);
            $this->writeSecondaryRestoreLog($result);
            return $result;
        }
    }

    public function getLatestSecondaryRestore(): array
    {
        $status = $this->readRestoreStatus();
        if (!$status) {
            return $this->appendSecondarySyncState([
                'state' => 'idle',
                'message' => 'No restore history is available.',
            ]);
        }

        $status = $this->normalizeAppliedStatus($status);

        if (($status['state'] ?? '') === 'running' && $this->isRestoreStatusStale($status)) {
            $status['state'] = 'failed';
            $status['message'] = 'Restore status timed out. Please verify the Secondary DB state manually.';
            $status['finished_at'] = $this->now()->format('Y-m-d H:i:s');
            $status['updated_at'] = $status['finished_at'];
            $status['stage'] = 'stale-timeout';
            $status['stale'] = true;
            $status['warning'] = 'The mysql restore process may have ended unexpectedly. Please check Secondary DB state and backup logs.';
            $this->writeRestoreStatus($status);
            $this->writeSecondaryRestoreLog($status);
        }

        return $this->appendSecondarySyncState($status);
    }


    private function ensureBackupDir(): void
    {
        if (is_dir($this->backupDir)) {
            return;
        }

        if (!@mkdir($this->backupDir, 0777, true) && !is_dir($this->backupDir)) {
            throw new \RuntimeException('Failed to create backup directory.');
        }
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }

    private function getCurrentDatabaseName(): string
    {
        $this->pdo->exec('SET NAMES utf8mb4');
        $dbName = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($dbName === '') {
            throw new \RuntimeException('Unable to determine the current database name.');
        }

        return $dbName;
    }

    private function cleanupBackupsIfNeeded(string $dbName): void
    {
        $cleanupEnabled = $this->settings->getBool('backup_cleanup_enabled', true);
        if (!$cleanupEnabled) {
            return;
        }

        $retentionDays = $this->normalizeRetentionDays($this->settings->getInt('backup_retention_days', 30));
        $pattern = $this->backupDir . $dbName . '_*.sql';
        $expireTime = time() - ($retentionDays * 86400);

        foreach (glob($pattern) ?: [] as $file) {
            if ((int) @filemtime($file) < $expireTime) {
                @unlink($file);
            }
        }
    }

    private function normalizeRetentionDays(int $days): int
    {
        return max(1, min(365, $days));
    }


    private function buildDatabaseDump(PDO $pdo, string $dbName): string
    {
        $sqlDump = "-- Sukhyang ERP Database Backup\n";
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

    private function writeBackupLog(string $line): void
    {
        @file_put_contents($this->backupDir . 'backup_log.txt', $line . "\n", FILE_APPEND);
    }

    private function findLatestBackupFile(): ?string
    {
        $dbName = $this->getCurrentDatabaseName();
        $files = glob($this->backupDir . $dbName . '_*.sql') ?: [];
        if (!$files) {
            return null;
        }

        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        return $files[0];
    }

    private function formatFileTime(string $path): string
    {
        return $this->now()->setTimestamp((int) @filemtime($path))->format('Y-m-d H:i:s');
    }

    private function getAutoBackupMetaPath(): string
    {
        return $this->backupDir . 'auto_backup_meta.json';
    }

    private function readAutoBackupMeta(): array
    {
        $path = $this->getAutoBackupMetaPath();
        if (!is_file($path)) {
            return $this->normalizeAutoBackupMeta([]);
        }

        $json = json_decode((string) @file_get_contents($path), true);
        return $this->normalizeAutoBackupMeta(is_array($json) ? $json : []);
    }

    private function writeAutoBackupMeta(array $payload): void
    {
        $meta = array_merge($this->readAutoBackupMeta(), $payload);

        @file_put_contents(
            $this->getAutoBackupMetaPath(),
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private function getAutoBackupDueDecision(): array
    {
        $now = $this->now();
        $triggerMode = $this->normalizeTriggerMode((string) $this->settings->get('backup_auto_trigger_mode', 'manual'));
        $minIntervalHours = $this->normalizeMinIntervalHours((int) $this->settings->getInt('backup_auto_min_interval_hours', 24));
        $meta = $this->readAutoBackupMeta();

        if ($triggerMode !== 'data-change') {
            return [
                'due' => false,
                'message' => 'Auto backup trigger mode is manual.',
                'trigger_mode' => $triggerMode,
                'min_interval_hours' => $minIntervalHours,
            ];
        }

        if (($meta['backup_dirty'] ?? false) !== true) {
            return [
                'due' => false,
                'message' => 'No data change has been marked since the last backup.',
                'trigger_mode' => $triggerMode,
                'min_interval_hours' => $minIntervalHours,
            ];
        }

        $lastBackupAt = $this->resolveLastBackupAt($meta);
        if ($lastBackupAt instanceof DateTimeImmutable) {
            $nextDueAt = $lastBackupAt->modify(sprintf('+%d hours', $minIntervalHours));
            if ($now < $nextDueAt) {
                return [
                    'due' => false,
                    'message' => 'Minimum auto-backup interval has not elapsed yet.',
                    'trigger_mode' => $triggerMode,
                    'min_interval_hours' => $minIntervalHours,
                    'last_backup_at' => $lastBackupAt->format('Y-m-d H:i:s'),
                    'next_due_at' => $nextDueAt->format('Y-m-d H:i:s'),
                ];
            }
        }

        return [
            'due' => true,
            'message' => 'Auto backup is due.',
            'trigger_mode' => $triggerMode,
            'min_interval_hours' => $minIntervalHours,
            'dirty_marked_at' => $meta['dirty_marked_at'] ?? null,
        ];
    }

    private function normalizeAutoBackupMeta(array $meta): array
    {
        return [
            'backup_dirty' => (bool) ($meta['backup_dirty'] ?? false),
            'dirty_marked_at' => $meta['dirty_marked_at'] ?? null,
            'dirty_source' => $meta['dirty_source'] ?? null,
            'last_run_at' => $meta['last_run_at'] ?? null,
            'last_backup_at' => $meta['last_backup_at'] ?? null,
            'last_backup_file' => $meta['last_backup_file'] ?? null,
            'last_trigger_mode' => $meta['last_trigger_mode'] ?? null,
            'last_min_interval_hours' => $meta['last_min_interval_hours'] ?? null,
        ];
    }

    private function normalizeTriggerMode(string $mode): string
    {
        return $mode === 'data-change' ? 'data-change' : 'manual';
    }

    private function normalizeMinIntervalHours(int $hours): int
    {
        return in_array($hours, [12, 24, 48], true) ? $hours : 24;
    }

    private function resolveLastBackupAt(array $meta): ?DateTimeImmutable
    {
        $latestBackupFile = $this->findLatestBackupFile();
        if ($latestBackupFile && is_file($latestBackupFile)) {
            return $this->now()->setTimestamp((int) @filemtime($latestBackupFile));
        }

        $lastBackupAt = trim((string) ($meta['last_backup_at'] ?? ''));
        if ($lastBackupAt === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($lastBackupAt, $this->timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function markBackupClean(string $filename, string $time): void
    {
        $this->writeAutoBackupMeta([
            'backup_dirty' => false,
            'dirty_marked_at' => null,
            'dirty_source' => null,
            'last_backup_at' => $time,
            'last_backup_file' => $filename,
        ]);
    }


    private function getSecondaryConfig(): array
    {
        $configPath = PROJECT_ROOT . '/../secure-config/db_replication.php';
        if (!is_file($configPath)) {
            throw new \RuntimeException('Restore DB config file was not found.');
        }

        $config = require $configPath;

        if (empty($config['secondary']) || !is_array($config['secondary'])) {
            throw new \RuntimeException('Secondary DB 설정이 올바르지 않습니다.');
        }

        return $config['secondary'];
    }

    private function connectSecondaryPdo(array $sec, string $db): PDO
    {
        $host = (string) ($sec['host'] ?? '');
        $port = (int) ($sec['port'] ?? 3306);
        $user = (string) ($sec['user'] ?? '');
        $pass = (string) ($sec['pass'] ?? '');

        if ($host === '' || $user === '' || $db === '') {
            throw new \RuntimeException('Secondary DB connection settings are incomplete.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    private function createSecondarySnapshot(PDO $secondaryPdo, string $db): string
    {
        $this->ensureBackupDir();
        $snapshotName = sprintf('secondary_before_restore_%s.sql', $this->now()->format('Y-m-d_His'));
        $snapshotPath = $this->backupDir . $snapshotName;

        $dump = $this->buildDatabaseDump($secondaryPdo, $db);
        if (@file_put_contents($snapshotPath, $dump) === false) {
            throw new \RuntimeException('Unable to save the Secondary DB snapshot file.');
        }

        return $snapshotPath;
    }

    private function dropAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function importSqlFileToDatabase(string $sqlFile, array $dbConfig, string $dbName): array
    {
        if (!is_file($sqlFile)) {
            return ['success' => false, 'message' => 'SQL file was not found.'];
        }

        $fileSize = (int) (@filesize($sqlFile) ?: 0);
        $trace = function (string $message) use ($sqlFile, $dbName, $fileSize): void {
            @file_put_contents(
                $this->backupDir . 'secondary_restore_trace.log',
                sprintf(
                    "[%s] IMPORT / %s / db=%s / file=%s / size=%d`n",
                    $this->now()->format('Y-m-d H:i:s'),
                    $message,
                    $dbName,
                    basename($sqlFile),
                    $fileSize
                ),
                FILE_APPEND
            );
        };

        $cmd = sprintf(
            'mysql --protocol=tcp -h%s -P%s -u%s %s',
            escapeshellarg((string) $dbConfig['host']),
            escapeshellarg((string) ($dbConfig['port'] ?? 3306)),
            escapeshellarg((string) $dbConfig['user']),
            escapeshellarg($dbName)
        );

        $trace('mysql-cli-start');
        $trace('command=' . $cmd);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, [
            'MYSQL_PWD' => (string) ($dbConfig['pass'] ?? ''),
        ]);

        if (!is_resource($process)) {
            $trace('proc-open-failed');
            return [
                'success' => false,
                'message' => 'mysql CLI 실행에 실패했습니다.',
            ];
        }

        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $fh = fopen($sqlFile, 'rb');
        if (!$fh) {
            $trace('sql-file-open-failed');
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return ['success' => false, 'message' => 'Unable to open the SQL file.'];
        }

        $bytesSent = 0;
        $stdoutBuffer = '';
        $stderrBuffer = '';
        $lastProgressLogAt = 0;
        $lastWriteProgressAt = time();
        $pendingChunk = '';
        $stdinClosed = false;

        while (true) {
            if ($pendingChunk === '' && !$stdinClosed) {
                if (feof($fh)) {
                    fclose($fh);
                    fclose($pipes[0]);
                    $stdinClosed = true;
                    $trace('input-stream-finished;bytes_sent=' . $bytesSent);
                } else {
                    $chunk = fread($fh, 8192);
                    if ($chunk === false) {
                        $trace('sql-file-read-failed');
                        fclose($fh);
                        fclose($pipes[0]);
                        $stdout = $stdoutBuffer . stream_get_contents($pipes[1]);
                        $stderr = $stderrBuffer . stream_get_contents($pipes[2]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        proc_close($process);

                        return [
                            'success' => false,
                            'message' => trim($stderr ?: $stdout ?: 'Failed to read the SQL file.'),
                        ];
                    }

                    if ($chunk !== '') {
                        $pendingChunk = $chunk;
                    }
                }
            }

            $stdoutBuffer .= stream_get_contents($pipes[1]);
            $stderrBuffer .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);

            if (!($status['running'] ?? false)) {
                $trace('process-finished-during-stream;exit=' . ($status['exitcode'] ?? 'null') . ';bytes_sent=' . $bytesSent);
                break;
            }

            if ($pendingChunk !== '') {
                $written = @fwrite($pipes[0], $pendingChunk);

                if ($written === false) {
                    $trace('stdin-write-failed;running=' . (($status['running'] ?? false) ? '1' : '0') . ';exit=' . ($status['exitcode'] ?? 'null'));
                    fclose($fh);
                    fclose($pipes[0]);
                    $stdout = $stdoutBuffer . stream_get_contents($pipes[1]);
                    $stderr = $stderrBuffer . stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);

                    return [
                        'success' => false,
                        'message' => trim($stderr ?: $stdout ?: 'mysql 프로세스가 SQL을 처리하지 못했습니다.'),
                    ];
                }

                if ($written > 0) {
                    $bytesSent += $written;
                    $pendingChunk = (string) substr($pendingChunk, $written);
                    $lastWriteProgressAt = time();
                }
            }

            $nowTs = time();
            if (($nowTs - $lastProgressLogAt) >= 5) {
                $trace(sprintf(
                    'streaming;bytes_sent=%d/%d;pending=%d;running=%s;exit=%s;stderr_bytes=%d',
                    $bytesSent,
                    $fileSize,
                    strlen($pendingChunk),
                    (($status['running'] ?? false) ? '1' : '0'),
                    ($status['exitcode'] ?? 'null'),
                    strlen($stderrBuffer)
                ));
                $lastProgressLogAt = $nowTs;
            }

            if (!$stdinClosed && ($nowTs - $lastWriteProgressAt) >= 30) {
                $trace('stdin-write-timeout;terminating-mysql;bytes_sent=' . $bytesSent . ';pending=' . strlen($pendingChunk));
                fclose($fh);
                fclose($pipes[0]);
                proc_terminate($process);
                $stdoutBuffer .= stream_get_contents($pipes[1]);
                $stderrBuffer .= stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return [
                    'success' => false,
                    'message' => trim($stderrBuffer ?: $stdoutBuffer ?: 'mysql process did not accept SQL input and was aborted.'),
                ];
            }

            if ($stdinClosed) {
                break;
            }

            usleep(100000);
        }

        $waitStartedAt = time();
        while (true) {
            $status = proc_get_status($process);
            $stdoutBuffer .= stream_get_contents($pipes[1]);
            $stderrBuffer .= stream_get_contents($pipes[2]);

            if (!($status['running'] ?? false)) {
                $trace('process-finished-before-close;exit=' . ($status['exitcode'] ?? 'null'));
                break;
            }

            if ((time() - $waitStartedAt) >= 120) {
                $trace('process-wait-timeout;terminating-mysql');
                proc_terminate($process);
                $stdoutBuffer .= stream_get_contents($pipes[1]);
                $stderrBuffer .= stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return [
                    'success' => false,
                    'message' => trim($stderrBuffer ?: $stdoutBuffer ?: 'mysql 蹂듦뎄 ?묒뾽??120珥??댁뿉 ?앸굹吏 ?딆븘 以묐떒?덉뒿?덈떎.'),
                ];
            }

            usleep(200000);
        }

        $stdout = $stdoutBuffer . stream_get_contents($pipes[1]);
        $stderr = $stderrBuffer . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $trace('proc-close;exit=' . $exitCode . ';stderr_bytes=' . strlen($stderr) . ';stdout_bytes=' . strlen($stdout));

        if ($exitCode !== 0) {
            $trace('import-failed;message=' . trim($stderr ?: $stdout ?: 'unknown-error'));
            return [
                'success' => false,
                'message' => trim($stderr ?: $stdout ?: 'An error occurred during mysql restore.'),
            ];
        }

        $trace('import-success');
        return ['success' => true];
    }
    private function rollbackSecondaryFromSnapshot(string $snapshotPath, array $secondaryConfig, string $dbName): array
    {
        $result = [
            'attempted' => true,
            'success' => false,
            'message' => '롤백에 실패했습니다.',
        ];

        try {
            $secondaryPdo = $this->connectSecondaryPdo($secondaryConfig, $dbName);
            $this->dropAllTables($secondaryPdo);
            $import = $this->importSqlFileToDatabase($snapshotPath, $secondaryConfig, $dbName);

            $result['success'] = (bool) ($import['success'] ?? false);
            $result['message'] = !empty($import['success'])
                ? '복구 실패 후 Secondary DB를 스냅샷으로 롤백했습니다.'
                : ($import['message'] ?? '알 수 없는 오류가 발생했습니다.');
        } catch (Throwable $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    private function getRestoreStatusPath(): string
    {
        return $this->backupDir . 'secondary_restore_status.json';
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
        $status = $this->mergeAppliedFields($status);

        if (!isset($status['updated_at'])) {
            $status['updated_at'] = $this->now()->format('Y-m-d H:i:s');
        }

        $json = json_encode(
            $status,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            $fallback = [
                'state' => $status['state'] ?? 'failed',
                'file' => $status['file'] ?? null,
                'started_at' => $status['started_at'] ?? null,
                'finished_at' => $status['finished_at'] ?? null,
                'updated_at' => $status['updated_at'],
                'stage' => $status['stage'] ?? 'status-write-failed',
                'message' => 'An encoding error occurred while saving restore status.',
                'warning' => 'Check the log file for the detailed message.',
            ];

            $json = json_encode(
                $fallback,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE
            );
        }

        if ($json !== false) {
            @file_put_contents($this->getRestoreStatusPath(), $json);
        }
    }

    private function mergeAppliedFields(array $status): array
    {
        $previous = $this->readRestoreStatus();
        if (!$previous) {
            return $this->normalizeAppliedStatus($status);
        }

        $previous = $this->normalizeAppliedStatus($previous);
        foreach (['applied_file', 'applied_at', 'applied_result', 'applied_source', 'latest_backup_file_at_apply'] as $field) {
            if (!array_key_exists($field, $status) && array_key_exists($field, $previous)) {
                $status[$field] = $previous[$field];
            }
        }

        return $this->normalizeAppliedStatus($status);
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

    private function appendSecondarySyncState(array $status): array
    {
        $status = $this->normalizeAppliedStatus($status);
        $latestBackupPath = $this->findLatestBackupFile();
        $latestBackupFile = $latestBackupPath ? basename($latestBackupPath) : null;
        $state = (string) ($status['state'] ?? '');
        $appliedFile = (string) ($status['applied_file'] ?? '');
        $appliedResult = (string) ($status['applied_result'] ?? '');

        if ($state === 'running') {
            $syncState = 'RUNNING';
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
            'RUNNING'  => 'Restore running',
            'FAILED'   => 'Restore failed',
            'APPLIED'  => 'Latest backup applied',
            'OUTDATED' => 'Latest backup not applied',
            default    => 'Sync state unknown',
        };

        return $status;
    }

    private function isRestoreStatusStale(array $status): bool
    {
        $pivot = $status['updated_at'] ?? $status['started_at'] ?? null;
        if (!$pivot) {
            return false;
        }

        $ts = strtotime((string) $pivot);
        if (!$ts) {
            return false;
        }

        return (time() - $ts) >= self::RESTORE_STALE_SECONDS;
    }

    private function writeRestoreProgress(
        string $state,
        string $file,
        string $startedAt,
        string $stage,
        string $message
    ): void {
        $payload = [
            'state' => $state,
            'file' => $file,
            'started_at' => $startedAt,
            'updated_at' => $this->now()->format('Y-m-d H:i:s'),
            'stage' => $stage,
            'message' => $message,
        ];

        $this->writeRestoreStatus($payload);

        @file_put_contents(
            $this->backupDir . 'secondary_restore_trace.log',
            sprintf(
                "[%s] %s / %s / %s\n",
                $payload['updated_at'],
                strtoupper($state),
                $stage,
                $message
            ),
            FILE_APPEND
        );
    }

    private function writeSecondaryRestoreLog(array $result): void
    {
        $line = sprintf(
            "[%s] %s: %s%s\n",
            $this->now()->format('Y-m-d H:i:s'),
            ($result['success'] ?? false) ? 'SUCCESS' : 'FAILED',
            $result['file'] ?? '-',
            !empty($result['message']) ? ' / ' . $result['message'] : ''
        );

        @file_put_contents($this->backupDir . 'secondary_restore_log.txt', $line, FILE_APPEND);
    }

}
