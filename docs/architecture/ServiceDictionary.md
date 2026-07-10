# Service Dictionary

## 2026-06 Auth

- `ApprovalService`
  - Responsibility: approval token verification, approval request view-data assembly, user approval execution, approval audit logging
  - Controllers: `UserApprovalController`
  - Out of scope: HTML rendering, direct view DB access, approval result page rendering

- `AuthService`
  - Expanded responsibility: login, 2FA verification, password change, password recovery result assembly
  - Controllers: `LoginController`, `PasswordController`, `TwoFactorController`
  - Out of scope: direct view DB access, HTML rendering

- `PermissionService`
  - Responsibility: permission catalog query, user permission evaluation through `auth_role_permissions`, permission create/update/delete/reorder orchestration
  - Controllers: `PermissionController`, `PasswordController`, `TwoFactorController`, route/middleware permission checks
  - Out of scope: direct HTML rendering, role-permission bulk assignment UI state

- `RolePermissionService`
  - Responsibility: role permission tree/list/assign/remove/reorder orchestration using `auth_role_permissions`, `auth_permissions`, and `system_page_registry`
  - Controllers: `RolePermissionController`
  - Out of scope: DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `permission-assignment`; runtime compatibility aliases remain `role_permissions`, `role-permission`, and `RolePermission*`

- `TemplateService`
  - Responsibility: approval-template list/save/delete/reorder orchestration for `user_approval_templates`
  - Controllers: `ApprovalTemplateController`
  - Out of scope: step persistence, DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `approval-template`; runtime compatibility aliases remain `approval`, `approval/template`, and `TemplateService`

- `TemplateStepService`
  - Responsibility: approval-template step list/save/delete orchestration for `user_approval_template_steps`
  - Controllers: `ApprovalTemplateController`
  - Out of scope: template row persistence, DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `approval-template`; runtime compatibility aliases remain `approval/step` and `TemplateStepService`

## 2026-06 Additions

- `DatabaseSyncService`
  - Responsibility: latest SQL backup selection, current Active/Standby DB pair resolution, Standby DB snapshot creation, Standby DB PDO sync execution, sync heartbeat/status/log persistence, failed-sync automatic rollback
  - Controllers: `DatabaseSyncController`
  - Out of scope: Primary backup generation, Primary restore, mysql CLI execution

- `DatabaseRestoreService`
  - Responsibility: selected SQL backup validation, current Active/Standby DB pair resolution, Active DB PDO restore execution, restore heartbeat/status/log persistence, sync-running guard
  - Controllers: `DatabaseRestoreController`
  - Out of scope: backup generation, Standby sync execution, mysql CLI execution

- `DatabaseActiveSwitchService`
  - Responsibility: Active DB switch guard validation, `db_replication.php` active_target update, target DB connection validation, latest switch status persistence, and switch log persistence
  - Controllers: `DatabaseActiveController`
  - Out of scope: DB connection recovery, automatic failover, automatic promotion

- `DatabaseActiveSwitchService` update
  - Updated responsibility: Active DB switch guard validation for running sync/restore status, `db_replication.php` active_target switch, target DB connection validation, latest switch status persistence, switch log persistence
  - Updated out of scope: DB connection recovery, automatic failover, automatic promotion

- `DataTableColumnMetaService`
  - Responsibility: shared DataTable physical-column meta lookup, composite table meta merge, canonical domain-alias to physical-table resolution, and DB table comment exposure for table-settings subtitle rendering
  - Controllers: `SystemController`
  - Out of scope: page-specific column rendering, DataTable state persistence, direct modal UI rendering

- `ChartAccountExcelService`
  - Responsibility: ledger chart-account excel template generation, full-list export, upload parsing, row validation, save-result summary assembly
  - Controllers: `ChartAccountController`
  - Out of scope: chart-account page rendering, core account persistence rules, sub-account persistence

- `JournalRuleExcelService`
  - Responsibility: ledger journal-rule excel template generation, export, upload parsing, code/account resolution, row validation, save-result summary assembly
  - Controllers: `JournalRuleController`
  - Out of scope: journal-rule page rendering, core journal-rule persistence rules

