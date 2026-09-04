<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\Institution\BusinessIncomeModel;
use App\Services\Approval\ApprovalWorkflowService;
use Core\Helpers\ActorHelper;
use Core\Helpers\AuthHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

final class BusinessIncomeService
{
    public const DOCUMENT_TYPE='BUSINESS_INCOME';
    private BusinessIncomeModel $model;
    private BusinessIncomeCalculationService $calculator;
    private BusinessIncomeTaxProfileService $taxProfiles;
    private DailyEmploymentIncomeBusinessUnitPolicyService $businessUnitPolicy;
    private LoggerInterface $logger;
    private ?array $unitNames = null;
    public function __construct(private readonly PDO $db){$this->model=new BusinessIncomeModel($db);$this->calculator=new BusinessIncomeCalculationService($db);$this->taxProfiles=new BusinessIncomeTaxProfileService($db);$this->businessUnitPolicy=new DailyEmploymentIncomeBusinessUnitPolicyService();$this->logger=LoggerFactory::getLogger('service-institution-business-income');}

    public function page(array $request):array{return ['success'=>true]+$this->model->page($request);}
    public function detail(string $id):array{$row=$this->model->detail($id);if(!$row)throw new \RuntimeException('사업소득 문서를 찾을 수 없습니다.');return ['success'=>true,'data'=>$row];}
    public function options(array $input=[]):array
    {
        $options=$this->model->options($input);
        if(trim((string)($input['option_type']??''))===''){
            $options['business_units']=array_map(fn(array $row):array=>$this->businessUnitPolicy->fromCodeRow([
                'code'=>$row['id'],'code_name'=>$row['name'],'sort_no'=>$row['sort_no'],'extra_data'=>$row['extra_data'],
            ]),$options['business_units']??[]);
        }
        return ['success'=>true,'data'=>$options];
    }

    public function calculate(array $payload):array
    {
        $month=trim((string)($payload['income_year_month']??''));$referenceDate=$this->withholdingDate($payload['withholding_date']??null);
        $groups=$this->normalizeGroups($payload['groups']??[],false,$month,$referenceDate);
        return ['success'=>true,'data'=>['groups'=>$groups,'totals'=>$this->totals($groups),'statutory_reference_date'=>$referenceDate]];
    }

    public function save(array $payload):array
    {
        return $this->logged('BUSINESS_INCOME_SAVED','save',['target_id'=>trim((string)($payload['id']??''))?:null],fn():array=>$this->saveInternal($payload));
    }

