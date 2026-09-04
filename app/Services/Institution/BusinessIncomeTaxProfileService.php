<?php

declare(strict_types=1);

namespace App\Services\Institution;

use App\Models\System\ClientTaxProfileModel;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

final class BusinessIncomeTaxProfileService
{
    private ClientTaxProfileModel $profiles;
    private LoggerInterface $logger;
    public function __construct(private readonly PDO $db){$this->profiles=new ClientTaxProfileModel($db);$this->logger=LoggerFactory::getLogger('service-institution-business-income-tax-profile');}

    public function resolve(string $clientId,string $paymentDate):array
    {
        $profile=$this->profiles->resolveVerified($clientId,$paymentDate);
        if($profile===null) throw new \RuntimeException('소득자의 세무 프로필이 확정되지 않았습니다.');
        $expected=['client_type'=>'FREELANCER','taxpayer_entity_type'=>'INDIVIDUAL','residency_status'=>'RESIDENT','income_recipient_type'=>'BUSINESS_INCOME','withholding_policy_code'=>'BUSINESS_INCOME_WITHHOLDING','verification_status'=>'VERIFIED'];
        foreach($expected as $field=>$value){
            if(strtoupper((string)($profile[$field]??''))!==$value){
                throw new \RuntimeException('일반 사업소득 자동계산 대상이 아닙니다.');
            }
        }
        return ['id'=>$profile['id'],'client_id'=>$profile['client_id'],'client_name'=>$profile['client_name'],'effective_from'=>$profile['effective_from'],'effective_to'=>$profile['effective_to'],'taxpayer_entity_type'=>$profile['taxpayer_entity_type'],'residency_status'=>$profile['residency_status'],'income_recipient_type'=>$profile['income_recipient_type'],'withholding_policy_code'=>$profile['withholding_policy_code'],'verification_status'=>$profile['verification_status'],'verified_at'=>$profile['verified_at'],'verified_by'=>$profile['verified_by']];
    }

    public function save(array $input, string $actor): string
    {
        $id=trim((string)($input['id']??''));$clientId=trim((string)($input['client_id']??''));
        $from=$this->date((string)($input['effective_from']??''));$to=$this->nullableDate($input['effective_to']??null);
        if($to!==null&&$to<$from)throw new \InvalidArgumentException('세무 프로필 종료일은 시작일보다 빠를 수 없습니다.');
        $codes=['taxpayer_entity_type'=>'TAXPAYER_ENTITY_TYPE','residency_status'=>'RESIDENCY_STATUS','income_recipient_type'=>'INCOME_RECIPIENT_TYPE','withholding_policy_code'=>'WITHHOLDING_POLICY','verification_status'=>'CLIENT_TAX_PROFILE_VERIFICATION'];
        $owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();
        try{
            if(!$this->profiles->lockClient($clientId))throw new \InvalidArgumentException('거래처를 찾을 수 없습니다.');
            $current=$id!==''?$this->profiles->find($id,true):null;if($id!==''&&!$current)throw new \InvalidArgumentException('세무 프로필을 찾을 수 없습니다.');
            if($current&&$current['client_id']!==$clientId)throw new \InvalidArgumentException('세무 프로필의 거래처를 변경할 수 없습니다.');
            $data=['client_id'=>$clientId,'effective_from'=>$from,'effective_to'=>$to];
            foreach($codes as$field=>$group){$value=strtoupper(trim((string)($input[$field]??'')));if($value===''||!$this->profiles->codeExists($group,$value))throw new \InvalidArgumentException('세무 프로필 코드값이 올바르지 않습니다.');$data[$field]=$value;}
            if($this->profiles->overlapping($clientId,$from,$to,$id))throw new \DomainException('CLIENT_TAX_PROFILE_PERIOD_OVERLAP');
            $now=date('Y-m-d H:i:s');$data+=['verified_at'=>$data['verification_status']==='VERIFIED'?($input['verified_at']??$now):null,'verified_by'=>$data['verification_status']==='VERIFIED'?$actor:null,'updated_at'=>$now,'updated_by'=>$actor];
            if($id===''){$id=UuidHelper::generate();$this->profiles->insert(['id'=>$id,'created_at'=>$now,'created_by'=>$actor]+$data);}else{$this->profiles->update($id,$data);}
            if($owns)$this->db->commit();$this->logger->info('사업소득자 세무 프로필이 저장되었습니다.',['event_code'=>'BUSINESS_INCOME_TAX_PROFILE_SAVED','result'=>'SUCCESS','service'=>self::class,'action'=>$current?'update':'create','actor'=>$actor,'target_id'=>$id,'client_id'=>$clientId]);return$id;
        }catch(\InvalidArgumentException|\DomainException$e){if($owns&&$this->db->inTransaction())$this->db->rollBack();$this->logger->warning('사업소득자 세무 프로필 저장이 차단되었습니다.',['event_code'=>'BUSINESS_INCOME_TAX_PROFILE_SAVE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'save','actor'=>$actor,'target_id'=>$id?:null,'client_id'=>$clientId,'error_code'=>get_class($e),'error'=>$e]);throw$e;}catch(\Throwable$e){if($owns&&$this->db->inTransaction())$this->db->rollBack();$this->logger->error('사업소득자 세무 프로필 저장에 실패했습니다.',['event_code'=>'BUSINESS_INCOME_TAX_PROFILE_SAVE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'save','actor'=>$actor,'target_id'=>$id?:null,'client_id'=>$clientId,'error_code'=>get_class($e),'error'=>$e]);throw$e;}
    }

    private function date(string $value): string{$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new \InvalidArgumentException('세무 프로필 적용일을 확인해 주세요.');return$value;}
    private function nullableDate(mixed $value): ?string{$value=trim((string)$value);return$value===''?null:$this->date($value);}
}
