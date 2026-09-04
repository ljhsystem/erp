<?php

namespace App\Services\Ledger;

final class EvidenceWorkflowPolicyService
{
    public const CORRECTION_REQUIRED = 'CORRECTION_REQUIRED';
    public const COMPLETED = 'COMPLETED';

    public const LINK_SOURCE_TRACE = 'SOURCE_TRACE';
    public const LINK_ACCOUNTING_READY = 'ACCOUNTING_READY';

    public function canLink(string $status, string $purpose): bool
    {
        $status = strtoupper(trim($status));
        $purpose = strtoupper(trim($purpose));

        if ($purpose === self::LINK_SOURCE_TRACE) {
            return in_array($status, [self::CORRECTION_REQUIRED, self::COMPLETED], true);
        }

        return $purpose === self::LINK_ACCOUNTING_READY && $status === self::COMPLETED;
    }

    public function canBecomeVoucherCandidate(string $status): bool
    {
        return strtoupper(trim($status)) === self::COMPLETED;
    }

    public function transitionAllowed(string $from, string $to): bool
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        return in_array($from . '>' . $to, [
            self::CORRECTION_REQUIRED . '>' . self::COMPLETED,
            self::COMPLETED . '>' . self::CORRECTION_REQUIRED,
        ], true);
    }
}
