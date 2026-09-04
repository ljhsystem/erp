<?php

declare(strict_types=1);

use App\Services\Institution\DailyEmploymentIncomeService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

$db=DbPdo::conn();
$userId=$db->query('SELECT id FROM auth_users ORDER BY created_at,id LIMIT 1')->fetchColumn();
if (!is_string($userId) || $userId==='') throw new RuntimeException('Fixture Actor 사용자를 찾을 수 없습니다.');
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
$_SESSION['user']=['id'=>$userId];
$_SESSION['auth_state']=['user_id'=>$userId,'status'=>'NORMAL'];
$worker=$db->query('SELECT id FROM system_clients WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_no,id LIMIT 1')->fetchColumn();
if (!is_string($worker) || $worker==='') throw new RuntimeException('Fixture 작업자를 찾을 수 없습니다.');
$service=new DailyEmploymentIncomeService($db);
$suffix=bin2hex(random_bytes(6));
$documentNumber='FIXTURE-DAILY-GROUP-'.$suffix;
$requestKeys=['fixture-daily-group-save-'.$suffix,'fixture-daily-group-update-'.$suffix];
$id='';
try {
    $payload=[
        'request_key'=>$requestKeys[0],
        'income_year_month'=>'2013-08',
        'payment_date'=>'2013-08-31',
        'document_number'=>$documentNumber,
        'document_title'=>'일용근로소득 그룹 SSOT Fixture',
        'items'=>[
            ['business_unit'=>'HQ','project_id'=>null,'work_team_id'=>null,'worker_client_id'=>$worker,'workdays'=>[['work_date'=>'2013-08-01','daily_rate_amount'=>100000]]],
            ['business_unit'=>'ECOMMERCE','project_id'=>null,'work_team_id'=>null,'worker_client_id'=>$worker,'workdays'=>[['work_date'=>'2013-08-01','daily_rate_amount'=>120000]]],
        ],
    ];
    $saved=$service->save($payload);$id=(string)$saved['data']['id'];
    $read=$service->detail($id);
    if (count($read['data']['items'])!==2) throw new RuntimeException('사업구분별 Item 분리가 유지되지 않았습니다.');
    if ((int)$read['data']['header']['worker_count']!==1 || (int)$read['data']['header']['work_team_count']!==0) throw new RuntimeException('Header 집계값이 확정 기준과 일치하지 않습니다.');
    $duplicateGrainBlocked=false;
    try {$service->calculate(['income_year_month'=>'2013-08','items'=>[$payload['items'][0],$payload['items'][0]]]);} catch (InvalidArgumentException) {$duplicateGrainBlocked=true;}
    if (!$duplicateGrainBlocked) throw new RuntimeException('동일 Grain 중복 Item이 차단되지 않았습니다.');
    $duplicateWorkday=$payload['items'][0];$duplicateWorkday['workdays'][]=$duplicateWorkday['workdays'][0];$duplicateWorkdayBlocked=false;
    try {$service->calculate(['income_year_month'=>'2013-08','items'=>[$duplicateWorkday]]);} catch (InvalidArgumentException) {$duplicateWorkdayBlocked=true;}
    if (!$duplicateWorkdayBlocked) throw new RuntimeException('동일 Item의 중복 근무일이 차단되지 않았습니다.');
    $taxProjection=$db->prepare('SELECT h.income_year_month,i.worker_client_id,COUNT(DISTINCT i.id) source_item_count,SUM(w.taxable_amount+w.non_taxable_amount) gross_amount FROM institution_daily_employment_incomes h JOIN institution_daily_employment_income_items i ON i.daily_employment_income_id=h.id JOIN institution_daily_employment_income_workdays w ON w.daily_employment_income_item_id=i.id WHERE h.id=:id GROUP BY h.income_year_month,i.worker_client_id');
    $taxProjection->execute([':id'=>$id]);$taxRows=$taxProjection->fetchAll(PDO::FETCH_ASSOC)?:[];
    $insuranceProjection=$db->prepare('SELECT h.income_year_month,i.business_unit,i.project_id,i.worker_client_id,COUNT(DISTINCT w.id) workday_count FROM institution_daily_employment_incomes h JOIN institution_daily_employment_income_items i ON i.daily_employment_income_id=h.id JOIN institution_daily_employment_income_workdays w ON w.daily_employment_income_item_id=i.id WHERE h.id=:id GROUP BY h.income_year_month,i.business_unit,i.project_id,i.worker_client_id');
    $insuranceProjection->execute([':id'=>$id]);$insuranceRows=$insuranceProjection->fetchAll(PDO::FETCH_ASSOC)?:[];
    $internalProjection=$db->prepare('SELECT i.business_unit,i.project_id,i.work_team_id,i.worker_client_id,COUNT(w.id) workday_count FROM institution_daily_employment_income_items i JOIN institution_daily_employment_income_workdays w ON w.daily_employment_income_item_id=i.id WHERE i.daily_employment_income_id=:id GROUP BY i.business_unit,i.project_id,i.work_team_id,i.worker_client_id');
    $internalProjection->execute([':id'=>$id]);$internalRows=$internalProjection->fetchAll(PDO::FETCH_ASSOC)?:[];
    if (count($taxRows)!==1 || (int)$taxRows[0]['source_item_count']!==2 || count($insuranceRows)!==2 || count($internalRows)!==2) throw new RuntimeException('기관별 Projection 결과가 예상 Grain과 일치하지 않습니다.');
    $payload['id']=$id;$payload['request_key']=$requestKeys[1];$payload['document_title']='일용근로소득 그룹 SSOT Fixture 수정';
    $service->save($payload);
    $updated=$service->detail($id);
    if (($updated['data']['header']['document_title']??'')!==$payload['document_title']) throw new RuntimeException('수정 결과가 일치하지 않습니다.');
    $service->delete($id);$service->restore($id);$service->delete($id);$purged=$service->purge($id);
    if (($purged['data']['deleted_count']??0)!==1) throw new RuntimeException('Fixture 완전삭제에 실패했습니다.');
    $id='';
    echo json_encode(['save'=>'PASS','read_items'=>2,'header_counts'=>['worker_count'=>1,'work_team_count'=>0],'update'=>'PASS','delete_restore_purge'=>'PASS','same_worker_multi_business_unit'=>'PASS','duplicate_grain_blocked'=>'PASS','duplicate_workday_blocked'=>'PASS','projections'=>['tax_worker_rows'=>count($taxRows),'tax_source_items'=>(int)$taxRows[0]['source_item_count'],'insurance_scope_rows'=>count($insuranceRows),'internal_grain_rows'=>count($internalRows)]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),PHP_EOL;
} finally {
    if ($id!=='') {
        $db->prepare('DELETE FROM institution_daily_employment_income_lines WHERE daily_employment_income_item_id IN (SELECT id FROM institution_daily_employment_income_items WHERE daily_employment_income_id=:id)')->execute([':id'=>$id]);
        $db->prepare('DELETE FROM institution_daily_employment_income_workdays WHERE daily_employment_income_item_id IN (SELECT id FROM institution_daily_employment_income_items WHERE daily_employment_income_id=:id)')->execute([':id'=>$id]);
        $db->prepare('DELETE FROM institution_daily_employment_income_items WHERE daily_employment_income_id=:id')->execute([':id'=>$id]);
        $db->prepare('DELETE FROM institution_daily_employment_incomes WHERE id=:id')->execute([':id'=>$id]);
    }
    $marks=implode(',',array_fill(0,count($requestKeys),'?'));
    $db->prepare("DELETE FROM institution_daily_employment_income_commands WHERE request_key IN ($marks)")->execute($requestKeys);
}
