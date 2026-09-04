<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use Core\Helpers\ActorHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class EvidenceStatusService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;
    private LoggerInterface $logger;

    public function __construct(private PDO $pdo, private $placeholderBuilder = null, private $jsonEncoder = null,
        private $dataTypeNormalizer = null, private $queryDataTypes = null, private $tableExists = null,
        private $tableColumnExists = null, private $payloadSortNo = null, private $evidenceMetadataResolver = null)
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger-evidence-status');
    }

    public function updateStatus(array $ids, string $status): array
    {
        $status = strtoupper(trim($status));
        if ($ids === [] || !in_array($status, [EvidenceWorkflowPolicyService::COMPLETED, EvidenceWorkflowPolicyService::CORRECTION_REQUIRED], true)) {
            return ['success' => false, 'message' => '변경할 증빙 상태를 확인해 주세요.', 'status' => 400];
        }
        $rows = $this->bodyModel->identitiesByIds($ids, '', false);
        $grouped = [];
        foreach ($rows as $row) {
            $type = (string) $row['canonical_import_type'];
            $id = (string) $row['id'];
            if ($this->linkModel->findLinkedVoucherInfo($type, $id) !== null) {
                return [
                    'success' => false,
                    'message' => '전표에 연결된 증빙은 일반 상태변경 대신 전표 취소·정정 절차를 사용해 주세요.',
                    'status' => 409,
                ];
            }
            $grouped[$type][] = $id;
        }
        $count = 0;
        foreach ($grouped as $type => $groupIds) $count += $this->bodyModel->updateEvidenceStatus($type, $groupIds, $status, ActorHelper::user());
        $this->logger->info('증빙원본 상태가 변경되었습니다.', [
            'event_code' => 'EVIDENCE_STATUS_UPDATED', 'result' => 'SUCCESS',
            'service' => self::class, 'action' => 'update-status', 'actor' => ActorHelper::user(),
            'target_status' => $status, 'requested_count' => count($ids), 'updated_count' => $count,
        ]);
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
        $this->logger->info('증빙원본 순서가 변경되었습니다.', [
            'event_code' => 'EVIDENCE_ORDER_UPDATED', 'result' => 'SUCCESS',
            'service' => self::class, 'action' => 'reorder', 'actor' => $actor,
            'import_type' => $type, 'requested_count' => count($rows), 'updated_count' => $count,
        ]);
        return ['success' => true, 'message' => '증빙원본 순서가 변경되었습니다.', 'data' => ['updated_count' => $count]];
    }
}
