<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';
$db=DbPdo::conn(); $sql=file_get_contents(PROJECT_ROOT.'/app/migrations/20260821_07_backfill_notification_core.up.sql');
if($sql===false) throw new RuntimeException('Backfill Migration 파일을 읽을 수 없습니다.');
foreach(array_values(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[]))) as $statement)$db->exec($statement);
$result=$db->query("SELECT (SELECT COUNT(*) FROM system_notifications) legacy,(SELECT COUNT(*) FROM system_notification_events WHERE event_key LIKE 'LEGACY:%') events,(SELECT COUNT(*) FROM system_notification_recipients r JOIN system_notification_events e ON e.id=r.event_id WHERE e.event_key LIKE 'LEGACY:%') recipients,(SELECT COUNT(*) FROM system_notification_deliveries d JOIN system_notification_recipients r ON r.id=d.recipient_id JOIN system_notification_events e ON e.id=r.event_id WHERE e.event_key LIKE 'LEGACY:%') deliveries")->fetch(PDO::FETCH_ASSOC);
$mismatch=(int)$db->query("SELECT COUNT(*) FROM system_notifications n JOIN system_notification_events e ON e.event_key=CONCAT('LEGACY:',n.id) JOIN system_notification_recipients r ON r.event_id=e.id AND r.recipient_user_id=n.recipient_user_id WHERE n.is_read<>r.is_read OR NOT(n.read_at<=>r.read_at) OR n.title<>e.title OR n.message<>e.message OR n.created_at<>e.created_at")->fetchColumn();
$result['mismatches']=$mismatch; $result['success']=count(array_unique(array_map('intval',array_slice($result,0,4))))===1&&$mismatch===0;
$result['fixture_remaining']=(int)$db->query("SELECT COUNT(*) FROM system_notification_events WHERE event_key LIKE 'FIXTURE:%'")->fetchColumn();
$result['registry']=$db->query("SELECT page_key,page_label,page_description,default_route_url FROM system_page_registry WHERE default_route_url='/main/notifications' OR page_key LIKE '%approval%' OR page_label LIKE '%자격%' OR page_label LIKE '%교육%'")->fetchAll(PDO::FETCH_ASSOC);
$result['tables']=$db->query("SELECT table_name,table_comment FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('system_notification_events','system_notification_recipients','system_notification_deliveries','system_notification_channel_policies','system_notification_user_preferences') ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
$sampleUser=(string)$db->query('SELECT recipient_user_id FROM system_notification_recipients ORDER BY created_at DESC LIMIT 1')->fetchColumn();
if($sampleUser!==''){
    $started=microtime(true); $feed=(new App\Services\System\NotificationService($db))->getNavigationFeed($sampleUser,20);
    $result['navbar']=['items'=>count($feed['notifications']),'unread_count'=>$feed['unread_count'],'approval_pending_count'=>$feed['approval_pending_count'],'query_ms'=>round((microtime(true)-$started)*1000,3)];
}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
