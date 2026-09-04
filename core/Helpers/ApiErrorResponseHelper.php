<?php

declare(strict_types=1);

namespace Core\Helpers;

final class ApiErrorResponseHelper
{
    public static function payload(string $error, string $message, array $details = []): array
    {
        return array_replace([
            'success' => false,
            'error' => $error,
            'reason_code' => $error,
            'message' => $message,
            'field_errors' => [],
            'missing_inputs' => [],
            'invalid_workdays' => [],
        ], $details);
    }

    public static function exception(\Throwable $exception, string $fallback): array
    {
        $unsafe = $exception instanceof \PDOException
            || $exception->getPrevious() instanceof \PDOException
            || str_contains($exception->getMessage(), 'SQLSTATE');
        if ($unsafe) return self::payload('INTERNAL_ERROR', $fallback);
        $message = trim($exception->getMessage());
        $error = str_contains(strtolower($message), 'request key') || str_contains($message, 'request_key')
            ? 'REQUEST_KEY_ERROR'
            : 'VALIDATION_ERROR';
        return self::payload($error, $message !== '' ? $message : $fallback);
    }
}
