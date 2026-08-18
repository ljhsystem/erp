<?php

declare(strict_types=1);

use App\Models\Approval\ApprovalInboxModel;
use App\Repositories\Institution\PersonnelActionRepository;
use App\Services\Approval\ApprovalWorkflowService;
use App\Services\Institution\PersonnelActionApplyService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$repository = new PersonnelActionRepository($pdo);
$workflow = new ApprovalWorkflowService($pdo);
$inbox = new ApprovalInboxModel($pdo);
$apply = new PersonnelActionApplyService($pdo);
$actor = 'SYSTEM:PERSONNEL_ACTION_APPROVAL_TEST';
$result = [];

$pdo->beginTransaction();
try {
    $approverId = (string) $pdo->query("SELECT approver_id FROM user_approval_template_steps WHERE template_id='7540cbd0-679b-4bd4-919c-afede9622d6c' AND step_type='FINAL_APPROVAL' AND is_active=1 LIMIT 1")->fetchColumn();
    $requester = $pdo->prepare("SELECT u.id, e.employee_name FROM auth_users u JOIN user_employees e ON e.user_id=u.id WHERE u.id<>:approver AND u.approved=1 AND u.is_active=1 ORDER BY e.sort_no LIMIT 1");
    $requester->execute([':approver' => $approverId]);
    $requester = $requester->fetch(PDO::FETCH_ASSOC);
    if (!$requester || $approverId === '') {
        throw new RuntimeException('발의자 또는 최종결재자 테스트 계정을 찾을 수 없습니다.');
    }

    $employees = $pdo->query("SELECT * FROM user_employees WHERE employment_status='ACTIVE' AND department_id IS NOT NULL ORDER BY sort_no LIMIT 2 FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    if (count($employees) < 2) {
        throw new RuntimeException('복수 대상자 테스트용 재직 직원이 부족합니다.');
    }

    $actionId = UuidHelper::generate();
    $repository->insertAction([
        'id' => $actionId, 'sort_no' => $repository->nextSortNo(),
        'action_no' => 'TEST-WF-' . substr($actionId, 0, 8), 'action_name' => '2단계 결재 롤백 테스트',
        'action_type_code' => 'DEPARTMENT_TRANSFER', 'issued_date' => date('Y-m-d'),
        'action_date' => date('Y-m-d'), 'business_status' => 'DRAFT', 'created_by' => $actor,
    ]);
    foreach ($employees as $index => $employee) {
        $department = $pdo->prepare('SELECT id,dept_name FROM user_departments WHERE id<>:current AND is_active=1 ORDER BY sort_no LIMIT 1');
        $department->execute([':current' => $employee['department_id']]);
        $after = $department->fetch(PDO::FETCH_ASSOC);
        $targetId = UuidHelper::generate();
        $repository->insertTarget(['id' => $targetId, 'personnel_action_id' => $actionId, 'employee_id' => $employee['id'], 'sort_no' => $index + 1, 'application_status' => 'PENDING', 'created_by' => $actor]);
        $repository->insertChange([
            'id' => UuidHelper::generate(), 'personnel_action_target_id' => $targetId, 'sort_no' => 1,
            'change_type_code' => 'DEPARTMENT', 'effective_date' => date('Y-m-d'),
            'before_department_id' => $employee['department_id'], 'after_department_id' => $after['id'],
            'before_display_snapshot' => '변경 전 부서', 'after_display_snapshot' => $after['dept_name'], 'created_by' => $actor,
        ]);
    }

    $submission = $workflow->submit('PERSONNEL_ACTION', $actionId, (string) $requester['id'], $actor);
    $repository->updateWorkflow($actionId, 'APPROVAL_PENDING', (string) $submission['request_id'], $actor);
    $steps = $repository->approvalSteps((string) $submission['request_id']);
    $finalStep = $steps[1] ?? null;
    $approverRows = $inbox->page($approverId, 'actionable', ['start' => 0, 'length' => 100])['rows'];
    $requesterRows = $inbox->page((string) $requester['id'], 'actionable', ['start' => 0, 'length' => 100])['rows'];
    $otherActBlocked = false;
    try {
        $workflow->act((string) $finalStep['id'], 'PERSONNEL_ACTION', 'approved', null, (string) $requester['id'], $actor);
    } catch (RuntimeException) {
        $otherActBlocked = true;
    }
    $approval = $workflow->act((string) $finalStep['id'], 'PERSONNEL_ACTION', 'approved', null, $approverId, $actor);
    $repository->updateWorkflow($actionId, 'APPROVED', (string) $submission['request_id'], $actor);
    $applyResult = $apply->apply($actionId, $actor);
    $secondApply = $apply->apply($actionId, $actor);
    $appliedTargets = $repository->targets($actionId);

    $result['approval'] = [
        'request_id' => $submission['request_id'],
        'step_count' => count($steps),
        'submit_step' => [$steps[0]['step_type'] ?? null, $steps[0]['status'] ?? null, $steps[0]['acted_by'] ?? null],
        'final_step' => [$finalStep['step_type'] ?? null, $finalStep['approver_id'] ?? null, $finalStep['role_id'] ?? null],
        'designated_inbox_visible' => count(array_filter($approverRows, static fn(array $row): bool => (string) $row['request_id'] === (string) $submission['request_id'])) === 1,
        'other_user_inbox_hidden' => count(array_filter($requesterRows, static fn(array $row): bool => (string) $row['request_id'] === (string) $submission['request_id'])) === 0,
        'other_user_act_blocked' => $otherActBlocked,
        'final_state' => $approval['state'],
        'target_count' => count($appliedTargets),
        'all_targets_applied' => count(array_filter($appliedTargets, static fn(array $row): bool => $row['application_status'] === 'APPLIED')) === 2,
        'all_applied_by' => count(array_filter($appliedTargets, static fn(array $row): bool => $row['applied_by'] === $actor)) === 2,
        'apply' => $applyResult,
        'idempotent_apply' => $secondApply['already_applied'] ?? false,
    ];

    $workflowActionId = UuidHelper::generate();
    $repository->insertAction([
        'id' => $workflowActionId, 'sort_no' => $repository->nextSortNo(),
        'action_no' => 'TEST-WF-' . substr($workflowActionId, 0, 8), 'action_name' => '회수·재상신·반려 롤백 테스트',
        'action_type_code' => 'OTHER', 'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
        'business_status' => 'DRAFT', 'created_by' => $actor,
    ]);
    $withdrawSubmission = $workflow->submit('PERSONNEL_ACTION', $workflowActionId, (string) $requester['id'], $actor);
    $withdrawn = $workflow->withdraw((string) $withdrawSubmission['request_id'], 'PERSONNEL_ACTION', (string) $requester['id'], $actor);
    $repository->updateWorkflow($workflowActionId, 'DRAFT', null, $actor);
    $resubmission = $workflow->submit('PERSONNEL_ACTION', $workflowActionId, (string) $requester['id'], $actor);
    $repository->updateWorkflow($workflowActionId, 'APPROVAL_PENDING', (string) $resubmission['request_id'], $actor);
    $rejectSteps = $repository->approvalSteps((string) $resubmission['request_id']);
    $rejection = $workflow->act((string) $rejectSteps[1]['id'], 'PERSONNEL_ACTION', 'rejected', '통합테스트 반려', $approverId, $actor);
    $repository->updateWorkflow($workflowActionId, 'DRAFT', (string) $resubmission['request_id'], $actor);
    $result['workflow_states'] = [
        'withdrawn' => $withdrawn['status'] === 'pending',
        'resubmitted_new_request' => $withdrawSubmission['request_id'] !== $resubmission['request_id'],
        'rejected' => $rejection['state'] === 'REJECTED',
    ];

    $trashActionId = UuidHelper::generate();
    $repository->insertAction([
        'id' => $trashActionId, 'sort_no' => $repository->nextSortNo(),
        'action_no' => 'TEST-WF-' . substr($trashActionId, 0, 8), 'action_name' => '휴지통 롤백 테스트',
        'action_type_code' => 'OTHER', 'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
        'business_status' => 'DRAFT', 'created_by' => $actor,
    ]);
    $result['trash_restore'] = [
        'deleted' => $repository->softDelete($trashActionId, $actor),
        'restored' => $repository->restore($trashActionId, $actor),
    ];

    $futureActionId = UuidHelper::generate();
    $repository->insertAction([
        'id' => $futureActionId, 'sort_no' => $repository->nextSortNo(),
        'action_no' => 'TEST-WF-' . substr($futureActionId, 0, 8), 'action_name' => '미래 효력일 롤백 테스트',
        'action_type_code' => 'OTHER', 'issued_date' => date('Y-m-d'),
        'action_date' => date('Y-m-d', strtotime('+1 day')), 'business_status' => 'APPROVED',
        'approved_at' => date('Y-m-d H:i:s'), 'created_by' => $actor,
    ]);
    $futureBlocked = false;
    try {
        $apply->apply($futureActionId, $actor);
    } catch (RuntimeException $exception) {
        $futureBlocked = str_contains($exception->getMessage(), '효력일');
    }
    $result['future_effective_date_waits'] = $futureBlocked
        && $repository->find($futureActionId)['business_status'] === 'APPROVED';

    $pdo->rollBack();
    $result['rolled_back'] = true;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $result['rolled_back'] = true;
    $result['error'] = $exception->getMessage();
}

$result['residue'] = [
    'actions' => (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE action_no LIKE 'TEST-WF-%'")->fetchColumn(),
    'requests' => (int) $pdo->query("SELECT COUNT(*) FROM user_approval_requests WHERE created_by='SYSTEM:PERSONNEL_ACTION_APPROVAL_TEST'")->fetchColumn(),
    'steps' => (int) $pdo->query("SELECT COUNT(*) FROM user_approval_request_steps WHERE created_by='SYSTEM:PERSONNEL_ACTION_APPROVAL_TEST'")->fetchColumn(),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
