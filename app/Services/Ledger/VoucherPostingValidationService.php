<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceLinkModel;
use App\Models\Ledger\VoucherPostingValidationModel;
use App\Repositories\Ledger\EvidenceSourceRepository;
use PDO;

final class VoucherPostingValidationService
{
    private EvidenceLinkModel $linkModel;
    private EvidenceSourceRepository $evidenceRepository;
    private TransactionReferenceValidatorService $referenceValidator;
    private VoucherPostingValidationModel $validationModel;

    public function __construct(private readonly PDO $pdo)
    {
        $this->linkModel = new EvidenceLinkModel($pdo);
        $this->evidenceRepository = new EvidenceSourceRepository($pdo);
        $this->referenceValidator = new TransactionReferenceValidatorService($pdo);
        $this->validationModel = new VoucherPostingValidationModel($pdo);
    }

    public function validate(string $voucherId, array $lines): void
    {
        $accountIds = array_values(array_unique(array_filter(array_map(
            static fn(array $line): string => trim((string) ($line['account_id'] ?? '')),
            $lines
        ))));
        if ($accountIds === [] || count($accountIds) !== count(array_unique(array_map(
            static fn(array $line): string => trim((string) ($line['account_id'] ?? '')),
            $lines
        )))) {
            throw new VoucherValidationException('계정과목이 지정되지 않은 분개가 있습니다.', 'posting_account');
        }
        if (count($this->validationModel->activePostingAccountIds($accountIds)) !== count($accountIds)) {
            throw new VoucherValidationException('현재 전기할 수 없는 계정과목이 포함되어 있습니다.', 'posting_account');
        }

        $idsByTarget = [];
        foreach ($lines as $line) {
            foreach ((array) ($line['refs'] ?? []) as $ref) {
                $target = strtoupper(trim((string) ($ref['ref_target'] ?? '')));
                $id = trim((string) ($ref['ref_id'] ?? ''));
                if ($target === '' || $id === '') {
                    throw new VoucherValidationException('유효하지 않은 보조계정 참조가 포함되어 있습니다.', 'posting_reference');
                }
                $idsByTarget[$target][$id] = $id;
            }
        }
        try {
            $this->referenceValidator->validateGroupedIds($idsByTarget);
        } catch (\InvalidArgumentException $e) {
            throw new VoucherValidationException($e->getMessage(), 'posting_reference');
        }

        $links = $this->linkModel->getVoucherEvidences($voucherId);
        if ($links !== [] && count($this->evidenceRepository->findMany($links)) !== count($links)) {
            throw new VoucherValidationException('삭제되었거나 유효하지 않은 증빙이 연결되어 있습니다.', 'posting_evidence');
        }
    }
}
