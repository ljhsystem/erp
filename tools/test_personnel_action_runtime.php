<?php

declare(strict_types=1);

use App\Repositories\Institution\PersonnelActionRepository;
use App\Services\Institution\PersonnelActionApplyService;
use App\Services\Institution\PersonnelActionService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$service = new PersonnelActionService($pdo);
$repository = new PersonnelActionRepository($pdo);
$result = [
    'options' => array_map('count', $service->modalOptions()['data']),
    'empty_list_ok' => $service->list(['start' => 0, 'length' => 10])['success'] ?? false,
];

$pdo->beginTransaction();
try {
    $employee = $pdo->query("SELECT e.* FROM user_employees e WHERE e.employment_status='ACTIVE' AND e.department_id IS NOT NULL ORDER BY e.sort_no LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    $department = $pdo->prepare('SELECT id FROM user_departments WHERE id<>:current AND is_active=1 ORDER BY sort_no LIMIT 1');
    $department->execute([':current' => $employee['department_id']]);
    $afterDepartmentId = (string) $department->fetchColumn();
    if (!$employee || $afterDepartmentId === '') throw new RuntimeException('테스트 가능한 직원 또는 대체 부서가 없습니다.');
    $actionId = UuidHelper::generate();
    $targetId = UuidHelper::generate();
    $actor = 'SYSTEM:PERSONNEL_ACTION_TEST';
    $repository->insertAction([
        'id'=>$actionId,'sort_no'=>$repository->nextSortNo(),'action_no'=>'TEST-'.substr($actionId,0,8),
        'action_name'=>'인사발령 적용 롤백 테스트','action_type_code'=>'DEPARTMENT_TRANSFER',
        'issued_date'=>date('Y-m-d'),'action_date'=>date('Y-m-d'),'business_status'=>'APPROVED',
        'approved_at'=>date('Y-m-d H:i:s'),'created_by'=>$actor,
    ]);
    $repository->insertTarget(['id'=>$targetId,'personnel_action_id'=>$actionId,'employee_id'=>$employee['id'],'sort_no'=>1,'application_status'=>'PENDING','created_by'=>$actor]);
    $repository->insertChange([
        'id'=>UuidHelper::generate(),'personnel_action_target_id'=>$targetId,'sort_no'=>1,'change_type_code'=>'DEPARTMENT',
        'effective_date'=>date('Y-m-d'),'before_department_id'=>$employee['department_id'],'after_department_id'=>$afterDepartmentId,
        'before_display_snapshot'=>'테스트 변경 전','after_display_snapshot'=>'테스트 변경 후','created_by'=>$actor,
    ]);
    $apply = new PersonnelActionApplyService($pdo);
    $first = $apply->apply($actionId, $actor);
    $second = $apply->apply($actionId, $actor);
    $detail = $service->detail($actionId)['data'];
    $result['apply'] = [
        'first' => $first,
        'second' => $second,
        'business_status' => $detail['action']['business_status'],
        'target_status' => $detail['targets'][0]['application_status'] ?? null,
        'applied_by' => $detail['targets'][0]['applied_by'] ?? null,
        'department_changed' => ($repository->employee((string) $employee['id'])['department_id'] ?? null) === $afterDepartmentId,
    ];
    $pdo->rollBack();
    $result['rolled_back'] = true;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result['rolled_back'] = true;
    $result['error'] = $exception->getMessage();
}

$result['test_rows_after_rollback'] = (int) $pdo->query("SELECT COUNT(*) FROM institution_personnel_actions WHERE action_no LIKE 'TEST-%'")->fetchColumn();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
