DROP INDEX IF EXISTS `idx_bank_evidence_deleted`
    ON `ledger_bank_transactions`;

DROP INDEX IF EXISTS `idx_evidence_source_key_lookup`
    ON `ledger_data_evidences`;

DROP INDEX IF EXISTS `idx_evidence_type_delete_date`
    ON `ledger_data_evidences`;

DROP INDEX IF EXISTS `idx_evidence_delete_scope`
    ON `ledger_data_evidences`;
