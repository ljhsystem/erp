<?php

namespace Core\Helpers;

class RefTypeHelper
{
    public const OPTIONS = [
        'MANUAL' => '수동전표',
        'AUTO' => '자동전표',
        'ADJUST' => '조정전표',
        'CLOSING' => '결산전표',
        'ACCOUNT' => '일반',
        'EXPENSE' => '비용',
        'PAYMENT' => '지급',
        'TAX' => '세금',
        'CLIENT' => '거래처',
        'PROJECT' => '프로젝트',
        'CARD' => '카드',
        'EMPLOYEE' => '직원',
        'ORDER' => '주문',
        'CUSTOMS' => '통관',
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
