<?php

namespace App\Services\System;

use App\Models\Approval\ApprovalInboxModel;
use App\Models\Auth\UserModel;
use App\Models\System\NotificationModel;
use Core\Helpers\ActorHelper;
use Core\Helpers\UuidHelper;
use Core\LoggerFactory;
use PDO;
use Psr\Log\LoggerInterface;

class NotificationService
{
    private NotificationModel $notifications;
    private ApprovalInboxModel $approvalInbox;
    private UserModel $users;
    private LoggerInterface $logger;

    public function __construct(private readonly PDO $pdo)
    {
        $this->notifications=new NotificationModel($pdo); $this->approvalInbox=new ApprovalInboxModel($pdo); $this->users=new UserModel($pdo);$this->logger=LoggerFactory::getLogger('service-system-notification');
    }

    public function getNavigationFeed(string $userId,int $storedLimit=20): array
    {
        $stored=array_map(function(array $row): array { $row['notification_kind']='stored'; $row['action_url']=$this->resolveActionUrl($row); return $row; },$this->getNotifications($userId,$storedLimit));
        $approvals=array_map(static function(array $row): array {
            $name=match((string)$row['document_type']){'PERSONAL_EXPENSE'=>'개인경비','EMPLOYMENT_CONTRACT'=>'근로계약','PERSONNEL_ACTION'=>'인사발령','LEAVE_REQUEST'=>'휴가신청',default=>(string)$row['document_type']};
            return ['id'=>'approval:'.(string)$row['step_id'],'notification_kind'=>'approval_actionable','action_type'=>'APPROVAL_REQUEST','ref_table'=>'user_approval_requests','ref_id'=>(string)$row['request_id'],'title'=>'['.$name.' 결재요청]','message'=>sprintf('%s · #%s · %s · %s원 · %s',(string)$row['requester_name'],(string)$row['document_no'],(string)$row['title'],number_format((float)$row['total_amount']),(string)$row['current_step_name']),'is_read'=>0,'created_at'=>$row['arrived_at'],'action_url'=>'/approval/status?box=actionable&request_id='.rawurlencode((string)$row['request_id'])];
        },$this->approvalInbox->actionableNotifications($userId));
        $items=[...$approvals,...$stored]; usort($items,static fn(array $a,array $b): int=>strcmp((string)$b['created_at'],(string)$a['created_at']));
        return ['notifications'=>array_slice($items,0,max(1,$storedLimit)),'approval_pending_count'=>count($approvals),'unread_count'=>$this->notifications->unreadCount($userId)+count($approvals)];
    }

