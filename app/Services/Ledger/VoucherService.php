<?php

namespace App\Services\Ledger;

use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherPaymentModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class VoucherService
{
    private const STATUS_VALUES = ['draft', 'posted', 'locked', 'deleted'];
    private const REF_TYPES = ['CLIENT', 'PROJECT', 'ACCOUNT', 'CARD', 'EMPLOYEE', 'ORDER'];

    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherPaymentModel $voucherPaymentModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherPaymentModel = new VoucherPaymentModel($pdo);
    }

    public function save(array $data): array
    {
        $actor = ActorHelper::user();
        $voucherId = trim((string) ($data['id'] ?? ''));
        $voucherDate = trim((string) ($data['voucher_date'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'draft'));
        $refType = strtoupper(trim((string) ($data['ref_type'] ?? '')));
        $refId = trim((string) ($data['ref_id'] ?? ''));
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        $payments = is_array($data['payments'] ?? null) ? $data['payments'] : [];

        if ($voucherDate === '') {
            throw new \RuntimeException('전표일자를 입력해주세요.');
        }

        if ($status === '' || !in_array($status, self::STATUS_VALUES, true)) {
            throw new \RuntimeException('유효하지 않은 전표 상태입니다.');
        }

        if ($refType !== '' && !in_array($refType, self::REF_TYPES, true)) {
            throw new \RuntimeException('유효하지 않은 참조유형입니다.');
        }

        $normalizedLines = $this->normalizeLines($lines);
        $this->validateBalance($normalizedLines);

        $normalizedPayments = $this->normalizePayments($payments);

        try {
            $this->pdo->beginTransaction();

            if ($voucherId === '') {
                $voucherId = UuidHelper::generate();

                $saved = $this->voucherModel->insert([
                    'id' => $voucherId,
                    'voucher_date' => $voucherDate,
                    'ref_type' => $refType !== '' ? $refType : null,
                    'ref_id' => $refId !== '' ? $refId : null,
                    'status' => $status,
                    'summary_text' => $this->nullableString($data['summary_text'] ?? null),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'memo' => $this->nullableString($data['memo'] ?? null),
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $actor,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actor,
                ]);
            } else {
                $existing = $this->voucherModel->getById($voucherId);
                if (!$existing) {
                    throw new \RuntimeException('전표를 찾을 수 없습니다.');
                }

                $saved = $this->voucherModel->update($voucherId, [
                    'voucher_date' => $voucherDate,
                    'ref_type' => $refType !== '' ? $refType : null,
                    'ref_id' => $refId !== '' ? $refId : null,
                    'status' => $status,
                    'summary_text' => $this->nullableString($data['summary_text'] ?? null),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'memo' => $this->nullableString($data['memo'] ?? null),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actor,
                ]);
            }

            if (!$saved) {
                throw new \RuntimeException('전표 저장에 실패했습니다.');
            }

            $this->hardDeleteLinesByVoucherId($voucherId);
            foreach ($normalizedLines as $line) {
                $ok = $this->voucherLineModel->insert([
                    'id' => UuidHelper::generate(),
                    'voucher_id' => $voucherId,
                    'line_no' => $line['line_no'],
                    'account_code' => $line['account_code'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_summary' => $line['line_summary'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $actor,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $actor,
                ]);

                if (!$ok) {
                    throw new \RuntimeException('전표 라인 저장에 실패했습니다.');
                }
            }

            $this->hardDeletePaymentsByVoucherId($voucherId);
            foreach ($normalizedPayments as $payment) {
                $ok = $this->voucherPaymentModel->insert([
                    'id' => UuidHelper::generate(),
                    'voucher_id' => $voucherId,
                    'payment_type' => $payment['payment_type'],
                    'payment_id' => $payment['payment_id'],
                    'amount' => $payment['amount'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $actor,
                ]);

                if (!$ok) {
                    throw new \RuntimeException('전표 결제수단 저장에 실패했습니다.');
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $voucherId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        $lineNo = 1;

        foreach ($lines as $line) {
            $accountCode = trim((string) ($line['account_code'] ?? ''));
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            $lineSummary = $this->nullableString($line['line_summary'] ?? null);

            if ($accountCode === '' && $debit === 0.0 && $credit === 0.0) {
                continue;
            }

            if ($accountCode === '') {
                throw new \RuntimeException('계정코드가 없는 전표 라인이 있습니다.');
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \RuntimeException('금액이 0인 전표 라인이 있습니다.');
            }

            $normalized[] = [
                'line_no' => $lineNo++,
                'account_code' => $accountCode,
                'debit' => number_format(max($debit, 0), 2, '.', ''),
                'credit' => number_format(max($credit, 0), 2, '.', ''),
                'line_summary' => $lineSummary,
            ];
        }

        if ($normalized === []) {
            throw new \RuntimeException('전표 라인을 1건 이상 입력해주세요.');
        }

        return $normalized;
    }

    private function normalizePayments(array $payments): array
    {
        $normalized = [];

        foreach ($payments as $payment) {
            $paymentType = strtoupper(trim((string) ($payment['payment_type'] ?? '')));
            $paymentId = trim((string) ($payment['payment_id'] ?? ''));
            $amount = round((float) ($payment['amount'] ?? 0), 2);

            if ($paymentType === '' && $paymentId === '' && $amount === 0.0) {
                continue;
            }

            if ($paymentType === '' || $paymentId === '' || $amount <= 0) {
                throw new \RuntimeException('결제수단 정보가 올바르지 않습니다.');
            }

            $normalized[] = [
                'payment_type' => $paymentType,
                'payment_id' => $paymentId,
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $normalized;
    }

    private function validateBalance(array $lines): void
    {
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $line) {
            $debit = (float) $line['debit'];
            $credit = (float) $line['credit'];

            if ($debit > 0 && $credit > 0) {
                throw new \RuntimeException('같은 라인에는 차변 또는 대변 중 하나만 입력할 수 있습니다.');
            }

            $debitSum += $debit;
            $creditSum += $credit;
        }

        if (round($debitSum, 2) !== round($creditSum, 2)) {
            throw new \RuntimeException('차변 합계와 대변 합계가 일치해야 합니다.');
        }
    }

    private function hardDeletePaymentsByVoucherId(string $voucherId): void
    {
        $payments = $this->voucherPaymentModel->getByVoucherId($voucherId);

        foreach ($payments as $payment) {
            $paymentId = trim((string) ($payment['id'] ?? ''));
            if ($paymentId === '') {
                continue;
            }

            $this->voucherPaymentModel->hardDelete($paymentId);
        }
    }

    private function hardDeleteLinesByVoucherId(string $voucherId): void
    {
        $lines = $this->voucherLineModel->getByVoucherId($voucherId);

        foreach ($lines as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId === '') {
                continue;
            }

            $this->voucherLineModel->hardDelete($lineId);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));
        return $string === '' ? null : $string;
    }
}
