DROP PROCEDURE IF EXISTS `migrate_20260826_03_regular_income_closure`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260826_03_regular_income_closure`()
BEGIN
    IF (SELECT COUNT(*) FROM `institution_regular_employment_income_accounting_links`) <> 0
       OR (SELECT COUNT(*) FROM `institution_regular_income_accounting_schedules`) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 Closure 또는 지급예정 연결 데이터가 있어 책임분리 Migration을 차단했습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules') <> 1
       OR (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND CONSTRAINT_NAME='chk_regular_income_accounting_role_fields' AND CONSTRAINT_TYPE='CHECK') <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='예상한 Accounting Registry 기준선과 다릅니다.';
    END IF;

    ALTER TABLE `institution_regular_employment_income_accounting_links`
        DROP CONSTRAINT `chk_regular_income_accounting_role_fields`,
        DROP CONSTRAINT `chk_regular_income_accounting_role`,
        ADD CONSTRAINT `chk_regular_income_accounting_role`
            CHECK (`generation_role` IN ('EMPLOYEE_PAYROLL','INSTITUTION_LIABILITY','PAYROLL_REPORT_EVIDENCE')),
        ADD CONSTRAINT `chk_regular_income_accounting_role_fields` CHECK (
            (`generation_role`='PAYROLL_REPORT_EVIDENCE' AND `regular_employment_income_item_id` IS NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NULL AND `payment_schedule_id` IS NULL AND `recognition_date` IS NULL)
            OR (`generation_role`='EMPLOYEE_PAYROLL' AND `regular_employment_income_item_id` IS NOT NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NOT NULL AND `payment_schedule_id` IS NULL AND `recognition_date` IS NOT NULL)
            OR (`generation_role`='INSTITUTION_LIABILITY' AND `regular_employment_income_item_id` IS NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NOT NULL AND `payment_schedule_id` IS NULL AND `recognition_date` IS NOT NULL)
        );

    DROP TABLE `institution_regular_income_accounting_schedules`;

    INSERT INTO `system_codes` (`id`,`code_group`,`group_name`,`code`,`code_name`,`sort_no`,`is_active`,`created_at`,`created_by`,`updated_at`,`updated_by`)
    SELECT UUID(),'EVIDENCE_STATUS','증빙상태','CLASSIFICATION_PENDING','업무분류 확인대기',1,1,NOW(),'SYSTEM:MIGRATION',NOW(),'SYSTEM:MIGRATION'
    WHERE NOT EXISTS (SELECT 1 FROM `system_codes` WHERE `code_group`='EVIDENCE_STATUS' AND `code`='CLASSIFICATION_PENDING');
END$$
DELIMITER ;
CALL `migrate_20260826_03_regular_income_closure`();
DROP PROCEDURE `migrate_20260826_03_regular_income_closure`;