    private function saveInternal(array $payload):array
    {
        $id=trim((string)($payload['id']??''));$month=trim((string)($payload['income_year_month']??''));$withholdingDate=$this->withholdingDate($payload['withholding_date']??null);$title=trim((string)($payload['title']??''));
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)||$title==='')throw new \InvalidArgumentException('귀속연월과 제목을 확인해 주세요.');
        $groups=$this->normalizeGroups($payload['groups']??[],true,$month,$withholdingDate);$actor=ActorHelper::user();$owns=!$this->db->inTransaction();
        if($owns)$this->db->beginTransaction();
        try{
            if($id===''){$id=UuidHelper::generate();$this->model->insert('institution_business_incomes',['id'=>$id,'income_year_month'=>$month,'withholding_date'=>$withholdingDate,'title'=>$title,'description'=>$payload['description']??null,'memo'=>$payload['memo']??null,'created_by'=>$actor,'updated_by'=>$actor]);}
            else{$current=$this->model->detail($id,true);if(!$current)throw new \RuntimeException('사업소득 문서를 찾을 수 없습니다.');if(!in_array($current['document_status'],['DRAFT','REJECTED','WITHDRAWN'],true))throw new \RuntimeException('현재 상태에서는 수정할 수 없습니다.');$this->model->update('institution_business_incomes',$id,['income_year_month'=>$month,'withholding_date'=>$withholdingDate,'title'=>$title,'description'=>$payload['description']??null,'memo'=>$payload['memo']??null,'updated_by'=>$actor]);$this->softDeleteAggregate($id,$actor);}
            $revisionId=$this->persistAggregate($id,$groups,$actor,$month,$withholdingDate);
            $this->model->update('institution_business_incomes',$id,['current_calculation_revision_id'=>$revisionId,'calculation_status'=>'CALCULATED','updated_by'=>$actor]+$this->headerTotals($groups));
            if($owns)$this->db->commit();return ['success'=>true,'data'=>['id'=>$id,'calculation_revision_id'=>$revisionId],'message'=>'저장되었습니다.'];
        }catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function submit(string $id):array
    {
        return $this->logged('BUSINESS_INCOME_SUBMITTED','submit',['target_id'=>$id],fn():array=>$this->submitInternal($id));
    }

    private function submitInternal(string $id):array
    {
        $owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();
        try{$header=$this->assertSubmissionReady($id,true);$actor=ActorHelper::user();$result=(new ApprovalWorkflowService($this->db))->submit(self::DOCUMENT_TYPE,$id,(string)AuthHelper::userId(),$actor);
            $this->model->update('institution_business_incomes',$id,['document_status'=>'PENDING','approval_status'=>'PENDING','current_approval_request_id'=>$result['request_id'],'updated_by'=>$actor]);
            if($owns)$this->db->commit();return ['success'=>true,'data'=>$result,'message'=>'결재요청이 완료되었습니다.'];
        }catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function submissionPreflight(string $id):array
    {
        $header=$this->assertSubmissionReady($id,false);
        return ['success'=>true,'data'=>['ready'=>true,'document_id'=>$id,'calculation_revision_id'=>$header['current_calculation_revision_id']]];
    }

    public function withdraw(string $requestId):array
    {
        return $this->logged('BUSINESS_INCOME_WITHDRAWN','withdraw',['request_id'=>$requestId],fn():array=>$this->withdrawInternal($requestId));
    }

    private function withdrawInternal(string $requestId):array
    {
        if($requestId==='')throw new \InvalidArgumentException('회수할 결재요청을 확인해 주세요.');
        $owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();
        try{$actor=ActorHelper::user();$request=(new ApprovalWorkflowService($this->db))->withdraw($requestId,self::DOCUMENT_TYPE,(string)AuthHelper::userId(),$actor);$this->model->update('institution_business_incomes',(string)$request['document_id'],['document_status'=>'WITHDRAWN','approval_status'=>'WITHDRAWN','updated_by'=>$actor]);if($owns)$this->db->commit();return ['success'=>true,'message'=>'기안을 회수했습니다.'];}
        catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function delete(string $id):array
    {
        return $this->logged('BUSINESS_INCOME_DELETED','delete',['target_id'=>$id],fn():array=>$this->deleteInternal($id));
    }

    private function deleteInternal(string $id):array
    {
        $header=$this->model->detail($id,true);if(!$header)throw new \RuntimeException('사업소득 문서를 찾을 수 없습니다.');
        if(!in_array($header['document_status'],['DRAFT','REJECTED','WITHDRAWN'],true))throw new \RuntimeException('현재 상태에서는 삭제할 수 없습니다.');
        $this->model->update('institution_business_incomes',$id,['deleted_at'=>date('Y-m-d H:i:s'),'deleted_by'=>ActorHelper::user(),'updated_by'=>ActorHelper::user()]);return ['success'=>true,'message'=>'삭제되었습니다.'];
    }

    public function trash():array
    {
        $rows=$this->db->query('SELECT id,income_year_month,title,document_status,deleted_at FROM institution_business_incomes WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC,id')->fetchAll(PDO::FETCH_ASSOC)?:[];return ['success'=>true,'data'=>$rows];
    }

    public function restore(string $id):array
    {
        return $this->logged('BUSINESS_INCOME_RESTORED','restore',['target_id'=>$id],fn():array=>$this->restoreInternal($id));
    }

    private function restoreInternal(string $id):array
    {
        $this->model->update('institution_business_incomes',$id,['deleted_at'=>null,'deleted_by'=>null,'updated_by'=>ActorHelper::user()]);return ['success'=>true,'message'=>'복구되었습니다.'];
    }

    public function purge(string $id):array
    {
        return $this->logged('BUSINESS_INCOME_PURGED','purge',['target_id'=>$id],fn():array=>$this->purgeInternal($id),true);
    }

    private function purgeInternal(string $id):array
    {
        $this->db->beginTransaction();try{$check=$this->db->prepare('SELECT document_status FROM institution_business_incomes WHERE id=:id AND deleted_at IS NOT NULL FOR UPDATE');$check->execute([':id'=>$id]);$status=$check->fetchColumn();if($status===false)throw new \RuntimeException('휴지통 문서를 찾을 수 없습니다.');if($status==='APPROVED')throw new \RuntimeException('승인 완료 문서는 영구삭제할 수 없습니다.');$groupIds=$this->db->prepare('SELECT id FROM institution_business_income_groups WHERE business_income_id=:id');$groupIds->execute([':id'=>$id]);$ids=$groupIds->fetchAll(PDO::FETCH_COLUMN)?:[];if($ids!==[]){$marks=implode(',',array_fill(0,count($ids),'?'));$itemIds=$this->db->prepare('SELECT id FROM institution_business_income_items WHERE group_id IN ('.$marks.')');$itemIds->execute($ids);$items=$itemIds->fetchAll(PDO::FETCH_COLUMN)?:[];if($items!==[]){$itemMarks=implode(',',array_fill(0,count($items),'?'));$this->db->prepare('DELETE FROM institution_business_income_calculation_lines WHERE business_income_item_id IN ('.$itemMarks.')')->execute($items);$this->db->prepare('DELETE FROM institution_business_income_work_lines WHERE business_income_item_id IN ('.$itemMarks.')')->execute($items);$this->db->prepare('DELETE FROM institution_business_income_items WHERE id IN ('.$itemMarks.')')->execute($items);}$this->db->prepare('DELETE FROM institution_business_income_groups WHERE id IN ('.$marks.')')->execute($ids);}$this->db->prepare('UPDATE institution_business_incomes SET current_calculation_revision_id=NULL WHERE id=:id')->execute([':id'=>$id]);$this->db->prepare('DELETE FROM institution_business_income_calculation_revisions WHERE business_income_id=:id')->execute([':id'=>$id]);$this->db->prepare('DELETE FROM institution_business_incomes WHERE id=:id')->execute([':id'=>$id]);$this->db->commit();return ['success'=>true,'message'=>'영구삭제되었습니다.'];}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function act(string $stepId,string $decision,?string $comment):array
    {
        return $this->logged('BUSINESS_INCOME_APPROVAL_ACTED','act',['step_id'=>$stepId,'decision'=>strtoupper(trim($decision))],fn():array=>$this->actInternal($stepId,$decision,$comment));
    }

    private function actInternal(string $stepId,string $decision,?string $comment):array
    {
        $owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();
        try{$userId=(string)AuthHelper::userId();$actor=ActorHelper::user();
            if(strtolower(trim($decision))==='approved'){
                $replay=$this->db->prepare("SELECT request_row.id request_id,request_row.document_id FROM user_approval_request_steps step_row JOIN user_approval_requests request_row ON request_row.id=step_row.request_id WHERE step_row.id=:step_id AND step_row.status='approved' AND step_row.acted_by=:user_id AND request_row.document_type=:document_type AND request_row.status='approved' LIMIT 1 FOR UPDATE");
                $replay->execute([':step_id'=>$stepId,':user_id'=>$userId,':document_type'=>self::DOCUMENT_TYPE]);$approved=$replay->fetch(PDO::FETCH_ASSOC);
                if($approved){$generated=(new BusinessIncomeTransactionGenerationService($this->db))->generate((string)$approved['document_id'],(string)$approved['request_id'],$actor);if($owns)$this->db->commit();return ['success'=>true,'data'=>['state'=>'APPROVED','closure'=>$generated]];}
            }
            $result=(new ApprovalWorkflowService($this->db))->act($stepId,self::DOCUMENT_TYPE,$decision,$comment,$userId,$actor);
            if(($result['state']??'')==='APPROVED'){
                $result['closure']=(new BusinessIncomeTransactionGenerationService($this->db))->generate((string)$result['request']['document_id'],(string)$result['request']['id'],$actor);
            }else{
                $status=($result['state']??'')==='REJECTED'?'REJECTED':'PENDING';
                $this->model->update('institution_business_incomes',(string)$result['request']['document_id'],['document_status'=>$status,'approval_status'=>$status,'updated_by'=>$actor]);
            }
            if($owns)$this->db->commit();return ['success'=>true,'data'=>$result];
        }catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function approvalDetail(array $request):array
    {
        $document=$this->detail((string)($request['document_id']??''))['data'];
        $items=[];
        foreach($document['groups'] as $group){
            foreach($group['items'] as $item){
                $items[]=$item+[
                    'transaction_date'=>$item['transaction_date'],
                    'business_unit'=>$group['business_unit'],
                    'project_id'=>$group['project_id'],
                    'work_team_id'=>$group['work_team_id'],
                    'work_line_count'=>count($item['work_lines']??[]),
                ];
            }
        }
        return [
            'type'=>self::DOCUMENT_TYPE,
            'type_name'=>'사업소득',
            'header'=>array_merge($request,$document),
            'items'=>$items,
            'totals'=>$this->totals($document['groups']),
            'detail_supported'=>true,
            'attachments'=>[],
            'attachment_supported'=>false,
        ];
    }

    private function normalizeGroups(mixed $groups,bool $forSave,string $incomeYearMonth,string $withholdingDate):array
    {
        $statutoryReferenceDate=$withholdingDate;
        if(!is_array($groups)||$groups===[])throw new \InvalidArgumentException('지급 그룹을 한 건 이상 입력해 주세요.');$result=[];
        foreach(array_values($groups) as $groupIndex=>$group){$businessUnit=strtoupper(trim((string)($group['business_unit']??'')));$projectId=trim((string)($group['project_id']??''))?:null;$workTeamId=trim((string)($group['work_team_id']??''))?:null;$items=[];if(!is_array($group['items']??null)||$group['items']===[])throw new \InvalidArgumentException('소득자를 한 명 이상 입력해 주세요.');
            foreach(array_values($group['items']) as $itemIndex=>$item){
                $date=trim((string)($item['transaction_date']??''));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new \InvalidArgumentException(($itemIndex+1).'번째 소득자 거래일을 확인해 주세요.');
                $clientId=trim((string)($item['client_id']??''));$profile=$this->taxProfiles->resolve($clientId,$date);
                $workLines=$this->normalizeWorkLines($item['work_lines']??null,$itemIndex);$gross=round(array_sum(array_column($workLines,'final_amount')),2);
                $calculated=$this->calculator->calculate($statutoryReferenceDate,$gross);
                $items[]=array_merge($item,$calculated,['transaction_date'=>$date,'gross_payment_amount'=>$gross,'work_lines'=>$workLines,'client_tax_profile_id'=>$profile['id'],'recipient_tax_snapshot_json'=>$profile,'sort_no'=>$itemIndex+1]);
            }
            $this->model->assertReferences($businessUnit,$projectId,$workTeamId,$items[0]['transaction_date']);
            $result[]=['business_unit'=>$businessUnit,'project_id'=>$projectId,'work_team_id'=>$workTeamId,'group_description'=>trim((string)($group['group_description']??''))?:null,'sort_no'=>$groupIndex+1,'items'=>$items];
        }return $result;
    }

    private function normalizeWorkLines(mixed $workLines,int $itemIndex):array
    {
        if(!is_array($workLines)||$workLines===[])throw new \InvalidArgumentException(($itemIndex+1).'번째 소득자의 외주 작업내역을 한 건 이상 입력해 주세요.');
        $normalized=[];
        foreach(array_values($workLines) as $lineIndex=>$line){
            $name=trim((string)($line['item_name']??''));$unitInput=trim((string)($line['item_unit_name']??''));$this->unitNames??=$this->model->activeUnitNames();$unit=$this->unitNames[$unitInput]??'';$quantity=round((float)($line['item_quantity']??0),4);$unitPrice=round((float)($line['item_unit_price']??0),2);$adjustment=round((float)($line['adjustment_amount']??0),2);$reason=trim((string)($line['adjustment_reason']??''))?:null;
            if($name===''||$unit===''||$quantity<=0||$unitPrice<0)throw new \InvalidArgumentException(($itemIndex+1).'번째 소득자의 '.($lineIndex+1).'번째 작업내역 품명·단위·수량·단가를 확인해 주세요. 단위는 코드관리의 활성 단위에서 선택해야 합니다.');
            if($adjustment!==0.0&&$reason===null)throw new \InvalidArgumentException(($itemIndex+1).'번째 소득자의 '.($lineIndex+1).'번째 증감 사유를 입력해 주세요.');
            $calculated=round($quantity*$unitPrice,2);$final=round($calculated+$adjustment,2);if($final<0)throw new \InvalidArgumentException('작업 확정금액은 0원보다 작을 수 없습니다.');
            $normalized[]=['item_name'=>$name,'item_specification'=>trim((string)($line['item_specification']??''))?:null,'item_unit_name'=>$unit,'item_quantity'=>$quantity,'item_unit_price'=>$unitPrice,'calculated_amount'=>$calculated,'adjustment_amount'=>$adjustment,'adjustment_reason'=>$reason,'final_amount'=>$final,'sort_no'=>$lineIndex+1];
        }
        return $normalized;
    }

    private function persistAggregate(string $id,array $groups,string $actor,string $incomeYearMonth,string $withholdingDate):string
    {
        $revisionId=UuidHelper::generate();$revisionNo=$this->model->nextRevisionNo($id);$hash=$this->sourceHash($groups,$incomeYearMonth,$withholdingDate);
        $this->model->insert('institution_business_income_calculation_revisions',['id'=>$revisionId,'business_income_id'=>$id,'revision_no'=>$revisionNo,'revision_status'=>'CONFIRMED','calculation_date'=>date('Y-m-d'),'policy_status'=>'READY','source_hash'=>$hash,'calculated_at'=>date('Y-m-d H:i:s'),'calculated_by'=>$actor,'created_by'=>$actor]);
        foreach($groups as $group){$groupId=UuidHelper::generate();$this->model->insert('institution_business_income_groups',['id'=>$groupId,'business_income_id'=>$id,'business_unit'=>$group['business_unit'],'project_id'=>$group['project_id'],'work_team_id'=>$group['work_team_id'],'group_description'=>$group['group_description'],'sort_no'=>$group['sort_no'],'created_by'=>$actor,'updated_by'=>$actor]);
            foreach($group['items'] as $item){$itemId=UuidHelper::generate();$this->model->insert('institution_business_income_items',['id'=>$itemId,'group_id'=>$groupId,'client_id'=>$item['client_id'],'client_tax_profile_id'=>$item['client_tax_profile_id'],'transaction_date'=>$item['transaction_date'],'service_type_code'=>$item['service_type_code'],'service_description'=>$item['service_description']??null,'gross_payment_amount'=>$item['gross_payment_amount'],'income_tax_amount'=>$item['income_tax_amount'],'local_income_tax_amount'=>$item['local_income_tax_amount'],'total_deduction_amount'=>$item['total_deduction_amount'],'net_payment_amount'=>$item['net_payment_amount'],'recipient_tax_snapshot_json'=>json_encode($item['recipient_tax_snapshot_json'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'sort_no'=>$item['sort_no'],'created_by'=>$actor,'updated_by'=>$actor]);foreach($item['work_lines'] as $workLine)$this->model->insert('institution_business_income_work_lines',['id'=>UuidHelper::generate(),'business_income_item_id'=>$itemId,'created_by'=>$actor,'updated_by'=>$actor]+$workLine);foreach($item['lines'] as $line){$this->model->insert('institution_business_income_calculation_lines',['id'=>UuidHelper::generate(),'calculation_revision_id'=>$revisionId,'business_income_item_id'=>$itemId,'created_by'=>$actor]+$line);}}
        }return $revisionId;
    }

    private function softDeleteAggregate(string $id,string $actor):void{$now=date('Y-m-d H:i:s');$this->db->prepare('UPDATE institution_business_income_work_lines work_line JOIN institution_business_income_items item ON item.id=work_line.business_income_item_id JOIN institution_business_income_groups business_group ON business_group.id=item.group_id SET work_line.deleted_at=:now,work_line.deleted_by=:actor WHERE business_group.business_income_id=:id AND work_line.deleted_at IS NULL')->execute([':now'=>$now,':actor'=>$actor,':id'=>$id]);$this->db->prepare('UPDATE institution_business_income_items item JOIN institution_business_income_groups business_group ON business_group.id=item.group_id SET item.deleted_at=:now,item.deleted_by=:actor WHERE business_group.business_income_id=:id AND item.deleted_at IS NULL')->execute([':now'=>$now,':actor'=>$actor,':id'=>$id]);$this->db->prepare('UPDATE institution_business_income_groups SET deleted_at=:now,deleted_by=:actor WHERE business_income_id=:id AND deleted_at IS NULL')->execute([':now'=>$now,':actor'=>$actor,':id'=>$id]);}
    private function totals(array $groups):array{$totals=['gross_payment_amount'=>0.0,'income_tax_amount'=>0.0,'local_income_tax_amount'=>0.0,'total_deduction_amount'=>0.0,'net_payment_amount'=>0.0];foreach($groups as $group)foreach($group['items'] as $item)foreach($totals as $key=>$_)$totals[$key]+=(float)$item[$key];return $totals;}
    private function headerTotals(array $groups):array{$totals=$this->totals($groups);return['group_count'=>count($groups),'item_count'=>array_sum(array_map(static fn(array $group):int=>count($group['items']),$groups)),'total_gross_payment_amount'=>$totals['gross_payment_amount'],'total_income_tax_amount'=>$totals['income_tax_amount'],'total_local_income_tax_amount'=>$totals['local_income_tax_amount'],'total_deduction_amount'=>$totals['total_deduction_amount'],'total_net_payment_amount'=>$totals['net_payment_amount']];}

    private function assertSubmissionReady(string $id,bool $lock):array
    {
        $header=$this->model->detail($id,$lock);if(!$header)throw new \RuntimeException('사업소득 문서를 찾을 수 없습니다.');
        if(!in_array((string)$header['document_status'],['DRAFT','REJECTED','WITHDRAWN'],true))throw new \RuntimeException('현재 상태에서는 결재요청할 수 없습니다.');
        if($header['calculation_status']!=='CALCULATED'||empty($header['current_calculation_revision_id']))throw new \RuntimeException('계산결과를 먼저 확정해 주세요.');
        $storedGroups=$header['groups'];
        $incomeYearMonth=(string)$header['income_year_month'];$statutoryReferenceDate=$this->withholdingDate($header['withholding_date']??null);
        $groups=$this->normalizeGroups($storedGroups,true,$incomeYearMonth,$statutoryReferenceDate);$revision=$this->db->prepare('SELECT source_hash,policy_status FROM institution_business_income_calculation_revisions WHERE id=:id AND business_income_id=:document_id');$revision->execute([':id'=>$header['current_calculation_revision_id'],':document_id'=>$id]);$stored=$revision->fetch(PDO::FETCH_ASSOC);
        if(!$stored||$stored['policy_status']!=='READY'||!hash_equals((string)$stored['source_hash'],$this->sourceHash($groups,$incomeYearMonth,$statutoryReferenceDate)))throw new \RuntimeException('계산결과가 최신 원천자료와 일치하지 않습니다.');
        foreach($header['groups'] as $group){foreach($group['items'] as $item){$calculated=$this->calculator->calculate($statutoryReferenceDate,(float)$item['gross_payment_amount']);foreach(['income_tax_amount','local_income_tax_amount','total_deduction_amount','net_payment_amount'] as $field){if(round((float)$item[$field],2)!==round((float)$calculated[$field],2))throw new \RuntimeException('저장된 계산금액이 원천징수일 법정기준과 일치하지 않습니다.');}}}
        foreach($this->headerTotals($groups) as $field=>$value){if(round((float)($header[$field]??-1),2)!==round((float)$value,2))throw new \RuntimeException('사업소득 문서 합계가 소득자 지급내역과 일치하지 않습니다.');}
        return $header;
    }

    private function sourceHash(array $groups,string $incomeYearMonth,string $withholdingDate):string
    {
        $canonical=['income_year_month'=>$incomeYearMonth,'groups'=>[]];foreach($groups as $group){$groupRow=['business_unit'=>$group['business_unit'],'project_id'=>$group['project_id'],'work_team_id'=>$group['work_team_id'],'group_description'=>$group['group_description'],'sort_no'=>(int)$group['sort_no'],'items'=>[]];foreach($group['items'] as $item){$groupRow['items'][]=['client_id'=>$item['client_id'],'client_tax_profile_id'=>$item['client_tax_profile_id'],'transaction_date'=>$item['transaction_date'],'service_type_code'=>$item['service_type_code'],'service_description'=>$item['service_description']??null,'gross_payment_amount'=>(float)$item['gross_payment_amount'],'income_tax_amount'=>(float)$item['income_tax_amount'],'local_income_tax_amount'=>(float)$item['local_income_tax_amount'],'total_deduction_amount'=>(float)$item['total_deduction_amount'],'net_payment_amount'=>(float)$item['net_payment_amount'],'sort_no'=>(int)$item['sort_no'],'work_lines'=>$item['work_lines']];}$canonical['groups'][]=$groupRow;}
        return hash('sha256',$withholdingDate.'|'.json_encode($canonical,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION));
    }

    private function withholdingDate(mixed $value):string
    {
        $date=trim((string)$value);$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if($parsed===false||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('원천징수일을 확인해 주세요.');
        return $date;
    }

    private function logged(string $eventCode,string $action,array $context,callable $operation,bool $warningOnSuccess=false):array
    {
        $started=microtime(true);$base=['event_code'=>$eventCode,'service'=>self::class,'action'=>$action,'actor'=>ActorHelper::user()]+$context;
        try{$result=$operation();$this->logger->{$warningOnSuccess?'warning':'info'}('사업소득 업무 처리가 완료되었습니다.',$base+['result'=>'SUCCESS','duration_ms'=>(int)round((microtime(true)-$started)*1000)]);return$result;}
        catch(\InvalidArgumentException|\DomainException $e){$this->logger->warning('사업소득 업무 처리가 차단되었습니다.',$base+['result'=>'BLOCKED','error_code'=>get_class($e),'error'=>$e,'duration_ms'=>(int)round((microtime(true)-$started)*1000)]);throw$e;}
        catch(\Throwable $e){$this->logger->error('사업소득 업무 처리에 실패했습니다.',$base+['result'=>'FAILED','error_code'=>get_class($e),'error'=>$e,'duration_ms'=>(int)round((microtime(true)-$started)*1000)]);throw$e;}
    }
}
