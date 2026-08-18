# Service Dictionary

## 2026-08-12 상용근로소득

- `RegularEmploymentIncomeService`: 귀속월 Header와 직원별 Item의 원자 저장, Item 합계 기반 Header 집계, 공용 결재 상신·회수, 최종승인 시 급여(신고) 증빙과 거래 및 Evidence Link의 멱등 생성을 담당한다. 자동 급여계산이나 법정요율 하드코딩은 하지 않는다.
- `RegularEmploymentIncomeApprovalAdapter`: `REGULAR_EMPLOYMENT_INCOME` 문서의 공용 결재함 상세와 승인·반려 처리를 위 Service로 연결한다.
- 재사용: `ApprovalWorkflowService`, `TransactionCrudService`, `EvidenceExternalKeyService`, `EvidenceLinkModel`, `ActorHelper`.

## 2026-08-11 코드관리 영구삭제

- `CodeService`: `system_codes` 코드 목록·상세·저장·그룹명 동기화·정렬과 단건 Hard Delete 흐름을 담당한다. 식별키 변경과 삭제는 `CodeReferenceService`의 동일한 fail-closed 판정을 사용하고, 참조 중 코드의 비활성화와 표시명 변경은 허용한다. 휴지통·복구·purge 및 Excel 입출력 책임은 제공하지 않는다.
- `CodeReferenceService`: `CodeReferenceRegistry`에 등록된 일반 물리 컬럼과 JSON 참조를 `CodeModel`로 검사해 사용자용 업무명·참조건수를 반환한다. 운영 코드그룹 전체를 등록 대상으로 하며 미등록 그룹, 존재하지 않는 대상, 조회 오류는 삭제·식별키 변경을 허용하지 않는다.
- `CodeReferenceRegistry`: 코드그룹별 참조 물리 위치의 단일 Registry다. 코드 삭제, `code_group`/`code` 변경, 참조내역 조회가 같은 정의를 사용한다. 신규 코드그룹은 참조 없음까지 명시적으로 등록하기 전 삭제할 수 없다.

## 2026-08-11 법정기준 Metadata 연계

- `StatutoryStandardService`는 `DataTableColumnMetaService`의 `statutory-standard`, `statutory-standard-source` 메타데이터를 options 응답과 물리 컬럼 필수값 검증에 재사용한다. DB NOT NULL은 완화할 수 없고, 사용자가 Table Setting에서 선택 컬럼을 필수로 강화한 정책은 저장 요청에도 적용한다.

## 2026-08-11 법정기준관리

- `StatutoryStandardService`: 한 행=한 적용기간 법정기준 CRUD, `SequenceHelper` 기반 신규 순번 채번, 동적 값 검증, NULL 종료일을 무한 종료로 보는 동일 종류 기간중복 방지, 관련근거와 원본파일의 트랜잭션 저장 및 영구삭제를 담당한다. 개정 등록은 기존 열린 기간을 사용자가 먼저 종료하는 방식 B를 사용하며 신규 저장이 기존 행을 자동 변경하지 않는다. 현재 적용 중인 행은 삭제할 수 없고 선택삭제는 전건 사전검증 후 하나의 DB Transaction에서 처리한다. `rate` 기준값은 Resolver용 소수 비율을 유지하되 저장 전에 12자리 정밀도로 정규화해 JSON 부동소수점 꼬리를 제거한다. 관련근거 파일은 파일저장소의 `public_document` 정책을 SSOT로 사용하며 options 응답에도 허용 확장자·MIME·용량·활성 상태를 제공해 파일 선택 UI와 서버 검증을 일치시킨다. 저장소 내부 생성명은 `file_path`에만 사용하고 `file_name`에는 사용자가 업로드한 원본 파일명을 보존한다. 본체 적용기간은 `effective_from`/`effective_to`, 근거자료 공표일은 Source의 `published_at`만 사용한다. Web/API는 `ActorHelper::user()`를 기본으로 사용하며 승인된 CLI 데이터 정비는 생성자에 `ActorHelper::system(context)`를 주입해 같은 검증·트랜잭션 경로를 사용한다.
- `StatutoryStandardTemplateService`: 운영 DB `system_codes.STATUTORY_STANDARD_TYPE.extra_data`를 직접 JSON decode하는 유일한 입력 Template 파서다. 활성 13개 유형에서 동적 값과 선택적 `calculation_policy.fields`의 `code`, `name`, `type`, `required` 계약을 조회·검증한다. 계산정책은 `text`, `select`, `boolean`, `amount`, `rate`, `number`, `rounding` 공용 타입으로 계산기초·단계·집계·판정·순서를 표현하며 `select.options`의 값·표시명·중복도 검증한다. `bracket`은 공용 Matrix 계약으로 정규화하고 연속구간 Validation을 활성화하되 DB 원본 Type은 유지한다. 실제 법정 숫자는 소유하지 않는다.
- `StatutoryStandardTemplateService`의 `type=matrix`는 비어 있지 않은 기본 `columns`와 선택적인 `dynamic_dimension`/`object_storage` 계약을 검증한다. `StatutoryStandardService`는 같은 계약으로 동적 Map과 숨김 컬럼의 시스템 기본값까지 정규화하며 `dash_as_zero`는 대시만 0으로, `blank_as_zero`는 대시와 공란을 0으로 구분한다. 행 필수값, 숫자·선택값, 음수·rate 범위, 중복키, 구간 역전·중첩·마지막 무제한 구간 검증은 유지한다. `connects_after.rows_key`가 선언된 구조화 규칙은 self-describing Matrix의 최종 상한과 첫 규칙 구간의 연결도 검증한다. `preserve_schema_in_value` 유형은 신규 저장 시 `_schema.fields`를 찍고 수정 시 DB에 저장된 구조 계약을 신뢰하되 현재 Template의 표시명·접힘 UI·숨김 기본값만 code 기준으로 병합한다. 따라서 과거 계산 의미는 유지하면서 사용자용 표현 개선은 과거 수정화면에도 적용된다. 특정 가족수나 초과구간 개수는 Service에 두지 않는다.
- `StatutoryStandardResolver`: `standard_type_code`, 기준일로 `effective_from <= 기준일 AND (effective_to IS NULL OR effective_to >= 기준일)`인 적용기간 행을 한 번 조회한다. Consumer는 같은 행의 `value_data`에서 기준값과 `calculation_policy`를 함께 사용하며 별도 후속 정책을 다시 Resolve하지 않는다. 0건은 적용 기준 없음, 1건은 정상, 2건 이상은 기간 중복 오류로 처리한다. 별도 Revision leaf나 상태를 해석하지 않으며 산재보험 사업종류별 요율 선택은 향후 계산 Consumer 책임이다.
- `StatutoryStandardSourceModel::replace`는 관련근거 전체를 삭제후 재삽입하지 않고 ID 기반 diff를 적용한다. 존재 ID는 수정하여 생성 감사값과 미교체 파일을 보존하고, 신규 ID는 삽입하며, 요청에서 제외된 ID만 삭제한다. 이 처리와 본체 저장은 같은 DB Transaction을 사용한다.
- `StatutoryStandardService::list`는 목록 projection에 `value_data` 전체를 노출하지 않고 Type Template의 첫 필드를 기준으로 생성한 `value_summary`만 제공한다. Matrix·Bracket·`_schema`·`calculation_policy`·Source 상세는 `detail`에서만 조회한다.
## 2026-08-06 취업규칙·인사규정

| Service | Responsibility | Reuse |
|---|---|---|
| `EmploymentRuleService` | 초안·개정·결재·시행·감사·Excel | `ApprovalWorkflowService`, `ActorHelper`, `system_codes` |
| `EmploymentRulePolicyService` | 기준일과 적용범위에 맞는 시행 정책 해석 | 근로계약·근태·휴가·급여의 읽기 경계 |
| `EmploymentRuleApprovalAdapter` | `EMPLOYMENT_RULE_REVISION` 결재 상세와 승인·반려 | 공용 결재 Adapter registry |

## 2026-08-04 인사발령관리

