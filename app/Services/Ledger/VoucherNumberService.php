<?php

namespace App\Services\Ledger;

use App\Models\Ledger\VoucherModel;
use PDO;

class VoucherNumberService
{
    private VoucherNumberPolicy $policy;
    private VoucherModel $voucherModel;

    public function __construct(private readonly PDO $pdo, ?VoucherNumberPolicy $policy = null)
    {
        $this->policy = $policy ?? new VoucherNumberPolicy();
        $this->voucherModel = new VoucherModel($pdo);
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

        $this->pdo->beginTransaction();
        try {
            $this->voucherModel->updateVoucherNo($voucherId, $nextNo, $actor);

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
        $voucher = $this->voucherModel->findActiveNumberState($voucherId);

        if (!$voucher) {
            throw new \RuntimeException('전표를 찾을 수 없습니다.');
        }

        return $voucher;
    }

    private function assertUnique(string $voucherId, string $voucherNo): void
    {
        if ($this->voucherModel->activeVoucherNoExists($voucherNo, $voucherId)) {
            throw new \RuntimeException('이미 사용 중인 전표번호입니다.');
        }
    }
}
