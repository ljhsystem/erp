<?php

namespace App\Services\Funds;

use App\Models\Funds\PaymentScheduleHistoryModel;
use App\Models\Funds\PaymentScheduleModel;
use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherLineRefModel;
use PDO;

class PaymentObligationService
{
    private PaymentScheduleModel $schedules;
    private PaymentScheduleHistoryModel $histories;
    private VoucherLineModel $voucherLines;
    private VoucherLineRefModel $voucherLineRefs;
    private EvidenceLinkModel $links;

    public function __construct(private PDO $pdo)
    {
        $this->schedules = new PaymentScheduleModel($pdo);
        $this->histories = new PaymentScheduleHistoryModel($pdo);
        $this->voucherLines = new VoucherLineModel($pdo);
        $this->voucherLineRefs = new VoucherLineRefModel($pdo);
        $this->links = new EvidenceLinkModel($pdo);
    }

    public function synchronizeOnFirstPosting(array $voucher, string $actor): array
    {
        $voucherId = trim((string) ($voucher['id'] ?? ''));
        if ($voucherId === '' || !empty($voucher['deleted_at'])) {
            return ['created' => 0, 'cancelled' => 0, 'review_required' => 0];
        }

        $reversalOf = trim((string) ($voucher['reversal_of'] ?? ''));
        if ($reversalOf !== '') {
            return $this->applyReversal($voucherId, $reversalOf, $actor);
        }

        $lines = $this->eligibleVoucherLines($voucherId);
        if ($lines === []) {
            return ['created' => 0, 'cancelled' => 0, 'review_required' => 0];
        }

        $refsByLine = $this->voucherLineRefs->getGroupedByVoucherLineIds(array_column($lines, 'id'));
        $voucherFallback = $this->unambiguousVoucherRefs($refsByLine);
        $created = 0;

        foreach ($lines as $line) {
            $lineId = (string) $line['id'];
            if ($this->schedules->existsByVoucherLine($voucherId, $lineId)) {
                continue;
            }
            $refs = $this->attribution($refsByLine[$lineId] ?? [], $voucherFallback);
            $scheduleId = $this->schedules->createFromVoucherLine([
                'voucher_id' => $voucherId,
                'voucher_line_id' => $lineId,
                'scheduled_amount' => number_format((float) $line['obligation_amount'], 2, '.', ''),
                'client_id' => $refs['client_id'],
                'project_id' => $refs['project_id'],
                'assignee_id' => $refs['assignee_id'],
                'memo' => trim((string) ($line['line_summary'] ?? '')),
            ], $actor);
            $after = $this->schedules->find($scheduleId);
            $this->histories->record(
                $scheduleId,
                'AUTO_CREATED_FROM_POSTED_VOUCHER',
                null,
                $after,
                '전표 최초 승인으로 지급의무가 생성되었습니다.',
                $actor
            );
            $created++;
        }

        return ['created' => $created, 'cancelled' => 0, 'review_required' => 0];
    }

    private function eligibleVoucherLines(string $voucherId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT line.id,
                   line.voucher_id,
                   line.line_summary,
                   account.payment_obligation_type,
                   (line.credit - line.debit) AS obligation_amount
            FROM ledger_voucher_lines line
            INNER JOIN ledger_accounts account
              ON account.id = line.account_id
             AND account.deleted_at IS NULL
             AND account.is_active = 1
             AND account.is_posting = 1
             AND account.creates_payment_obligation = 1
             AND account.payment_obligation_type IS NOT NULL
            WHERE line.voucher_id = :voucher_id
              AND (line.credit - line.debit) > 0
            ORDER BY line.line_no ASC
            FOR UPDATE
        ");
        $stmt->execute([':voucher_id' => $voucherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function applyReversal(string $reversalVoucherId, string $originalVoucherId, string $actor): array
    {
        $schedules = $this->schedules->lockBySourceVoucher($originalVoucherId);
        $cancelled = 0;
        $reviewRequired = 0;

        foreach ($schedules as $schedule) {
            if ((string) $schedule['obligation_lifecycle_status'] !== 'ACTIVE') {
                continue;
            }
            $allocations = $this->links->lockPaymentAllocationsBySchedule((string) $schedule['id']);
            $status = $allocations === [] ? 'CANCELLED' : 'REVIEW_REQUIRED';
            $reason = $status === 'CANCELLED'
                ? "역분개 전표 {$reversalVoucherId} 승인으로 취소되었습니다."
                : "역분개 전표 {$reversalVoucherId} 승인 후 기존 지급연결 검토가 필요합니다.";
            $this->schedules->setLifecycle((string) $schedule['id'], $status, $reason, $actor);
            $after = $this->schedules->find((string) $schedule['id']);
            $this->histories->record(
                (string) $schedule['id'],
                $status === 'CANCELLED' ? 'CANCELLED_BY_REVERSAL' : 'REVIEW_REQUIRED_BY_REVERSAL',
                $schedule,
                $after,
                $reason,
                $actor
            );
            $status === 'CANCELLED' ? $cancelled++ : $reviewRequired++;
        }

        return ['created' => 0, 'cancelled' => $cancelled, 'review_required' => $reviewRequired];
    }

    private function attribution(array $lineRefs, array $voucherFallback): array
    {
        $line = $this->extractRefs($lineRefs);
        return [
            'client_id' => $line['client_id'] ?: $voucherFallback['client_id'],
            'project_id' => $line['project_id'] ?: $voucherFallback['project_id'],
            'assignee_id' => $line['assignee_id'] ?: $voucherFallback['assignee_id'],
        ];
    }

    private function unambiguousVoucherRefs(array $refsByLine): array
    {
        $values = ['client_id' => [], 'project_id' => [], 'assignee_id' => []];
        foreach ($refsByLine as $refs) {
            foreach ($this->extractRefs($refs) as $key => $value) {
                if ($value !== '') {
                    $values[$key][$value] = true;
                }
            }
        }
        foreach ($values as $key => $ids) {
            $values[$key] = count($ids) === 1 ? (string) array_key_first($ids) : '';
        }
        return $values;
    }

    private function extractRefs(array $refs): array
    {
        $result = ['client_id' => '', 'project_id' => '', 'assignee_id' => ''];
        foreach ($refs as $ref) {
            $target = strtoupper(trim((string) ($ref['ref_target'] ?? '')));
            $id = trim((string) ($ref['ref_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            if (in_array($target, ['CLIENT', 'CUSTOMER', 'VENDOR', 'COUNTERPARTY'], true)) {
                $result['client_id'] = $id;
            } elseif ($target === 'PROJECT') {
                $result['project_id'] = $id;
            } elseif (in_array($target, ['EMPLOYEE', 'USER'], true)) {
                $result['assignee_id'] = $id;
            }
        }
        return $result;
    }
}
