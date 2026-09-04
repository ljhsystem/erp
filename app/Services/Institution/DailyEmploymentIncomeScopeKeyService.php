<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class DailyEmploymentIncomeScopeKeyService
{
    public const SCHEMA_VERSION = 'DAILY_INCOME_LINE_SCOPE_V1';

    public function lineKeys(
        string $itemId,
        ?string $workdayId,
        ?string $revisionId,
        ?string $effectiveFrom,
        ?string $effectiveTo
    ): array {
        $itemId = $this->requiredId($itemId, '작업자 Item');
        $workdayId = $this->nullable($workdayId);
        $revisionId = $this->nullable($revisionId);
        $effectiveFrom = $this->date($effectiveFrom);
        $effectiveTo = $this->date($effectiveTo);

        if (($effectiveFrom === null) !== ($effectiveTo === null)) {
            throw new \InvalidArgumentException('적용기간 시작일과 종료일은 함께 입력해야 합니다.');
        }
        if ($effectiveFrom !== null && ($workdayId !== null || $effectiveFrom > $effectiveTo)) {
            throw new \InvalidArgumentException('Workday 범위와 기간 범위를 동시에 사용할 수 없거나 적용기간이 올바르지 않습니다.');
        }

        return [
            'workday_scope_key' => $workdayId ?? 'ITEM',
            'revision_scope_key' => $revisionId ?? 'BASE',
            'period_scope_key' => $effectiveFrom === null ? 'NONE' : $effectiveFrom . ':' . $effectiveTo,
        ];
    }

    private function requiredId(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException($label . ' ID가 필요합니다.');
        }
        return $value;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function date(?string $value): ?string
    {
        $value = $this->nullable($value);
        if ($value === null) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('적용기간은 YYYY-MM-DD 형식이어야 합니다.');
        }
        return $value;
    }
}