- `ClientService`
  - Expanded responsibility: client save request payload normalization, save validation, file-attached save orchestration, duplicate business-number save message mapping
  - Controllers: `ClientController`
  - Out of scope: direct HTML rendering, controller response output

- `ClientPayloadService`
  - Responsibility: client request payload normalization, save validation, duplicate business-number save message mapping, nullable field normalization
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: DB persistence, file upload/delete, controller response output

- `ClientFileService`
  - Responsibility: client file-attached save orchestration helper, upload error mapping, attachment upload/delete/retain/rollback helper
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: DB persistence commit/rollback ownership, controller response output

- `ClientTrashService`
  - Responsibility: client delete, trash list, restore, purge, reorder orchestration including attachment cleanup
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: HTML rendering, controller response output

- `ClientExcelService`
  - Expanded responsibility: client template column selection, template/download spreadsheet generation, upload header mapping using label/key/alias rules, required-column validation, migration excel helper methods
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: controller response output, DB schema change

- `ProjectExcelService`
  - Responsibility: project template column selection, template/download spreadsheet generation, upload header mapping using label/key/alias rules, required-column validation, migration excel helper methods
  - Controllers: `ProjectController` via `ProjectService`
  - Out of scope: controller response output, DB schema change

- `ProjectService`
  - Expanded responsibility: project save request payload normalization, save validation, file-attached save orchestration
  - Controllers: `ProjectController`
  - Out of scope: direct HTML rendering, controller response output

- `BankAccountService`
  - Expanded responsibility: bank-account save request payload normalization, save validation, file-attached save orchestration
  - Controllers: `BankAccountController`
  - Out of scope: direct HTML rendering, controller response output

- `BrandService`
  - Responsibility: brand asset list/detail/save/status/purge orchestration for the settings brand page
  - Controllers: `BrandController`
  - Out of scope: direct HTML rendering, controller response output

- `CoverService`
  - Responsibility: cover list/public-list/save/delete/trash/reorder orchestration for `cover` domain, with legacy `CoverImageService` isolated as compatibility wrapper
  - Controllers: `CoverController`, `AboutController`
  - Out of scope: direct HTML rendering, controller response output

- `WorkTeamService`
  - Responsibility: work-team list/detail/save/delete/trash/reorder/excel orchestration
  - Controllers: `WorkTeamController`
  - Out of scope: direct HTML rendering, controller response output

- `EmployeeService`
  - Responsibility: employee list/detail/search/save/status/delete/reorder orchestration
  - Controllers: `EmployeeController`
  - Out of scope: direct HTML rendering, controller response output
  - Domain note: canonical organization domain is `employee`; runtime compatibility alias remains `employees`

- `DepartmentService`
  - Responsibility: department list/detail/save/delete/reorder orchestration
  - Controllers: `DepartmentController`
  - Out of scope: direct HTML rendering, controller response output
  - Domain note: canonical organization domain is `department`; runtime compatibility aliases remain `departments` and `dept`

- `PositionService`
  - Responsibility: position list/detail/save/delete/reorder orchestration
  - Controllers: `PositionController`
  - Out of scope: direct HTML rendering, controller response output
  - Domain note: canonical organization domain is `position`; runtime compatibility aliases remain `positions` and `positions_modal`

- `RoleService`
  - Responsibility: role list/detail/save/delete/reorder orchestration
  - Controllers: `RoleController`
  - Out of scope: direct HTML rendering, controller response output
  - Domain note: canonical organization domain is `role`; runtime compatibility alias remains `roles`

- `EvidenceReferenceResolverService`
  - Responsibility: base reference ID/name resolution, bank account lookup, voucher ref lookup
  - Controllers: `ImportController`
  - Out of scope: payload wrapper, readiness, voucher policy, transaction orchestration
- `EvidenceTransactionContextService`
  - Responsibility: upload transaction context resolution, transaction type mapping
  - Controllers: `ImportController`
  - Out of scope: readiness, rule engine, payload merge, reference resolution
