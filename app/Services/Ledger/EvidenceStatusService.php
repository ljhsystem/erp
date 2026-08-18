<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use Core\Helpers\ActorHelper;
use PDO;

class EvidenceStatusService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;

    public function __construct(private PDO $pdo, private $placeholderBuilder = null, private $jsonEncoder = null,
        private $dataTypeNormalizer = null, private $queryDataTypes = null, private $tableExists = null,
        private $tableColumnExists = null, private $payloadSortNo = null, private $evidenceMetadataResolver = null)
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
    }

    public function updateStatus(array $ids, string $status): array
    {
        $status = strtoupper(trim($status));
        if ($ids === [] || !in_array($status, ['COMPLETED', 'CORRECTION_REQUIRED'], true)) {
            return ['success' => false, 'message' => '변경할 증빙 상태를 확인해 주세요.', 'status' => 400];
        }
        $rows = $this->bodyModel->identitiesByIds($ids, '', false);
        $grouped = [];
        foreach ($rows as $row) {
            $type = (string) $row['canonical_import_type'];
            $id = (string) $row['id'];
            if ($this->linkModel->hasActiveLink($type, $id)) continue;
            $grouped[$type][] = $id;
        }
        $count = 0;
        foreach ($grouped as $type => $groupIds) $count += $this->bodyModel->updateEvidenceStatus($type, $groupIds, $status, ActorHelper::user());
        return ['success' => true, 'message' => '증빙 상태가 변경되었습니다.', 'data' => ['updated_count' => $count, 'skipped_count' => count($ids) - $count]];
    }

    public function reorder(array $payload, string $actor): array
    {
        $changes = $payload['changes'] ?? [];
        if (is_string($changes)) $changes = json_decode($changes, true) ?: [];
        $firstChange = is_array($changes) && isset($changes[0]) && is_array($changes[0])
            ? $changes[0]
            : [];
        $type = strtoupper(trim((string) (
            $payload['import_type']
            ?? $payload['data_type']
            ?? $firstChange['import_type']
            ?? $firstChange['data_type']
            ?? ''
        )));
        if (!is_array($changes) || $changes === [] || $type === '') {
            return ['success' => false, 'message' => '정렬 변경 대상을 확인해 주세요.', 'status' => 400];
        }
        $rows = [];
        foreach ($changes as $change) {
            if (!is_array($change)) continue;
            $rows[] = ['id' => $change['id'] ?? '', 'sort_no' => $change['newSortNo'] ?? $change['sort_no'] ?? 0];
        }
        $count = $this->bodyModel->reorder($type, $rows, $actor, 'sort_no');
        return ['success' => true, 'message' => '증빙원본 순서가 변경되었습니다.', 'data' => ['updated_count' => $count]];
    }
}
