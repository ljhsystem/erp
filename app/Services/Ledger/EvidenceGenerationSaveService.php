<?php

namespace App\Services\Ledger;

use App\Models\Ledger\EvidenceBodyStorageModel;
use App\Models\Ledger\EvidenceLinkModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use PDO;

/**
 * 기존 API 이름을 유지하는 Body 저장 호환 진입점이다. 생성센터 상태는 저장하지 않는다.
 */
class EvidenceGenerationSaveService
{
    private EvidenceBodyStorageModel $bodyModel;
    private EvidenceLinkModel $linkModel;
    private EvidenceBodyWriteService $bodyWriteService;

    public function __construct(private PDO $pdo, private array $callbacks)
    {
        $this->bodyModel = new EvidenceBodyStorageModel($pdo);
        $this->linkModel = new EvidenceLinkModel($pdo);
        $this->bodyWriteService = new EvidenceBodyWriteService($pdo);
    }

    public function seedRowSave(array $payload): array
    {
        $id = trim((string) ($payload['id'] ?? ''));
        $parsed = $payload['parsed_json'] ?? null;
        $type = $this->normalizeType((string) ($payload['import_type'] ?? (is_array($parsed) ? ($parsed['import_type'] ?? $parsed['source_type'] ?? '') : '')));
        if ($id === '' || !is_array($parsed) || $type === '') return $this->response(false, '수정할 증빙과 입력값이 필요합니다.', 400);
        $rows = $this->bodyModel->identitiesByIds([$id], $type, false);
        if ($rows === []) return $this->response(false, '수정할 증빙을 찾을 수 없습니다.', 404);
        if ($this->linkModel->hasActiveLink($type, $id)) return $this->response(false, '거래 또는 전표에 연결된 증빙은 수정할 수 없습니다.', 409);
        $row = array_merge($rows[0], $this->normalizePayload($parsed), ['id' => $id, 'source_type' => $type, 'import_type' => $type,
            'updated_by' => ActorHelper::user(), 'updated_at' => date('Y-m-d H:i:s')]);
        $row['evidence_status'] = $this->evidenceStatusForPayload($row, $payload);
        return $this->saveOne($row, '증빙을 수정했습니다.');
    }

    public function evidenceCreate(array $payload): array
    {
        $parsed = $payload['parsed_json'] ?? null;
        $type = $this->normalizeType((string) ($payload['import_type'] ?? (is_array($parsed) ? ($parsed['import_type'] ?? $parsed['source_type'] ?? '') : '')));
        if (!is_array($parsed) || $type === '') return $this->response(false, '새 증빙의 자료유형과 입력값이 필요합니다.', 400);
        $actor = ActorHelper::user();
        $requestedId = strtolower(trim((string) ($payload['id'] ?? '')));
        $id = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $requestedId) === 1
            ? $requestedId
            : UuidHelper::generate();
        $row = array_merge($this->normalizePayload($parsed), ['id' => $id, 'source_type' => $type, 'import_type' => $type,
            'created_by' => $actor, 'updated_by' => $actor, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $row['evidence_status'] = $this->evidenceStatusForPayload(array_merge($parsed, $row), $payload);
        return $this->saveOne($row, '증빙을 생성했습니다.');
    }

    public function evidenceBulkSave(array $payload): array
    {
        $ids = array_values(array_filter(array_unique(array_map('strval', $payload['ids'] ?? $payload['evidence_ids'] ?? []))));
        $patch = $payload['parsed_patch'] ?? [];
        $type = $this->normalizeType((string) ($payload['import_type'] ?? $payload['data_type'] ?? ''));
        if ($ids === [] || !is_array($patch) || $patch === [] || $type === '') return $this->response(false, '일괄수정 대상과 항목을 선택하세요.', 400);
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'fill_blank')));
        $updated = 0; $locked = 0;
        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) $this->pdo->beginTransaction();
            foreach ($this->bodyModel->identitiesByIds($ids, $type, false) as $current) {
                $id = (string) $current['id'];
                if ($this->linkModel->hasActiveLink($type, $id)) { $locked++; continue; }
                $changes = $this->normalizePayload($patch);
                if ($mode === 'fill_blank') $changes = array_filter($changes, static fn(mixed $value, string $key): bool => ($current[$key] ?? null) === null || ($current[$key] ?? '') === '', ARRAY_FILTER_USE_BOTH);
                $result = $this->bodyWriteService->save(array_merge($current, $changes, ['id' => $id, 'source_type' => $type, 'import_type' => $type,
                    'updated_by' => ActorHelper::user(), 'updated_at' => date('Y-m-d H:i:s')]));
                if (($result['status'] ?? '') === 'success') $updated++;
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return ['payload' => ['success' => true, 'message' => '증빙 일괄수정을 완료했습니다.', 'data' => ['updated_count' => $updated, 'locked_count' => $locked]], 'status' => 200];
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
            throw $e;
        }
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

    private function evidenceStatusForPayload(array $row, array $request): string
    {
        $validationPayload = $row;
        $validationPayload['_column_display_name'] = $request['column_display_name'] ?? [];
        $validationPayload['_column_requirement_policy'] = $request['column_requirement_policy'] ?? [];
        $missingMessages = isset($this->callbacks['requiredFormatMissingMessages'])
            ? ($this->callbacks['requiredFormatMissingMessages'])($validationPayload, [])
            : [];

        return isset($this->callbacks['evidenceStatusFromRequiredMissingMessages'])
            ? ($this->callbacks['evidenceStatusFromRequiredMissingMessages'])($missingMessages)
            : ($missingMessages === [] ? 'COMPLETED' : 'CORRECTION_REQUIRED');
    }

    private function response(bool $success, string $message, int $status): array
    {
        return ['payload' => ['success' => $success, 'message' => $message], 'status' => $status];
    }
}
