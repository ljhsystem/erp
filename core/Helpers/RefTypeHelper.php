<?php

namespace Core\Helpers;

class RefTypeHelper
{
    public const OPTIONS = [
        'CLIENT' => '거래처',
        'PROJECT' => '프로젝트',
        'EMPLOYEE' => '직원',
        'ACCOUNT' => '계좌',
        'BANK_ACCOUNT' => '계좌',
        'CARD' => '카드',
    ];

    public static function labels(): array
    {
        return self::OPTIONS;
    }

    public static function values(): array
    {
        return array_keys(self::OPTIONS);
    }

    public static function isValid(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return array_key_exists($value, self::OPTIONS);
    }

    public static function label(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return self::OPTIONS[$value] ?? $value;
    }
}
