<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceImportModel;

class EvidenceImportBatchService
{
    public function __construct(private readonly EvidenceImportModel $model = new EvidenceImportModel()) {}

    public function deletableRowIdsByImportDate(string $batchId): array
    {
        $batchId = trim($batchId);
        return $batchId === '' ? [] : $this->model->findDeletableIdsByImportDate($batchId);
    }
}
