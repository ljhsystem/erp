# ERP Architecture

## Settings standard management navigation

설정 메뉴는 `기초정보관리 → 조직관리 → 기준관리 → 시스템설정` 순서를 사용한다. `기준관리`는 공용 설정 탭 구조 아래 `코드`, `법정기준` 탭을 제공한다. 코드관리의 업무 구현과 API·권한은 이동 전 단일 구현을 그대로 재사용한다.

## Statutory standards boundary

`statutory_standards` owns one effective-period row per official standard in `system_statutory_standards` and zero or more evidence rows in `system_statutory_standard_sources`. Payroll, income reporting, tax, insurance, evidence, transaction, and voucher domains own their calculated results. Consumers use `StatutoryStandardResolver`; latest-row inference and numeric fallback are prohibited.

`system_statutory_standards.effective_from/effective_to` own the applicability period. Publication date belongs only to each related evidence row as `system_statutory_standard_sources.published_at`; the standard row does not duplicate or derive a publication-date SSOT.

The active type contract contains 13 `STATUTORY_STANDARD_TYPE` rows. Each type's `extra_data` owns its dynamic value-field contract. Resolution uses only `standard_type_code + date`; zero matches and multiple matches are both errors.

Physical-column labels and required defaults come from DB `COLUMN_COMMENT` and `IS_NULLABLE` through the shared Table Setting metadata path. DataTable and Modal consume that setting; dynamic JSON fields consume `STATUTORY_STANDARD_TYPE.extra_data`.

## Employment rules boundary

`employment_rules` is the company-policy SSOT. The flow is policy → employment contract → attendance → leave → payroll → tax outputs. Approved contract snapshots are not rewritten when a rule changes. Operational domains keep their own ledgers and resolve effective policies through `EmploymentRulePolicyService`.

## Evidence Read, Policy, and Schema DB Flow

```text
Evidence Services
  ├─ schema/expression policy → EvidenceSchemaModel
  ├─ evidence summary/download/body lookup → EvidenceImportModel → EvidenceBodyStorageModel
  ├─ business reference resolution → EvidenceReferenceModel + domain Models
  ├─ status/link lookup → EvidenceLinkModel (canonical import_type + evidence_id)
  ├─ code policy → CodeModel
  └─ dropdown reads → EvidenceDropdownModel

Upload persistence and duplicate lookup follow the same boundary:

`EvidenceUploadService → EvidenceUploadPersistService → EvidenceBodyStorageModel / type-specific Evidence Models`

- `EvidenceExternalKeyService` is the external-key SSOT. Stable provider IDs win; otherwise an ordered canonical raw-source string is SHA-256 hashed. File name, row number, upload time/user, UUID, sort order, state/link values, and corrected ERP business references never participate.
- Upload Services retain file parsing, validation, duplicate/new decisions, call ordering, counters, and transaction/rollback boundaries. An existing key is skipped regardless of soft deletion, readiness, processing completion, or transaction/voucher linkage and is never updated by upload.
- File-internal duplicates are skipped after the first row. `EvidenceBodyStorageModel` searches all rows including soft-deleted rows and reports multiple existing rows as a conflict. Per-Body unique indexes close the concurrent-insert race; SQLSTATE `23000` is treated as duplicate only when the DB reports a duplicate-key violation.
- Models own type-specific Body writes, Body seed lookup, batch reads, duplicate reads, and schema metadata reads.
- Retired integrated-source and processing-state tables must not be used as optional fallbacks. Upload processing state is not persisted separately.
- Evidence report and download reads select the type-specific Body directly. Bank reports use `ledger_evidence_bank_transaction`, bind `BANK_TRANSACTION` when reading `ledger_evidence_links`, and expose Body `evidence_status` without a generation-processing join.
- Body Read Models depend only on schema/status Models. Runtime metadata SQL belongs to `EvidenceSchemaModel`; `SystemFieldService` only assembles field policy and presentation metadata.
- Evidence lifecycle state is stored on each active Body table. Soft delete preserves evidence links; status mutation and delete/purge are blocked while a canonical link exists. Hard delete is limited to already deleted, unlinked Body rows.
- Generation Center and evidence Split are retired. Evidence identity uses each Body table's `(canonical import_type, id)`, while transaction and voucher linkage uses `ledger_evidence_links`.
- Dynamic evidence Body access is limited to the server-side evidence table allowlist; Services do not pass SQL text or SQL fragments to Models.
```