- `EvidenceRuleEngineService`
  - Responsibility: business-required readiness evaluation, system readiness evaluation, readiness result assembly, transaction create error formatting
  - Controllers: `ImportController`
  - Out of scope: reference resolution, transaction context resolution, payload normalization
- `EvidenceTypePolicyService`
  - Responsibility: evidence import/source type normalization, legacy type alias policy, upload/business data type allow policy, transaction direction policy, manual tax invoice type detection, upload business unit policy
  - Controllers: `EvidenceController`, `EvidenceImportController`, `EvidenceLifecycleController`, `EvidenceListController`, `EvidenceStatusController`, `EvidenceTransactionController`, `EvidenceUploadController`
  - Out of scope: page ready/planned rollout policy, field/meta domain policy, excel manager domain policy, field alias/display policy, modal preset policy, processing plan policy, upload save, voucher create, DB schema change
- `SystemFieldService`
  - Responsibility: body-table physical column discovery, data-type to target-table resolution, source field option generation, field ordering/default visibility/default required policy generation, field-group metadata assembly for table setting, excel upload/download, and modal rendering preparation
  - Controllers: `EvidenceImportController`, `EvidenceUploadController`
  - Out of scope: import/source type normalization, runtime page-ready policy, processing plan, badge/color/icon UI policy, DB schema change
- `EvidenceProcessingPolicyService`
  - Responsibility: evidence read-time processing-table existence policy, `ledger_evidence_processing` join SQL, runtime processing status/review status select fragments, and status-filter availability policy for body-table reads
  - Controllers: none directly; used by `EvidenceGenerationService`
  - Out of scope: import/source type normalization, field/meta generation, page rollout policy, upload/save/delete orchestration, and DB schema change
- `EvidenceUploadService`
  - Expanded responsibility: upload runtime/cancel/trace, preview session store/load/clear, file column validation/header-only read, fingerprint source key/build, duplicate lookup/annotation, required-missing summary/confirmation, upload source key policy, upload row status update helper, preview confirm orchestration, upload file path orchestration, trace payload build, validation response build, preview confirm response build, chunk upload progress/result build
  - Controllers: `ImportController`
  - Out of scope: transaction create, voucher create, DB schema change
- `EvidenceUploadParserService`
  - Responsibility: spreadsheet load, upload row parse, bank workbook parse, upload header/column mapping, upload payload key resolve
  - Controllers: `ImportController`
  - Out of scope: upload batch save, preview validation, transaction create, voucher create
- `EvidenceBatchSaveService`
  - Responsibility: upload batch save helper, validation/status preparation, duplicate lookup wrapper, payload build, persist parameter assembly, chunk commit, evidence payload sort allocation
  - Controllers: `ImportController`
  - Out of scope: batch upsert orchestration entrypoint, transaction boundary, bank side effect, transaction create, voucher create, DB schema change
- `VoucherPolicyService`
  - Responsibility: voucher ref policy lookup, voucher ref type/account normalization, evidence ref apply, required ref validation, ledger account resolve
  - Controllers: `ImportController`
  - Out of scope: voucher create orchestration, bundled voucher create, learning, link/update side effects
- `VoucherCreateService`
  - Responsibility: bank voucher create orchestration, existing voucher check, voucher line build, voucher link/status update
  - Controllers: `ImportController`
  - Out of scope: bundled voucher create, voucher policy lookup, learning helper, DB schema change
- `TransactionPayloadBuildService`
  - Responsibility: transaction payload build, transaction line payload build, header-only retry evaluation
  - Controllers: `ImportController`
  - Out of scope: transaction context resolution, transaction direction policy, business unit policy, DB schema change
- `EvidenceTemplateService`
  - Expanded responsibility: template download orchestration, template sheet fill, sample generation, and selected-column header rendering for evidence source templates.
  - Controllers: `EvidenceImportController`
  - Out of scope: dropdown option/query lookup, evidence type field SSOT decisions, upload parse/save, DB schema change
