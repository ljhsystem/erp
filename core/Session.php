<?php

namespace Core;

use Core\LoggerFactory;
use function Core\storage_system_path;

class Session
{
    private static bool $initialized = false;
    private static $logger;

    private static function logInit(): void
    {
        if (!self::$logger) {
            self::$logger = LoggerFactory::getLogger('core-Session');
        }
    }

    public static function start(int $timeoutMinutes = 30): void
    {
        self::logInit();

        if (self::$initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            );

            $savePath = storage_system_path('sessions');
            if (!$savePath) {
                throw new \RuntimeException('Session storage path not configured');
            }

            if (!is_dir($savePath)) {
                mkdir($savePath, 0777, true);
            }

            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $cookieDomain = preg_replace('/:\d+$/', '', $host);

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $cookieDomain,
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_name('SUKHYANG_ERP');
            session_save_path($savePath);
            session_start();
            self::$initialized = true;
        }

        if (empty($_SESSION['expire_time'])) {
            $_SESSION['expire_time'] = time() + ($timeoutMinutes * 60);
        }
    }

    public static function write(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_write_close();
        self::$initialized = false;
    }

    public static function extend(int $timeoutMinutes = 30): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::start($timeoutMinutes);
        }

        $_SESSION['expire_time'] = time() + ($timeoutMinutes * 60);

        return (int)$_SESSION['expire_time'];
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            return;
        }

        $_SESSION = [];
        session_unset();
        session_destroy();
        self::$initialized = false;
    }

    public static function getExpireTime(): int
    {
        return (int)($_SESSION['expire_time'] ?? 0);
    }

    public static function isExpired(): bool
    {
        return self::getExpireTime() < time();
    }
}
