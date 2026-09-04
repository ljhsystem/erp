START TRANSACTION;

INSERT INTO `system_page_registry` (
    `page_key`,`module_key`,`module_label`,`menu_key`,`menu_label`,`page_label`,
    `page_description`,`breadcrumb`,`default_route_key`,`default_route_url`,
    `source_description`,`is_active`,`created_at`,`updated_at`
)
VALUES (
    'approval.leave_request','approval','전자결재','approval.main','전자결재','휴가신청',
    '직원 본인 휴가 신청 및 결재','전자결재 > 휴가신청',
    'web.approval.leave-request','/approval/leave-request',
    '전자결재 휴가신청 직원 기안 Application',1,NOW(),NOW()
)
ON DUPLICATE KEY UPDATE
    `module_key`=VALUES(`module_key`),`module_label`=VALUES(`module_label`),
    `menu_key`=VALUES(`menu_key`),`menu_label`=VALUES(`menu_label`),
    `page_label`=VALUES(`page_label`),`page_description`=VALUES(`page_description`),
    `breadcrumb`=VALUES(`breadcrumb`),`default_route_key`=VALUES(`default_route_key`),
    `default_route_url`=VALUES(`default_route_url`),`source_description`=VALUES(`source_description`),
    `is_active`=1,`updated_at`=NOW();

UPDATE `system_page_registry`
SET `page_description`='관리자용 전체 휴가 현황·부여·잔액·휴가유형 관리',
    `source_description`='관리자 휴가 SSOT 관리',`updated_at`=NOW()
WHERE `page_key`='web.institution.human_resources.leave';

SET @leave_permission_sort := (SELECT COALESCE(MAX(`sort_no`),0) FROM `auth_permissions`);
INSERT INTO `auth_permissions` (
    `id`,`sort_no`,`page`,`permission_source`,`category`,`permission_key`,
    `permission_name`,`description`,`page_key`,`is_active`,
    `created_at`,`created_by`,`updated_at`,`updated_by`
)
SELECT UUID(),(@leave_permission_sort := @leave_permission_sort + 1),
       v.page_label,'ROUTE',v.category,v.permission_key,v.permission_name,v.description,
       v.page_key,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
FROM (
    SELECT 1 seq,'휴가신청' page_label,'전자결재' category,'web.approval.leave-request' permission_key,'화면조회' permission_name,'전자결재 휴가신청 화면 조회' description,'approval.leave_request' page_key
    UNION ALL SELECT 2,'휴가신청','전자결재','api.approval.leave-request.list','목록조회','본인 휴가신청 목록 조회','approval.leave_request'
    UNION ALL SELECT 3,'휴가신청','전자결재','api.approval.leave-request.options','선택항목조회','본인 휴가신청 선택항목 조회','approval.leave_request'
    UNION ALL SELECT 4,'휴가신청','전자결재','api.approval.leave-request.detail','상세조회','본인 휴가신청 상세 조회','approval.leave_request'
    UNION ALL SELECT 5,'휴가신청','전자결재','api.approval.leave-request.save','임시저장','본인 휴가신청 임시저장','approval.leave_request'
    UNION ALL SELECT 6,'휴가신청','전자결재','api.approval.leave-request.save-submit','저장후결재요청','본인 휴가신청 저장 후 결재요청','approval.leave_request'
    UNION ALL SELECT 7,'휴가신청','전자결재','api.approval.leave-request.submit','결재요청','기존 본인 휴가신청 결재요청','approval.leave_request'
    UNION ALL SELECT 8,'휴가신청','전자결재','api.approval.leave-request.withdraw','기안회수','본인 휴가신청 기안회수','approval.leave_request'
    UNION ALL SELECT 9,'휴가신청','전자결재','api.approval.leave-request.cancel-request','취소요청','승인된 본인 휴가 취소요청','approval.leave_request'
) v
WHERE NOT EXISTS (SELECT 1 FROM `auth_permissions` p WHERE p.`permission_key`=v.permission_key)
ORDER BY v.seq;

