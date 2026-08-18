-- Direct transaction-to-voucher relations are retired.
-- Pre-deployment verification on 2026-07-11 found zero rows in
-- ledger_transaction_links (active 0, deleted/inactive 0), so no audit rows
-- require preservation. A replacement archive table is intentionally not
-- created because it would retain a second relationship SSOT.

DELETE FROM `ledger_voucher_line_refs`
WHERE `ref_target` = 'TRANSACTION';

DROP TABLE IF EXISTS `ledger_transaction_links`;

ALTER TABLE `ledger_vouchers`
    DROP INDEX IF EXISTS `idx_ledger_vouchers_transaction_id`,
    DROP COLUMN IF EXISTS `transaction_id`;

ALTER TABLE `ledger_transactions`
    DROP INDEX IF EXISTS `idx_ledger_transactions_evidence_id`,
    DROP INDEX IF EXISTS `idx_ledger_transactions_match_status`,
    DROP COLUMN IF EXISTS `evidence_id`,
    DROP COLUMN IF EXISTS `match_status`;

SET @drop_legacy_evidence_transaction_id_sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ledger_data_evidences'
    ),
    'ALTER TABLE `ledger_data_evidences` DROP INDEX IF EXISTS `idx_ledger_data_evidences_transaction_id`, DROP COLUMN IF EXISTS `transaction_id`',
    'SELECT 1'
);
PREPARE drop_legacy_evidence_transaction_id_stmt FROM @drop_legacy_evidence_transaction_id_sql;
EXECUTE drop_legacy_evidence_transaction_id_stmt;
DEALLOCATE PREPARE drop_legacy_evidence_transaction_id_stmt;