- `PersonnelActionChangePolicy`: 변경구분 11개 저장값·한글 표시명·입력 metadata와 발령유형 13개의 `allowed`·`required_all`·`required_any`·필수 상태값을 소유하는 Runtime 정책 SSOT다. 변경구분은 코드관리 대상이 아닌 고정 시스템 실행명령이며 실제 DB 쓰기는 담당하지 않는다.
- `PersonnelActionService`: 인사발령 목록·상세, Modal 지연 입력옵션, 작성중 헤더/복수 대상자/복수 변경행의 원자 저장, 공용 목록 순서변경, 결재요청·회수·결재결과 투영과 휴지통 조회·복원·단건/선택/전체 완전삭제 정책을 담당한다. 변경 전 값은 클라이언트 입력을 신뢰하지 않고 직원 현재 SSOT에서 생성하며 변경명령 집합은 `PersonnelActionChangePolicy`로 검증한다.
- `PersonnelActionApplyService`: 승인되고 효력일이 도래한 인사발령만 행 잠금과 단일 트랜잭션으로 직원 현재값 및 기간 이력에 적용한다. 적용 직전 저장된 명령집합을 `PersonnelActionChangePolicy`로 재검증하고 미지원 명령을 명시적으로 거부한다. 이미 적용된 발령은 성공적으로 무시하고 변경 전 스냅샷 충돌은 대상자 `FAILED`와 `application_error`로 남긴다.
- `PersonnelActionApprovalAdapter`: 공용 결재함에 `PERSONNEL_ACTION` 상세·대상자·변경행을 제공하고 결재 처리를 `PersonnelActionService`로 위임한다. 결재선은 템플릿 SSOT의 `SUBMIT → FINAL_APPROVAL` 2단계를 그대로 사용한다.
- `AttendanceService`: 불변 출퇴근 등록, 일별 근태 재계산, 관리자 직접 정정, 월 마감 revision 생성과 재오픈을 하나의 트랜잭션 경계로 처리한다. DB 조회·저장과 트랜잭션을 담당하며 시간 분류 규칙은 `AttendanceCalculationPolicy`에 위임한다. 자동 계산 예외는 `daily_record + exception_type + CALCULATION` 단일 행을 잠금 재사용하며 재발생 시 활성화하고, 계산 결과에서 사라진 미처리 자동 예외만 해결 처리한다. 수동 예외와 기존 관리자 처리 필드는 자동 재계산으로 삭제하지 않는다.
- `AttendanceCalculationPolicy`: 출퇴근 사건의 계약 일정 기반 `work_date` 귀속, WORK/BREAK/OUTSIDE 구간 합집합, 승인 휴가, 계약 예정초과, 일·주 법정 연장 중복 방지, 기준일 야간구간, 법정공휴일 근로와 지원 근무형태를 판정하는 근태 계산정책 SSOT다. `StatutoryStandardResolver`가 제공한 기준일 revision만 소비하고 DB I/O와 급여 금액 계산은 담당하지 않는다. 법정기준 또는 공휴일 calendar가 없거나 근무형태가 미지원이면 `NEEDS_CONFIRMATION`으로 마감을 차단한다.
- `AttendanceService` 월 마감 Guard: 출퇴근 누락, 계약·일정 충돌, 휴직기간 충돌, 연속 출퇴근 중복, `NEEDS_CONFIRMATION`을 서버에서 차단하고 원인별 건수를 사용자 메시지로 제공한다.
- `AttendanceWeeklyRecalculationService`: `WORKING_TIME_STANDARD.week_start_day`로 법정 주간을 결정하고 해당 직원의 주 Daily를 잠근 일괄 조회해 일 초과와 주 추가 초과를 중복 없이 발생 날짜에 재배분한다. 월 경계 마감 전에는 마감 Transaction 내에서 다음 달 closure와 Daily를 잠그고 근무일·명시적 일정예외의 Daily 준비, `NEEDS_CONFIRMATION`, blocking exception, CLOSED 상태를 최종 재검증한다. 마감은 다음 달 Daily를 즉시 생성하지 않으며 CLOSED 월은 재오픈을 요구한다.
- `StatutoryStandardService` 근태 Revision Guard: Daily가 참조하는 근로시간·공휴일 row의 종류, 시작일, `value_data`, 근거 Source와 참조 날짜를 훼손하는 종료일 수정을 차단한다. 정정은 기존 row 보존 후 신규 Revision/Correction INSERT로 처리한다.
- `AttendanceScheduleService`: 근무일에 유효한 근로계약과 주간 일정만으로 예정 근무 스냅샷을 계산하고 계약 없음·복수 계약을 예외로 반환한다.

## 2026-07-28 자금 실제·회계 잔액 분리

- `FundsOverviewService`: 증빙원본 입금-출금의 실제잔액과 POSTED/CLOSED 전표의 BANK_ACCOUNT 참조 라인 차변-대변 회계잔액을 계좌별로 분리하고 차이를 제공한다.
- `DailyFundsReportService`: 동일 기준일의 실제잔액, 회계잔액, 차이 및 지급의무 전망을 서로 다른 영역으로 제공하며 확정 내부이체를 외부 입출금과 분리한다.
- `FundsBalanceModel`: POSTED/CLOSED 전표라인과 `ledger_voucher_line_refs`의 `ACCOUNT/BANK/BANK_ACCOUNT` 참조를 사용해 계좌별 회계잔액을 조회한다.
- `BankTransactionReportModel`: 은행 원본 한 행에서 전표연결, PAYMENT 지급배분, 확정 내부이체 방향·상대 자사계좌·전표·금액을 독립 필드로 조회한다.
- `InternalTransferRepository`: 확정 전표, 활성 은행 Evidence Link 2건, 서로 다른 자사계좌, canonical `ACCOUNT` 라인 참조와 정확한 차변·대변 금액을 단일 계약으로 검증한다. 별도 내부이체 테이블, 문자열 추론, legacy fallback을 사용하지 않는다.

## 2026-07 Funds information architecture

- `FundsOverviewService`
  - Responsibility: 사용 중인 계좌 마스터와 증빙원본 입출금(은행) 거래를 조합해 자금유형별 계좌, 입금·출금 계산잔액, 최종거래일, 총잔액을 구성하고 `BANK` 코드의 저장 코드·기존 코드명을 표준 코드명으로 정규화한다.
  - Controllers: `FundsOverviewController`
  - Models: `BankAccountModel`, `BankTransactionReportModel`, `CodeModel`
  - Out of scope: 거래 저장·수정, 계좌 마스터 저장, 전표 연결, DB 구조 변경
- `BankTransactionReportService`
  - Expanded responsibility: 선택한 활성 계좌의 화면 진입 컨텍스트를 제공하고 해당 계좌 필터가 적용된 기존 거래 조회·요약·수정 흐름을 유지한다.
  - Controllers: `BankTransactionReportController`
  - Models: `BankAccountModel`, `BankTransactionReportModel`
  - Out of scope: 자금유형 분류와 전체 자금현황 집계

## 2026-07 Evidence read/policy SQL boundary

- Body Read Models use `EvidenceSchemaModel` directly for validated physical-column expressions; Models do not depend on a Service for schema access.
- `EvidenceBankHelperService`: preserves BANK_TRANSACTION validation/normalization and delegates legacy claim reset to `EvidenceImportModel`.
- `EvidenceReferenceResolverService`: preserves lookup order, normalization, caches, and fallback while delegating reads to `EvidenceReferenceModel`, `ClientModel`, and `ProjectModel`.
- `EvidenceStatusHelperService`: preserves status/readiness interpretation and detects active outputs only through `EvidenceLinkModel` using canonical `(import_type, evidence_id)` identity.
- `EvidenceSummarySearchService`: preserves JSON summary aggregation and ordering while `EvidenceImportModel` reads candidate rows.
- `EvidenceTemplateDropdownService`: preserves spreadsheet labels, ordering, empty fallback, and validation composition while `EvidenceDropdownModel` and `CodeModel` perform reads.
- `EvidenceTypePolicyService`: preserves type-policy caches and fallback while `CodeModel` and `EvidenceSchemaModel` perform code/schema reads.
- `EvidenceDownloadService`: preserves column selection, workbook formatting, filters, and order while `EvidenceImportModel` and `EvidenceSchemaModel` perform reads.

## 2026-06 Auth

- `ApprovalService`
  - Responsibility: approval token verification, approval request view-data assembly, user approval execution, approval audit logging
  - Controllers: `UserApprovalController`
  - Out of scope: HTML rendering, direct view DB access, approval result page rendering

- `AuthService`
  - Expanded responsibility: login, 2FA verification, password change, password recovery result assembly, and current-user session refresh after password changes
  - Controllers: `LoginController`, `PasswordController`, `TwoFactorController`
  - Out of scope: direct view DB access, HTML rendering

- `PermissionService`
  - Responsibility: permission catalog query, approved/active user and active-role validation, `ROLE`/`EXTEND`/`REPLACE` final effective permission evaluation through `auth_user_permission_profiles`, `auth_user_permissions`, `auth_role_permissions`, request-scope decision caching, permission create/update/delete/reorder orchestration, and route permission registry synchronization through `PermissionModel`
  - Controllers: `PermissionController`, `PasswordController`, `TwoFactorController`, route/middleware permission checks
  - Construction: existing explicit PDO injection remains supported; core middleware may use the optional default connection without acquiring PDO itself
  - Out of scope: direct HTML rendering, role-permission bulk assignment UI state

- `SettingService` / `SessionConfigService`
  - Responsibility: system setting lookup and session timeout/alert setting interpretation through `SettingConfigModel`
  - Callers: settings Controllers, `ConfigHelper`, API access middleware, session Controller
  - Construction: existing explicit PDO injection remains supported; core callers may use the optional default connection
  - Out of scope: direct View DB access, core SQL, arbitrary configuration SQL

- `SettingsNavigationService`
  - Responsibility: settings menu registry query, current-user permission evaluation, and settings View data assembly
  - Controllers: `DashboardController`
  - Out of scope: HTML rendering, direct View DB access, calendar navigation

