<?php

namespace App\Repositories\Funds;

use PDO;

class InternalTransferRepository
{
    private const CONFIRMED_STATUSES = ['REVIEWED', 'POSTED', 'CLOSED'];

    public function __construct(private PDO $pdo)
    {
    }

    public function confirmedEvidenceMap(): array
    {
        $candidates = $this->loadCandidates();
        if ($candidates === []) {
            return [];
        }

        $this->attachBankAccountLineRefs($candidates);
        $map = [];

        foreach ($candidates as $candidate) {
            if (!self::qualifies($candidate)) {
                continue;
            }

            [$outgoing, $incoming] = self::directionalEvidences($candidate['evidences']);
            $voucher = $candidate['voucher'];
            $amount = (float) $outgoing['withdraw_amount'];
            $common = [
                'voucher_id' => (string) $voucher['id'],
                'voucher_no' => (string) ($voucher['voucher_no'] ?? ''),
                'voucher_status' => strtoupper((string) $voucher['status']),
                'transfer_amount' => $amount,
            ];

            $map[(string) $outgoing['id']] = $common + [
                'direction' => 'OUT',
                'direction_label' => '내부출금',
                'counterpart_evidence_id' => (string) $incoming['id'],
                'counterpart_bank_account_id' => (string) $incoming['bank_account_id'],
                'counterpart_bank_name' => (string) ($incoming['bank_name'] ?? ''),
                'counterpart_account_name' => (string) ($incoming['account_name'] ?? ''),
                'counterpart_account_number' => (string) ($incoming['account_number'] ?? ''),
            ];
            $map[(string) $incoming['id']] = $common + [
                'direction' => 'IN',
                'direction_label' => '내부입금',
                'counterpart_evidence_id' => (string) $outgoing['id'],
                'counterpart_bank_account_id' => (string) $outgoing['bank_account_id'],
                'counterpart_bank_name' => (string) ($outgoing['bank_name'] ?? ''),
                'counterpart_account_name' => (string) ($outgoing['account_name'] ?? ''),
                'counterpart_account_number' => (string) ($outgoing['account_number'] ?? ''),
            ];
        }

        return $map;
    }

    public static function qualifies(array $candidate): bool
    {
        $voucher = is_array($candidate['voucher'] ?? null) ? $candidate['voucher'] : [];
        if (!in_array(strtoupper((string) ($voucher['status'] ?? '')), self::CONFIRMED_STATUSES, true)
            || !empty($voucher['deleted_at'])
            || (int) ($voucher['is_reversal'] ?? 0) !== 0
            || trim((string) ($voucher['reversal_of'] ?? '')) !== '') {
            return false;
        }

        $evidences = array_values(is_array($candidate['evidences'] ?? null) ? $candidate['evidences'] : []);
        if (count($evidences) !== 2) {
            return false;
        }

        [$outgoing, $incoming] = self::directionalEvidences($evidences);
        if ($outgoing === null || $incoming === null) {
            return false;
        }

        $outgoingAccountId = trim((string) ($outgoing['bank_account_id'] ?? ''));
        $incomingAccountId = trim((string) ($incoming['bank_account_id'] ?? ''));
        if ($outgoingAccountId === ''
            || $incomingAccountId === ''
            || $outgoingAccountId === $incomingAccountId
            || empty($outgoing['account_exists'])
            || empty($incoming['account_exists'])
            || self::cents($outgoing['withdraw_amount'] ?? 0) !== self::cents($incoming['deposit_amount'] ?? 0)) {
            return false;
        }

        $lineRefs = array_values(is_array($candidate['bank_account_line_refs'] ?? null)
            ? $candidate['bank_account_line_refs']
            : []);
        if (count($lineRefs) !== 2) {
            return false;
        }

        $outgoingMatches = 0;
        $incomingMatches = 0;
        foreach ($lineRefs as $lineRef) {
            if (empty($lineRef['account_exists'])) {
                return false;
            }
            $accountId = trim((string) ($lineRef['bank_account_id'] ?? ''));
            $debit = self::cents($lineRef['debit'] ?? 0);
            $credit = self::cents($lineRef['credit'] ?? 0);

            if ($accountId === $outgoingAccountId
                && $debit === 0
                && $credit === self::cents($outgoing['withdraw_amount'] ?? 0)) {
                $outgoingMatches++;
            }
            if ($accountId === $incomingAccountId
                && $credit === 0
                && $debit === self::cents($incoming['deposit_amount'] ?? 0)) {
                $incomingMatches++;
            }
        }

        return $outgoingMatches === 1 && $incomingMatches === 1;
    }

