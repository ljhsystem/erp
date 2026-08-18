INSERT INTO `auth_permissions` (`id`,`sort_no`,`page`,`permission_source`,`category`,`permission_key`,`permission_name`,`description`,`page_key`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT UUID(),(SELECT COALESCE(MAX(p.sort_no),0)+v.s FROM auth_permissions p),'휴가관리','ROUTE','대외기관업무',v.k,v.n,CONCAT('휴가관리 ',v.n),'web.institution.human_resources.leave',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM (
 SELECT 1 s,'web.institution.human_resources.leave' k,'화면조회' n UNION ALL
 SELECT 2,'api.institution.human_resources.leave.view_self','본인조회' UNION ALL SELECT 3,'api.institution.human_resources.leave.view_all','전체조회' UNION ALL
 SELECT 4,'api.institution.human_resources.leave.detail','상세조회' UNION ALL SELECT 5,'api.institution.human_resources.leave.save','임시저장' UNION ALL
 SELECT 6,'api.institution.human_resources.leave.submit','결재상신' UNION ALL SELECT 7,'api.institution.human_resources.leave.withdraw','기안회수' UNION ALL
 SELECT 8,'api.institution.human_resources.leave.cancel','승인후취소' UNION ALL SELECT 9,'api.institution.human_resources.leave.grant','휴가부여' UNION ALL
 SELECT 10,'api.institution.human_resources.leave.adjust','잔액조정' UNION ALL SELECT 11,'api.institution.human_resources.leave.type_save','정책저장' UNION ALL
 SELECT 12,'api.institution.human_resources.leave.excel','Excel다운로드'
)v WHERE NOT EXISTS(SELECT 1 FROM auth_permissions x WHERE x.permission_key=v.k);

INSERT INTO `auth_role_permissions` (`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
SELECT UUID(),r.id,p.id,NOW(),'SYSTEM:MIGRATION' FROM auth_roles r JOIN auth_permissions p ON p.permission_key='web.institution.human_resources.leave' OR p.permission_key LIKE 'api.institution.human_resources.leave.%' LEFT JOIN auth_role_permissions rp ON rp.role_id=r.id AND rp.permission_id=p.id WHERE r.role_key='super_admin' AND rp.id IS NULL;

INSERT INTO `system_page_registry` (`page_key`,`module_key`,`module_label`,`menu_key`,`menu_label`,`page_label`,`page_description`,`breadcrumb`,`default_route_key`,`default_route_url`,`source_description`,`is_active`,`created_at`,`updated_at`)
VALUES ('web.institution.human_resources.leave','institution','대외기관업무','institution.human_resources','인사·노무관리','휴가관리','휴가 신청·부여·잔액·원장 관리','대외기관업무 > 인사·노무관리 > 휴가관리','web.institution.human_resources.leave','/institution/human-resources/leave','휴가관리 1차 SSOT',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE page_label=VALUES(page_label),page_description=VALUES(page_description),breadcrumb=VALUES(breadcrumb),default_route_key=VALUES(default_route_key),default_route_url=VALUES(default_route_url),source_description=VALUES(source_description),is_active=1,updated_at=NOW();

INSERT INTO `user_approval_templates` (`id`,`sort_no`,`template_key`,`template_name`,`document_type`,`description`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT '30b621cb-2f5a-4eb6-ae22-9087f9833461',COALESCE(MAX(sort_no),0)+1,'LEAVE_REQUEST_DEFAULT','휴가신청 기본 결재','LEAVE_REQUEST','기안자 제출 후 지정 최고관리자 최종 승인',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM user_approval_templates WHERE NOT EXISTS(SELECT 1 FROM user_approval_templates WHERE document_type='LEAVE_REQUEST' AND is_active=1);
INSERT INTO `user_approval_template_steps` (`id`,`sort_no`,`template_id`,`step_name`,`step_type`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT '0aec432d-d252-45be-b63e-3e1199a9fd81',1,t.id,'기안','SUBMIT',NULL,NULL,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM user_approval_templates t WHERE t.template_key='LEAVE_REQUEST_DEFAULT' AND NOT EXISTS(SELECT 1 FROM user_approval_template_steps s WHERE s.template_id=t.id AND s.sort_no=1);
INSERT INTO `user_approval_template_steps` (`id`,`sort_no`,`template_id`,`step_name`,`step_type`,`role_id`,`approver_id`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
SELECT 'e5c3a70f-9ac4-4bde-82e9-9adf0d646f2c',2,t.id,'최종승인','FINAL_APPROVAL','c1c90ecf-1a44-470c-8d9c-4d6e671cdcfa','f113b666-ff40-4f93-a7e7-8cea4cdc9c28',1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION' FROM user_approval_templates t WHERE t.template_key='LEAVE_REQUEST_DEFAULT' AND NOT EXISTS(SELECT 1 FROM user_approval_template_steps s WHERE s.template_id=t.id AND s.sort_no=2);
