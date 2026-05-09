ALTER TABLE `ledger_vouchers`
    ADD COLUMN IF NOT EXISTS `transaction_id` VARCHAR(36) NULL DEFAULT NULL
        COMMENT 'Linked transaction ID (ledger_transactions.id)'
        AFTER `source_id`;

CREATE INDEX IF NOT EXISTS `idx_ledger_vouchers_transaction_id`
    ON `ledger_vouchers` (`transaction_id`);

UPDATE `ledger_vouchers` v
LEFT JOIN (
    SELECT
        l.`voucher_id`,
        MIN(l.`transaction_id`) AS `transaction_id`
    FROM `ledger_transaction_links` l
    WHERE l.`deleted_at` IS NULL
      AND l.`is_active` = 1
    GROUP BY l.`voucher_id`
) linked
    ON linked.`voucher_id` = v.`id`
SET
    v.`transaction_id` = COALESCE(v.`transaction_id`, linked.`transaction_id`)
WHERE linked.`transaction_id` IS NOT NULL
   OR v.`transaction_id` IS NOT NULL;
