<?php

declare(strict_types=1);

use App\Repositories\Institution\PersonnelActionRepository;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$repository = new PersonnelActionRepository($pdo);
$actor = 'SYSTEM:PERSONNEL_ACTION_SEARCH_TEST';
$prefix = 'TEST-SEARCH-';
$results = [];

$query = static function (array $filters = [], string $keyword = '') use ($repository): array {
    $request = ['start' => 0, 'length' => 50];
    if ($keyword !== '') $request['search'] = ['value' => $keyword];
    if ($filters !== []) $request['filters'] = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $repository->page($request);
};
$assert = static function (string $name, bool $passed, mixed $actual = null) use (&$results): void {
    $results[$name] = ['passed' => $passed, 'actual' => $actual];
    if (!$passed) throw new RuntimeException($name . ' 검증에 실패했습니다.');
};
$containsAction = static fn(array $page, string $actionId): bool => in_array($actionId, array_column($page['rows'] ?? [], 'id'), true);

$before = (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE action_no LIKE '{$prefix}%'")->fetchColumn();
$pdo->beginTransaction();
try {
    $employees = $pdo->query('SELECT id,employee_name,department_id,position_id FROM user_employees ORDER BY sort_no,id LIMIT 2 FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    if (count($employees) < 2) throw new RuntimeException('검색 Fixture에는 직원 2명이 필요합니다.');
    $approval = $pdo->query('SELECT id,status FROM user_approval_requests ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$approval) throw new RuntimeException('결재상태 검색에 사용할 기존 결재요청이 없습니다.');
    $departmentStmt = $pdo->prepare('SELECT id FROM user_departments WHERE id<>:id AND is_active=1 ORDER BY sort_no LIMIT 1');
    $departmentStmt->execute([':id' => $employees[0]['department_id']]);
    $afterDepartmentId = (string) $departmentStmt->fetchColumn();
    $positionStmt = $pdo->prepare('SELECT id FROM user_positions WHERE id<>:id AND is_active=1 ORDER BY sort_no LIMIT 1');
    $positionStmt->execute([':id' => $employees[1]['position_id']]);
    $afterPositionId = (string) $positionStmt->fetchColumn();
    if ($afterDepartmentId === '' || $afterPositionId === '') throw new RuntimeException('검색 Fixture에 사용할 대체 부서·직위가 없습니다.');
    $baseSort = $repository->nextSortNo();
    $actionIds = [UuidHelper::generate(), UuidHelper::generate()];
    foreach ($actionIds as $index => $actionId) {
        $repository->insertAction([
            'id' => $actionId,
            'sort_no' => $baseSort + $index,
            'action_no' => $prefix . ($index + 1),
            'action_name' => $index === 0 ? '검색 회귀 부서이동' : '검색 회귀 직위변경',
            'action_type_code' => $index === 0 ? 'DEPARTMENT_TRANSFER' : 'POSITION_CHANGE',
            'issued_date' => $index === 0 ? '2026-08-01' : '2026-08-02',
            'action_date' => $index === 0 ? '2026-08-10' : '2026-08-11',
            'action_reason' => $index === 0 ? '조직개편 검색사유' : '승진 검색사유',
            'business_status' => 'DRAFT',
            'current_approval_request_id' => $index === 1 ? $approval['id'] : null,
            'created_by' => $actor,
        ]);
        $targetId = UuidHelper::generate();
        $employee = $employees[$index];
        $repository->insertTarget(['id' => $targetId, 'personnel_action_id' => $actionId, 'employee_id' => $employee['id'], 'sort_no' => 1, 'application_status' => 'PENDING', 'created_by' => $actor]);
        $repository->insertChange([
            'id' => UuidHelper::generate(), 'personnel_action_target_id' => $targetId, 'sort_no' => 1,
            'change_type_code' => $index === 0 ? 'DEPARTMENT' : 'POSITION', 'effective_date' => $index === 0 ? '2026-08-10' : '2026-08-11',
            'before_department_id' => $index === 0 ? $employee['department_id'] : null,
            'after_department_id' => $index === 0 ? $afterDepartmentId : null,
            'before_position_id' => $index === 1 ? $employee['position_id'] : null,
            'after_position_id' => $index === 1 ? $afterPositionId : null,
            'before_display_snapshot' => '변경 전', 'after_display_snapshot' => '변경 후', 'created_by' => $actor,
        ]);
    }

    $empty = $query();
    $assert('empty_search', $empty['filtered'] >= 2, $empty['filtered']);
    $assert('action_no', $query([], $prefix . '1')['filtered'] === 1);
    $assert('title', $query([], '부서이동')['filtered'] === 1);
    $assert('reason', $query([], '조직개편 검색사유')['filtered'] === 1);
    $assert('employee_name', $query([], (string) $employees[0]['employee_name'])['filtered'] >= 1);
    $assert('missing_keyword', $query([], '절대없는검색어-9381')['filtered'] === 0);
    $assert('action_type', $query([['field' => 'action_type_code', 'value' => 'DEPARTMENT_TRANSFER']])['filtered'] === 1);
    $assert('business_status', $query([['field' => 'business_status', 'value' => 'DRAFT']])['filtered'] >= 2);
    $employeeResult = $query([['field' => 'employee_id', 'value' => $employees[0]['id']]]);
    $assert('employee', $containsAction($employeeResult, $actionIds[0]), $employeeResult['filtered']);
    $departmentResult = $query([['field' => 'department_id', 'value' => $afterDepartmentId]]);
    $assert('department', $containsAction($departmentResult, $actionIds[0]), $departmentResult['filtered']);
    $positionResult = $query([['field' => 'position_id', 'value' => $afterPositionId]]);
    $assert('position', $positionResult['filtered'] >= 1, ['id' => $afterPositionId, 'filtered' => $positionResult['filtered']]);
    $assert('approval_status', $query([['field' => 'approval_status', 'value' => $approval['status']]])['filtered'] >= 1);
    $assert('date_range', $query([['field' => 'issued_date', 'value' => ['start' => '2026-08-01', 'end' => '2026-08-01']]])['filtered'] === 1);
    $assert('combined', $query([['field' => 'action_type_code', 'value' => 'DEPARTMENT_TRANSFER'], ['field' => 'employee_id', 'value' => $employees[0]['id']]])['filtered'] === 1);
    $assert('special_character', $query([], '%_[]')['filtered'] >= 0);
    $assert('sql_injection_string', $query([], "' OR 1=1 --")['filtered'] === 0);
    $assert('total_filtered_semantics', $query([], $prefix . '1')['total'] > $query([], $prefix . '1')['filtered']);
    $projection = $query([], $prefix . '1')['rows'][0] ?? [];
    $assert('projection_is_summary', array_keys($projection) === array_values(array_intersect(array_keys($projection), [
        'id','sort_no','action_no','action_type_code','action_name','issued_date','action_date','action_reason','business_status','approval_request_no','original_action_no','correction_kind','approved_at','applied_at','cancelled_at','cancelled_reason','note','created_by','created_at','updated_by','updated_at','deleted_by','deleted_at','action_type_name','business_status_name','approval_status','employee_names','target_count','change_count','change_summary','created_by_name','updated_by_name','deleted_by_name',
    ])), array_keys($projection));
    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $results['error'] = $exception->getMessage();
}

$after = (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE action_no LIKE '{$prefix}%'")->fetchColumn();
$results['rollback_clean'] = ['passed' => $before === $after, 'before' => $before, 'after' => $after];
$passed = !isset($results['error']) && !in_array(false, array_map(static fn(array $row): bool => (bool) ($row['passed'] ?? false), $results), true);
echo json_encode(['passed' => $passed, 'tests' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
exit($passed ? 0 : 1);
