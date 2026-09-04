<?php

namespace App\Services\Institution;

use App\Models\Institution\RegularEmploymentIncomeModel;
use App\Models\Institution\EmploymentContractModel;
use App\Services\Approval\ApprovalWorkflowService;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class RegularEmploymentIncomeService
{
    public const DOCUMENT_TYPE='REGULAR_EMPLOYMENT_INCOME';
    public const IMPORT_TYPE='PAYROLL_REPORT';
    private RegularEmploymentIncomeModel $model;private EmploymentContractValidityService $contracts;private EmploymentContractModel $contractModel;private RegularEmploymentIncomeFieldPolicyService $fieldPolicy;private LoggerInterface $logger;
    public function __construct(private readonly PDO $db){$this->model=new RegularEmploymentIncomeModel($db);$this->contractModel=new EmploymentContractModel($db);$this->contracts=new EmploymentContractValidityService($this->contractModel);$this->fieldPolicy=new RegularEmploymentIncomeFieldPolicyService($db);$this->logger=LoggerFactory::getLogger('service-institution-regular-employment-income');}
    public function page(array$q):array{$p=$this->model->page($q);return ['success'=>true,'data'=>$p['rows'],'draw'=>(int)($q['draw']??0),'recordsTotal'=>$p['total'],'recordsFiltered'=>$p['filtered']];}
    public function detail(string$id):array{$h=$this->model->find($id);if(!$h)throw new \RuntimeException('상용근로소득 문서를 찾을 수 없습니다.');$items=$this->model->items($id);$employeeIds=array_values(array_unique(array_map(static fn(array$item):string=>(string)$item['employee_id'],$items)));$coverageRows=$employeeIds===[]?[]:(new SocialInsuranceService($this->db))->batchForMonth($employeeIds,(string)$h['income_year_month'])['coverages'];$coverageByEmployee=[];foreach($coverageRows as$coverage)$coverageByEmployee[(string)$coverage['employee_id']][]=$coverage;$payPolicy=new RegularEmploymentIncomePayLineService();$projection=new RegularEmploymentIncomeInsuranceProjectionService();foreach($items as&$item){$lines=$this->model->lineItems((string)$item['id']);$contractId=trim((string)($item['employment_contract_id']??''));$contract=$contractId!==''?$this->contractModel->find($contractId,true):null;$lines=$this->projectContractInsuranceReasons($lines,$contract);$item['line_items']=$projection->project($lines,$contract,$coverageByEmployee[(string)$item['employee_id']]??[],$item['updated_at']??$h['updated_at']??null);$item=array_merge($item,$payPolicy->totals($item['line_items']));$item['calculation_bases']=$this->model->calculationBases((string)$item['id']);}unset($item);return['success'=>true,'data'=>['header'=>$h,'items'=>$items,'readiness'=>$this->readiness($items)]];}
    private function eligibleRows(string $month): array{return$this->candidateSelection($month)['candidates'];}
    private function candidateSelection(string $month): array
    {
        $from=$month.'-01';$to=date('Y-m-t',strtotime($from));
        $employees=$this->model->employmentCandidatesForPeriod($from,$to);
        $contracts=$this->contracts->effectiveContractsForPeriod($from,$to);
        $contractsByEmployee=[];
        foreach($contracts as$contract)$contractsByEmployee[(string)$contract['employee_id']][]=$contract;
        $singleContractIds=[];$excluded=[];$excludedIds=[];
        foreach($employees as$employee){
            $employeeId=(string)$employee['employee_id'];$employeeContracts=$contractsByEmployee[$employeeId]??[];
            if(count($employeeContracts)===1){$singleContractIds[]=(string)$employeeContracts[0]['id'];continue;}
            $reasonCode=$employeeContracts===[]?'NO_VALID_EMPLOYMENT_CONTRACT':'MULTIPLE_VALID_EMPLOYMENT_CONTRACTS';
            $reason=$employeeContracts===[]?'귀속월과 겹치는 유효 근로계약이 없습니다.':'귀속월과 겹치는 유효 근로계약이 여러 건입니다.';
            $excluded[]=$employee+['reason_code'=>$reasonCode,'reason'=>$reason,'reason_message'=>$reason,'contract_count'=>count($employeeContracts)];
            $excludedIds[$employeeId]=true;
        }
        $rows=$this->model->eligibleEmployees($month,array_values(array_unique($singleContractIds)));
        $employeeMap=[];foreach($employees as$employee)$employeeMap[(string)$employee['employee_id']]=$employee;
        $candidates=[];
        foreach($rows as$row){$employeeId=(string)$row['employee_id'];if(isset($employeeMap[$employeeId]))$candidates[]=$row+['calculation_status_code'=>'NEEDS_CONFIRMATION'];}
        $returned=[];foreach($candidates as$row)$returned[(string)$row['employee_id']]=true;
        foreach($employees as$employee){
            $employeeId=(string)$employee['employee_id'];
            if(isset($returned[$employeeId])||isset($excludedIds[$employeeId]))continue;
            if(count($contractsByEmployee[$employeeId]??[])===1){
                $reason='직원 인사이력 스냅샷을 확정할 수 없습니다.';
                $excluded[]=$employee+['reason_code'=>'EMPLOYEE_SNAPSHOT_UNAVAILABLE','reason'=>$reason,'reason_message'=>$reason,'contract_count'=>1];
                $excludedIds[$employeeId]=true;
            }
        }
        return['candidates'=>$candidates,'excluded'=>$excluded,'summary'=>[
            'employed_count'=>count($employees),'candidate_count'=>count($candidates),'excluded_count'=>count($excluded),'blocked_count'=>0,
            'period_from'=>$from,'period_to'=>$to,
        ]];
    }
    public function eligibleEmployees(string$month):array{if(!preg_match('/^\d{4}-\d{2}$/',$month))throw new \InvalidArgumentException('귀속연월을 확인해 주세요.');return['success'=>true,'data'=>$this->candidateSelection($month)];}
    public function calculate(array$input):array
    {
        $employees=$input['employees']??($input['employee_ids']??[]);if(is_string($employees))$employees=json_decode($employees,true);
        $month=trim((string)($input['income_year_month']??''));$withholdingDate=$this->withholdingDate($input['withholding_date']??null);$result=(new RegularEmploymentIncomeCalculationService($this->db))->preview($month,$withholdingDate,is_array($employees)?$employees:[],ActorHelper::user());
        return['success'=>true,'data'=>$result];
    }
    public function savePayEffect(array $input): array
    {
        return $this->savePayEffectWithActor($input, ActorHelper::user());
    }
    public function recalculatePayEffectAsSystem(array $input): array
    {
        return $this->savePayEffectWithActor($input, ActorHelper::system('REGULAR_INCOME_RECALCULATION'));
    }
    private function savePayEffectWithActor(array $input, string $actor): array
    {
        return $this->transaction(function () use ($input, $actor): array {
            $id = trim((string) ($input['id'] ?? ''));
            $month = trim((string) ($input['income_year_month'] ?? ''));
            $withholdingDate = $this->withholdingDate($input['withholding_date'] ?? null);
            $title = trim((string) ($input['title'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $month) || $title === '') {
                throw new \InvalidArgumentException('귀속연월과 제목을 확인해 주세요.');
            }
            $items = is_array($input['items'] ?? null) ? $input['items'] : [];
            if ($items === []) throw new \InvalidArgumentException('직원별 소득내역을 한 건 이상 입력해 주세요.');
            $existing = $id !== '' ? $this->model->find($id, true) : null;
            if ($id !== '' && !$existing) throw new \RuntimeException('수정할 문서를 찾을 수 없습니다.');
            if ($existing && !in_array($existing['document_status'], ['DRAFT','REJECTED','WITHDRAWN'], true)) throw new \RuntimeException('현재 상태에서는 수정할 수 없습니다.');
            $requestKey = trim((string) ($input['request_key'] ?? ''));
            $payloadHash = $requestKey === '' ? null : $this->payloadHash($input);
            if ($existing && $requestKey !== '') {
                $requestAudit = $this->model->findAuditByRequestKey($id, $requestKey);
                if ($requestAudit) {
                    $recorded = json_decode((string) ($requestAudit['after_value'] ?? ''), true);
                    if (($recorded['payload_hash'] ?? null) !== $payloadHash) {
                        throw new \DomainException('같은 요청키에 다른 급여 계산 Payload를 사용할 수 없습니다.');
                    }
                    return ['success'=>true,'data'=>['id'=>$id],'message'=>'이미 처리된 동일 요청입니다.'];
                }
            }
            $header = ['income_year_month'=>$month,'withholding_date'=>$withholdingDate,'title'=>$title,'description'=>$input['description']??null,'memo'=>$input['memo']??null,'calculation_version'=>RegularEmploymentIncomeCalculationService::VERSION,'calculation_source_code'=>'CALCULATED','updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor];
            if ($id === '') { $id=UuidHelper::generate();$this->model->insertHeader(['id'=>$id,'sort_no'=>$this->model->nextSortNo(),'created_by'=>$actor]+$header); }
            else $this->model->updateHeader($id,$header);
            $eligible=[];foreach($this->eligibleRows($month) as $employee)$eligible[(string)$employee['employee_id']]=$employee;
            $persistedItems=$this->matchRequestedItems($id,$items);
            $payPolicy=new RegularEmploymentIncomePayLineService();$rows=[];$linesByItem=[];$totals=array_fill_keys(['gross_amount','taxable_amount','non_taxable_amount','income_tax_amount','local_income_tax_amount','national_pension_amount','health_insurance_amount','long_term_care_amount','employment_insurance_amount','other_deduction_amount','deduction_amount','net_payment_amount'],0.0);$seen=[];
            foreach(array_values($items) as $index=>$item){$employeeId=trim((string)($item['employee_id']??''));if(!isset($eligible[$employeeId]))throw new \InvalidArgumentException('귀속월에 유효한 근로계약이 없는 직원이 포함되어 있습니다.');if(isset($seen[$employeeId]))throw new \InvalidArgumentException('동일 직원은 한 번만 등록할 수 있습니다.');$seen[$employeeId]=true;$lines=is_array($item['line_items']??null)?array_values($item['line_items']):[];if($lines===[])throw new \InvalidArgumentException('직원별 지급·공제 항목을 먼저 계산해 주세요.');$lineTotals=$payPolicy->totals($lines);$deductionByCode=[];foreach($lines as$line)if(($line['item_type_code']??'')==='DEDUCTION')$deductionByCode[$line['item_code']]=(float)$line['final_amount'];$source=$eligible[$employeeId];$persisted=$persistedItems[$index]??null;$itemId=$persisted?(string)$persisted['id']:UuidHelper::generate();$row=['id'=>$itemId,'sort_no'=>$index+1,'regular_employment_income_id'=>$id,'employee_id'=>$employeeId,'employee_name_snapshot'=>$source['employee_name'],'employee_identifier_snapshot'=>$source['employee_identifier_snapshot']?:null,'department_name_snapshot'=>$source['department_name']?:null,'position_name_snapshot'=>$source['position_name']?:null,'employment_contract_id'=>$source['employment_contract_id'],'dependent_count_snapshot'=>$this->dependentCount($item)]+(new RegularEmploymentIncomeHistoricalService())->normalizeSnapshots($item)+['calculation_status_code'=>$item['calculation_status_code']??'NEEDS_CONFIRMATION','calculation_message'=>$item['calculation_message']??null,'calculation_source_code'=>'CALCULATED','base_salary_amount'=>$this->lineAmount($lines,'PAY','BASE_SALARY'),'allowance_amount'=>0,'bonus_amount'=>$this->lineAmount($lines,'PAY','BONUS'),'non_taxable_amount'=>$lineTotals['non_taxable_amount'],'gross_amount'=>$lineTotals['gross_amount'],'taxable_amount'=>$lineTotals['taxable_amount'],'national_pension_amount'=>$deductionByCode['NATIONAL_PENSION']??0,'health_insurance_amount'=>$deductionByCode['HEALTH_INSURANCE']??0,'long_term_care_amount'=>$deductionByCode['LONG_TERM_CARE']??($deductionByCode['LONG_TERM_CARE_INSURANCE']??0),'employment_insurance_amount'=>$deductionByCode['EMPLOYMENT_INSURANCE']??0,'income_tax_amount'=>$deductionByCode['EMPLOYMENT_INCOME_TAX']??0,'local_income_tax_amount'=>$deductionByCode['LOCAL_INCOME_TAX']??0,'other_deduction_amount'=>$deductionByCode['OTHER_DEDUCTION']??0,'deduction_amount'=>$lineTotals['deduction_amount'],'net_payment_amount'=>$lineTotals['net_payment_amount'],'employer_burden_amount'=>$lineTotals['employer_burden_amount'],'created_by'=>$persisted['created_by']??$actor,'updated_by'=>$actor];$rows[]=$row;$linesByItem[$itemId]=$lines;foreach(array_keys($totals) as$key)$totals[$key]+=(float)$row[$key];}
            foreach($linesByItem as$itemId=>$itemLines)$linesByItem[$itemId]=$this->normalizeInsuranceOverrideLines($itemLines,$month,$actor);
            $this->model->syncHistoricalItems($id,$rows,$actor);$allowed=$this->linePersistenceColumns();foreach($rows as$row){$stored=[];foreach($linesByItem[$row['id']] as$index=>$line){$stored[]=['id'=>UuidHelper::generate(),'regular_employment_income_item_id'=>$row['id'],'sort_no'=>$index+1,'created_by'=>$actor,'updated_by'=>$actor]+array_intersect_key($line,$allowed);}$this->model->replaceLineItems($row['id'],$stored);$effects=array_values(array_filter($stored,fn($line)=>in_array($line['pay_effect_code']??null,['INCREASE','DECREASE'],true)));if($effects)$this->model->insertAudit(['id'=>UuidHelper::generate(),'regular_employment_income_id'=>$id,'regular_employment_income_item_id'=>$row['id'],'action_code'=>'CALCULATE','reason'=>'급여 증액·감액 계산 확정','before_value'=>null,'after_value'=>json_encode($effects,JSON_UNESCAPED_UNICODE),'request_key'=>null,'acted_by'=>$actor]);}
            $this->synchronizeDocumentTotalsFromLines($id,$actor);
            if ($requestKey !== '') {
                $this->model->insertAudit([
                    'id'=>UuidHelper::generate(),'regular_employment_income_id'=>$id,
                    'regular_employment_income_item_id'=>null,'action_code'=>'CALCULATE',
                    'reason'=>'급여 계산 요청 멱등성 확정','before_value'=>null,
                    'after_value'=>json_encode(['payload_hash'=>$payloadHash],JSON_UNESCAPED_UNICODE),
                    'request_key'=>$requestKey,'acted_by'=>$actor,
                ]);
            }
            return['success'=>true,'data'=>['id'=>$id],'message'=>'저장했습니다.'];
        });
    }
    public function adjust(array$input):array
    {
        return$this->transaction(function()use($input){$lineId=trim((string)($input['line_item_id']??''));$reason=trim((string)($input['adjustment_reason']??''));$amount=$this->signedAmount($input['adjustment_amount']??0);if($reason==='')throw new \InvalidArgumentException('조정사유를 입력해 주세요.');
            $stmt=$this->db->prepare('SELECT l.*,d.regular_employment_income_id,h.document_status FROM institution_regular_employment_income_line_items l JOIN institution_regular_employment_income_items d ON d.id=l.regular_employment_income_item_id JOIN institution_regular_employment_incomes h ON h.id=d.regular_employment_income_id WHERE l.id=:id FOR UPDATE');$stmt->execute([':id'=>$lineId]);$before=$stmt->fetch(PDO::FETCH_ASSOC);if(!$before)throw new \RuntimeException('조정할 항목을 찾을 수 없습니다.');if(!in_array($before['document_status'],['DRAFT','REJECTED','WITHDRAWN'],true))throw new \RuntimeException('현재 상태에서는 조정할 수 없습니다.');$final=round((float)$before['calculated_amount']+$amount,2);if($final<0)throw new \InvalidArgumentException('조정 후 금액은 0원 이상이어야 합니다.');$actor=ActorHelper::user();$this->db->prepare('UPDATE institution_regular_employment_income_line_items SET adjustment_amount=:amount,final_amount=:final,adjustment_reason=:reason,updated_at=NOW(),updated_by=:actor WHERE id=:id')->execute([':amount'=>$amount,':final'=>$final,':reason'=>$reason,':actor'=>$actor,':id'=>$lineId]);$this->model->insertAudit(['id'=>UuidHelper::generate(),'regular_employment_income_id'=>$before['regular_employment_income_id'],'regular_employment_income_item_id'=>$before['regular_employment_income_item_id'],'action_code'=>'ADJUST','reason'=>$reason,'before_value'=>json_encode($before,JSON_UNESCAPED_UNICODE),'after_value'=>json_encode(['adjustment_amount'=>$amount,'final_amount'=>$final],JSON_UNESCAPED_UNICODE),'request_key'=>null,'acted_by'=>$actor]);$this->synchronizeDocumentTotalsFromLines((string)$before['regular_employment_income_id'],$actor);return['success'=>true,'message'=>'조정했습니다.'];});
    }
    public function reportDataset(string$id):array
    {
        $data=$this->detail($id)['data'];$company=$this->db->query('SELECT company_name_ko,biz_number FROM system_company ORDER BY created_at LIMIT 1')->fetch(PDO::FETCH_ASSOC)?:[];$rows=[];$totals=[];
        foreach($data['items']as$item){$pay=[];$deduction=[];$burden=[];foreach($item['line_items']as$line){$entry=['name'=>$line['item_name_snapshot'],'amount'=>(float)$line['final_amount']];if($line['item_type_code']==='PAY')$pay[$line['item_code']]=$entry;elseif($line['item_type_code']==='DEDUCTION')$deduction[$line['item_code']]=$entry;else$burden[$line['item_code']]=$entry;$totals[$line['item_type_code']][$line['item_code']]=($totals[$line['item_type_code']][$line['item_code']]??0)+(float)$line['final_amount'];}$rows[]=['employee_id'=>$item['employee_id'],'employee_name'=>$item['employee_name_snapshot'],'employee_identifier'=>$this->maskIdentifier((string)($item['employee_identifier_snapshot']??'')),'department_name'=>$item['department_name_snapshot'],'position_name'=>$item['position_name_snapshot'],'pay_items'=>$pay,'deduction_items'=>$deduction,'employer_burden_items'=>$burden,'taxable_amount'=>(float)$item['taxable_amount'],'non_taxable_amount'=>(float)$item['non_taxable_amount'],'gross_amount'=>(float)$item['gross_amount'],'deduction_amount'=>(float)$item['deduction_amount'],'net_payment_amount'=>(float)$item['net_payment_amount'],'calculation_status'=>$item['calculation_status_code']];}
        return['success'=>true,'data'=>['report_key'=>'REGULAR_EMPLOYMENT_INCOME_PAYROLL_REGISTER','document'=>['company'=>$company,'income_year_month'=>$data['header']['income_year_month'],'status'=>$data['header']['document_status'],'readiness'=>$data['readiness']],'employees'=>$rows,'totals'=>['items'=>$totals,'gross_amount'=>(float)$data['header']['gross_amount'],'deduction_amount'=>(float)$data['header']['deduction_amount'],'net_payment_amount'=>(float)$data['header']['net_payment_amount']],'metadata'=>['calculation_version'=>$data['header']['calculation_version']??RegularEmploymentIncomeCalculationService::VERSION,'personal_identifier_policy'=>'MASKED','layout_owner'=>'COMMON_REPORT_RENDERER']]];
    }
    public function save(array$input):array{$this->fieldPolicy->validateRequiredFields($input);if(strtoupper(trim((string)($input['calculation_source_code']??'')))==='HISTORICAL_IMPORT')return$this->saveHistorical($input);return$this->transaction(function()use($input){$id=trim((string)($input['id']??''));$month=trim((string)($input['income_year_month']??''));$title=trim((string)($input['title']??''));if(!preg_match('/^\d{4}-\d{2}$/',$month))throw new \InvalidArgumentException($this->fieldPolicy->label('income_year_month','귀속연월').'을(를) 입력해 주세요.');if($title==='')throw new \InvalidArgumentException($this->fieldPolicy->label('title','제목').'을(를) 입력해 주세요.');$items=$input['items']??[];if(is_string($items))$items=json_decode($items,true);if(!is_array($items)||$items===[])throw new \InvalidArgumentException('직원별 소득내역을 한 건 이상 입력해 주세요.');$actor=ActorHelper::user();$existing=$id!==''?$this->model->find($id,true):null;if($id!==''&&!$existing)throw new \RuntimeException('수정할 문서를 찾을 수 없습니다.');if($existing&&!in_array($existing['document_status'],['DRAFT','REJECTED','WITHDRAWN'],true))throw new \RuntimeException('현재 상태에서는 수정할 수 없습니다.');if($id===''){$id=UuidHelper::generate();$this->model->insertHeader(['id'=>$id,'sort_no'=>$this->model->nextSortNo(),'income_year_month'=>$month,'title'=>$title,'description'=>$input['description']??null,'memo'=>$input['memo']??null,'created_by'=>$actor,'updated_by'=>$actor]);}else{$this->model->updateHeader($id,['income_year_month'=>$month,'title'=>$title,'description'=>$input['description']??null,'memo'=>$input['memo']??null,'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor]);}
        $eligible=[];foreach($this->eligibleRows($month)as$e)$eligible[$e['employee_id']]=$e;$rows=[];$totals=array_fill_keys(['gross_amount','taxable_amount','non_taxable_amount','income_tax_amount','local_income_tax_amount','national_pension_amount','health_insurance_amount','long_term_care_amount','employment_insurance_amount','other_deduction_amount','deduction_amount','net_payment_amount'],0.0);$seen=[];
        foreach(array_values($items)as$i=>$item){$employeeId=trim((string)($item['employee_id']??''));if(!isset($eligible[$employeeId]))throw new \InvalidArgumentException('귀속월에 유효한 근로계약이 없는 직원이 포함되어 있습니다.');if(isset($seen[$employeeId]))throw new \InvalidArgumentException('동일 직원은 한 번만 등록할 수 있습니다.');$seen[$employeeId]=true;$dependentCount=$this->dependentCount($item);$insuranceSnapshots=(new RegularEmploymentIncomeHistoricalService())->normalizeSnapshots($item);$base=$this->amount($item,'base_salary_amount');$allow=$this->amount($item,'allowance_amount');$bonus=$this->amount($item,'bonus_amount');$nonTax=$this->amount($item,'non_taxable_amount');$gross=round($base+$allow+$bonus+$nonTax,2);$taxable=round($base+$allow+$bonus,2);$deductKeys=['national_pension_amount','health_insurance_amount','long_term_care_amount','employment_insurance_amount','income_tax_amount','local_income_tax_amount','other_deduction_amount'];$deduction=0.0;foreach($deductKeys as$k)$deduction+=$this->amount($item,$k);$deduction=round($deduction,2);$net=round($gross-$deduction,2);if($net<0)throw new \InvalidArgumentException('실지급액은 0원보다 작을 수 없습니다.');$e=$eligible[$employeeId];$calculated=($item['calculation_status_code']??'')==='CALCULATED';$row=['id'=>UuidHelper::generate(),'sort_no'=>$i+1,'regular_employment_income_id'=>$id,'employee_id'=>$employeeId,'employee_name_snapshot'=>$e['employee_name'],'employee_identifier_snapshot'=>$e['employee_identifier_snapshot']?:null,'department_name_snapshot'=>$e['department_name']?:null,'position_name_snapshot'=>$e['position_name']?:null,'employment_contract_id'=>$e['employment_contract_id'],'dependent_count_snapshot'=>$dependentCount]+$insuranceSnapshots+['calculation_status_code'=>$calculated?'CALCULATED':'NEEDS_CONFIRMATION','calculation_message'=>$calculated?null:(trim((string)($item['calculation_message']??''))?:'자동계산 확정 전 확인이 필요합니다.'),'calculation_source_code'=>$calculated?'CALCULATED':'MANUAL','base_salary_amount'=>$base,'allowance_amount'=>$allow,'bonus_amount'=>$bonus,'non_taxable_amount'=>$nonTax,'gross_amount'=>$gross,'taxable_amount'=>$taxable,'national_pension_amount'=>$this->amount($item,'national_pension_amount'),'health_insurance_amount'=>$this->amount($item,'health_insurance_amount'),'long_term_care_amount'=>$this->amount($item,'long_term_care_amount'),'employment_insurance_amount'=>$this->amount($item,'employment_insurance_amount'),'income_tax_amount'=>$this->amount($item,'income_tax_amount'),'local_income_tax_amount'=>$this->amount($item,'local_income_tax_amount'),'other_deduction_amount'=>$this->amount($item,'other_deduction_amount'),'deduction_amount'=>$deduction,'net_payment_amount'=>$net,'description'=>$item['description']??null,'memo'=>$item['memo']??null,'created_by'=>$actor,'updated_by'=>$actor];$rows[]=$row;foreach(array_keys($totals)as$k)$totals[$k]+=((float)$row[$k]);}
        $this->model->replaceItems($id,$rows);foreach($rows as$row){$definitions=[['PAY','BASE_SALARY','기본급',$row['base_salary_amount'],1],['PAY','TAXABLE_ALLOWANCE','과세수당',$row['allowance_amount'],1],['PAY','BONUS','상여금',$row['bonus_amount'],1],['PAY','NON_TAXABLE_PAY','비과세소득',$row['non_taxable_amount'],0],['DEDUCTION','NATIONAL_PENSION','국민연금',$row['national_pension_amount'],null],['DEDUCTION','HEALTH_INSURANCE','건강보험',$row['health_insurance_amount'],null],['DEDUCTION','LONG_TERM_CARE_INSURANCE','장기요양보험',$row['long_term_care_amount'],null],['DEDUCTION','EMPLOYMENT_INSURANCE','고용보험',$row['employment_insurance_amount'],null],['DEDUCTION','EMPLOYMENT_INCOME_TAX','근로소득세',$row['income_tax_amount'],null],['DEDUCTION','LOCAL_INCOME_TAX','지방소득세',$row['local_income_tax_amount'],null],['DEDUCTION','OTHER_DEDUCTION','기타공제',$row['other_deduction_amount'],null]];$lineRows=[];foreach($definitions as$index=>$definition){[$type,$code,$name,$value,$taxable]=$definition;$lineRows[]=['id'=>UuidHelper::generate(),'regular_employment_income_item_id'=>$row['id'],'sort_no'=>$index+1,'item_type_code'=>$type,'item_code'=>$code,'item_name_snapshot'=>$name,'taxable_flag'=>$taxable,'calculated_amount'=>$value,'adjustment_amount'=>0,'final_amount'=>$value,'adjustment_reason'=>null,'calculation_source_code'=>'MANUAL','created_by'=>$actor,'updated_by'=>$actor];}$this->model->replaceLineItems($row['id'],$lineRows);$this->model->replaceCalculationBases($row['id'],[['id'=>UuidHelper::generate(),'regular_employment_income_item_id'=>$row['id'],'basis_type_code'=>'EMPLOYMENT_CONTRACT','source_table'=>'institution_employment_contracts','source_id'=>$row['employment_contract_id'],'source_revision'=>null,'effective_from'=>$month.'-01','effective_to'=>date('Y-m-t',strtotime($month.'-01')),'basis_code'=>'MANUAL_INPUT','basis_summary'=>'기존 수동 입력 경로이며 자동계산 확정 전 확인이 필요합니다.','created_by'=>$actor]]);} $update=['employee_count'=>count($rows)]+array_map(fn($v)=>round($v,2),$totals);$update['updated_at']=date('Y-m-d H:i:s');$update['updated_by']=$actor;$this->model->updateHeader($id,$update);return['success'=>true,'data'=>['id'=>$id],'message'=>'저장했습니다.'];});}
    private function saveHistorical(array$input):array
    {
        return$this->transaction(function()use($input){$id=trim((string)($input['id']??''));$month=trim((string)($input['income_year_month']??''));$title=trim((string)($input['title']??''));if(!preg_match('/^\d{4}-\d{2}$/',$month)||$title==='')throw new \InvalidArgumentException('귀속연월과 제목을 확인해 주세요.');$items=$input['items']??[];if(is_string($items))$items=json_decode($items,true);if(!is_array($items)||$items===[])throw new \InvalidArgumentException('직원별 과거 급여내역을 입력해 주세요.');$actor=ActorHelper::user();$existing=$id!==''?$this->model->find($id,true):null;if($id!==''&&!$existing)throw new \RuntimeException('수정할 문서를 찾을 수 없습니다.');if($existing&&!in_array($existing['document_status'],['DRAFT','REJECTED','WITHDRAWN'],true))throw new \RuntimeException('승인·결재 중인 급여 Snapshot은 변경할 수 없습니다.');$existingItems=[];if($existing)foreach($this->model->items($id,true)as$existingItem)$existingItems[(string)$existingItem['employee_id']]=$existingItem;$headerValues=['income_year_month'=>$month,'title'=>$title,'description'=>$input['description']??null,'memo'=>$input['memo']??null,'calculation_source_code'=>'HISTORICAL_IMPORT','calculation_version'=>RegularEmploymentIncomeCalculationService::VERSION,'updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor];if($id===''){$id=UuidHelper::generate();$this->model->insertHeader(['id'=>$id,'sort_no'=>$this->model->nextSortNo(),'created_by'=>$actor]+$headerValues);}else$this->model->updateHeader($id,$headerValues);$eligible=[];foreach($this->eligibleRows($month)as$employee)$eligible[(string)$employee['employee_id']]=$employee;$historical=new RegularEmploymentIncomeHistoricalService();$rows=[];$allLines=[];$totals=array_fill_keys(['gross_amount','taxable_amount','non_taxable_amount','income_tax_amount','local_income_tax_amount','national_pension_amount','health_insurance_amount','long_term_care_amount','employment_insurance_amount','other_deduction_amount','deduction_amount','net_payment_amount'],0.0);$seen=[];foreach(array_values($items)as$index=>$item){$employeeId=trim((string)($item['employee_id']??''));if(!isset($eligible[$employeeId]))throw new \InvalidArgumentException('귀속월에 유효한 근로계약이 없는 직원이 포함되어 있습니다.');if(isset($seen[$employeeId]))throw new \InvalidArgumentException('동일 직원은 한 번만 등록할 수 있습니다.');$seen[$employeeId]=true;$lines=$historical->normalizeLines(is_array($item['line_items']??null)?$item['line_items']:[]);$lineTotals=$historical->totals($lines);$snapshots=$historical->normalizeSnapshots($item);$payLines=array_filter($lines,fn($line)=>$line['item_type_code']==='PAY');$taxable=array_sum(array_map(fn($line)=>$line['taxable_flag']?(float)$line['final_amount']:0,$payLines));$deductionByCode=[];foreach($lines as$line)if($line['item_type_code']==='DEDUCTION')$deductionByCode[$line['item_code']]=(float)$line['final_amount'];$source=$eligible[$employeeId];$persisted=$existingItems[$employeeId]??null;$itemId=$persisted?(string)$persisted['id']:UuidHelper::generate();$row=['id'=>$itemId,'sort_no'=>$index+1,'regular_employment_income_id'=>$id,'employee_id'=>$employeeId,'employee_name_snapshot'=>$source['employee_name'],'employee_identifier_snapshot'=>$source['employee_identifier_snapshot']?:null,'department_name_snapshot'=>$source['department_name']?:null,'position_name_snapshot'=>$source['position_name']?:null,'employment_contract_id'=>$source['employment_contract_id'],'dependent_count_snapshot'=>$this->dependentCount($item)]+$snapshots+['calculation_status_code'=>'CONFIRMED','calculation_message'=>$historical->verificationStatus($lines),'calculation_source_code'=>'HISTORICAL_IMPORT','base_salary_amount'=>$this->lineAmount($lines,'PAY','BASE_SALARY'),'allowance_amount'=>round($taxable-$this->lineAmount($lines,'PAY','BASE_SALARY')-$this->lineAmount($lines,'PAY','BONUS'),2),'bonus_amount'=>$this->lineAmount($lines,'PAY','BONUS'),'non_taxable_amount'=>round($lineTotals['gross_amount']-$taxable,2),'gross_amount'=>$lineTotals['gross_amount'],'taxable_amount'=>round($taxable,2),'national_pension_amount'=>$deductionByCode['NATIONAL_PENSION']??0,'health_insurance_amount'=>$deductionByCode['HEALTH_INSURANCE']??0,'long_term_care_amount'=>$deductionByCode['LONG_TERM_CARE']??($deductionByCode['LONG_TERM_CARE_INSURANCE']??0),'employment_insurance_amount'=>$deductionByCode['EMPLOYMENT_INSURANCE']??0,'income_tax_amount'=>$deductionByCode['EMPLOYMENT_INCOME_TAX']??0,'local_income_tax_amount'=>$deductionByCode['LOCAL_INCOME_TAX']??0,'other_deduction_amount'=>$deductionByCode['OTHER_DEDUCTION']??0,'deduction_amount'=>$lineTotals['deduction_amount'],'net_payment_amount'=>$lineTotals['net_payment_amount'],'employer_burden_amount'=>$lineTotals['employer_burden_amount'],'confirmed_at'=>date('Y-m-d H:i:s'),'confirmed_by'=>$actor,'description'=>$item['description']??null,'memo'=>$item['memo']??null,'created_by'=>$persisted['created_by']??$actor,'updated_by'=>$actor,'deleted_at'=>null,'deleted_by'=>null];$rows[]=$row;$allLines[$row['id']]=$lines;foreach(array_keys($totals)as$key)$totals[$key]+=(float)$row[$key];}$this->model->syncHistoricalItems($id,$rows,$actor);foreach($rows as$row){$lineRows=[];foreach($allLines[$row['id']]as$line){unset($line['verification_status_code']);$lineRows[]=['id'=>UuidHelper::generate(),'regular_employment_income_item_id'=>$row['id'],'created_by'=>$actor,'updated_by'=>$actor]+$line;}$this->model->replaceLineItems($row['id'],$lineRows);$this->model->insertAudit(['id'=>UuidHelper::generate(),'regular_employment_income_id'=>$id,'regular_employment_income_item_id'=>$row['id'],'action_code'=>'CORRECT','reason'=>'과거 실제 급여대장 값 확정','before_value'=>isset($existingItems[$row['employee_id']])?json_encode($existingItems[$row['employee_id']],JSON_UNESCAPED_UNICODE):null,'after_value'=>json_encode(['source_code'=>'HISTORICAL_IMPORT','snapshots'=>array_intersect_key($row,array_flip(['dependent_count_snapshot','national_pension_basis_snapshot','health_insurance_basis_snapshot','employment_insurance_basis_snapshot'])),'gross_amount'=>$row['gross_amount'],'deduction_amount'=>$row['deduction_amount'],'net_payment_amount'=>$row['net_payment_amount'],'verification_status'=>$row['calculation_message']],JSON_UNESCAPED_UNICODE),'request_key'=>null,'acted_by'=>$actor]);}$this->model->updateHeader($id,['employee_count'=>count($rows)]+array_map(fn($value)=>round($value,2),$totals)+['updated_at'=>date('Y-m-d H:i:s'),'updated_by'=>$actor]);return['success'=>true,'data'=>['id'=>$id],'message'=>'과거 실제 급여를 저장했습니다.'];});
    }
    private function lineAmount(array$lines,string$type,string$code):float{foreach($lines as$line)if($line['item_type_code']===$type&&$line['item_code']===$code)return(float)$line['final_amount'];return 0.0;}
    private function calculationInputsForStoredItems(array$items):array{$inputs=[];foreach($items as$item){$lines=$this->model->lineItems((string)$item['id']);$inputs[]=['employee_id'=>$item['employee_id'],'dependent_count_snapshot'=>$item['dependent_count_snapshot']??null,'national_pension_basis_snapshot'=>$item['national_pension_basis_snapshot']??null,'health_insurance_basis_snapshot'=>$item['health_insurance_basis_snapshot']??null,'employment_insurance_basis_snapshot'=>$item['employment_insurance_basis_snapshot']??null,'pay_line_items'=>array_values(array_filter($lines,fn($line)=>$line['item_type_code']==='PAY'&&in_array($line['pay_effect_code']??null,['INCREASE','DECREASE'],true))),'deduction_line_items'=>array_values(array_filter($lines,fn($line)=>$line['item_type_code']==='DEDUCTION'&&str_starts_with((string)($line['source_key']??''),'SETTLEMENT|'))),'insurance_override_line_items'=>array_values(array_filter($lines,fn($line)=>$line['item_type_code']==='DEDUCTION'&&in_array($line['item_code'],['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'],true)&&(abs((float)($line['adjustment_amount']??0))>=.01||str_starts_with((string)($line['source_key']??''),'INSURANCE_OVERRIDE'))))];}return$inputs;}
    public function submit(string$id):array{return$this->transaction(function()use($id){$this->assertSubmittable($id);$header=$this->assertUnifiedSubmittable($id);[$userId,$actor]=$this->identity();$result=(new ApprovalWorkflowService($this->db))->submit(self::DOCUMENT_TYPE,$id,$userId,$actor);$this->model->updateWorkflow($id,'PENDING',$result['request_id'],$actor);return['success'=>true,'data'=>$result,'message'=>'결재를 요청했습니다.'];});}
    public function withdraw(string$requestId):array{return$this->transaction(function()use($requestId){[$userId,$actor]=$this->identity();$request=(new ApprovalWorkflowService($this->db))->withdraw($requestId,self::DOCUMENT_TYPE,$userId,$actor);$this->model->updateWorkflow((string)$request['document_id'],'WITHDRAWN',$requestId,$actor);return['success'=>true,'message'=>'기안을 회수했습니다.'];});}
    public function act(string$stepId,string$decision,?string$comment):array{return$this->transaction(function()use($stepId,$decision,$comment){[$userId,$actor]=$this->identity();$generation=new RegularEmploymentIncomeAccountingGenerationService($this->db);$preflight=strtolower(trim($decision))==='approved'?$generation->preflightFinalStep($stepId,true):['is_final'=>false];$result=(new ApprovalWorkflowService($this->db))->act($stepId,self::DOCUMENT_TYPE,$decision,$comment,$userId,$actor);$id=(string)$result['request']['document_id'];if($result['state']==='APPROVED'){if(empty($preflight['is_final'])||!isset($preflight['plan']))throw new \RuntimeException('최종 결재단계 사전검증 결과가 없습니다.');$accounting=$this->finalizeEmployeeAccounting($preflight['plan'],$actor);$this->model->updateWorkflow($id,'APPROVED',(string)$result['request']['id'],$actor);$userMessage='최종 승인이 완료되었습니다. 직원별 급여 증빙과 거래가 생성되었습니다. 증빙관리에서 업무분류를 확인해 주세요.';return['success'=>true,'data'=>$accounting,'message'=>$userMessage,'user_message'=>$userMessage,'result_code'=>'REGULAR_INCOME_FINAL_APPROVAL_COMPLETED','correlation_id'=>null];}$this->model->updateWorkflow($id,$result['state'],(string)$result['request']['id'],$actor);return['success'=>true,'message'=>$result['state']==='REJECTED'?'반려했습니다.':'승인했습니다.'];});}
    public function approvalDetail(array$request):array{$d=$this->detail((string)$request['document_id'])['data'];$employerBurdenAmount=array_sum(array_map(static fn(array$item):float=>(float)($item['employer_burden_amount']??0),$d['items']));return['type'=>self::DOCUMENT_TYPE,'type_name'=>'상용근로소득','header'=>array_merge($request,$d['header']), 'items'=>$d['items'],'totals'=>['total_amount'=>$d['header']['gross_amount'],'deduction_amount'=>$d['header']['deduction_amount'],'net_payment_amount'=>$d['header']['net_payment_amount'],'employer_burden_amount'=>$employerBurdenAmount],'detail_supported'=>true];}
    public function delete(string$id):array{if($id==='')throw new \InvalidArgumentException('삭제할 문서를 확인해 주세요.');$row=$this->model->find($id,true);if(!$row||!in_array((string)$row['document_status'],['DRAFT','REJECTED','WITHDRAWN'],true))throw new \RuntimeException('현재 문서상태에서는 삭제할 수 없습니다.');$this->model->softDelete($id,ActorHelper::user());return['success'=>true,'message'=>'삭제했습니다.'];}
    public function trash():array{return['success'=>true,'data'=>$this->model->trash()];}
    public function restore(string$id):array{return$this->restoreMany([$id]);}
    public function restoreMany(array$ids):array{return$this->transaction(function()use($ids){$count=0;$actor=ActorHelper::user();foreach($this->ids($ids)as$id)if($this->model->restore($id,$actor))$count++;return['success'=>true,'data'=>['restored_count'=>$count],'message'=>$count>0?'복원했습니다.':'복원된 문서가 없습니다.'];});}
    public function restoreAll():array{return$this->restoreMany($this->model->trashIds());}
    public function purge(string$id):array{return$this->purgeMany([$id]);}
    public function purgeMany(array$ids):array{return$this->transaction(function()use($ids){$count=0;$skipped=0;foreach($this->ids($ids)as$id){if($this->model->purge($id))$count++;else$skipped++;}return['success'=>true,'data'=>['deleted_count'=>$count,'skipped_count'=>$skipped],'message'=>$count>0?'완전삭제했습니다.':'완전삭제 가능한 문서가 없습니다.'];});}
    public function purgeAll():array{return$this->purgeMany($this->model->trashIds());}
    private function assertSubmittable(string $id): array
    {
        $header = $this->model->find($id, true);
        if (!$header || !in_array($header['document_status'], ['DRAFT', 'REJECTED', 'WITHDRAWN'], true)) {
            throw new \RuntimeException('결재요청할 수 있는 문서가 아닙니다.');
        }
        $items = $this->model->items($id, true);
        if ($items === []) throw new \RuntimeException('직원별 소득내역이 없습니다.');
        if (($header['calculation_source_code'] ?? '') === 'HISTORICAL_IMPORT') {
            return $this->assertHistoricalSubmittable($header, $items);
        }

        $actor = ActorHelper::user();
        $preview = (new RegularEmploymentIncomeCalculationService($this->db))->preview(
            (string) $header['income_year_month'],
            $this->withholdingDate($header['withholding_date']??null),
            $this->calculationInputsForStoredItems($items),
            $actor
        );
        $calculated = [];
        foreach ($preview['results'] as $result) $calculated[(string) $result['employee_id']] = $result;

        $aggregateKeys = [
            'gross_amount', 'taxable_amount', 'non_taxable_amount', 'income_tax_amount',
            'local_income_tax_amount', 'national_pension_amount', 'health_insurance_amount',
            'long_term_care_amount', 'employment_insurance_amount', 'other_deduction_amount',
            'deduction_amount', 'net_payment_amount',
        ];
        $headerTotals = array_fill_keys($aggregateKeys, 0.0);
        $lineColumns = $this->linePersistenceColumns();

        foreach ($items as $item) {
            $result = $calculated[(string) $item['employee_id']] ?? null;
            if (!$result || ($result['calculation_status_code'] ?? '') !== 'CALCULATED') {
                throw new \RuntimeException($result['calculation_message'] ?? '사회보험 계산근거를 확인할 수 없습니다.');
            }
            $itemTotals = array_intersect_key($result, array_flip($aggregateKeys));
            $this->model->updateItem((string) $item['id'], $itemTotals + [
                'calculation_status_code' => 'CALCULATED',
                'calculation_message' => null,
                'calculation_source_code' => 'CALCULATED',
                'employer_burden_amount' => $result['employer_burden_amount'],
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $actor,
            ]);
            foreach ($aggregateKeys as $key) $headerTotals[$key] += (float) $result[$key];

            $lineRows = [];
            foreach ($result['line_items'] as $index => $line) {
                $lineRows[] = [
                    'id' => UuidHelper::generate(),
                    'regular_employment_income_item_id' => $item['id'],
                    'sort_no' => $index + 1,
                ] + array_intersect_key($line, $lineColumns) + ['created_by' => $actor, 'updated_by' => $actor];
            }
            $basisRows = [];
            foreach ($result['calculation_bases'] as $basis) {
                $basisRows[] = ['id' => UuidHelper::generate(), 'regular_employment_income_item_id' => $item['id']]
                    + $basis + ['created_by' => $actor];
            }
            $this->model->replaceLineItems((string) $item['id'], $lineRows);
            $this->model->replaceCalculationBases((string) $item['id'], $basisRows);
        }

        $this->model->updateHeader($id, array_map(static fn(float $value): float => round($value, 2), $headerTotals) + [
            'employee_count' => count($items),
            'calculation_version' => RegularEmploymentIncomeCalculationService::VERSION,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actor,
        ]);
        $items = $this->model->items($id, true);
        if ($this->readiness($items) !== 'READY') throw new \RuntimeException('확인이 필요한 직원이 있어 결재를 요청할 수 없습니다.');
        return $this->model->find($id, true) ?? $header;
    }
    private function assertHistoricalSubmittable(array$header,array$items):array{$historical=new RegularEmploymentIncomeHistoricalService();$gross=0.0;$deduction=0.0;$net=0.0;foreach($items as$item){$lines=$historical->normalizeLines($this->model->lineItems((string)$item['id']));$totals=$historical->totals($lines);if(abs($totals['gross_amount']-(float)$item['gross_amount'])>=0.01||abs($totals['deduction_amount']-(float)$item['deduction_amount'])>=0.01||abs($totals['net_payment_amount']-(float)$item['net_payment_amount'])>=0.01)throw new \RuntimeException('과거 급여의 지급·공제·실지급 합계가 저장된 직원 Snapshot과 일치하지 않습니다.');if(empty($item['confirmed_at'])||empty($item['confirmed_by']))throw new \RuntimeException('과거 실제 급여의 확인 Actor와 확인일시가 없습니다.');$gross+=$totals['gross_amount'];$deduction+=$totals['deduction_amount'];$net+=$totals['net_payment_amount'];}if(abs($gross-(float)$header['gross_amount'])>=0.01||abs($deduction-(float)$header['deduction_amount'])>=0.01||abs($net-(float)$header['net_payment_amount'])>=0.01)throw new \RuntimeException('과거 급여의 문서 합계가 직원별 실제값 합계와 일치하지 않습니다.');return$header;}
    private function assertUnifiedSubmittable(string $id): array
    {
        $header = $this->model->find($id, true);
        if (!$header || !in_array($header['document_status'], ['DRAFT', 'REJECTED', 'WITHDRAWN'], true)) {
            throw new \RuntimeException('결재요청할 수 있는 문서가 아닙니다.');
        }
        $items = $this->model->items($id, true);
        if ($items === []) {
            throw new \RuntimeException('직원별 소득내역이 없습니다.');
        }
        $policy = new RegularEmploymentIncomePayLineService();
        $documentTotals = ['gross_amount' => 0.0, 'deduction_amount' => 0.0, 'net_payment_amount' => 0.0];
        foreach ($items as $item) {
            $lines = $this->model->lineItems((string) $item['id']);
            $totals = $policy->totals($lines);
            if (abs($totals['gross_amount'] - (float) $item['gross_amount']) >= 0.01
                || abs($totals['deduction_amount'] - (float) $item['deduction_amount']) >= 0.01
                || abs($totals['net_payment_amount'] - (float) $item['net_payment_amount']) >= 0.01) {
                throw new \RuntimeException('지급총액·공제총액·실지급액이 직원별 최종 Line 합계와 일치하지 않습니다.');
            }
            foreach (array_keys($documentTotals) as $key) $documentTotals[$key] += (float) $totals[$key];
            foreach ($lines as $line) {
                if (($line['calculation_status_code'] ?? 'CALCULATED') === 'NEEDS_CONFIRMATION' || $line['final_amount'] === null) {
                    throw new \RuntimeException($line['calculation_message'] ?? '계산기초 또는 법정기준을 확인해 주세요.');
                }
                if (abs((float) ($line['adjustment_amount'] ?? 0)) >= 0.01 && trim((string) ($line['adjustment_reason'] ?? '')) === '') {
                    throw new \RuntimeException('자동계산값과 다른 항목의 적용사유를 입력해 주세요.');
                }
            }
        }
        foreach ($documentTotals as $key => $value) {
            if (abs(round($value, 2) - (float) $header[$key]) >= 0.01) {
                throw new \RuntimeException('문서 합계가 직원별 최종 Line 합계와 일치하지 않습니다.');
            }
        }
        if ($this->readiness($items) !== 'READY') {
            throw new \RuntimeException('확인이 필요한 직원이 있어 결재를 요청할 수 없습니다.');
        }
        return $header;
    }

    private function matchRequestedItems(string $documentId, array $items): array
    {
        $existingById=[];$existingByEmployee=[];
        foreach($this->model->items($documentId,true)as$row){
            $itemId=(string)$row['id'];$employeeId=(string)$row['employee_id'];
            if(isset($existingByEmployee[$employeeId]))throw new \RuntimeException('동일 문서에 같은 직원 계산행이 여러 건 존재합니다.');
            $existingById[$itemId]=$row;$existingByEmployee[$employeeId]=$row;
        }
        $seenEmployees=[];$seenItemIds=[];$seenSortNos=[];$matches=[];
        foreach(array_values($items)as$index=>$item){
            $employeeId=trim((string)($item['employee_id']??''));
            if($employeeId==='')throw new \InvalidArgumentException('직원 식별값이 없는 계산행이 포함되어 있습니다.');
            if(isset($seenEmployees[$employeeId]))throw new \InvalidArgumentException('동일 직원은 한 번만 등록할 수 있습니다.');
            $seenEmployees[$employeeId]=true;
            $requestedId=trim((string)($item['id']??($item['item_id']??($item['calculation_item_id']??''))));
            if($requestedId!==''){
                if(isset($seenItemIds[$requestedId]))throw new \InvalidArgumentException('동일 직원 계산행 ID가 중복되었습니다.');
                $seenItemIds[$requestedId]=true;
                $persisted=$existingById[$requestedId]??$this->model->findItemById($requestedId,true);
                if(!$persisted)throw new \InvalidArgumentException('존재하지 않는 직원 계산행 ID가 포함되어 있습니다.');
                if((string)$persisted['regular_employment_income_id']!==$documentId)throw new \InvalidArgumentException('다른 문서의 직원 계산행 ID는 사용할 수 없습니다.');
                if((string)$persisted['employee_id']!==$employeeId)throw new \InvalidArgumentException('직원 계산행 ID와 직원 식별값이 일치하지 않습니다.');
                $matches[$index]=$persisted;
            }else{
                if(isset($existingByEmployee[$employeeId]))$matches[$index]=$existingByEmployee[$employeeId];
                else{
                    $employeeRows=$this->model->itemsByEmployeeIncludingDeleted($documentId,$employeeId,true);
                    if(count($employeeRows)>1)throw new \RuntimeException('동일 문서에 같은 직원 계산행이 여러 건 존재합니다.');
                    $matches[$index]=$employeeRows[0]??null;
                }
            }
            if(array_key_exists('sort_no',$item)&&$item['sort_no']!==null&&$item['sort_no']!==''){
                $requestedSort=(int)$item['sort_no'];
                if($requestedSort<1||isset($seenSortNos[$requestedSort]))throw new \InvalidArgumentException('요청 직원 순번이 없거나 중복되었습니다.');
                $seenSortNos[$requestedSort]=true;
            }
        }
        return $matches;
    }

    private function synchronizeDocumentTotalsFromLines(string $id, string $actor): void
    {
        $items = $this->model->items($id, true);
        $policy = new RegularEmploymentIncomePayLineService();
        $aggregateKeys = [
            'gross_amount','taxable_amount','non_taxable_amount','income_tax_amount',
            'local_income_tax_amount','national_pension_amount','health_insurance_amount',
            'long_term_care_amount','employment_insurance_amount','other_deduction_amount',
            'deduction_amount','net_payment_amount',
        ];
        $headerTotals = array_fill_keys($aggregateKeys, 0.0);
        foreach ($items as $item) {
            $lines = $this->model->lineItems((string) $item['id']);
            $totals = $policy->totals($lines);
            $deductions = [];
            foreach ($lines as $line) {
                if (($line['item_type_code'] ?? '') !== 'DEDUCTION'
                    || (new RegularEmploymentIncomeDeductionLineService())->isSettlement($line)) continue;
                $deductions[(string) $line['item_code']] = round((float) $line['final_amount'], 2);
            }
            $itemTotals = [
                'gross_amount'=>$totals['gross_amount'],
                'taxable_amount'=>$totals['taxable_amount'],
                'non_taxable_amount'=>$totals['non_taxable_amount'],
                'income_tax_amount'=>$deductions['EMPLOYMENT_INCOME_TAX']??0.0,
                'local_income_tax_amount'=>$deductions['LOCAL_INCOME_TAX']??0.0,
                'national_pension_amount'=>$deductions['NATIONAL_PENSION']??0.0,
                'health_insurance_amount'=>$deductions['HEALTH_INSURANCE']??0.0,
                'long_term_care_amount'=>$deductions['LONG_TERM_CARE']??($deductions['LONG_TERM_CARE_INSURANCE']??0.0),
                'employment_insurance_amount'=>$deductions['EMPLOYMENT_INSURANCE']??0.0,
                'other_deduction_amount'=>$deductions['OTHER_DEDUCTION']??0.0,
                'deduction_amount'=>$totals['deduction_amount'],
                'net_payment_amount'=>$totals['net_payment_amount'],
            ];
            $this->model->updateItem((string) $item['id'], $itemTotals + [
                'employer_burden_amount'=>$totals['employer_burden_amount'],
                'updated_at'=>date('Y-m-d H:i:s'),
                'updated_by'=>$actor,
            ]);
            foreach ($aggregateKeys as $key) $headerTotals[$key] += (float) $itemTotals[$key];
        }
        $this->model->updateHeader($id, array_map(static fn(float $value): float => round($value, 2), $headerTotals) + [
            'employee_count'=>count($items),
            'updated_at'=>date('Y-m-d H:i:s'),
            'updated_by'=>$actor,
        ]);
    }

    private function finalizeEmployeeAccounting(array$plan,string$actor):array{return(new RegularEmploymentIncomeAccountingGenerationService($this->db))->materialize($plan,$actor);}
    private function linePersistenceColumns(): array
    {
        return array_flip([
            'sort_no', 'item_type_code', 'pay_effect_code', 'item_code', 'item_name_snapshot', 'taxable_flag',
            'calculated_amount', 'adjustment_amount', 'final_amount', 'adjustment_reason',
            'calculation_source_code', 'business_source_code', 'source_reference_id',
            'source_key', 'business_reason', 'processed_at', 'processed_by',
            ...RegularEmploymentIncomeCalculationService::TRACE_COLUMNS,
        ]);
    }
    private function projectContractInsuranceReasons(array $lines, ?array $contract): array
    {
        if (!$contract) return $lines;
        $statusField = 'application_' . 'status_code';
        $policy = [
            'EMPLOYMENT_INSURANCE' => [
                'status' => $contract['employment_insurance_application_status_code'] ?? null,
                'reason' => $contract['employment_insurance_exclusion_reason'] ?? null,
            ],
            'EMPLOYMENT_INSURANCE_VOCATIONAL' => [
                'status' => $contract['employment_insurance_application_status_code'] ?? null,
                'reason' => $contract['employment_insurance_exclusion_reason'] ?? null,
            ],
            'INDUSTRIAL_ACCIDENT_INSURANCE' => [
                'status' => $contract['industrial_accident_application_status_code'] ?? null,
                'reason' => $contract['industrial_accident_exclusion_reason'] ?? null,
            ],
        ];
        foreach ($lines as &$line) {
            $rule = $policy[(string)($line['item_code'] ?? '')] ?? null;
            if (!$rule || $rule['status'] !== 'EXCLUDED') continue;
            $line[$statusField] = 'EXCLUDED';
            $line['calculation_status_code'] = 'CALCULATED';
            $line['calculation_message'] = trim((string)$rule['reason']) ?: '근로계약상 적용 제외';
        }
        unset($line);
        return $lines;
    }
    private function payloadHash(array $input): string
    {
        unset($input['request_key']);
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (!is_array($value)) return $value;
            if (!array_is_list($value)) ksort($value);
            foreach ($value as $key => $item) $value[$key] = $normalize($item);
            return $value;
        };
        return hash('sha256', json_encode($normalize($input), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }
    private function amount(array$row,string$key):float{$v=(float)str_replace(',','',(string)($row[$key]??0));if($v<0)throw new \InvalidArgumentException('금액은 0원 이상이어야 합니다.');return round($v,2);}
    private function signedAmount(mixed$value):float{return round((float)str_replace(',','',(string)$value),2);}
    private function normalizeInsuranceOverrideLines(array$lines,string$month,string$actor):array
    {
        $codes=['NATIONAL_PENSION','HEALTH_INSURANCE','LONG_TERM_CARE','EMPLOYMENT_INSURANCE'];$now=date('Y-m-d H:i:s');
        foreach($lines as&$line){if(($line['item_type_code']??'')!=='DEDUCTION'||!in_array($line['item_code']??'',$codes,true))continue;$calculated=$line['calculated_amount']??null;$final=$line['final_amount']??null;if($calculated===null||$final===null)continue;$changed=abs((float)$final-(float)$calculated)>=.01;$reset=str_starts_with((string)($line['source_key']??''),'INSURANCE_OVERRIDE_RESET|');$reason=trim((string)($line['adjustment_reason']??''));if($changed&&$reason==='')throw new \InvalidArgumentException('자동계산액과 다른 보험료의 적용사유를 입력해 주세요.');if(!$changed&&!$reset)continue;$line['adjustment_amount']=round((float)$final-(float)$calculated,2);$line['adjustment_reason']=$changed?$reason:null;$line['calculation_source_code']=$changed?'MANUAL':'CALCULATED';$line['business_source_code']='MANUAL';$line['source_reference_id']=$line['source_reference_id']??($line['id']??null);$line['source_key']=($reset?'INSURANCE_OVERRIDE_RESET|':'INSURANCE_OVERRIDE|').$line['item_code'].'|'.$month;$line['processed_at']=$now;$line['processed_by']=$actor;}
        unset($line);return$lines;
    }
    private function dependentCount(array$item):?int{$value=$item['dependent_count_snapshot']??null;if($value===null||$value==='')return null;if(filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]])===false)throw new \InvalidArgumentException('공제대상 가족수는 1명 이상으로 입력해 주세요.');return(int)$value;}
    private function readiness(array$items):string{foreach($items as$item)if(!in_array((string)($item['calculation_status_code']??''),['CALCULATED','CONFIRMED','MANUAL'],true))return'BLOCKED';return$items===[]?'BLOCKED':'READY';}
    private function maskIdentifier(string$value):string{$plain=preg_replace('/\D+/','',$value)??'';if(strlen($plain)!==13)return'';return substr($plain,0,6).'-'.substr($plain,6,1).'******';}
    private function identity():array{$p=ActorHelper::parse(ActorHelper::user());$id=trim((string)($p['id']??''));if($id==='')throw new \RuntimeException('로그인 사용자 정보를 확인할 수 없습니다.');return[$id,ActorHelper::user()];}
    private function withholdingDate(mixed$value):string{$date=trim((string)$value);$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if($parsed===false||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('원천징수일을 확인해 주세요.');return$date;}
    private function ids(array$ids):array{return array_values(array_unique(array_filter(array_map(static fn($id):string=>trim((string)$id),$ids),static fn(string$id):bool=>$id!=='')));}
    private function transaction(callable$fn):array{$outer=$this->db->inTransaction();if(!$outer)$this->db->beginTransaction();try{$r=$fn();if(!$outer){$this->db->commit();$this->logger->info('상용근로소득 변경이 완료되었습니다.',['event_code'=>'REGULAR_INCOME_CHANGED','result'=>'SUCCESS','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user()]);}return$r;}catch(\PDOException$e){if(!$outer&&$this->db->inTransaction())$this->db->rollBack();if(!$outer)$this->logger->error('상용근로소득 변경에 실패했습니다.',['event_code'=>'REGULAR_INCOME_CHANGE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);throw$e;}catch(\InvalidArgumentException|\DomainException|\RuntimeException$e){if(!$outer&&$this->db->inTransaction())$this->db->rollBack();if(!$outer)$this->logger->warning('상용근로소득 변경이 차단되었습니다.',['event_code'=>'REGULAR_INCOME_CHANGE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);throw$e;}catch(\Throwable$e){if(!$outer&&$this->db->inTransaction())$this->db->rollBack();if(!$outer)$this->logger->error('상용근로소득 변경에 실패했습니다.',['event_code'=>'REGULAR_INCOME_CHANGE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'change','actor'=>ActorHelper::user(),'error_code'=>get_class($e),'error'=>$e]);throw$e;}}
}
