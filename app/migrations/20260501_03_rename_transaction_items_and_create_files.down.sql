DROP TABLE IF EXISTS `ledger_transaction_files`;

ALTER TABLE `ledger_transaction_lines`
    ADD COLUMN IF NOT EXISTS `line_no` INT NOT NULL DEFAULT 1
        COMMENT 'Line number'
        AFTER `transaction_id`;

UPDATE `ledger_transaction_lines`
SET `line_no` = `sort_no`;

DROP INDEX IF EXISTS `idx_ledger_transaction_lines_transaction_sort`
    ON `ledger_transaction_lines`;

ALTER TABLE `ledger_transaction_lines`
    DROP COLUMN IF EXISTS `item_date`,
    MODIFY COLUMN `sort_no` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Sort code',
    ADD UNIQUE KEY `uk_ledger_transaction_items_sort_no` (`sort_no`),
    ADD UNIQUE KEY `uk_ledger_transaction_items_line` (`transaction_id`, `line_no`);

RENAME TABLE `ledger_transaction_lines` TO `ledger_transaction_items`;
