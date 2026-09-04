<?php

declare(strict_types=1);

namespace App\Services\Institution;

final class BusinessIncomeEvidenceCanonicalPolicy
{
    public const SOURCE_TYPE = 'INTERNAL_APPROVAL';
    public const DOCUMENT_TYPE = 'BUSINESS_INCOME_REPORT';
    public const DIRECTION = 'EXPENSE';
    public const EVIDENCE_TYPE = 'BUSINESS_INCOME';

    public function assert(array $evidence): void
    {
        $expected = [
            'source_type' => self::SOURCE_TYPE,
            'import_type' => self::DOCUMENT_TYPE,
            'transaction_direction' => self::DIRECTION,
            'operation_type' => self::EVIDENCE_TYPE,
            'employee_id' => null,
        ];
        foreach ($expected as $field => $value) {
            if (($evidence[$field] ?? null) !== $value) {
                throw new \DomainException('BUSINESS_INCOME_EVIDENCE_CANONICAL_INVALID');
            }
        }
        $money = static fn(string $field): float => round((float) ($evidence[$field] ?? 0), 2);
        if (round($money('raw_gross_payment_amount') - $money('raw_total_deduction_amount'), 2) !== $money('raw_net_payment_amount')) {
            throw new \DomainException('BUSINESS_INCOME_EVIDENCE_AMOUNT_GRAIN_INVALID');
        }
    }
}
