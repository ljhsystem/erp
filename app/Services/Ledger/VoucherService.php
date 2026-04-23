<?php

namespace App\Services\Ledger;

use App\Models\Ledger\TransactionLinkModel;
use App\Models\Ledger\TransactionModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

class VoucherService
{
    private const STATUS_VALUES = ['draft', 'posted', 'locked', 'deleted'];
    private const TYPE_VALUES = ['MANUAL', 'AUTO', 'ADJUST', 'CLOSING'];

    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private TransactionLinkModel $transactionLinkModel;
    private TransactionModel $transactionModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->transactionLinkModel = new TransactionLinkModel($pdo);
        $this->transactionModel = new TransactionModel($pdo);
    }

    public function save(array $data): array
    {
        $actor = ActorHelper::user();
        $voucherId = trim((string) ($data['id'] ?? ''));
        $voucherDate = trim((string) ($data['voucher_date'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'draft'));
        $type = strtoupper(trim((string) ($data['ref_type'] ?? $data['type'] ?? 'MANUAL'))) ?: 'MANUAL';
        $linkedTransactionId = trim((string) ($data['linked_transaction_id'] ?? ''));
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];

        if ($voucherDate === '') {
            throw new \RuntimeException('전표일자를 입력해 주세요.');
        }

        if (!in_array($status, self::STATUS_VALUES, true)) {
            throw new \RuntimeException('올바른 전표 상태를 선택해 주세요.');
        }

        if (!in_array($type, self::TYPE_VALUES, true)) {
            throw new \RuntimeException('올바른 전표 타입을 선택해 주세요.');
        }

        if ($linkedTransactionId !== '' && !$this->transactionModel->getById($linkedTransactionId)) {
            throw new \RuntimeException('선택한 거래를 찾을 수 없습니다.');
        }

        $normalizedLines = $this->normalizeLines($lines);
        $this->validateBalance($normalizedLines);
        $timestamp = date('Y-m-d H:i:s');

        try {
            $this->pdo->beginTransaction();

            if ($voucherId === '') {
                $voucherId = UuidHelper::generate();
                $voucherNo = $this->resolveVoucherNo($data, $voucherDate);

                $saved = $this->voucherModel->insert([
                    'id' => $voucherId,
                    'sort_no' => null,
                    'voucher_no' => $voucherNo,
                    'voucher_date' => $voucherDate,
                    'ref_type' => $type,
                    'ref_id' => null,
                    'status' => $status,
                    'summary_text' => $this->nullableString($data['summary_text'] ?? null),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'memo' => $this->nullableString($data['memo'] ?? null),
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ]);
            } else {
                $existing = $this->voucherModel->getById($voucherId);
                if (!$existing) {
                    throw new \RuntimeException('전표를 찾을 수 없습니다.');
                }

                $payload = [
                    'voucher_date' => $voucherDate,
                    'ref_type' => $type,
                    'ref_id' => null,
                    'status' => $status,
                    'summary_text' => $this->nullableString($data['summary_text'] ?? null),
                    'note' => $this->nullableString($data['note'] ?? null),
                    'memo' => $this->nullableString($data['memo'] ?? null),
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ];

                if (trim((string) ($existing['voucher_no'] ?? '')) === '') {
                    $payload['voucher_no'] = $this->resolveVoucherNo($data, $voucherDate);
                }

                $saved = $this->voucherModel->update($voucherId, $payload);
            }

            if (!$saved) {
                throw new \RuntimeException('전표 저장에 실패했습니다.');
            }

            $this->hardDeleteLinesByVoucherId($voucherId);
            foreach ($normalizedLines as $line) {
                $ok = $this->voucherLineModel->insert([
                    'id' => UuidHelper::generate(),
                    'sort_no' => null,
                    'voucher_id' => $voucherId,
                    'line_no' => $line['line_no'],
                    'account_code' => $line['account_code'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'line_summary' => $line['line_summary'],
                    'created_at' => $timestamp,
                    'created_by' => $actor,
                    'updated_at' => $timestamp,
                    'updated_by' => $actor,
                ]);

                if (!$ok) {
                    throw new \RuntimeException('분개라인 저장에 실패했습니다.');
                }
            }

            $this->replaceManualTransactionLink($voucherId, $linkedTransactionId, $actor, $timestamp);

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

    public function reorder(array $changes): bool
    {
        if ($changes === []) {
            return true;
        }

        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
            }

            foreach ($changes as $row) {
                if (empty($row['id']) || !isset($row['newSortNo'])) {
                    throw new \RuntimeException('정렬 데이터가 올바르지 않습니다.');
                }
            }

            foreach ($changes as $row) {
                $this->voucherModel->updateSortNo((string) $row['id'], (int) $row['newSortNo'] + 1000000);
            }

            foreach ($changes as $row) {
                $this->voucherModel->updateSortNo((string) $row['id'], (int) $row['newSortNo']);
            }

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return true;
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
            $debit = round($this->parseAmount($line['debit'] ?? 0), 2);
            $credit = round($this->parseAmount($line['credit'] ?? 0), 2);
            $lineSummary = $this->nullableString($line['line_summary'] ?? null);

            if ($accountCode === '' && $debit === 0.0 && $credit === 0.0 && $lineSummary === null) {
                continue;
            }

            if ($accountCode === '') {
                throw new \RuntimeException('분개라인의 계정과목을 선택해 주세요.');
            }

            if ($debit <= 0 && $credit <= 0) {
                throw new \RuntimeException('분개라인에는 차변 또는 대변 금액이 필요합니다.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new \RuntimeException('한 분개라인에는 차변 또는 대변 중 하나만 입력할 수 있습니다.');
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
            throw new \RuntimeException('분개라인을 1건 이상 입력해 주세요.');
        }

        return $normalized;
    }

    private function validateBalance(array $lines): void
    {
        $debitSum = 0.0;
        $creditSum = 0.0;

        foreach ($lines as $line) {
            $debitSum += (float) $line['debit'];
            $creditSum += (float) $line['credit'];
        }

        if (round($debitSum, 2) !== round($creditSum, 2)) {
            throw new \RuntimeException('차변 합계와 대변 합계가 일치해야 합니다.');
        }
    }

    private function resolveVoucherNo(array $data, string $voucherDate): string
    {
        $voucherNo = trim((string) ($data['voucher_no'] ?? ''));
        if ($voucherNo !== '') {
            return $voucherNo;
        }

        return $this->nextVoucherNo($voucherDate);
    }

    private function nextVoucherNo(string $voucherDate): string
    {
        $dateKey = preg_replace('/[^0-9]/', '', $voucherDate) ?: date('Ymd');
        $prefix = substr($dateKey, 0, 8);

        $stmt = $this->pdo->prepare("
            SELECT voucher_no
            FROM ledger_vouchers
            WHERE voucher_no LIKE :prefix
            ORDER BY voucher_no DESC
            LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix . '-%']);

        $latest = (string) ($stmt->fetchColumn() ?: '');
        $next = 1;
        if (preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function hardDeleteLinesByVoucherId(string $voucherId): void
    {
        $lines = $this->voucherLineModel->getByVoucherId($voucherId);

        foreach ($lines as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId !== '') {
                $this->voucherLineModel->hardDelete($lineId);
            }
        }
    }

    private function replaceManualTransactionLink(
        string $voucherId,
        string $transactionId,
        string $actor,
        string $timestamp
    ): void {
        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_transaction_links
            WHERE voucher_id = :voucher_id
              AND link_type = 'MANUAL'
        ");
        $stmt->execute([':voucher_id' => $voucherId]);

        if ($transactionId === '') {
            return;
        }

        $stmt = $this->pdo->prepare("
            DELETE FROM ledger_transaction_links
            WHERE voucher_id = :voucher_id
              AND transaction_id = :transaction_id
        ");
        $stmt->execute([
            ':voucher_id' => $voucherId,
            ':transaction_id' => $transactionId,
        ]);

        $ok = $this->transactionLinkModel->insert([
            'id' => UuidHelper::generate(),
            'sort_no' => null,
            'transaction_id' => $transactionId,
            'voucher_id' => $voucherId,
            'link_type' => 'MANUAL',
            'is_active' => 1,
            'note' => null,
            'memo' => null,
            'created_at' => $timestamp,
            'created_by' => $actor,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
        ]);

        if (!$ok) {
            throw new \RuntimeException('거래 연결 저장에 실패했습니다.');
        }
    }

    private function parseAmount(mixed $value): float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) ($value ?? '')));

        if ($cleaned === '' || $cleaned === '-' || $cleaned === '.' || $cleaned === '-.') {
            return 0.0;
        }

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string === '' ? null : $string;
    }
}
