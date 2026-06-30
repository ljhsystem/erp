<?php

namespace App\Services\System;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseActiveSwitchService
{
    private const STATUS_STALE_SECONDS = 300;
    private const TRACE_RECENT_SECONDS = 120;

    private string $legacyConfigPath;
    private string $topologyConfigPath;
    private string $logDir;
    private string $statusPath;
    private string $logPath;

    public function __construct()
    {
        $this->legacyConfigPath = PROJECT_ROOT . '/../secure-config/db_config.php';
        $this->topologyConfigPath = PROJECT_ROOT . '/../secure-config/db_replication.php';
        $this->logDir = PROJECT_ROOT . '/storage/db_backup/';
        $this->statusPath = $this->logDir . 'active_db_switch_status.json';
        $this->logPath = $this->logDir . 'active_db_switch_log.txt';
    }

    public function getLatestSwitch(): array
    {
        if (!is_file($this->statusPath)) {
            return [];
        }

        $json = @file_get_contents($this->statusPath);
        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public function getSwitchGuardStatus(): array
    {
        $syncStatus = $this->readJsonStatus($this->logDir . 'secondary_restore_status.json');
        if ($this->isSyncRunning($syncStatus)) {
            return [
                'blocked' => true,
                'source' => 'sync',
                'message' => 'DB 동기화 진행 중에는 Active DB를 전환할 수 없습니다.',
            ];
        }

        $restoreStatus = $this->readJsonStatus($this->logDir . 'active_restore_status.json');
        if ($this->isRestoreRunning($restoreStatus)) {
            return [
                'blocked' => true,
                'source' => 'restore',
                'message' => 'DB 복원 진행 중에는 Active DB를 전환할 수 없습니다.',
            ];
        }

        return [
            'blocked' => false,
            'source' => null,
            'message' => null,
        ];
    }

    public function switchActiveDatabase(string $target, array $actor): array
    {
        $guard = $this->getSwitchGuardStatus();
        if (!empty($guard['blocked'])) {
            throw new RuntimeException((string) $guard['message']);
        }

        $targetRole = $this->normalizeTarget($target);
        $topology = $this->loadPhpArray($this->topologyConfigPath, 'DB topology config not found.');
        $legacy = $this->loadPhpArrayIfExists($this->legacyConfigPath);
        $currentRole = $this->resolveCurrentRole($topology, $legacy);

        if ($currentRole === $targetRole) {
            throw new RuntimeException('이미 선택한 DB가 Active DB로 설정되어 있습니다.');
        }

        $targetKey = strtolower($targetRole);
        $targetConfig = $this->normalizeNodeConfig($topology, $targetKey, $legacy);
        $this->validateTargetConnection($targetConfig);

        $topology['active_target'] = $targetKey;
        $topology = $this->ensureTopologyDbname($topology, $targetConfig, $legacy);

        $this->ensureDirectory($this->logDir);
        $this->writeTopologyConfig($topology);

        $executedAt = date('Y-m-d H:i:s');
        $payload = [
            'before_active_db' => $this->labelForRole($currentRole, (int) ($this->normalizeNodeConfig($topology, strtolower($currentRole), $legacy)['port'] ?? 0)),
            'after_active_db' => $this->labelForRole($targetRole, (int) ($targetConfig['port'] ?? 0)),
            'executed_by_user_id' => (string) ($actor['user_id'] ?? ''),
            'executed_by_name' => (string) ($actor['display_name'] ?? '사용자 없음'),
            'executed_at' => $executedAt,
        ];

        $this->writeStatus($payload);
        $this->appendLog($payload);

        return $payload;
    }

    private function normalizeTarget(string $target): string
    {
        $normalized = strtolower(trim($target));

        return match ($normalized) {
            'primary', '3306' => 'PRIMARY',
            'secondary', '3307' => 'SECONDARY',
            default => throw new RuntimeException('전환 대상 DB 값이 올바르지 않습니다.'),
        };
    }

    private function resolveCurrentRole(array $topology, ?array $legacy): string
    {
        $activeTarget = strtolower((string) ($topology['active_target'] ?? ''));
        if (in_array($activeTarget, ['primary', 'secondary'], true)) {
            return strtoupper($activeTarget);
        }

        if (is_array($legacy)) {
            $host = (string) ($legacy['host'] ?? '');
            $port = (int) ($legacy['port'] ?? 3306);
            foreach (['primary', 'secondary'] as $target) {
                $node = $topology[$target] ?? null;
                if (!is_array($node)) {
                    continue;
                }

                if ($host === (string) ($node['host'] ?? '') && $port === (int) ($node['port'] ?? 3306)) {
                    return strtoupper($target);
                }
            }
        }

        throw new RuntimeException('현재 Active DB를 확인할 수 없습니다.');
    }

    private function normalizeNodeConfig(array $topology, string $target, ?array $legacy): array
    {
        $node = $topology[$target] ?? null;
        if (!is_array($node)) {
            throw new RuntimeException('전환 대상 DB 설정이 올바르지 않습니다.');
        }

        $dbName = (string) ($node['dbname'] ?? $topology['dbname'] ?? $legacy['dbname'] ?? '');

        return [
            'host' => (string) ($node['host'] ?? ''),
            'port' => (int) ($node['port'] ?? ($target === 'primary' ? 3306 : 3307)),
            'user' => (string) ($node['user'] ?? ''),
            'pass' => (string) ($node['pass'] ?? ''),
            'dbname' => $dbName,
        ];
    }

    private function ensureTopologyDbname(array $topology, array $targetConfig, ?array $legacy): array
    {
        $dbName = (string) ($topology['dbname'] ?? $targetConfig['dbname'] ?? $legacy['dbname'] ?? '');
        if ($dbName !== '') {
            $topology['dbname'] = $dbName;
        }

        foreach (['primary', 'secondary'] as $target) {
            if (!isset($topology[$target]) || !is_array($topology[$target])) {
                continue;
            }

            if (!isset($topology[$target]['dbname']) || $topology[$target]['dbname'] === '') {
                $topology[$target]['dbname'] = $dbName;
            }
        }

        return $topology;
    }

    private function labelForRole(string $role, int $port): string
    {
        return sprintf('%s (%d)', $role, $port);
    }

    private function loadPhpArray(string $path, string $message): array
    {
        if (!is_file($path)) {
            throw new RuntimeException($message);
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('DB 설정 파일 형식이 올바르지 않습니다.');
        }

        return $config;
    }

    private function loadPhpArrayIfExists(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $data = require $path;
        return is_array($data) ? $data : null;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('로그 저장 경로를 생성하지 못했습니다.');
        }
    }

