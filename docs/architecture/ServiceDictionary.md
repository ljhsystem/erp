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
  - Responsibility: readiness evaluation, readiness result assembly, transaction create error formatting
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
  - Expanded responsibility: template download orchestration, template sheet fill, sample generation, selected-column header rendering for 자료유형별 증빙원본 템플릿
  - Controllers: `EvidenceImportController`
  - Out of scope: dropdown option/query lookup, 자료유형 필드 SSOT 결정, upload parse/save, DB schema change
- `EvidenceDownloadService`
  - Expanded responsibility: 증빙원본 자료유형별 다운로드 spreadsheet generation, selected-column filtering, `information_schema.COLUMNS` 기반 실DB 컬럼 헤더 출력
  - Controllers: `EvidenceDownloadController`
  - Out of scope: 화면 렌더링, DB schema change, controller response output
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
  - Responsibility: evidence status helper, readiness apply helper, active output detection, transaction/voucher existence helper, evidence status SQL helper
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

??ш끽維?????源놁졆 ?熬곣뫀?????れ삀?? Ledger Evidence Service ???????? Service ??獄쏅똻?????獒?癲?????怨뚮뼚??????????뽮덫?臾먯뒞筌???影?얠맽 ??좊즲????筌먲퐢??

| Service癲?| ?????????| ????Controller | ??????ヂ??? ???ㅼ굡??|
| --- | --- | --- | --- |
| EvidencePayloadService | 癲ル슣鍮섌뜮??payload ???源낅??棺??짆?삠궘? 癲ル슣鍮섌뜮????釉먯뒠???濡ろ떟??? payload ?釉뚰???濚욌꼬?댄꺁????れ삀??????????筌먲퐢?? | ImportController, EvidencePayloadController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| ????겾?????ㅺ컼???怨뚮뼚??? ?????怨뚮옖甕걔?? 癲꾧퀗??????獄쏅똻??|
| EvidenceStatusService | 癲ル슣鍮섌뜮??癲ル슪?ｇ몭?????ㅺ컼???怨뚮뼚??濡ろ뜑????嶺뚮㉡?ｈ????筌?留??怨뚮뼚??濡ろ뜑?????????筌먲퐢?? | ImportController, EvidenceStatusController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| payload ?怨뚮옖筌?쑜猷????? ????겾??????? ?????怨뚮옖甕걔?? 癲꾧퀗??????ш낄援????獄쏅똻??|
| EvidenceTrashService | 癲ル슣鍮섌뜮?????? ?怨뚮옖甕걔?? purge ???????釉뚰????嶺뚮Ĳ??????????筌먲퐢?? | ImportController, EvidenceGenerationController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| ????겾??????? payload ?怨뚮옖??? 癲꾧퀗??????獄쏅똻?? ??ш낄援????獄쏅똻??|
| EvidenceUploadService | ????겾???袁⑸즲????釉뚰??? ????겾?????釉뚰??? ????겾?????爾???롢걫???????筌먲퐢?? | ImportController, EvidenceUploadController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| ??獄쏅똻????醫롫뙃 ???? split/merge, 癲꾧퀗??????獄쏅똻?? ??ш낄援????獄쏅똻??|
| EvidenceGenerationService | ??獄쏅똻????醫롫뙃 癲ル슢?꾤땟戮⑤뭄??釉뚰??? ??ш낄援?? ?嶺뚮㉡?ｈ?? processing item ?嶺뚮Ĳ????釉뚰???袁ⓦ걫???????筌먲퐢?? | ImportController, EvidenceGenerationController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| ???? ????겾??????? 癲꾧퀗??????獄쏅똻??|
| EvidencePayloadNormalizeService | payload ???????쑩?젆?normalize, ??ш끽維??????筌???ш끽維곲??濡ろ떟??? format column ?嶺뚮㉡?ｈ?????????筌먲퐢?? | ImportController, EvidenceGenerationSaveService, EvidenceGenerationService, EvidenceUploadService ??ш끽維쀨굢??濡ろ뜑?灌鍮?| readiness, reference resolve, transaction helper, voucher ???ㅺ컼???????|
| EvidenceRuleEngineService | readiness ????? readiness result ?釉뚰????, transaction create error format?????????筌먲퐢?? | ImportController, EvidenceGenerationService, EvidenceTransactionCreateService ??ш끽維쀨굢??濡ろ뜑?灌鍮?| reference resolve, transaction context resolve, payload normalize |
| EvidenceTypePolicyService | import/source type ?嶺뚮Ĳ????嶺???? ???⑤슢?? SQL 癲ル슢???⑸눀? legacy data type ?嶺뚮Ĳ????釉뚰???袁ⓦ걫???????筌먲퐢?? | ImportController, EvidenceGenerationService, EvidenceStatusService, EvidenceTrashService ??ш끽維쀨굢??濡ろ뜑?灌鍮?| readiness, payload ?嶺???? 癲꾧퀗??????獄쏅똻?? ??ш낄援????獄쏅똻??|
| EvidenceGenerationSplitService | ??獄쏅똻????醫롫뙃 split child ??獄쏅똻?????쒓낯????????processing item split ?怨뚮옖???癲ル슪?ｇ몭???????????筌먲퐢?? | ImportController, EvidenceGenerationController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| ????겾?????? bulk save, 癲꾧퀗??????獄쏅똻?? ??ш낄援????獄쏅똻??|
| EvidenceGenerationSaveService | ??獄쏅똻????醫롫뙃 ??影?뉕틧 ???? ??癲ル슣鍮섌뜮????獄쏅똻?? ????源??怨뚮옖???????????????筌먲퐢?? | ImportController, EvidenceGenerationController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| ???살쓴??helper ???띠룇??? ????겾??????? 癲꾧퀗??????獄쏅똻?? ??ш낄援????獄쏅똻??|
| EvidenceTransactionCreateService | 癲ル슣鍮섌뜮????れ삀??뫢?癲꾧퀗??????獄쏅똻??orchestration??Transaction ??ш끽維???釉뚰???濚욌꼬?댄꺇??燁???helper????????筌먲퐢?? | ImportController, EvidenceTransactionController ??ш끽維쀨굢??濡ろ뜑?灌鍮?| Upload helper ????? Generation helper ????? readiness ?嶺뚮Ĳ???????? Route 癲ル슪?ｇ몭??|
| EvidenceDualWriteService | legacy row ???ㅺ컼???癲ル슣鍮섌뜮?????Β????? ???ル㎦??癲ル슣鍮섌뜮???怨뚮옖筌?쑜猷???????怨?繞?????뗫탿??????? | EvidenceController, ImportController, EvidenceGenerationSaveService | payload/status/link ?嶺뚮Ĳ????濡ろ뜏??? Controller ???쑩?젆?癲ル슪?ｇ몭??|
| ProcessingItemSplitService | processing item split ?濡ろ뜏???? aggregate ???ㅼ뒭?嚥▲룗????????筌먲퐢?? | EvidenceGenerationSplitService, ImportController ?怨뚮옖?????ш끽維쀨굢??濡ろ뜑?灌鍮?| payload ???? ???ㅺ컼?????? 癲꾧퀗??????ш낄援????獄쏅똻??|
| ProcessingItemAggregateService | split/merge ?濡ろ뜏???醫듽걫?processing item ??れ삀?????⑥??癲ル슣?띰ℓ???筌먲퐢?? | ProcessingItemSplitService, TransactionCrudService 癲ル슔?蹂?덫???濡ろ뜑?灌鍮?| Controller ???쑩?젆?癲ル슪?ｇ몭?? ????겾???????|
| ProcessingItemTreeService | processing item 癲ル슢?꾤땟戮⑤뭄???嶺뚮ㅎ遊뉔걡???節뚮쳮嶺?????깼??瑜곷퓠??嶺뚮㉡?섌걡??筌먲퐢?? | EvidenceGenerationService, ImportController ?怨뚮옖?????ш끽維쀨굢??濡ろ뜑?灌鍮?| DB ???? payload ?怨뚮옖???|
| ProcessingItemActionService | processing item action ??れ삀??쎈뭄??釉뚰???????????????筌먲퐢?? | ProcessingItemActionModel ?????濡ろ뜑?灌鍮?| payload ???? 癲ル슣鍮섌뜮?????ㅺ컼???嶺뚮Ĳ???|

