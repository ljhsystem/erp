-- No-op migration after tax-invoice item raw columns were removed from the final schema.
-- Keep only legacy item table cleanup so re-running this file never reintroduces dropped columns.

DROP TABLE IF EXISTS `ledger_evidence_tax_invoice_items`;
