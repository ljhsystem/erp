ALTER TABLE `approval_personal_expense_items`
    ADD COLUMN `client_id` VARCHAR(36) NULL COMMENT '기존 거래처' AFTER `project_id`,
    ADD INDEX `idx_personal_expense_items_client` (`client_id`),
    ADD CONSTRAINT `fk_personal_expense_items_client`
        FOREIGN KEY (`client_id`)
        REFERENCES `system_clients` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
