<?php

declare(strict_types=1);

use App\Services\Institution\PersonnelActionService;
use App\Services\System\DataTableColumnMetaService;
use App\Services\System\EmployeeService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$user = $pdo->query("SELECT u.id FROM auth_users u JOIN user_employees e ON e.user_id=u.id WHERE u.approved=1 AND u.is_active=1 ORDER BY e.sort_no LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$employee = $pdo->query("SELECT id,user_id,employee_name,department_id,position_id,real_hire_date,doc_hire_date FROM user_employees WHERE employment_status='ACTIVE' AND department_id IS NOT NULL AND position_id IS NOT NULL ORDER BY sort_no LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$department = $pdo->prepare('SELECT id FROM user_departments WHERE is_active=1 AND id<>:id ORDER BY sort_no LIMIT 1');
$department->execute([':id' => $employee['department_id'] ?? '']);
$departmentId = (string) $department->fetchColumn();
$employees = $pdo->query("SELECT id,department_id,position_id FROM user_employees WHERE employment_status='ACTIVE' AND department_id IS NOT NULL AND position_id IS NOT NULL ORDER BY sort_no LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
$position = $pdo->prepare('SELECT id FROM user_positions WHERE is_active=1 AND id<>:id ORDER BY sort_no LIMIT 1');
$position->execute([':id' => $employee['position_id'] ?? '']);
$positionId = (string) $position->fetchColumn();
$pickerRows = $employee ? (new EmployeeService($pdo))->searchPicker((string) $employee['employee_name']) : [];
$pickerMatch = array_values(array_filter($pickerRows, static fn(array $row): bool => (string) ($row['id'] ?? '') === (string) ($employee['id'] ?? '')));
$pickerEmployeeId = (string) ($pickerMatch[0]['id'] ?? '');

if (!$user || !$employee || count($employees) < 2 || $departmentId === '' || $positionId === '' || $pickerEmployeeId === '') {
    throw new RuntimeException('P0 저장 Fixture 기준정보가 부족합니다.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['user'] = ['id' => $user['id']];
$_SESSION['auth_state'] = ['user_id' => $user['id'], 'status' => 'NORMAL'];

$before = (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE action_no LIKE 'P0-%'")->fetchColumn();
$result = [];
$targetEmployeeFk = $pdo->query("SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_personnel_actions_targets' AND COLUMN_NAME='employee_id' AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$result['target_employee_fk'] = $targetEmployeeFk;
$pdo->beginTransaction();
try {
    $service = new PersonnelActionService($pdo);
    $saved = $service->save([
        'action_type_code' => 'DEPARTMENT_TRANSFER',
        'action_name' => 'P0 신규등록 저장 검증',
        'issued_date' => date('Y-m-d'),
        'action_date' => date('Y-m-d'),
        'action_reason' => 'P0 원인 추적',
        'note' => '롤백 Fixture',
        'business_status' => 'DRAFT',
        'targets' => [[
            'employee_id' => $pickerEmployeeId,
            'individual_reason' => '부서이동',
            'changes' => [[
                'change_type_code' => 'DEPARTMENT',
                'before_display_snapshot' => '클라이언트 위조값',
                'after_department_id' => $departmentId,
            ]],
        ]],
    ]);
    $id = (string) ($saved['data']['id'] ?? '');
    $result['save'] = $saved;
    $result['stored'] = [
        'header' => (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE id=" . $pdo->quote($id))->fetchColumn(),
        'target' => (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions_targets WHERE personnel_action_id=" . $pdo->quote($id))->fetchColumn(),
        'change' => (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions_changes c JOIN institution_personnel_actions_targets t ON t.id=c.personnel_action_target_id WHERE t.personnel_action_id=" . $pdo->quote($id))->fetchColumn(),
        'before_snapshot' => $pdo->query("SELECT c.before_display_snapshot FROM institution_personnel_actions_changes c JOIN institution_personnel_actions_targets t ON t.id=c.personnel_action_target_id WHERE t.personnel_action_id=" . $pdo->quote($id) . ' LIMIT 1')->fetchColumn(),
    ];
    $result['detail'] = $service->detail($id)['data'];
    $result['picker_employee_id'] = [
        'value' => $pickerEmployeeId,
        'matches_employee_ssot' => $pickerEmployeeId === (string) $employee['id'],
        'differs_from_auth_user_id' => $pickerEmployeeId !== (string) $employee['user_id'],
    ];
    $storedTargetEmployeeId = (string) $pdo->query("SELECT employee_id FROM institution_personnel_actions_targets WHERE personnel_action_id=" . $pdo->quote($id))->fetchColumn();
    $result['target_employee_id_ssot'] = $storedTargetEmployeeId === $pickerEmployeeId;
    $result['detail_employee_restore_id'] = (string) ($result['detail']['targets'][0]['employee_id'] ?? '') === $pickerEmployeeId
        && (string) ($result['detail']['targets'][0]['id'] ?? '') !== $pickerEmployeeId;
    $secondDepartment = $pdo->prepare('SELECT id FROM user_departments WHERE is_active=1 AND id<>:id ORDER BY sort_no LIMIT 1');
    $secondDepartment->execute([':id' => $employees[1]['department_id']]);
    $secondDepartmentId = (string) $secondDepartment->fetchColumn();
    $twoTarget = $service->save([
        'action_type_code' => 'DEPARTMENT_TRANSFER', 'action_name' => 'P0 복수 대상 저장',
        'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
        'targets' => [
            ['employee_id' => $employees[0]['id'], 'changes' => [['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId]]],
            ['employee_id' => $employees[1]['id'], 'changes' => [['change_type_code' => 'DEPARTMENT', 'after_department_id' => $secondDepartmentId]]],
        ],
    ]);
    $twoTargetId = (string) $twoTarget['data']['id'];
    $result['two_targets'] = count($service->detail($twoTargetId)['data']['targets']) === 2;
    $multiChange = $service->save([
        'action_type_code' => 'OTHER', 'action_name' => 'P0 복수 변경 저장',
        'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
        'targets' => [[
            'employee_id' => $employee['id'],
            'changes' => [
                ['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId],
                ['change_type_code' => 'POSITION', 'after_position_id' => $positionId],
            ],
        ]],
    ]);
    $multiChangeId = (string) $multiChange['data']['id'];
    $result['multiple_changes'] = count($service->detail($multiChangeId)['data']['targets'][0]['changes'] ?? []) === 2;
    $originalSortNos = $pdo->query('SELECT id,sort_no FROM institution_personnel_actions WHERE id IN (' . implode(',', array_map([$pdo, 'quote'], [$multiChangeId, $twoTargetId, $id])) . ')')->fetchAll(PDO::FETCH_KEY_PAIR);
    $result['reorder'] = $service->reorder([
        ['id' => $multiChangeId, 'newSortNo' => (int) $originalSortNos[$id]],
        ['id' => $twoTargetId, 'newSortNo' => (int) $originalSortNos[$twoTargetId]],
        ['id' => $id, 'newSortNo' => (int) $originalSortNos[$multiChangeId]],
    ]);
    $reordered = $pdo->query('SELECT id,sort_no FROM institution_personnel_actions WHERE id IN (' . implode(',', array_map([$pdo, 'quote'], [$multiChangeId, $twoTargetId, $id])) . ')')->fetchAll(PDO::FETCH_KEY_PAIR);
    $result['reorder_stored'] = (int) ($reordered[$multiChangeId] ?? 0) === (int) $originalSortNos[$id]
        && (int) ($reordered[$twoTargetId] ?? 0) === (int) $originalSortNos[$twoTargetId]
        && (int) ($reordered[$id] ?? 0) === (int) $originalSortNos[$multiChangeId];
    $result['table_meta'] = [
        'supported' => (new DataTableColumnMetaService($pdo))->hasDomain('personnel-action'),
        'columns' => count((new DataTableColumnMetaService($pdo))->columnsForDomain('personnel-action')),
    ];
    try {
        $service->save([
            'action_type_code' => 'DEPARTMENT_TRANSFER', 'action_name' => 'P0 동일값 오류 재현',
            'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
            'targets' => [['employee_id' => $employee['id'], 'changes' => [[
                'change_type_code' => 'DEPARTMENT', 'after_department_id' => $employee['department_id'],
            ]]]],
        ]);
    } catch (Throwable $sameValueException) {
        $result['same_value_exception'] = [
            'class' => get_class($sameValueException),
            'message' => $sameValueException->getMessage(),
            'code' => $sameValueException->getCode(),
        ];
    }
    try {
        $service->save([
            'action_type_code' => 'POSITION_CHANGE',
            'action_name' => 'P0 동일 직위 오류 재현',
            'issued_date' => date('Y-m-d'),
            'action_date' => date('Y-m-d'),
            'targets' => [[
                'employee_id' => $pickerEmployeeId,
                'changes' => [[
                    'change_type_code' => 'POSITION',
                    'after_position_id' => $employee['position_id'],
                ]],
            ]],
        ]);
        $result['same_position_value_exception'] = false;
    } catch (Throwable $samePositionValueException) {
        $result['same_position_value_exception'] = [
            'class' => get_class($samePositionValueException),
            'message' => $samePositionValueException->getMessage(),
            'code' => $samePositionValueException->getCode(),
        ];
    }
    $positionChange = $service->save([
        'action_type_code' => 'POSITION_CHANGE',
        'action_name' => 'P0 Picker 직원 정상 직위변경',
        'issued_date' => date('Y-m-d'),
        'action_date' => date('Y-m-d'),
        'targets' => [[
            'employee_id' => $pickerEmployeeId,
            'changes' => [[
                'change_type_code' => 'POSITION',
                'after_position_id' => $positionId,
            ]],
        ]],
    ]);
    $positionChangeId = (string) ($positionChange['data']['id'] ?? '');
    $result['picker_position_change_saved'] = $positionChangeId !== '';
    $result['picker_position_target_ssot'] = (string) $pdo->query("SELECT employee_id FROM institution_personnel_actions_targets WHERE personnel_action_id=" . $pdo->quote($positionChangeId))->fetchColumn() === $pickerEmployeeId;
    $result['selected_delete'] = $service->delete($positionChangeId);
    $result['selected_delete_stored'] = (bool) $pdo->query("SELECT deleted_at IS NOT NULL FROM institution_personnel_actions WHERE id=" . $pdo->quote($positionChangeId))->fetchColumn();
    foreach ([
        'missing_employee' => [
            'action_type_code' => 'POSITION_CHANGE', 'action_name' => 'P0 존재하지 않는 직원', 'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
            'targets' => [['employee_id' => '00000000-0000-0000-0000-000000000000', 'changes' => [['change_type_code' => 'POSITION', 'after_position_id' => $positionId]]]],
        ],
        'invalid_change_type' => [
            'action_type_code' => 'OTHER', 'action_name' => 'P0 잘못된 변경구분', 'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
            'targets' => [['employee_id' => $employee['id'], 'changes' => [['change_type_code' => 'INVALID']]]],
        ],
        'disallowed_change_type' => [
            'action_type_code' => 'DEPARTMENT_TRANSFER', 'action_name' => 'P0 허용조합 오류', 'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
            'targets' => [['employee_id' => $employee['id'], 'changes' => [['change_type_code' => 'POSITION', 'after_position_id' => $positionId]]]],
        ],
        'duplicate_target' => [
            'action_type_code' => 'DEPARTMENT_TRANSFER', 'action_name' => 'P0 중복 대상', 'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
            'targets' => [
                ['employee_id' => $employee['id'], 'changes' => [['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId]]],
                ['employee_id' => $employee['id'], 'changes' => [['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId]]],
            ],
        ],
        'duplicate_change_type' => [
            'action_type_code' => 'OTHER', 'action_name' => 'P0 중복 변경', 'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
            'targets' => [['employee_id' => $employee['id'], 'changes' => [
                ['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId],
                ['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId],
            ]]],
        ],
        'before_hire_date' => [
            'action_type_code' => 'DEPARTMENT_TRANSFER', 'action_name' => 'P0 입사일 이전 발령 차단',
            'issued_date' => date('Y-m-d'),
            'action_date' => date('Y-m-d', strtotime('-1 day', strtotime((string) ($employee['real_hire_date'] ?: $employee['doc_hire_date'])))),
            'targets' => [['employee_id' => $employee['id'], 'changes' => [['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId]]]],
        ],
    ] as $case => $payload) {
        try {
            $service->save($payload);
            $result[$case] = false;
        } catch (InvalidArgumentException $validationException) {
            $result[$case] = ['blocked' => true, 'message' => $validationException->getMessage()];
        }
    }

    $atomicBefore = (int) $pdo->query('SELECT COUNT(*) FROM institution_personnel_actions')->fetchColumn();
    try {
        $service->save([
            'action_type_code' => 'DEPARTMENT_TRANSFER', 'action_name' => 'P0 원자성 실패',
            'issued_date' => date('Y-m-d'), 'action_date' => date('Y-m-d'),
            'targets' => [
                ['employee_id' => $employees[0]['id'], 'changes' => [['change_type_code' => 'DEPARTMENT', 'after_department_id' => $departmentId]]],
                ['employee_id' => $employees[1]['id'], 'changes' => [['change_type_code' => 'INVALID']]],
            ],
        ]);
    } catch (InvalidArgumentException) {
        $atomicAfter = (int) $pdo->query('SELECT COUNT(*) FROM institution_personnel_actions')->fetchColumn();
        $result['atomic_failure_rollback'] = $atomicBefore === $atomicAfter;
    }
} catch (Throwable $exception) {
    $result['exception'] = [
        'class' => get_class($exception),
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'trace' => $exception->getTraceAsString(),
    ];
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

$after = (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE action_no LIKE 'P0-%'")->fetchColumn();
$result['rollback'] = ['before' => $before, 'after' => $after, 'clean' => $before === $after];
$result['employee_id_ssot_passed'] = !isset($result['exception'])
    && ($result['target_employee_fk']['REFERENCED_TABLE_NAME'] ?? '') === 'user_employees'
    && ($result['target_employee_fk']['REFERENCED_COLUMN_NAME'] ?? '') === 'id'
    && ($result['picker_employee_id']['matches_employee_ssot'] ?? false) === true
    && ($result['picker_employee_id']['differs_from_auth_user_id'] ?? false) === true
    && ($result['target_employee_id_ssot'] ?? false) === true
    && ($result['detail_employee_restore_id'] ?? false) === true
    && ($result['same_position_value_exception']['message'] ?? '') === '현재 직위와 다른 직위를 선택해 주세요.'
    && ($result['picker_position_change_saved'] ?? false) === true
    && ($result['picker_position_target_ssot'] ?? false) === true
    && ($result['selected_delete']['success'] ?? false) === true
    && ($result['selected_delete_stored'] ?? false) === true
    && ($result['missing_employee']['message'] ?? '') === '직원 정보를 찾을 수 없습니다.'
    && ($result['atomic_failure_rollback'] ?? false) === true
    && ($result['rollback']['clean'] ?? false) === true;
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
exit($result['employee_id_ssot_passed'] ? 0 : 1);
