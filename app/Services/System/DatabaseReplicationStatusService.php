<?php

namespace App\Services\System;

use PDO;
use RuntimeException;
use Throwable;

class DatabaseReplicationStatusService
{
    private readonly PDO $pdo;
    private array $topology;
    private array $primary;
    private array $secondary;
    private array $active;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->topology = $this->loadTopologyConfig();
        $this->primary = $this->normalizeNodeConfig('primary');
        $this->secondary = $this->normalizeNodeConfig('secondary');
        $this->active = $this->loadActiveConfig();
    }

    public function check(): array
    {
        return [
            'primary' => $this->checkPrimary(),
            'secondary' => $this->checkSecondary(),
            'active_db' => $this->getActiveDatabaseInfo(),
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function loadTopologyConfig(): array
    {
        $configPath = PROJECT_ROOT . '/../secure-config/db_replication.php';
        if (!is_file($configPath)) {
            throw new RuntimeException('Replication DB config not found');
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new RuntimeException('Replication DB config format is invalid');
        }

        return $config;
    }

    private function loadActiveConfig(): array
    {
        $legacy = $this->loadLegacyActiveConfig();
        $target = strtolower((string) ($this->topology['active_target'] ?? ''));
        if (!in_array($target, ['primary', 'secondary'], true)) {
            $target = $this->inferTargetFromLegacy($legacy);
        }

        if (in_array($target, ['primary', 'secondary'], true)) {
            return $this->normalizeNodeConfig($target, $legacy);
        }

        if (is_array($legacy)) {
            return $legacy;
        }

        throw new RuntimeException('Active DB config not found');
    }

    private function loadLegacyActiveConfig(): ?array
    {
        $configPath = PROJECT_ROOT . '/../secure-config/db_config.php';
        if (!is_file($configPath)) {
            return null;
        }

        $config = require $configPath;
        return is_array($config) ? $config : null;
    }

    private function normalizeNodeConfig(string $target, ?array $legacy = null): array
    {
        $node = $this->topology[$target] ?? [];
        $fallbackDbName = (string) ($legacy['dbname'] ?? '');
        $dbName = (string) ($node['dbname'] ?? $this->topology['dbname'] ?? $fallbackDbName);

        return [
            'host' => (string) ($node['host'] ?? ''),
            'port' => (int) ($node['port'] ?? ($target === 'primary' ? 3306 : 3307)),
            'user' => (string) ($node['user'] ?? ''),
            'pass' => (string) ($node['pass'] ?? ''),
            'dbname' => $dbName,
        ];
    }

    private function inferTargetFromLegacy(?array $legacy): string
    {
        if (!is_array($legacy)) {
            return '';
        }

        $host = (string) ($legacy['host'] ?? '');
        $port = (int) ($legacy['port'] ?? 3306);

        foreach (['primary', 'secondary'] as $target) {
            $node = $this->topology[$target] ?? null;
            if (!is_array($node)) {
                continue;
            }

            if ($host === (string) ($node['host'] ?? '') && $port === (int) ($node['port'] ?? 3306)) {
                return $target;
            }
        }

        return '';
    }

    private function getActiveDatabaseInfo(): array
    {
        $host = (string) ($this->active['host'] ?? '');
        $port = (int) ($this->active['port'] ?? 3306);
        $dbname = (string) ($this->active['dbname'] ?? '');

        $primaryHost = (string) ($this->primary['host'] ?? '');
        $primaryPort = (int) ($this->primary['port'] ?? 3306);
        $secondaryHost = (string) ($this->secondary['host'] ?? '');
        $secondaryPort = (int) ($this->secondary['port'] ?? 3307);

        $role = 'UNKNOWN';
        if ($host === $primaryHost && $port === $primaryPort) {
            $role = 'PRIMARY';
        } elseif ($host === $secondaryHost && $port === $secondaryPort) {
            $role = 'SECONDARY';
        }

        return [
            'role' => $role,
            'host' => $host,
            'port' => $port,
            'dbname' => $dbname,
            'label' => sprintf('%s (%d)', $role, $port),
        ];
    }

    private function checkPrimary(): array
    {
        try {
            $pdo = $this->connect($this->primary);

            $row = $pdo->query(
                'SELECT @@hostname AS host, @@port AS port, @@read_only AS read_only'
            )->fetch(PDO::FETCH_ASSOC);

            return [
                'online' => true,
                'host' => $row['host'] ?? null,
                'port' => $row['port'] ?? null,
                'read_only' => ((int) ($row['read_only'] ?? 0) === 1),
            ];
        } catch (Throwable $e) {
            return [
                'online' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkSecondary(): array
    {
        try {
            $pdo = $this->connect($this->secondary);

            try {
                $status = $pdo->query('SHOW REPLICA STATUS')->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                $status = $pdo->query('SHOW SLAVE STATUS')->fetch(PDO::FETCH_ASSOC);
            }

            if (!$status) {
                return [
                    'online' => true,
                    'replication' => false,
                    'message' => 'Replication information is not available.',
                ];
            }

            $lag = $status['Seconds_Behind_Master'] ?? null;
            $lag = is_numeric($lag) ? (int) $lag : null;

            return [
                'online' => true,
                'replication' => true,
                'io_running' => ($status['Slave_IO_Running'] ?? $status['Replica_IO_Running'] ?? '') === 'Yes',
                'sql_running' => ($status['Slave_SQL_Running'] ?? $status['Replica_SQL_Running'] ?? '') === 'Yes',
                'lag' => $lag,
                'last_error' => $status['Last_Error'] ?? $status['Last_SQL_Error'] ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'online' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function connect(array $cfg): PDO
    {
        foreach (['host', 'port', 'user', 'pass'] as $key) {
            if (!isset($cfg[$key]) || $cfg[$key] === '') {
                throw new RuntimeException("Missing DB config: {$key}");
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            (string) $cfg['host'],
            (int) $cfg['port']
        );

        return new PDO(
            $dsn,
            (string) $cfg['user'],
            (string) $cfg['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]
        );
    }
}
