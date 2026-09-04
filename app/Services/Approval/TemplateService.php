<?php
namespace App\Services\Approval;

use PDO;
use App\Models\User\ApprovalTemplateModel;
use App\Models\User\ApprovalTemplateStepModel;
use App\Models\User\EmployeeModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\Helpers\SequenceHelper;
use Core\LoggerFactory;

class TemplateService
{
    private readonly PDO $pdo;
    private  $model;
    private $logger;
    private ApprovalTemplateStepModel $steps;
    private EmployeeModel $employees;

    public function __construct(PDO $pdo)
    {
        $this->pdo    = $pdo;
        $this->model  = new ApprovalTemplateModel($pdo);
        $this->steps = new ApprovalTemplateStepModel($pdo);
        $this->employees = new EmployeeModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval.ApprovalTemplateService');
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function generateTemplateKey(string $name): string
    {
        $roman = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name)
              ?: preg_replace('/[^\x20-\x7E]/', '', $name);

        $base = substr(strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $roman), '_')), 0, 42)
             ?: 'template_' . substr(md5(uniqid()), 0, 6);

        $key = $base;
        $i = 1;

        while ($this->model->templateKeyExists($key)) {
            $key = $base . '_' . ($i++);
        }

        return $key;
    }

    public function getAll(): array
    {
        return $this->model->getAll();
    }

    public function getById(string $id): ?array
    {
        return $this->model->getById($id);
    }

    public function create(array $data): array
    {
        return $this->logged('APPROVAL_TEMPLATE_CREATE', 'create', [], fn(): array => $this->createInternal($data));
    }

    private function createInternal(array $data): array
    {
        $validation = $this->validatePayload($data, false);
        if (!$validation['success']) {
            return $validation;
        }
        $data = $validation['data'];
        $data['is_active'] = 0;
        if ($this->model->existsName($data['template_name'], $data['document_type'])) {

            return [
                'success' => false,
                'message' => '이미 동일한 템플릿이 존재합니다.'
            ];
        }

        $id  = UuidHelper::generate();
        $key = $this->generateTemplateKey($data['template_name']);
        $data['sort_no'] = SequenceHelper::next('user_approval_templates', 'sort_no');
        $data['created_by'] = ActorHelper::user();
        $data['updated_by'] = $data['created_by'];

        $ok = $this->model->create($id, $key, $data);

        return [
            'success' => (bool)$ok,
            'id'      => $id,
            'key'     => $key
        ];
    }

    public function update(string $id, array $data): array
    {
        return $this->logged('APPROVAL_TEMPLATE_UPDATE', 'update', ['template_id' => $id], fn(): array => $this->updateInternal($id, $data));
    }

    private function updateInternal(string $id, array $data): array
    {
        $existing = $this->model->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => '결재템플릿을 찾을 수 없습니다.'];
        }
        $validation = $this->validatePayload(array_merge($existing, $data), true);
        if (!$validation['success']) {
            return $validation;
        }
        $data = $validation['data'];
        $data['updated_by'] = ActorHelper::user();

        if ($this->model->existsName($data['template_name'], $data['document_type'], $id)) {

            return [
                'success' => false,
                'message' => '이미 동일한 템플릿이 존재합니다.'
            ];
        }

        if ((int) $existing['is_active'] === 1
            && (string) $existing['document_type'] !== (string) $data['document_type']) {
            return ['success' => false, 'message' => '활성 템플릿의 문서유형을 변경하려면 먼저 비활성화해 주세요.'];
        }
        if ((int) $data['is_active'] === 1) {
            if ($this->model->activeDocumentTypeExists($data['document_type'], $id)) {
                return ['success' => false, 'message' => '같은 문서유형의 활성 결재템플릿이 이미 존재합니다. 기존 템플릿을 먼저 비활성화해 주세요.'];
            }
            $flow = $this->validateExecutionFlow($id);
            if (!$flow['success']) {
                return $flow;
            }
        }
        $ok = $this->model->update($id, $data);

        return ['success' => $ok];
    }

    public function delete(string $id): array
    {
        return $this->logged('APPROVAL_TEMPLATE_DELETE', 'delete', ['template_id' => $id], fn(): array => $this->deleteInternal($id));
    }

    private function deleteInternal(string $id): array
    {
        $existing = $this->model->getById($id);
        if (!$existing) {
            return ['success' => false, 'message' => '결재템플릿을 찾을 수 없습니다.'];
        }
        $dependencies = $this->model->dependencyCounts($id);
        if ($dependencies['requests'] > 0) {
            return ['success' => false, 'message' => '결재요청 사용 이력이 있어 영구 삭제할 수 없습니다. 비활성화해 주세요.'];
        }
        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            if ($dependencies['steps'] > 0 && !$this->model->deleteSteps($id)) {
                throw new \RuntimeException('결재단계 삭제에 실패했습니다.');
            }
            if (!$this->model->delete($id)) {
                throw new \RuntimeException('결재템플릿 삭제에 실패했습니다.');
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return ['success' => true, 'message' => '삭제되었습니다.'];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => '삭제 중 오류가 발생했습니다.'];
        }
    }

    private function validatePayload(array $data, bool $allowActive): array
    {
        $name = $this->normalize((string) ($data['template_name'] ?? ''));
        $documentType = $this->normalize((string) ($data['document_type'] ?? ''));
        if ($name === '' || $documentType === '') {
            return ['success' => false, 'message' => '템플릿명과 문서유형은 필수 항목입니다.'];
        }
        if (mb_strlen($name) > 100 || mb_strlen($documentType) > 100) {
            return ['success' => false, 'message' => '템플릿명은 100자, 문서유형은 100자 이내로 입력해 주세요.'];
        }
        $active = $allowActive ? (int) ($data['is_active'] ?? 0) : 0;
        if (!in_array($active, [0, 1], true)) {
            return ['success' => false, 'message' => '활성 상태 값이 올바르지 않습니다.'];
        }
        return ['success' => true, 'data' => [
            'template_name' => $name,
            'document_type' => $documentType,
            'description' => (string) ($data['description'] ?? ''),
            'is_active' => $active,
        ]];
    }

    private function validateExecutionFlow(string $templateId): array
    {
        $rows = $this->steps->getActiveSteps($templateId);
        if (count($rows) < 2) {
            return ['success' => false, 'message' => '활성화하려면 제출과 최종승인을 포함한 최소 2개 활성 단계가 필요합니다.'];
        }
        $types = array_map(static fn (array $row): string => strtoupper((string) $row['step_type']), $rows);
        if ($types[0] !== 'SUBMIT' || $types[count($types) - 1] !== 'FINAL_APPROVAL'
            || count(array_filter($types, static fn (string $type): bool => $type === 'SUBMIT')) !== 1
            || count(array_filter($types, static fn (string $type): bool => $type === 'FINAL_APPROVAL')) !== 1) {
            return ['success' => false, 'message' => '결재단계는 제출 1개로 시작하고 최종승인 1개로 끝나야 합니다.'];
        }
        foreach ($rows as $index => $row) {
            $expected = $index === 0 ? 'SUBMIT' : ($index === count($rows) - 1 ? 'FINAL_APPROVAL' : 'APPROVAL');
            if (strtoupper((string) $row['step_type']) !== $expected) {
                return ['success' => false, 'message' => '결재단계 순서와 단계유형이 올바르지 않습니다.'];
            }
            if ($expected === 'SUBMIT') {
                if (!empty($row['role_id']) || !empty($row['approver_id'])) {
                    return ['success' => false, 'message' => '제출 단계에는 역할이나 지정결재자를 설정할 수 없습니다.'];
                }
                continue;
            }
            $roleId = trim((string) ($row['role_id'] ?? ''));
            $approverId = trim((string) ($row['approver_id'] ?? ''));
            if ($roleId === '' && $approverId === '') {
                $stepName = trim((string) ($row['step_name'] ?? '')) ?: '승인 단계';
                return ['success' => false, 'message' => "{$stepName} 단계에는 역할 또는 지정결재자가 필요합니다."];
            }
            $eligibility = $roleId !== '' && $approverId !== ''
                ? $this->employees->userEligibilityForRole($approverId, $roleId)
                : ($roleId !== ''
                    ? ['eligible' => $this->employees->hasEligibleUserForRole($roleId), 'message' => '해당 역할에 현재 적격 결재자가 없습니다.']
                    : $this->employees->userEligibility($approverId));
            if (!($eligibility['eligible'] ?? false)) {
                return ['success' => false, 'message' => (string) ($eligibility['message'] ?? '결재자 적격성 검증에 실패했습니다.')];
            }
        }
        return ['success' => true, 'message' => ''];
    }

    public function reorder(array $changes): bool
    {
        return $this->logged('APPROVAL_TEMPLATE_REORDER', 'reorder', ['change_count' => count($changes)], fn(): bool => $this->reorderInternal($changes));
    }

    private function reorderInternal(array $changes): bool
    {
        if (!$changes) {
            return true;
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }

            $lockedRows = $this->model->getAllForUpdate();
            $lockedIds = array_map(
                static fn (array $row): string => (string) $row['id'],
                $lockedRows
            );
            $requested = [];
            foreach ($changes as $row) {
                $id = trim((string) ($row['id'] ?? ''));
                $sortNo = $row['newSortNo'] ?? $row['sort_no'] ?? null;
                if ($id === '' || $sortNo === null || isset($requested[$id])) {
                    throw new \InvalidArgumentException('순번 변경 데이터가 올바르지 않습니다.');
                }
                $requested[$id] = (int) $sortNo;
            }
            if (count($requested) !== count($lockedIds)
                || array_diff(array_keys($requested), $lockedIds)
                || array_diff($lockedIds, array_keys($requested))) {
                throw new \InvalidArgumentException('전체 결재템플릿 순서가 필요합니다.');
            }
            asort($requested, SORT_NUMERIC);

            $actor = ActorHelper::user();
            $temporaryBase = max(
                array_map(static fn (array $row): int => (int) $row['sort_no'], $lockedRows)
            ) + count($lockedRows) + 1000;
            foreach (array_keys($requested) as $index => $id) {
                if (!$this->model->updateSortNo($id, $temporaryBase + $index + 1, $actor)) {
                    throw new \RuntimeException('임시 순번 저장에 실패했습니다.');
                }
            }
            foreach (array_keys($requested) as $index => $id) {
                if (!$this->model->updateSortNo($id, $index + 1, $actor)) {
                    throw new \RuntimeException('순번 저장에 실패했습니다.');
                }
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return true;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function logged(string $eventCode, string $action, array $context, callable $operation): mixed
    {
        try {
            $result = $operation();
            $blocked = $result === false || (is_array($result) && array_key_exists('success', $result) && !$result['success']);
            $this->logger->{$blocked ? 'warning' : 'info'}($blocked ? '결재양식 업무 처리가 차단되었습니다.' : '결재양식 업무 처리를 완료했습니다.', ['event_code' => $eventCode . ($blocked ? '_BLOCKED' : ''), 'result' => $blocked ? 'BLOCKED' : 'SUCCESS', 'service' => self::class, 'action' => $action] + $context);
            return $result;
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $this->logger->warning('결재양식 업무 처리가 차단되었습니다.', ['event_code' => $eventCode . '_BLOCKED', 'result' => 'BLOCKED', 'service' => self::class, 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('결재양식 업무 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'service' => self::class, 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception;
        }
    }
}
