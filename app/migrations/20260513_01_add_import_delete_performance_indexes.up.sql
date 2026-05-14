CREATE INDEX IF NOT EXISTS `idx_evidence_delete_scope`
    ON `ledger_data_evidences` (`deleted_at`, `transaction_status`, `id`);

CREATE INDEX IF NOT EXISTS `idx_evidence_type_delete_date`
    ON `ledger_data_evidences` (`source_type`, `deleted_at`, `evidence_date`, `latest_imported_at`);

CREATE INDEX IF NOT EXISTS `idx_evidence_source_key_lookup`
    ON `ledger_data_evidences` (`source_type`, `source_key`, `created_at`);

CREATE INDEX IF NOT EXISTS `idx_bank_evidence_deleted`
    ON `ledger_bank_transactions` (`evidence_id`, `deleted_at`);
