<?php
// 경로: PROJECT_ROOT/app/services/system/DatabaseReplicationStatusService.php
namespace App\Services\System;

use PDO;
use Throwable;

class DatabaseReplicationStatusService
{
    private readonly PDO $pdo;
    private array $primary;
    private array $secondary;

    public function __construct(PDO $pdo)
    {
        $this->pdo         = $pdo;        
        $configPath = PROJECT_ROOT . '/../secure-config/db_replication.php';
        if (!file_exists($configPath)) {
            throw new \RuntimeException('Replication DB config not found');
        }
        $config = require $configPath;
        $this->primary   = $config['primary']   ?? [];
        $this->secondary = $config['secondary'] ?? [];
    }

    /**
     * 전체 이중화 상태
     */
    public function check(): array
    {
        return [
            'primary'    => $this->checkPrimary(),
            'secondary'  => $this->checkSecondary(),
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Primary DB 상태 체크
     */
    private function checkPrimary(): array
    {
        try {
            $pdo = $this->connect($this->primary);

            $row = $pdo->query(
                "SELECT @@hostname AS host, @@port AS port, @@read_only AS read_only"
            )->fetch(PDO::FETCH_ASSOC);

            return [
                'online'    => true,
                'host'      => $row['host'] ?? null,
                'port'      => $row['port'] ?? null,
                'read_only' => ((int)$row['read_only'] === 1)
            ];

        } catch (Throwable $e) {
            return [
                'online' => false,
                'error'  => $e->getMessage()
            ];
        }
    }

    /**
     * Secondary DB (Replication) 상태 체크
     */
    private function checkSecondary(): array
    {
        try {
            $pdo = $this->connect($this->secondary);

            /**
             * MySQL 8.0.22+ : SHOW REPLICA STATUS
             * MySQL 5.x / MariaDB : SHOW SLAVE STATUS
             */
            try {
                $status = $pdo->query("SHOW REPLICA STATUS")->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $status = $pdo->query("SHOW SLAVE STATUS")->fetch(PDO::FETCH_ASSOC);
            }

            // ▶ Replication 미구성 (정상적인 STANDBY 상태)
            if (!$status) {
                return [
                    'online'      => true,
                    'replication' => false,
                    'message'     => 'Replication 정보 없음'
                ];
            }

            // ▶ Lag 값 보정 (NULL이면 null 유지)
            $lag = $status['Seconds_Behind_Master'] ?? null;
            $lag = is_numeric($lag) ? (int)$lag : null;

            return [
                'online'      => true,
                'replication' => true,
                'io_running'  => ($status['Slave_IO_Running'] ?? $status['Replica_IO_Running'] ?? '') === 'Yes',
                'sql_running' => ($status['Slave_SQL_Running'] ?? $status['Replica_SQL_Running'] ?? '') === 'Yes',
                'lag'         => $lag,
                'last_error'  => $status['Last_Error'] ?? $status['Last_SQL_Error'] ?? null
            ];

        } catch (Throwable $e) {
            return [
                'online' => false,
                'error'  => $e->getMessage()
            ];
        }
    }


    /**
     * 진단 전용 PDO 생성
     */
    private function connect(array $cfg): PDO
    {
        foreach (['host','port','user','pass'] as $key) {
            if (!isset($cfg[$key])) {
                throw new \InvalidArgumentException("Missing DB config: {$key}");
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $cfg['host'],
            $cfg['port']
        );

        return new PDO(
            $dsn,
            $cfg['user'],
            $cfg['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2, // 상태 체크는 짧게
            ]
        );
    }
}
