<?php

namespace App\Models\System;

use PDO;
use PDOException;

class NotificationModel
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByRecipient(string $userId, int $limit, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("SELECT r.id,r.recipient_user_id,e.event_type_code action_type,e.source_domain_code ref_table,e.source_id ref_id,e.title,e.message,r.is_read,r.read_at,r.created_at,r.action_page_key,r.action_entity_type_code,r.action_entity_id,r.action_params_json,r.action_url_fallback,pr.default_route_url action_page_route FROM system_notification_recipients r JOIN system_notification_events e ON e.id=r.event_id LEFT JOIN system_page_registry pr ON pr.page_key=r.action_page_key AND pr.is_active=1 WHERE r.recipient_user_id=:user_id ORDER BY r.created_at DESC,r.id DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':user_id', $userId); $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', $offset, PDO::PARAM_INT); $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByRecipient(string $userId): int { $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM system_notification_recipients WHERE recipient_user_id=:user_id'); $stmt->execute([':user_id'=>$userId]); return (int)$stmt->fetchColumn(); }
    public function unreadCount(string $userId): int { $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM system_notification_recipients WHERE recipient_user_id=:user_id AND is_read=0'); $stmt->execute([':user_id'=>$userId]); return (int)$stmt->fetchColumn(); }
    public function markAsRead(string $id,string $userId): bool { $stmt=$this->pdo->prepare('UPDATE system_notification_recipients SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE id=:id AND recipient_user_id=:user_id'); $stmt->execute([':id'=>$id,':user_id'=>$userId]); return $stmt->rowCount()>0; }
    public function markAllAsRead(string $userId): bool { $stmt=$this->pdo->prepare('UPDATE system_notification_recipients SET is_read=1,read_at=COALESCE(read_at,NOW()) WHERE recipient_user_id=:user_id AND is_read=0'); return $stmt->execute([':user_id'=>$userId]); }
    public function allowsInApp(string $userId,string $eventType,string $deliveryPolicy): bool { if($deliveryPolicy==='MANDATORY')return true; $stmt=$this->pdo->prepare("SELECT COALESCE(p.is_enabled,1) policy_enabled,COALESCE(u.is_enabled,1) user_enabled FROM (SELECT 1) seed LEFT JOIN system_notification_channel_policies p ON p.event_type_code=:event_type AND p.channel_code='IN_APP' LEFT JOIN system_notification_user_preferences u ON u.user_id=:user_id AND u.channel_code='IN_APP'"); $stmt->execute([':event_type'=>$eventType,':user_id'=>$userId]); $row=$stmt->fetch(PDO::FETCH_ASSOC)?:[]; return (int)($row['policy_enabled']??1)===1&&(int)($row['user_enabled']??1)===1; }

    public function createEvent(array $event,array $recipients): string
    {
        $eventId=(string)$event[':id'];
        try {
            $stmt=$this->pdo->prepare('INSERT INTO system_notification_events (id,source_domain_code,source_id,event_type_code,event_key,title,message,template_key,payload_json,importance_code,occurred_at,request_key,created_by,created_at) VALUES (:id,:source_domain_code,:source_id,:event_type_code,:event_key,:title,:message,:template_key,:payload_json,:importance_code,:occurred_at,:request_key,:created_by,:created_at)');
            $stmt->execute($event);
        } catch (PDOException $e) {
            if ((string)$e->getCode()!=='23000') throw $e;
            $lookup=$this->pdo->prepare('SELECT id FROM system_notification_events WHERE event_key=:event_key'); $lookup->execute([':event_key'=>$event[':event_key']]); $eventId=(string)$lookup->fetchColumn(); if ($eventId==='') throw $e;
        }
        foreach ($recipients as $recipient) $this->createRecipient($eventId,$recipient);
        return $eventId;
    }

    private function createRecipient(string $eventId,array $recipient): void
    {
        $find=$this->pdo->prepare('SELECT id FROM system_notification_recipients WHERE event_id=:event_id AND recipient_user_id=:user_id'); $find->execute([':event_id'=>$eventId,':user_id'=>$recipient['recipient_user_id']]); $recipientId=(string)$find->fetchColumn();
        if ($recipientId==='') {
            $recipientId=(string)$recipient['id'];
            $stmt=$this->pdo->prepare('INSERT INTO system_notification_recipients (id,event_id,recipient_user_id,delivery_policy_code,action_page_key,action_entity_type_code,action_entity_id,action_params_json,action_url_fallback,is_read,read_at,created_at) VALUES (:id,:event_id,:recipient_user_id,:delivery_policy_code,:action_page_key,:action_entity_type_code,:action_entity_id,:action_params_json,:action_url_fallback,0,NULL,:created_at)');
            $stmt->execute([':id'=>$recipientId,':event_id'=>$eventId,':recipient_user_id'=>$recipient['recipient_user_id'],':delivery_policy_code'=>$recipient['delivery_policy_code'],':action_page_key'=>$recipient['action_page_key'],':action_entity_type_code'=>$recipient['action_entity_type_code'],':action_entity_id'=>$recipient['action_entity_id'],':action_params_json'=>$recipient['action_params_json'],':action_url_fallback'=>$recipient['action_url_fallback'],':created_at'=>$recipient['created_at']]);
        }
        $find=$this->pdo->prepare("SELECT id FROM system_notification_deliveries WHERE recipient_id=:recipient_id AND channel_code='IN_APP'"); $find->execute([':recipient_id'=>$recipientId]);
        if (!$find->fetchColumn()) {
            $stmt=$this->pdo->prepare("INSERT INTO system_notification_deliveries (id,recipient_id,channel_code,delivery_status_code,queued_at,sent_at,retry_count,request_key,updated_by,created_at,updated_at) VALUES (:id,:recipient_id,'IN_APP','SENT',:queued_at,:sent_at,0,:request_key,:updated_by,:created_at,:updated_at)");
            $stmt->execute([':id'=>$recipient['delivery_id'],':recipient_id'=>$recipientId,':queued_at'=>$recipient['created_at'],':sent_at'=>$recipient['created_at'],':request_key'=>$recipient['request_key'],':updated_by'=>$recipient['updated_by'],':created_at'=>$recipient['created_at'],':updated_at'=>$recipient['created_at']]);
        }
    }
}