UPDATE `auth_permissions` p
JOIN (
    SELECT 'web.approval.leave-request' permission_key,'화면조회' permission_name,'전자결재 휴가신청 화면 조회' description
    UNION ALL SELECT 'api.approval.leave-request.list','목록조회','본인 휴가신청 목록 조회'
    UNION ALL SELECT 'api.approval.leave-request.options','선택항목조회','본인 휴가신청 선택항목 조회'
    UNION ALL SELECT 'api.approval.leave-request.detail','상세조회','본인 휴가신청 상세 조회'
    UNION ALL SELECT 'api.approval.leave-request.save','임시저장','본인 휴가신청 임시저장'
    UNION ALL SELECT 'api.approval.leave-request.save-submit','저장후결재요청','본인 휴가신청 저장 후 결재요청'
    UNION ALL SELECT 'api.approval.leave-request.submit','결재요청','기존 본인 휴가신청 결재요청'
    UNION ALL SELECT 'api.approval.leave-request.withdraw','기안회수','본인 휴가신청 기안회수'
    UNION ALL SELECT 'api.approval.leave-request.cancel-request','취소요청','승인된 본인 휴가 취소요청'
) v ON v.permission_key=p.permission_key
SET p.`page`='휴가신청',p.`permission_source`='ROUTE',p.`category`='전자결재',
    p.`permission_name`=v.permission_name,p.`description`=v.description,
    p.`page_key`='approval.leave_request',p.`is_active`=1,
    p.`updated_at`=NOW(),p.`updated_by`='SYSTEM:MIGRATION';

UPDATE `auth_permissions`
SET `page`='휴가관리',`permission_source`='ROUTE',`category`='대외기관업무',
    `page_key`='web.institution.human_resources.leave',`is_active`=1,
    `updated_at`=NOW(),`updated_by`='SYSTEM:MIGRATION'
WHERE `permission_key` IN (
    'web.institution.human_resources.leave',
    'api.institution.human_resources.leave.status_list',
    'api.institution.human_resources.leave.balance_list',
    'api.institution.human_resources.leave.options',
    'api.institution.human_resources.leave.detail',
    'api.institution.human_resources.leave.grant',
    'api.institution.human_resources.leave.adjust',
    'api.institution.human_resources.leave.type_save'
);

