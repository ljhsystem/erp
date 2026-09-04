<?php

namespace App\Services\Institution;

final class WorkplaceSizeRateResolver
{
    public function resolveAdditionalEmployerRate(array $workplaceSizePeriod, array $statutoryValueData): float
    {
        $sizeCode = trim((string) ($workplaceSizePeriod['business_size_code'] ?? ''));
        foreach ((array) ($statutoryValueData['additional_employer_rates'] ?? []) as $matrixRow) {
            if ((string) ($matrixRow['business_size_code'] ?? '') === $sizeCode && is_numeric($matrixRow['employer_rate'] ?? null)) {
                return (float) $matrixRow['employer_rate'];
            }
        }
        throw new \RuntimeException('회사규모 코드에 해당하는 직업능력개발 부담률이 없습니다.');
    }
}