- `PageRegistryQueryService`
  - Responsibility: page registry lookup for `PageKeyResolver` while preserving resolver cache and legacy key interpretation contracts
  - Callers: `PageKeyResolver`
  - Out of scope: page-key interpretation, legacy alias policy, DB schema change

- `RolePermissionService`
  - Responsibility: reusable Permission Master tree and compact role-selection mapping reads, reorder orchestration, plus differential batch saving of a role's complete selected permission set in one transaction using `auth_role_permissions`, `auth_permissions`, and `system_page_registry`; protects the `super_admin` management permissions, preserves at least one active approved recovery administrator using effective ROLE/EXTEND/REPLACE results, and records before/after sets in the security log

- `UserPermissionService`
  - Responsibility: 사용자별 `ROLE`/`EXTEND`/`REPLACE` Mode와 직접 Permission 전체 Set 조회·차집합 저장, canonical-hash `state_version`, 자기권한·admin·super_admin·권한상승·마지막 복구관리자 Guard, Profile·Mapping·Audit 단일 트랜잭션 처리
  - Models/Repository: `UserPermissionModel`, `UserPermissionRepository`
  - Out of scope: 역할 Permission 저장, 직원 퇴사 시 계정 비활성화, 기간제한 권한
  - Controllers: `RolePermissionController`
  - Batch policy: validates the active role and every active permission ID, computes add/remove differences against current mappings, then uses one bulk insert and one bulk delete at most; it never issues one HTTP request per checkbox.
  - Out of scope: DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `permission-assignment`; runtime compatibility aliases remain `role_permissions`, `role-permission`, and `RolePermission*`

- `TemplateService`
  - Responsibility: approval-template list/save/delete/reorder orchestration for `user_approval_templates`; creates inactive drafts, validates the complete executable step flow and one-active-template-per-document-type policy before activation, guards request dependencies before hard delete, and reorders the locked complete template set with collision-safe sequences
  - Controllers: `ApprovalTemplateController`
  - Out of scope: step persistence, DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `approval-template`; runtime compatibility aliases remain `approval`, `approval/template`, and `TemplateService`

- `TemplateStepService`
  - Responsibility: approval-template step list/save/delete/reorder orchestration, active-template structural edit guard, role/approver assignment and eligibility validation, template-row locking, template-scoped `MAX(sort_no) + 1`, complete step-set validation, and continuous collision-safe renumbering for `user_approval_template_steps`
  - Controllers: `ApprovalTemplateController`
  - Out of scope: template row persistence, DB schema change, direct HTML rendering
  - Domain note: canonical organization domain is `approval-template`; runtime compatibility aliases remain `approval/step` and `TemplateStepService`

## 2026-06 Additions

- `ChartAccountService`
  - Responsibility: chart-account validation, hierarchy policy, transaction boundaries, Actor selection, and orchestration through `ChartAccountModel` and `SubChartAccountModel`
  - Controllers: `ChartAccountController`, `SubChartAccountController`
  - Out of scope: direct SQL, sub-account persistence SQL, Excel parsing

- `CustomSubAccountService`
  - Responsibility: `ledger_accounts_sub`의 계정별 `ref_target` 허용·필수 정책 CRUD, `REF_TARGET` 기준코드 검증, 계정 소유권 검증, `allow_sub_account` 동기화
  - Controllers: `SubChartAccountController`; 전표의 실제 선택값 저장은 `VoucherLineRefService` 책임으로 분리 유지
  - Out of scope: direct SQL, legacy `ref_type` fallback, voucher-line reference persistence

- `ChartAccountReferenceGuardService`
  - Responsibility: 휴지통 계정의 단건·선택·전체 영구삭제 전 실제 물리 참조와 대상 외 하위계정을 일괄 검증
  - Models: `ChartAccountReferenceModel`; 실제 삭제는 `ChartAccountService`의 단일 트랜잭션에서 수행
  - Out of scope: 계정 삭제 SQL, 보조계정 정책 삭제, FK 또는 DB schema 변경

- `EvidenceImportBusinessService`
  - Responsibility: Import own-company profile assembly, client existence lookup, and client company-name update orchestration through CompanyModel and ClientModel
  - Controllers: shared Ledger Import Controller traits
  - Out of scope: HTTP handling, raw SQL, evidence persistence, transaction/voucher creation

- `EvidenceImportBatchService`
  - Responsibility: normalize an Import batch-date key and query deletable evidence source row IDs through EvidenceImportModel
  - Controllers: shared Ledger Import Controller upload trait
  - Out of scope: batch deletion, status transition, transaction handling

- `EvidenceSchemaService`
  - Responsibility: cached evidence/import table and column existence lookup through EvidenceSchemaModel
  - Controllers: shared Ledger Import Controller utility trait
  - Out of scope: schema mutation, arbitrary SQL, user-provided identifier interpolation

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
  - Responsibility: shared DataTable physical-column meta assembly, canonical domain-alias resolution, and table-comment exposure. Composite domains preserve source table, original column, source ordinal, database default, nullable/required state, and a collision-safe public key; the employee domain appends all `user_employees` columns followed by all `auth_users` columns. The `personnel-action` domain exposes the full `institution_personnel_actions` physical header order while replacing approval/original-action FK settings keys with display projections. The `statutory-standard` domain maps directly to final `system_statutory_standards`; related-source metadata maps to `system_statutory_standard_sources`.
  - Controllers: `SystemController`
  - Out of scope: page-specific column rendering, DataTable state persistence, direct modal UI rendering

- `DatabaseReplicationStatusService`
  - Responsibility: replication topology configuration normalization and primary/secondary status interpretation. Fixed server identity and replica status queries are delegated to `DatabaseReplicationStatusModel`.
  - Controllers: `DatabaseActiveController`, `SystemController`
  - Out of scope: backup, restore, synchronization, failover, and arbitrary SQL execution

- `LayoutService`
  - Responsibility: layout UI/session/user/brand data composition. Employee and setting reads are delegated to `EmployeeModel` and `SettingService`.
  - Controllers: `LayoutController`
  - Out of scope: layout persistence, UI rendering, and direct DB access

- `NotificationService`
  - Responsibility: notification validation and orchestration for the navbar feed, stored-notification read-state updates, creation, administrator recipient resolution, and live aggregation of the logged-in user's currently actionable approval steps. Stored persistence is delegated to `NotificationModel`, approval projection reads to `ApprovalInboxModel`, and administrator lookup to `UserModel`.
  - Controllers: `NotificationController`; also used by ledger notification flows
  - Out of scope: notification UI rendering, direct DB access, approval-state persistence, and treating notification read state as approval workflow state

- `JournalRuleService`
  - Responsibility: 분개규칙 CRUD, 코드·계정 유효성, 거래처유형 wildcard, 동일조건 중복·충돌 차단, 정렬, 휴지통, 과거 전표에서 사용된 Rule 영구삭제 방어
  - Controllers: `JournalRuleController`
  - Out of scope: Excel 입출력, 추천 점수 계산, 시스템 Rule 자동생성

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
  - Responsibility: client delete, trash list, restore, dependency-guarded partial-success purge, reorder orchestration including attachment cleanup
  - Purge policy: `ClientDependencyRepository` checks physical and logical ID references immediately before hard delete; referenced clients remain in trash while unreferenced clients are deleted
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: HTML rendering, controller response output

- `ClientExcelService`
  - Expanded responsibility: client template column selection, template/download spreadsheet generation, upload header mapping using label/key/alias rules, and required-column validation. Client/account dropdown reads are delegated to `ClientModel` and `ChartAccountModel`.
  - Controllers: `ClientController` via `ClientService`
  - Out of scope: controller response output, DB schema change

- `ProjectExcelService`
  - Responsibility: project template column selection, template/download spreadsheet generation, upload header mapping using label/key/alias rules, and required-column validation. Client/employee dropdown reads are delegated to their domain Models.
  - Controllers: `ProjectController` via `ProjectService`
  - Out of scope: controller response output, DB schema change

- `ProjectService`
  - Responsibility: project query/save orchestration; payload normalization and business validation delegate to `ProjectPayloadService`, client/employee resolution delegates to `ProjectReferenceResolver`, trash lifecycle delegates to `ProjectTrashService`, and Excel delegates to `ProjectExcelService`
  - Controllers: `ProjectController`
  - Out of scope: direct HTML rendering, controller response output

- `ProjectPayloadService`
  - Responsibility: project request normalization, nullable-field normalization, and Korean business validation; `project_name` remains an invariant business-required field
  - Consumers: `ProjectService`, `ProjectExcelService` through the normal save path
  - Out of scope: DB persistence, user-specific Table Setting storage, controller response output

- `ProjectTrashService`
  - Responsibility: project soft delete, trash list, restore, ordering, and dependency-guarded partial-success purge
  - Purge policy: `ProjectDependencyRepository` checks current physical and logical ID references immediately before hard delete; referenced projects remain in trash
  - Consumers: `ProjectService`
  - Out of scope: controller response output, schema mutation

