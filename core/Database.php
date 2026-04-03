<?php
// 경로: PROJECT_ROOT . '/core/Database.php';

namespace Core;

class Database
{
    private static ?Database $instance = null;
    private \PDO $connection;

    private function __construct()
    {
        // 1. DB 설정 파일 경로
        $configPath = PROJECT_ROOT . '/../secure-config/db_config.php';

        if (!file_exists($configPath)) {
            error_log("[Database] DB config file not found: {$configPath}");
            die('Database configuration file missing.');
        }

        // 2. 설정 파일 로드
        $config = require $configPath;

        // 3. 필수 키 확인
        $required = ['host', 'dbname', 'user', 'pass'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                error_log("[Database] Missing DB config key: {$key}");
                die("Database config key missing: {$key}");
            }
        }

        // 4. PDO 연결
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";

            $this->connection = new \PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

        } catch (\PDOException $e) {
            error_log("[Database] Connection failed: " . $e->getMessage());
            die('Database connection failed.');
        }
    }

    // 싱글턴 인스턴스
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // PDO 핸들 반환
    public function getConnection(): \PDO
    {
        return $this->connection;
    }
}