INSERT INTO `auth_role_permissions` (`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
SELECT UUID(),old_map.role_id,new_permission.id,NOW(),'SYSTEM:MIGRATION'
FROM `auth_role_permissions` old_map
JOIN `auth_permissions` old_permission ON old_permission.id=old_map.permission_id
JOIN (
    SELECT 'api.institution.human_resources.leave.view_self' old_key,'web.approval.leave-request' new_key
    UNION ALL SELECT 'api.institution.human_resources.leave.view_self','api.approval.leave-request.list'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_self','api.approval.leave-request.options'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_self','api.approval.leave-request.detail'
    UNION ALL SELECT 'api.institution.human_resources.leave.save','api.approval.leave-request.save'
    UNION ALL SELECT 'api.institution.human_resources.leave.submit','api.approval.leave-request.save-submit'
    UNION ALL SELECT 'api.institution.human_resources.leave.submit','api.approval.leave-request.submit'
    UNION ALL SELECT 'api.institution.human_resources.leave.withdraw','api.approval.leave-request.withdraw'
    UNION ALL SELECT 'api.institution.human_resources.leave.cancel','api.approval.leave-request.cancel-request'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','web.institution.human_resources.leave'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.status_list'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.balance_list'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.options'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.detail'
) transition ON transition.old_key=old_permission.permission_key
JOIN `auth_permissions` new_permission ON new_permission.permission_key=transition.new_key
LEFT JOIN `auth_role_permissions` existing ON existing.role_id=old_map.role_id AND existing.permission_id=new_permission.id
WHERE existing.id IS NULL;

INSERT INTO `auth_user_permissions` (`id`,`user_id`,`permission_id`,`created_at`,`created_by`)
SELECT UUID(),old_map.user_id,new_permission.id,NOW(),'SYSTEM:MIGRATION'
FROM `auth_user_permissions` old_map
JOIN `auth_permissions` old_permission ON old_permission.id=old_map.permission_id
JOIN (
    SELECT 'api.institution.human_resources.leave.view_self' old_key,'web.approval.leave-request' new_key
    UNION ALL SELECT 'api.institution.human_resources.leave.view_self','api.approval.leave-request.list'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_self','api.approval.leave-request.options'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_self','api.approval.leave-request.detail'
    UNION ALL SELECT 'api.institution.human_resources.leave.save','api.approval.leave-request.save'
    UNION ALL SELECT 'api.institution.human_resources.leave.submit','api.approval.leave-request.save-submit'
    UNION ALL SELECT 'api.institution.human_resources.leave.submit','api.approval.leave-request.submit'
    UNION ALL SELECT 'api.institution.human_resources.leave.withdraw','api.approval.leave-request.withdraw'
    UNION ALL SELECT 'api.institution.human_resources.leave.cancel','api.approval.leave-request.cancel-request'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','web.institution.human_resources.leave'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.status_list'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.balance_list'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.options'
    UNION ALL SELECT 'api.institution.human_resources.leave.view_all','api.institution.human_resources.leave.detail'
) transition ON transition.old_key=old_permission.permission_key
JOIN `auth_permissions` new_permission ON new_permission.permission_key=transition.new_key
LEFT JOIN `auth_user_permissions` existing ON existing.user_id=old_map.user_id AND existing.permission_id=new_permission.id
WHERE existing.id IS NULL;

INSERT INTO `auth_role_permissions` (`id`,`role_id`,`permission_id`,`created_at`,`created_by`)
SELECT UUID(),r.id,p.id,NOW(),'SYSTEM:MIGRATION'
FROM `auth_roles` r
JOIN `auth_permissions` p ON p.permission_key IN (
    'web.approval.leave-request','api.approval.leave-request.list','api.approval.leave-request.options',
    'api.approval.leave-request.detail','api.approval.leave-request.save','api.approval.leave-request.save-submit',
    'api.approval.leave-request.submit','api.approval.leave-request.withdraw','api.approval.leave-request.cancel-request',
    'web.institution.human_resources.leave','api.institution.human_resources.leave.status_list',
    'api.institution.human_resources.leave.balance_list','api.institution.human_resources.leave.options',
    'api.institution.human_resources.leave.detail','api.institution.human_resources.leave.grant',
    'api.institution.human_resources.leave.adjust','api.institution.human_resources.leave.type_save'
)
LEFT JOIN `auth_role_permissions` existing ON existing.role_id=r.id AND existing.permission_id=p.id
WHERE r.role_key='super_admin' AND existing.id IS NULL;

DELETE old_user_map
FROM `auth_user_permissions` old_user_map
JOIN `auth_permissions` old_permission ON old_permission.id=old_user_map.permission_id
WHERE old_permission.permission_key IN (
    'api.institution.human_resources.leave.view_self','api.institution.human_resources.leave.view_all',
    'api.institution.human_resources.leave.save','api.institution.human_resources.leave.submit',
    'api.institution.human_resources.leave.withdraw','api.institution.human_resources.leave.cancel',
    'api.institution.human_resources.leave.excel'
);

DELETE old_role_map
FROM `auth_role_permissions` old_role_map
JOIN `auth_permissions` old_permission ON old_permission.id=old_role_map.permission_id
WHERE old_permission.permission_key IN (
    'api.institution.human_resources.leave.view_self','api.institution.human_resources.leave.view_all',
    'api.institution.human_resources.leave.save','api.institution.human_resources.leave.submit',
    'api.institution.human_resources.leave.withdraw','api.institution.human_resources.leave.cancel',
    'api.institution.human_resources.leave.excel'
);

DELETE FROM `auth_permissions`
WHERE `permission_key` IN (
    'api.institution.human_resources.leave.view_self','api.institution.human_resources.leave.view_all',
    'api.institution.human_resources.leave.save','api.institution.human_resources.leave.submit',
    'api.institution.human_resources.leave.withdraw','api.institution.human_resources.leave.cancel',
    'api.institution.human_resources.leave.excel'
);

COMMIT;