- `import_type + evidence_id` remains the evidence identity contract; no evidence-id-only persistence path is introduced.
- Active transaction/voucher output detection is derived from `ledger_evidence_links`; transaction IDs, voucher source IDs, fingerprints, and evidence-id-only lookup are not fallback identities.
- Dynamic dropdown identifiers are accepted only after Service policy/schema checks and are revalidated against Model-side identifier and table allowlists.
- EvidenceReferenceModel owns a fixed reference-type-to-table/column map. It is not a generic reference or raw-query Model.
- Services keep normalization, fallback, cache, status, label, workbook, and result-composition responsibilities and contain no executable SQL.
- Removed generation-center READY/readiness storage paths and the removed `transaction_type` field are not restored.
- Calendar is outside this flow and remains unchanged.

## Evidence list initial-loading boundary

- The initial critical path is limited to the server-rendered Type policy, the current Type metadata needed to construct columns, and the first server-side DataTable page.
- Type counters run independently, and display-only code options load after the first table render. Neither may delay the first table request or first usable render.
- Initial hidden-column policy is applied without an additional server-side redraw; normal search, sort, paging, and settings redraws remain unchanged.
- The evidence DataTable disables the shared global page-loading overlay and retains only its internal loading state, preventing a server-rendered screen from being covered again after ES Module startup.
- Trash list data and trash UI assets load on first trash use. PDF/ZIP/print, spreadsheet/Excel UI, and address-search assets are excluded from the initial evidence-list asset profile unless the related feature is invoked.
- Read-only evidence list requests release the authenticated PHP session lock before Service/DB work. Authentication and permission checks remain middleware responsibilities and are completed before the Controller action.

## Phase 1-1 DB Access Boundary

## Chart Account and Journal Rule DB Flow

Voucher reads follow `VoucherController → VoucherQueryService → VoucherModel / VoucherLineModel / VoucherLineRefService / EvidenceLinkModel / EvidenceSourceRepository`. The Controller collects requests and formats responses; it does not assemble multi-Model read flows.

`전표검토·전기` 목록은 `VoucherReviewQueryModel`의 DB server-side read model을 사용한다. 목록 범위는 `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`이며, 경량 Count와 allowlist 기반 정렬·100건 페이지 조회를 분리한다. Line, Ref, Evidence Body, 추천 Snapshot과 취소전표 관계는 상세 요청에서만 batch 조회한다.

```text
ChartAccountController -> ChartAccountService -> ChartAccountModel / SubChartAccountModel
SubChartAccountController -> CustomSubAccountService -> SubChartAccountModel / ChartAccountModel
JournalRuleController -> JournalRuleService -> JournalRuleModel
VoucherController -> VoucherEvidenceRecommendationService -> JournalCandidateEngineService -> JournalCandidateRepository
```

전표입력은 Excel 양식·다운로드·업로드를 입력 또는 관리 경로로 제공하지 않는다. 공식 작성 경로는 수동 작성, Evidence 기반 추천 적용, 취소전표 생성이며 모두 `VoucherService`의 계정·차대변·보조계정·Evidence 검증을 통과한다. 추천 선택 후 계정·차대·금액·라인 참조가 추천 Snapshot과 달라지면 `is_user_modified=1`, 최종값이 Snapshot과 다시 같아지면 `0`으로 복원한다. 적요만 변경한 경우에는 분개판정 변경으로 보지 않는다. 최초 추천 계정·차대·금액은 POSTED Event 생성에 필요한 최소 서버 Snapshot으로 라인에 함께 저장한다. 취소전표는 감사 추적을 위해 원전표의 `journal_rule_id`를 유지할 수 있지만 Rule usage와 학습 대상에서는 제외한다. 추천 Repository cache는 HTTP 요청 범위에만 존재한다.

