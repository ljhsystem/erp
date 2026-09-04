<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class EvidenceLifecycleService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;
    private LoggerInterface $logger;

    public function __construct(private PDO $pdo, private array $callbacks = [])
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger-evidence-lifecycle');
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
        $this->logger->warning('연결되지 않은 증빙원본이 영구삭제되었습니다.', [
            'event_code' => 'EVIDENCE_ROWS_PURGED', 'result' => 'SUCCESS',
            'service' => self::class, 'action' => 'purge',
            'requested_count' => count($ids), 'deleted_count' => $count,
            'import_type' => $importType,
        ]);
        return $count;
    }
}
