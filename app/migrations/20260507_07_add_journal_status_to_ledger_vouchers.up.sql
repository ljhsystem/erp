ALTER TABLE `ledger_vouchers`
    ADD COLUMN IF NOT EXISTS `journal_status` VARCHAR(20) NOT NULL DEFAULT 'EMPTY'
        COMMENT 'Journal status: EMPTY, UNBALANCED, READY, POSTED'
        AFTER `status`;

CREATE INDEX IF NOT EXISTS `idx_ledger_vouchers_journal_status`
    ON `ledger_vouchers` (`journal_status`);

UPDATE `ledger_vouchers` v
LEFT JOIN (
    SELECT
        l.`voucher_id`,
        COUNT(*) AS `line_count`,
        COALESCE(SUM(l.`debit`), 0) AS `debit_total`,
        COALESCE(SUM(l.`credit`), 0) AS `credit_total`,
        SUM(CASE WHEN a.`id` IS NULL THEN 1 ELSE 0 END) AS `missing_account_count`
    FROM `ledger_voucher_lines` l
    LEFT JOIN `ledger_accounts` a
        ON a.`id` = l.`account_id`
    WHERE l.`deleted_at` IS NULL
    GROUP BY l.`voucher_id`
) vl
    ON vl.`voucher_id` = v.`id`
SET v.`journal_status` = CASE
    WHEN v.`status` IN ('posted', 'closed') THEN 'POSTED'
    WHEN COALESCE(vl.`line_count`, 0) = 0 THEN 'EMPTY'
    WHEN ROUND(COALESCE(vl.`debit_total`, 0), 2) <> ROUND(COALESCE(vl.`credit_total`, 0), 2)
         OR COALESCE(vl.`debit_total`, 0) <= 0
         OR COALESCE(vl.`credit_total`, 0) <= 0
         OR COALESCE(vl.`missing_account_count`, 0) > 0 THEN 'UNBALANCED'
    ELSE 'READY'
END;