- `BankAccountService`
  - Responsibility: bank-account CRUD, explicit business validation, reorder, Excel orchestration, commit-safe bank-copy lifecycle, and dependency-guarded partial-success purge
  - Repository: `BankAccountDependencyRepository` checks cards, client default accounts, payment schedules, evidence bodies, transactions, voucher summaries, and polymorphic voucher-line account references immediately before hard delete; referenced accounts remain in trash
  - File policy: new upload is compensating-deleted when DB save fails; replaced or purged files are deleted only after DB commit
  - Controllers: `BankAccountController`
  - Out of scope: direct HTML rendering, controller response output, runtime schema mutation

- `BrandService`
  - Responsibility: brand asset list/detail/save/status/purge orchestration for the settings brand page
  - Controllers: `BrandController`
  - Out of scope: direct HTML rendering, controller response output

- `CoverService`
  - Responsibility: cover list/public-list/save/delete/trash/reorder orchestration for `cover` domain, with legacy `CoverImageService` isolated as compatibility wrapper
  - Controllers: `CoverController`, `AboutController`
  - Out of scope: direct HTML rendering, controller response output

- `WorkTeamService`
  - Responsibility: work-team list/detail/save/delete/trash/reorder/excel orchestration, shared modal/Excel save validation, and transaction-safe dependency-guarded partial-success purge; team-leader and Excel dropdown reads are delegated to `ClientModel`
  - Repository: `WorkTeamDependencyRepository` checks member, evidence, transaction, and polymorphic voucher-line TEAM references by team ID immediately before hard delete; referenced teams remain in trash
  - Purge policy: only rows with `deleted_at IS NOT NULL` can be hard-deleted; bulk purge classifies blocked rows before deleting eligible rows in one transaction
  - Controllers: `WorkTeamController`
  - Out of scope: direct HTML rendering, controller response output

- `CardService`
  - Responsibility: card list/detail/save/delete/trash/reorder/excel orchestration, DB-column-aligned business validation, commit-safe card-image lifecycle, and dependency-guarded partial-success purge; client and bank-account dropdown reads are delegated to their Models
  - Repository: `CardDependencyRepository` checks evidence bodies, transactions, voucher summaries, and polymorphic voucher-line card references by card ID immediately before hard delete; referenced cards remain in trash
  - File policy: new uploads are compensating-deleted when DB save fails; replaced, explicitly removed, or purged images are deleted only after DB commit
  - Controllers: `CardController`
  - Out of scope: direct SQL, card-number logging, controller response output

- `ProjectReferenceResolver`
  - Responsibility: preserve client/employee name normalization and nullable resolution contracts used by project import while delegating DB lookup to `ClientModel` and `EmployeeModel`
  - Consumers: `ProjectService`
  - Out of scope: direct PDO/SQL, project persistence, generic master-data lookup

- `EmployeeService`
  - Responsibility: employee list/detail/search/save/status/delete/reorder orchestration across employee SSOT `user_employees` and login-account SSOT `auth_users`, including representative qualification registration/update/delete, qualification/education count summaries, and server-side enforcement of the current user's employee Table Setting required-field policy. The representative qualification data remains in `institution_qualifications_employee_records`; `user_employees` stores only its connection ID. Employees are referenced by `employee_id`; the Service does not resolve, validate, or persist a client link because `system_clients` is an independent client SSOT.
  - Controllers: `EmployeeController`
  - Out of scope: direct HTML rendering, controller response output
  - Domain note: canonical organization domain is `employee`; runtime compatibility alias remains `employees`

- `DepartmentService`
  - Responsibility: department list/detail/save/reorder orchestration, DB-bound and user Table Setting required-field validation, selectable `auth_users.id` manager validation, and guarded hard delete through `DepartmentDependencyRepository`. A department referenced by current employees, assignment histories, personnel-action changes, employment-rule ownership, or employment-rule scopes cannot be deleted; organizational closure uses `is_active = 0`.
  - Controllers: `DepartmentController`
  - Out of scope: direct HTML rendering, controller response output, schema changes, soft delete/trash
  - Domain note: canonical organization domain is `department`; runtime compatibility aliases remain `departments` and `dept`

- `PositionService`
  - Responsibility: integrated position/title master list/detail/save/reorder orchestration, DB-bound and user Table Setting required-field validation, and guarded hard delete through `PositionDependencyRepository`. A row referenced by current employees, position histories, personnel-action changes, or employment-rule scopes cannot be deleted; discontinued use is represented by `is_active = 0`.
  - Controllers: `PositionController`
  - Out of scope: direct HTML rendering, controller response output, schema changes, soft delete/trash, independent grade/title domain design
  - Domain note: canonical organization domain is `position`; runtime compatibility aliases remain `positions` and `positions_modal`

- `RoleService`
  - Responsibility: `auth_roles` list/detail/save/reorder, protected-role policy, Table Setting required-field validation, dependency-guarded transactional hard delete of role permissions and the role
  - Controllers: `RoleController`
  - Out of scope: direct HTML rendering, controller response output, schema changes, soft delete/trash
  - Domain note: canonical organization domain is `role`; `super_admin` and `admin` are protected, and runtime compatibility is limited to the plural URL redirect

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
  - Controllers: `EvidenceController`, `EvidenceImportController`, `EvidenceLifecycleController`, `EvidenceListController`, `EvidenceStatusController`, `EvidenceUploadController`
  - Out of scope: page ready/planned rollout policy, field/meta domain policy, excel manager domain policy, field alias/display policy, modal preset policy, processing plan policy, upload save, voucher create, DB schema change
- `SystemFieldService`
  - Responsibility: body-table physical column discovery, data-type to target-table resolution, source field option generation, field ordering/default visibility/default required policy generation, field-group metadata assembly for table setting, excel upload/download, and modal rendering preparation
  - Controllers: `EvidenceImportController`, `EvidenceUploadController`
  - Out of scope: import/source type normalization, runtime page-ready policy, processing plan, badge/color/icon UI policy, DB schema change
- The deprecated processing-policy compatibility service was removed after Body reads moved to `EvidenceBodyStatusProjectionModel` and `body.evidence_status`.
  - Responsibility: legacy compatibility boundary while generation-processing state is retired; Body reads must derive transaction/voucher linkage from `ledger_evidence_links` and must not restore a separate processing table.
  - Controllers: none directly; used by `EvidenceGenerationService`
  - Out of scope: processing-state persistence, import/source type normalization, field/meta generation, upload/save/delete orchestration, and DB schema change
- `EvidenceExternalKeyService`
  - Responsibility: canonical external-source normalization and deterministic `external_key` generation for every evidence upload type. It uses stable provider identifiers first and otherwise hashes an ordered allowlist of raw source fields; ERP IDs, sort/order, file metadata, Actor, processing state, links, and corrected business references are excluded.
  - Controllers: none; used by `EvidenceUploadService`
  - Out of scope: DB reads/writes, duplicate decisions, upload counters, backfill, and UI messages- `EvidenceUploadService`
  - Expanded responsibility: upload runtime/cancel/trace, preview session store/load/clear, file column validation/header-only read, deterministic external-key build through `EvidenceExternalKeyService`, duplicate annotation, required-missing summary/confirmation, upload source key policy, preview confirm orchestration, upload file path orchestration, trace payload build, validation response build, preview confirm response build, chunk upload progress/result build. Duplicate and batch reads delegate to `EvidenceBodyStorageModel` over the type-specific Evidence Body SSOT.
  - Controllers: `ImportController`
  - Out of scope: transaction create, voucher create, DB schema change
- `EvidenceUploadParserService`
  - Responsibility: spreadsheet load, upload row parse, bank workbook parse, upload header/column mapping, upload payload key resolve
  - Controllers: `ImportController`
  - Out of scope: upload batch save, preview validation, transaction create, voucher create
- `EvidenceBatchSaveService`
  - Responsibility: upload batch save helper, Excel-upload validation result handling, TABLE requirement-policy based `evidence_status` preparation, duplicate/deleted-duplicate/conflict counters and details, payload build, persist parameter assembly, per-Body `sort_no` allocation, and chunk commit
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
  - Responsibility: evidence status/readiness interpretation and canonical `(import_type, evidence_id)` active-link detection through `EvidenceLinkModel`
  - Controllers: `ImportController`
  - Out of scope: payload helper, link helper, sort helper, DB schema change
- `VoucherQueryService`
  - Responsibility: voucher list/detail/trash/picker result assembly, `전표검토·전기` server-side page/count orchestration, direct `linked_evidences` reads, and reversal-only read-only `original_linked_evidences` projection through `reversal_of`
  - Controllers: `VoucherController`
  - Out of scope: HTTP handling, voucher state transitions, persistence orchestration, direct SQL, DB schema change

- `EvidencePayloadHelperService`
  - Responsibility: evidence payload scalar normalization, storage JSON encode helper, seed row id extraction, evidence total amount calculation, blank value detection
  - Controllers: `ImportController`
  - Out of scope: link helper, sort helper, DB schema change
