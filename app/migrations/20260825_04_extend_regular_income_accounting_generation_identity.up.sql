DROP PROCEDURE IF EXISTS `migrate_20260825_04_regular_income_generation`;
DELIMITER $$
CREATE PROCEDURE `migrate_20260825_04_regular_income_generation`()
BEGIN
    IF (SELECT COUNT(*) FROM `institution_regular_employment_income_accounting_links`) <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='기존 상용근로소득 회계연결이 존재하여 생성역할을 자동 추정할 수 없습니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE()
            AND ((TABLE_NAME='ledger_transaction_items' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id'))
              OR (TABLE_NAME='ledger_transaction_settlements' AND COLUMN_NAME IN ('regular_employment_income_line_item_id','statutory_standard_revision_id','calculation_basis_id'))
              OR (TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('generation_role','aggregation_key','approval_request_id','attribution_month','recognition_date','payload_hash')))) <> 0
       OR (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_income_accounting_schedules') <> 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용근로소득 회계생성 Migration이 이미 적용됐거나 부분 적용된 상태입니다.';
    END IF;
    IF (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND INDEX_NAME='uk_regular_income_accounting_detail') <> 1
       OR (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='institution_regular_employment_income_accounting_links' AND COLUMN_NAME IN ('regular_employment_income_item_id','evidence_id','transaction_id','payment_schedule_id','request_key')) <> 5 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='상용근로소득 회계연결 기준 스키마가 승인된 Preflight와 다릅니다.';
    END IF;

    ALTER TABLE `ledger_transaction_items`
        ADD COLUMN `regular_employment_income_line_item_id` varchar(36) NULL COMMENT '상용근로소득 원천 지급·회사부담 Line' AFTER `transaction_id`,
        ADD COLUMN `statutory_standard_revision_id` varchar(36) NULL COMMENT '계산에 적용한 법정기준 Revision' AFTER `regular_employment_income_line_item_id`,
        ADD COLUMN `calculation_basis_id` varchar(36) NULL COMMENT '상용근로소득 계산기초' AFTER `statutory_standard_revision_id`,
        ADD KEY `idx_transaction_item_regular_income_line` (`regular_employment_income_line_item_id`),
        ADD KEY `idx_transaction_item_statutory_standard` (`statutory_standard_revision_id`),
        ADD KEY `idx_transaction_item_calculation_basis` (`calculation_basis_id`),
        ADD CONSTRAINT `fk_transaction_item_regular_income_line` FOREIGN KEY (`regular_employment_income_line_item_id`) REFERENCES `institution_regular_employment_income_line_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT `fk_transaction_item_statutory_standard` FOREIGN KEY (`statutory_standard_revision_id`) REFERENCES `system_statutory_standards` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT `fk_transaction_item_calculation_basis` FOREIGN KEY (`calculation_basis_id`) REFERENCES `institution_regular_employment_income_calculation_bases` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

    ALTER TABLE `ledger_transaction_settlements`
        ADD COLUMN `regular_employment_income_line_item_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '상용근로소득 원천 공제·환급 Line' AFTER `transaction_item_id`,
        ADD COLUMN `statutory_standard_revision_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '계산에 적용한 법정기준 Revision' AFTER `regular_employment_income_line_item_id`,
        ADD COLUMN `calculation_basis_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL COMMENT '상용근로소득 계산기초' AFTER `statutory_standard_revision_id`,
        ADD KEY `idx_transaction_settlement_regular_income_line` (`regular_employment_income_line_item_id`),
        ADD KEY `idx_transaction_settlement_statutory_standard` (`statutory_standard_revision_id`),
        ADD KEY `idx_transaction_settlement_calculation_basis` (`calculation_basis_id`),
        ADD CONSTRAINT `fk_transaction_settlement_regular_income_line` FOREIGN KEY (`regular_employment_income_line_item_id`) REFERENCES `institution_regular_employment_income_line_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT `fk_transaction_settlement_statutory_standard` FOREIGN KEY (`statutory_standard_revision_id`) REFERENCES `system_statutory_standards` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT `fk_transaction_settlement_calculation_basis` FOREIGN KEY (`calculation_basis_id`) REFERENCES `institution_regular_employment_income_calculation_bases` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

    ALTER TABLE `institution_regular_employment_income_accounting_links`
        DROP INDEX `uk_regular_income_accounting_detail`,
        MODIFY `regular_employment_income_item_id` varchar(36) NULL,
        MODIFY `transaction_id` varchar(36) NULL,
        MODIFY `payment_schedule_id` varchar(36) NULL,
        ADD COLUMN `generation_role` varchar(40) NOT NULL COMMENT '회계자료 생성역할' AFTER `regular_employment_income_item_id`,
        ADD COLUMN `aggregation_key` varchar(191) NOT NULL COMMENT '역할별 서버 집계 기준키' AFTER `generation_role`,
        ADD COLUMN `approval_request_id` varchar(36) NOT NULL COMMENT '최종승인 요청' AFTER `aggregation_key`,
        ADD COLUMN `attribution_month` char(7) NOT NULL COMMENT '급여 귀속월 YYYY-MM' AFTER `approval_request_id`,
        ADD COLUMN `recognition_date` date NULL COMMENT '회계 인식일' AFTER `attribution_month`,
        ADD COLUMN `payload_hash` char(64) NOT NULL COMMENT '서버 정규 Payload SHA-256' AFTER `request_key`,
        ADD UNIQUE KEY `uk_regular_income_accounting_identity` (`regular_employment_income_id`,`generation_role`,`aggregation_key`),
        ADD KEY `idx_regular_income_accounting_approval` (`approval_request_id`),
        ADD KEY `idx_regular_income_accounting_evidence` (`evidence_id`),
        ADD KEY `idx_regular_income_accounting_attribution` (`attribution_month`,`generation_role`),
        ADD CONSTRAINT `fk_regular_income_accounting_approval_request` FOREIGN KEY (`approval_request_id`) REFERENCES `user_approval_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        ADD CONSTRAINT `chk_regular_income_accounting_role` CHECK (`generation_role` IN ('EMPLOYEE_PAYROLL','EMPLOYER_CONTRIBUTION','PAYROLL_REPORT_EVIDENCE')),
        ADD CONSTRAINT `chk_regular_income_accounting_month` CHECK (`attribution_month` REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
        ADD CONSTRAINT `chk_regular_income_accounting_payload_hash` CHECK (`payload_hash` REGEXP '^[0-9a-f]{64}$'),
        ADD CONSTRAINT `chk_regular_income_accounting_role_fields` CHECK (
            (`generation_role`='PAYROLL_REPORT_EVIDENCE' AND `regular_employment_income_item_id` IS NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NULL AND `payment_schedule_id` IS NULL AND `recognition_date` IS NULL)
            OR (`generation_role`='EMPLOYEE_PAYROLL' AND `regular_employment_income_item_id` IS NOT NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NOT NULL AND `payment_schedule_id` IS NOT NULL AND `recognition_date` IS NOT NULL)
            OR (`generation_role`='EMPLOYER_CONTRIBUTION' AND `regular_employment_income_item_id` IS NULL AND `evidence_id` IS NOT NULL AND `transaction_id` IS NOT NULL AND `payment_schedule_id` IS NULL AND `recognition_date` IS NOT NULL)
        );

    CREATE TABLE `institution_regular_income_accounting_schedules` (
        `id` varchar(36) NOT NULL,
        `accounting_link_id` varchar(36) NOT NULL COMMENT '상용근로소득 회계생성 Registry',
        `payment_schedule_id` varchar(36) NOT NULL COMMENT '역할별 지급·납부예정',
        `schedule_role` varchar(40) NOT NULL COMMENT 'EMPLOYEE_NET·SOCIAL_INSURANCE·WITHHOLDING_TAX',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `created_by` varchar(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_regular_income_accounting_schedule_pair` (`accounting_link_id`,`payment_schedule_id`),
        UNIQUE KEY `uk_regular_income_accounting_schedule` (`payment_schedule_id`),
        KEY `idx_regular_income_accounting_schedule_role` (`accounting_link_id`,`schedule_role`),
        CONSTRAINT `fk_regular_income_accounting_schedule_link` FOREIGN KEY (`accounting_link_id`) REFERENCES `institution_regular_employment_income_accounting_links` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `fk_regular_income_accounting_schedule_payment` FOREIGN KEY (`payment_schedule_id`) REFERENCES `ledger_payment_schedules` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `chk_regular_income_accounting_schedule_role` CHECK (`schedule_role` IN ('EMPLOYEE_NET','SOCIAL_INSURANCE','WITHHOLDING_TAX'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='상용근로소득 생성역할별 지급·납부예정 다건 연결';
END$$
DELIMITER ;
CALL `migrate_20260825_04_regular_income_generation`();
DROP PROCEDURE `migrate_20260825_04_regular_income_generation`;
