<?php
declare(strict_types=1);

use Core\DbPdo;

define('PROJECT_ROOT', dirname(__DIR__));
require PROJECT_ROOT . '/vendor/autoload.php';

$db=DbPdo::conn(); $users=$db->query('SELECT id FROM auth_users WHERE is_active=1 ORDER BY id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
if(count($users)<2) throw new RuntimeException('성능 Fixture에는 활성 사용자 2명이 필요합니다.');
$prefix='FIXTURE:NOTIFICATION:SCALE:'.bin2hex(random_bytes(5)).':'; $db->beginTransaction();
try {
    $started=microtime(true); $db->exec('SET @notification_fixture_no:=0');
    $db->exec("INSERT INTO system_notification_events (id,source_domain_code,source_id,event_type_code,event_key,title,message,importance_code,occurred_at,created_by) SELECT UUID(),'FIXTURE_SCALE',CAST(n AS CHAR),'FIXTURE_SCALE',CONCAT(".$db->quote($prefix).",n),'알림 성능 Fixture','알림 성능 Fixture','NORMAL',NOW(),'SYSTEM:FIXTURE' FROM (SELECT @notification_fixture_no:=@notification_fixture_no+1 n FROM information_schema.columns a CROSS JOIN information_schema.columns b LIMIT 500) seq");
    $db->exec("INSERT INTO system_notification_recipients (id,event_id,recipient_user_id,delivery_policy_code,created_at) SELECT UUID(),e.id,u.user_id,'MANDATORY',e.created_at FROM system_notification_events e JOIN (SELECT ".$db->quote($users[0])." user_id UNION ALL SELECT ".$db->quote($users[1]).") u WHERE e.event_key LIKE ".$db->quote($prefix.'%'));
    $db->exec("INSERT INTO system_notification_deliveries (id,recipient_id,channel_code,delivery_status_code,queued_at,sent_at,updated_by) SELECT UUID(),r.id,'IN_APP','SENT',r.created_at,r.created_at,'SYSTEM:FIXTURE' FROM system_notification_recipients r JOIN system_notification_events e ON e.id=r.event_id WHERE e.event_key LIKE ".$db->quote($prefix.'%'));
    $insertMs=round((microtime(true)-$started)*1000,2); $queries=[
        'recent'=>'SELECT r.id,e.title FROM system_notification_recipients r JOIN system_notification_events e ON e.id=r.event_id WHERE r.recipient_user_id='.$db->quote($users[0]).' ORDER BY r.created_at DESC LIMIT 20',
        'unread'=>'SELECT COUNT(*) FROM system_notification_recipients WHERE recipient_user_id='.$db->quote($users[0]).' AND is_read=0',
        'paging'=>'SELECT r.id FROM system_notification_recipients r WHERE r.recipient_user_id='.$db->quote($users[0]).' ORDER BY r.created_at DESC LIMIT 20 OFFSET 250',
        'source'=>"SELECT id FROM system_notification_events WHERE source_domain_code='FIXTURE_SCALE' AND source_id='250' AND event_type_code='FIXTURE_SCALE'",
        'queue'=>"SELECT id FROM system_notification_deliveries WHERE delivery_status_code='QUEUED' AND locked_at IS NULL ORDER BY next_attempt_at LIMIT 20",
    ]; $times=[];$explains=[];
    foreach($queries as $name=>$sql){$time=microtime(true);$db->query($sql)->fetchAll();$times[$name]=round((microtime(true)-$time)*1000,3);$explains[$name]=$db->query('EXPLAIN '.$sql)->fetchAll(PDO::FETCH_ASSOC);}
    $counts=$db->query("SELECT (SELECT COUNT(*) FROM system_notification_events WHERE event_key LIKE ".$db->quote($prefix.'%').") events,(SELECT COUNT(*) FROM system_notification_recipients r JOIN system_notification_events e ON e.id=r.event_id WHERE e.event_key LIKE ".$db->quote($prefix.'%').") recipients,(SELECT COUNT(*) FROM system_notification_deliveries d JOIN system_notification_recipients r ON r.id=d.recipient_id JOIN system_notification_events e ON e.id=r.event_id WHERE e.event_key LIKE ".$db->quote($prefix.'%').") deliveries")->fetch(PDO::FETCH_ASSOC);
    $db->rollBack(); $remaining=(int)$db->query("SELECT COUNT(*) FROM system_notification_events WHERE event_key LIKE ".$db->quote($prefix.'%'))->fetchColumn();
    echo json_encode(['success'=>$counts==['events'=>500,'recipients'=>1000,'deliveries'=>1000]&&$remaining===0,'counts'=>$counts,'insert_ms'=>$insertMs,'query_ms'=>$times,'explains'=>$explains,'fixture_remaining'=>$remaining],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
