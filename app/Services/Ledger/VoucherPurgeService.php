<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherLineRefModel;
use App\Models\Ledger\VoucherModel;
use PDO;

class VoucherPurgeService
{
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefModel $voucherLineRefModel;
    private EvidenceLinkModel $evidenceLinkModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherLineRefModel = new VoucherLineRefModel($pdo);
        $this->evidenceLinkModel = new EvidenceLinkModel($pdo);
    }

    public function purge(array $voucherIds): void
    {
        $voucherIds = array_values(array_unique(array_filter(array_map(
            static fn(mixed $id): string => trim((string) $id),
            $voucherIds
        ))));
        if ($voucherIds === []) {
            return;
        }

        try {
            $this->pdo->beginTransaction();
            foreach ($voucherIds as $voucherId) {
                $voucher = $this->voucherModel->getById($voucherId);
                if (!$voucher || empty($voucher['deleted_at'])) {
                    throw new \RuntimeException('삭제된 전표만 영구삭제할 수 있습니다.');
                }

                $this->voucherLineRefModel->purgeByVoucherId($voucherId);
                $this->voucherLineModel->purgeByVoucherId($voucherId);
                $this->evidenceLinkModel->purgeByVoucherId($voucherId);
                if (!$this->voucherModel->hardDelete($voucherId)) {
                    throw new \RuntimeException('전표를 영구삭제하지 못했습니다.');
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
