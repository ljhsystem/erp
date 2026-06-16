<?php

namespace Core;

use RuntimeException;

class Database
{
    private static ?Database $instance = null;
    private \PDO $connection;

    private function __construct()
    {
        $config = $this->loadRuntimeConfig();

        foreach (['host', 'dbname', 'user', 'pass'] as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                error_log("[Database] Missing DB config key: {$key}");
                die("Database config key missing: {$key}");
            }
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) $config['host'],
                (int) ($config['port'] ?? 3306),
                (string) $config['dbname']
            );

            $this->connection = new \PDO(
                $dsn,
                (string) $config['user'],
                (string) $config['pass'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (\PDOException $e) {
            error_log('[Database] Connection failed: ' . $e->getMessage());
            die('Database connection failed.');
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }

        return self::$instance;
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }

    private function loadRuntimeConfig(): array
    {
        $topology = $this->loadPhpArrayIfExists(PROJECT_ROOT . '/../secure-config/db_replication.php');
        $legacy = $this->loadPhpArrayIfExists(PROJECT_ROOT . '/../secure-config/db_config.php');

        if (is_array($topology)) {
            $resolved = $this->resolveConfigFromTopology($topology, $legacy);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (is_array($legacy)) {
            return $legacy;
        }

        throw new RuntimeException('No database runtime configuration was found.');
    }

    private function resolveConfigFromTopology(array $topology, ?array $legacy): ?array
    {
        $target = strtolower((string) ($topology['active_target'] ?? ''));
        if (!in_array($target, ['primary', 'secondary'], true)) {
            $target = $this->inferTargetFromLegacy($legacy, $topology);
        }

        if (!in_array($target, ['primary', 'secondary'], true)) {
            return null;
        }

        $node = $topology[$target] ?? null;
        if (!is_array($node)) {
            return null;
        }

        $dbname = (string) ($node['dbname'] ?? $topology['dbname'] ?? $legacy['dbname'] ?? '');

        return [
            'host' => (string) ($node['host'] ?? ''),
            'port' => (int) ($node['port'] ?? 3306),
            'user' => (string) ($node['user'] ?? ''),
            'pass' => (string) ($node['pass'] ?? ''),
            'dbname' => $dbname,
        ];
    }

    private function inferTargetFromLegacy(?array $legacy, array $topology): string
    {
        if (!is_array($legacy)) {
            return '';
        }

        $host = (string) ($legacy['host'] ?? '');
        $port = (int) ($legacy['port'] ?? 3306);

        foreach (['primary', 'secondary'] as $target) {
            $node = $topology[$target] ?? null;
            if (!is_array($node)) {
                continue;
            }

            if ($host === (string) ($node['host'] ?? '') && $port === (int) ($node['port'] ?? 3306)) {
                return $target;
            }
        }

        return '';
    }

    private function loadPhpArrayIfExists(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $data = require $path;
        return is_array($data) ? $data : null;
    }
}
