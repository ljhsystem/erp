<?php
declare(strict_types=1);
define('PROJECT_ROOT',dirname(__DIR__,2));
require PROJECT_ROOT.'/vendor/autoload.php';
require_once PROJECT_ROOT.'/core/Storage.php';
use App\Services\Site\SalesService;
use Core\DbPdo;

$db=DbPdo::conn();
$tables=['site_sales_organizations','site_sales_people','site_sales_affiliations','site_sales_opportunities','site_sales_activities','site_sales_followups'];
$counts=static function()use($db,$tables):array{$result=[];foreach($tables as$table)$result[$table]=(int)$db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();return$result;};
$employee=(string)$db->query('SELECT id FROM user_employees ORDER BY employee_name LIMIT 1')->fetchColumn();
if($employee==='')throw new RuntimeException('영업관리 회귀검증에 사용할 직원 기준정보가 없습니다.');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$_SESSION['user']=['id'=>'site-sales-regression-user'];
$_SESSION['auth_state']=['user_id'=>'site-sales-regression-user','status'=>'NORMAL'];
$before=$counts();$service=new SalesService($db);$checks=['empty_or_list_query'=>is_array($service->list()),'options_query'=>is_array($service->options()),'dashboard_query'=>is_array($service->dashboard())];
$db->beginTransaction();
try{
    $created=$service->saveOrganization(['organization_name'=>'영업관리 회귀검증 업체','organization_type_code'=>'COMPANY','owner_employee_id'=>$employee,'relationship_level_code'=>'WARM','sales_status_code'=>'ACTIVE','industry_name'=>'전문건설업','next_action_date'=>'2026-09-10','next_action_summary'=>'담당자 연락']);
    $organizationId=(string)($created['data']['id']??'');$checks['organization_created']=$organizationId!=='';
    $person=$service->addPerson(['organization_id'=>$organizationId,'person_name'=>'회귀검증 담당자','owner_employee_id'=>$employee,'influence_role_code'=>'DECISION_MAKER','mobile_phone'=>'010-0000-0000']);$personId=(string)($person['data']['people'][0]['id']??'');$checks['person_and_affiliation_created']=$personId!=='';
    $service->addOpportunity(['organization_id'=>$organizationId,'person_id'=>$personId,'opportunity_name'=>'회귀검증 공사','stage_code'=>'CONSULTING','owner_probability_rate'=>60,'expected_amount'=>10000000,'probability_reason'=>'도면 검토 요청']);
    $service->addActivity(['organization_id'=>$organizationId,'person_id'=>$personId,'activity_type_code'=>'MEETING','activity_at'=>'2026-09-05 10:00:00','activity_summary'=>'공사 범위 협의','customer_request'=>'견적서 요청','our_commitment'=>'검토 후 회신']);
    $followup=$service->addFollowup(['organization_id'=>$organizationId,'person_id'=>$personId,'assigned_employee_id'=>$employee,'due_at'=>'2026-09-06 18:00:00','followup_summary'=>'견적자료 회신']);
    $followupId=(string)($followup['data']['followups'][0]['id']??'');$service->completeFollowup(['id'=>$followupId,'organization_id'=>$organizationId,'completed_note'=>'회귀검증 완료']);
    $detail=$service->detail($organizationId);$checks['activity_created']=count($detail['activities']??[])===1;$checks['opportunity_created']=count($detail['opportunities']??[])===1;$checks['followup_completed']=($detail['followups'][0]['followup_status_code']??'')==='DONE';$checks['analysis_explained']=trim((string)($detail['analysis_reason']??''))!=='';
}finally{$db->rollBack();}
$after=$counts();$checks['transaction_rolled_back']=$before===$after;$passed=!in_array(false,$checks,true);
echo json_encode(['passed'=>$passed,'checks'=>$checks,'before'=>$before,'after'=>$after],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
