CREATE TABLE IF NOT EXISTS `institution_employment_contracts_audits` (
    `id` VARCHAR(36) NOT NULL COMMENT '근로계약 감사 ID(UUID)',
    `contract_id` VARCHAR(36) NOT NULL COMMENT '감사 대상 근로계약 물리 ID',
    `action_type` VARCHAR(30) NOT NULL COMMENT '업무 작업 유형',
    `before_data` LONGTEXT COLLATE utf8mb4_bin NULL COMMENT '변경 전 계약조건 JSON',
    `after_data` LONGTEXT COLLATE utf8mb4_bin NULL COMMENT '변경 후 계약조건 JSON',
    `reason` VARCHAR(1000) NOT NULL COMMENT '처리 사유',
    `processed_by` VARCHAR(100) NOT NULL COMMENT '처리 Actor',
    `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '업무 처리 및 감사 기록 시각',
    `approval_request_id` VARCHAR(36) NULL COMMENT '관련 결재요청 ID',
    `request_key` VARCHAR(100) NOT NULL COMMENT '업무 액션 멱등 식별값',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employment_contract_audit_action_request` (`contract_id`, `action_type`, `request_key`),
    KEY `idx_employment_contract_audit_contract_time` (`contract_id`, `processed_at`),
    KEY `idx_employment_contract_audit_approval` (`approval_request_id`),
    KEY `idx_employment_contract_audit_action_time` (`action_type`, `processed_at`),
    CONSTRAINT `fk_employment_contract_audit_approval` FOREIGN KEY (`approval_request_id`)
        REFERENCES `user_approval_requests` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT `chk_employment_contract_audit_reason` CHECK (CHAR_LENGTH(TRIM(`reason`)) > 0),
    CONSTRAINT `chk_employment_contract_audit_before_json` CHECK (`before_data` IS NULL OR JSON_VALID(`before_data`)),
    CONSTRAINT `chk_employment_contract_audit_after_json` CHECK (`after_data` IS NULL OR JSON_VALID(`after_data`)),
    CONSTRAINT `chk_employment_contract_audit_snapshot` CHECK (`before_data` IS NOT NULL OR `after_data` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='근로계약 불변 감사 원장';

ALTER TABLE `institution_regular_employment_income_items`
    DROP FOREIGN KEY `fk_institution_regular_employment_income_item_contract`;
ALTER TABLE `institution_regular_employment_income_items`
    ADD CONSTRAINT `fk_institution_regular_employment_income_item_contract`
        FOREIGN KEY (`employment_contract_id`) REFERENCES `institution_employment_contracts` (`id`)
        ON UPDATE RESTRICT ON DELETE RESTRICT;

DELETE FROM `system_codes`
WHERE `code_group` = 'EMPLOYMENT_CONTRACT_STATUS'
  AND `code` IN ('EFFECTIVE', 'EXPIRED');
