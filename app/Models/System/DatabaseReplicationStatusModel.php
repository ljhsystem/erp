<?php

namespace App\Models\System;

use PDO;
use RuntimeException;
use Throwable;

class DatabaseReplicationStatusModel
{
    public function getServerIdentity(array $config): array
    {
        $row = $this->connect($config)->query(
            'SELECT @@hostname AS host, @@port AS port, @@read_only AS read_only'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    public function getReplicaStatus(array $config): ?array
    {
        $pdo = $this->connect($config);

        try {
            $status = $pdo->query('SHOW REPLICA STATUS')->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $status = $pdo->query('SHOW SLAVE STATUS')->fetch(PDO::FETCH_ASSOC);
        }

        return is_array($status) ? $status : null;
    }

    private function connect(array $config): PDO
    {
        foreach (['host', 'port', 'user', 'pass'] as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                throw new RuntimeException("Missing DB config: {$key}");
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            (string) $config['host'],
            (int) $config['port']
        );

        return new PDO($dsn, (string) $config['user'], (string) $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
    }
}
