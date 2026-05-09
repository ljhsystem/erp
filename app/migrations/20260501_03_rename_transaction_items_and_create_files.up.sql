RENAME TABLE `ledger_transaction_items` TO `ledger_transaction_lines`;

DROP INDEX IF EXISTS `uk_ledger_transaction_items_sort_no`
    ON `ledger_transaction_lines`;

DROP INDEX IF EXISTS `uk_ledger_transaction_items_line`
    ON `ledger_transaction_lines`;

ALTER TABLE `ledger_transaction_lines`
    ADD COLUMN IF NOT EXISTS `item_date` DATE NULL
        COMMENT 'Line date'
        AFTER `transaction_id`,
    MODIFY COLUMN `sort_no` INT NOT NULL COMMENT 'Line display order';

UPDATE `ledger_transaction_lines` l
INNER JOIN `ledger_transactions` t
    ON t.id = l.transaction_id
SET l.item_date = COALESCE(l.item_date, t.transaction_date);

ALTER TABLE `ledger_transaction_lines`
    MODIFY COLUMN `item_date` DATE NOT NULL COMMENT 'Line date',
    DROP COLUMN IF EXISTS `line_no`;

CREATE INDEX IF NOT EXISTS `idx_ledger_transaction_lines_transaction_sort`
    ON `ledger_transaction_lines` (`transaction_id`, `sort_no`);

CREATE TABLE IF NOT EXISTS `ledger_transaction_files` (
    `id` CHAR(36) NOT NULL COMMENT 'Transaction file UUID',
    `transaction_id` CHAR(36) NOT NULL COMMENT 'Transaction ID',
    `file_path` VARCHAR(500) NOT NULL COMMENT 'Storage DB path',
    `file_name` VARCHAR(255) DEFAULT NULL COMMENT 'Original file name',
    `file_order` INT NOT NULL DEFAULT 1 COMMENT 'Display order',
    `file_size` BIGINT UNSIGNED DEFAULT NULL COMMENT 'File size',
    `created_at` DATETIME DEFAULT NULL COMMENT 'Created at',
    `created_by` VARCHAR(100) DEFAULT NULL COMMENT 'Created by',
    PRIMARY KEY (`id`),
    KEY `idx_ledger_transaction_files_transaction_id` (`transaction_id`),
    KEY `idx_ledger_transaction_files_order` (`transaction_id`, `file_order`),
    CONSTRAINT `fk_ledger_transaction_files_transaction_id`
        FOREIGN KEY (`transaction_id`) REFERENCES `ledger_transactions` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Transaction evidence files';