- `EvidenceDownloadService`
  - Expanded responsibility: evidence source type spreadsheet download generation, selected-column filtering, and DB column header rendering based on `information_schema.COLUMNS`
  - Controllers: `EvidenceDownloadController`
  - Out of scope: page rendering, DB schema change, controller response output
- `EvidenceTemplateDropdownService`
  - Responsibility: template dropdown build/apply, dropdown option lookup, bank template dropdown composition
  - Controllers: `ImportController`
  - Out of scope: template file download/write, format mapping/query, upload save, DB schema change
- `EvidenceFormatMappingService`
  - Responsibility: format lookup, format column lookup, legacy/canonical system field mapping, template field grouping
  - Controllers: `ImportController`
  - Out of scope: template file generation, dropdown apply, upload parse/save, DB schema change
- `EvidenceBusinessRefService`
  - Responsibility: business reference resolve helper, payload business reference normalize, business reference candidate extraction
  - Controllers: `ImportController`
  - Out of scope: client sync, bank helper, DB schema change
- `EvidenceClientSyncService`
  - Responsibility: import client sync, tax invoice client sync, client upsert/update helper, import party normalization
  - Controllers: `ImportController`
  - Out of scope: business reference helper, bank helper, DB schema change
- `EvidenceBankHelperService`
  - Responsibility: bank payload normalize, bank evidence sync, bank transaction upsert, bank voucher line validation helper
  - Controllers: `ImportController`
  - Out of scope: evidence status helper, separate payment-domain orchestration, DB schema change
- `EvidenceStatusHelperService`
  - Responsibility: evidence status helper, business-status apply helper, readiness apply helper, active output detection, transaction/voucher existence helper, evidence status SQL helper
  - Controllers: `ImportController`
  - Out of scope: payload helper, link helper, sort helper, DB schema change
- `EvidencePayloadHelperService`
  - Responsibility: evidence payload scalar normalization, storage JSON encode helper, seed row id extraction, evidence total amount calculation, blank value detection
  - Controllers: `ImportController`
  - Out of scope: link helper, sort helper, DB schema change
- `EvidenceDeleteRestoreService`
  - Responsibility: evidence soft delete helper, evidence restore helper, evidence body delete/restore helper
  - Controllers: `ImportController`
  - Out of scope: evidence lifecycle purge orchestration, bundled voucher, upload save, DB schema change
- `EvidenceLifecycleService`
  - Responsibility: evidence purge lifecycle, evidence processing delete lifecycle, bank transaction sync lifecycle, evidence hard delete lifecycle
  - Controllers: `ImportController`
  - Out of scope: bundled voucher, upload save, template/format, DB schema change
- `EvidenceUploadValidationService`
  - Responsibility: upload row enrichment, upload amount normalization, preview validation, business/project validation, upload validation error assertion
  - Controllers: `ImportController`
  - Out of scope: upload batch save, parser, transaction create, voucher create, DB schema change
- `EvidenceSummarySearchService`
  - Responsibility: evidence summary text search, evidence summary ranking, `mapped_payload_json` decode/search helper
  - Controllers: `EvidenceController`
  - Out of scope: request/response output, page rendering, upload save, DB schema change
- `EvidenceUploadPersistService`
  - Responsibility: upload batch persistence entrypoint, payload/processing upsert orchestration, upload transaction boundary, dual-write sync, batch result assembly
  - Controllers: `EvidenceUploadController`
  - Out of scope: upload file parse, preview validation, request payload collection, DB schema change
- `EvidenceLinkHelperService`
  - Responsibility: evidence link purge helper, evidence source reference detach, evidence link soft-delete/delete helper, processing item detach on evidence purge
  - Controllers: `ImportController`
  - Out of scope: upload helper, transaction helper, voucher helper, DB schema change
- `EvidenceSortHelperService`
  - Responsibility: evidence payload sort value helper, evidence sort column ensure helper
  - Controllers: `ImportController`
  - Out of scope: payload helper, link helper, DB schema change
