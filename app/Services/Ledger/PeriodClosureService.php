<?php
namespace App\Services\Ledger;
use App\Models\Ledger\OpeningBalanceModel;use App\Models\Ledger\PeriodClosureModel;use Core\Helpers\ActorHelper;use Core\Helpers\UuidHelper;use Core\LoggerFactory;use InvalidArgumentException;use PDO;use Throwable;
class PeriodClosureService
{
 private PeriodClosureModel$model;private OpeningBalanceModel$openingBalances;private$logger;
 public function __construct(private readonly PDO$pdo){$this->model=new PeriodClosureModel($pdo);$this->openingBalances=new OpeningBalanceModel($pdo);$this->logger=LoggerFactory::getLogger('service-ledger-period-closure');}
 public function list():array{return ActorHelper::enrichActorNames($this->model->list(),['closed_by_name'=>'closed_by','reopened_by_name'=>'reopened_by','updated_by_name'=>'updated_by']);}
 public function options():array{return['companies'=>$this->model->companies()];}
 public function check(string$companyId,int$year):array{$this->validateKey($companyId,$year);$period=$this->model->period($companyId,$year);return$period+$this->model->readiness($companyId,$period['period_start_date'],$period['period_end_date'])+['closure'=>$this->model->find($companyId,$year)];}
 public function close(array$input):array
 {
  [$companyId,$year,$reason]=$this->input($input);if($reason==='')throw new InvalidArgumentException('결산 마감 사유를 입력해 주세요.');
  return$this->transaction(function()use($companyId,$year,$reason){$current=$this->model->find($companyId,$year,true);if(($current['close_status_code']??'')==='CLOSED')throw new InvalidArgumentException('이미 마감된 회계기간입니다.');$check=$this->check($companyId,$year);if(!$check['ready'])throw new InvalidArgumentException('미전기 전표 또는 불균형 전표를 먼저 정리해 주세요.');$actor=ActorHelper::user();$now=date('Y-m-d H:i:s');$id=(string)($current['id']??UuidHelper::generate());$revision=(int)($current['current_revision']??0)+1;
   $this->model->saveClosed([':id'=>$id,':company_id'=>$companyId,':fiscal_year'=>$year,':period_start_date'=>$check['period_start_date'],':period_end_date'=>$check['period_end_date'],':revision'=>$revision,':reason'=>$reason,':processed_at'=>$now,':actor'=>$actor]);$this->history($id,$revision,'CLOSE',$reason,$check,$now,$actor);
   $this->logger->info('회계기간 결산을 마감했습니다.',['event_code'=>'LEDGER_PERIOD_CLOSED','result'=>'SUCCESS','service'=>self::class,'action'=>'close','actor'=>$actor,'target_type'=>'fiscal_year','target_id'=>(string)$year]);
   return['success'=>true,'message'=>'회계기간 결산을 마감했습니다.','data'=>$this->check($companyId,$year)];});
 }
 public function reopen(array$input):array
 {
  [$companyId,$year,$reason]=$this->input($input);if($reason==='')throw new InvalidArgumentException('재개방 사유를 입력해 주세요.');
  return$this->transaction(function()use($companyId,$year,$reason){$current=$this->model->find($companyId,$year,true);if(!$current||$current['close_status_code']!=='CLOSED')throw new InvalidArgumentException('마감 상태인 회계기간만 재개방할 수 있습니다.');$actor=ActorHelper::user();$now=date('Y-m-d H:i:s');$revision=(int)$current['current_revision']+1;$this->model->saveReopened((string)$current['id'],$revision,$reason,$actor,$now);$this->history((string)$current['id'],$revision,'REOPEN',$reason,$this->check($companyId,$year),$now,$actor);
   $this->logger->warning('회계기간 결산을 재개방했습니다.',['event_code'=>'LEDGER_PERIOD_REOPENED','result'=>'SUCCESS','service'=>self::class,'action'=>'reopen','actor'=>$actor,'target_type'=>'fiscal_year','target_id'=>(string)$year]);
   return['success'=>true,'message'=>'회계기간을 재개방했습니다.','data'=>$this->check($companyId,$year)];});
 }
 public function carryForward(array$input):array
 {
  $companyId=trim((string)($input['company_id']??''));$sourceYear=(int)($input['fiscal_year']??0);$this->validateKey($companyId,$sourceYear);$source=$this->model->find($companyId,$sourceYear);if(!$source||$source['close_status_code']!=='CLOSED')throw new InvalidArgumentException('원본 회계연도를 먼저 마감해 주세요.');$targetYear=$sourceYear+1;if($this->openingBalances->findByCompanyYear($companyId,$targetYear))throw new InvalidArgumentException('차기 회계연도의 기초금액이 이미 있습니다.');$lines=[];
  foreach($this->model->balances($companyId,(string)$source['period_end_date'])as$row){$net=round((float)$row['debit']-(float)$row['credit'],2);if(abs($net)<.01)continue;$refs=[];foreach(array_filter(explode('|',(string)($row['ref_tokens']??'')))as$token){[$target,$refId]=array_pad(explode(':',$token,2),2,'');if($target!==''&&$refId!=='')$refs[]=['ref_target'=>$target,'ref_id'=>$refId];}$lines[]=['account_id'=>$row['account_id'],'debit'=>$net>0?$net:0,'credit'=>$net<0?abs($net):0,'summary'=>$sourceYear.'년 결산잔액 이월','refs'=>$refs];}
  $result=(new OpeningBalanceService($this->pdo))->save(['company_id'=>$companyId,'fiscal_year'=>$targetYear,'opening_date'=>sprintf('%04d-01-01',$targetYear),'period_end_date'=>sprintf('%04d-12-31',$targetYear),'note'=>$sourceYear.'년 결산잔액 자동 이월','lines'=>$lines],ActorHelper::system('PERIOD_CLOSURE'));
  $this->logger->info('결산잔액을 차기 기초금액으로 이월했습니다.',['event_code'=>'LEDGER_BALANCE_CARRIED_FORWARD','result'=>'SUCCESS','service'=>self::class,'action'=>'carry-forward','actor'=>ActorHelper::user(),'target_type'=>'fiscal_year','target_id'=>(string)$targetYear]);
  return['success'=>true,'message'=>$targetYear.'년 기초금액으로 이월했습니다.','data'=>$result['data']??null];
 }
 private function input(array$input):array{$companyId=trim((string)($input['company_id']??''));$year=(int)($input['fiscal_year']??0);$this->validateKey($companyId,$year);return[$companyId,$year,trim((string)($input['reason']??''))];}
 private function validateKey(string$companyId,int$year):void{if($companyId===''||$year<1900||$year>9998)throw new InvalidArgumentException('회사와 회계연도를 확인해 주세요.');}
 private function history(string$id,int$revision,string$action,string$reason,array$snapshot,string$at,string$actor):void{$this->model->addHistory([':id'=>UuidHelper::generate(),':period_closure_id'=>$id,':revision_no'=>$revision,':action_code'=>$action,':reason'=>$reason,':snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE),':processed_at'=>$at,':actor'=>$actor]);}
 private function transaction(callable$callback):array{try{$owns=!$this->pdo->inTransaction();if($owns)$this->pdo->beginTransaction();$result=$callback();if($owns)$this->pdo->commit();return$result;}catch(Throwable$e){if(isset($owns)&&$owns&&$this->pdo->inTransaction())$this->pdo->rollBack();$outcome=$e instanceof InvalidArgumentException?'BLOCKED':'FAILED';$this->logger->error('결산관리 업무를 완료하지 못했습니다.',['event_code'=>'LEDGER_PERIOD_OPERATION_FAILED','result'=>$outcome,'service'=>self::class,'action'=>'period-operation','error_code'=>$e::class]);throw$e;}}
}
