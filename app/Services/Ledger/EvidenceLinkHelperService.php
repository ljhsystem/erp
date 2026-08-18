<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceLinkModel;
use PDO;

class EvidenceLinkHelperService
{
    private EvidenceLinkModel $linkModel;

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
        $this->linkModel = new EvidenceLinkModel($pdo);
    }

    public function hasActiveLink(string $importType, string $evidenceId): bool
    {
        return $this->linkModel->hasActiveLink($importType, $evidenceId);
    }

    public function deleteEvidencePurgeDependencies(array $identities): void
    {
        foreach ($identities as $identity) {
            if (!is_array($identity)) continue;
            $this->linkModel->deleteByEvidenceIdentity(
                (string) ($identity['import_type'] ?? $identity['canonical_import_type'] ?? ''),
                (string) ($identity['evidence_id'] ?? $identity['id'] ?? '')
            );
        }
    }
}
