-- 개인경비 공식 재처리 레거시 구조 제거.
-- 운영 DB 적용은 별도 승인 및 실행 절차를 따른다.

DROP TABLE IF EXISTS `ledger_personal_expense_reprocess_items`;
DROP TABLE IF EXISTS `ledger_personal_expense_reprocess_batches`;

DELETE `role_permission`
FROM `auth_role_permissions` `role_permission`
INNER JOIN `auth_permissions` `permission`
    ON `permission`.`id` = `role_permission`.`permission_id`
WHERE `permission`.`permission_key` = 'api.approval.personal-expense.reprocess-accounting';

DELETE FROM `auth_permissions`
WHERE `permission_key` = 'api.approval.personal-expense.reprocess-accounting';
