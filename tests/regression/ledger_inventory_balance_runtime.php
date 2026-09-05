<?php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__, 2));
require PROJECT_ROOT . '/vendor/autoload.php';
require_once PROJECT_ROOT . '/core/Storage.php';

use App\Services\Ledger\InventoryBalanceService;
use Core\DbPdo;
use Core\Helpers\ActorHelper;

$db=DbPdo::conn();
$company=(string)$db->query('SELECT id FROM system_company ORDER BY id LIMIT 1')->fetchColumn();
$project=(string)$db->query('SELECT id FROM system_projects WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
if($company===''||$project==='')throw new RuntimeException('회사와 프로젝트 검증자료가 필요합니다.');
$before=['headers'=>(int)$db->query('SELECT COUNT(*) FROM ledger_inventory_balances')->fetchColumn(),'items'=>(int)$db->query('SELECT COUNT(*) FROM ledger_inventory_balance_items')->fetchColumn()];
$view=(string)file_get_contents(PROJECT_ROOT.'/app/views/ledger/inventory-balances/index.php');
$script=(string)file_get_contents(PROJECT_ROOT.'/public/assets/js/pages/ledger/inventory-balances/index.js');
$db->beginTransaction();
try{
    $service=new InventoryBalanceService($db);
    $actor=ActorHelper::system('INVENTORY_BALANCE_REGRESSION');
    $saved=$service->save(['company_id'=>$company,'fiscal_year'=>2998,'note'=>'재고관리 회귀검증','items'=>[
        ['business_unit'=>'CONSTRUCTION','project_id'=>$project,'inventory_category_code'=>'RAW_MATERIAL','item_name'=>'현장 자재','opening_amount'=>100000,'increase_amount'=>50000,'decrease_amount'=>30000,'calculation_basis'=>'기초명세와 구매·사용 확인자료 합계','evidence_reference'=>'회귀검증 증거자료'],
        ['business_unit'=>'ECOMMERCE','project_id'=>'','inventory_category_code'=>'MERCHANDISE','item_name'=>'판매 상품','opening_amount'=>20000,'increase_amount'=>10000,'decrease_amount'=>5000,'calculation_basis'=>'판매상품 입고 및 판매자료 합계','evidence_reference'=>'회귀검증 판매자료'],
    ]],$actor);
    $id=(string)$saved['data']['id'];$confirmed=$service->confirm($id,true,$actor);$cancelled=$service->confirm($id,false,$actor);
    $checks=['common_table_partial'=>str_contains($view,'/app/views/components/ui-table.php'),'common_data_table'=>str_contains($script,'createDataTable'),'common_table_settings'=>str_contains($script,"enabled:true,pageKey:'ledger.settings.inventory_balances'"),'common_actor_column'=>str_contains($script,"actorColumn('updated_by','수정자')"),'item_count'=>count($saved['data']['items'])===2,'opening_total'=>(float)$saved['data']['opening_amount']===120000.0,'increase_total'=>(float)$saved['data']['increase_amount']===60000.0,'decrease_total'=>(float)$saved['data']['decrease_amount']===35000.0,'ending_total'=>(float)$saved['data']['ending_amount']===145000.0,'confirmed'=>$confirmed['data']['status_code']==='CONFIRMED','cancelled'=>$cancelled['data']['status_code']==='DRAFT'];
    if(in_array(false,$checks,true))throw new RuntimeException('재고관리 Runtime 불변식이 실패했습니다.');
    $service->delete($id,$actor);$db->rollBack();
    $after=['headers'=>(int)$db->query('SELECT COUNT(*) FROM ledger_inventory_balances')->fetchColumn(),'items'=>(int)$db->query('SELECT COUNT(*) FROM ledger_inventory_balance_items')->fetchColumn()];
    $passed=$before===$after;echo json_encode(['passed'=>$passed,'checks'=>$checks,'before'=>$before,'after'=>$after,'rolled_back'=>$passed],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($passed?0:1);
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
