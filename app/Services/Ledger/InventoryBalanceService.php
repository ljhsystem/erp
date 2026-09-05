<?php

namespace App\Services\Ledger;

use App\Models\Ledger\InventoryBalanceModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;

class InventoryBalanceService
{
    private InventoryBalanceModel $model;
    private $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->model=new InventoryBalanceModel($pdo);
        $this->logger=LoggerFactory::getLogger('service-ledger-inventory-balance');
    }

    public function getList(array $filters=[]): array
    {
        $rows=array_map(fn(array $row):array=>$this->totals($row),$this->model->list($filters));
        return ActorHelper::enrichActorNames($rows,['created_by_name'=>'created_by','updated_by_name'=>'updated_by','confirmed_by_name'=>'confirmed_by']);
    }

    public function getDetail(string $id): ?array
    {
        $row=$this->model->find($id);if(!$row)return null;
        $items=array_map(function(array $item):array{$item['opening_amount']=(float)$item['opening_amount'];$item['increase_amount']=(float)$item['increase_amount'];$item['decrease_amount']=(float)$item['decrease_amount'];$item['ending_amount']=$item['opening_amount']+$item['increase_amount']-$item['decrease_amount'];return $item;},$this->model->items($id));
        $row['items']=$items;$row=$this->totals($row,$items);
        return ActorHelper::enrichActorNamesRow($row,['created_by_name'=>'created_by','updated_by_name'=>'updated_by','confirmed_by_name'=>'confirmed_by']);
    }

    public function options(): array { return ['companies'=>$this->model->companies(),'projects'=>$this->model->projects(),'business_units'=>$this->model->businessUnits(),'inventory_categories'=>[['id'=>'RAW_MATERIAL','name'=>'원재료'],['id'=>'SUB_MATERIAL','name'=>'부재료'],['id'=>'MERCHANDISE','name'=>'상품'],['id'=>'SUPPLIES','name'=>'저장품'],['id'=>'OTHER','name'=>'기타']]]; }

    public function save(array $input, ?string $actorOverride=null): array
    {
        $id=trim((string)($input['id']??''));$companyId=trim((string)($input['company_id']??''));$year=(int)($input['fiscal_year']??0);$items=$input['items']??[];
        if(is_string($items))$items=json_decode($items,true);
        if($companyId===''||$year<1900||$year>9999)throw new \InvalidArgumentException('회사와 회계연도를 입력해 주세요.');
        if(!is_array($items)||$items===[])throw new \InvalidArgumentException('재고 증감내역을 한 행 이상 입력해 주세요.');
        $normalized=[];
        foreach(array_values($items) as $index=>$item)$normalized[]=$this->normalizeItem((array)$item,$index+1);
        $actor=$actorOverride?:ActorHelper::user();$now=date('Y-m-d H:i:s');$owns=!$this->pdo->inTransaction();
        try{
            if($owns)$this->pdo->beginTransaction();
            $current=$id!==''?$this->model->find($id,true):null;
            if($id!==''&&!$current)throw new \InvalidArgumentException('재고관리 문서를 찾을 수 없습니다.');
            if(($current['status_code']??'')==='CONFIRMED')throw new \InvalidArgumentException('확정된 재고관리 문서는 수정할 수 없습니다. 확정을 취소해 주세요.');
            $duplicate=$this->model->findByCompanyYear($companyId,$year);
            if($duplicate&&(string)$duplicate['id']!==$id)throw new \InvalidArgumentException('해당 회사와 회계연도의 재고관리 문서가 이미 있습니다.');
            if($current){$this->model->updateHeader($id,[':company_id'=>$companyId,':fiscal_year'=>$year,':note'=>$this->nullable($input['note']??null),':updated_at'=>$now,':updated_by'=>$actor]);}
            else{$id=UuidHelper::generate();$this->model->insertHeader([':id'=>$id,':company_id'=>$companyId,':fiscal_year'=>$year,':status_code'=>'DRAFT',':note'=>$this->nullable($input['note']??null),':created_at'=>$now,':created_by'=>$actor,':updated_at'=>$now,':updated_by'=>$actor]);}
            $this->model->replaceItems($id,$normalized,$actor,$now);
            if($owns)$this->pdo->commit();
            $this->logger->info('재고가액 증감 근거를 저장했습니다.',['event_code'=>'INVENTORY_BALANCE_SAVED','result'=>'SUCCESS','service'=>self::class,'action'=>'save','actor'=>$actor,'target_type'=>'ledger_inventory_balances','target_id'=>$id]);
            return ['success'=>true,'message'=>'재고관리 문서를 저장했습니다.','data'=>$this->getDetail($id)];
        }catch(\InvalidArgumentException $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();$this->logger->warning('재고관리 문서를 저장할 수 없습니다.',['event_code'=>'INVENTORY_BALANCE_SAVE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'save','actor'=>$actor,'target_id'=>$id,'error_code'=>$e::class]);throw $e;
        }catch(\Throwable $e){if($owns&&$this->pdo->inTransaction())$this->pdo->rollBack();$this->logger->error('재고관리 문서 저장에 실패했습니다.',['event_code'=>'INVENTORY_BALANCE_SAVE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'save','actor'=>$actor,'target_id'=>$id,'error_code'=>$e::class,'error'=>$e]);throw $e;}
    }

    public function confirm(string $id,bool $confirmed,?string $actorOverride=null): array
    {
        $row=$this->model->find($id);if(!$row)throw new \InvalidArgumentException('재고관리 문서를 찾을 수 없습니다.');
        if($confirmed&&$this->model->items($id)===[])throw new \InvalidArgumentException('재고 증감내역이 없어 확정할 수 없습니다.');
        $actor=$actorOverride?:ActorHelper::user();$this->model->setConfirmed($id,$confirmed,$actor);
        $this->logger->info($confirmed?'기말재고를 확정했습니다.':'기말재고 확정을 취소했습니다.',['event_code'=>$confirmed?'INVENTORY_BALANCE_CONFIRMED':'INVENTORY_BALANCE_CONFIRMATION_CANCELLED','result'=>'SUCCESS','service'=>self::class,'action'=>$confirmed?'confirm':'cancel-confirm','actor'=>$actor,'target_type'=>'ledger_inventory_balances','target_id'=>$id]);
        return ['success'=>true,'message'=>$confirmed?'기말재고를 확정했습니다.':'기말재고 확정을 취소했습니다.','data'=>$this->getDetail($id)];
    }

    public function delete(string $id,?string $actorOverride=null): array
    {
        $row=$this->model->find($id);if(!$row)throw new \InvalidArgumentException('재고관리 문서를 찾을 수 없습니다.');if($row['status_code']==='CONFIRMED')throw new \InvalidArgumentException('확정된 재고관리 문서는 삭제할 수 없습니다.');
        $this->model->delete($id);$actor=$actorOverride?:ActorHelper::user();$this->logger->info('재고관리 문서를 삭제했습니다.',['event_code'=>'INVENTORY_BALANCE_DELETED','result'=>'SUCCESS','service'=>self::class,'action'=>'delete','actor'=>$actor,'target_type'=>'ledger_inventory_balances','target_id'=>$id]);return ['success'=>true,'message'=>'재고관리 문서를 삭제했습니다.'];
    }

    private function normalizeItem(array $item,int $sort): array
    {
        $business=trim((string)($item['business_unit']??''));$project=$this->nullable($item['project_id']??null);$category=trim((string)($item['inventory_category_code']??''));$name=trim((string)($item['item_name']??''));$basis=trim((string)($item['calculation_basis']??''));$evidence=trim((string)($item['evidence_reference']??''));
        if($business===''||$category===''||$name===''||$basis===''||$evidence==='')throw new \InvalidArgumentException("{$sort}번 재고행의 사업구분, 재고구분, 품명, 산출근거와 증거자료를 입력해 주세요.");
        if($business==='CONSTRUCTION'&&$project===null)throw new \InvalidArgumentException("{$sort}번 전문건설업 재고행의 프로젝트를 선택해 주세요.");
        $opening=max(0,(float)($item['opening_amount']??0));$increase=max(0,(float)($item['increase_amount']??0));$decrease=max(0,(float)($item['decrease_amount']??0));
        if($opening+$increase+$decrease<=0)throw new \InvalidArgumentException("{$sort}번 재고행의 기초·증가·감소 금액 중 하나 이상을 입력해 주세요.");
        if($opening+$increase-$decrease<0)throw new \InvalidArgumentException("{$sort}번 재고행의 기말재고가 음수가 될 수 없습니다.");
        return [':id'=>UuidHelper::generate(),':sort_no'=>$sort,':business_unit'=>$business,':project_id'=>$project,':inventory_category_code'=>$category,':item_name'=>$name,':opening_amount'=>$opening,':increase_amount'=>$increase,':decrease_amount'=>$decrease,':calculation_basis'=>$basis,':evidence_reference'=>$evidence,':note'=>$this->nullable($item['note']??null)];
    }
    private function totals(array $row,array $items=[]): array { $opening=$items?array_sum(array_column($items,'opening_amount')):(float)($row['opening_amount']??0);$increase=$items?array_sum(array_column($items,'increase_amount')):(float)($row['increase_amount']??0);$decrease=$items?array_sum(array_column($items,'decrease_amount')):(float)($row['decrease_amount']??0);$row['opening_amount']=$opening;$row['increase_amount']=$increase;$row['decrease_amount']=$decrease;$row['current_change_amount']=$increase-$decrease;$row['ending_amount']=$opening+$increase-$decrease;return $row; }
    private function nullable(mixed $value): ?string { $value=trim((string)$value);return $value===''?null:$value; }
}
