ALTER TABLE `ledger_data_evidences`
    ADD COLUMN IF NOT EXISTS `employee_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'Employee ID'
        AFTER `project_id`,
    ADD COLUMN IF NOT EXISTS `bank_account_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'Bank account ID'
        AFTER `employee_id`,
    ADD COLUMN IF NOT EXISTS `card_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'Card ID'
        AFTER `bank_account_id`,
    ADD COLUMN IF NOT EXISTS `client_name` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Client display name'
        AFTER `card_id`,
    ADD COLUMN IF NOT EXISTS `project_name` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Project display name'
        AFTER `client_name`,
    ADD COLUMN IF NOT EXISTS `employee_name` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Employee display name'
        AFTER `project_name`,
    ADD COLUMN IF NOT EXISTS `bank_account_name` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Bank account display name'
        AFTER `employee_name`,
    ADD COLUMN IF NOT EXISTS `card_name` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Card display name'
        AFTER `bank_account_name`;

CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_client_id`
    ON `ledger_data_evidences` (`client_id`);
CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_project_id`
    ON `ledger_data_evidences` (`project_id`);
CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_employee_id`
    ON `ledger_data_evidences` (`employee_id`);
CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_bank_account_id`
    ON `ledger_data_evidences` (`bank_account_id`);
CREATE INDEX IF NOT EXISTS `idx_ledger_data_evidences_card_id`
    ON `ledger_data_evidences` (`card_id`);