취소전표는 원전표의 Header·Line·Line Ref와 `reversal_of` 관계를 생성하지만 원전표 Evidence 링크를 복제하지 않는다. Evidence는 원전표에 직접 귀속되며, 취소전표 상세는 `reversal_of → 원전표 ledger_evidence_links → Evidence Body` 경로로 원전표 증빙을 읽기 전용 조회한다. API와 UI는 취소전표의 `직접 연결 증빙`과 `원전표 증빙 (읽기 전용)`을 구분한다.

- `ledger_accounts` is the chart-account SSOT, `ledger_accounts_sub.ref_target` is the account-level allowed sub-account-target policy, and `ledger_journal_rules` is the journal-rule SSOT.
- 분개규칙 화면은 Excel Manager를 사용하지 않는다. `JournalRuleService`가 코드·계정 유효성, 조건 충돌, 휴지통과 사용 Rule 영구삭제 방어를 담당한다.
- 회계결과 SSOT는 `ledger_vouchers`, `ledger_voucher_lines`, `ledger_voucher_line_refs`다. 거래처 기본계정은 사용자 명시정책이고, 분개규칙·최근패턴·거래처패턴·학습이벤트는 추천용 정책 또는 Projection이다. 추천 조회는 Read-only이며 결과 점수는 영구 회계 SSOT가 아니다.
- `POSTED` 최초 전환 뒤 실제 미수정 Rule 연결에 대해서만 `usage_count`와 `last_used_at`을 갱신한다. 자동 Rule 생성·수정은 manual/system 구분 Schema 승인 전까지 수행하지 않는다.
- `ledger_journal_learning_events`는 최초 POSTED 확정라인의 학습 Event SSOT이고 Recent/Client 테이블은 파생 Projection이다. Context는 연결 Evidence semantic과 공통 line ref에서만 재구성하며 복수 Evidence가 불일치하면 임의 방향·거래처를 선택하지 않고 Context Projection을 생략한다.
- Voucher-line sub-account values remain in `ledger_voucher_line_refs`; account policy rows do not become a second voucher-line value store.
- The absent historical policy table is not recreated or used as a fallback, and `ref_type` is not restored.

- General business SQL belongs to a domain Model. Controller, View, Controller Trait, Helper, Registry, Resolver, Middleware, and other core files must not execute business SQL.
- Core callers use Services or responsibility-specific Models while preserving their public contracts and bootstrap timing. A Service may resolve its default connection internally when legacy core static entry points cannot receive constructor injection.
- `Database.php` is limited to runtime connection creation and PDO options. `DbPdo.php` is limited to connection access and transaction boundaries. `Router.php` may obtain the shared connection only to preserve the existing Controller constructor-injection contract.
- These infrastructure files must not contain business-table SQL, accept user-provided SQL, or be reused as a raw-query bypass.
- `PermissionRegistry` and `PageKeyResolver` retain their PDO-compatible public constructors but delegate permission and page-registry SQL to Query Services and Models.
- Backup, restore, and synchronization dump SQL remains limited to the previously approved infrastructure Services. This exception does not extend to ordinary business Services or core files.
- Calendar code and Calendar-owned SQL are excluded from phase 1-1. Shared contracts used by Calendar remain unchanged.

```text
Core Middleware / Helper → Domain Service → Domain Model
Router → shared PDO → Controller constructor → Domain Service → Domain Model
Database / DbPdo → connection and transaction infrastructure only
```

## Common Helper DB Access Flow

```text
ActorHelper    → ActorDirectoryModel → user_employees
DataHelper     → CoverModel          → system_coverimage_assets
SequenceHelper → SequenceModel       → validated table/column MAX lookup
```

- Helpers preserve their existing public static contracts and contain no SQL, PDO execution, or Database access.
- Actor token selection, parsing, fallback, and standard `*_by_name` field rules remain owned by ActorHelper.
- ActorDirectoryModel is a read-only Actor display directory, preventing ActorHelper from calling EmployeeModel, whose result methods already use ActorHelper for display enrichment.
- CoverModel preserves the original resequencing query order, transaction boundary, rollback behavior, active-before-trash ordering, and one-based sequence.
- SequenceModel preserves the existing `MAX + 1` behavior. SequenceHelper validates identifiers before the Model call and preserves error logging and exception propagation.
- Calendar continues to call the unchanged ActorHelper contract; no Calendar file or Calendar-specific behavior is changed.

## Ledger Import Controller Trait DB Flow