    private function writeTopologyConfig(array $config): void
    {
        $content = "<?php\n";
        $content .= "// 경로: PROJECT_ROOT . '/../secure-config/db_replication.php'\n";
        $content .= "// 웹에서 직접 접근을 차단한 DB 토폴로지 설정 파일\n";
        $content .= "if (basename(__FILE__) === basename(\$_SERVER['SCRIPT_FILENAME'])) {\n";
        $content .= "    http_response_code(403);\n";
        $content .= "    exit('이 파일은 직접 접근할 수 없습니다.');\n";
        $content .= "}\n\n";
        $content .= 'return ' . var_export($config, true) . ";\n";

        if (@file_put_contents($this->topologyConfigPath, $content, LOCK_EX) === false) {
            throw new RuntimeException('DB 토폴로지 설정 파일 저장에 실패했습니다.');
        }
    }

    private function writeStatus(array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || @file_put_contents($this->statusPath, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException('전환 상태 저장에 실패했습니다.');
        }
    }

    private function appendLog(array $payload): void
    {
        $line = sprintf(
            "[%s] %s -> %s / 사용자=%s (%s)\n",
            $payload['executed_at'] ?? '-',
            $payload['before_active_db'] ?? '-',
            $payload['after_active_db'] ?? '-',
            $payload['executed_by_name'] ?? '-',
            $payload['executed_by_user_id'] ?? '-'
        );

        if (@file_put_contents($this->logPath, $line, FILE_APPEND) === false) {
            throw new RuntimeException('전환 로그 저장에 실패했습니다.');
        }
    }

    private function validateTargetConnection(array $config): void
    {
        foreach (['host', 'port', 'dbname', 'user', 'pass'] as $key) {
            if (!array_key_exists($key, $config) || $config[$key] === '' || $config[$key] === null) {
                throw new RuntimeException('전환 대상 DB 연결 정보가 올바르지 않습니다.');
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) $config['host'],
            (int) $config['port'],
            (string) $config['dbname']
        );

        try {
            new PDO($dsn, (string) $config['user'], (string) $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException) {
            throw new RuntimeException('전환 대상 DB에 연결할 수 없습니다.');
        }
    }

    private function readJsonStatus(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $json = @file_get_contents($path);
        if ($json === false || $json === '') {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private function isSyncRunning(?array $status): bool
    {
        if (!$this->isRunningStatus($status)) {
            return false;
        }

        if (!$this->isStatusStale($status)) {
            return true;
        }

        $runtime = $this->inspectTraceRuntime(
            $this->logDir . 'secondary_restore_trace.log',
            $this->logDir . 'secondary_restore_runner.lock'
        );

        return (bool) ($runtime['trace_recent'] || $runtime['runner_active']);
    }

    private function isRestoreRunning(?array $status): bool
    {
        if (!$this->isRunningStatus($status)) {
            return false;
        }

        if (!$this->isStatusStale($status)) {
            return true;
        }

        $runtime = $this->inspectTraceRuntime($this->logDir . 'active_restore_trace.log');
        return (bool) ($runtime['trace_recent']);
    }

    private function isRunningStatus(?array $status): bool
    {
        return is_array($status) && (string) ($status['state'] ?? '') === 'running';
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
}
