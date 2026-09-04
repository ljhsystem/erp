<?php
namespace App\Services\Approval;

use App\Models\User\ApprovalTemplateStepModel;
use App\Models\User\EmployeeModel;
use App\Models\User\ApprovalTemplateModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class TemplateStepService
{
    private ApprovalTemplateStepModel $model;
    private EmployeeModel $employees;
    private ApprovalTemplateModel $templates;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model = new ApprovalTemplateStepModel($pdo);
        $this->employees = new EmployeeModel($pdo);
        $this->templates = new ApprovalTemplateModel($pdo);
        $this->logger = LoggerFactory::getLogger('service-approval.ApprovalTemplateStepService');
    }

    public function getSteps(string $templateId): array
    {
        try {
            return $this->model->getSteps($templateId);
        } catch (\Throwable $e) {
            $this->logger->error('결재단계 목록 조회에 실패했습니다.', ['event_code'=>'APPROVAL_TEMPLATE_STEP_LIST_FAILED','result'=>'FAILED','error_code'=>get_class($e),'error'=>$e]);
            return [];
        }
    }

    public function getById(string $id): ?array
    {
        try {
            return $this->model->getById($id);
        } catch (\Throwable $e) {
            $this->logger->error('결재단계 상세 조회에 실패했습니다.', ['event_code'=>'APPROVAL_TEMPLATE_STEP_DETAIL_FAILED','result'=>'FAILED','error_code'=>get_class($e),'error'=>$e]);
            return null;
        }
    }

    public function create(array $data): array
    {
        return $this->logged('APPROVAL_TEMPLATE_STEP_CREATE', 'create', ['template_id' => $data['template_id'] ?? null], fn(): array => $this->createInternal($data));
    }

    private function createInternal(array $data): array
    {
        try {
            return $this->transaction(function () use ($data): array {
                $templateId = trim((string) ($data['template_id'] ?? ''));
                $stepName = trim((string) ($data['step_name'] ?? ''));
                $basic = $this->validatePayload($templateId, $stepName, $data);
                if (!$basic['success']) {
                    return $basic;
                }
                if ($this->templateIsActive($templateId)) {
                    return ['success' => false, 'message' => '해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.'];
                }
                $stepType = strtoupper(trim((string) ($data['step_type'] ?? 'APPROVAL')));
                if (!in_array($stepType, ['SUBMIT', 'APPROVAL', 'FINAL_APPROVAL'], true)) {
                    return ['success' => false, 'message' => '유효하지 않은 단계유형입니다.'];
                }
                $assignmentValidation = $this->validateAssignment($data, $stepType);
                if (!$assignmentValidation['success']) {
                    return $assignmentValidation;
                }
                $this->model->lockTemplate($templateId);

                if ($this->model->existsStepName($templateId, $stepName)) {
                    return ['success' => false, 'message' => '이미 동일한 단계명이 존재합니다.'];
                }

                $actor = ActorHelper::user();
                $payload = $data + [];
                $payload['id'] = UuidHelper::generate();
                $payload['step_type'] = $stepType;
                if ($stepType === 'SUBMIT') {
                    $payload['role_id'] = null;
                    $payload['approver_id'] = null;
                }
                $payload['sort_no'] = $this->model->nextSortNoForTemplate($templateId);
                $payload['is_active'] = $data['is_active'] ?? 1;
                $payload['created_by'] = $actor;
                $payload['updated_by'] = $actor;
                $ok = $this->model->create($payload);
                $this->model->normalizeActiveExecutionTypes($templateId, $actor);

                return ['success' => $ok, 'id' => $payload['id'], 'sort_no' => $payload['sort_no']];
            });
        } catch (\Throwable $e) {
            $this->logger->error('결재단계 저장에 실패했습니다.', ['event_code' => 'APPROVAL_TEMPLATE_STEP_CREATE_FAILED', 'result' => 'FAILED', 'service' => self::class, 'action' => 'approval_template_step.create', 'error_code'=>get_class($e),'error'=>$e]);
            return ['success' => false, 'message' => '결재단계 저장 중 오류가 발생했습니다.'];
        }
    }

    public function update(string $id, array $data): array
    {
        return $this->logged('APPROVAL_TEMPLATE_STEP_UPDATE', 'update', ['step_id' => $id], fn(): array => $this->updateInternal($id, $data));
    }

    private function updateInternal(string $id, array $data): array
    {
        try {
            return $this->transaction(function () use ($id, $data): array {
                $existing = $this->model->getById($id);
                if (!$existing) {
                    return ['success' => false, 'message' => '결재단계를 찾을 수 없습니다.'];
                }

                $oldTemplateId = (string) $existing['template_id'];
                $newTemplateId = trim((string) ($data['template_id'] ?? $oldTemplateId));
                $templateIds = array_values(array_unique([$oldTemplateId, $newTemplateId]));
                sort($templateIds, SORT_STRING);
                foreach ($templateIds as $templateId) {
                    $this->model->lockTemplate($templateId);
                    if ($this->templateIsActive($templateId)) {
                        return ['success' => false, 'message' => '해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.'];
                    }
                }

                $stepName = trim((string) ($data['step_name'] ?? $existing['step_name']));
                $basic = $this->validatePayload($newTemplateId, $stepName, array_merge($existing, $data));
                if (!$basic['success']) {
                    return $basic;
                }
                $stepType = strtoupper(trim((string) ($data['step_type'] ?? $existing['step_type'] ?? 'APPROVAL')));
                if (!in_array($stepType, ['SUBMIT', 'APPROVAL', 'FINAL_APPROVAL'], true)) {
                    return ['success' => false, 'message' => '유효하지 않은 단계유형입니다.'];
                }
                $assignmentValidation = $this->validateAssignment(array_merge($existing, $data), $stepType);
                if (!$assignmentValidation['success']) {
                    return $assignmentValidation;
                }
                if ($this->model->existsStepName($newTemplateId, $stepName, $id)) {
                    return ['success' => false, 'message' => '이미 동일한 단계명이 존재합니다.'];
                }

                $merged = array_merge($existing, $data);
                $merged['template_id'] = $newTemplateId;
                $merged['step_name'] = $stepName;
                $merged['step_type'] = $stepType;
                if ($stepType === 'SUBMIT') {
                    $merged['role_id'] = null;
                    $merged['approver_id'] = null;
                }
                $merged['sort_no'] = $newTemplateId === $oldTemplateId
                    ? (int) $existing['sort_no']
                    : $this->model->nextSortNoForTemplate($newTemplateId);
                $merged['updated_by'] = ActorHelper::user();
                $ok = $this->model->update($id, $merged);
                $this->normalizeLocked($oldTemplateId, $merged['updated_by']);
                if ($newTemplateId !== $oldTemplateId) {
                    $this->normalizeLocked($newTemplateId, $merged['updated_by']);
                }
                return ['success' => $ok];
            });
        } catch (\Throwable $e) {
            $this->logger->error('결재단계 수정에 실패했습니다.', ['event_code' => 'APPROVAL_TEMPLATE_STEP_UPDATE_FAILED', 'result' => 'FAILED', 'service' => self::class, 'action' => 'approval_template_step.update', 'target_type' => 'APPROVAL_TEMPLATE_STEP', 'target_id' => $id, 'error_code'=>get_class($e),'error'=>$e]);
            return ['success' => false, 'message' => '결재단계 수정 중 오류가 발생했습니다.'];
        }
    }

    public function delete(string $id): bool
    {
        return $this->logged('APPROVAL_TEMPLATE_STEP_DELETE', 'delete', ['step_id' => $id], fn(): bool => $this->deleteInternal($id));
    }

    private function deleteInternal(string $id): bool
    {
        try {
            return $this->transaction(function () use ($id): bool {
                $existing = $this->model->getById($id);
                if (!$existing) {
                    return false;
                }
                $templateId = (string) $existing['template_id'];
                $this->model->lockTemplate($templateId);
                if ($this->templateIsActive($templateId)) {
                    throw new \DomainException('해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.');
                }
                if (!$this->model->delete($id)) {
                    return false;
                }
                $this->normalizeLocked($templateId, ActorHelper::user());
                return true;
            });
        } catch (\Throwable $e) {
            $this->logger->error('결재단계 삭제에 실패했습니다.', ['event_code'=>'APPROVAL_TEMPLATE_STEP_DELETE_FAILED','result'=>'FAILED','error_code'=>get_class($e),'error'=>$e]);
            return false;
        }
    }

    private function validateAssignment(array $data, string $stepType): array
    {
        if ($stepType === 'SUBMIT') {
            return ['success' => true, 'message' => ''];
        }
        $roleId = trim((string) ($data['role_id'] ?? ''));
        $approverId = trim((string) ($data['approver_id'] ?? ''));
        if ($roleId === '' && $approverId === '') {
            return ['success' => false, 'message' => '승인 단계에는 결재자 역할 또는 특정 결재자가 필요합니다.'];
        }
        if ($roleId === '') {
            $eligibility = $this->employees->userEligibility($approverId);
        } elseif ($approverId === '') {
            $eligible = $this->employees->hasEligibleUserForRole($roleId);
            $eligibility = ['eligible' => $eligible, 'message' => $eligible ? '' : '해당 역할에 현재 적격 결재자가 없습니다.'];
        } else {
            $eligibility = $this->employees->userEligibilityForRole($approverId, $roleId);
        }
        return [
            'success' => (bool) ($eligibility['eligible'] ?? false),
            'message' => (string) ($eligibility['message'] ?? '특정 결재자의 적격 여부를 확인할 수 없습니다.'),
        ];
    }

    public function reorder(string $templateId, array $changes): bool
    {
        return $this->logged('APPROVAL_TEMPLATE_STEP_REORDER', 'reorder', ['template_id' => $templateId, 'change_count' => count($changes)], fn(): bool => $this->reorderInternal($templateId, $changes));
    }

    private function reorderInternal(string $templateId, array $changes): bool
    {
        return $this->transaction(function () use ($templateId, $changes): bool {
            $this->model->lockTemplate($templateId);
            if ($this->templateIsActive($templateId)) {
                throw new \DomainException('해당 템플릿의 결재흐름을 변경하려면 해당 템플릿을 먼저 비활성화해 주세요.');
            }
            $rows = $this->model->getAllForTemplate($templateId, true);
            $byId = [];
            foreach ($rows as $row) {
                $byId[(string) $row['id']] = $row;
            }

            $requested = [];
            foreach ($changes as $index => $change) {
                $id = trim((string) ($change['id'] ?? ''));
                $changeTemplateId = trim((string) ($change['template_id'] ?? $templateId));
                if ($id === '' || isset($requested[$id]) || !isset($byId[$id])
                    || $changeTemplateId !== $templateId) {
                    throw new \InvalidArgumentException('다른 템플릿의 결재단계가 포함되어 있습니다.');
                }
                $requested[$id] = (int) ($change['sort_no'] ?? $change['newSortNo'] ?? ($index + 1));
            }
            if (count($requested) !== count($rows)) {
                throw new \InvalidArgumentException('선택한 템플릿의 전체 결재단계 순서가 필요합니다.');
            }
            asort($requested, SORT_NUMERIC);

            $orderedIds = array_keys($requested);
            $orderedRows = array_map(static fn (string $id): array => $byId[$id], $orderedIds);
            $lastIndex = count($orderedRows) - 1;
            foreach ($orderedRows as $index => $row) {
                $expectedType = $index === 0
                    ? 'SUBMIT'
                    : ($index === $lastIndex ? 'FINAL_APPROVAL' : 'APPROVAL');
                if (strtoupper((string) ($row['step_type'] ?? '')) !== $expectedType) {
                    throw new \InvalidArgumentException('발의와 최종승인 단계의 위치는 변경할 수 없습니다. 중간 승인 단계만 순서를 변경해 주세요.');
                }
            }
            $this->applyOrderLocked($templateId, $orderedIds, ActorHelper::user());
            return true;
        });
    }

    private function normalizeLocked(string $templateId, string $actor): void
    {
        $rows = $this->model->getAllForTemplate($templateId, true);
        $this->applyOrderLocked($templateId, array_map(static fn (array $row): string => (string) $row['id'], $rows), $actor);
        $this->model->normalizeActiveExecutionTypes($templateId, $actor);
    }

    private function applyOrderLocked(string $templateId, array $orderedIds, string $actor): void
    {
        $temporaryBase = 1000000 + count($orderedIds);
        foreach ($orderedIds as $index => $id) {
            $this->model->setSortNo($id, $templateId, $temporaryBase + $index + 1, $actor);
        }
        foreach ($orderedIds as $index => $id) {
            $this->model->setSortNo($id, $templateId, $index + 1, $actor);
        }
    }

    private function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function templateIsActive(string $templateId): bool
    {
        $template = $this->templates->getById($templateId);
        return $template && (int) ($template['is_active'] ?? 0) === 1;
    }

    private function validatePayload(string $templateId, string $stepName, array $data): array
    {
        if ($templateId === '' || !$this->templates->getById($templateId)) {
            return ['success' => false, 'message' => '결재템플릿을 찾을 수 없습니다.'];
        }
        if ($stepName === '' || mb_strlen($stepName) > 100) {
            return ['success' => false, 'message' => '단계명은 필수이며 100자 이내로 입력해 주세요.'];
        }
        $active = (int) ($data['is_active'] ?? 1);
        if (!in_array($active, [0, 1], true)) {
            return ['success' => false, 'message' => '활성 상태 값이 올바르지 않습니다.'];
        }
        return ['success' => true, 'message' => ''];
    }

    private function logged(string $eventCode, string $action, array $context, callable $operation): mixed
    {
        try {
            $result = $operation();
            $blocked = $result === false || (is_array($result) && array_key_exists('success', $result) && !$result['success']);
            $this->logger->{$blocked ? 'warning' : 'info'}($blocked ? '결재단계 업무 처리가 차단되었습니다.' : '결재단계 업무 처리를 완료했습니다.', ['event_code' => $eventCode . ($blocked ? '_BLOCKED' : ''), 'result' => $blocked ? 'BLOCKED' : 'SUCCESS', 'service' => self::class, 'action' => $action] + $context);
            return $result;
        } catch (\InvalidArgumentException|\DomainException $exception) {
            $this->logger->warning('결재단계 업무 처리가 차단되었습니다.', ['event_code' => $eventCode . '_BLOCKED', 'result' => 'BLOCKED', 'service' => self::class, 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception;
        } catch (\Throwable $exception) {
            $this->logger->error('결재단계 업무 처리에 실패했습니다.', ['event_code' => $eventCode . '_FAILED', 'result' => 'FAILED', 'service' => self::class, 'action' => $action, 'error_code' => get_class($exception), 'error' => $exception] + $context); throw $exception;
        }
    }
}