Ledger Import Controller traits are HTTP-entry helpers and call domain Services only.

```text
Import Controller Trait
  ├─ EvidenceImportBusinessService → CompanyModel / ClientModel
  ├─ EvidenceImportBatchService → EvidenceImportModel
  └─ EvidenceSchemaService → EvidenceSchemaModel
```

- Client lookup/update SQL, Import source-row lookup SQL, and schema metadata SQL reside only in Models.
- Table and column names are bound as values against `information_schema`; no user identifier is interpolated into SQL.
- Existing Trait method signatures and callback return shapes remain unchanged for all Import controllers.
- This flow does not restore or reference the removed generation-center runtime.

## Settings Permission and Page Metadata Flow

`DashboardController → SettingsNavigationService → MenuRegistryModel/PermissionService → DashboardController → settings.php`

- Settings Views receive prepared menu rows and permission decisions and do not create DB, Model, or Service objects.
- `PermissionRegistry` retains route permission registration and synchronization ordering; all `auth_permissions` SQL is executed by `PermissionModel` through `PermissionService`.
- `PageKeyResolver` retains cache keys and legacy page-key interpretation; `system_page_registry` SQL is executed by `PageRegistryModel` through `PageRegistryQueryService`.
- Calendar files and calendar-specific permission/menu behavior are outside this flow and remain unchanged.

## Future Policy Engine

## System Metadata and Runtime Status DB Boundary

- System Services compose runtime results and preserve their public response contracts; they do not execute SQL or obtain DB connections directly.
- `DataTableColumnMetaService -> SystemSchemaModel` owns allowlisted physical metadata reads from `information_schema`.
- `DatabaseReplicationStatusService -> DatabaseReplicationStatusModel` keeps topology/config interpretation in the Service and fixed server-status SQL in the Model. This does not extend the backup/restore/sync infrastructure SQL exception.
- `LayoutService` composes session, employee, UI, and brand data through `EmployeeModel`, `SettingService`, and existing authentication/session Services.
- `NotificationService` keeps validation and orchestration while `NotificationModel` owns notification persistence and `UserModel` owns administrator recipient lookup.


- Status: deferred
- Decision date: 2026-06-26

### Decision

Policy Registry, Policy Loader, Policy Reader, Policy Helper, Policy Metadata, Policy Alias, Policy Normalizer, and Policy Validator were approved as an ERP-wide future architecture direction.

The design is retained as a standard architecture reference, but implementation is deferred.

### Why Deferred

Policy Registry is an ERP framework-level refactor.

Current priority is stabilizing core accounting workflows in this order:

1. Evidence
2. Transaction Management
3. Transaction Management
4. Voucher Management
5. Ledger Management
6. Closing
7. Financial Statements

Framework refactoring will resume only after the business domains above are functionally stable.

### Current Runtime Rule

- Keep the current implementation structure.
- Keep `EvidenceTypePolicyService` as-is.
- Do not start Policy Registry implementation during the current accounting delivery phase.

### Resume Condition

Revisit Policy Registry only in the later framework refactoring phase, after the accounting core modules above are completed and stabilized.
# Evidence Selection Master Refactoring Architecture

```text
Evidence SSOT (증빙원본)
        ├─ 거래입력 → TransactionCrudService ─┐
        └─ 전표입력 → VoucherService ─────────┤
                                               ↓
                                      EvidenceLinkModel
                                               ↓
                                  ledger_evidence_links (DB SSOT)
```

## 책임

- 증빙원본: 조회, 수정, 상태, Validation, 연결정보. 거래입력·전표입력은 Evidence Body를 `ledger_evidence_links`로 연결한다.
- 거래입력: 신규 생성, 증빙 참조 생성, 거래 추천, 거래 저장, 증빙 연결.
- 전표입력: 신규 생성, 증빙 참조 생성, 전표 추천, 전표 저장, 증빙 연결, 검토·승인.
- EvidenceLinkModel: 증빙↔거래/전표 연결, 활성 링크 조회, 삭제·복구, 중복 방지. 저장·추천·Validation·workflow는 금지한다.

## Evidence Selection Module

- 거래입력, 전표입력과 향후 자금관리·지급결의·신고업무 등에서 재사용 가능한 프로젝트 공용 모듈이다.

