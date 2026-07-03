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
  - Responsibility: Active DB 판별, `db_replication.php` active_target 전환, 대상 DB 접속정보 정규화, 전환 대상 DB 연결 검증, 마지막 전환 상태 저장, 전환 로그 기록
  - Controllers: `DatabaseActiveController`
  - Out of scope: DB 연결 자체 재기동, 자동 전환, 자동 장애 복구

- `DatabaseActiveSwitchService` update
  - Updated responsibility: Active DB switch guard validation for running sync/restore status, `db_replication.php` active_target switch, target DB connection validation, latest switch status persistence, switch log persistence
  - Updated out of scope: DB connection recovery, automatic failover, automatic promotion

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
  - Responsibility: evidence data type policy, transaction direction policy, processing plan resolution, manual tax invoice type detection, upload business unit policy
  - Controllers: `ImportController`
  - Out of scope: upload save, voucher create, DB schema change
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
  - Responsibility: bank voucher create orchestration, existing voucher check, voucher line/payment build, voucher link/status update
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
  - Responsibility: bank payload normalize, bank evidence sync, bank transaction upsert, bank voucher validation helper
  - Controllers: `ImportController`
  - Out of scope: evidence status helper, payload helper, DB schema change
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
  - Responsibility: bundled voucher create, bundled voucher tagging, bundled evidence voucher orchestration
  - Controllers: `ImportController`
  - Out of scope: upload save, bank helper, DB schema change
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
| EvidenceGenerationService | Owns evidence generation orchestration, processing item handling, and generation-stage save flow coordination. | `ImportController`, `EvidenceGenerationController` | Upload parsing and direct transaction save orchestration |
| EvidencePayloadNormalizeService | Owns payload value normalization, field-level cleanup, and format-column-based payload normalization helpers. | `ImportController`, `EvidenceGenerationSaveService`, `EvidenceGenerationService`, `EvidenceUploadService` | Readiness, reference resolution, transaction helpers, and voucher orchestration |
| EvidenceRuleEngineService | Owns business-required readiness evaluation, system readiness validation, readiness result assembly, and transaction-create error formatting. | `ImportController`, `EvidenceGenerationService`, `EvidenceTransactionCreateService` | Reference resolution, transaction context resolution, and payload normalization |
| EvidenceTypePolicyService | Owns import/source type resolution, type-based SQL target lookup, and legacy evidence type policy helpers. | `ImportController`, `EvidenceGenerationService`, `EvidenceStatusService`, `EvidenceTrashService` | Readiness, payload save, transaction creation, and DB schema change |
| EvidenceGenerationSplitService | Owns generation split child creation, processing item split handling, and related split orchestration. | `ImportController`, `EvidenceGenerationController` | Upload save, bulk save orchestration, transaction creation, and DB schema change |
| EvidenceGenerationSaveService | Owns generation save entrypoints, normalized payload save orchestration, and evidence write coordination. | `ImportController`, `EvidenceGenerationController` | Upload-only helpers, transaction creation, voucher creation, and DB schema change |
| EvidenceTransactionCreateService | Owns evidence-based transaction creation orchestration and transaction-create helper coordination. | `ImportController`, `EvidenceTransactionController` | Upload helpers, generation helpers, readiness policy changes, and route/controller concerns |
| EvidenceDualWriteService | Owns legacy-row synchronization and dual-write mirroring between evidence payload/state stores. | `EvidenceController`, `ImportController`, `EvidenceGenerationSaveService` | Payload/status/link policy changes and controller request handling |
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
| TransactionCrudService | Owns transaction list/detail/save, pretax item save, settlement save, transaction file download payload resolution, trash list, restore, purge, and header total recomputation using the SSOT amounts `transaction_foreign_amount`, `transaction_supply_amount`, `transaction_settlement_amount`, and `transaction_final_amount`. | TransactionController, EvidenceTransactionController, VoucherController | Keeps transaction modal save/read/trash orchestration in one service while leaving voucher approval and posting flows outside the transaction domain; legacy header amounts remain detail/list fallback only and are excluded from new save and recalculation flows. |
