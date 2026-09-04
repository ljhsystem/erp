<?php

namespace App\Services\System;

final class StatutoryStandardPeriodStatusProjection
{
    public const SCHEDULED = 'SCHEDULED';
    public const CURRENT = 'CURRENT';
    public const ENDED = 'ENDED';

    public static function displayOptions(array $managedOptions = []): array
    {
        $managedByCode = [];
        foreach ($managedOptions as $option) {
            $code = strtoupper(trim((string)($option['value'] ?? '')));
            $label = trim((string)($option['label'] ?? ''));
            if ($code !== '' && $label !== '') $managedByCode[$code] = $option;
        }
        $labels = [
            self::SCHEDULED => '적용 예정',
            self::CURRENT => '현재 적용',
            self::ENDED => '적용 종료',
        ];
        $result = [];
        foreach ($labels as $code => $label) {
            $result[] = $managedByCode[$code] ?? ['value'=>$code, 'label'=>$label, 'extra_data'=>null];
        }
        return $result;
    }

    public static function sql(string $alias = 's', string $businessDateSql = 'CURRENT_DATE'): string
    {
        return "CASE WHEN {$alias}.effective_from>{$businessDateSql} THEN '" . self::SCHEDULED . "'"
            . " WHEN {$alias}.effective_from<={$businessDateSql}"
            . " AND ({$alias}.effective_to IS NULL OR {$alias}.effective_to>={$businessDateSql}) THEN '" . self::CURRENT . "'"
            . " ELSE '" . self::ENDED . "' END";
    }

    public static function normalizeFilter(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'CURRENT', 'ACTIVE', '현재 적용', '적용중', '현행' => self::CURRENT,
            'ENDED', 'EXPIRED', '종료', '적용종료' => self::ENDED,
            'SCHEDULED', '적용 예정', '적용예정', '예정' => self::SCHEDULED,
            default => '',
        };
    }
}