- `EvidenceDeleteRestoreService`
  - Responsibility: 자료유형별 Evidence Body의 소프트삭제·복구 DB 변경을 담당한다. 처리상태 테이블이나 링크 삭제는 수행하지 않는다.
  - Responsibility: evidence soft delete helper, evidence restore helper, evidence body delete/restore helper
  - Controllers: `ImportController`
  - Out of scope: evidence lifecycle purge orchestration, bundled voucher, upload save, DB schema change
- `EvidenceLifecycleService`
  - Responsibility: 삭제된 Body의 영구삭제를 조정하며 canonical `(import_type, evidence_id)` 링크와 분할 계보가 없을 때만 수행한다.
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
  - Responsibility: upload batch persistence entrypoint, type-specific Body Model-call orchestration, upload transaction boundary, file-internal/DB/concurrent duplicate skipping, and batch result assembly. Existing rows are never updated by upload when `external_key` already exists. Upload acceptance remains governed by `EXCEL_UPLOAD`, while the persisted Body `evidence_status` is calculated independently from the current evidence-type TABLE requirement policy. SQL execution delegates to `EvidenceBodyStorageModel`, `EvidenceSchemaModel`, and the type-specific Evidence Models on the same PDO transaction connection; removed integrated-source and generation-processing tables are not fallback targets.
  - Controllers: `EvidenceUploadController`
  - Out of scope: upload file parse, preview validation, request payload collection, DB schema change
- `EvidenceLinkHelperService`
  - Responsibility: `EvidenceLinkModel`을 통해 canonical 증빙 Identity의 링크 존재 확인과 영구삭제 의존성 정리를 제공한다. 소프트삭제 시 링크는 유지한다.
  - Responsibility: evidence link purge helper, evidence source reference detach, evidence link soft-delete/delete helper
  - Controllers: `ImportController`
  - Out of scope: upload helper, transaction helper, voucher helper, DB schema change
- `EvidenceSortHelperService`
  - Responsibility: per-Body `sort_no` payload value helper and compatibility no-op for existing sort-column initialization calls
  - Controllers: `ImportController`
  - Out of scope: payload helper, link helper, DB schema change
## Legacy Ledger Evidence Service Notes

This section preserves the older evidence-service split reference in readable form until the active service inventory above fully replaces it.

| Service | Responsibility | Used By | Out of Scope |
| --- | --- | --- | --- |
| EvidencePayloadService | Owns evidence payload read/save, payload normalization entrypoints, and payload-oriented lookup helpers. | `ImportController`, `EvidencePayloadController` | Readiness, transaction creation, voucher creation, and DB schema change |
| EvidenceStatusService | Owns final Body `evidence_status` (`COMPLETED`, `CORRECTION_REQUIRED`) changes and type-specific Body `sort_no` reorder. Linked evidence is protected from status mutation. | `EvidenceStatusController` | Processing/generation state persistence, payload save orchestration, transaction/voucher creation, and DB schema change |
| EvidenceTrashService | Owns Body trash, restore, and purge orchestration. Soft delete retains `ledger_evidence_links`; linked evidence is blocked, and purge accepts deleted Body rows only. | `EvidenceLifecycleController` | Upload parse/save, processing-state persistence, transaction/voucher creation, and DB schema change |
| EvidenceUploadService | Owns upload flow orchestration, parsed row handling, and upload batch save coordination. | `ImportController`, `EvidenceUploadController` | Transaction creation, voucher creation, and DB schema change |
| EvidenceGenerationService | Owns evidence-original runtime read orchestration, body-row assembly, and shared Actor display-name enrichment (`created_by_name`, `updated_by_name`, `deleted_by_name`). Transaction/voucher linkage is projected only from `ledger_evidence_links`. | `EvidenceListController`, `EvidenceLifecycleController` | Type policy, field meta, upload/save/delete orchestration, transaction/voucher creation, and DB schema change |
| EvidenceBodyReadService | Owns the canonical supported body-reader list and body-table read/count orchestration, including zero-count results for supported types such as canonical `PAYROLL`; delegates concrete SQL access to domain-specific read models. | `EvidenceGenerationService` | Direct SQL ownership, type normalization policy, page policy, field meta policy, upload/save/delete flows, UI policy, and DB schema change |
| EvidenceSourceRepository | Metadata-allowlisted integrated evidence-source repository. Builds server-side DataTable identity counts, filtered counts and bounded page projections, validates requested projection/search/sort fields against actual source-table columns, and batch-loads body/reference/link data for the current page. | `EvidenceGenerationService`, evidence reference consumers | Type normalization policy, UI policy, writes, schema mutation, permission decisions |
| EvidencePayloadNormalizeService | Owns payload value normalization, field-level cleanup, and format-column-based payload normalization helpers. | `ImportController`, `EvidenceGenerationSaveService`, `EvidenceGenerationService`, `EvidenceUploadService` | Readiness, reference resolution, transaction helpers, and voucher orchestration |
| EvidenceRuleEngineService | Owns evidence required-field validation and evidence-state result assembly. | `ImportController`, `EvidenceGenerationService` | Reference resolution, transaction creation, and payload normalization |
| EvidenceTypePolicyService | Owns import/source type normalization, legacy alias resolution, upload/business allow lists, source/import query helper policy, transaction direction policy, manual tax invoice type detection, and the active evidence-type UI policy consumed by the original evidence editor. | `EvidenceController`, `JournalController`, `EvidenceImportController`, `EvidenceLifecycleController`, `EvidenceListController`, `EvidenceStatusController`, `EvidenceUploadController`, `EvidenceGenerationService`, `EvidenceStatusService`, `EvidenceTrashService` | Field/meta domain policy, payload save, transaction/voucher creation, and DB schema change |
| SystemFieldService | Owns body-table physical-column metadata lookup, data-type target-table resolution, source-column option generation, field-group ordering/default visibility/default required policy assembly, and the field-meta baseline that table settings, excel upload/download, and detail modal can converge on. Runtime default field-meta generation must start from `targetTableForDataType() -> information_schema.COLUMNS`; transitional `mapped_payload_json` helpers remain classified as legacy and are not the default path. | `EvidenceImportController`, `EvidenceUploadController`, `EvidenceDownloadService`, `EvidenceListController`, `EvidenceLifecycleController`, `EvidenceSaveController` | Import/source type normalization, UI policy, and DB schema change |
| EvidenceGenerationSaveService | 기존 API/DI 명칭을 유지하는 Body 저장 호환 진입점이다. 자료유형별 Body 저장과 canonical 링크 잠금만 조정하며 payload/processing 저장소를 사용하지 않는다. 단건 생성·수정 시 테이블설정의 필수구분 정책과 저장될 전체 Body 값을 대조해 `COMPLETED` 또는 `CORRECTION_REQUIRED`를 기록한다. 저장과 저장 결과 확인은 하나의 트랜잭션으로 묶어 실패 응답 시 Body 행이 남지 않게 하며, 새 증빙 요청의 RFC 4122 v4 UUID를 재사용해 동일 모달의 재시도를 멱등 처리한다. | `EvidenceSaveController` | Generation Center 상태, transaction/voucher 생성, DB 직접 SQL |
| JournalCandidateEngineService | Owns journal candidate collection from journal rules, recent use, learning events, client patterns, and client default accounts; canonical account-set merge; request-scope candidate-source cache reuse; score calculation; deterministic ranking; balanced-line construction; and Top-N response assembly. It does not select a candidate for the user or write learning data. | Voucher evidence recommendation flow | Candidate-source SQL ownership, learning writes, voucher persistence, workflow state, and UI candidate selection |
| VoucherEvidenceRecommendationService | Builds canonical recommendation contexts from all evidence currently linked in the voucher modal, recognizes a uniquely matching DATA/FUND pair, invokes `JournalCandidateEngineService`, and assembles two or three selectable voucher-level recommendation sets. It returns per-evidence `connection_status`, `recommendation_status`, and `reason_code`, so a valid evidence link remains allowed when amount, bank-account mapping, counterpart account, or a complete journal rule is unavailable. For every recommended account it reads `ledger_accounts_sub` policy and maps unambiguous Evidence SSOT base references (`client`, `project`, `bank-account`, `card`, `work-team`, `employee`) into canonical `line.refs[]`. It never applies a set or persists temporary selection. | `VoucherController::apiEvidenceRecommendations()` | Journal rule CRUD, user candidate selection, voucher persistence, evidence-link persistence, and temporary UI state |
| JournalRuleCandidateProvider | Produces unscored debit/credit/VAT account-set candidates from active `ledger_journal_rules` rows matching the current business context. | `JournalCandidateEngineService` | Candidate merge, scoring, ranking, learning writes, and persistence |
| JournalClientPatternCandidateProvider | Produces unscored debit/credit candidates from the logical client's existing direction/line patterns and, when configured, combines the client master `default_account_id` with recent opposite-side accounts. | `JournalCandidateEngineService` | Candidate merge, scoring, ranking, learning writes, and persistence |
| JournalRecentPatternCandidateProvider | Produces unscored account-set candidates from existing recent confirmed journal patterns, retaining usage, recency, client, and project evidence as metrics only. | `JournalCandidateEngineService` | Candidate merge, scoring, ranking, learning writes, and persistence |
| JournalLearningCandidateProvider | Produces unscored final-account candidates from existing successful learning events and exposes accepted/modified event metrics without updating learning tables. | `JournalCandidateEngineService` | Candidate merge, scoring, ranking, learning writes, and persistence |
| JournalLearningFeedbackService | 최초 POSTED 전표의 확정 라인을 서버 SSOT에서 재구성해 멱등 Event로 기록하고, Event 전체 aggregate로 Recent/Client Projection을 deterministic 재계산한다. 단일 Evidence Context 또는 공통 Line Ref가 명확할 때만 Context Projection을 만든다. | `VoucherService` POSTED transition, rollback 검증 tool | 추천 GET 쓰기, Rule usage/confidence, SYSTEM Rule 생성, 거래↔전표 직접 연결 |
| JournalLearningFeedbackRepository | `ledger_journal_learning_events` Event insert/duplicate 판정과 Recent/Client Projection upsert SQL을 소유한다. | `JournalLearningFeedbackService` | Context 판정, workflow, 사용자 응답, Rule usage |
| EvidenceBodyWriteService | Owns metadata-compatible evidence Body payload mapping, Body Model dispatch, persistence verification, and physical-column filtering through `information_schema.COLUMNS`. It enforces server-managed canonical identity columns for manual tax invoices (`source_type=MANUAL`, `import_type=TAX_INVOICE_MANUAL`) for both modal and Excel-upload writes, regardless of client payload values. It does not expose a legacy synchronization API or persist a second evidence SSOT. | `EvidenceGenerationSaveService`, `EvidenceUploadPersistService` | Evidence workflow state, recommendation state, transaction/voucher creation, and controller request handling |
| VoucherCreateService | 은행 증빙 전표라인을 정규화하고 기존 전표·중복 지문을 Model로 확인한 뒤 `VoucherService` 저장 흐름을 호출한다. 기존 전표 자동연결은 canonical `(import_type, evidence_id)`로 `EvidenceLinkModel`에 위임한다. | Evidence import/save controllers | SQL 실행, 범용 링크 저장, processing 상태 |

