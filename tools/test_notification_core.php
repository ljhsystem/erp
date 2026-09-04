<?php
declare(strict_types=1);

use App\Services\System\NotificationService;
use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db=DbPdo::conn();
$users=$db->query("SELECT id FROM auth_users WHERE is_active=1 ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
if(count($users)<3) throw new RuntimeException('Fixture에는 활성 사용자 3명이 필요합니다.');
$service=new NotificationService($db); $prefix='FIXTURE:NOTIFICATION:'.bin2hex(random_bytes(6)); $checks=[];
$assert=static function(bool $condition,string $name) use (&$checks): void { if(!$condition)throw new RuntimeException($name.' 검증 실패'); $checks[]=$name; };

$db->beginTransaction();
try {
    $eventId=$service->createEvent(['event_type_code'=>'FIXTURE_MANDATORY','source_domain_code'=>'FIXTURE','source_id'=>'A','event_key'=>$prefix.':A','title'=>'Fixture 알림','message'=>'Core 원자성 검증','delivery_policy_code'=>'MANDATORY','action_page_key'=>'web.main.notifications','action_entity_type_code'=>'FIXTURE','action_entity_id'=>'A','action_url_fallback'=>'/main/notifications','created_by'=>'SYSTEM:FIXTURE'], $users);
    $assert($eventId!=='','Event 생성');
    $counts=$db->query("SELECT (SELECT COUNT(*) FROM system_notification_events WHERE event_key=".$db->quote($prefix.':A').") events,(SELECT COUNT(*) FROM system_notification_recipients WHERE event_id=".$db->quote($eventId).") recipients,(SELECT COUNT(*) FROM system_notification_deliveries d JOIN system_notification_recipients r ON r.id=d.recipient_id WHERE r.event_id=".$db->quote($eventId).") deliveries")->fetch();
    $assert((int)$counts['events']===1,'Event 1건'); $assert((int)$counts['recipients']===3,'Recipient 3명'); $assert((int)$counts['deliveries']===3,'IN_APP Delivery 3건');
    $same=$service->createEvent(['event_type_code'=>'FIXTURE_MANDATORY','source_domain_code'=>'FIXTURE','source_id'=>'A','event_key'=>$prefix.':A','title'=>'Fixture 알림','message'=>'Core 원자성 검증','delivery_policy_code'=>'MANDATORY','created_by'=>'SYSTEM:FIXTURE'],$users);
    $assert($same===$eventId,'Event 멱등성');
    $counts=$db->query('SELECT COUNT(*) recipients,(SELECT COUNT(*) FROM system_notification_deliveries d JOIN system_notification_recipients x ON x.id=d.recipient_id WHERE x.event_id='.$db->quote($eventId).') deliveries FROM system_notification_recipients r WHERE r.event_id='.$db->quote($eventId))->fetch();
    $assert((int)$counts['recipients']===3,'Recipient 멱등성'); $assert((int)$counts['deliveries']===3,'Delivery 멱등성');

    $recipientId=(string)$db->query('SELECT id FROM system_notification_recipients WHERE event_id='.$db->quote($eventId).' AND recipient_user_id='.$db->quote($users[0]))->fetchColumn();
    $assert($service->markAsRead($recipientId,$users[0]),'읽음 처리');
    $assert(!$service->markAsRead($recipientId,$users[1]),'타인 읽음 차단');
    $service->markAllAsRead($users[0]);
    $page=$service->getNotificationPage($users[0],1,20); $assert($page['total']>=1,'알림센터 Paging');

    $db->prepare("INSERT INTO system_notification_user_preferences (user_id,channel_code,is_enabled,updated_by) VALUES (:user,'IN_APP',0,'SYSTEM:FIXTURE') ON DUPLICATE KEY UPDATE is_enabled=0,updated_by='SYSTEM:FIXTURE'")->execute([':user'=>$users[2]]);
    $optional=$service->createEvent(['event_type_code'=>'FIXTURE_OPTIONAL','source_domain_code'=>'FIXTURE','source_id'=>'B','event_key'=>$prefix.':B','title'=>'선택 알림','message'=>'Preference 검증','delivery_policy_code'=>'OPTIONAL','created_by'=>'SYSTEM:FIXTURE'],$users);
    $optionalCount=(int)$db->query('SELECT COUNT(*) FROM system_notification_recipients WHERE event_id='.$db->quote($optional))->fetchColumn(); $assert($optionalCount===2,'OPTIONAL Preference');
    $mandatory=$service->createEvent(['event_type_code'=>'FIXTURE_MANDATORY','source_domain_code'=>'FIXTURE','source_id'=>'C','event_key'=>$prefix.':C','title'=>'필수 알림','message'=>'Mandatory 검증','delivery_policy_code'=>'MANDATORY','created_by'=>'SYSTEM:FIXTURE'],$users);
    $mandatoryCount=(int)$db->query('SELECT COUNT(*) FROM system_notification_recipients WHERE event_id='.$db->quote($mandatory))->fetchColumn(); $assert($mandatoryCount===3,'MANDATORY 우선');

    foreach(['TRAINING_ASSIGNED','TRAINING_UPDATED','TRAINING_CANCELLED'] as $type){ $id=$service->createEvent(['event_type_code'=>$type,'source_domain_code'=>'EDUCATION_SESSION','source_id'=>'fixture-session','event_key'=>$prefix.':'.$type,'title'=>$type,'message'=>'교육 Notification 연계 검증','delivery_policy_code'=>'MANDATORY','action_page_key'=>'web.institution.human_resources.qualification_education','action_entity_id'=>'fixture-session','created_by'=>'SYSTEM:FIXTURE'],$users); $assert($id!=='',$type); }
    $ackBefore=$db->query('SELECT acknowledged_at FROM institution_educations_session_targets LIMIT 1')->fetchColumn(); $service->markAllAsRead($users[0]); $ackAfter=$db->query('SELECT acknowledged_at FROM institution_educations_session_targets LIMIT 1')->fetchColumn(); $assert($ackBefore===$ackAfter,'교육 acknowledged 분리');

    $start=microtime(true); $eventStmt=$db->prepare("INSERT INTO system_notification_events (id,source_domain_code,source_id,event_type_code,event_key,title,message,importance_code,occurred_at,created_by) VALUES (UUID(),'FIXTURE',:source,'FIXTURE_SCALE',:event_key,'성능 Fixture','성능 검증','NORMAL',NOW(),'SYSTEM:FIXTURE')");
    $recipientStmt=$db->prepare("INSERT INTO system_notification_recipients (id,event_id,recipient_user_id,delivery_policy_code,created_at) SELECT UUID(),id,:user,'MANDATORY',NOW() FROM system_notification_events WHERE event_key=:event_key");
    $deliveryStmt=$db->prepare("INSERT INTO system_notification_deliveries (id,recipient_id,channel_code,delivery_status_code,queued_at,sent_at,updated_by) SELECT UUID(),r.id,'IN_APP','SENT',NOW(),NOW(),'SYSTEM:FIXTURE' FROM system_notification_recipients r JOIN system_notification_events e ON e.id=r.event_id WHERE e.event_key=:event_key AND r.recipient_user_id=:user");
    for($i=0;$i<10;$i++){ $key=$prefix.':SCALE:'.$i; $eventStmt->execute([':source'=>(string)$i,':event_key'=>$key]); foreach([$users[0],$users[1]] as $user){$recipientStmt->execute([':user'=>$user,':event_key'=>$key]);$deliveryStmt->execute([':event_key'=>$key,':user'=>$user]);} }
    $insertMs=round((microtime(true)-$start)*1000,2); $queryTimes=[];
    foreach(['recent'=>'SELECT r.id FROM system_notification_recipients r WHERE r.recipient_user_id='.$db->quote($users[0]).' ORDER BY r.created_at DESC LIMIT 20','unread'=>'SELECT COUNT(*) FROM system_notification_recipients WHERE recipient_user_id='.$db->quote($users[0]).' AND is_read=0','paging'=>'SELECT r.id FROM system_notification_recipients r WHERE r.recipient_user_id='.$db->quote($users[0]).' ORDER BY r.created_at DESC LIMIT 20 OFFSET 1000','queue'=>"SELECT id FROM system_notification_deliveries WHERE delivery_status_code='QUEUED' AND locked_at IS NULL ORDER BY next_attempt_at LIMIT 20"] as $name=>$sql){$q=microtime(true);$db->query($sql)->fetchAll();$queryTimes[$name]=round((microtime(true)-$q)*1000,3);}
    $assert(array_sum($queryTimes)<1000,'성능 Fixture Query');
    $result=['success'=>true,'checks'=>$checks,'scale'=>['events'=>10,'recipients'=>20,'deliveries'=>20,'insert_ms'=>$insertMs,'query_ms'=>$queryTimes]];
    $db->rollBack();
    $remaining=(int)$db->query("SELECT COUNT(*) FROM system_notification_events WHERE event_key LIKE ".$db->quote($prefix.'%'))->fetchColumn();
    $result['fixture_remaining']=$remaining; $assert($remaining===0,'Fixture 잔존 0'); $result['checks']=$checks;
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); throw $e; }
