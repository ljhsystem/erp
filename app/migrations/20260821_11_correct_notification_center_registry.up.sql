UPDATE `system_page_registry`
   SET `page_label`='알림센터',
       `page_description`='내 업무 알림센터',
       `source_description`='공용 Notification Core 알림센터',
       `updated_at`=NOW()
 WHERE `page_key`='dashboard.notifications';

UPDATE `system_menu_registry`
   SET `menu_label`='알림센터',
       `updated_at`=NOW()
 WHERE `page_key`='dashboard.notifications';
