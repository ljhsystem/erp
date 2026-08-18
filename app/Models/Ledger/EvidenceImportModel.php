<?php

namespace App\Models\Ledger;

use Core\Database;
use PDO;

class EvidenceImportModel
{
    private EvidenceBodyStorageModel $bodyStorageModel;

    public function __construct(?PDO $pdo = null)
    {
        $this->bodyStorageModel = new EvidenceBodyStorageModel($pdo ?? Database::getInstance()->getConnection());
    }

    public function findDeletableIdsByImportDate(string $importDate): array
    {
        return $this->bodyStorageModel->findDeletableIdsByImportDate($importDate);
    }

    public function resetBankTransactionClaim(string $evidenceId, string $actor): void
    {
        // 생성센터의 PROCESSING claim 저장은 폐기되었다.
    }

    public function findSummaryPayloadRows(string $keyword): array
    {
        return $this->bodyStorageModel->findSummaryPayloadRows($keyword);
    }

    public function findDownloadRows(string $sourceType, string $formatId = '', bool $withFormatId = false): array
    {
        return $this->bodyStorageModel->findDownloadRows($sourceType, $formatId, $withFormatId);
    }

    public function createdTransactionCondition(string $alias = ''): string
    {
        if ($alias !== '' && preg_match('/^[A-Za-z0-9_]+$/', $alias) !== 1) throw new \InvalidArgumentException('Invalid evidence alias');
        $prefix = $alias !== '' ? $alias . '.' : '';
        return "({$prefix}transaction_status IN ('CREATED', 'PROCESSED', 'DONE', 'COMPLETED', 'POSTED'))";
    }

}
