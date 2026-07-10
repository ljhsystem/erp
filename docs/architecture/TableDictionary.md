# Table Dictionary

## System

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| system_page_registry | PageRegistry | Stores canonical ERP page rows keyed by `page_key`, including module/menu/page labels, breadcrumb, and representative WEB route information promoted from the current `PAGE_MAPPING`. | Prepared for future permission, breadcrumb, sitemap, and menu consumers through `page_key`, `default_route_key`, and `default_route_url`. |
| system_menu_registry | MenuRegistry | Stores ERP menu display-policy rows keyed by `menu_key`, with nullable `page_key` links to `system_page_registry`, per-surface visibility flags, ordering, icon, and representative entry URL. | Seeded from current Sidebar, Settings Menu, SiteMap, and Navbar structures; prepared for future menu consumers through `page_key`, `visible_in_sidebar`, `visible_in_settings`, `visible_in_sitemap`, and `visible_in_navbar`. |
| system_user_settings | UserSetting | Stores per-user page preference JSON such as DataTable and view-state payloads, keyed by page and setting type for DB-backed UI preference restore. | Scoped to the current authenticated user, logically keyed by `user_id + page_key + setting_type`, and consumed by `UserSettingService` plus shared client-side settings storage bridges. |

## Auth

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| auth_permissions | Permission | Stores ERP permission master rows including `permission_key`, `permission_name`, `description`, `category`, `page_key`, `page`, `permission_source`, and `is_active`. | Referenced by `auth_role_permissions.permission_id`, synchronized by `PermissionRegistry`, logically linked to `system_page_registry.page_key`. |
| auth_role_permissions | RolePermission | Stores role-to-permission mappings keyed by `role_id + permission_id` and acts as the runtime permission source of truth. | Links `auth_roles.id` to `auth_permissions.id`. |

