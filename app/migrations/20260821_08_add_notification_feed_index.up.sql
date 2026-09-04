ALTER TABLE `system_notification_recipients`
  ADD KEY `idx_notification_recipient_recent` (`recipient_user_id`,`created_at`);
