DROP PROCEDURE IF EXISTS `rollback_20260826_03_regular_income_closure`;
DELIMITER $$
CREATE PROCEDURE `rollback_20260826_03_regular_income_closure`()
BEGIN
    IF EXISTS (SELECT 1 FROM `institution_regular_employment_income_accounting_links` WHERE `generation_role` IN ('EMPLOYEE_PAYROLL','INSTITUTION_LIABILITY') AND `payment_schedule_id` IS NULL)
       OR EXISTS (SELECT 1 FROM `ledger_evidence_salary_report` WHERE `evidence_status`='CLASSIFICATION_PENDING') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='신규 Closure 데이터가 있어 기존 지급예정 의존 구조로 복원할 수 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules') <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='지급예정 연결 테이블이 예상과 다르게 존재합니다.';
    END IF;

    ALTER TABLE `institution_regular_employment_income_accounting_links`
        DROP CONSTRAINT `chk_regular_income_accounting_role_fields`,
        DROP CONSTRAINT `chk_regular_income_accounting_role`,
        ADD CONSTRAINT `chk_regular_income_accounting_role`
            CHECK (`generation_role` IN ('EMPLOYEE_PAYROLL','EMPLOYER_CONTRIBUTION','PAYROLL_REPORT_EVIDENCE')),
        ADD CONSTRAINT `chk_regular_income_accounting_role_fields` CHECK (
            (`generation_role`='PAYROLL_REPORT_EVIDENCE' AND `regular_employment_income_item_id` IS NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NULL AND `payment_schedule_id` IS NULL AND `recognition_date` IS NULL)
            OR (`generation_role`='EMPLOYEE_PAYROLL' AND `regular_employment_income_item_id` IS NOT NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NOT NULL AND `payment_schedule_id` IS NOT NULL AND `recognition_date` IS NOT NULL)
            OR (`generation_role`='EMPLOYER_CONTRIBUTION' AND `regular_employment_income_item_id` IS NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NOT NULL AND `payment_schedule_id` IS NULL AND `recognition_date` IS NOT NULL)
        );

    CREATE TABLE `institution_regular_income_accounting_schedules` (
        `id` varchar(36) NOT NULL,
        `accounting_link_id` varchar(36) NOT NULL,
        `payment_schedule_id` varchar(36) NOT NULL,
        `schedule_role` varchar(40) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_by` varchar(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_regular_income_accounting_schedule_pair` (`accounting_link_id`,`payment_schedule_id`),
        UNIQUE KEY `uk_regular_income_accounting_schedule` (`payment_schedule_id`),
        KEY `idx_regular_income_accounting_schedule_role` (`accounting_link_id`,`schedule_role`),
        CONSTRAINT `fk_regular_income_accounting_schedule_link` FOREIGN KEY (`accounting_link_id`) REFERENCES `institution_regular_employment_income_accounting_links` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `fk_regular_income_accounting_schedule_payment` FOREIGN KEY (`payment_schedule_id`) REFERENCES `ledger_payment_schedules` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `chk_regular_income_accounting_schedule_role` CHECK (`schedule_role` IN ('EMPLOYEE_NET','SOCIAL_INSURANCE','WITHHOLDING_TAX'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

    DELETE FROM `system_codes` WHERE `code_group`='EVIDENCE_STATUS' AND `code`='CLASSIFICATION_PENDING';
END$$
DELIMITER ;
CALL `rollback_20260826_03_regular_income_closure`();
DROP PROCEDURE `rollback_20260826_03_regular_income_closure`;
