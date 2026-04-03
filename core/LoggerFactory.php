<?php
// 경로: PROJECT_ROOT . '/core/LoggerFactory.php';

namespace Core;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use function Core\storage_system_path;

class LoggerFactory
{
    private static array $instances = [];

    public static function getLogger(string $name = 'app'): Logger
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        // ✅ Storage 기준 로그 경로
        $logDir = storage_system_path('logs');
        if (!$logDir) {
            throw new \RuntimeException('Log storage path not configured');
        }

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $filePath = rtrim($logDir, '/') . '/' . $name . '.log';

        // Monolog 로거 생성
        $logger = new Logger($name);

        // 날짜 기준 회전 핸들러
        $handler = new RotatingFileHandler(
            $filePath,
            30,
            Logger::DEBUG
        );

        $logger->pushHandler($handler);

        self::$instances[$name] = $logger;
        return $logger;
    }
}
