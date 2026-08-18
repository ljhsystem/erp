<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use PDO;

class EvidenceTrashService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;

    public function __construct(private PDO $pdo, private $placeholderBuilder, private $dataTypeNormalizer,
        private $queryDataTypes, private $hasActiveOutput, private $softDeleteProcessing,
        private $softDeleteLinks, private $softDeleteBody, private $syncBankSoftDelete,
        private $restoreProcessing, private $restoreBody, private $syncBankRestore, private $purgeRows)
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
    }

    public function trashQueryParams(array $query): array { $query['status'] = 'DELETED'; return $query; }

    public function delete(array $ids, string $actor, ?string $importType = null): array
    {
        return $this->changeDeletedState($ids, $actor, (string) $importType, true);
    }

    public function restore(array $ids, string $actor, ?string $importType = null): array
    {
        return $this->changeDeletedState($ids, $actor, (string) $importType, false);
    }

    public function restoreAll(string $importType, string $actor): array
    {
        return $this->changeDeletedState(array_column($this->bodyModel->identities($importType, true), 'id'), $actor, $importType, false);
    }

    public function purge(array $ids, ?string $importType = null): array
    {
        $rows = $this->bodyModel->identitiesByIds($ids, strtoupper(trim((string) $importType)), true);
        [$allowed, $blocked] = $this->filterMutable($rows);
        if ($allowed === []) return $this->result('영구삭제할 수 있는 증빙이 없습니다.', 0, count($blocked));
        $allowedIds = array_column($allowed, 'id');
        try {
            $this->pdo->beginTransaction();
            $count = ($this->purgeRows)($allowedIds, $importType);
            $this->pdo->commit();
            return $this->result('증빙이 영구삭제되었습니다.', $count, count($blocked));
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function purgeAll(string $importType): array
    {
        return $this->purge(array_column($this->bodyModel->identities($importType, true), 'id'), $importType);
    }

    private function changeDeletedState(array $ids, string $actor, string $importType, bool $deleted): array
    {
        try {
            $this->pdo->beginTransaction();
            $rows = $this->bodyModel->identitiesByIds($ids, strtoupper(trim($importType)), !$deleted);
            [$allowed, $blocked] = $deleted ? $this->filterMutable($rows) : [$rows, []];
            $grouped = [];
            foreach ($allowed as $row) {
                $grouped[(string) $row['canonical_import_type']][] = (string) $row['id'];
            }
            $count = 0;
            foreach ($grouped as $type => $groupIds) $count += $this->bodyModel->updateDeletedState($type, $groupIds, $deleted, $actor);
            $this->pdo->commit();
            return $this->result($deleted ? '증빙이 삭제되었습니다.' : '증빙이 복구되었습니다.', $count, count($blocked));
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function filterMutable(array $rows): array
    {
        $allowed = []; $blocked = [];
        foreach ($rows as $row) {
            $type = (string) $row['canonical_import_type']; $id = (string) $row['id'];
            if ($this->linkModel->hasActiveLink($type, $id)) $blocked[] = $row;
            else $allowed[] = $row;
        }
        return [$allowed, $blocked];
    }

    private function result(string $message, int $count, int $skipped): array
    {
        return ['success' => true, 'message' => $message, 'data' => ['deleted_count' => $count, 'restored_count' => $count, 'purged_count' => $count, 'skipped_count' => $skipped]];
    }
}