## 기준정보 조회 경계

카드·프로젝트·업무팀 Service는 검증, Excel 조정과 상태 흐름만 담당하며 카드사·결제계좌·프로젝트 관계·팀장 후보 조회는 각각 `CardModel`, `ProjectModel`, `WorkTeamModel`, `ClientModel`, `BankAccountModel`, `EmployeeModel`에 둔다. `ProjectReferenceResolver`는 기존 이름 해석 계약만 유지하고 DB를 직접 조회하지 않는다.

거래처·프로젝트 Excel Service는 PhpSpreadsheet 파일 처리, 헤더·필수구분, 행 정규화와 결과 집계만 담당한다. 참조 시트 dropdown은 `ClientModel`, `ChartAccountModel`, `EmployeeModel`의 명시적 allowlist 조회를 사용한다.
- 증빙 검색, 자료유형 검색, 다중선택, 연결상태 표시, 선택 결과 반환만 담당한다.
- 반환 계약은 `[{ import_type, evidence_id }]`이다.

## 자사계좌 내부이체 SSOT

- 별도 내부이체 관계 테이블은 사용하지 않는다.
- `ledger_evidence_bank_transaction → ledger_evidence_links → ledger_vouchers → ledger_voucher_lines → ledger_voucher_line_refs → system_bank_accounts`가 유일한 판정 경로다.
- 삭제·역분개 전표와 `draft`, `review_requested`는 제외하고 `REVIEWED`, `POSTED`, `CLOSED` 전표만 확정 대상으로 삼는다.
- 활성 은행 증빙은 정확히 출금 1건·입금 1건이어야 하고 금액이 정확히 같으며 서로 다른 자사계좌여야 한다.
- 실제 DB canonical 계좌 참조인 `ACCOUNT`가 출금 대변·입금 차변 라인에 각각 한 번 존재하고 라인 금액이 증빙 금액과 일치해야 한다.
- 추가 은행 증빙·추가 계좌 참조·방향 또는 금액 불일치가 있으면 확정하지 않는다.
- 적요·계정명·날짜 근접성·유사금액은 판정 근거로 사용하지 않는다.
- 자금일보는 확정 관계를 내부입금·내부출금으로 분리하되 계좌별 잔액에는 원본 입출금을 그대로 반영한다. 계좌별 거래내역은 같은 공용 판정에서 상대 자사계좌·전표·방향·금액을 표시한다.

## 대외기관업무 정보구조

- 대외기관업무는 기관별 원본 메뉴가 아니라 `인사·노무관리 → 소득자료관리 → 기관별 신고업무 → 신고이력` 순서로 구성한다.
- 대외기관업무의 페이지 소유 테이블은 `institution_{복수형 page_domain}_*`을 사용한다. 근로계약은 `institution_employment_contracts_*`, 인사발령은 `institution_personnel_actions_*`, 직무·배치는 `institution_job_assignments_*`, 근태는 `institution_attendance_*`가 물리 네이밍 SSOT다.
- `user_*`은 인증 사용자와 전사 공용 직원·조직 마스터에만 유지한다. 페이지 소유 거래·기간이력·스냅샷·감사 테이블에는 사용하지 않는다.
- 직원 기본정보 SSOT는 기존 `auth_users`, `user_employees`다. 대외기관업무 아래에 직원관리·사용자관리·직원인사정보 메뉴를 중복 생성하지 않는다.
- 직원은 거래처가 아니며 모든 직원 관계는 `employee_id → user_employees.id`를 사용한다. `system_clients`는 독립된 거래처 SSOT이고 `user_employees`와 직접 연결하지 않는다.
- 부서 마스터 SSOT는 `user_departments`이며 현재 계층형 구조가 아니다. 참조가 없는 오등록 부서만 영구삭제하고 직원 현재부서·기간이력·인사발령·취업규칙에서 참조하는 부서는 Application Guard로 삭제를 차단한다. 조직개편과 부서 폐지는 `is_active = 0`으로 처리한다.
- 직위·직책 통합 마스터 SSOT는 `user_positions`다. 별도 직급 Master를 만들지 않고 `level_rank`를 독립 SSOT로 해석하지 않는다. 현재 직원·기간이력·인사발령·취업규칙에서 참조하는 행은 Application Guard로 삭제를 차단하며, 참조 없는 오등록 행만 영구삭제하고 미사용은 `is_active = 0`으로 처리한다.
- 인사·노무관리 순서는 근로계약, 인사발령, 직무·배치, 근태, 휴가, 자격·교육, 성과평가, 보상·인센티브, 취업규칙·인사규정이다.
- 목표관리와 평가는 `성과평가관리` 하나로 통합한다. 승진·부서이동·직위변경·현장배치·휴직·복직·퇴직과 그 이력은 향후 `인사발령관리` 책임으로 설계하며 별도 인사이력 메뉴를 만들지 않는다.
- 소득자료관리는 상용근로소득, 일용근로소득, 사업소득으로 구성하고 이후 국세업무, 지방세업무, 4대보험업무, 세무사업무, 신고이력으로 연결한다.
- 각 페이지의 구현 단계와 지원 범위는 해당 프로젝트 문서와 TableDictionary를 따른다. 정보구조 문단의 메뉴 순서는 구현 완료 여부를 뜻하지 않는다.

