<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class DailyEmploymentIncomeLineContractService
{
    public function scopeKey(?string $workdayId): string
    {
        return $workdayId === null ? 'ITEM' : $workdayId;
    }

    public function assertGrain(string $lineType, string $lineCode, ?string $workdayId): void
    {
        $lineType = strtoupper(trim($lineType));
        $lineCode = strtoupper(trim($lineCode));
        $isWorkday = $workdayId !== null;
        $isTax = in_array($lineCode, ['DAILY_WORKER_INCOME_TAX', 'LOCAL_INCOME_TAX'], true);
        if ($lineType === 'PAY' && !$isWorkday) {
            throw new \InvalidArgumentException('지급 Line은 Workday Grain이어야 합니다.');
        }
        if ($lineType === 'EMPLOYER_BURDEN' && $isWorkday) {
            throw new \InvalidArgumentException('사용자부담 Line은 Item Grain이어야 합니다.');
        }
        if ($lineType === 'DEDUCTION' && $isTax !== $isWorkday) {
            throw new \InvalidArgumentException('세금 Workday Line과 보험 Item Line의 Grain이 일치하지 않습니다.');
        }
        if (!in_array($lineType, ['PAY', 'DEDUCTION', 'EMPLOYER_BURDEN'], true)) {
            throw new \InvalidArgumentException('지원하지 않는 일용근로소득 Line 유형입니다.');
        }
    }

    public function adjustmentReason(mixed $value, ?float $calculatedAmount, ?float $finalAmount): ?string
    {
        $reason = trim((string) $value);
        if (mb_strlen($reason) > 500) {
            throw new \InvalidArgumentException('적용금액 조정사유는 500자 이하로 입력해 주세요.');
        }
        if ($calculatedAmount !== null && $finalAmount !== null
            && abs($finalAmount - $calculatedAmount) >= 0.01 && $reason === '') {
            throw new \InvalidArgumentException('자동계산액과 다른 적용금액에는 조정사유가 필요합니다.');
        }
        return $reason === '' ? null : $reason;
    }
}