## ???⑤㈇猿 ???獒??

- Controller??request ???쒓낯?? Service ?嶺뚮ㅎ??? response ?袁⑸즵???猷뱀떴????얜Ŧ類??筌먲퐢??
- ???살쓴??helper??????????곕쿊 ??????? ??熬곥걿??callback ??낆뒩???????Β?띾쭡??筌먲퐢??
- Service??좊읈? ????렺???ш끽維곮?嶺뚮ㅎ?닺린??????嶺뚮Ĳ????癲ル슣?????怨뚮뼚??濡ろ뜑???壤?????筌먲퐢??
- Service 癲??????袁⑸즴????????????뽮덫??? DecisionLog????影?얠맽 ??좊즲????筌먲퐢??
## 2026-06-27 Addendum

| Service | Responsibility | Used By | Notes |
| --- | --- | --- | --- |
| TransactionVoucherService | Owns voucher recommendation, draft voucher creation, voucher link and unlink, and transaction-voucher relation hydration for the transaction domain. | TransactionController | Keeps voucher orchestration out of the transaction controller so transaction entry can stay focused on common transaction management. |
| TransactionCrudService | Owns transaction header save, pretax item save, settlement save, detail payload hydration, and header total recomputation using the SSOT amounts `transaction_foreign_amount`, `transaction_supply_amount`, `transaction_settlement_amount`, and `transaction_final_amount`. | TransactionController, EvidenceTransactionController, VoucherController | Keeps transaction modal save/read orchestration in one service while leaving voucher approval and posting flows outside the transaction domain; legacy header amounts remain detail/list fallback only and are excluded from new save and recalculation flows. |
