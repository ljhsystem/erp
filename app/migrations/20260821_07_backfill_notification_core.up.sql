INSERT INTO `system_notification_events`
(`id`,`source_domain_code`,`source_id`,`event_type_code`,`event_key`,`title`,`message`,`payload_json`,`importance_code`,`occurred_at`,`request_key`,`created_by`,`created_at`)
SELECT n.id,
       COALESCE(NULLIF(n.ref_table,''),'LEGACY_NOTIFICATION'),
       n.ref_id,
       n.action_type,
       CONCAT('LEGACY:',n.id),
       n.title,
       n.message,
       JSON_OBJECT('legacy_notification_id',n.id,'ref_table',n.ref_table,'ref_id',n.ref_id,'actor_user_id',n.actor_user_id),
       'NORMAL',
       n.created_at,
       CONCAT('LEGACY:',n.id),
       'SYSTEM:MIGRATION',
       n.created_at
  FROM `system_notifications` n
 WHERE NOT EXISTS (SELECT 1 FROM `system_notification_events` e WHERE e.event_key=CONCAT('LEGACY:',n.id));

INSERT INTO `system_notification_recipients`
(`id`,`event_id`,`recipient_user_id`,`delivery_policy_code`,`action_page_key`,`action_entity_type_code`,`action_entity_id`,`action_params_json`,`action_url_fallback`,`is_read`,`read_at`,`created_at`)
SELECT UUID(),e.id,n.recipient_user_id,'MANDATORY',
       CASE WHEN n.ref_table='user_approval_requests' THEN 'web.approval.inbox' ELSE NULL END,
       n.ref_table,n.ref_id,
       CASE WHEN n.ref_table='user_approval_requests' AND n.ref_id IS NOT NULL THEN JSON_OBJECT('box','submitted','request_id',n.ref_id) WHEN n.ref_id IS NULL THEN NULL ELSE JSON_OBJECT('id',n.ref_id) END,
       CASE WHEN n.ref_table='user_approval_requests' AND n.ref_id IS NOT NULL THEN CONCAT('/approval/status?box=submitted&request_id=',n.ref_id) ELSE NULL END,
       n.is_read,n.read_at,n.created_at
  FROM `system_notifications` n
  JOIN `system_notification_events` e ON e.event_key=CONCAT('LEGACY:',n.id)
 WHERE NOT EXISTS (
       SELECT 1 FROM `system_notification_recipients` r
        WHERE r.event_id=e.id AND r.recipient_user_id=n.recipient_user_id
 );

INSERT INTO `system_notification_deliveries`
(`id`,`recipient_id`,`channel_code`,`delivery_status_code`,`queued_at`,`sent_at`,`retry_count`,`request_key`,`updated_by`,`created_at`,`updated_at`)
SELECT UUID(),r.id,'IN_APP','SENT',n.created_at,n.created_at,0,CONCAT('LEGACY:',n.id),'SYSTEM:MIGRATION',n.created_at,n.created_at
  FROM `system_notifications` n
  JOIN `system_notification_events` e ON e.event_key=CONCAT('LEGACY:',n.id)
  JOIN `system_notification_recipients` r ON r.event_id=e.id AND r.recipient_user_id=n.recipient_user_id
 WHERE NOT EXISTS (
       SELECT 1 FROM `system_notification_deliveries` d
        WHERE d.recipient_id=r.id AND d.channel_code='IN_APP'
 );
