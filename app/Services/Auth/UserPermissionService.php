<?php
namespace App\Services\Auth;

use App\Models\Auth\UserPermissionModel;
use App\Repositories\Auth\UserPermissionRepository;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class UserPermissionService
{
    private const MODES=['ROLE','EXTEND','REPLACE'];
    private const REQUIRED_KEYS=['web.settings.organization.permission-assignment','api.settings.rolepermission.list','api.settings.rolepermission.assign'];
    private UserPermissionModel $model; private UserPermissionRepository $repository;private LoggerInterface $logger;
    public function __construct(private readonly PDO $pdo){$this->model=new UserPermissionModel($pdo);$this->repository=new UserPermissionRepository($pdo);$this->logger=LoggerFactory::getLogger('service-auth-user-permission');}

    public function listUsers(string $actorId): array
    { return array_map(function($row)use($actorId){$p=$this->editPolicy($actorId,$row);return $row+['editable'=>$p['editable'],'readonly_reason'=>$p['readonly_reason'],'retired_account_warning'=>$this->isRetired($row)&&(int)$row['is_active']===1];},$this->repository->listUsers()); }

    public function detail(string $actorId,string $targetId): array
    {
        $target=$this->requireUser($targetId); $policy=$this->editPolicy($actorId,$target); $mode=(string)$target['permission_mode'];
        $personal=array_fill_keys($this->repository->userPermissionIds($targetId),true);$permissions=[];$tree=[];
        foreach((new RolePermissionService($this->pdo))->getPermissionTreeForRole((string)$target['role_id']) as $page){
            $children=[];
            foreach($page['children']??[] as $permission){$id=(string)($permission['permission_id']??'');$roleAllowed=(bool)($permission['checked']??false);$personalAllowed=isset($personal[$id]);$effective=$mode==='ROLE'?$roleAllowed:($mode==='EXTEND'?($roleAllowed||$personalAllowed):$personalAllowed);$decorated=$permission+['role_allowed'=>$roleAllowed,'user_allowed'=>$personalAllowed,'effective_allowed'=>$effective,'editable'=>$policy['editable']&&$mode!=='ROLE'];$children[]=$decorated;$permissions[]=$decorated;}
            $tree[]=$page+['children'=>$children];
        }
        return ['user'=>$target,'permission_mode'=>$mode,'editable'=>$policy['editable'],'readonly_reason'=>$policy['readonly_reason'],'state_version'=>$this->stateVersion($mode,array_keys($personal)),'permission_tree'=>$tree,'permissions'=>$permissions];
    }

    public function save(string $actorId,array $input): array
    {
        $targetId=trim((string)($input['user_id']??''));$mode=strtoupper(trim((string)($input['permission_mode']??'')));$version=trim((string)($input['state_version']??''));$ids=$input['permission_ids']??null;
        if($targetId===''||!in_array($mode,self::MODES,true)||!is_array($ids))throw new \InvalidArgumentException('저장할 개인권한 정보가 올바르지 않습니다.');
        $ids=array_values(array_unique(array_filter(array_map(static fn($v)=>trim((string)$v),$ids)))); if($mode==='ROLE')$ids=[];
        $outer=$this->pdo->inTransaction();if(!$outer)$this->pdo->beginTransaction();
        try{$target=$this->requireUser($targetId,true);$policy=$this->editPolicy($actorId,$target);if(!$policy['editable'])throw new \InvalidArgumentException($policy['readonly_reason']);
            $currentMode=(string)$target['permission_mode'];$current=$this->repository->userPermissionIds($targetId,true);sort($current);$sorted=$ids;sort($sorted);if($version===''||!hash_equals($this->stateVersion($currentMode,$current),$version))throw new \InvalidArgumentException('다른 사용자가 권한 설정을 변경했습니다. 다시 조회한 후 저장해 주세요.');
            $map=$this->repository->permissionMap($ids);if(count($map)!==count($ids))throw new \InvalidArgumentException('비활성 또는 존재하지 않는 권한이 포함되어 있습니다.');
            $actor=$this->requireUser($actorId);if($actor['role_key']!=='super_admin'){ $actorSet=(new PermissionService($this->pdo))->getEffectivePermissionSet($actorId);foreach($ids as $id)if(!isset($actorSet[$id]))throw new \InvalidArgumentException('보유하지 않은 권한은 부여할 수 없습니다.'); }
            $required=$this->repository->permissionMapByKeys(self::REQUIRED_KEYS);if(count($required)!==count(self::REQUIRED_KEYS))throw new \RuntimeException('핵심 권한 정보를 확인할 수 없습니다.');
            if($target['role_key']==='super_admin'&&$mode==='REPLACE'&&array_diff(array_keys($required),$ids)!==[])throw new \InvalidArgumentException('최고관리자의 핵심 관리 권한은 해제할 수 없습니다.');
            if($this->repository->countRecoveryAdministrators(array_keys($required),null,null,$targetId,$mode,$ids)<1)throw new \InvalidArgumentException('권한을 복구할 수 있는 활성 관리자가 최소 1명 이상 필요합니다.');
            $grant=array_values(array_diff($ids,$current));$revoke=array_values(array_diff($current,$ids));$actorValue=ActorHelper::user();$batch=UuidHelper::generate();$this->model->replaceProfile($targetId,$mode,$actorValue);$this->model->deleteMappings($targetId,$revoke);foreach($grant as $id)$this->model->insertMapping($targetId,$id,$actorValue,UuidHelper::generate());
            if($currentMode!==$mode)$this->audit($batch,$target,null,'MODE',$currentMode,$mode,$actorValue,null);foreach($grant as $id)$this->audit($batch,$target,$map[$id],'GRANT',$currentMode,$mode,$actorValue,$id);foreach($revoke as $id){$p=$this->repository->permissionMap([$id])[$id]??['permission_key'=>'','permission_name'=>''];$this->audit($batch,$target,$p,'REVOKE',$currentMode,$mode,$actorValue,$id);}
            if(!$outer)$this->pdo->commit();$changed=count($grant)+count($revoke)+($currentMode!==$mode?1:0);$this->logger->info('사용자 개인권한이 저장되었습니다.',['event_code'=>'USER_PERMISSION_SAVED','result'=>'SUCCESS','service'=>self::class,'action'=>'save','actor'=>ActorHelper::user(),'target_id'=>$targetId,'changed_count'=>$changed,'permission_mode'=>$mode]);return ['changed_count'=>$changed,'state_version'=>$this->stateVersion($mode,$ids)];
        }catch(\InvalidArgumentException|\DomainException $e){if(!$outer&&$this->pdo->inTransaction())$this->pdo->rollBack();$this->logger->warning('사용자 개인권한 저장이 차단되었습니다.',['event_code'=>'USER_PERMISSION_SAVE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'save','actor'=>ActorHelper::user(),'target_id'=>$targetId,'error_code'=>get_class($e),'error'=>$e]);throw $e;}catch(\Throwable $e){if(!$outer&&$this->pdo->inTransaction())$this->pdo->rollBack();$this->logger->error('사용자 개인권한 저장에 실패했습니다.',['event_code'=>'USER_PERMISSION_SAVE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'save','actor'=>ActorHelper::user(),'target_id'=>$targetId,'error_code'=>get_class($e),'error'=>$e]);throw $e;}
    }

    private function audit(string $batch,array $user,?array $p,string $type,string $before,string $after,string $actor,?string $permissionId):void{$this->model->insertAudit([':id'=>UuidHelper::generate(),':batch_id'=>$batch,':user_id'=>$user['user_id'],':username_snapshot'=>$user['username'],':employee_name_snapshot'=>$user['employee_name']?:null,':permission_id'=>$permissionId,':permission_key_snapshot'=>$p['permission_key']??null,':permission_name_snapshot'=>$p['permission_name']??null,':change_type'=>$type,':before_mode'=>$before,':after_mode'=>$after,':created_by'=>$actor]);}
    private function requireUser(string $id,bool $lock=false):array{$u=$this->repository->userContext($id,$lock);if(!$u)throw new \InvalidArgumentException('사용자를 확인할 수 없습니다.');return $u;}
    private function editPolicy(string $actorId,array $target):array{$actor=$this->requireUser($actorId);$actorRole=(string)($actor['role_key']??'');$targetRole=(string)($target['role_key']??'');$reason='';if(!in_array($actorRole,['super_admin','admin'],true))$reason='권한관리 권한이 없습니다.';elseif($targetRole==='super_admin'&&$actorRole!=='super_admin')$reason='최고관리자 개인권한은 최고관리자만 수정할 수 있습니다.';elseif($targetRole==='admin'&&$actorRole!=='super_admin')$reason='관리자 개인권한은 최고관리자만 수정할 수 있습니다.';elseif($actorRole==='admin'&&$actorId===$target['user_id'])$reason='관리자는 자기 개인권한을 수정할 수 없습니다.';return ['editable'=>$reason==='','readonly_reason'=>$reason];}
    private function stateVersion(string $mode,array $ids):string{sort($ids);return hash('sha256',$mode.'|'.implode('|',$ids));}
    private function isRetired(array $r):bool{return ($r['employment_status']??'')==='퇴사'||!empty($r['doc_retire_date'])||!empty($r['real_retire_date']);}
}
