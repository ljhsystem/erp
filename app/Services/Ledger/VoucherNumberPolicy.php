<?php

namespace App\Services\Ledger;

class VoucherNumberPolicy
{
    private const PATTERN = '/^\d{8}-\d{4}$/';
    private const LOCKED_STATUSES = ['posted', 'closed'];

    public function normalize(string $voucherNo): string
    {
        return trim($voucherNo);
    }

    public function assertValidFormat(string $voucherNo): void
    {
        if (!preg_match(self::PATTERN, $voucherNo)) {
            throw new \RuntimeException('전표번호 형식은 YYYYMMDD-XXXX 입니다.');
        }
    }

    public function assertEditable(array $voucher): void
    {
        $status = strtolower(trim((string) ($voucher['status'] ?? '')));
        if (in_array($status, self::LOCKED_STATUSES, true)) {
            throw new \RuntimeException('승인 또는 마감 상태의 전표번호는 변경할 수 없습니다.');
        }
    }
}