## Ledger

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| ledger_data_formats | EvidenceFormat | Stores saved evidence-import format headers grouped by `source_type` and `format_name`. | Parent of `ledger_data_format_columns`; referenced by evidence upload format flows. |
| ledger_data_format_columns | EvidenceFormat | Stores per-format column order, visibility, requirement, normalized field mapping, and source-column SSOT key through `original_column_key`. | Child of `ledger_data_formats.id`; unique by `(format_id, system_field_name)` for normalized mapping and `(format_id, original_column_key)` for source-column settings. |
| ledger_evidence_links | EvidenceLink | Stores links between evidence rows and generated transactions, vouchers, or processing items. | Bridges `(evidence_type, evidence_id)` to `(target_type, target_id)`. |
| ledger_evidence_metadata | EvidenceMetadata | Stores one header policy per `import_type`, including source table, evidence type, processing role, and shared soft-delete fields `deleted_at`/`deleted_by`. It does not store semantic column mappings, matching policy, or active state. | Parent SSOT for `ledger_evidence_metadata_columns`; soft-delete and restore change only the header, while permanent deletion relies on the DB FK `ON DELETE CASCADE` to remove detail rows. |
| ledger_evidence_metadata_columns | EvidenceMetadata | Stores the semantic accounting meaning to actual source-table column mapping as `(metadata_id, semantic_key, physical_column)`, with optional `adjustment_direction`, the required flag, and audit fields. `adjustment_direction` accepts `ADD` or `DEDUCT` only for `ADJUST_AMOUNT`; every other semantic uses `NULL`. | Child of `ledger_evidence_metadata.id`; unique by `(metadata_id, semantic_key, physical_column)` and validated against the header source table's actual DB columns. Multiple `ADJUST_AMOUNT` rows are allowed when their physical columns differ. |
| ledger_processing_items | EvidenceGeneration | Stores split/merge/manual-adjust processing items and processing-tree metadata. | Connected to evidence payloads, processing item actions, and generated transactions/vouchers. |
| ledger_processing_item_actions | EvidenceGeneration | Stores processing-item action history such as split, merge, and adjustment events. | Linked to `ledger_processing_items.id` by `processing_item_id`. |
| ledger_evidence_bank_transaction | EvidenceBody | Stores `BANK_TRANSACTION` evidence body rows with raw bank transaction fields. | `id` is the evidence body key and connects to `ledger_evidence_links` by `(evidence_type, evidence_id)` using `BANK_TRANSACTION`. |
| ledger_evidence_tax_invoice | EvidenceBody | Stores `TAX_INVOICE` evidence body rows with raw HomeTax tax-invoice fields. | `id` is the evidence body key and is logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_tax_invoice_manual | EvidenceBody | Stores `TAX_INVOICE_MANUAL` evidence body rows with raw manual tax-invoice fields. | `id` is the evidence body key and is logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_cash_receipt | EvidenceBody | Stores `CASH_RECEIPT` evidence body rows with raw cash-receipt fields. | `id` is the evidence body key and is logically linked to client and project references when mapped. |
| ledger_evidence_card_hometax | EvidenceBody | Stores `CARD_HOMETAX` evidence body rows with raw HomeTax card fields. | `id` is the evidence body key and connects to `ledger_evidence_links` by `(evidence_type, evidence_id)` using `CARD_HOMETAX`. |
| ledger_evidence_card_statement | EvidenceBody | Stores `CARD_STATEMENT` and `CARD_APPROVAL` evidence body rows with raw card-company fields. | `id` is the evidence body key and card-company evidence types resolve here. |
| ledger_bank_transactions | FundsLegacy | Legacy bank transaction storage used by historical funds flows and migration/backfill paths. | Referenced by bank-evidence generation, processing-item backfill, and legacy funds screens. |
| ledger_transactions | Transaction | Stores ERP common transaction header data including classification, base references, and the SSOT header amounts `transaction_foreign_amount`, `transaction_supply_amount`, `transaction_settlement_amount`, and `transaction_final_amount`. | Linked from evidence links using `target_type='TRANSACTION'`; parent of `ledger_transaction_items`; related to vouchers through `ledger_transaction_links`. |
| ledger_transaction_items | Transaction | Stores pretax transaction item rows with SSOT fields such as `sort_no`, `item_date`, `item_name`, `item_specification`, `item_unit_name`, `item_quantity`, `item_unit_price`, `item_foreign_unit_price`, `item_foreign_amount`, `item_supply_amount`, `item_tax_type`, and `item_description`. | Child of `ledger_transactions.id`. |
| ledger_transaction_settlements | TransactionSettlement | Stores transaction-level settlement adjustments with SSOT fields `sort_no`, `settlement_type`, `amount_sign`, `amount`, and `settlement_description`. | Child of `ledger_transactions.id`; legacy column `transaction_item_id` remains for compatibility, but current application logic treats settlements as transaction-level rows. |
| ledger_vouchers | Voucher | Stores voucher header read-model data for list/search/sort flows, including `debit_total`, `credit_total`, line count, representative summary refs, and the workflow status (`DRAFT`, `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`, `CLOSED`, `DELETED`) derived from voucher runtime operations. | Linked from evidence links using `target_type='VOUCHER'`; related to transactions; totals and summary columns are recalculated from `ledger_voucher_lines` and `ledger_voucher_line_refs`. The balance difference is calculated at runtime and is not stored. |
| ledger_voucher_lines | Voucher | Stores voucher journal lines with voucher-local `line_no` as the UI, drag-and-drop, save, and query order SSOT; global `sort_no` remains internal-only. It also stores processing item, account, amount, line summary, journal rule, and user-modification fields. | Child of `ledger_vouchers.id`; parent of `ledger_voucher_line_refs`; `line_no` is contiguous from 1 within each voucher. |
| ledger_voucher_line_refs | Voucher | Stores all voucher-line sub-account refs from `line.refs[]` as normalized `(voucher_line_id, ref_target, ref_id)` rows, where `ref_id` uses the shared UUID key shape `CHAR(36)`. | Child of `ledger_voucher_lines.id`; the only SSOT for Voucher sub-account save/detail/reopen flows. |

## Notes

- Register new runtime tables here before shipping code that depends on them.
- When a table references another table logically rather than with an FK, document that relation in the Relations column.
- Keep payload, status, link, and body responsibilities separated rather than overloading one table.
