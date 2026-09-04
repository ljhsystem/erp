UPDATE `system_page_registry`
   SET `page_label`='공지사항',
       `page_description`='공지사항',
       `source_description`='Route Registry',
       `updated_at`=NOW()
 WHERE `page_key`='dashboard.notifications';

UPDATE `system_menu_registry`
   SET `menu_label`='공지사항',
       `updated_at`=NOW()
 WHERE `page_key`='dashboard.notifications';