## Legacy Service Principles

- Controllers collect requests, delegate orchestration to services, and return responses.
- Shared helpers should stay focused on reusable domain behavior rather than controller callbacks.
- Each service should own one business flow clearly enough that state transitions and side effects remain traceable.
- If a service split changes architecture decisions, record the reason in `DecisionLog.md`.
## 2026-06-27 Addendum

| Service | Responsibility | Used By | Notes |
| --- | --- | --- | --- |
| VoucherService | 전표·분개·보조계정·증빙링크 저장과 상태 workflow 및 소프트삭제·복원을 조정한다. SQL은 Voucher/Line/LineRef/EvidenceLink 및 참조 Model에 위임한다. REVIEWED·POSTED 전환은 전표 상태, 분개 READY/POSTED, 차변·대변 균형을 검증하며 거래 존재 여부를 요구하지 않는다. 취소전표는 Header·Line·Line Ref와 `reversal_of`를 생성하되 원전표 Evidence 링크는 복제하지 않는다. | VoucherController, VoucherCreateService callbacks | 거래 직접 FK 조회 및 동일 증빙의 거래 연결 강제 금지. `linked_evidences[]`와 `line.refs[].ref_target`이 runtime 계약이다. 소프트삭제·복원은 링크를 유지한다. 원전표 증빙은 Query Service가 읽기 전용으로 제공한다. |
| VoucherPostingValidationService | POSTED 직전 전표 라인의 계정 사용 가능 여부, Type별 Ref Master, 연결 Evidence Body 유효성을 Set 기반으로 재검증한다. Evidence 0건과 거래 직접 Link 0건은 허용한다. | VoucherService REVIEWED→POSTED transition | 상태전이·후속처리는 담당하지 않으며 Posting readiness만 검증한다. |
| VoucherPurgeService | 단건·선택·전체 전표 영구삭제의 트랜잭션을 소유한다. 전달받은 ID 전체가 실제 휴지통 상태인지 재검증하고 ref→line→VOUCHER link→header 순서로 삭제하며, 하나라도 실패하면 전체 롤백한다. | VoucherController | Controller 트랜잭션, 중첩 트랜잭션, 소프트삭제·복원, 거래 영구삭제 |
| VoucherStatus | Defines the canonical voucher workflow values, transitions, normalization, editable/locked groups, picker values, and the review-list status set `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`. | VoucherController, VoucherService, VoucherModel, VoucherReviewQueryModel | Keeps status filtering and workflow decisions on the shared status SSOT; `CLOSED` is reserved for the future accounting-close policy and is excluded from the current review list. |
| VoucherLineRefService | Owns voucher-line ref replace, detail hydration, and validation-shape reconstruction for voucher `line.refs[]` using `ledger_voucher_line_refs`. | VoucherController, VoucherService | Keeps voucher controller/service code focused on voucher orchestration while centralizing voucher multi-ref read/write mapping in one voucher-only service. |
| VoucherNumberService | 전표번호 형식·수정 가능 여부·중복 방지 정책과 트랜잭션을 소유하며 조회·중복검사·번호 갱신은 `VoucherModel`에 위임한다. | VoucherController | 현행 날짜별 `MAX 성격의 최신번호 + 1` 방식과 반환 계약을 유지하며 별도 번호이력 테이블은 사용하지 않는다. |
| VoucherPolicyService | 증빙 기반 라인 보조계정 보강과 필수 `ref_target` 정책을 판정한다. 계정과 정책 조회는 `ChartAccountModel`, `SubAccountPolicyModel`에 위임한다. | Evidence import/save controllers | SQL 실행, `ref_type` fallback, 업무 데이터 저장 |
| TransactionCrudService | Owns transaction validation and atomic header/item/settlement/evidence-link orchestration, file flow, active transaction list ordering, trash/restore/purge policy, and header total recomputation. It joins an existing PDO transaction without committing or rolling it back, allowing final approval to atomically include the transaction flow. `TransactionModel`, `TransactionItemModel`, `TransactionSettlementModel`, and `EvidenceLinkModel` own every SQL operation. List ordering validates unique transaction IDs and positive `sort_no` values, then updates active rows transactionally. Soft delete and restore retain evidence links; purge accepts deleted transactions only and removes items, settlements, TRANSACTION target links, and the header in one transaction. Application conflict checks are backed by the DB conditional unique key for active TRANSACTION evidence, and DB duplicate races are normalized to `이미 다른 거래에 연결된 증빙입니다.`. Settlement ownership is backed by the header FK cascade. | TransactionController, VoucherController | Transaction evidence identity uses the Evidence Body ID; the transaction header has no evidence identity column, and transaction-to-voucher direct linking is outside this service. |
| TransactionEvidenceReferenceService | Projects transaction-connectable `DATA`/`BOTH` Evidence SSOT rows for selection and detail restoration, and converts evidence policy semantics into independently applicable business-classification, transaction-overview, one-unit pretax item, and directional settlement recommendation candidates. Settlement candidates must resolve to an active `SETTLEMENT_TYPE` code; their descriptions use the evidence policy `DESCRIPTION` value rather than physical source-column names. Each line candidate carries the canonical `(import_type, evidence_id)` source identity; conflicting scalar candidates remain selectable review data and are never chosen automatically. | TransactionController, TransactionCrudService, transaction recommendation card | Does not apply recommendations, save transactions or links, expose `FUND` evidence, or own evidence validation, recommendation-history persistence, and metadata persistence. |
| TransactionReferenceValidatorService | 거래 및 전표 Posting의 Master ID와 코드 필드를 SSOT 기준으로 검증한다. `validateGroupedIds()`는 Ref Type별 ID Set을 한 번의 Master 조회로 검증한다. | TransactionCrudService, VoucherPostingValidationService | 거래처·프로젝트·직원·계좌·카드·팀의 공용 서버 검증 책임이다. |
| EvidenceMetadataService | Owns the Evidence Metadata Registry and Semantic Mapping contract: header validation and CRUD, read-only health projection, centralized delete/purge reference guards, atomic restore conflict checks, transactional row ordering, schema-based recommendations, physical-column validation, duplicate prevention, and Actor enrichment. The controlled basis policy includes `BASE_DATE`, `DESCRIPTION`, amount meanings, and repeatable `ADJUST_AMOUNT`. | EvidenceMetadataController | Metadata supplies the body location, DATA/FUND/BOTH usage area, and semantic values; it does not select transaction handlers or journal accounts. Active runtime types and rows referenced by Evidence Body or `ledger_evidence_links` cannot be deleted. Header and detail saves are transactional. |
| UserSettingService | Owns per-user page setting detail/save/delete orchestration for `system_user_settings`, including page-key and setting-type validation, current-user scoping, `exists` response metadata, and DB-backed persistence for `TABLE`, `VIEW`, `EXCEL_UPLOAD`, and `EXCEL_DOWNLOAD`. The service stays storage-type agnostic while the shared client bridge decides when a single UI state should be split across separate `TABLE` and `VIEW` rows. | UserSettingController | Keeps browser-storage replacement logic out of page JS while leaving screen-specific setting-key selection to common client modules. |

