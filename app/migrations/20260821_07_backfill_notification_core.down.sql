DELETE d FROM `system_notification_deliveries` d
JOIN `system_notification_recipients` r ON r.id=d.recipient_id
JOIN `system_notification_events` e ON e.id=r.event_id
WHERE e.event_key LIKE 'LEGACY:%';
DELETE r FROM `system_notification_recipients` r
JOIN `system_notification_events` e ON e.id=r.event_id
WHERE e.event_key LIKE 'LEGACY:%';
DELETE FROM `system_notification_events` WHERE `event_key` LIKE 'LEGACY:%';
