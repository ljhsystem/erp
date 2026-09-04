<?php
// 경로: PROJECT_ROOT . '/core/LoggerFactory.php';

namespace Core;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger;
use function Core\storage_system_path;

class LoggerFactory
{
    private static array $instances = [];

    public static function getLogger(string $name = 'app'): Logger
    {
        $channel = self::normalizeChannel($name);
        if (isset(self::$instances[$channel])) {
            return self::$instances[$channel];
        }

        $logDir = storage_system_path('logs');
        if (!$logDir) {
            throw new \RuntimeException('로그 저장경로가 설정되지 않았습니다.');
        }

        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            throw new \RuntimeException('로그 저장경로를 생성하지 못했습니다.');
        }

        $filePath = rtrim($logDir, '/\\') . '/' . $channel . '.log';
        $logger = new Logger($channel);

        $handler = new RotatingFileHandler(
            $filePath,
            30,
            self::minimumLevel()
        );
        $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true, false, false));

        $logger->pushHandler($handler);
        $logger->pushProcessor(static function (LogRecord $record) use ($channel): LogRecord {
            return $record->with(
                message: self::redactText($record->message),
                context: self::redactContext($record->context),
                extra: self::redactContext(['log_channel' => $channel] + $record->extra)
            );
        });

        self::$instances[$channel] = $logger;
        return $logger;
    }

    public static function normalizeChannel(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === 'app') {
            return 'system-application';
        }

        $name = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name) ?? $name;
        $name = strtolower(str_replace(['.', '_', '\\', '/'], '-', $name));
        $name = preg_replace('/-service(?=-|$)/', '', $name) ?? $name;
        $name = preg_replace('/-+/', '-', $name) ?? $name;
        $name = trim($name, '-');
        $name = preg_replace('/^service-([a-z0-9]+)-\1-/', 'service-$1-', $name) ?? $name;

        if (str_starts_with($name, 'core-') || str_starts_with($name, 'core.middleware-')) {
            $name = 'system-' . $name;
        } elseif (str_starts_with($name, 'cli-')) {
            $name = 'job-' . substr($name, 4);
        } elseif ($name === 'security') {
            $name = 'security-authentication';
        }

        return preg_replace('/[^a-z0-9-]/', '-', $name) ?: 'system-application';
    }

    public static function redactContext(array $context): array
    {
        $redacted = [];
        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (self::isSecretKey($normalizedKey)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            if (str_contains($normalizedKey, 'email')) {
                $redacted[$key] = is_string($value) ? self::maskEmail($value) : '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $redacted[$key] = self::redactContext($value);
                continue;
            }
            if ($value instanceof \Throwable) {
                $redacted[$key] = [
                    'exception_class' => $value::class,
                    'error_code' => (string) $value->getCode(),
                    'message' => self::redactText($value->getMessage()),
                ];
                continue;
            }
            $redacted[$key] = is_string($value) ? self::redactText($value) : $value;
        }

        return $redacted;
    }

    private static function minimumLevel(): Level
    {
        $configured = strtolower(trim((string) getenv('APP_LOG_LEVEL')));
        if ($configured !== '') {
            try {
                return Logger::toMonologLevel($configured);
            } catch (\Throwable) {
                return Level::Info;
            }
        }

        return strtolower(trim((string) getenv('APP_ENV'))) === 'development'
            ? Level::Debug
            : Level::Info;
    }

    private static function isSecretKey(string $key): bool
    {
        foreach ([
            'password', 'passwd', 'secret', 'authorization', 'cookie', 'session', 'token',
            'access_token', 'refresh_token', 'csrf_token', 'api_key', 'service_key',
            'private_key', 'encryption_key', 'rrn', 'resident_registration',
            'social_security',
        ] as $blocked) {
            if ($key === $blocked || str_ends_with($key, '_' . $blocked)) {
                return true;
            }
        }

        return false;
    }

    private static function redactText(string $value): string
    {
        $value = preg_replace('/(?<!\d)(\d{6})-?[1-4]\d{6}(?!\d)/u', '$1-*******', $value) ?? $value;
        $value = preg_replace_callback(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            static fn (array $match): string => self::maskEmail($match[0]),
            $value
        ) ?? $value;

        return $value;
    }

    private static function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return '[REDACTED]';
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = $local === '' ? '' : mb_substr($local, 0, 1);
        return $visible . '***@' . $domain;
    }
}