    private function loadCandidates(): array
    {
        $stmt = $this->pdo->query("
            SELECT voucher.id AS voucher_id,
                   voucher.voucher_no,
                   voucher.status,
                   voucher.is_reversal,
                   voucher.reversal_of,
                   voucher.deleted_at AS voucher_deleted_at,
                   evidence.id AS evidence_id,
                   evidence.bank_account_id,
                   evidence.raw_deposit_amount AS deposit_amount,
                   evidence.raw_withdraw_amount AS withdraw_amount,
                   account.bank_name,
                   account.account_name,
                   account.account_number
            FROM ledger_vouchers voucher
            INNER JOIN ledger_evidence_links evidence_link
              ON evidence_link.target_type = 'VOUCHER'
             AND evidence_link.target_id = voucher.id
             AND evidence_link.evidence_type = 'BANK_TRANSACTION'
             AND evidence_link.deleted_at IS NULL
            INNER JOIN ledger_evidence_bank_transaction evidence
              ON evidence.id = evidence_link.evidence_id
             AND evidence.deleted_at IS NULL
            LEFT JOIN system_bank_accounts account
              ON account.id = evidence.bank_account_id
             AND account.deleted_at IS NULL
             AND account.is_active = 1
        ");

        $candidates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $voucherId = (string) $row['voucher_id'];
            $candidates[$voucherId] ??= [
                'voucher' => [
                    'id' => $voucherId,
                    'voucher_no' => $row['voucher_no'],
                    'status' => $row['status'],
                    'is_reversal' => $row['is_reversal'],
                    'reversal_of' => $row['reversal_of'],
                    'deleted_at' => $row['voucher_deleted_at'],
                ],
                'evidences' => [],
                'bank_account_line_refs' => [],
            ];
            $candidates[$voucherId]['evidences'][(string) $row['evidence_id']] = [
                'id' => (string) $row['evidence_id'],
                'bank_account_id' => (string) $row['bank_account_id'],
                'deposit_amount' => $row['deposit_amount'],
                'withdraw_amount' => $row['withdraw_amount'],
                'account_exists' => $row['account_name'] !== null,
                'bank_name' => $row['bank_name'],
                'account_name' => $row['account_name'],
                'account_number' => $row['account_number'],
            ];
        }

        foreach ($candidates as &$candidate) {
            $candidate['evidences'] = array_values($candidate['evidences']);
        }
        unset($candidate);

        return $candidates;
    }

    private function attachBankAccountLineRefs(array &$candidates): void
    {
        $voucherIds = array_keys($candidates);
        if ($voucherIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($voucherIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT voucher_line.voucher_id,
                   voucher_line.id AS voucher_line_id,
                   line_ref.ref_id AS bank_account_id,
                   account.account_name,
                   voucher_line.debit,
                   voucher_line.credit
            FROM ledger_voucher_lines voucher_line
            INNER JOIN ledger_voucher_line_refs line_ref
              ON line_ref.voucher_line_id = voucher_line.id
             AND line_ref.ref_target = 'ACCOUNT'
            LEFT JOIN system_bank_accounts account
              ON account.id = line_ref.ref_id
             AND account.deleted_at IS NULL
             AND account.is_active = 1
            WHERE voucher_line.voucher_id IN ({$placeholders})
            ORDER BY voucher_line.voucher_id, voucher_line.line_no, line_ref.id
        ");
        $stmt->execute($voucherIds);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $voucherId = (string) $row['voucher_id'];
            if (!isset($candidates[$voucherId])) {
                continue;
            }
            $candidates[$voucherId]['bank_account_line_refs'][] = [
                'voucher_line_id' => (string) $row['voucher_line_id'],
                'bank_account_id' => (string) $row['bank_account_id'],
                'account_exists' => $row['account_name'] !== null,
                'debit' => $row['debit'],
                'credit' => $row['credit'],
            ];
        }
    }

    private static function directionalEvidences(array $evidences): array
    {
        $outgoing = null;
        $incoming = null;

        foreach ($evidences as $evidence) {
            $deposit = self::cents($evidence['deposit_amount'] ?? 0);
            $withdraw = self::cents($evidence['withdraw_amount'] ?? 0);
            if ($withdraw > 0 && $deposit === 0 && $outgoing === null) {
                $outgoing = $evidence;
            } elseif ($deposit > 0 && $withdraw === 0 && $incoming === null) {
                $incoming = $evidence;
            } else {
                return [null, null];
            }
        }

        return [$outgoing, $incoming];
    }

    private static function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
