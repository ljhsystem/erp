UPDATE `system_notification_recipients` r
JOIN `system_notification_events` e ON e.id=r.event_id
   SET r.action_page_key=NULL,
       r.action_params_json=JSON_OBJECT('id',e.source_id)
 WHERE e.event_key LIKE 'LEGACY:%'
   AND e.source_domain_code='user_approval_requests';
