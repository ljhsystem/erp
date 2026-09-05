<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT.'/vendor/autoload.php';
require_once PROJECT_ROOT.'/core/Storage.php';

use App\Services\Ledger\VehicleLogService;
use Core\DbPdo;

$db=DbPdo::conn();
$employee=$db->query("SELECT id,user_id,employee_name FROM user_employees WHERE employment_status<>'RETIRED' ORDER BY sort_no LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$company=$db->query('SELECT id FROM system_company ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if(!$employee||!$company)throw new RuntimeException('검증용 회사 또는 직원을 찾을 수 없습니다.');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$_SESSION['user']=['id'=>$employee['user_id'],'username'=>'vehicle-regression'];
$service=new VehicleLogService($db);$vehicleId='';$tripId='';
try{
    $blocked=false;
    try{$service->saveVehicle(['company_id'=>$company['id'],'ownership_type_code'=>'CORPORATE','vehicle_no'=>'TEST-CORPORATE-NO-ASSET','vehicle_name'=>'검증차량']);}catch(InvalidArgumentException){$blocked=true;}
    if(!$blocked)throw new RuntimeException('법인차량 자산연결 필수 검증이 작동하지 않습니다.');
    $vehicleNo='TEST-'.date('His').'-'.bin2hex(random_bytes(2));
    $vehicle=$service->saveVehicle(['company_id'=>$company['id'],'ownership_type_code'=>'PERSONAL','vehicle_no'=>$vehicleNo,'vehicle_name'=>'개인차량 회귀검증','default_employee_id'=>$employee['id'],'is_active'=>1]);
    $vehicleId=$vehicle['data']['id'];
    $trip=$service->saveTrip(['trip_date'=>date('Y-m-d'),'vehicle_id'=>$vehicleId,'employee_id'=>$employee['id'],'purpose_type_code'=>'BUSINESS','purpose'=>'차량운행기록부 회귀검증','origin_location'=>'출발지','destination_location'=>'도착지','start_odometer_km'=>100,'end_odometer_km'=>112.5,'distance_km'=>12.5,'business_distance_km'=>12.5,'source_type_code'=>'MANUAL','capture_status_code'=>'COMPLETE']);
    $tripId=$trip['data']['id'];
    $list=$service->list(['vehicle_id'=>$vehicleId]);$row=$list['rows'][0]??null;
    if(!$row||abs((float)$row['distance_km']-12.5)>0.001||(int)$row['is_tax_reportable']!==0)throw new RuntimeException('개인차량 운행거리 또는 세무제출 제외 Projection이 올바르지 않습니다.');
    if(abs((float)$list['summary']['business_distance_km']-12.5)>0.001)throw new RuntimeException('운행 집계가 올바르지 않습니다.');
    $service->delete($tripId);$service->restore($tripId);
    echo json_encode(['success'=>true,'corporate_asset_required'=>true,'personal_tax_reportable'=>false,'distance_km'=>12.5,'delete_restore'=>true],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
}finally{
    if($tripId!==''){$stmt=$db->prepare('DELETE FROM ledger_vehicle_trip_logs WHERE id=?');$stmt->execute([$tripId]);}
    if($vehicleId!==''){$stmt=$db->prepare('DELETE FROM ledger_vehicles WHERE id=?');$stmt->execute([$vehicleId]);}
}
