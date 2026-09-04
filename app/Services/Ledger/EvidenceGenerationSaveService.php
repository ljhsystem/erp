<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * 기존 API 이름을 유지하는 Body 저장 호환 진입점이다. 생성센터 상태는 저장하지 않는다.
 */
class EvidenceGenerationSaveService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;
    private EvidenceBodyWriteService $bodyWriteService;
    private LoggerInterface $logger;

    public function __construct(private PDO $pdo, private array $callbacks)
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
        $this->bodyWriteService = new EvidenceBodyWriteService($pdo);
        $this->logger = LoggerFactory::getLogger('service-ledger-evidence-save');
    }

    public function seedRowSave(array $payload): array
    {
        $id = trim((string) ($payload['id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        $type = $this->normalizeType((string) ($payload['import_type'] ?? (is_array($parsed) ? ($parsed['import_type'] ?? $parsed['source_type'] ?? '') : '')));
        if ($id === '' || !is_array($parsed) || $type === '') return $this->response(false, '수정할 증빙과 입력값이 필요합니다.', 400);
        if (!$this->hasRequiredBusinessClassification($parsed)) return $this->response(false, '사업구분, 거래구분, 업무유형을 모두 입력해 주세요.', 400);
        $rows = $this->bodyModel->identitiesByIds([$id], $type, false);
        if ($rows === []) return $this->response(false, '수정할 증빙을 찾을 수 없습니다.', 404);
        $linkType = $type === 'PAYROLL' ? 'PAYROLL_REPORT' : $type;
        if ($this->linkModel->hasActiveLink($linkType, $id)) {
            return $this->response(false, '활성 거래 또는 전표에 연결된 증빙은 수정할 수 없습니다.', 409);
        }
        $sourceType = $type === 'PAYROLL' ? (string) ($rows[0]['source_type'] ?? 'APPROVAL') : $type;
        $storageImportType = $type === 'PAYROLL' ? 'PAYROLL_REPORT' : $type;
        $row = array_merge($rows[0], $this->normalizePayload($parsed), ['id' => $id, 'source_type' => $sourceType, 'import_type' => $storageImportType,
            'updated_by' => ActorHelper::user(), 'updated_at' => date('Y-m-d H:i:s')]);
        $requestedStatus = $this->requestedEvidenceStatus($parsed, (string) ($rows[0]['evidence_status'] ?? 'CORRECTION_REQUIRED'));
        if ($requestedStatus === null) return $this->response(false, '올바른 증빙상태를 선택해 주세요.', 400);
        $row['evidence_status'] = $requestedStatus;
        return $this->saveOne($row, '증빙을 수정했습니다.');
    }

    public function evidenceCreate(array $payload): array
    {
        $parsed = $payload['parsed_json'] ?? null;
        $type = $this->normalizeType((string) ($payload['import_type'] ?? (is_array($parsed) ? ($parsed['import_type'] ?? $parsed['source_type'] ?? '') : '')));
        if (!is_array($parsed) || $type === '') return $this->response(false, '새 증빙의 자료유형과 입력값이 필요합니다.', 400);
        if (!$this->hasRequiredBusinessClassification($parsed)) return $this->response(false, '사업구분, 거래구분, 업무유형을 모두 입력해 주세요.', 400);
        $actor = ActorHelper::user();
        $requestedId = strtolower(trim((string) ($payload['id'] ?? '')));
        $id = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $requestedId) === 1
            ? $requestedId
            : UuidHelper::generate();
        $row = array_merge($this->normalizePayload($parsed), ['id' => $id, 'source_type' => $type, 'import_type' => $type,
            'created_by' => $actor, 'updated_by' => $actor, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $requestedStatus = $this->requestedEvidenceStatus($parsed, 'CORRECTION_REQUIRED');
        if ($requestedStatus === null) return $this->response(false, '올바른 증빙상태를 선택해 주세요.', 400);
        $row['evidence_status'] = $requestedStatus;
        return $this->saveOne($row, '증빙을 생성했습니다.');
    }

    public function evidenceBulkSave(array $payload): array
    {
        $ids = array_values(array_filter(array_unique(array_map('strval', $payload['ids'] ?? $payload['evidence_ids'] ?? []))));
        $patch = $payload['parsed_patch'] ?? [];
        $type = $this->normalizeType((string) ($payload['import_type'] ?? $payload['data_type'] ?? ''));
        if ($ids === [] || !is_array($patch) || $patch === [] || $type === '') return $this->response(false, '일괄수정 대상과 항목을 선택하세요.', 400);
        if (array_key_exists('evidence_status', $patch) && $this->requestedEvidenceStatus($patch, '') === null) {
            return $this->response(false, '올바른 증빙상태를 선택해 주세요.', 400);
        }
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'fill_blank')));
        $currentRows = $this->bodyModel->identitiesByIds($ids, $type, false);
        if (count($currentRows) !== count($ids)) return $this->response(false, '일괄수정 대상 증빙을 모두 찾을 수 없습니다.', 404);
        $linkType = $type === 'PAYROLL' ? 'PAYROLL_REPORT' : $type;
        foreach ($currentRows as $current) {
            if ($this->linkModel->hasActiveLink($linkType, (string) $current['id'])) {
                return $this->response(false, '활성 거래 또는 전표에 연결된 증빙이 포함되어 일괄수정할 수 없습니다.', 409);
            }
        }
        $updated = 0;
        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) $this->pdo->beginTransaction();
            foreach ($currentRows as $current) {
                $id = (string) $current['id'];
                $changes = $this->normalizePayload($patch);
                if ($mode === 'fill_blank') $changes = array_filter($changes, static fn(mixed $value, string $key): bool => ($current[$key] ?? null) === null || ($current[$key] ?? '') === '', ARRAY_FILTER_USE_BOTH);
                if (array_key_exists('evidence_status', $patch)) {
                    $changes['evidence_status'] = $this->requestedEvidenceStatus($patch, (string) $current['evidence_status']);
                } else {
                    unset($changes['evidence_status']);
                }
                if (!$this->hasRequiredBusinessClassification(array_merge($current, $changes))) {
                    if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
                    return $this->response(false, '사업구분, 거래구분, 업무유형을 모두 입력해 주세요.', 400);
                }
                $sourceType = $type === 'PAYROLL' ? (string) ($current['source_type'] ?? 'APPROVAL') : $type;
                $storageImportType = $type === 'PAYROLL' ? 'PAYROLL_REPORT' : $type;
                $result = $this->bodyWriteService->save(array_merge($current, $changes, ['id' => $id, 'source_type' => $sourceType, 'import_type' => $storageImportType,
                    'updated_by' => ActorHelper::user(), 'updated_at' => date('Y-m-d H:i:s')]));
                if (($result['status'] ?? '') === 'success') $updated++;
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('증빙원본 일괄수정에 실패했습니다.', [
                'event_code' => 'EVIDENCE_BULK_SAVE_FAILED',
                'result' => 'FAILED',
                'service' => self::class,
                'action' => 'bulk-save',
                'error_code' => get_class($e),
                'error' => $e,
            ]);
            throw $e;
        }
        $this->logger->info('증빙원본 일괄수정이 완료되었습니다.',['event_code'=>'EVIDENCE_BULK_SAVE_COMPLETED','result'=>'SUCCESS','service'=>self::class,'action'=>'bulk-save','actor'=>ActorHelper::user(),'requested_count'=>count($ids),'updated_count'=>$updated,'import_type'=>$type]);
        return ['payload' => ['success' => true, 'message' => '증빙 일괄수정을 완료했습니다.', 'data' => ['updated_count' => $updated, 'locked_count' => 0]], 'status' => 200];
    }

    private function saveOne(array $row, string $message): array
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) $this->pdo->beginTransaction();
            $result = $this->bodyWriteService->save($row);
            if (($result['status'] ?? '') !== 'success') {
                if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
                return $this->response(false, '저장 중 오류가 발생했습니다.', 500);
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->logger->error('증빙원본 저장에 실패했습니다.', [
                'event_code' => 'EVIDENCE_SAVE_FAILED',
                'result' => 'FAILED',
                'service' => self::class,
                'action' => 'save',
                'error_code' => get_class($e),
                'error' => $e,
            ]);
            throw $e;
        }
        $this->logger->info('증빙원본 저장이 완료되었습니다.',['event_code'=>'EVIDENCE_SAVE_COMPLETED','result'=>'SUCCESS','service'=>self::class,'action'=>'save','actor'=>ActorHelper::user(),'target_id'=>$row['id'],'import_type'=>$row['import_type']]);
        return ['payload' => ['success' => true, 'message' => $message, 'data' => ['id' => $row['id'], 'import_type' => $row['import_type']]], 'status' => 200];
    }

    private function normalizePayload(array $payload): array
    {
        return isset($this->callbacks['mappedPayloadForStorage']) ? ($this->callbacks['mappedPayloadForStorage'])($payload) : $payload;
    }

    private function normalizeType(string $type): string
    {
        return isset($this->callbacks['normalizeDataType']) ? ($this->callbacks['normalizeDataType'])($type) : strtoupper(trim($type));
    }

    private function requestedEvidenceStatus(array $payload, string $default): ?string
    {
        $status = strtoupper(trim((string) ($payload['evidence_status'] ?? $default)));
        if (!in_array($status, ['COMPLETED', 'CORRECTION_REQUIRED'], true)) {
            return null;
        }

        return $status;
    }

    private function hasRequiredBusinessClassification(array $payload): bool
    {
        foreach (['business_unit', 'transaction_direction', 'operation_type'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') return false;
        }

        return true;
    }

    private function response(bool $success, string $message, int $status): array
    {
        if(!$success)$this->logger->warning('증빙원본 저장이 차단되었습니다.',['event_code'=>'EVIDENCE_SAVE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'save','actor'=>ActorHelper::user(),'status'=>$status,'reason'=>$message]);
        return ['payload' => ['success' => $success, 'message' => $message], 'status' => $status];
    }
}
