<?php

namespace App\Services\Ledger;

use Core\Helpers\UuidHelper;
use PDO;

class VoucherNumberService
{
    private VoucherNumberPolicy $policy;

    public function __construct(private readonly PDO $pdo, ?VoucherNumberPolicy $policy = null)
    {
        $this->policy = $policy ?? new VoucherNumberPolicy();
    }

    public function change(string $voucherId, string $voucherNo, string $actor): array
    {
        $voucherId = trim($voucherId);
        $nextNo = $this->policy->normalize($voucherNo);
        if ($voucherId === '') {
            throw new \RuntimeException('전표 ID가 없습니다.');
        }

        $this->policy->assertValidFormat($nextNo);
        $voucher = $this->findVoucher($voucherId);
        $this->policy->assertEditable($voucher);

        $oldNo = trim((string) ($voucher['voucher_no'] ?? ''));
        if ($oldNo === $nextNo) {
            return [
                'id' => $voucherId,
                'voucher_no' => $nextNo,
                'changed' => false,
            ];
        }

        $this->assertUnique($voucherId, $nextNo);
        $this->ensureHistoryTable();

        $this->pdo->beginTransaction();
        try {
            $historyStmt = $this->pdo->prepare("
                INSERT INTO ledger_voucher_number_histories (
                    id, voucher_id, old_voucher_no, new_voucher_no, reason,
                    created_by, created_at
                ) VALUES (
                    :id, :voucher_id, :old_voucher_no, :new_voucher_no, :reason,
                    :created_by, NOW()
                )
            ");
            $historyStmt->execute([
                ':id' => UuidHelper::generate(),
                ':voucher_id' => $voucherId,
                ':old_voucher_no' => $oldNo,
                ':new_voucher_no' => $nextNo,
                ':reason' => 'manual_change',
                ':created_by' => $actor,
            ]);

            $updateStmt = $this->pdo->prepare("
                UPDATE ledger_vouchers
                SET voucher_no = :voucher_no,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id
                  AND deleted_at IS NULL
            ");
            $updateStmt->execute([
                ':voucher_no' => $nextNo,
                ':updated_by' => $actor,
                ':id' => $voucherId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $voucherId,
            'old_voucher_no' => $oldNo,
            'voucher_no' => $nextNo,
            'changed' => true,
        ];
    }

    private function findVoucher(string $voucherId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, voucher_no, status
            FROM ledger_vouchers
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $voucherId]);
        $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }

        return $voucher;
    }

    private function assertUnique(string $voucherId, string $voucherNo): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM ledger_vouchers
            WHERE voucher_no = :voucher_no
              AND id <> :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':voucher_no' => $voucherNo,
            ':id' => $voucherId,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('이미 사용 중인 전표번호입니다.');
        }
    }

    private function ensureHistoryTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ledger_voucher_number_histories (
                id CHAR(36) NOT NULL,
                voucher_id CHAR(36) NOT NULL,
                old_voucher_no VARCHAR(30) NULL DEFAULT NULL,
                new_voucher_no VARCHAR(30) NOT NULL,
                reason VARCHAR(100) NULL DEFAULT NULL,
                created_by VARCHAR(100) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_voucher_number_history_voucher (voucher_id),
                KEY idx_voucher_number_history_new_no (new_voucher_no),
                KEY idx_voucher_number_history_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