### 직무·배치관리 SSOT와 조회 경계

- 직원 마스터 `user_employees`는 현재 재직상태·부서·직위·직무만 보유한다. 근로계약은 계약 당시 조건 스냅샷이므로 현재 배치 변경으로 갱신하지 않는다.
- 기준일 상태는 재직상태, 부서, 직위·직책, 직무, 프로젝트, 근무지 기간이력의 시작일 이상이면서 종료일이 없거나 기준일 이상인 행에서 재현한다.
- 프로젝트 자체 기간과 직원 프로젝트 배치 기간은 독립적이며, `institution_job_assignments_project_histories`는 복수 프로젝트 참여를 허용하고 활성 주배치는 직원당 하나만 허용한다.
- 직위·직책은 현행 `user_positions` 통합 SSOT를 유지하고 직무는 `institution_job_assignments_jobs`를 사용한다. 화면 편의를 위한 별도 직책·직무 문자열 SSOT를 만들지 않는다.
- 일반 직무·배치 변경은 인사발령 기안·결재·효력일 도래 후 `PersonnelActionApplyService`가 직원 현재값과 기간이력을 한 트랜잭션으로 갱신한다. 직무·배치관리 화면은 해당 결과와 근거 발령을 조회하며 직접 변경·종료 로직을 소유하지 않는다.
- 과거 직접 보정은 승인된 별도 관리자 정책이 없으므로 제공하지 않는다. 도입 전 부서·직위 과거값은 추정하거나 계약 스냅샷에서 복원하지 않는다.

## 지급예정 구조 보류

- `ledger_payment_schedules`와 `ledger_payment_schedule_histories`는 실제 업무 요구사항 재확정 전까지 현 구조를 승인하거나 변경하지 않는다.
- 지급의무·지급계획 분리, 계획 분할지급, 실제 지급 전표 대사, 현금·외화 정책과 감사이력 형식은 사용자 승인 후 결정한다.
- 거래·전표 저장, 추천, 업무 Validation을 수행하지 않고 consumer callback에 결과만 전달한다.

## 거래입력 흐름

`신규 시작 → Evidence Selection Module → 증빙 선택 → 거래 추천 → Controller → TransactionCrudService → 거래 저장 → EvidenceLinkModel 링크 저장 → 결과 재조회`

- `TransactionCrudService` owns validation, transaction boundaries, call ordering, totals, and result assembly. Header, item, settlement, and link SQL is owned by their respective Models.
- Header, items, settlements, canonical DATA/BOTH evidence links, totals, and Actor fields commit or roll back together.
- Transaction soft delete and restore mutate only transaction lifecycle fields and retain evidence links. Purge is limited to already deleted transactions and removes items, settlements, `target_type=TRANSACTION` links, and the header in the same transaction.
- 거래입력은 Header·Item·Settlement 개별 Excel 입출력을 제공하지 않는다. 거래 생성은 Evidence 기반 생성, 승인업무 자동생성 또는 수동 Draft 등록 경로를 사용한다.

## 전표입력 흐름