## Personal expense approval

- `ApprovalInboxService`
  - Responsibility: authenticated-user approval-box classification, authorized document detail assembly, current-request and immutable resubmission-history projection, actionable-step policy, mandatory rejection reason validation, and document-type action delegation. `recordsTotal` is the box/user scope before keyword search and `recordsFiltered` is the same scope after search. Count uses a display-independent access projection, while history loads every related request step once and groups it by `request_id`.
  - Controller: `ApprovalInboxController`
  - Model: `ApprovalInboxModel`
  - Amount projection: 근로계약 문서의 공용 `total_amount`는 `institution_employment_contracts_components` 활성 행의 `SUM(amount)`를 사전 집계한 월 지급합계다. 결재단계 JOIN과 분리하여 행 증폭을 방지하고 결재함 전체 구분에서 동일 projection을 사용한다.
  - Current document adapter: `PERSONAL_EXPENSE` delegates mutations to `PersonalExpenseApprovalService`; future document types add an adapter without duplicating request/step workflow.
  - 역할 공동결재 단계는 활성·승인·재직 상태이며 활성 역할을 가진 사용자에게만 노출하고, 처리 시 원자적 조건 갱신으로 동시 승인을 한 명으로 제한한다.
- `PersonalExpenseService`
  - Responsibility: validates and saves employee-owned personal-expense headers/items and recalculates the four stored header aggregates from active items in the same transaction after every supported item mutation. Submission validation locks active items, recalculates the header, requires at least one active item, and verifies persisted values before workflow processing. Personal-expense list rows also receive the status-aware virtual approval projection (`approval_stage_name`, `approval_actor_name`, `approval_actor_type`, `approval_action_result`): only pending/in-progress work exposes the actual current step name, all draft and terminal states expose `-`, pending work uses the assigned approver/role, approved or rejected work uses the latest actual action, and withdrawn/cancelled work uses the request actor and timestamp without falling back to a stale approver. Detail timelines separately project scheduled users for waiting/pending steps, actual `acted_by` users for approved/rejected steps, no actor fallback for cancelled steps, and request-level withdrawal/cancellation actors and timestamps.
  - Responsibility: current-login employee resolution, header-based owner-scoped search/list/detail, atomic header and multi-item create/update/delete synchronization, owner-scoped trash listing and restore, guarded transactional item-first permanent deletion, server-side amount recalculation, active code resolution, employee-scoped header sequence allocation and row reordering, and document-local item ordering.
  - Controller: `PersonalExpenseController`
  - Models: `PersonalExpenseModel`, `PersonalExpenseItemModel`, `EmployeeModel`, `ProjectModel`, `CodeModel`
  - Performance: 일반 목록은 서버 페이징 최대 500건으로 제한하고 필터 건수는 헤더 테이블만 조회한다. 결재 처리자 상관 조회를 윈도 함수 파생테이블로 바꾸는 안은 운영 데이터 실측에서 더 느려 기존 요청별 인덱스 조회를 유지한다. 휴지통 아이템은 `listForHeaders()` 한 번으로 일괄 조회한 뒤 신청서 ID로 그룹화한다.
  - Delete policy: soft delete is limited to draft/rejected/withdrawn current requests. Purge requires a trashed header, no pending/in-progress/approved approval history, no generated evidence reference, and deletes all items before the header while preserving non-final approval audit rows.
- `PersonalExpenseApprovalService`
  - 개인경비 신청서 상신과 결재 상태 전환을 담당한다. 최종승인 시 신청 아이템마다 증빙원본 1건, 거래헤더 1건, 거래내역 1건, 필요 시 VAT 정산 1건과 증빙–거래 링크 1건을 동일 DB 트랜잭션에서 생성한다.
  - Responsibility: active `PERSONAL_EXPENSE` template resolution, immutable header-level request/step snapshot creation, header status-projection and current-request-pointer synchronization, approver resolution, locked approve/reject/withdraw transitions, resubmission history preservation, and atomic final approval that creates one evidence and one independent transaction per item with merchant-client updates and shared evidence links.
  - Controllers: `PersonalExpenseController`, `ApprovalInboxController` through `ApprovalInboxService`
  - Final approval: creates one evidence and one independent transaction with one transaction item per application item, resolves external keys through `EvidenceExternalKeyService`, resolves new merchants through `EvidenceClientSyncService`, and links each evidence to its transaction without creating a voucher.
  - SSOT: `approval_personal_expenses`, `approval_personal_expense_items`, `user_approval_requests`, `user_approval_request_steps`, `ledger_evidence_employee_personal_expense`, `ledger_evidence_links`

## Employment contract approval

- `ApprovalWorkflowService`
  - Responsibility: 문서유형 공통 결재템플릿 해석, 불변 요청·단계 스냅샷 생성, 지정결재자·역할 적격성 검증, 승인·반려·회수 및 다음 단계 활성화를 담당한다.
  - SSOT: `user_approval_requests`, `user_approval_request_steps`
  - Out of scope: 업무문서 검증·상태 projection·최종승인 후속처리
  - Transaction contract: Workflow는 독립 트랜잭션을 소유하지 않는다. Adapter의 승인·반려 callback과 최종승인 후속처리는 반드시 도메인 Service가 소유한 동일 트랜잭션에서 실행한다.
- `ApprovalDocumentAdapterRegistry`
  - Responsibility: 결재함의 문서유형별 상세 projection과 mutation 위임 경계를 등록하고 표시명·상세 섹션·아이템/합계 필드·첨부 지원·최종승인 확인문으로 제한된 UI metadata를 제공한다.
  - Adapters: `PersonalExpenseApprovalAdapter`, `EmploymentContractApprovalAdapter`, `PersonnelActionApprovalAdapter`, `LeaveApprovalAdapter`, `EmploymentRuleApprovalAdapter`, `RegularEmploymentIncomeApprovalAdapter`
  - Rule: 결재함에 문서유형 하드코딩 분기를 추가하지 않고 Adapter를 등록한다.
- `EmploymentContractService`
  - Responsibility: 실제 코드관리 SSOT에 따른 근로계약 Validation, 명시적 목록 projection과 검색 전/후 건수, 공용 목록 순서변경, Modal 최초 개방 시 입력정책·급여항목 option 조회, 계약 헤더·요일별 소정근로 일정·계약 당시 지급조건의 원자적 저장·상세조회·작성중 수정, 결재상태 projection, 종료·해지, 소프트삭제·복원·안전한 완전삭제를 조정한다. 계약기간은 `EMPLOYMENT_CONTRACT_PERIOD_TYPE`, 고용구분은 `EMPLOYMENT_CATEGORY`, 근로시간은 `EMPLOYMENT_WORKING_TIME_TYPE`을 SSOT로 사용한다. `contract_period_type = FIXED_TERM`일 때만 종료일과 기간제 사유를 저장하고, `INDEFINITE`는 해당 값을 `NULL`로 정규화한다. 기간제는 종료일을 필수로 하며 사유별 상세·프로젝트 조건, `REVIEW_REQUIRED` 결재 차단 및 일반 기간제 계속근로 2년 초과 결재 차단을 담당한다. 결재요청 직전 활성 지급조건과 0원 초과 월 지급합계를 다시 검증한다.
- `EmploymentContractValidityService`
  - Responsibility: 승인 계약의 기준일·기간 유효성, 종료일 경계, 종료 처리일, 개정 계보의 최신본 선택을 단일 SSOT로 해석한다. 결재요청과 최종 승인 직전에는 직원별 계약행을 잠그고 서로 다른 계약 계보의 기간 중복을 차단하며, 근태·휴가·상용근로소득이 동일 Resolver 결과를 사용한다.
