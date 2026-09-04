<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use PDOException;
use Psr\Log\LoggerInterface;

trait LogsServiceOperations
{
    private function runLoggedOperation(
        LoggerInterface $logger,
        string $domainLabel,
        string $eventCode,
        string $action,
        array $context,
        callable $operation,
        string $successLevel = 'info',
        bool $runtimeIsBlocked = true,
        ?callable $resultClassifier = null
    ): mixed {
        $startedAt = microtime(true);
        $base = ['service' => self::class, 'action' => $action] + $context;

        try {
            $result = $operation();
            $outcome = $resultClassifier ? strtoupper((string) $resultClassifier($result)) : 'SUCCESS';
            if (!in_array($outcome, ['SUCCESS', 'BLOCKED', 'FAILED'], true)) {
                $outcome = 'FAILED';
            }
            $level = match ($outcome) {
                'SUCCESS' => $successLevel,
                'BLOCKED' => 'warning',
                default => 'error',
            };
            $message = match ($outcome) {
                'SUCCESS' => $domainLabel . ' 업무 처리를 완료했습니다.',
                'BLOCKED' => $domainLabel . ' 업무 처리가 차단되었습니다.',
                default => $domainLabel . ' 업무 처리에 실패했습니다.',
            };
            $logger->{$level}($message, [
                'event_code' => $eventCode . ($outcome === 'SUCCESS' ? '' : '_' . $outcome),
                'result' => $outcome,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ] + $base);
            return $result;
        } catch (PDOException $exception) {
            $this->logServiceFailure($logger, $domainLabel, $eventCode, $base, $startedAt, $exception);
            throw $exception;
        } catch (\InvalidArgumentException|\DomainException|\LogicException $exception) {
            $this->logServiceBlock($logger, $domainLabel, $eventCode, $base, $startedAt, $exception);
            throw $exception;
        } catch (\RuntimeException $exception) {
            if (!$runtimeIsBlocked) {
                $this->logServiceFailure($logger, $domainLabel, $eventCode, $base, $startedAt, $exception);
            } else {
                $this->logServiceBlock($logger, $domainLabel, $eventCode, $base, $startedAt, $exception);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logServiceFailure($logger, $domainLabel, $eventCode, $base, $startedAt, $exception);
            throw $exception;
        }
    }

    private function logServiceBlock(LoggerInterface $logger, string $label, string $event, array $base, float $startedAt, \Throwable $exception): void
    {
        $logger->warning($label . ' 업무 처리가 차단되었습니다.', ['event_code' => $event . '_BLOCKED', 'result' => 'BLOCKED', 'error_code' => get_class($exception), 'error' => $exception, 'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000)] + $base);
    }

    private function logServiceFailure(LoggerInterface $logger, string $label, string $event, array $base, float $startedAt, \Throwable $exception): void
    {
        $logger->error($label . ' 업무 처리에 실패했습니다.', ['event_code' => $event . '_FAILED', 'result' => 'FAILED', 'error_code' => get_class($exception), 'error' => $exception, 'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000)] + $base);
    }
}
