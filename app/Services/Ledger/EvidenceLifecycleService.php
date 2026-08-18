<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use PDO;

class EvidenceLifecycleService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
    }

    public function purgeSeedRowsByIds(array $ids, ?string $importType = null): int
    {
        $rows = $this->bodyModel->identitiesByIds($ids, strtoupper(trim((string) $importType)), true);
        $grouped = [];
        foreach ($rows as $row) {
            $type = (string) $row['canonical_import_type'];
            $id = (string) $row['id'];
            if ($this->linkModel->hasActiveLink($type, $id)) continue;
            $this->linkModel->deleteByEvidenceIdentity($type, $id);
            $grouped[$type][] = $id;
        }
        $count = 0;
        foreach ($grouped as $type => $groupIds) $count += $this->bodyModel->purge($type, $groupIds);
        return $count;
    }
}
