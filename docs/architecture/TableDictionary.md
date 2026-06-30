# Table Dictionary

## System

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| system_page_registry | PageRegistry | Stores canonical ERP page rows keyed by `page_key`, including module/menu/page labels, breadcrumb, and representative WEB route information promoted from the current `PAGE_MAPPING`. | Prepared for future permission, breadcrumb, sitemap, and menu consumers through `page_key`, `default_route_key`, and `default_route_url`. |
| system_menu_registry | MenuRegistry | Stores ERP menu display-policy rows keyed by `menu_key`, with nullable `page_key` links to `system_page_registry`, per-surface visibility flags, ordering, icon, and representative entry URL. | Seeded from current Sidebar, Settings Menu, SiteMap, and Navbar structures; prepared for future menu consumers through `page_key`, `visible_in_sidebar`, `visible_in_settings`, `visible_in_sitemap`, and `visible_in_navbar`. |

## Auth

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| auth_permissions | Permission | Stores ERP permission master rows including `permission_key`, `permission_name`, `description`, `category`, `page_key`, `page`, `permission_source`, and `is_active`. | Referenced by `auth_role_permissions.permission_id`, synchronized by `PermissionRegistry`, logically linked to `system_page_registry.page_key`. |
| auth_role_permissions | RolePermission | Stores role-to-permission mappings keyed by `role_id + permission_id` and acts as the runtime permission source of truth. | Links `auth_roles.id` to `auth_permissions.id`. |

## Ledger

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| ledger_data_formats | EvidenceFormat | Stores saved evidence-import format headers grouped by `source_type` and `format_name`. | Parent of `ledger_data_format_columns`; referenced by `ledger_data_evidences.format_id` and `ledger_evidence_payloads.format_id`. |
| ledger_data_format_columns | EvidenceFormat | Stores per-format column order, visibility, requirement, normalized field mapping, and source-column SSOT key through `original_column_key`. | Child of `ledger_data_formats.id`; unique by `(format_id, system_field_name)` for normalized mapping and `(format_id, original_column_key)` for source-column settings. |
| ledger_evidence_payloads | EvidencePayload | Stores evidence payload snapshots including `mapped_payload_json`, `raw_json`, `format_id`, `source_key`, and `payload_hash`. | Linked to payload consumers by `(evidence_type, evidence_id)` and to the originating format by `format_id`. |
| ledger_evidence_processing | EvidenceStatus | Stores evidence processing state such as `processing_status`, `review_status`, and `last_error_message`. | Linked 1:1 with evidence payload/body rows by `(evidence_type, evidence_id)`. |
| ledger_evidence_links | EvidenceLink | Stores links between evidence rows and generated transactions, vouchers, or processing items. | Bridges `(evidence_type, evidence_id)` to `(target_type, target_id)`. |
| ledger_processing_items | EvidenceGeneration | Stores split/merge/manual-adjust processing items and processing-tree metadata. | Connected to evidence payloads, processing item actions, and generated transactions/vouchers. |
| ledger_processing_item_actions | EvidenceGeneration | Stores processing-item action history such as split, merge, and adjustment events. | Linked to `ledger_processing_items.id` by `processing_item_id`. |
| ledger_evidence_processing_logs | EvidenceStatus | Stores evidence processing status-change and handling logs. | Linked to `ledger_evidence_processing.id` by `processing_id`. |
| ledger_evidence_bank | EvidenceBody | Stores bank-evidence body rows. | `id` is the evidence body key and connects to payload/status/link rows by `evidence_type`. |
| ledger_evidence_tax_invoice | EvidenceBody | Stores tax-invoice evidence body rows for 홈택스 원본/양식 업로드. | `id` is the evidence body key and connects to payload/status/link rows by `evidence_type`; logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_tax_invoice_manual | EvidenceBody | Stores manual tax-invoice evidence body rows for 수기 세금계산서매입매출 원본/양식 업로드. | `id` is the evidence body key and connects to payload/status/link rows by `evidence_type`; logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_card_statement | EvidenceBody | Stores card-company purchase evidence body rows for 카드매입(카드사) 원본/양식 업로드. | `id` is the evidence body key and connects to payload/status/link rows by `evidence_type`; card-company types such as `CARD_STATEMENT` and `CARD_APPROVAL` resolve here. |
| ledger_bank_transactions | FundsLegacy | Legacy bank transaction storage used by historical funds flows and migration/backfill paths. | Referenced by bank-evidence generation, processing-item backfill, and legacy funds screens. |
| ledger_transactions | Transaction | Stores ERP common transaction header data including classification, base references, and the SSOT header amounts `transaction_foreign_amount`, `transaction_supply_amount`, `transaction_settlement_amount`, and `transaction_final_amount`. | Linked from evidence links using `target_type='TRANSACTION'`; parent of `ledger_transaction_items`; related to vouchers through `ledger_transaction_links`. |
| ledger_transaction_items | Transaction | Stores pretax transaction item rows with SSOT fields such as `sort_no`, `item_date`, `item_name`, `item_specification`, `item_unit_name`, `item_quantity`, `item_unit_price`, `item_foreign_unit_price`, `item_foreign_amount`, `item_supply_amount`, `item_tax_type`, and `item_description`. | Child of `ledger_transactions.id`; can reference `ledger_processing_items.id` through `processing_item_id`. |
| ledger_transaction_settlements | TransactionSettlement | Stores post-transaction settlement adjustments with SSOT fields `sort_no`, `settlement_type`, `amount_sign`, `amount`, and `settlement_description`. | Child of `ledger_transactions.id`; can optionally reference `ledger_transaction_items.id` through `transaction_item_id`. |
| ledger_vouchers | Voucher | Stores generated voucher header data. | Linked from evidence links using `target_type='VOUCHER'`; related to transactions. |

## Notes

- Register new runtime tables here before shipping code that depends on them.
- When a table references another table logically rather than with an FK, document that relation in the Relations column.
- Keep payload, status, link, and body responsibilities separated rather than overloading one table.
