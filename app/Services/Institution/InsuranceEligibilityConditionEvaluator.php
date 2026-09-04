<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class InsuranceEligibilityConditionEvaluator
{
    public const TRUE = 'TRUE';
    public const FALSE = 'FALSE';
    public const UNKNOWN = 'UNKNOWN';

    /** @param list<string> $states */
    public function combine(array $states, string $combinationCode): string
    {
        $combinationCode = strtoupper(trim($combinationCode));
        if (!in_array($combinationCode, ['ALL', 'ANY', 'NONE'], true)) {
            throw new \InvalidArgumentException('지원하지 않는 가입자격 조건 결합 방식입니다.');
        }
        if ($states === []) return self::TRUE;
        foreach ($states as $state) {
            if (!in_array($state, [self::TRUE, self::FALSE, self::UNKNOWN], true)) {
                throw new \InvalidArgumentException('지원하지 않는 가입자격 조건 평가값입니다.');
            }
        }
        $hasTrue = in_array(self::TRUE, $states, true);
        $hasFalse = in_array(self::FALSE, $states, true);
        $hasUnknown = in_array(self::UNKNOWN, $states, true);
        return match ($combinationCode) {
            'ALL' => $hasFalse ? self::FALSE : ($hasUnknown ? self::UNKNOWN : self::TRUE),
            'ANY' => $hasTrue ? self::TRUE : ($hasUnknown ? self::UNKNOWN : self::FALSE),
            'NONE' => $hasTrue ? self::FALSE : ($hasUnknown ? self::UNKNOWN : self::TRUE),
        };
    }
}