- `BundledVoucherService`
  - Responsibility: bundled voucher create, bundled voucher tagging, bundled evidence voucher orchestration with voucher lines only
  - Controllers: `ImportController`
  - Out of scope: upload save, separate payment-domain save, DB schema change
- `VoucherLearningService`
  - Responsibility: voucher learning save, voucher learning line build, reference payload normalize, amount bucket classification
  - Controllers: `ImportController`
  - Out of scope: voucher create orchestration, bundled voucher, DB schema change
## Legacy Ledger Evidence Service Notes

This section preserves the older evidence-service split reference in readable form until the active service inventory above fully replaces it.

| Service | Responsibility | Used By | Out of Scope |
| --- | --- | --- | --- |
| EvidencePayloadService | Owns evidence payload read/save, payload normalization entrypoints, and payload-oriented lookup helpers. | `ImportController`, `EvidencePayloadController` | Readiness, transaction creation, voucher creation, and DB schema change |
| EvidenceStatusService | Owns evidence status updates, readiness-related state changes, and status lookup helpers. | `ImportController`, `EvidenceStatusController` | Payload save orchestration, transaction creation, voucher creation, and DB schema change |
| EvidenceTrashService | Owns evidence trash, restore, and purge orchestration for evidence lifecycle cleanup. | `ImportController`, `EvidenceGenerationController` | Upload parse/save, payload normalization, transaction creation, and DB schema change |
| EvidenceUploadService | Owns upload flow orchestration, parsed row handling, and upload batch save coordination. | `ImportController`, `EvidenceUploadController` | Generation split/merge, transaction creation, voucher creation, and DB schema change |
| EvidenceGenerationService | Owns runtime read orchestration, body-row assembly, and processing-item expansion for evidence list/detail generation. | `EvidenceListController`, `EvidenceLifecycleController`, `EvidenceTransactionCreateService` | Type policy, field meta, page policy, processing SQL policy, upload/save/delete orchestration, and DB schema change |
| EvidenceBodyReadService | Owns body-table read orchestration for runtime evidence list/detail generation and delegates concrete SQL access to domain-specific read models so the service keeps flow-only responsibility. | `EvidenceGenerationService` | Direct SQL ownership, type normalization policy, page policy, field meta policy, upload/save/delete flows, UI policy, and DB schema change |
| EvidencePayloadNormalizeService | Owns payload value normalization, field-level cleanup, and format-column-based payload normalization helpers. | `ImportController`, `EvidenceGenerationSaveService`, `EvidenceGenerationService`, `EvidenceUploadService` | Readiness, reference resolution, transaction helpers, and voucher orchestration |
| EvidenceRuleEngineService | Owns business-required readiness evaluation, system readiness validation, readiness result assembly, and transaction-create error formatting. | `ImportController`, `EvidenceGenerationService`, `EvidenceTransactionCreateService` | Reference resolution, transaction context resolution, and payload normalization |
| EvidenceTypePolicyService | Owns import/source type normalization, legacy alias resolution, upload/business allow lists, source/import query helper policy, transaction direction policy, and manual tax invoice type detection. | `EvidenceController`, `EvidenceImportController`, `EvidenceLifecycleController`, `EvidenceListController`, `EvidenceStatusController`, `EvidenceTransactionController`, `EvidenceUploadController`, `EvidenceGenerationService`, `EvidenceStatusService`, `EvidenceTrashService` | Page-ready/planned policy, field/meta domain policy, excel-manager domain policy, field alias/display policy, modal preset policy, processing plan, payload save, transaction execution, voucher creation, and DB schema change |
| SystemFieldService | Owns body-table physical-column metadata lookup, data-type target-table resolution, source-column option generation, field-group ordering/default visibility/default required policy assembly, and the field-meta baseline that table settings, excel upload/download, and detail modal can converge on. Runtime default field-meta generation must start from `targetTableForDataType() -> information_schema.COLUMNS`; transitional `mapped_payload_json` helpers remain classified as legacy and are not the default path. | `EvidenceImportController`, `EvidenceUploadController`, `EvidenceDownloadService`, `EvidenceListController`, `EvidenceLifecycleController`, `EvidenceSaveController`, `EvidenceSplitController`, `EvidenceTransactionController` | Import/source type normalization, page-ready/planned policy, badge/color/icon UI policy, processing plan, and DB schema change |
| EvidenceProcessingPolicyService | Owns read-time `ledger_evidence_processing` existence policy, body-table join SQL, processing status/review status select fragments, and status-filter availability rules. | `EvidenceGenerationService` | Import/source type normalization, field/meta policy, upload/save/delete flows, UI policy, and DB schema change |
| EvidenceGenerationSplitService | Owns generation split child creation, processing item split handling, and related split orchestration. | `ImportController`, `EvidenceGenerationController` | Upload save, bulk save orchestration, transaction creation, and DB schema change |
| EvidenceGenerationSaveService | Owns generation save entrypoints, normalized payload save orchestration, and evidence write coordination. | `ImportController`, `EvidenceGenerationController` | Upload-only helpers, transaction creation, voucher creation, and DB schema change |
| EvidenceTransactionCreateService | Owns evidence-based transaction creation orchestration and transaction-create helper coordination. | `ImportController`, `EvidenceTransactionController` | Upload helpers, generation helpers, readiness policy changes, and route/controller concerns |
| EvidenceDualWriteService | Owns legacy-row synchronization and dual-write mirroring between evidence payload/state stores, with body-table writes filtered by actual DB columns from `information_schema.COLUMNS`. | `EvidenceController`, `ImportController`, `EvidenceGenerationSaveService` | Payload/status/link policy changes and controller request handling |
| ProcessingItemSplitService | Owns processing item split execution and aggregate recalculation coordination. | `EvidenceGenerationSplitService`, `ImportController` | Payload save, transaction creation, voucher creation, and DB schema change |
| ProcessingItemAggregateService | Owns split/merge aggregate recalculation and processing item summary recomputation. | `ProcessingItemSplitService`, `TransactionCrudService` | Controller request handling and upload parsing |
| ProcessingItemTreeService | Owns processing item tree composition, parent-child traversal, and hierarchy lookup helpers. | `EvidenceGenerationService`, `ImportController` | Direct DB migration work and payload save orchestration |
| ProcessingItemActionService | Owns processing item action execution and action-state update helpers. | `ProcessingItemActionModel` | Payload save and evidence lifecycle policy changes |