계정과목의 저장 SSOT는 `ChartAccountService`이며 신규·수정·계층 및 보조계정 정책 변경은 화면 Modal에서만 수행한다. 계정과목 페이지는 전용 Excel Manager, 양식 다운로드, 데이터 다운로드, 업로드 기능을 사용하지 않는다. `ledger_accounts`는 계정과목, `ledger_accounts_sub`는 허용 보조계정 정책, `ledger_voucher_line_refs`는 전표에서 선택한 실제 참조값의 SSOT다.

`신규 시작 → Evidence Selection Module → 증빙 선택 → Journal Candidate → Controller → VoucherService → 전표/분개 저장 → EvidenceLinkModel 링크 저장 → 결과 재조회`

REVIEWED와 POSTED 전환은 전표 상태, 분개 상태, 차변·대변 균형을 검증한다. 거래와 전표는 각각 `ledger_evidence_links`에 독립적으로 연결되므로 동일 증빙의 활성 거래 존재 여부를 전표 검토완료·승인 조건으로 사용하지 않는다.

전표 저장 SQL은 `VoucherModel → VoucherLineModel → VoucherLineRefModel → EvidenceLinkModel`에 두고 Service는 검증, 저장 순서와 단일 트랜잭션만 조정한다. 소프트삭제·복원은 기존 증빙링크를 유지하며, 영구삭제는 이미 삭제된 전표에 한해 line ref, line, VOUCHER target link, header를 동일 트랜잭션에서 제거한다.

## 확정된 입력 책임

1. 증빙원본은 원본 조회·입력·수정·분할·상태관리만 담당한다.
2. 거래 생성과 거래추천은 거래입력이 담당한다.
3. 전표 생성과 분개추천은 전표입력이 담당한다.
4. 증빙 연결은 `(import_type, evidence_id)` Identity와 `ledger_evidence_links`를 SSOT로 사용한다.
5. 증빙 Split과 생성센터 processing 저장 구조는 사용하지 않는다.

## Personal expense approval boundary

Personal expense applications use `approval_personal_expenses` as the document-header SSOT and `approval_personal_expense_items` as the multi-line item SSOT. Employee ownership is resolved exclusively from the authenticated user. Approval history and overall workflow state remain in `user_approval_requests`, while `user_approval_request_steps` is the sole step SSOT; rejection or withdrawal followed by resubmission creates a new request rather than resetting history. The document header's `current_approval_request_id` selects that current immutable request and `document_status` is only its synchronized document-side projection. The integrated `approval-inbox` reads user-scoped boxes, full document detail, current steps, and prior submission history from those SSOTs, and delegates personal-expense actions to `PersonalExpenseApprovalService`. The application header is an approval bundle, while each application item is an independent accounting transaction unit. Final approval creates or reuses one evidence body, one transaction header, one transaction item, an optional VAT settlement, and one evidence link per item through `TransactionCrudService`, all in one database transaction. Existing evidence and active transaction links are reused, and only missing accounting results are created. Separate reprocess batch/item SSOTs are not used. Business corrections require a future approval cancellation/correction policy; operational recovery, if approved later, must inspect the current source, evidence, transaction, and link state and restore only missing results. The shared evidence page is read-only for this automatically generated source. Receipt attachments remain outside this flow until a shared business-document attachment store is approved.

Approval step assignment is snapshotted at submission. A `SUBMIT` step is completed immediately by the requester and is never exposed as an actionable approval. A step with `approver_id` is assigned to that user; a step with only `role_id` is jointly actionable by every currently eligible member of the active role without preselecting a person. The first successful atomic action records the actual user in `acted_by` and activates the next step once. Before action, role steps display the role and waiting state; after action, they display the actual actor and action time.
## Approval template step ordering

Approval-template step order is scoped by `template_id`, not by the whole table. `TemplateStepService` locks the parent template row and `ApprovalTemplateStepModel` allocates `MAX(sort_no) + 1 WHERE template_id = :template_id`. Create, delete, activation changes, and drag reorder preserve a continuous `1..N` sequence for that template only; inactive rows remain part of the uniqueness and renumbering set.

## Organization role boundary