- `EmploymentContractAuditService`
  - Responsibility: 계약 헤더·요일별 일정과 휴게구간·비고정 근무정책·지급조건을 업무 필드 중심 Snapshot으로 구성하고, Actor·사유·결재요청·요청키와 함께 불변 감사 원장에 멱등 기록한다. 계약 ID 기준 감사이력 조회를 제공하며 SQL 저장은 `EmploymentContractAuditModel`에 위임한다.
  - Models: `EmploymentContractModel`, `EmploymentContractWeeklyScheduleModel`, `EmploymentContractWorkSchedulePolicyModel`, `EmploymentContractComponentModel`, `PayComponentModel`
  - `NORMAL`·`NIGHT`는 주간 반복 일정만, `SELECTIVE`·`SHIFT`는 계약당 1행의 비고정 근무정책만 사용한다. `FLEXIBLE`·`OTHER`는 기준 반복일정과 추가 정책을 함께 저장한다. 근무형태는 계약 헤더만 SSOT이며 정책행에는 유형을 중복 저장하지 않는다. 주간·월평균·통상임금 기준시간은 요일별 일정에서 조회 시 파생한다.
  - 지급조건 Grid는 `quantity`, `rate`, 선택적 `premium_rate`, 확정 `amount`를 계산 SSOT로 사용한다. 기본급·연차수당은 수량과 기준단가, 근로수당은 수량·기준단가·가산배율로 계산하고 문자열 산식은 저장하지 않는다. 지급합계는 활성 components의 `amount` 합계로 파생한다.
  - Approval: `ApprovalWorkflowService`를 사용하며 승인된 계약은 직접 수정하지 않는다.
  - Attachment boundary: 공용 파일 메타데이터 구조가 사용자 직접 DB 생성 절차로 확정될 때까지 첨부 기능을 구현하지 않는다.
  - 발의자는 `requester_id`/`requested_at`, 실제 결재 처리자는 단계의 `acted_by`/`action_at`을 사용한다. `SUBMIT`은 상신 즉시 완료하고 첫 실제 승인단계만 `pending`으로 활성화한다.
  - 역할 단계는 첫 사용자를 자동배정하지 않으며 `approver_id = NULL`인 불변 스냅샷으로 생성한다. 최종승인은 기존 증빙·거래·VAT·링크 생성 진입점을 재사용한다.
  - 상신과 재상신은 동일한 `submit()` 요청 생성 경로를 사용한다. 템플릿 첫 단계는 `SUBMIT`으로 정규화되어 현재 로그인 사용자가 즉시 `acted_by`로 완료하며, 직접 삭제로 남은 신청서의 고아 요청 포인터는 새 요청 생성 트랜잭션 안에서 정리한다.
  - Attachment boundary: document attachments are not handled until a shared business-document attachment store exists.
- `PersonalExpenseExcelService`
  - Responsibility: shared Excel Manager domain adapter for the personal-expense item grid. It creates the 23-field item template/download workbook through `ExcelValueFormatterHelper`, parses uploads, strips all injected IDs, and delegates code/reference/amount validation to `PersonalExpenseService`. Upload returns replacement grid rows only and never persists an application or registers a merchant client.
  - Controller: `PersonalExpenseController`
  - Settings: page key `approval.personal_expense`, metadata domain `personal-expense-item`, keys `excel.template.personal-expense-item.v1` (`EXCEL_UPLOAD`) and `excel.download.personal-expense-item.v1` (`EXCEL_DOWNLOAD`).
- `DailyFundsReportService`
  - `InternalTransferRepository`가 확정한 기존 증빙·전표 SSOT 관계만 내부입금·내부출금으로 분리하고 미확정 후보는 일반 입출금에 유지한다.
  - Responsibility: 기준일 은행·현금 자금수단의 전일잔액, 당일 입출금, 마감잔액, 미연결 요약, 지급예정 잔여액과 보고서 엑셀을 기존 은행 증빙·계좌·연결·지급예정 SSOT에서 파생한다.
  - Controller: `DailyFundsReportController`
  - Models/Repository: `BankTransactionReportModel`, `PaymentScheduleModel`, `BankAccountModel`, `CodeModel`, `CompanyModel`, `InternalTransferRepository`
  - Constraint: `REVIEWED/POSTED/CLOSED` 전표만 확정하며 문자열·날짜·유사금액으로 내부이체를 추정하지 않는다.
- `PaymentScheduleService`
  - Responsibility: 자동 생성된 지급예정의 목록·요약·상세·계획정보 수정·보류·해제·소프트삭제·복원·엑셀과 은행 출금 검색·배분·해제, 계산 상태 투영, 참조 무결성 검증 및 지급이력 기록을 단일 업무 흐름으로 조정한다. 수동 지급의무 생성은 허용하지 않는다.
  - Models: `PaymentScheduleModel`, `PaymentScheduleHistoryModel`, `BankPaymentEvidenceModel`, `EvidenceLinkModel`
  - Integrity: 전표 원천과 참조값은 활성 원본·마스터로 검증한다. 지급예정행·은행원본행·관련 활성 링크를 `FOR UPDATE`로 잠근 뒤 출금액과 예정액을 재검증한다. 보류·취소·검토필요 상태의 신규 연결은 금지하며 현재 KRW `BANK_TRANSACTION` 출금만 지원한다. 예정계좌와 실제 출금계좌가 다르면 경고·이력을 남기되 계획계좌를 덮어쓰지 않는다.

- `PaymentObligationService`
  - Responsibility: 전표가 `REVIEWED → POSTED`로 최초 전환되는 동일 트랜잭션에서 지급의무 계정의 대변 순증액만 지급예정으로 생성하고, `reversal_of`가 있는 역분개 승인 시 원전표 지급의무를 취소 또는 검토필요로 전환한다.
  - SSOT: 계정 판정은 `ledger_accounts.creates_payment_obligation/payment_obligation_type`, 원천 Identity는 `VOUCHER_LINE/voucher_id/voucher_line_id`, 귀속값은 전표라인 참조 후 전표 전체 단일 참조 순서만 사용한다.
  - Integrity: 후보·초안·차변 감소·0원·삭제 전표는 생성하지 않는다. 이미 지급 연결된 원지급의무는 역분개 시 링크를 보존하고 `REVIEW_REQUIRED`로 전환한다.

## 직무·배치 조회

- `EmployeeAssignmentResolver`
  - Responsibility: 명시적 `as_of_date`와 양끝 포함 기간정책으로 직무·프로젝트·근무지 등 배치의 effective 상태를 `PLANNED`, `ACTIVE`, `ENDED`, `CANCELLED`로 판정하고, 단건 Service와 일괄 목록 SQL이 동일 정책을 사용하도록 SQL 식을 제공한다.
  - Boundary: DB 조회와 HTTP API를 소유하지 않는다. 목록은 직원별 호출 없이 `JobAssignmentModel`의 단일 batch SQL에서 Resolver 식을 사용한다.
- `JobAssignmentService`
  - Responsibility: 기준일 직무·배치 조회와 함께 종료된 과거 직무 이력, 비주요 프로젝트 배치, 직접 등록 프로젝트 종료 및 관리자 정정을 검증·처리한다. 입력 options는 Modal 또는 선택형 검색의 최초 사용 시 지연 조회하며 코드·직무·부서·직위만 제공하고, 직원과 프로젝트는 공용 AJAX search-picker가 조회한다. 직접 쓰기는 입퇴사·휴직·프로젝트 기간, 중복기간, 인사발령 원본 금지, `request_key` 멱등성을 동일 트랜잭션에서 강제하며 직원 마스터를 변경하지 않는다.
  - Models: `JobAssignmentModel`, `EmployeeAssignmentAuditModel`
  - SSOT: 상태 조회는 `institution_job_assignments_job_histories`, `institution_job_assignments_project_histories`, `institution_job_assignments_workplace_histories`; 감사 증적은 `institution_job_assignments_audits`다.
  - SSOT: 직원 현재값은 `user_employees`, 기간 재현은 `institution_job_assignments_*` 기간이력 테이블, 변경 근거는 `institution_personnel_actions`다.
  - List scope: 기본 목록과 `recordsTotal`은 전체 `user_employees`를 직원범위로 사용한다. `current_only=1`은 지정 기준일의 `ACTIVE`·`ON_LEAVE`만 `recordsFiltered`에 적용하는 명시적 검색조건이며 기본값은 OFF다. 퇴직자의 목록 상태는 `RETIRED`로 유지하고 기준일 이력이 없으면 Current Master의 마지막 부서·직위를 표시한다.
  - Mutation boundary: 공식 주 직무·주 프로젝트·근무지는 `PersonnelActionService`와 `PersonnelActionApplyService`가 변경한다. 이 Service는 도입 전 종료 직무와 직접 생성 비주요 프로젝트만 등록·종료·Audit 정정하며 Current Master를 변경하지 않는다.
- `LeaveService`: 일반 휴가의 종류 정책, 수동 부여, 분 단위 불변 원장, 신청 계산, 전자결재 적용·전체 취소, 근태 재계산을 단일 트랜잭션으로 조정한다. 장기 휴직 SSOT와 분리한다.
- `LeaveApprovalAdapter`: 공용 결재함에 `LEAVE_REQUEST` 상세를 제공하고 승인·반려를 `LeaveService`에 위임한다.
## 2026-08-06 — 자격·교육관리

| Service | Responsibility | Reuse |
|---|---|---|
| App\Services\Institution\QualificationEducationService | 자격·교육 목록, 등록·수정·삭제·검증·갱신, 교육과정 기준정보, 첨부, 감사, Excel | 직원관리와 자격·교육관리의 단일 업무 Service다. 대표 자격증의 직원 변경을 막고 삭제 시 대표 연결을 함께 해제한다. FileService, ActorHelper, system_codes를 재사용한다. |
### EmployeeHrBaselineService

- 경로: `app/Services/Institution/EmployeeHrBaselineService.php`
- 책임: 신규 직원 Master 생성 트랜잭션 안에서 부서·직위·직무·재직상태 최초 기간이력을 원자적으로 생성한다.
- 기준일: `real_hire_date` 우선, 없으면 `doc_hire_date`를 사용한다.
- 제약: 기존 직원의 이력 보정에는 사용하지 않으며, 인사발령 원본이 없는 최초 Baseline만 생성한다.