## Legacy Service Principles

- Controllers collect requests, delegate orchestration to services, and return responses.
- Shared helpers should stay focused on reusable domain behavior rather than controller callbacks.
- Each service should own one business flow clearly enough that state transitions and side effects remain traceable.
- If a service split changes architecture decisions, record the reason in `DecisionLog.md`.
## 2026-06-27 Addendum

| Service | Responsibility | Used By | Notes |
| --- | --- | --- | --- |
| TransactionVoucherService | Owns voucher recommendation, draft voucher creation, voucher link and unlink, and transaction-voucher relation hydration for the transaction domain. | TransactionController | Keeps voucher orchestration out of the transaction controller so transaction entry can stay focused on common transaction management. |
| VoucherService | Owns voucher save, `DRAFT -> REVIEW_REQUESTED -> REVIEWED -> POSTED -> CLOSED` workflow validation, voucher line normalization with contiguous voucher-local `line_no`, voucher-line ref persistence orchestration, header-summary recomputation for the voucher read model, reversal voucher creation with original-row locking and duplicate prevention, and restore/delete summary sync using `ledger_vouchers`, `ledger_voucher_lines`, and `ledger_voucher_line_refs`. | VoucherController, VoucherCreateService callbacks | Keeps voucher write orchestration and line validation out of the controller while making `line_no` the only user-visible voucher-line order SSOT and `line.refs[]` the runtime source for voucher multi-ref save and approval flows. Reversal creation clones the complete reversed lines and refs in one transaction, recalculates header summaries, and serializes duplicate checks by locking the original voucher row. |
| VoucherStatus | Defines the canonical voucher workflow values, transitions, normalization, editable/locked groups, picker values, and the review-list status set `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`, `CLOSED`. | VoucherController, VoucherService, VoucherModel | Keeps status filtering and workflow decisions on the shared status SSOT; review-list queries must not duplicate status literals in controllers, models, or page JS. |
| VoucherLineRefService | Owns voucher-line ref replace, detail hydration, and validation-shape reconstruction for voucher `line.refs[]` using `ledger_voucher_line_refs`. | VoucherController, VoucherService | Keeps voucher controller/service code focused on voucher orchestration while centralizing voucher multi-ref read/write mapping in one voucher-only service. |
| VoucherNumberService | Owns editable voucher number validation, duplicate-number guard, and direct voucher number change persistence for `ledger_vouchers`. | VoucherController | Keeps manual voucher number change logic out of the controller while excluding deleted voucher-number-history persistence from runtime flows. |
| TransactionCrudService | Owns transaction list/detail/save, evidence-link validation against the active evidence metadata policy (`DATA`/`BOTH` only), synchronization of the selected evidence to both the transaction header and `ledger_evidence_links`, pretax item save, settlement save, transaction file download payload resolution, trash list, restore, purge, and header total recomputation using the SSOT amounts `transaction_foreign_amount`, `transaction_supply_amount`, `transaction_settlement_amount`, and `transaction_final_amount`. | TransactionController, EvidenceTransactionController, VoucherController | Keeps transaction modal save/read/trash orchestration in one service while leaving voucher approval and posting flows outside the transaction domain; evidence eligibility is never inferred from `import_type`, and legacy header amounts remain excluded from new save and recalculation flows. |
| TransactionExcelService | Owns transaction-header excel template generation, full-list export with display-name values, header upload row parsing, code/master name resolution, header-only transaction save orchestration through `TransactionCrudService`, plus modal transaction-item and transaction-settlement Excel template/export/upload row parsing for AG Grid current-row workflows. | TransactionController | Keeps transaction Excel Manager template/download/upload behavior aligned with the shared DB-backed Excel settings flow without moving spreadsheet parsing into the controller; modal AG Grid Excel flows reuse the same service while staying independent from header Excel settings. |
| VoucherExcelService | Owns voucher-header Excel template generation, voucher-list export, DB physical-column ordering reuse through `DataTableColumnMetaService`, and draft-voucher header upload update flow for the shared Excel Manager. | VoucherController | Brings voucher input onto the same shared Excel Manager architecture as transaction input without pushing spreadsheet parsing and upload-row validation into `VoucherController`. |
| EvidenceMetadataService | Owns evidence-policy header validation and CRUD, shared soft-delete trash/restore/purge flows, row ordering, transactional semantic-column persistence, schema-based recommendations, source-column validation, duplicate prevention, and Actor enrichment. | EvidenceMetadataController | Soft-delete and restore update only `ledger_evidence_metadata`; permanent deletion relies on the verified Header→Detail FK CASCADE. The shared trash modal and DataTable toolbar remain the UI entry points. |
| UserSettingService | Owns per-user page setting detail/save/delete orchestration for `system_user_settings`, including page-key and setting-type validation, current-user scoping, `exists` response metadata, and DB-backed persistence for `TABLE`, `VIEW`, `EXCEL_UPLOAD`, and `EXCEL_DOWNLOAD`. The service stays storage-type agnostic while the shared client bridge decides when a single UI state should be split across separate `TABLE` and `VIEW` rows. | UserSettingController | Keeps browser-storage replacement logic out of page JS while leaving screen-specific setting-key selection to common client modules. |
| SubChartAccountExcelService | Owns ledger sub-account excel template generation, full-list export, upload parsing, account-code resolution, and create/update save orchestration through `CustomSubAccountService` for the account-subject sub-table domain. | SubChartAccountController | Keeps sub-account excel I/O out of the controller while leaving modal table editing and base sub-account CRUD flows in page JS and `CustomSubAccountService`. |
