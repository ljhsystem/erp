<?php
declare(strict_types=1);

use App\Services\Institution\QualificationEducationService;
use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$user = $db->query("SELECT id,username FROM auth_users WHERE is_active=1 ORDER BY created_at LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$employee = $db->query("SELECT id FROM user_employees ORDER BY sort_no LIMIT 1")->fetchColumn();
if (!$user || !$employee) throw new RuntimeException('Fixture에 사용할 사용자 또는 직원이 없습니다.');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];
$_SESSION['auth_state'] = ['user_id' => $user['id'], 'status' => 'NORMAL'];

$service = new QualificationEducationService($db);
$prefix = 'FIXTURE-QE-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
$created = ['qualification_records'=>[], 'education_records'=>[], 'types'=>[], 'courses'=>[], 'jobs'=>[], 'qualification_requirements'=>[], 'education_requirements'=>[]];
$result = [];
$request = static fn(string $suffix): string => $prefix . '-' . $suffix;
$originalEmployeeJob = null;

try {
    $type = $service->saveQualificationType(['qualification_code'=>$prefix,'qualification_name'=>'Fixture 영구 자격','category_code'=>'OTHER','validity_policy_code'=>'PERMANENT','renewal_policy_code'=>'RENEWAL','is_active'=>1,'request_key'=>$request('TYPE'),'reason'=>'Runtime Fixture'])['data'];
    $created['types'][] = $type['id'];
    $qualification = $service->saveQualification(['employee_id'=>$employee,'qualification_type_id'=>$type['id'],'qualification_name'=>'Fixture 영구 자격','status_code'=>'PENDING_VERIFICATION','request_key'=>$request('Q'),'reason'=>'Runtime Fixture']);
    $created['qualification_records'][] = $qualification['data']['id'];
    $verified = $service->verifyQualification(['id'=>$qualification['data']['id'],'request_key'=>$request('VERIFY'),'reason'=>'Fixture 검증'])['data'];
    $result['qualification_verified'] = $verified['status_code'] === 'ACTIVE';
    $renewed = $service->renewQualification(['id'=>$qualification['data']['id'],'qualification_type_id'=>$type['id'],'employee_id'=>$employee,'qualification_name'=>'Fixture 영구 자격','status_code'=>'PENDING_VERIFICATION','request_key'=>$request('RENEW'),'reason'=>'Fixture 갱신']);
    $created['qualification_records'][] = $renewed['data']['id'];
    $result['qualification_renewal_chain'] = $renewed['data']['supersedes_record_id'] === $qualification['data']['id'];

    $course = $service->saveCourse(['course_code'=>$prefix,'course_name'=>'Fixture 주기교육','education_type_code'=>'INTERNAL','default_minutes'=>60,'recurrence_policy_code'=>'PERIODIC','recurrence_interval_value'=>1,'recurrence_interval_unit_code'=>'YEAR','is_active'=>1,'request_key'=>$request('COURSE'),'reason'=>'Runtime Fixture'])['data'];
    $created['courses'][] = $course['id'];
    $education = $service->saveEducation(['employee_id'=>$employee,'course_id'=>$course['id'],'education_start_at'=>'2026-01-10 09:00','education_end_at'=>'2026-01-10 10:00','education_minutes'=>60,'attendance_status_code'=>'ATTENDED','completion_status_code'=>'COMPLETED','request_key'=>$request('EDUCATION'),'reason'=>'Runtime Fixture']);
    $created['education_records'][] = $education['data']['id'];
    $page = $service->educationList(['start'=>0,'length'=>50,'filters'=>json_encode([['field'=>'course_id','value'=>$course['id']]])], null);
    $result['periodic_projection'] = ($page['data'][0]['next_due_date'] ?? null) === '2027-01-10';

    $jobStatement = $db->prepare("SELECT job_id FROM user_employees WHERE id=:id");
    $jobStatement->execute([':id' => $employee]);
    $job = $jobStatement->fetchColumn();
    $originalEmployeeJob = $job ?: null;
    if (!$job) {
        $job = Core\Helpers\UuidHelper::generate();
        $sortNo = (int)$db->query('SELECT COALESCE(MAX(sort_no),0)+1 FROM institution_job_assignments_jobs')->fetchColumn();
        $statement = $db->prepare('INSERT INTO institution_job_assignments_jobs (id,sort_no,job_code,job_name,is_active,created_at,created_by) VALUES (:id,:sort,:code,:name,1,NOW(),:actor)');
        $statement->execute([':id'=>$job, ':sort'=>$sortNo, ':code'=>$prefix, ':name'=>'Fixture 직무', ':actor'=>Core\Helpers\ActorHelper::user()]);
        $created['jobs'][] = $job;
        $statement = $db->prepare('UPDATE user_employees SET job_id=:job WHERE id=:employee');
        $statement->execute([':job'=>$job, ':employee'=>$employee]);
    }
    $expectedPolicyAuditCount = 2;
    if ($job) {
        $qr = $service->saveRequirement('qualification',['job_id'=>$job,'qualification_type_id'=>$type['id'],'requirement_level_code'=>'REQUIRED','effective_from'=>'2026-01-01','request_key'=>$request('QR'),'reason'=>'Runtime Fixture'])['data'];
        $created['qualification_requirements'][] = $qr['id'];
        $er = $service->saveRequirement('education',['job_id'=>$job,'course_id'=>$course['id'],'requirement_level_code'=>'REQUIRED','effective_from'=>'2026-01-01','request_key'=>$request('ER'),'reason'=>'Runtime Fixture'])['data'];
        $created['education_requirements'][] = $er['id'];
        $expectedPolicyAuditCount += 2;
        try { $service->saveRequirement('qualification',['job_id'=>$job,'qualification_type_id'=>$type['id'],'requirement_level_code'=>'REQUIRED','effective_from'=>'2026-06-01','request_key'=>$request('OVERLAP'),'reason'=>'Runtime Fixture']); $result['overlap_guard']=false; }
        catch (InvalidArgumentException) { $result['overlap_guard']=true; }
        $compliance = $service->employeeCompliance((string)$employee, '2026-06-01')['data'];
        $result['requirement_compliance'] = $compliance['satisfied'] === true;
        $missingType = $service->saveQualificationType(['qualification_code'=>$prefix.'-MISSING','qualification_name'=>'Fixture 미충족 자격','category_code'=>'OTHER','validity_policy_code'=>'PERMANENT','renewal_policy_code'=>'NONE','is_active'=>1,'request_key'=>$request('TYPE-MISSING'),'reason'=>'Runtime Fixture'])['data'];
        $created['types'][] = $missingType['id'];
        $missingRequirement = $service->saveRequirement('qualification',['job_id'=>$job,'qualification_type_id'=>$missingType['id'],'requirement_level_code'=>'REQUIRED','effective_from'=>'2026-01-01','request_key'=>$request('QR-MISSING'),'reason'=>'Runtime Fixture'])['data'];
        $created['qualification_requirements'][] = $missingRequirement['id'];
        $result['requirement_non_compliance'] = $service->employeeCompliance((string)$employee, '2026-06-01')['data']['satisfied'] === false;
    }
    $meta = new DataTableColumnMetaService($db);
    $result['metadata_domains'] = array_reduce(['qualification-status','education-status','qualification-type','education-course','job-qualification-requirement','job-education-requirement'], static fn($ok,$domain) => $ok && count($meta->columnsForDomain($domain)) > 0, true);
    $invalidQualification = $service->invalidateQualification(['id'=>$renewed['data']['id'],'request_key'=>$request('Q-INVALIDATE'),'reason'=>'Fixture 무효화'])['data'];
    $result['qualification_invalidation'] = $invalidQualification['status_code'] === 'INVALIDATED';
    $invalidEducation = $service->invalidateEducation(['id'=>$education['data']['id'],'request_key'=>$request('E-INVALIDATE'),'reason'=>'Fixture 무효화'])['data'];
    $result['education_invalidation'] = $invalidEducation['completion_status_code'] === 'INVALIDATED';
    $auditCount = (int) $db->query("SELECT COUNT(*) FROM institution_qualification_education_policy_audits WHERE request_key LIKE " . $db->quote($prefix . '%'))->fetchColumn();
    $result['policy_audit'] = $auditCount >= $expectedPolicyAuditCount;
    $employeeAuditCount = (int) $db->query("SELECT (SELECT COUNT(*) FROM institution_qualifications_audits WHERE request_key LIKE " . $db->quote($prefix . '%') . ") + (SELECT COUNT(*) FROM institution_educations_audits WHERE request_key LIKE " . $db->quote($prefix . '%') . ")")->fetchColumn();
    $result['employee_audit'] = $employeeAuditCount >= 6;
} finally {
    foreach (['institution_qualifications_audits','institution_educations_audits','institution_qualification_education_policy_audits'] as $table) $db->exec("DELETE FROM {$table} WHERE request_key LIKE " . $db->quote($prefix . '%'));
    foreach (array_reverse($created['education_requirements']) as $id) { $s=$db->prepare('DELETE FROM institution_educations_job_requirements WHERE id=?');$s->execute([$id]); }
    foreach (array_reverse($created['qualification_requirements']) as $id) { $s=$db->prepare('DELETE FROM institution_qualifications_job_requirements WHERE id=?');$s->execute([$id]); }
    foreach (array_reverse($created['education_records']) as $id) { $s=$db->prepare('DELETE FROM institution_educations_employee_records WHERE id=?');$s->execute([$id]); }
    foreach (array_reverse($created['qualification_records']) as $id) { $s=$db->prepare('DELETE FROM institution_qualifications_employee_records WHERE id=?');$s->execute([$id]); }
    foreach (array_reverse($created['courses']) as $id) { $s=$db->prepare('DELETE FROM institution_educations_courses WHERE id=?');$s->execute([$id]); }
    foreach (array_reverse($created['types']) as $id) { $s=$db->prepare('DELETE FROM institution_qualifications_types WHERE id=?');$s->execute([$id]); }
    if ($created['jobs'] !== []) { $s=$db->prepare('UPDATE user_employees SET job_id=:job WHERE id=:employee');$s->execute([':job'=>$originalEmployeeJob,':employee'=>$employee]); }
    foreach (array_reverse($created['jobs']) as $id) { $s=$db->prepare('DELETE FROM institution_job_assignments_jobs WHERE id=?');$s->execute([$id]); }
}

$remaining = 0;
foreach (['institution_qualifications_employee_records','institution_educations_employee_records','institution_qualifications_types','institution_educations_courses','institution_qualifications_job_requirements','institution_educations_job_requirements','institution_qualifications_audits','institution_educations_audits','institution_qualification_education_policy_audits'] as $table) {
    $remaining += (int) $db->query("SELECT COUNT(*) FROM {$table} WHERE request_key LIKE " . $db->quote($prefix . '%'))->fetchColumn();
}
$remaining += (int)$db->query("SELECT COUNT(*) FROM institution_job_assignments_jobs WHERE job_code LIKE " . $db->quote($prefix . '%'))->fetchColumn();
$result['fixture_remaining'] = $remaining;
$result['success'] = !in_array(false, $result, true) && $remaining === 0;
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
exit($result['success'] ? 0 : 1);
