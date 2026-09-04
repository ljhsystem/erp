<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\VoucherLineModel;
use App\Models\Ledger\VoucherModel;
use App\Models\Ledger\VoucherReviewQueryModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use Closure;
use PDO;

class VoucherQueryService
{
    private VoucherModel $voucherModel;
    private VoucherReviewQueryModel $voucherReviewQueryModel;
    private VoucherLineModel $voucherLineModel;
    private VoucherLineRefService $voucherLineRefService;
    private VoucherLineSourceRefService $voucherLineSourceRefService;
    private EvidenceLinkModel $evidenceLinkModel;
    private EvidenceSourceRepository $evidenceSourceRepository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->voucherModel = new VoucherModel($pdo);
        $this->voucherReviewQueryModel = new VoucherReviewQueryModel($pdo);
        $this->voucherLineModel = new VoucherLineModel($pdo);
        $this->voucherLineRefService = new VoucherLineRefService($pdo);
        $this->voucherLineSourceRefService = new VoucherLineSourceRefService($pdo);
        $this->evidenceLinkModel = new EvidenceLinkModel($pdo);
        $this->evidenceSourceRepository = new EvidenceSourceRepository($pdo);
    }

    public function getList(array $filters): array
    {
        return $this->voucherModel->getList($filters);
    }

    public function getReviewPage(array $request, array $filters): array
    {
        return $this->voucherReviewQueryModel->getPage($request, $filters);
    }

    public function getDetail(string $id, Closure $normalizeEvidence): ?array
    {
        $voucher = $this->voucherModel->getById($id);
        if (!$voucher) {
            return null;
        }

        $voucher['lines'] = $this->voucherLineRefService->hydrateVoucherLines(
            $this->voucherLineModel->getByVoucherId($id)
        );
        $voucher['lines'] = $this->voucherLineSourceRefService->hydrateVoucherLines(
            $this->companyId(),
            $id,
            $voucher['lines']
        );
        $voucher['reversal_voucher'] = $this->voucherModel->findActiveReversalOf($id);
        $voucher['original_voucher'] = !empty($voucher['reversal_of'])
            ? $this->voucherModel->getById((string) $voucher['reversal_of'])
            : null;
        $voucher['linked_evidences'] = $this->normalizedEvidences($id, $normalizeEvidence);
        $voucher['original_linked_evidences'] = !empty($voucher['reversal_of'])
            ? $this->normalizedEvidences((string) $voucher['reversal_of'], $normalizeEvidence)
            : [];

        return $voucher;
    }

    private function normalizedEvidences(string $voucherId, Closure $normalizeEvidence): array
    {
        $evidenceLinks = $this->evidenceLinkModel->getVoucherEvidences($voucherId);
        $evidenceRows = $this->evidenceSourceRepository->findMany($evidenceLinks);

        return array_values(array_filter(array_map(
            static function (array $link) use ($normalizeEvidence, $evidenceRows): ?array {
                $key = strtoupper(trim((string) ($link['import_type'] ?? '')))
                    . "\0"
                    . trim((string) ($link['evidence_id'] ?? ''));
                $source = $evidenceRows[$key] ?? null;
                return is_array($source) ? $normalizeEvidence($source) : null;
            },
            $evidenceLinks
        )));
    }

    public function getById(string $id): ?array
    {
        return $this->voucherModel->getById($id);
    }

    public function searchForPicker(array $filters): array
    {
        return $this->voucherModel->searchForPicker($filters);
    }

    public function getTrashList(): array
    {
        return $this->voucherModel->getTrashList();
    }

    public function getDeletedIds(): array
    {
        return $this->voucherModel->getDeletedIds();
    }

    public function getVoucherEvidences(string $voucherId): array
    {
        return $this->evidenceLinkModel->getVoucherEvidences($voucherId);
    }

    public function activeEvidenceMetadataOptions(): array
    {
        return $this->evidenceSourceRepository->activeMetadataOptions();
    }

    public function pagedEvidenceProjections(array $filters): array
    {
        return $this->evidenceSourceRepository->pagedProjections($filters);
    }

    public function evidenceSemanticValues(string $importType, array $row): array
    {
        return $this->evidenceSourceRepository->semanticValues($importType, $row);
    }

    private function companyId(): string
    {
        $ids = $this->pdo->query('SELECT id FROM system_company ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (count($ids) !== 1) {
            throw new \RuntimeException('전표 Source Ref의 회사 범위를 확정할 수 없습니다.');
        }
        return (string) $ids[0];
    }
}
