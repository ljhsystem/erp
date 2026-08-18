<?php

declare(strict_types=1);

use App\Repositories\Institution\PersonnelActionRepository;
use App\Services\Institution\PersonnelActionApplyService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$repository = new PersonnelActionRepository($pdo);
$apply = new PersonnelActionApplyService($pdo);
$actor = 'SYSTEM:PERSONNEL_ACTION_POLICY_TEST';
$today = date('Y-m-d');
$result = [];

$pdo->beginTransaction();
try {
    $employees = $pdo->query("SELECT * FROM user_employees WHERE employment_status='ACTIVE' AND position_id IS NOT NULL ORDER BY sort_no LIMIT 2 FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
    if (count($employees) < 2) throw new RuntimeException('적용 정책 테스트용 직원이 부족합니다.');
    $employeeId = (string) $employees[0]['id'];

    $createApproved = static function (string $actionType, string $employeeId, array $changes) use ($repository, $actor, $today): string {
        $actionId = UuidHelper::generate();
        $targetId = UuidHelper::generate();
        $repository->insertAction([
            'id' => $actionId, 'sort_no' => $repository->nextSortNo(), 'action_no' => 'TEST-POL-' . substr($actionId, 0, 8),
            'action_name' => '적용 정책 롤백 테스트', 'action_type_code' => $actionType,
            'issued_date' => $today, 'action_date' => $today, 'business_status' => 'APPROVED',
            'approved_at' => date('Y-m-d H:i:s'), 'created_by' => $actor,
        ]);
        $repository->insertTarget(['id' => $targetId, 'personnel_action_id' => $actionId, 'employee_id' => $employeeId, 'sort_no' => 1, 'application_status' => 'PENDING', 'created_by' => $actor]);
        foreach ($changes as $index => $change) {
            $repository->insertChange($change + [
                'id' => UuidHelper::generate(), 'personnel_action_target_id' => $targetId,
                'sort_no' => $index + 1, 'effective_date' => $today,
                'before_display_snapshot' => '변경 전', 'after_display_snapshot' => '변경 후', 'created_by' => $actor,
            ]);
        }
        return $actionId;
    };

    $repository->updateEmployee($employeeId, ['employment_status' => 'PENDING_HIRE']);
    $hireId = $createApproved('HIRE', $employeeId, [
        [
            'change_type_code' => 'EMPLOYMENT_STATUS', 'before_employment_status' => 'PENDING_HIRE', 'after_employment_status' => 'ACTIVE',
            'effective_date' => date('Y-m-d', strtotime('-3 days')),
        ],
        [
            'change_type_code' => 'HIRE_DATE', 'date_kind' => 'ACTUAL', 'before_date' => $employees[0]['real_hire_date'],
            'after_date' => date('Y-m-d', strtotime('-3 days')), 'effective_date' => date('Y-m-d', strtotime('-3 days')),
        ],
    ]);
    $apply->apply($hireId, $actor);
    $result['pending_hire_to_active'] = $repository->employee($employeeId)['employment_status'] === 'ACTIVE';

    $leaveType = (string) $pdo->query("SELECT code FROM system_codes WHERE code_group='EMPLOYEE_LEAVE_TYPE' AND is_active=1 ORDER BY sort_no LIMIT 1")->fetchColumn();
    $leaveId = $createApproved('LEAVE_OF_ABSENCE', $employeeId, [
        ['change_type_code' => 'LEAVE', 'leave_type_code' => $leaveType, 'leave_start_date' => date('Y-m-d', strtotime('-2 days')), 'leave_planned_end_date' => date('Y-m-d', strtotime('+30 days')), 'effective_date' => date('Y-m-d', strtotime('-2 days'))],
        ['change_type_code' => 'EMPLOYMENT_STATUS', 'before_employment_status' => 'ACTIVE', 'after_employment_status' => 'ON_LEAVE', 'effective_date' => date('Y-m-d', strtotime('-2 days'))],
    ]);
    $apply->apply($leaveId, $actor);
    $leavePeriod = $repository->activeLeave($employeeId);
    $result['leave'] = $repository->employee($employeeId)['employment_status'] === 'ON_LEAVE' && $leavePeriod !== null;

    $returnId = $createApproved('REINSTATEMENT', $employeeId, [
        ['change_type_code' => 'RETURN_FROM_LEAVE', 'leave_period_id' => $leavePeriod['id'], 'leave_actual_end_date' => date('Y-m-d', strtotime('-1 day')), 'effective_date' => date('Y-m-d', strtotime('-1 day'))],
        ['change_type_code' => 'EMPLOYMENT_STATUS', 'before_employment_status' => 'ON_LEAVE', 'after_employment_status' => 'ACTIVE', 'effective_date' => date('Y-m-d', strtotime('-1 day'))],
    ]);
    $apply->apply($returnId, $actor);
    $result['return_from_leave'] = $repository->employee($employeeId)['employment_status'] === 'ACTIVE' && $repository->activeLeave($employeeId) === null;

    $position = $pdo->prepare('SELECT id FROM user_positions WHERE id<>:current AND is_active=1 ORDER BY sort_no LIMIT 1');
    $position->execute([':current' => $employees[0]['position_id']]);
    $newPositionId = (string) $position->fetchColumn();
    $positionId = $createApproved('POSITION_CHANGE', $employeeId, [[
        'change_type_code' => 'POSITION', 'before_position_id' => $employees[0]['position_id'], 'after_position_id' => $newPositionId,
    ]]);
    $apply->apply($positionId, $actor);
    $currentPosition = $repository->currentPeriod('institution_job_assignments_position_histories', 'employee_id', $employeeId, false);
    $result['position_master_and_period'] = $repository->employee($employeeId)['position_id'] === $newPositionId && $currentPosition['position_id'] === $newPositionId;

    $retirementId = $createApproved('RETIREMENT', $employeeId, [
        ['change_type_code' => 'RETIRE_DATE', 'date_kind' => 'ACTUAL', 'before_date' => $repository->employee($employeeId)['real_retire_date'], 'after_date' => $today],
        ['change_type_code' => 'EMPLOYMENT_STATUS', 'before_employment_status' => 'ACTIVE', 'after_employment_status' => 'RETIRED'],
    ]);
    $apply->apply($retirementId, $actor);
    $result['retired'] = $repository->employee($employeeId)['employment_status'] === 'RETIRED';

    $conflictEmployee = $employees[1];
    $wrongDepartment = $pdo->prepare('SELECT id FROM user_departments WHERE id<>:current AND is_active=1 ORDER BY sort_no LIMIT 1');
    $wrongDepartment->execute([':current' => $conflictEmployee['department_id']]);
    $wrongDepartmentId = (string) $wrongDepartment->fetchColumn();
    $conflictId = $createApproved('DEPARTMENT_TRANSFER', (string) $conflictEmployee['id'], [[
        'change_type_code' => 'DEPARTMENT', 'before_department_id' => $wrongDepartmentId, 'after_department_id' => $conflictEmployee['department_id'],
    ]]);
    $conflictBlocked = false;
    try {
        $apply->apply($conflictId, $actor);
    } catch (RuntimeException $exception) {
        $conflictBlocked = str_contains($exception->getMessage(), '변경 전 값');
    }
    $result['before_state_conflict_blocked'] = $conflictBlocked;

    $pdo->rollBack();
    $result['rolled_back'] = true;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result['rolled_back'] = true;
    $result['error'] = $exception->getMessage();
}

$result['residue'] = (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE action_no LIKE 'TEST-POL-%'")->fetchColumn();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
