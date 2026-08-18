<?php

namespace App\Services\Institution;

use DateTimeImmutable;

final class EmployeeAssignmentResolver
{
    public const PLANNED = 'PLANNED';
    public const ACTIVE = 'ACTIVE';
    public const ENDED = 'ENDED';
    public const CANCELLED = 'CANCELLED';

    public static function effectiveStatus(string $startDate, ?string $endDate, string $asOfDate, ?string $storedStatus = null): string
    {
        self::assertDate($startDate);
        self::assertDate($asOfDate);
        if ($endDate !== null) self::assertDate($endDate);
        if (strtoupper((string) $storedStatus) === self::CANCELLED) return self::CANCELLED;
        if ($asOfDate < $startDate) return self::PLANNED;
        if ($endDate !== null && $asOfDate > $endDate) return self::ENDED;
        return self::ACTIVE;
    }

    public static function effectiveStatusSql(string $startColumn, string $endColumn, string $statusColumn, string $quotedAsOfDate): string
    {
        return "CASE WHEN {$statusColumn}='CANCELLED' THEN 'CANCELLED'"
            . " WHEN {$quotedAsOfDate}<{$startColumn} THEN 'PLANNED'"
            . " WHEN {$endColumn} IS NOT NULL AND {$quotedAsOfDate}>{$endColumn} THEN 'ENDED'"
            . " ELSE 'ACTIVE' END";
    }

    public static function containsSql(string $startColumn, string $endColumn, string $quotedAsOfDate): string
    {
        return "{$startColumn}<={$quotedAsOfDate} AND ({$endColumn} IS NULL OR {$endColumn}>={$quotedAsOfDate})";
    }

    private static function assertDate(string $value): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new \InvalidArgumentException('기준일을 YYYY-MM-DD 형식으로 확인해 주세요.');
    }
}
