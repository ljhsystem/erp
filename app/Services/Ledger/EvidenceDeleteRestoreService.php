<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use PDO;

class EvidenceDeleteRestoreService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
    }

    public function softDeleteEvidenceBodyByEvidenceIds(array $ids, string $actor, ?string $importType = null): void
    {
        $this->updateDeletedState($ids, $actor, true, $importType);
    }

    public function restoreEvidenceBodyByEvidenceIds(array $ids, string $actor, ?string $importType = null): void
    {
        $this->updateDeletedState($ids, $actor, false, $importType);
    }

    public function updateEvidenceBodyDeletedAtByEvidenceIds(array $ids, string $actor, bool $deleted, ?string $importType = null): void
    {
        $this->updateDeletedState($ids, $actor, $deleted, $importType);
    }

    public function bodyTablesForEvidenceType(?string $importType): array
    {
        $table = $this->bodyModel->tableForImportType((string) $importType);
        return $table === null ? [] : [$table];
    }

    public function evidenceBodyTables(): array
    {
        $tables = [];
        foreach ($this->bodyModel->importTypes() as $type) {
            $table = $this->bodyModel->tableForImportType($type);
            if ($table !== null) $tables[$table] = $table;
        }
        return array_values($tables);
    }

    private function updateDeletedState(array $ids, string $actor, bool $deleted, ?string $importType): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) $this->pdo->beginTransaction();
            $rows = $this->bodyModel->identitiesByIds($ids, strtoupper(trim((string) $importType)), !$deleted);
            $grouped = [];
            foreach ($rows as $row) {
                $type = (string) $row['canonical_import_type'];
                $id = (string) $row['id'];
                if ($deleted && $this->linkModel->hasActiveLink($type, $id)) continue;
                $grouped[$type][] = $id;
            }
            foreach ($grouped as $type => $groupIds) {
                $this->bodyModel->updateDeletedState($type, $groupIds, $deleted, $actor);
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