`auth_roles` is the organization role SSOT and each account retains one `role_id`. `super_admin` and `admin` are protected role keys: their key, active state, and row cannot be removed through runtime role management. A general role can be hard-deleted only when user and approval-step references are absent; the role-permission children and role row are then deleted in one transaction. Inactive roles remain visible in the master list and for an existing selected value, but are excluded from new role selection and never grant runtime permissions.

Role-permission assignment keeps `auth_role_permissions` as the role Set SSOT. User mode is stored in `auth_user_permission_profiles` and direct mappings in `auth_user_permissions`. `ROLE` uses the role Set, `EXTEND` uses the union of role and user Sets, and `REPLACE` uses only the user Set. `PermissionService` is the runtime effective resolver and denies missing, unapproved, inactive, role-less, inactive-role, missing-Permission, and inactive-Permission cases before applying the selected Mode. `RolePermissionService` and `UserPermissionService` reuse the same effective recovery-administrator calculation. Permission changes apply on the next HTTP request because the cache remains request scoped.
## 휴가관리 경계

- 일반 휴가는 `institution_leave_*`, 장기 휴직은 `institution_job_assignments_leave_periods`를 각각 SSOT로 사용한다.
- 휴가 잔액은 별도 캐시 없이 불변 원장 합계로 계산하며, 승인된 사용은 근태가 복제하지 않고 직접 조회한다.
- `LEAVE_REQUEST`는 공용 전자결재 요청·단계를 상태 SSOT로 사용하고 휴가 헤더에는 현재 결재 포인터와 업무 projection만 둔다.
## 자격·교육관리

- 화면 도메인은 qualification-education, 물리 업무 도메인은 institution_qualifications_*와 institution_educations_*로 구분한다.
- Controller → QualificationEducationService → Qualification/Education Model 구조를 사용한다.
- 직원관리의 자격 진입점도 같은 자격·교육관리 URL과 API 원장을 사용하며 직원 마스터에 자격 값을 중복 저장하지 않는다.
# 법정기준관리 운영 계약

- 업무 저장소는 `system_statutory_standards`, `system_statutory_standard_sources` 두 테이블뿐이다.
- `system_statutory_standards`의 적용기간별 한 행 자체가 법정기준의 개정 이력이며 `value_data`를 직접 소유한다. 별도 Revision 객체·번호·parent·chain·Draft·Confirm·Correction·History·Audit 저장구조와 별도 적용조건 저장계약은 사용하지 않는다.
- 종료된 과거 기준은 `effective_from~effective_to`, 종료되지 않은 현행 기준은 `effective_from~NULL`로 표현한다. 새 기준 시행 시 기존 현행 행의 적용종료일을 확정하고 새로운 적용시작일의 행을 신규등록한다.
- `StatutoryStandardResolver`는 `effective_from <= 기준일 AND (effective_to IS NULL OR effective_to >= 기준일)`인 행을 조회한다. 0건은 적용 기준 없음, 1건은 정상, 2건 이상은 기간 중복 오류다.
- 근거자료는 기준행에 0~N으로 연결하며 별도 Correction 또는 Audit workflow를 만들지 않는다.
- 코드그룹은 `STATUTORY_STANDARD_TYPE`, `STATUTORY_ROUNDING_METHOD`만 사용한다.
## Voucher POSTED feedback loop

- 회계 원본은 `ledger_vouchers`와 `ledger_voucher_lines`이며 학습 원천은 최초 POSTED 시 생성되는 라인 단위 `ledger_journal_learning_events`다.
- Event는 회계 POSTED Transaction 안에서 기록하고, Recent/Client Projection과 Rule usage는 commit 후 후처리한다. Projection 실패는 회계확정을 rollback하지 않으며 Event로 재처리할 수 있다.
- `ledger_journal_recent_patterns`와 `ledger_journal_client_account_patterns`는 Event aggregate에서 deterministic 재계산되는 파생 Projection이다. 추천 GET은 이 테이블을 읽기만 한다.
- 취소전표는 Event, Projection, Rule usage 모두 0건이다. 수동라인도 Event 대상이지만 추천 원본은 NULL이며, 추천 수정라인은 최초 추천과 최종 계정을 구분해 기록한다.
- 복수 Evidence Context가 서로 다르면 임의 Context를 선택하지 않고 Context Projection에서 제외한다. 거래↔전표 직접 Link는 만들지 않는다.
