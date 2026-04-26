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
                throw new \RuntimeException('백업 파일을 저장할 수 없습니다.');
            }

            $size = (int) (@filesize($filepath) ?: 0);
            $time = $this->now()->format('Y-m-d H:i:s');

            $this->writeBackupLog(sprintf('[%s] BACKUP SUCCESS: %s (%d bytes)', $time, $filename, $size));
            $this->logger->info('[BACKUP] done', ['file' => $filename, 'size' => $size]);

            return [
                'success' => true,
                'message' => 'Primary DB 백업이 완료되었습니다.',
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
                'message' => '자동 백업이 비활성화되어 있습니다.',
                'skipped' => true,
            ];
        }

        $due = $this->getAutoBackupDueDecision();
        if (!$due['due']) {
            return [
                'success' => false,
                'message' => $due['message'],
                'skipped' => true,
                'schedule' => $due['schedule'],
                'backup_time' => $due['backup_time'],
            ];
        }

        $result = $this->backupDatabase();
        if (!$result['success']) {
            return $result;
        }

        $restoreResult = null;
        if ($this->settings->getBool('backup_restore_secondary_enabled', false)) {
            $restoreResult = $this->restoreLatestBackupToSecondary('auto');
        }

        $this->writeAutoBackupMeta([
            'last_run_at' => $this->now()->format('Y-m-d H:i:s'),
            'last_schedule' => $due['schedule'],
            'last_schedule_key' => $due['schedule_key'],
            'last_backup_file' => $result['filename'] ?? null,
        ]);

        if ($restoreResult) {
            $result['secondary_restore'] = [
                'success' => (bool) ($restoreResult['success'] ?? false),
                'message' => $restoreResult['message'] ?? '',
                'state' => $restoreResult['state'] ?? null,
            ];
        }

        return $result;
    }

    public function getBackupDirectory(): string
    {
        return $this->backupDir;
    }

    public function getBackupDirectoryMasked(): string
    {
        return '\uBC31\uC5C5 \uACBD\uB85C\uB294 \uD654\uBA74\uC5D0\uC11C \uB9C8\uC2A4\uD0B9\uB429\uB2C8\uB2E4.';
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
                'message' => '복원할 백업 파일이 없습니다.',
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
            'message' => 'Secondary DB 복원을 진행 중입니다.',
            'trigger' => $trigger,
            'file' => $latestFile,
            'started_at' => $startedAt,
            'updated_at' => $startedAt,
            'stage' => 'starting',
            'warning' => '복원 중 문제가 발생하면 스냅샷을 기준으로 Secondary DB 상태를 복구합니다.',
        ];
        $this->writeRestoreStatus($status);
        $this->writeRestoreProgress('running', $latestFile, $startedAt, 'starting', '복원을 시작했습니다.');

        try {
            $db = $this->getCurrentDatabaseName();

            $this->writeRestoreProgress('running', $latestFile, $startedAt, 'load-secondary-config', 'Secondary 설정을 불러오는 중입니다.');
            $secondaryConfig = $this->getSecondaryConfig();

            $this->writeRestoreProgress('running', $latestFile, $startedAt, 'connect-secondary', 'Secondary DB에 연결하는 중입니다.');
            $secondaryPdo = $this->connectSecondaryPdo($secondaryConfig, $db);

            $this->writeRestoreProgress('running', $latestFile, $startedAt, 'snapshot-secondary', 'Secondary DB 스냅샷을 생성하는 중입니다.');
            $snapshotPath = $this->createSecondarySnapshot($secondaryPdo, $db);
            $dropCompleted = false;

            try {
                $this->writeRestoreProgress('running', $latestFile, $startedAt, 'drop-secondary-tables', 'Secondary DB 기존 테이블을 정리하는 중입니다.');
                $this->dropAllTables($secondaryPdo);
                $dropCompleted = true;

                $this->writeRestoreProgress('running', $latestFile, $startedAt, 'import-backup', '최신 백업 파일을 Secondary DB에 복원하는 중입니다.');
                $import = $this->importSqlFileToDatabase($latestPath, $secondaryConfig, $db);
                if (!$import['success']) {
                    throw new \RuntimeException($import['message']);
                }
            } catch (Throwable $restoreError) {
                $rollbackResult = [
                    'attempted' => false,
                    'success' => false,
                    'message' => '롤백을 시도하지 않았습니다.',
                ];

                if ($dropCompleted) {
                    $this->writeRestoreProgress('running', $latestFile, $startedAt, 'rollback-secondary', '복원 실패로 롤백하는 중입니다.');
                    $rollbackResult = $this->rollbackSecondaryFromSnapshot($snapshotPath, $secondaryConfig, $db);
                }

                $result = [
                    'success' => false,
                    'state' => 'failed',
                    'message' => 'Secondary DB 복원에 실패했습니다.',
                    'error' => $restoreError->getMessage(),
                    'file' => $latestFile,
                    'started_at' => $startedAt,
                    'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                    'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                    'stage' => $dropCompleted ? 'rollback-secondary' : 'import-backup',
                    'rollback_attempted' => $rollbackResult['attempted'],
                    'rollback_success' => $rollbackResult['success'],
                    'rollback_message' => $rollbackResult['message'],
                    'warning' => '복원 실패 후 롤백을 시도했습니다. Secondary DB 상태를 직접 확인해주세요.',
                ];

                $this->writeRestoreStatus($result);
                $this->writeSecondaryRestoreLog($result);
                return $result;
            }

            $result = [
                'success' => true,
                'state' => 'success',
                'message' => 'Secondary DB 복원이 완료되었습니다.',
                'file' => $latestFile,
                'started_at' => $startedAt,
                'finished_at' => $this->now()->format('Y-m-d H:i:s'),
                'updated_at' => $this->now()->format('Y-m-d H:i:s'),
                'stage' => 'completed',
                'rollback_attempted' => false,
                'rollback_success' => false,
            ];

            $this->writeRestoreStatus($result);
            $this->writeSecondaryRestoreLog($result);
            return $result;
        } catch (Throwable $e) {
            $result = [
                'success' => false,
                'state' => 'failed',
                'message' => 'Secondary DB 복원 중 오류가 발생했습니다.',
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
            return [
                'state' => 'idle',
                'message' => '복원 작업이 없습니다.',
            ];
        }

        if (($status['state'] ?? '') === 'running' && $this->isRestoreStatusStale($status)) {
            $status['state'] = 'failed';
            $status['message'] = '복원 작업이 제한 시간을 초과해 실패 상태로 전환되었습니다. Secondary DB 상태를 직접 확인해주세요.';
            $status['finished_at'] = $this->now()->format('Y-m-d H:i:s');
            $status['updated_at'] = $status['finished_at'];
            $status['stage'] = 'stale-timeout';
            $status['stale'] = true;
            $status['warning'] = 'mysql 복원 프로세스가 비정상 종료되었을 수 있습니다. Secondary DB 상태와 백업 로그를 확인해주세요.';
            $this->writeRestoreStatus($status);
            $this->writeSecondaryRestoreLog($status);
        }

        return $status;
    }

    private function ensureBackupDir(): void
    {
        if (is_dir($this->backupDir)) {
            return;
        }

        if (!@mkdir($this->backupDir, 0777, true) && !is_dir($this->backupDir)) {
            throw new \RuntimeException('백업 경로를 생성할 수 없습니다.');
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
            throw new \RuntimeException('데이터베이스 이름을 확인할 수 없습니다.');
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

    private function normalizeBackupTime(?string $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $value, $matches)) {
            return '02:00';
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
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
            return [];
        }

        $json = json_decode((string) @file_get_contents($path), true);
        return is_array($json) ? $json : [];
    }

    private function writeAutoBackupMeta(array $payload): void
    {
        @file_put_contents(
            $this->getAutoBackupMetaPath(),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private function getAutoBackupDueDecision(): array
    {
        $now = $this->now();
        $schedule = (string) $this->settings->get('backup_schedule', 'daily');
        if (!in_array($schedule, ['daily', 'weekly', 'monthly'], true)) {
            $schedule = 'daily';
        }

        $backupTime = $this->normalizeBackupTime((string) $this->settings->get('backup_time', '02:00'));
        [$hour, $minute] = array_map('intval', explode(':', $backupTime));
        $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');
        $scheduledMinutes = ($hour * 60) + $minute;

        if ($currentMinutes < $scheduledMinutes) {
            return [
                'due' => false,
                'message' => '설정된 백업 시간이 아직 되지 않았습니다.',
                'schedule' => $schedule,
                'backup_time' => $backupTime,
                'schedule_key' => $this->getScheduleKey($schedule, $now),
            ];
        }

        $scheduleKey = $this->getScheduleKey($schedule, $now);
        $meta = $this->readAutoBackupMeta();
        if (($meta['last_schedule_key'] ?? '') === $scheduleKey) {
            return [
                'due' => false,
                'message' => '현재 스케줄에서는 이미 백업이 실행되었습니다.',
                'schedule' => $schedule,
                'backup_time' => $backupTime,
                'schedule_key' => $scheduleKey,
            ];
        }

        return [
            'due' => true,
            'message' => '자동 백업 실행 대상입니다.',
            'schedule' => $schedule,
            'backup_time' => $backupTime,
            'schedule_key' => $scheduleKey,
        ];
    }

    private function getScheduleKey(string $schedule, DateTimeImmutable $now): string
    {
        return match ($schedule) {
            'weekly' => $now->format('o-\WW'),
            'monthly' => $now->format('Y-m'),
            default => $now->format('Y-m-d'),
        };
    }

    private function getSecondaryConfig(): array
    {
        $configPath = PROJECT_ROOT . '/../secure-config/db_replication.php';
        if (!is_file($configPath)) {
            throw new \RuntimeException('복원 DB 설정 파일을 찾을 수 없습니다.');
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
            throw new \RuntimeException('Secondary DB 접속 정보가 충분하지 않습니다.');
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
            throw new \RuntimeException('Secondary DB 스냅샷을 저장할 수 없습니다.');
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
            return ['success' => false, 'message' => 'SQL 파일을 찾을 수 없습니다.'];
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
            return ['success' => false, 'message' => 'mysql CLI 실행에 실패했습니다.'];
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
            return ['success' => false, 'message' => 'SQL 파일을 열지 못했습니다.'];
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
                            'message' => trim($stderr ?: $stdout ?: 'SQL 파일 읽기에 실패했습니다.'),
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
                        'message' => trim($stderr ?: $stdout ?: 'mysql 프로세스에 SQL을 전달하지 못했습니다.'),
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
                    'message' => trim($stderrBuffer ?: $stdoutBuffer ?: 'mysql 프로세스가 SQL 입력을 받지 않아 중단했습니다.'),
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
                    'message' => trim($stderrBuffer ?: $stdoutBuffer ?: 'mysql 복원 프로세스가 120초 이상 종료되지 않아 중단했습니다.'),
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
                'message' => trim($stderr ?: $stdout ?: 'mysql 복원 중 오류가 발생했습니다.'),
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
                ? '복원 실패 전 기준으로 Secondary 상태를 롤백했습니다.'
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
                'message' => '복원 상태를 저장하는 중 인코딩 오류가 발생했습니다.',
                'warning' => '상세 메시지는 로그 파일을 확인해주세요.',
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