    public function getNotifications(string $userId,int $limit=20,int $offset=0): array { return $this->notifications->findByRecipient($userId,max(1,min($limit,100)),max(0,$offset)); }
    public function getNotificationPage(string $userId,int $page,int $pageSize): array { $page=max(1,$page); $pageSize=max(1,min($pageSize,100)); return ['items'=>array_map(function(array $row): array{$row['action_url']=$this->resolveActionUrl($row); return $row;},$this->getNotifications($userId,$pageSize,($page-1)*$pageSize)),'total'=>$this->notifications->countByRecipient($userId),'unread_count'=>$this->notifications->unreadCount($userId),'page'=>$page,'page_size'=>$pageSize]; }
    public function markAsRead(string $id,string $userId): bool { $ok=$this->notifications->markAsRead($id,$userId);$this->logger->{$ok?'info':'warning'}($ok?'알림을 읽음 처리했습니다.':'알림 읽음 처리가 차단되었습니다.',['event_code'=>$ok?'NOTIFICATION_READ':'NOTIFICATION_READ_BLOCKED','result'=>$ok?'SUCCESS':'BLOCKED','service'=>self::class,'action'=>'mark-read','actor'=>ActorHelper::user(),'target_id'=>$id]);return$ok; }
    public function markAllAsRead(string $userId): bool { $ok=$this->notifications->markAllAsRead($userId);$this->logger->{$ok?'info':'warning'}($ok?'전체 알림을 읽음 처리했습니다.':'전체 알림 읽음 처리가 차단되었습니다.',['event_code'=>$ok?'NOTIFICATIONS_ALL_READ':'NOTIFICATIONS_ALL_READ_BLOCKED','result'=>$ok?'SUCCESS':'BLOCKED','service'=>self::class,'action'=>'mark-all-read','actor'=>ActorHelper::user()]);return$ok; }
    public function createNotification(array $data): bool { $recipient=trim((string)($data['recipient_user_id']??''));if($recipient===''){$this->logger->warning('알림 생성이 차단되었습니다.',['event_code'=>'NOTIFICATION_CREATE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'create','reason_code'=>'RECIPIENT_REQUIRED']);return false;}return$this->createEvent($data,[$recipient])!==''; }

    public function createEvent(array $data,array $recipientUserIds): string
    {
        $type=trim((string)($data['event_type_code']??$data['action_type']??'')); $title=trim((string)($data['title']??'')); $message=trim((string)($data['message']??'')); $sourceDomain=trim((string)($data['source_domain_code']??$data['ref_table']??'SYSTEM')); $sourceId=$this->nullable($data['source_id']??$data['ref_id']??null);
        if($type===''||$title===''||$message===''){$this->logger->warning('알림 생성이 차단되었습니다.',['event_code'=>'NOTIFICATION_CREATE_BLOCKED','result'=>'BLOCKED','service'=>self::class,'action'=>'create','reason_code'=>'REQUIRED_FIELDS_MISSING']);throw new \InvalidArgumentException('알림 필수정보가 없습니다.');}
        $recipientUserIds=array_values(array_unique(array_filter(array_map(static fn($id): string=>trim((string)$id),$recipientUserIds))));
        $deliveryPolicy=strtoupper((string)($data['delivery_policy_code']??'MANDATORY'));
        $recipientUserIds=array_values(array_filter($recipientUserIds,fn(string $userId): bool=>$this->notifications->allowsInApp($userId,$type,$deliveryPolicy)));
        if($recipientUserIds===[]) return '';
        $now=(string)($data['occurred_at']??date('Y-m-d H:i:s')); $requestKey=$this->nullable($data['request_key']??null); $eventKey=trim((string)($data['event_key']??''))?:implode(':',[$type,$sourceDomain,$sourceId??'NONE',hash('sha256',$title.'|'.($requestKey??$message))]); $actor=trim((string)($data['created_by']??''))?:ActorHelper::user(); $fallback=$this->safeInternalUrl($data['action_url_fallback']??$data['action_url']??null);
        $event=[':id'=>(string)($data['id']??UuidHelper::generate()),':source_domain_code'=>$sourceDomain,':source_id'=>$sourceId,':event_type_code'=>$type,':event_key'=>$eventKey,':title'=>$title,':message'=>$message,':template_key'=>$this->nullable($data['template_key']??null),':payload_json'=>$this->json($data['payload']??null),':importance_code'=>strtoupper((string)($data['importance_code']??'NORMAL')),':occurred_at'=>$now,':request_key'=>$requestKey,':created_by'=>$actor,':created_at'=>$now];
        $defaultPageKey=$sourceDomain==='user_approval_requests'?'web.approval.inbox':null;
        $defaultFallback=$sourceDomain==='user_approval_requests'&&$sourceId!==null?'/approval/status?box=submitted&request_id='.rawurlencode($sourceId):$fallback;
        $defaultParams=$sourceDomain==='user_approval_requests'&&$sourceId!==null?['box'=>'submitted','request_id'=>$sourceId]:null;
        $recipients=array_map(fn(string $userId): array=>['id'=>UuidHelper::generate(),'delivery_id'=>UuidHelper::generate(),'recipient_user_id'=>$userId,'delivery_policy_code'=>$deliveryPolicy,'action_page_key'=>$this->nullable($data['action_page_key']??$defaultPageKey),'action_entity_type_code'=>$this->nullable($data['action_entity_type_code']??$sourceDomain),'action_entity_id'=>$this->nullable($data['action_entity_id']??$sourceId),'action_params_json'=>$this->json($data['action_params']??$defaultParams),'action_url_fallback'=>$defaultFallback,'created_at'=>$now,'request_key'=>$requestKey,'updated_by'=>$actor],$recipientUserIds);
        try{$eventId=$this->notifications->createEvent($event,$recipients);$this->logger->info('시스템 알림이 생성되었습니다.',['event_code'=>'NOTIFICATION_EVENT_CREATED','result'=>'SUCCESS','service'=>self::class,'action'=>'create','actor'=>$actor,'target_id'=>$eventId,'event_type'=>$type,'recipient_count'=>count($recipients),'source_domain'=>$sourceDomain,'source_id'=>$sourceId]);return$eventId;}
        catch(\Throwable$e){$this->logger->error('시스템 알림 생성에 실패했습니다.',['event_code'=>'NOTIFICATION_EVENT_CREATE_FAILED','result'=>'FAILED','service'=>self::class,'action'=>'create','actor'=>$actor,'event_type'=>$type,'recipient_count'=>count($recipients),'source_domain'=>$sourceDomain,'source_id'=>$sourceId,'error_code'=>get_class($e),'error'=>$e]);throw$e;}
    }

    public function getAdminUserIds(): array { return $this->users->getActiveAdminUserIds(); }
    private function resolveActionUrl(array $row): ?string { $base=$this->safeInternalUrl($row['action_page_route']??null); if($base!==null){$params=json_decode((string)($row['action_params_json']??''),true); return is_array($params)&&$params!==[]?$base.(str_contains($base,'?')?'&':'?').http_build_query($params):$base;} return $this->safeInternalUrl($row['action_url_fallback']??null); }
    private function safeInternalUrl(mixed $value): ?string { $url=trim((string)($value??'')); if($url===''||!str_starts_with($url,'/')||str_starts_with($url,'//')||preg_match('/[\r\n]/',$url))return null; return $url; }
    private function nullable(mixed $value): ?string { $value=trim((string)($value??'')); return $value===''?null:$value; }
    private function json(mixed $value): ?string { if($value===null||$value===[])return null; return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
}
