UPDATE `system_notification_recipients` r
JOIN `system_notification_events` e ON e.id=r.event_id
   SET r.action_page_key='web.approval.inbox',
       r.action_params_json=JSON_OBJECT('box','submitted','request_id',e.source_id),
       r.action_url_fallback=CONCAT('/approval/status?box=submitted&request_id=',e.source_id)
 WHERE e.event_key LIKE 'LEGACY:%'
   AND e.source_domain_code='user_approval_requests'
   AND e.source_id IS NOT NULL;
