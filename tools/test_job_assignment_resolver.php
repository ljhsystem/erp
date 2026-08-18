<?php

declare(strict_types=1);

use App\Models\Institution\JobAssignmentModel;
use App\Repositories\Institution\PersonnelActionRepository;
use App\Services\Institution\EmployeeAssignmentResolver;
use App\Services\Institution\JobAssignmentService;
use App\Services\System\EmployeeService;
use App\Services\System\ProjectService;
use Core\DbPdo;
use Core\Helpers\UuidHelper;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$pdo = DbPdo::conn();
$model = new JobAssignmentModel($pdo);
$asOf = '2026-09-15';
$employee = $pdo->query("SELECT id,employee_name FROM user_employees WHERE employment_status='ACTIVE' ORDER BY sort_no LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$projects = $pdo->query("SELECT id,project_name FROM system_projects WHERE deleted_at IS NULL ORDER BY sort_no LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
$activeProject = $pdo->query("SELECT id FROM system_projects WHERE deleted_at IS NULL AND is_active=1 AND start_date IS NULL AND completion_date IS NULL ORDER BY sort_no LIMIT 1")->fetchColumn();
$user = $pdo->query("SELECT u.id FROM auth_users u JOIN user_employees e ON e.user_id=u.id WHERE e.id=".$pdo->quote((string)($employee['id']??'')))->fetchColumn();
if (!$employee || count($projects) < 3 || !$activeProject || !$user) throw new RuntimeException('Resolver Fixture 기준정보가 부족합니다.');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user']=['id'=>$user];$_SESSION['auth_state']=['user_id'=>$user,'status'=>'NORMAL'];

$before = (int) $pdo->query("SELECT COUNT(*) FROM institution_job_assignments_project_histories WHERE assignment_role LIKE 'FIXTURE-RESOLVER-%'")->fetchColumn();
$results = [];
$pdo->beginTransaction();
try {
    $results['status'] = [
        'planned' => EmployeeAssignmentResolver::effectiveStatus('2026-09-16', null, $asOf) === 'PLANNED',
        'start_inclusive' => EmployeeAssignmentResolver::effectiveStatus($asOf, null, $asOf) === 'ACTIVE',
        'end_inclusive' => EmployeeAssignmentResolver::effectiveStatus('2026-09-01', $asOf, $asOf) === 'ACTIVE',
        'ended' => EmployeeAssignmentResolver::effectiveStatus('2026-09-01', '2026-09-14', $asOf) === 'ENDED',
        'cancelled_priority' => EmployeeAssignmentResolver::effectiveStatus('2026-09-01', null, $asOf, 'CANCELLED') === 'CANCELLED',
    ];
    foreach ($projects as $index => $project) {
        $id = UuidHelper::generate();
        $stmt = $pdo->prepare('INSERT INTO institution_job_assignments_project_histories(id,employee_id,project_id,job_id,assignment_role,start_date,end_date,is_primary,status_code,assignment_personnel_action_target_id,end_personnel_action_target_id,created_by) VALUES(:id,:employee,:project,NULL,:role,:start,NULL,:primary,:status,NULL,NULL,:actor)');
        $stmt->execute([
            ':id'=>$id, ':employee'=>$employee['id'], ':project'=>$project['id'],
            ':role'=>'FIXTURE-RESOLVER-'.$index, ':start'=>'2026-09-01',
            ':primary'=>$index===0?1:0, ':status'=>'ACTIVE', ':actor'=>'SYSTEM:JOB_ASSIGNMENT_RESOLVER_TEST',
        ]);
    }
    $page = $model->page(['start'=>0,'length'=>50,'filters'=>json_encode([['field'=>'keyword','value'=>$employee['employee_name']]],JSON_UNESCAPED_UNICODE)], $asOf);
    $matching = array_values(array_filter($page['rows'], static fn(array $row): bool => $row['employee_id'] === $employee['id']));
    $row = $matching[0] ?? [];
    $results['projection'] = [
        'one_employee_one_row' => count($matching) === 1,
        'three_projects' => count(array_filter(explode(', ', (string)($row['project_names'] ?? '')))) === 3,
        'primary_project' => (string)($row['primary_project_name'] ?? '') === (string)$projects[0]['project_name'],
        'other_summary' => str_ends_with((string)($row['other_project_summary'] ?? ''), '외 1건'),
    ];
    $filtered = $model->page(['start'=>0,'length'=>50,'filters'=>json_encode([['field'=>'project_id','value'=>$projects[2]['id']]])], $asOf);
    $results['count'] = [
        'total_not_filtered' => $filtered['total'] >= $filtered['filtered'],
        'filtered_employee_once' => count(array_filter($filtered['rows'], static fn(array $row): bool => $row['employee_id'] === $employee['id'])) === 1,
    ];
    $options = (new JobAssignmentService($pdo))->options();
    $employeePicker = (new EmployeeService($pdo))->searchPicker((string) $employee['employee_name']);
    $projectPicker = (new ProjectService($pdo))->searchPicker((string) $projects[0]['project_name']);
    $employeeFiltered = $model->page(['start'=>0,'length'=>50,'filters'=>json_encode([['field'=>'employee_id','value'=>$employee['id']]])], $asOf);
    $specialSearchSafe = true;
    foreach (['%', '_', "'", '"', '\\', "' OR 1=1 --", '한글', (string) $employee['id']] as $keyword) {
        try {
            $model->page(['start'=>0,'length'=>10,'filters'=>json_encode([['field'=>'keyword','value'=>$keyword]], JSON_UNESCAPED_UNICODE)], $asOf);
        } catch (Throwable) {
            $specialSearchSafe = false;
        }
    }
    $results['closure_contract'] = [
        'employee_options_not_preloaded' => !array_key_exists('employees', $options),
        'project_options_not_preloaded' => !array_key_exists('projects', $options),
        'employee_picker_uses_employee_id' => in_array((string) $employee['id'], array_map(static fn(array $row): string => (string) ($row['id'] ?? $row['value'] ?? ''), $employeePicker), true),
        'project_picker_uses_project_id' => in_array((string) $projects[0]['id'], array_map(static fn(array $row): string => (string) ($row['id'] ?? $row['value'] ?? ''), $projectPicker), true),
        'employee_filter_exact' => count(array_filter($employeeFiltered['rows'], static fn(array $row): bool => $row['employee_id'] === $employee['id'])) === 1,
        'special_search_safe' => $specialSearchSafe,
    ];
    $scopeEmployees = $pdo->query('SELECT id FROM user_employees ORDER BY sort_no,employee_name LIMIT 3')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (count($scopeEmployees) !== 3) throw new RuntimeException('직원범위 Fixture 기준 직원이 부족합니다.');
    $scopeMarker = 'FIXTURE-SCOPE-' . substr(str_replace('-', '', UuidHelper::generate()), 0, 8);
    $rename = $pdo->prepare('UPDATE user_employees SET employee_name=:name WHERE id=:id');
    $endExisting = $pdo->prepare("UPDATE institution_job_assignments_employment_status_histories SET ended_date='2098-12-31' WHERE employee_id=:employee AND (ended_date IS NULL OR ended_date>='2099-01-01')");
    $insertStatus = $pdo->prepare("INSERT INTO institution_job_assignments_employment_status_histories(id,employee_id,status_code,effective_date,ended_date,reason,source_personnel_action_target_id,created_by) VALUES(:id,:employee,:status,'2099-01-01',NULL,:reason,NULL,'SYSTEM:JOB_ASSIGNMENT_SCOPE_TEST')");
    foreach (['ACTIVE', 'ON_LEAVE', 'RETIRED'] as $index => $status) {
        $rename->execute([':name'=>$scopeMarker.'-'.$status, ':id'=>$scopeEmployees[$index]]);
        $endExisting->execute([':employee'=>$scopeEmployees[$index]]);
        $insertStatus->execute([':id'=>UuidHelper::generate(), ':employee'=>$scopeEmployees[$index], ':status'=>$status, ':reason'=>$scopeMarker]);
    }
    $scopeFilters = [['field'=>'keyword','value'=>$scopeMarker]];
    $scopeDefault = $model->page(['start'=>0,'length'=>50,'filters'=>json_encode($scopeFilters)], '2099-01-01');
    $scopeCurrent = $model->page(['start'=>0,'length'=>50,'filters'=>json_encode([...$scopeFilters, ['field'=>'current_only','value'=>'1']])], '2099-01-01');
    $scopeRetired = $model->page(['start'=>0,'length'=>50,'filters'=>json_encode([...$scopeFilters, ['field'=>'employment_status','value'=>'RETIRED']])], '2099-01-01');
    $employeeBaseline = (int) $pdo->query('SELECT COUNT(*) FROM user_employees')->fetchColumn();
    $results['employee_scope'] = [
        'default_includes_all_statuses' => $scopeDefault['filtered'] === 3,
        'current_only_active_and_leave' => $scopeCurrent['filtered'] === 2,
        'retired_search_only' => $scopeRetired['filtered'] === 1 && ($scopeRetired['rows'][0]['employment_status'] ?? '') === 'RETIRED',
        'records_total_is_employee_scope' => $scopeDefault['total'] === $employeeBaseline && $scopeCurrent['total'] === $employeeBaseline,
    ];
    $masterBefore=$pdo->query('SELECT job_id FROM user_employees WHERE id='.$pdo->quote((string)$employee['id']))->fetchColumn();
    $requestKey=UuidHelper::generate();$service=new JobAssignmentService($pdo);
    $payload=['employee_id'=>$employee['id'],'project_id'=>$activeProject,'start_date'=>'2026-09-16','end_date'=>null,'is_primary'=>0,'source_type'=>'TEMPORARY_PROJECT_ASSIGNMENT','reason'=>'Resolver rollback Fixture','request_key'=>$requestKey];
    $created=$service->saveProject($payload);$replayed=$service->saveProject($payload);
    $assignmentId=(string)($created['data']['assignment']['id']??'');
    $results['direct_service']=[
        'future_planned'=>(string)($created['data']['assignment']['status_code']??'')==='PLANNED',
        'idempotent_replay'=>($replayed['data']['idempotent']??false)===true,
        'single_assignment'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_job_assignments_project_histories WHERE id='.$pdo->quote($assignmentId))->fetchColumn()===1,
        'single_audit'=>(int)$pdo->query('SELECT COUNT(*) FROM institution_job_assignments_audits WHERE request_key='.$pdo->quote($requestKey))->fetchColumn()===1,
        'master_unchanged'=>$masterBefore===$pdo->query('SELECT job_id FROM user_employees WHERE id='.$pdo->quote((string)$employee['id']))->fetchColumn(),
    ];
    $jobId=UuidHelper::generate();$jobSort=(int)$pdo->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM institution_job_assignments_jobs')->fetchColumn();
    $pdo->prepare('INSERT INTO institution_job_assignments_jobs(id,sort_no,job_code,job_name,is_active,created_by) VALUES(:id,:sort,:code,:name,1,:actor)')->execute([':id'=>$jobId,':sort'=>$jobSort,':code'=>'FIXTURE-'.substr(str_replace('-','',$jobId),0,8),':name'=>'Resolver Fixture 직무',':actor'=>'SYSTEM:JOB_ASSIGNMENT_RESOLVER_TEST']);
    $jobRequest=UuidHelper::generate();$jobPayload=['employee_id'=>$employee['id'],'job_id'=>$jobId,'start_date'=>'2013-07-01','end_date'=>'2013-07-15','source_type'=>'PRE_PERSONNEL_ACTION_HISTORY','reason'=>'과거 직무 rollback Fixture','request_key'=>$jobRequest];
    $jobCreated=$service->saveHistory($jobPayload);$jobReplayed=$service->saveHistory($jobPayload);
    $results['past_job']=[
        'created'=>(string)($jobCreated['data']['assignment']['status_code']??'')==='ENDED',
        'idempotent_replay'=>($jobReplayed['data']['idempotent']??false)===true,
        'master_unchanged'=>$masterBefore===$pdo->query('SELECT job_id FROM user_employees WHERE id='.$pdo->quote((string)$employee['id']))->fetchColumn(),
    ];
    $personnelRepository=new PersonnelActionRepository($pdo);$actionId=UuidHelper::generate();$targetId=UuidHelper::generate();$officialAssignmentId=UuidHelper::generate();
    $personnelRepository->insertAction(['id'=>$actionId,'sort_no'=>$personnelRepository->nextSortNo(),'action_no'=>'FIXTURE-RESOLVER-'.substr(str_replace('-','',$actionId),0,8),'action_type_code'=>'PROJECT_ASSIGNMENT','action_name'=>'Resolver source 보호 Fixture','issued_date'=>date('Y-m-d'),'action_date'=>date('Y-m-d'),'business_status'=>'APPLIED','approved_at'=>date('Y-m-d H:i:s'),'applied_at'=>date('Y-m-d H:i:s'),'created_by'=>'SYSTEM:JOB_ASSIGNMENT_RESOLVER_TEST']);
    $personnelRepository->insertTarget(['id'=>$targetId,'personnel_action_id'=>$actionId,'employee_id'=>$employee['id'],'sort_no'=>1,'application_status'=>'APPLIED','applied_at'=>date('Y-m-d H:i:s'),'applied_by'=>'SYSTEM:JOB_ASSIGNMENT_RESOLVER_TEST','created_by'=>'SYSTEM:JOB_ASSIGNMENT_RESOLVER_TEST']);
    $personnelRepository->insertHistory('institution_job_assignments_project_histories',['id'=>$officialAssignmentId,'employee_id'=>$employee['id'],'project_id'=>$activeProject,'job_id'=>null,'assignment_role'=>'FIXTURE-OFFICIAL-SOURCE','start_date'=>'2027-01-01','end_date'=>null,'is_primary'=>1,'status_code'=>'PLANNED','assignment_personnel_action_target_id'=>$targetId,'end_personnel_action_target_id'=>null,'created_by'=>'SYSTEM:JOB_ASSIGNMENT_RESOLVER_TEST']);
    $sourceBlocked=false;try{$service->endProject(['assignment_id'=>$officialAssignmentId,'end_date'=>date('Y-m-d'),'source_type'=>'ADMIN_CORRECTION','reason'=>'차단 검증','request_key'=>UuidHelper::generate()]);}catch(RuntimeException $exception){$sourceBlocked=str_contains($exception->getMessage(),'직접 종료·정정할 수 없습니다');}
    $results['source_protection']=['official_project_end_blocked'=>$sourceBlocked];
    $results['passed'] = !in_array(false, array_merge(...array_values(array_filter($results, 'is_array'))), true);
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
$after = (int) $pdo->query("SELECT COUNT(*) FROM institution_job_assignments_project_histories WHERE assignment_role LIKE 'FIXTURE-RESOLVER-%'")->fetchColumn();
$results['rollback_clean'] = $before === $after;
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if (empty($results['passed']) || !$results['rollback_clean']) exit(1);
