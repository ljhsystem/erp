<?php
declare(strict_types=1);

use App\Services\Institution\EducationSessionService;
use App\Services\System\DataTableColumnMetaService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db = DbPdo::conn();
$user = $db->query("SELECT id,username FROM auth_users WHERE is_active=1 ORDER BY created_at LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$employees = $db->query('SELECT id FROM user_employees ORDER BY sort_no,id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
$course = $db->query('SELECT id FROM institution_educations_courses WHERE deleted_at IS NULL AND is_active=1 ORDER BY sort_no,id LIMIT 1')->fetchColumn();
if (!$user || count($employees) < 3 || !$course) throw new RuntimeException('Fixture 기준 데이터가 부족합니다.');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user'] = ['id' => $user['id'], 'username' => $user['username']];
$_SESSION['auth_state'] = ['user_id' => $user['id'], 'status' => 'NORMAL'];

$service = new EducationSessionService($db);
$prefix = 'FIXTURE-SESSION-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
$sessionIds = []; $result = [];
try {
    $session = $service->save(['course_id'=>$course,'title'=>'Fixture 교육','starts_at'=>'2026-09-01 09:00','ends_at'=>'2026-09-01 11:00','location_name'=>'Fixture 교육장','request_key'=>$prefix.'-CREATE','reason'=>'Runtime Fixture'])['data'];
    $sessionIds[] = $session['id'];
    $service->addTargets(['session_id'=>$session['id'],'employee_ids'=>$employees,'assignment_source_code'=>'INDIVIDUAL','request_key'=>$prefix.'-TARGET','reason'=>'Runtime Fixture']);
    try {$service->addTargets(['session_id'=>$session['id'],'employee_ids'=>[$employees[0]],'request_key'=>$prefix.'-DUP']);$result['duplicate_blocked']=false;} catch (InvalidArgumentException) {$result['duplicate_blocked']=true;}
    $service->transition(['id'=>$session['id'],'action'=>'SCHEDULE','request_key'=>$prefix.'-SCHEDULE','reason'=>'Runtime Fixture']);
    $targets = $service->targetList($session['id'], ['start'=>0,'length'=>10])['data'];
    $service->acknowledge($targets[0]['id'], $employees[0], ['request_key'=>$prefix.'-ACK','reason'=>'Runtime Fixture']);
    $service->updateOutcome(['id'=>$targets[0]['id'],'attendance_status_code'=>'ATTENDED','completion_status_code'=>'COMPLETED','request_key'=>$prefix.'-O1']);
    $service->updateOutcome(['id'=>$targets[1]['id'],'attendance_status_code'=>'ATTENDED','completion_status_code'=>'NOT_COMPLETED','request_key'=>$prefix.'-O2']);
    $service->updateOutcome(['id'=>$targets[2]['id'],'attendance_status_code'=>'ABSENT','completion_status_code'=>'PENDING','request_key'=>$prefix.'-O3']);
    $updated = $service->save(['id'=>$session['id'],'course_id'=>$course,'title'=>'Fixture 교육','starts_at'=>'2026-09-01 10:00','ends_at'=>'2026-09-01 12:00','location_name'=>'변경 교육장','request_key'=>$prefix.'-UPDATE','reason'=>'일정 변경']);
    $result['updated_event'] = ($updated['events'][0]['event_type'] ?? '') === 'TRAINING_UPDATED';
    $service->transition(['id'=>$session['id'],'action'=>'COMPLETE','request_key'=>$prefix.'-COMPLETE','reason'=>'Runtime Fixture']);
    $records = $db->prepare('SELECT employee_id FROM institution_educations_employee_records WHERE session_id=:id ORDER BY employee_id');$records->execute([':id'=>$session['id']]);
    $recordEmployees = $records->fetchAll(PDO::FETCH_COLUMN);
    $result['records_policy'] = count($recordEmployees) === 2 && in_array($employees[0],$recordEmployees,true) && in_array($employees[1],$recordEmployees,true) && !in_array($employees[2],$recordEmployees,true);
    $page = $service->list(['start'=>0,'length'=>10,'search'=>['value'=>'Fixture 교육'],'order'=>[['column'=>0,'dir'=>'asc']],'columns'=>[['data'=>'title']]]);
    $result['aggregate'] = (int)($page['data'][0]['target_count']??0)===3 && (int)($page['data'][0]['attended_count']??0)===2 && (int)($page['data'][0]['absent_count']??0)===1;
    $result['metadata'] = count((new DataTableColumnMetaService($db))->columnsForDomain('education-session'))>0 && count((new DataTableColumnMetaService($db))->columnsForDomain('education-session-target'))>0;

    $cancel = $service->save(['course_id'=>$course,'title'=>'Fixture 취소','starts_at'=>'2026-10-01 09:00','ends_at'=>'2026-10-01 10:00','request_key'=>$prefix.'-CANCEL-CREATE'])['data'];$sessionIds[]=$cancel['id'];
    $cancelled=$service->transition(['id'=>$cancel['id'],'action'=>'CANCEL','reason'=>'Fixture 취소','request_key'=>$prefix.'-CANCEL']);
    $result['cancel_event']=($cancelled['events'][0]['event_type']??'')==='TRAINING_CANCELLED';
} finally {
    foreach ($sessionIds as $id) {
        $db->prepare("DELETE FROM institution_educations_audits WHERE target_id IN (SELECT id FROM institution_educations_employee_records WHERE session_id=:id) OR target_id IN (SELECT id FROM institution_educations_session_targets WHERE session_id=:id2) OR target_id=:id3")->execute([':id'=>$id,':id2'=>$id,':id3'=>$id]);
        $db->prepare('DELETE FROM institution_educations_employee_records WHERE session_id=:id')->execute([':id'=>$id]);
        $db->prepare('DELETE FROM institution_educations_session_targets WHERE session_id=:id')->execute([':id'=>$id]);
        $db->prepare('DELETE FROM institution_educations_sessions WHERE id=:id')->execute([':id'=>$id]);
    }
}
$residual=(int)$db->query("SELECT COUNT(*) FROM institution_educations_sessions WHERE request_key LIKE 'FIXTURE-SESSION-%'")->fetchColumn();
$result['residual_zero']=$residual===0;
echo json_encode(['success'=>!in_array(false,$result,true),'checks'=>$result],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
