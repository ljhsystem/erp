<?php

namespace App\Controllers\Main\Settings;

use App\Services\Backup\DatabaseSyncService;
use Core\DbPdo;
use RuntimeException;
use function Core\storage_system_path;

class DatabaseSyncController
{
    private DatabaseSyncService $syncService;

    public function __construct()
    {
        $this->syncService = new DatabaseSyncService(DbPdo::conn());
    }

    public function apiSync(): void
    {
        try {
            $latest = $this->syncService->getLatestSyncInfo();
            if (($latest['state'] ?? '') === 'running') {
                $this->respondJson([
                    'success' => false,
                    'state' => 'running',
                    'message' => 'DB 동기화가 이미 진행 중입니다.',
                ], 409);
                return;
            }

            $this->writeSyncStartStatus($latest);
            $this->launchSyncRunner();

            $this->respondJson([
                'success' => true,
                'state' => 'running',
                'message' => 'DB 동기화 요청을 접수했습니다. 상태 카드에서 진행 상황을 확인해 주세요.',
            ], 202);
        } catch (\Throwable $e) {
            $this->respondJson([
                'success' => false,
                'state' => 'failed',
                'message' => 'DB 동기화 시작 중 오류가 발생했습니다.',
                'debug_message' => $this->isDebugEnabled() ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function apiSyncInfo(): void
    {
        try {
            $info = $this->syncService->getLatestSyncInfo();

            $this->respondJson([
                'success' => true,
                'data' => [
                    'state' => $this->normalizeText($info['state'] ?? 'idle'),
                    'message' => $this->toSyncMessage($info),
                    'file' => $this->normalizeText($info['file'] ?? null),
                    'started_at' => $this->normalizeText($info['started_at'] ?? null),
                    'finished_at' => $this->normalizeText($info['finished_at'] ?? null),
                    'updated_at' => $this->normalizeText($info['updated_at'] ?? null),
                    'stage' => $this->normalizeText($info['stage'] ?? null),
                    'stage_label' => $this->toStageLabel((string) ($info['stage'] ?? '')),
                    'last_synced_file' => $this->normalizeText($info['applied_file'] ?? null),
                    'last_synced_at' => $this->normalizeText($info['applied_at'] ?? null),
                    'last_error' => $this->normalizeText($info['error'] ?? null),
                    'stale' => (bool) ($info['stale'] ?? false),
                    'statement_count' => (int) ($info['statement_count'] ?? 0),
                    'snapshot_created' => (bool) ($info['snapshot_created'] ?? false),
                    'snapshot_file' => $this->normalizeText($info['snapshot_file'] ?? null),
                    'rollback_attempted' => (bool) ($info['rollback_attempted'] ?? false),
                    'rollback_success' => (bool) ($info['rollback_success'] ?? false),
                    'rollback_message' => $this->normalizeText($info['rollback_message'] ?? null),
                    'sync_state' => $this->normalizeText($info['sync_state'] ?? null),
                    'sync_state_label' => $this->normalizeText($info['sync_state_label'] ?? null),
                    'runtime' => is_array($info['runtime'] ?? null) ? $info['runtime'] : null,
                    'active_db' => $this->normalizeNode($info['active_db'] ?? null),
                    'standby_db' => $this->normalizeNode($info['standby_db'] ?? null),
                ],
            ]);
        } catch (\Throwable) {
            $this->respondJson([
                'success' => false,
                'message' => 'DB 동기화 상태 조회 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    private function toSyncMessage(array $info): string
    {
        return match ((string) ($info['state'] ?? 'idle')) {
            'running' => 'DB 동기화가 진행 중입니다.',
            'stale_suspected' => '동기화 상태 확인이 필요합니다.',
            'success' => 'DB 동기화가 완료되었습니다.',
            'failed' => 'DB 동기화에 실패했습니다.',
            default => 'DB 동기화 이력이 없습니다.',
        };
    }

    private function toStageLabel(string $stage): string
    {
        return match ($stage) {
            'starting' => 'starting',
            'load-standby-config', 'load-secondary-config' => 'load-standby-config',
            'connect-standby', 'connect-secondary' => 'connect-standby',
            'snapshot-standby' => 'snapshot-standby',
            'prepare-standby', 'drop-secondary-tables' => 'prepare-standby',
            'apply-backup', 'import-backup' => 'apply-sql-by-pdo',
            'rollback-standby' => 'rollback-standby',
            'stale-suspected' => 'stale-suspected',
            'completed' => 'completed',
            'stale-timeout' => 'timeout',
            'no-backup-file' => 'no-backup-file',
            default => $stage !== '' ? $stage : '-',
        };
    }

    private function normalizeNode(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        return [
            'role' => $this->normalizeText($value['role'] ?? null),
            'host' => $this->normalizeText($value['host'] ?? null),
            'port' => isset($value['port']) ? (int) $value['port'] : null,
            'dbname' => $this->normalizeText($value['dbname'] ?? null),
            'label' => $this->normalizeText($value['label'] ?? null),
            'topology_role' => $this->normalizeText($value['topology_role'] ?? null),
        ];
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        $normalized = mb_convert_encoding($text, 'UTF-8', 'UTF-8,CP949,EUC-KR,ISO-8859-1');
        return preg_replace('/^\xEF\xBB\xBF/u', '', $normalized) ?? $normalized;
    }

    private function writeSyncStartStatus(array $latest): void
    {
        $backupDir = storage_system_path('db_backup');
        if (!$backupDir) {
            throw new RuntimeException('DB backup storage path not configured.');
        }

        $now = date('Y-m-d H:i:s');
        $status = [
            'state' => 'running',
            'message' => 'DB 동기화를 시작하고 있습니다.',
            'trigger' => 'sync',
            'stage' => 'starting',
            'started_at' => $now,
            'updated_at' => $now,
            'statement_count' => 0,
            'snapshot_created' => false,
            'snapshot_file' => null,
            'rollback_attempted' => false,
            'rollback_success' => false,
            'rollback_message' => null,
        ];

        $latestFile = $latest['latest_backup_file'] ?? $latest['file'] ?? null;
        if (is_string($latestFile) && $latestFile !== '') {
            $status['file'] = $latestFile;
        }

        foreach ([
            'applied_file',
            'applied_at',
            'applied_result',
            'applied_source',
            'latest_backup_file_at_apply',
            'active_db',
            'standby_db',
        ] as $field) {
            if (array_key_exists($field, $latest)) {
                $status[$field] = $latest[$field];
            }
        }

        $path = rtrim(str_replace('\\', '/', $backupDir), '/') . '/secondary_restore_status.json';
        $json = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false || @file_put_contents($path, $json) === false) {
            throw new RuntimeException('동기화 시작 상태 파일을 저장할 수 없습니다.');
        }
    }

    private function launchSyncRunner(): void
    {
        $phpExecutable = $this->resolvePhpExecutable();
        $scriptPath = PROJECT_ROOT . '/cli/sync_runner.php';
        if (!is_file($scriptPath)) {
            throw new RuntimeException('동기화 실행 스크립트를 찾을 수 없습니다.');
        }

        $logDir = PROJECT_ROOT . '/storage/logs';
        if (!is_dir($logDir) && !@mkdir($logDir, 0777, true) && !is_dir($logDir)) {
            throw new RuntimeException('동기화 로그 디렉터리를 생성할 수 없습니다.');
        }

        $logPath = $logDir . '/cli-sync-runner.log';

        if (DIRECTORY_SEPARATOR === '\\') {
            $handle = @popen($this->buildWindowsBackgroundCommand($phpExecutable, $scriptPath, $logPath), 'r');
            if ($handle === false) {
                throw new RuntimeException('DB 동기화 실행 프로세스를 시작할 수 없습니다.');
            }

            @pclose($handle);
            return;
        }

        $command = $this->buildUnixBackgroundCommand($phpExecutable, $scriptPath, $logPath);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, PROJECT_ROOT);
        if (!is_resource($process)) {
            throw new RuntimeException('DB 동기화 실행 프로세스를 시작할 수 없습니다.');
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }

    private function resolvePhpExecutable(): string
    {
        $candidates = [];
        $binary = defined('PHP_BINARY') ? (string) PHP_BINARY : '';
        $binDir = defined('PHP_BINDIR') ? (string) PHP_BINDIR : '';

        if ($binDir !== '') {
            $candidates[] = $binDir . DIRECTORY_SEPARATOR . 'php.exe';
            $candidates[] = $binDir . DIRECTORY_SEPARATOR . 'php-cli.exe';
            $candidates[] = $binDir . DIRECTORY_SEPARATOR . 'php';
        }

        if ($binary !== '') {
            $binaryDir = dirname($binary);
            $candidates[] = $binaryDir . DIRECTORY_SEPARATOR . 'php.exe';
            $candidates[] = $binaryDir . DIRECTORY_SEPARATOR . 'php-cli.exe';
            $candidates[] = $binaryDir . DIRECTORY_SEPARATOR . 'php';
            $candidates[] = $binary;
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('PHP CLI 실행 파일을 찾을 수 없습니다.');
    }

    private function buildWindowsBackgroundCommand(string $phpExecutable, string $scriptPath, string $logPath): string
    {
        return sprintf(
            'start "" /B cmd /c ""%s" "%s" >> "%s" 2>&1"',
            str_replace('"', '""', $phpExecutable),
            str_replace('"', '""', $scriptPath),
            str_replace('"', '""', $logPath)
        );
    }

    private function buildUnixBackgroundCommand(string $phpExecutable, string $scriptPath, string $logPath): string
    {
        return sprintf(
            '%s %s >> %s 2>&1 &',
            escapeshellarg($phpExecutable),
            escapeshellarg($scriptPath),
            escapeshellarg($logPath)
        );
    }

    private function isDebugEnabled(): bool
    {
        $appDebug = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '';
        return in_array(strtolower((string) $appDebug), ['1', 'true', 'yes', 'on'], true);
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            http_response_code(500);
            $json = json_encode([
                'success' => false,
                'message' => 'JSON 응답 생성 중 오류가 발생했습니다.',
            ], JSON_UNESCAPED_UNICODE);
        }

        echo $json;
    }
}
