<?php
namespace App\Services\System;

use App\Models\System\CodeModel;
use App\Services\Concerns\LogsServiceOperations;
use Core\Helpers\ActorHelper;
use Core\Helpers\SequenceHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class CodeService
{
    use LogsServiceOperations;
    private readonly PDO $pdo;
    private CodeModel $model;
    private CodeReferenceService $references;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new CodeModel($pdo);
        $this->references = new CodeReferenceService($this->model);
        $this->logger = LoggerFactory::getLogger('service-system.CodeService');
    }

    public function getList(array $filters = []): array
    {
        try {
            return $this->model->getList($filters);
        } catch (\Throwable $e) {
            $this->logger->error('기준코드 목록 조회에 실패했습니다.', ['event_code' => 'SYSTEM_CODE_LIST_FAILED', 'result' => 'FAILED', 'error_code' => get_class($e), 'error' => $e]);
            return [];
        }
    }

    public function getOptionsByGroup(string $codeGroup): array
    {
        $codeGroup = trim($codeGroup);

        if ($codeGroup === '') {
            return [];
        }

        try {
            return $this->model->getOptionsByGroup($codeGroup);
        } catch (\Throwable $e) {
            $this->logger->error('기준코드 선택항목 조회에 실패했습니다.', [
                'event_code' => 'SYSTEM_CODE_OPTIONS_FAILED',
                'result' => 'FAILED',
                'code_group' => $codeGroup,
                'error_code' => get_class($e),
                'error' => $e,
            ]);
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('기준코드 상세 조회에 실패했습니다.', ['event_code' => 'SYSTEM_CODE_DETAIL_FAILED', 'result' => 'FAILED', 'error_code' => get_class($e), 'error' => $e]);
            return null;
        }
    }

    public function getGroups(): array
    {
        try {
            return $this->model->getGroups();
        } catch (\Throwable $e) {
            $this->logger->error('기준코드 그룹 조회에 실패했습니다.', ['event_code' => 'SYSTEM_CODE_GROUPS_FAILED', 'result' => 'FAILED', 'error_code' => get_class($e), 'error' => $e]);
            return [];
        }
    }

    public function save(array $data, string $actorType = 'USER'): array
    {
        return $this->loggedMutation('기준코드 저장', 'SYSTEM_CODE_SAVE', 'save', fn(): array => $this->saveInternal($data, $actorType));
    }

    private function saveInternal(array $data, string $actorType = 'USER'): array
    {
        $actor = ActorHelper::resolve($actorType);

        try {
            $this->pdo->beginTransaction();

            $id = trim((string)($data['id'] ?? ''));
            $sortNoProvided = array_key_exists('sort_no', $data) && $data['sort_no'] !== '' && $data['sort_no'] !== null;
            $data = $this->normalize($data);
            $duplicateExcludeId = $id !== '' ? $id : null;

            if ($id === '') {
                $existingGroupName = $this->model->getGroupNameByCodeGroup($data['code_group']);
                if ($existingGroupName !== null && $existingGroupName !== $data['group_name']) {
                    throw new \InvalidArgumentException('기존 코드그룹의 그룹명과 일치하지 않습니다.');
                }
            }

            if ($this->model->existsByGroupAndCode($data['code_group'], $data['code'], $duplicateExcludeId)) {
                throw new \Exception('같은 코드그룹에 동일한 코드가 이미 등록되어 있습니다.');
            }

            if ($id !== '') {
                $before = $this->model->getById($id);
                if (!$before) {
                    throw new \Exception('수정할 코드 정보를 찾을 수 없습니다.');
                }

                if (!$sortNoProvided) {
                    $data['sort_no'] = (int)($before['sort_no'] ?? 0);
                }

                $this->assertUpdateAllowed($before, $data);

                $data['updated_by'] = $actor;
                unset($data['id']);

                if (!$this->model->updateById($id, $data)) {
                    throw new \Exception('코드 정보 수정에 실패했습니다.');
                }

                $this->model->updateGroupNameByCodeGroup($data['code_group'], $data['group_name'], $actor);

                $this->pdo->commit();

                return [
                    'success' => true,
                    'id' => $id,
                    'sort_no' => $data['sort_no'] ?? ($before['sort_no'] ?? null),
                ];
            }

            $newId = UuidHelper::generate();
            $newSortNo = $sortNoProvided
                ? (int)$data['sort_no']
                : SequenceHelper::next('system_codes', 'sort_no');

            $insertData = array_merge($data, [
                'id' => $newId,
                'sort_no' => $newSortNo,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            if (!$this->model->create($insertData)) {
                throw new \Exception('코드 정보 저장에 실패했습니다.');
            }

            $this->model->updateGroupNameByCodeGroup($data['code_group'], $data['group_name'], $actor);

            $this->pdo->commit();

            return [
                'success' => true,
                'id' => $newId,
                'sort_no' => $newSortNo,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['success' => false, 'message' => $this->userMessage($e, '저장 중 오류가 발생했습니다.')];
        }
    }

    public function delete(string $id, string $actorType = 'USER'): array
    {
        return $this->loggedMutation('기준코드 삭제', 'SYSTEM_CODE_DELETE', 'delete', fn(): array => $this->deleteInternal($id, $actorType));
    }

    private function deleteInternal(string $id, string $actorType = 'USER'): array
    {
        ActorHelper::resolve($actorType);

        try {
            $this->pdo->beginTransaction();
            $row = $this->model->getById($id);
            if (!$row) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => '삭제할 코드 정보를 찾을 수 없습니다.'];
            }

            $this->assertDeleteAllowed($row);
            $deleted = $this->model->deleteById($id);
            $this->pdo->commit();
            return ['success' => $deleted];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $this->userMessage($e, '삭제 중 오류가 발생했습니다.')];
        }
    }

    public function referenceStatus(string $id): array
    {
        $row = $this->model->getById($id);
        if (!$row) {
            return ['success' => false, 'message' => '코드 정보를 찾을 수 없습니다.'];
        }
        $status = $this->inspectReferences($row);
        return ['success' => true, 'data' => $status];
    }

    public function reorder(array $changes, string $actorType = 'USER'): bool
    {
        return $this->runLoggedOperation($this->logger, '기준코드 정렬 저장', 'SYSTEM_CODE_REORDER', 'reorder', ['change_count' => count($changes)], fn(): bool => $this->reorderInternal($changes, $actorType), 'info', false, static fn(bool $result): string => $result ? 'SUCCESS' : 'BLOCKED');
    }

    private function reorderInternal(array $changes, string $actorType = 'USER'): bool
    {
        if (empty($changes)) {
            return true;
        }

        $actor = ActorHelper::resolve($actorType);
        $this->pdo->beginTransaction();

        try {
            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)((int)$row['newSortNo'] + 1000000), $actor);
            }

            foreach ($changes as $row) {
                $this->model->updateSortNo((string)$row['id'], (string)(int)$row['newSortNo'], $actor);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function normalize(array $data): array
    {
        $data['code_group'] = strtoupper(preg_replace('/\s+/', '', trim((string)($data['code_group'] ?? ''))));
        $data['group_name'] = trim((string)($data['group_name'] ?? ''));
        $data['code'] = strtoupper(trim((string)($data['code'] ?? '')));
        $data['code_name'] = trim((string)($data['code_name'] ?? ''));
        $data['note'] = $this->blankToNull($data['note'] ?? null);
        $data['memo'] = $this->blankToNull($data['memo'] ?? null);
        $data['extra_data'] = $this->normalizeExtraData($data['code_group'], $data['extra_data'] ?? null);
        $data['is_active'] = (int)($data['is_active'] ?? 1);
        if (!in_array($data['is_active'], [0, 1], true)) {
            throw new \InvalidArgumentException('사용여부 값이 올바르지 않습니다.');
        }
        $data['sort_no'] = isset($data['sort_no']) && $data['sort_no'] !== ''
            ? (int)$data['sort_no']
            : null;

        if ($data['code_group'] === '' || !preg_match('/^[A-Z_]+$/', $data['code_group'])) {
            throw new \InvalidArgumentException('코드그룹은 영문 대문자와 밑줄(_)만 입력할 수 있습니다.');
        }

        if ($data['group_name'] === '') {
            throw new \InvalidArgumentException('그룹명은 필수 입력값입니다.');
        }

        return $data;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function normalizeExtraData(string $codeGroup, mixed $value): ?string
    {
        $json = $this->blankToNull($value);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \InvalidArgumentException('추가정보는 올바른 JSON 형식으로 입력해야 합니다.');
        }

        if ($codeGroup === 'BANK') {
            $this->validateBankExtraData($decoded);
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validateBankExtraData(array $extraData): void
    {
        $formatValue = null;
        foreach (['account_format', 'account_formats', 'accountNumberFormat', 'account_number_format', 'format', 'formats'] as $key) {
            if (array_key_exists($key, $extraData)) {
                $formatValue = $extraData[$key];
                break;
            }
        }

        if ($formatValue === null) {
            return;
        }

        if (is_string($formatValue)) {
            $this->assertBankFormatPattern($formatValue);
            return;
        }

        if (is_array($formatValue)) {
            if ($this->isListArray($formatValue)) {
                $this->assertBankFormatParts($formatValue);
                return;
            }

            foreach ($formatValue as $length => $pattern) {
                if (!ctype_digit((string)$length) || (int)$length <= 0) {
                    throw new \InvalidArgumentException('계좌번호 형식 길이 키는 1 이상의 숫자여야 합니다.');
                }

                if (is_string($pattern)) {
                    $this->assertBankFormatPattern($pattern);
                    continue;
                }

                if (is_array($pattern)) {
                    $this->assertBankFormatParts($pattern);
                    continue;
                }

                throw new \InvalidArgumentException('계좌번호 형식 값은 문자열 패턴 또는 숫자 배열만 입력할 수 있습니다.');
            }

            return;
        }

        throw new \InvalidArgumentException('계좌번호 형식은 문자열 패턴, 숫자 배열, 또는 길이별 형식 객체만 입력할 수 있습니다.');
    }

    private function assertBankFormatPattern(string $pattern): void
    {
        $pattern = trim($pattern);
        if ($pattern === '' || preg_match('/^[#0]+(?:-[#0]+)*$/', $pattern) !== 1) {
            throw new \InvalidArgumentException('계좌번호 형식 문자열은 # 또는 0 패턴만 사용할 수 있습니다.');
        }
    }

    private function assertBankFormatParts(array $parts): void
    {
        if (empty($parts)) {
            throw new \InvalidArgumentException('계좌번호 형식 숫자 배열에는 1개 이상의 값이 필요합니다.');
        }

        foreach ($parts as $part) {
            if (!is_int($part) && !ctype_digit((string)$part)) {
                throw new \InvalidArgumentException('계좌번호 형식 숫자 배열에는 숫자만 입력할 수 있습니다.');
            }

            if ((int)$part <= 0) {
                throw new \InvalidArgumentException('계좌번호 형식 숫자 배열 값은 0보다 커야 합니다.');
            }
        }
    }

    private function isListArray(array $values): bool
    {
        return array_keys($values) === range(0, count($values) - 1);
    }

    private function assertUpdateAllowed(array $before, array $after): void
    {
        $status = $this->inspectReferences($before);
        $references = $status['references'];

        if ((string)($before['code_group'] ?? '') !== (string)($after['code_group'] ?? '')) {
            if (!$status['checked']) {
                throw new \RuntimeException('참조 상태를 확인할 수 없어 코드그룹을 변경할 수 없습니다.');
            }
            if (empty($references)) {
                return;
            }
            throw new \RuntimeException($this->buildReferenceMessage('참조 중인 코드라 코드그룹을 변경할 수 없습니다.', $references));
        }

        if ((string)($before['code'] ?? '') !== (string)($after['code'] ?? '')) {
            if (!$status['checked']) {
                throw new \RuntimeException('참조 상태를 확인할 수 없어 코드값을 변경할 수 없습니다.');
            }
            if (empty($references)) {
                return;
            }
            throw new \RuntimeException($this->buildReferenceMessage('참조 중인 코드라 코드값을 변경할 수 없습니다.', $references));
        }
    }

    private function assertDeleteAllowed(array $row): void
    {
        $status = $this->inspectReferences($row);
        if (!$status['checked']) {
            throw new \RuntimeException('참조 상태를 확인할 수 없어 삭제할 수 없습니다.');
        }
        $references = $status['references'];
        if (empty($references)) {
            return;
        }

        throw new \RuntimeException($this->buildReferenceMessage('참조 중인 코드라 영구삭제할 수 없습니다.', $references));
    }

    private function inspectReferences(array $row): array
    {
        return $this->references->inspect(
            (string) ($row['code_group'] ?? ''),
            (string) ($row['code'] ?? ''),
            (string) ($row['code_name'] ?? '')
        );
    }

    private function buildReferenceMessage(string $message, array $references): string
    {
        $summary = array_map(
            fn(array $reference) => "{$reference['label']} {$reference['count']}건",
            array_slice($references, 0, 5)
        );

        $remaining = count($references) - count($summary);
        if ($remaining > 0) {
            $summary[] = "외 {$remaining}건";
        }

        return $message . ' 참조처: ' . implode(', ', $summary);
    }

    private function userMessage(\Throwable $error, string $fallback): string
    {
        $message = $error->getMessage();
        if ($error instanceof \InvalidArgumentException) {
            return $message;
        }
        if (str_contains($message, '동일한 코드가 이미 등록되어 있습니다.')) {
            return '이미 등록된 코드입니다.';
        }
        foreach (['참조 중인 코드', '참조 상태를 확인할 수 없어', '삭제할 코드 정보를 찾을 수 없습니다.'] as $allowed) {
            if (str_contains($message, $allowed)) {
                return $message;
            }
        }
        return $fallback;
    }

    private function loggedMutation(string $label, string $eventCode, string $action, callable $operation): array
    {
        return $this->runLoggedOperation($this->logger, $label, $eventCode, $action, [], $operation, 'info', false,
            static fn(array $result): string => !empty($result['success']) ? 'SUCCESS' : (str_contains((string) ($result['message'] ?? ''), '오류') ? 'FAILED' : 'BLOCKED'));
    }

}
