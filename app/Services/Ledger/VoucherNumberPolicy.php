<?php

namespace App\Services\Ledger;

class VoucherNumberPolicy
{
    private const PATTERN = '/^\d{8}-\d{4}$/';

    public function normalize(string $voucherNo): string
    {
        return trim($voucherNo);
    }

    public function assertValidFormat(string $voucherNo): void
    {
        if (!preg_match(self::PATTERN, $voucherNo)) {
            throw new \RuntimeException('?꾪몴踰덊샇 ?뺤떇? YYYYMMDD-XXXX ?낅땲??');
        }
    }

    public function assertEditable(array $voucher): void
    {
        if (VoucherStatus::isLocked($voucher['status'] ?? null)) {
            throw new \RuntimeException('승인 또는 마감 상태의 전표번호는 변경할 수 없습니다.');
        }
    }
}
