# Table Dictionary

## 프로젝트 기준정보

| Table | Responsibility | SSOT contract |
|---|---|---|
| `system_projects` | 전사 프로젝트 기준정보와 표시순서·Soft Delete 상태 | `id`가 Consumer 참조 SSOT이며 작은 `sort_no`가 위에 표시된다. 삭제 생명주기는 Soft Delete → 복원 또는 실제 ID 참조를 검사하는 guarded purge다. |

## 2026-08-12 상용근로소득·급여(신고)

| Table | Responsibility | SSOT contract |
|---|---|---|
| `institution_regular_employment_incomes` | 귀속월별 상용근로소득 Header와 결재 연결 | 한 귀속월=한 문서, 집계값은 활성 Item 합계 |
| `institution_regular_employment_income_items` | 직원별 확정 소득·공제와 당시 인사 스냅샷 | 직원·근로계약 FK 및 승인 당시 표시 스냅샷. `employment_contract_id`는 급여·소득 근거 보존을 위해 계약 삭제를 `RESTRICT`한다. |
| `ledger_evidence_salary_report` | 최종승인 당시 급여(신고) 증빙 Snapshot | 원본 Header 1:1, 거래/전표 연결은 `ledger_evidence_links` |

- 기존 3개 운영 테이블을 그대로 사용하며 신규 급여 상세·상태·연결 테이블은 만들지 않는다.
- `ledger_evidence_metadata`의 `PAYROLL_REPORT`가 기준일 `raw_payment_date`, 기준금액 `raw_gross_amount`, 원본 테이블을 선언한다.

## 2026-08-11 법정기준 Metadata 계약

- `system_statutory_standards`, `system_statutory_standard_sources`의 `COLUMN_COMMENT`와 `IS_NULLABLE`은 공용 Table Setting 기본 `label`/`required`의 DB Metadata SSOT다. 시스템 자동입력 컬럼은 입력 대상 판정에서 제외하며, `value_data` 내부 필드의 표시명과 필수 여부는 `STATUTORY_STANDARD_TYPE.extra_data`가 담당한다.

## 2026-08-11 법정기준관리

| Table | Responsibility |
|---|---|
| `system_statutory_standards` | 한 행=한 종류·적용기간 법정기준 SSOT |
| `system_statutory_standard_sources` | 법정기준 한 행에 연결되는 0~N개 공식 법령·고시·URL·원본파일 근거 |

- `system_codes.STATUTORY_STANDARD_TYPE`의 활성 13개 행은 법정기준 종류와 동적 값·계산정책 입력계약의 SSOT다. `calculation_policy.fields`는 공식 근거가 확인된 Type만 선언한다. 현재 국민연금, 일용근로소득, 근로소득 간이세액표, 장기요양보험, 지방소득세 특별징수는 계산기초·계산단계·집계단위·판정단위·적용순서 중 필요한 항목만 선언한다. 실제 기준값과 정책은 같은 `system_statutory_standards.value_data`, 당시 계약은 같은 `_schema`가 소유하며 전용 rounding 테이블은 사용하지 않는다.
- `system_codes`는 코드관리에서 단건 Hard Delete만 사용한다. 휴지통 상태를 저장하지 않으며 `deleted_at`, `deleted_by` 컬럼을 두지 않는다. `is_active=0`은 과거 참조를 유지하면서 신규 선택에서 제외하는 상태이며 애플리케이션은 0/1만 저장한다. Hard Delete는 `CodeReferenceRegistry`의 일반 컬럼·JSON 참조 검사가 완료되고 참조가 없을 때만 허용한다. `sort_no`는 현재 전역 순번을 유지하며 정렬 변경 시 `updated_at`, `updated_by`도 갱신한다. `extra_data`는 JSON 문자열 확장정보다.
- 운영 법정기준의 기간은 공표일이 아닌 실제 법령 적용일로 분할한다. 2013년 법인지방소득세 독립세 이전의 법인세분은 `CORPORATE_LOCAL_INCOME_TAX`에 저장하지 않으며, 2014년 독립세 전환 이후 구간만 관리한다.
  - `INDUSTRIAL_ACCIDENT.value_data.industry_rates`는 같은 적용기간의 사업종류별 공식 보험료율을 `[{industry_name,employer_rate}]`로 저장한다. 별도 공식 사업종류 코드 SSOT가 없으므로 현재는 `industry_name` 텍스트를 중복키로 사용하며 회사 업종과 자동 연결하지 않는다.
  - `EMPLOYMENT_INSURANCE.value_data`는 `employee_rate`, `employer_rate`, `additional_employer_rates[{business_size_name,employer_rate}]`를 저장한다. 앞의 두 값은 실업급여 노사 부담률이고 Matrix는 법령상 사업규모별 고용안정·직업능력개발사업 사업주 부담률이다. 회사 규모를 법정기준에 하드코딩하지 않으며 실제 Consumer가 업무 SSOT의 사업규모와 대조한다.
  - `INDUSTRIAL_ACCIDENT`의 2018년 이후 건설업 `employer_rate`는 사업종류별 요율과 전 업종 출퇴근재해 요율의 합계다. 개별실적요율 같은 사업장별 가감은 공통 법정기준 값이 아니라 향후 해당 Consumer의 판정 책임이다.
- `system_statutory_standards`는 한 행을 하나의 종류·적용기간 법정기준으로 저장한다. 값은 `value_data`에 완결하며 별도 적용조건 컬럼은 두지 않는다. 동일 종류의 기간중복은 Service가 차단한다.
- 각 적용기간 행 자체가 개정 이력이다. 별도 Revision/History/Correction/Audit 테이블이나 revision 번호·parent·chain·Draft·Confirm 상태를 만들지 않는다.
- `EMPLOYMENT_INCOME_TAX_TABLE.value_data.table`은 `salary_unit`, `dependent_counts`, `rows`를 함께 보존한다. 각 행은 `salary_from`, `salary_to`, 가족수 문자열 키 Map인 `tax_by_dependents`를 가지므로 현재 1~11명과 미래의 다른 가족수 범위를 같은 JSON 계약으로 저장한다. `value_data.excess_rules`는 가변 개수의 시작·종료, 기준급여, 기준세액 참조방법, 초과반영률, 세율, 고정가산액을 저장하고 `adjustment_rules`는 연령조건 자녀수별 공제 같은 표 외 조정을 구조화한다. `_schema.fields`에는 저장 당시 렌더링·검증 계약을 스냅샷으로 보존한다. Global `extra_data`는 신규 입력 UI의 기본 계약만 제공하며 저장된 `value_data`의 차원·규칙·스키마가 과거 기준 재현 SSOT다. 공식 HWP/PDF/XLS는 Source 원본파일로 보존하며 구형 `matrix_cells`, 가족수별 물리 컬럼, 문자열 수식은 사용하지 않는다.
- `EMPLOYMENT_INCOME_TAX_TABLE.extra_data.fields[].ui`는 공식표·표 상한 초과 계산기준·별도 세액 조정기준의 제목, 도움말, 기본 접힘 상태와 `allow_paste`를 제공한다. 대량 공식표만 `allow_paste=true`이며 두 소규모 규칙은 직접 행 입력만 허용한다. `excess_rules`와 `adjustment_rules`는 Metadata의 `required=false` 계약에 따라 0건 저장할 수 있다. 가족수 원시 배열은 기본 화면에서 숨기고 현재 `dependent_counts` 범위를 요약하며, `base_tax_reference=TABLE`과 `rule_type=CHILD_COUNT_DEDUCTION`은 숨김 `default_value`로 자동 저장한다. 이는 표시 Adapter이며 `value_data` 계산 SSOT를 대체하지 않는다.
- 공식 간이세액표의 가족수별 세액 동적 컬럼은 `dash_as_zero=true`로 대시만 0원 처리한다. `salary_to`는 마지막 상한행의 공란을 보존하도록 nullable이며, 공란은 0이 아니라 JSON `null`로 저장한다.
- Resolver 조회 인덱스는 `idx_statutory_standard_resolve(standard_type_code, effective_from, effective_to)`를 유지한다. 동일 종류의 기간중복은 시작일 단일 UNIQUE 제약이 아니라 Service의 전체 기간 교차검증으로 차단한다.
- `system_statutory_standards`에는 공표일 컬럼을 두지 않는다. `effective_from`, `effective_to`는 법정기준 본체의 적용기간이다.
- 공표일 SSOT는 `system_statutory_standard_sources.published_at`이며, 1:N으로 연결된 각 근거자료가 자기 공표일을 소유한다.
- `system_statutory_standard_sources.id`는 근거자료 식별자로 유지된다. 기준 수정 시 ID 기반 diff 동기화를 사용하여 존재 근거의 `created_at`, `created_by`, 미교체 파일 참조를 보존한다.
- 법정기준 종류명과 동적 입력계약은 `system_codes.STATUTORY_STANDARD_TYPE`에서 조회하며 이름을 업무 테이블에 중복 저장하지 않는다.
- 법정기준관리 업무 테이블은 위 두 개뿐이다. 적용연도와 유효 여부는 `effective_from`, `effective_to`에서 파생한다.

## 2026-08-06 취업규칙·인사규정

| Table | Responsibility | SSOT contract |
|---|---|---|
| `institution_employment_rules` | 회사별 규정 문서 헤더 | 규정 코드와 현재 시행 개정본을 식별한다. |
| `institution_employment_rules_revisions` | 규정 개정본과 결재·시행 상태 | 승인본은 수정하지 않고 새 revision을 만든다. |
| `institution_employment_rules_items` | 형식화된 정책값 | 시간·분·일·비율·Boolean·숫자를 물리 컬럼에 저장한다. |
| `institution_employment_rules_scopes` | 회사 전체·부서·직위·직무·고용구분 적용범위 | 직원별 예외는 저장하지 않는다. |
| `institution_employment_rules_audits` | 처리자·사유·요청 키·변경 전후 감사 | 정책 조회에는 사용하지 않는다. |

## System

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| system_page_registry | PageRegistry | Stores canonical ERP page rows keyed by `page_key`, including module/menu/page labels, breadcrumb, and representative WEB route information promoted from the current `PAGE_MAPPING`. | Prepared for future permission, breadcrumb, sitemap, and menu consumers through `page_key`, `default_route_key`, and `default_route_url`. |
| system_menu_registry | MenuRegistry | Stores ERP menu display-policy rows keyed by `menu_key`, with nullable `page_key` links to `system_page_registry`, per-surface visibility flags, ordering, icon, and representative entry URL. | Seeded from current Sidebar, Settings Menu, SiteMap, and Navbar structures; prepared for future menu consumers through `page_key`, `visible_in_sidebar`, `visible_in_settings`, `visible_in_sitemap`, and `visible_in_navbar`. |
| system_user_settings | UserSetting | Stores per-user page preference JSON such as DataTable and view-state payloads, keyed by page and setting type for DB-backed UI preference restore. | Scoped to the current authenticated user, logically keyed by `user_id + page_key + setting_type`, and consumed by `UserSettingService` plus shared client-side settings storage bridges. |
| system_clients | Client | 거래처 기본정보, 업무분류, 연락처, 정산정보, 파일 참조와 soft-delete 상태의 SSOT. | 프로젝트·카드·거래·전표·증빙·지급예정·개인경비 등에서 FK 또는 논리참조한다. 직원은 거래처가 아니므로 `user_employees`와 직접 연결하지 않는다. 참조 안전성을 보장할 수 없어 영구삭제를 금지하고 soft delete와 복원만 허용한다. |
| system_bank_accounts | BankAccount | 전사 은행계좌 기준정보, 통장사본 참조, 표시순서, 활성상태와 soft-delete 상태의 SSOT. | `id`가 카드·거래처 기본계좌·증빙·거래·전표·지급예정의 물리 또는 논리참조 SSOT다. 삭제 생명주기는 Soft Delete → 복원 또는 ID 참조를 검사하는 guarded purge이며, 통장사본 물리파일은 DB commit 이후 정리한다. |
| system_cards | Card | 카드명, 전체 카드번호, 유효기간 년·월, 한도, 카드 이미지, 카드사·결제계좌 참조, 표시순서와 soft-delete 상태의 SSOT. CVC/CVV 및 금융 비밀번호는 저장하지 않는다. | 카드사는 `system_clients.id`, 결제계좌는 `system_bank_accounts.id`를 사용한다. 증빙·거래·전표의 카드 ID 참조 및 `ledger_voucher_line_refs(ref_target='CARD')`를 검사하는 guarded purge를 적용하며 이미지 물리파일은 DB commit 이후 정리한다. |
| system_client_histories | ClientHistory | 증빙 동기화로 변경된 거래처 필드의 변경 전후값과 원본 증빙을 보존한다. | `client_id`는 `system_clients.id` FK이며 기존 `20260526_02_create_system_client_histories` Migration이 소유한다. CRUD 런타임에서 Schema를 생성하지 않는다. |
| system_settings_config | SettingConfig | Stores DB-backed system configuration values such as layout branding and UI policy. | Read through `SettingConfigModel`/`SettingService`; layout composition does not query this table directly. |
| system_notifications | Notification | Stores user-targeted notifications, read state, actor reference, action type, optional domain reference, and message content. | Owned by `NotificationModel`; `recipient_user_id` and `actor_user_id` refer to authentication users. Actionable approval navbar entries are live projections from approval request/step SSOT and are not duplicated in this table. |

## Auth

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| auth_permissions | Permission | Stores ERP permission master rows including `permission_key`, `permission_name`, `description`, `category`, `page_key`, `page`, `permission_source`, and `is_active`. | Referenced by `auth_role_permissions.permission_id`, synchronized by `PermissionRegistry`, logically linked to `system_page_registry.page_key`. |
| auth_roles | Role | Organization role master SSOT. Stores one role key/name, ordering, active state, description, and Actor audit values. The physical update Actor column is the legacy `updated_bY` column and is mapped to standard `updated_by` API output. | Referenced by `auth_users.role_id`, `auth_role_permissions.role_id`, `user_approval_template_steps.role_id`, and `user_approval_request_steps.role_id`. |
| auth_role_permissions | RolePermission | Stores role-to-permission mappings keyed by `role_id + permission_id` and acts as the runtime permission source of truth. | Links `auth_roles.id` to `auth_permissions.id`. |
| auth_user_permission_profiles | UserPermissionProfile | 사용자별 Permission 적용방식 `ROLE`/`EXTEND`/`REPLACE` SSOT. | 사용자당 PK 1건이며 기존 사용자는 `ROLE`로 초기화한다. 전체 컬럼은 공용 테이블 설정에 사용할 한글 업무 코멘트를 제공한다. |
| auth_user_permissions | UserPermission | 사용자별 직접 Permission Mapping. | `UNIQUE(user_id, permission_id)`, Permission 인덱스와 사용자·Permission CASCADE FK를 사용하며 권한부여 일시·Actor 코멘트를 제공한다. |
| auth_user_permission_audits | UserPermissionAudit | Mode 및 개인 Permission Set 변경을 `MODE`/`GRANT`/`REVOKE`와 `batch_id`로 영구 기록한다. | 사용자·Permission 삭제 시 FK는 SET NULL이고 식별 Snapshot은 유지하며 전체 감사 컬럼에 한글 업무 코멘트를 제공한다. |

## Ledger

| Table | Domain | Purpose | Relations |
| --- | --- | --- | --- |
| ledger_accounts | ChartAccount | Stores the canonical chart of accounts, hierarchy, normal-balance policy, posting availability, payment-obligation policy, sub-account allowance, ordering, and soft-delete state. | `creates_payment_obligation=1` requires a posting account and one checked `payment_obligation_type`; voucher posting uses only these columns and never account-name heuristics. Parent hierarchy uses `parent_id`; referenced by journal rules and voucher lines through account IDs. |
| ledger_accounts_sub | SubAccountTargetPolicy | Stores allowed sub-account targets per account using canonical `ref_target`, plus `is_required` and ordering. | Child of `ledger_accounts.id`; voucher-line values remain normalized separately in `ledger_voucher_line_refs`. No `ref_type` fallback is used. |
| ledger_journal_rules | JournalRule | 추천조건과 차변·대변·VAT 계정 조합을 저장하는 분개규칙 SSOT. 수동 보정은 화면에서만 수행하며 Excel 입출력은 운영하지 않는다. | 계정 ID는 사용 가능한 `ledger_accounts`만 허용한다. `client_type IS NULL`은 전체 거래처유형 wildcard다. `sort_no`는 동일한 신뢰도·사용통계 조건의 최종 추천 정렬에도 영향을 준다. `usage_count`와 `last_used_at`은 실제 POSTED 전표에서 수정 없이 사용된 Rule만 반영한다. |
| ledger_journal_learning_events | JournalFeedback | POSTED 전표의 확정 분개라인을 보존하는 학습 Event SSOT. 신규 Event는 `event_type=POSTED_CONFIRMATION`이며 `(voucher_line_id,event_type)` UNIQUE로 멱등성을 보장한다. | 기존 5건은 삭제된 과거 전표의 Legacy Event라 `voucher_line_id/event_type` NULL로 보존한다. 신규 라인은 FK RESTRICT이며 취소전표는 Event를 만들지 않는다. |
| ledger_journal_recent_patterns | JournalFeedback | 학습 Event에서 재계산되는 최근 확정 분개 조합 Projection. `pattern_hash=SHA1(debit|credit|vat)`가 key다. | `legacy_usage_count`는 Writer 도입 전 2건의 통계를 보존하고 신규 Event aggregate를 더해 deterministic SET한다. 회계 SSOT가 아니다. |
| ledger_journal_client_account_patterns | JournalFeedback | 명확한 단일 거래처 Context의 확정 계정 Projection. `(client_id,transaction_direction,line_type,account_id)`가 key다. | `legacy_usage_count/legacy_recent_score`로 기존 3건을 보존하고 신규 Event aggregate를 deterministic SET한다. 애매한 거래처 Context는 제외한다. |
| ledger_data_formats | EvidenceFormat | Stores saved evidence-import format headers grouped by `source_type` and `format_name`. | Parent of `ledger_data_format_columns`; referenced by evidence upload format flows. |
| ledger_data_format_columns | EvidenceFormat | Stores per-format column order, visibility, requirement, normalized field mapping, and source-column SSOT key through `original_column_key`. | Child of `ledger_data_formats.id`; unique by `(format_id, system_field_name)` for normalized mapping and `(format_id, original_column_key)` for source-column settings. |
| ledger_evidence_links | EvidenceLink | Evidence와 거래·전표·지급예정의 유일한 DB 연결 SSOT. | `(evidence_type, evidence_id, target_type, target_id, link_type)`가 동일 링크 중복을 방지한다. 활성 `TRANSACTION` 링크는 생성 컬럼 `active_transaction_evidence_type`, `active_transaction_evidence_id`와 `uk_evl_active_transaction_evidence`로 canonical 증빙 Identity당 하나만 허용한다. soft-delete 링크와 `VOUCHER`·`PAYMENT_SCHEDULE` 링크는 이 조건부 유일성 대상이 아니다. 확정 내부이체는 동일 확정 전표의 활성 `BANK_TRANSACTION` 2건과 canonical `ACCOUNT` 라인 참조를 함께 검증해 파생한다. `PAYMENT_SCHEDULE`은 `BANK_TRANSACTION`·`PAYMENT`·양수 `amount`만 허용하며, 한 출금을 여러 지급예정에 배분할 때 활성 링크 합계가 출금 원본액을 초과하지 않도록 Service가 잠금 검증한다. |
| ledger_payment_schedules | PaymentSchedule | 지급의무와 지급계획의 단일 SSOT. | 전표 승인 생성 원천은 `VOUCHER_LINE`, `source_id=voucher_id`, `source_line_key=voucher_line_id`이며 UNIQUE로 재생성을 차단한다. `payment_due_date`와 지급계좌는 미확정 시 NULL이다. `obligation_lifecycle_status`는 `ACTIVE/CANCELLED/REVIEW_REQUIRED`만 허용하고 역분개 시 취소 Actor·일시·사유를 보존한다. 지급상태·기지급액·잔여액·연체정보는 저장하지 않고 활성 PAYMENT 링크 합계와 기준일로 계산한다. |
| ledger_payment_schedule_histories | PaymentScheduleHistory | 지급예정 생성·변경·보류·삭제·복원 및 실제 지급 연결·해제 업무이력. | JSON 전후값과 Actor token을 보존한다. 지급예정 운영행의 물리 삭제를 허용하지 않아 FK는 RESTRICT다. |
| ledger_evidence_metadata | EvidenceMetadata | Stores one Registry header per `import_type`: Evidence Body `source_table`, DATA/FUND/BOTH `evidence_type`, ordering, Actor, and soft-delete state. The retained `process_role` column is legacy compatibility data and is not a Runtime handler selector. | Parent SSOT for `ledger_evidence_metadata_columns`. Runtime-required or referenced headers are protected from delete/purge. No revision, rule-engine, handler, or matching responsibility is stored here. |
| ledger_evidence_metadata_columns | EvidenceMetadata | Stores semantic accounting meaning to actual source-table column mapping as `(metadata_id, semantic_key, physical_column)`, including `BASE_DATE`, representative `DESCRIPTION`, amount meanings, and optional `adjustment_direction`. `adjustment_direction` accepts `ADD` or `DEDUCT` only for `ADJUST_AMOUNT`; every other semantic uses `NULL`. The retained `is_required` column is compatibility data fixed to `N`, not Evidence Body validation SSOT. | Child of `ledger_evidence_metadata.id`; unique by `(metadata_id, semantic_key, physical_column)` and server-validated against the Header source table. Detail replacement remains inside the Header save transaction. |
| ledger_evidence_bank_transaction | EvidenceBody | Stores `BANK_TRANSACTION` evidence body rows with raw bank transaction fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and connects to `ledger_evidence_links` by `(evidence_type, evidence_id)` using `BANK_TRANSACTION`. |
| ledger_evidence_tax_invoice | EvidenceBody | Stores `TAX_INVOICE` evidence body rows with raw HomeTax tax-invoice fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and is logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_tax_invoice_manual | EvidenceBody | Stores `TAX_INVOICE_MANUAL` evidence body rows with raw manual tax-invoice fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and is logically linked to `system_clients.id` and `system_projects.id`. |
| ledger_evidence_cash_receipt | EvidenceBody | Stores `CASH_RECEIPT` evidence body rows with raw cash-receipt fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and is logically linked to client and project references when mapped. |
| ledger_evidence_card_hometax | EvidenceBody | Stores `CARD_HOMETAX` evidence body rows with raw HomeTax card fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and connects to `ledger_evidence_links` by `(evidence_type, evidence_id)` using `CARD_HOMETAX`. |
| ledger_evidence_card_statement | EvidenceBody | Stores `CARD_STATEMENT` and `CARD_APPROVAL` evidence body rows with raw card-company fields. | `id` is the evidence body key; non-empty `external_key` is unique within the Body table and card-company evidence types resolve here. |
| ledger_evidence_employee_expense | EvidenceBody | Stores employee-expense source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_payroll | EvidenceBody | Stores payroll and payroll-withholding source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_daily_worker | EvidenceBody | Stores construction daily-worker source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_business_income | EvidenceBody | Stores business-income source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_cash_sales | EvidenceBody | Stores business/cash-sales source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. |
| ledger_evidence_business_data | EvidenceBody | Stores shopping-order and import-invoice source rows. | Non-empty `external_key` is unique within the Body table; duplicate uploads never update existing rows. || ledger_bank_transactions | FundsLegacy | Legacy bank transaction storage used by historical funds flows and migration/backfill paths. | Referenced by bank-evidence generation, processing-item backfill, and legacy funds screens. |
| ledger_transactions | Transaction | Stores ERP common transaction header data including classification, base references, and the SSOT header amounts `transaction_foreign_amount`, `transaction_supply_amount`, `transaction_settlement_amount`, and `transaction_final_amount`. | Linked from evidence links using `target_type='TRANSACTION'`; parent of `ledger_transaction_items`; no direct voucher relation is stored. |
| ledger_transaction_items | Transaction | Stores transaction item rows with SSOT fields such as `sort_no`, `item_date`, `item_name`, `item_specification`, `item_unit_name`, `item_quantity`, `item_unit_price`, `item_foreign_unit_price`, `item_foreign_amount`, `item_supply_amount`, and `item_description`. Item tax type is not stored; evidence keeps its own tax semantics and transaction VAT remains a settlement. | Child of `ledger_transactions.id`; removed with the header during transaction purge. |
| ledger_transaction_settlements | TransactionSettlement | Stores transaction-level settlement adjustments with SSOT fields `sort_no`, `settlement_type`, `amount_sign`, `amount`, and `settlement_description`. | `transaction_id` is normalized to `varchar(36) utf8mb4_general_ci NOT NULL` and protected by `fk_transaction_settlement_transaction` to `ledger_transactions.id` with `ON DELETE/UPDATE CASCADE`. The legacy `transaction_item_id` column remains physical compatibility only. |
| ledger_vouchers | Voucher | Stores voucher header read-model data for list/search/sort flows, including `debit_total`, `credit_total`, line count, representative summary refs, and the workflow status (`DRAFT`, `REVIEW_REQUESTED`, `REVIEWED`, `POSTED`, `CLOSED`, `DELETED`) derived from voucher runtime operations. | Linked from evidence links using `target_type='VOUCHER'`; no direct transaction relation is stored. Totals and summary columns are recalculated from `ledger_voucher_lines` and `ledger_voucher_line_refs`. The balance difference is calculated at runtime and is not stored. |
| ledger_voucher_lines | Voucher | Stores voucher journal lines with voucher-local `line_no` as the UI, drag-and-drop, save, and query order SSOT; global `sort_no` remains internal-only. It also stores account, amount, line summary, journal rule, user-modification fields, and the original recommended account/side/amount needed by POSTED feedback. | Child of `ledger_vouchers.id`; parent of `ledger_voucher_line_refs`; `line_no` is contiguous from 1 within each voucher. `recommended_*` is nullable for manual lines. |
| ledger_voucher_line_refs | Voucher | Stores all voucher-line sub-account refs from `line.refs[]` as normalized `(voucher_line_id, ref_target, ref_id)` rows, where `ref_id` uses the shared UUID key shape `CHAR(36)`. | Child of `ledger_voucher_lines.id`; the only SSOT for Voucher sub-account save/detail/reopen flows. |

## Notes

- Register new runtime tables here before shipping code that depends on them.
- When a table references another table logically rather than with an FK, document that relation in the Relations column.
- Keep payload, status, link, and body responsibilities separated rather than overloading one table.
- Evidence Body ordering is local to each type-specific table through `sort_no`. The retired generation-center-wide `evidence_sort_no`, `ledger_evidence_number_sequences`, and `ledger_evidence_number_histories` are not part of the runtime schema or API contract.

## Personal expense approval tables

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| `approval_personal_expenses` | PersonalExpense | Employee-owned personal-expense application header containing application date, title, whole-document description, memo, stored active-item aggregates (`item_count`, `supply_amount`, `vat_amount`, `total_amount`), current document-status projection, current approval-request pointer, soft-delete audit fields, and other audit fields. | Aggregate columns are server-calculated from non-deleted `approval_personal_expense_items` in the same transaction as item mutation; client header totals are never trusted. Ownership is forced by the authenticated user's `user_employees.id`; `UNIQUE(employee_id, sort_no)` keeps employee-local numbering. Header `deleted_at` is the sole application-trash state, so items are retained unchanged during soft delete. `user_approval_requests.status` remains the workflow SSOT. |
| `approval_personal_expense_items` | PersonalExpenseItem | Stores multiple expense lines for one personal-expense header, including expense, merchant snapshot, project, optional exact existing-client reference, item, amount, and audit fields. | Actual DB FK `personal_expense_id → approval_personal_expenses.id` has `ON DELETE CASCADE`; purge nevertheless deletes items explicitly before the header for count/error verification. Generated `ledger_evidence_employee_personal_expense.source_personal_expense_item_id` uses `ON DELETE RESTRICT`, so any evidence reference blocks purge. Nullable `client_id` uses `ON DELETE SET NULL`; `UNIQUE(personal_expense_id, sort_no)` keeps document-local ordering. |
| `user_approval_requests` | ApprovalRequest | Approval execution header, document link, current step, and overall workflow status SSOT. | `document_type=PERSONAL_EXPENSE`, `document_id=approval_personal_expenses.id`; resubmission creates a new immutable-history row and the document pointer selects the current request. |
| `user_approval_request_steps` | ApprovalRequestStep | 결재요청 단계의 불변 스냅샷 SSOT. `step_type`은 `SUBMIT`·`APPROVAL`·`FINAL_APPROVAL`이며, `approver_id`가 있으면 지정결재, 없고 `role_id`가 있으면 역할 공동결재다. | `SUBMIT`은 상신 시 `approved`로 완료되고 `requester_id`/`requested_at`이 발의 SSOT다. 역할 공동결재의 `approver_id`는 NULL이며 실제 처리자는 `acted_by`에 기록한다. `(role_id,status,is_active)` 조회 인덱스를 사용한다. |
| `user_approval_templates` | ApprovalTemplate | 미래 결재요청의 단계 설계도 Header SSOT. 신규 행은 비활성 초안으로 생성하며, 활성화 시 전체 단계 완결성과 동일 `document_type` 활성 템플릿 단일성을 Service에서 검증한다. | `user_approval_template_steps.template_id`, `user_approval_requests.template_id`가 참조한다. 요청 생성 시 단계 Snapshot을 만들며 이후 템플릿 변경은 기존 요청에 영향을 주지 않는다. |
| `user_approval_template_steps` | ApprovalTemplateStep | 결재템플릿 단계 정의 SSOT. 순번, 단계명, `step_type`, 역할, 특정 결재자, 활성 여부를 관리한다. | `approver_id`가 있으면 역할은 지정 사용자 적격성 검증 조건이고, `approver_id`가 없으면 역할 보유자 공동결재 정책을 요청 스냅샷으로 복사한다. |
| `ledger_evidence_employee_personal_expense` | EvidenceBody | Final-approved employee personal-expense item evidence body, including separate raw merchant base and detailed addresses. | `source_personal_expense_item_id` references `approval_personal_expense_items.id` with one unique evidence per item; identity uses `EMPLOYEE_EXPENSE_PERSONAL + evidence_id`. |

## Employment contract tables

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| `institution_employment_contracts` | EmploymentContract | 근로계약 헤더, 당사자 스냅샷, 기간·근무형태·급여지급·결재상태와 감사정보의 SSOT다. | 계약기간은 `contract_period_type`, 고용구분은 `employment_category`, 근로시간은 `working_time_type`이 각각 단일 SSOT다. `FIXED_TERM`일 때만 종료일과 기간제 사유를 저장하며 근무시간 요약, 법정 휴일·연차, 출력·교부 상태는 저장하지 않는다. |
| `institution_employment_contracts_audits` | EmploymentContractAudit | 근로계약의 법적 의미가 있는 액션과 전체 계약조건 before/after JSON을 보존하는 불변 감사 원장이다. | 계약 ID는 Draft purge 후에도 물리 식별값을 보존하기 위해 FK 없이 저장하고 인덱스로 조회한다. 결재요청은 `RESTRICT` FK로 보존하며 `(contract_id, action_type, request_key)`가 멱등성을 보장한다. |
| `institution_employment_contracts_weekly_schedules` | EmploymentContractWeeklySchedule | `NORMAL`, `NIGHT`의 반복 일정과 `FLEXIBLE`, `OTHER`의 기준 반복 일정 SSOT다. | 계약별 7행과 `WEEKLY_HOLIDAY` 정확히 1행을 Service가 강제한다. 주간·월평균·통상임금 기준시간은 이 일정에서 조회 시 파생하며 헤더에 중복 저장하지 않는다. |
| `institution_employment_contracts_work_schedule_policies` | EmploymentContractWorkSchedulePolicy | `SELECTIVE`, `SHIFT`, `FLEXIBLE`, `OTHER` 계약의 근무형태별 정책 SSOT다. | 계약당 최대 1행이다. 선택근무는 기준시간·선택/의무시간대, 탄력근무는 정산기간·기준시간, 교대근무는 기준시간, 기타근무는 전체 정책 필드를 유형별로 저장한다. `NORMAL`·`NIGHT`에는 행을 두지 않는다. |
| `institution_employment_contracts_components` | EmploymentContractComponent | 계약 당시 급여 구성항목과 연장·야간·휴일근로수당 약정을 계약별로 저장한다. | 계약금액 SSOT는 활성 행(`deleted_at IS NULL`)의 `amount`다. 월 지급합계와 결재함 `total_amount`는 계약별 `SUM(amount)`, 연 환산액은 그 합계의 `× 12` 파생값이다. 기본급·연차수당은 `quantity × rate`, 근로수당은 `quantity × rate × premium_rate`로 계산하고 문자열 산식이나 헤더·결재 전용 합계는 저장하지 않는다. 정액수당은 `amount`를 직접 확정한다. |
| `institution_employment_contracts_pay_components` | PayComponent | 상시근로자 근로계약용 급여구성항목 마스터다. | Employment 도메인 전용이며 계약시작일에 활성인 항목만 참조한다. ERP 범용 지급항목으로 사용하지 않는다. |

## Human-resources common SSOT tables

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| `user_departments` | Department | 전사 공용 부서 마스터 SSOT다. | 실제 10개 물리 컬럼만 사용하며 계층 구조와 휴지통은 없다. `manager_id`는 직원과 연결된 선택 가능한 `auth_users.id`를 저장한다. 참조가 없는 오등록 건만 Guarded Hard Delete하고 조직개편·폐지는 `is_active = 0`으로 처리한다. |
| `user_positions` | Position | 전사 공용 직위·직책 통합 마스터 SSOT다. | 실제 10개 물리 컬럼만 사용하며 독립 직급 Master나 휴지통은 없다. `sort_no`는 표시순서, nullable `level_rank`는 업무상 등급 숫자이며 별도 SSOT가 아니다. 현재 직원·기간이력·인사발령·취업규칙 참조가 없는 오등록 건만 Guarded Hard Delete하고 미사용은 `is_active = 0`으로 처리한다. |
| `user_employees` | Employee | 직원 기본정보와 부서·직위·재직상태·현재 직무의 현재상태 SSOT다. | `employment_status`는 명시적 현재 재직상태이고 `job_id`는 현재 직무 캐시다. 입퇴사일은 문서상·실제 날짜를 보존한다. 직원 참조는 `user_employees.id`인 `employee_id`를 사용하며 `system_clients`와 직접 연결하지 않는다. `representative_qualification_id`는 복수 자격 원장 중 직원 모달에서 관리할 대표 자격증 한 건만 연결하며 이름과 파일은 원장에 저장한다. 재직·직무 현재값과 기간이력의 동기화는 인사발령 적용 Service만 담당하며 로그인 활성상태와 분리한다. `client_id` 제거는 forward-only Migration `20260814_01_remove_employee_client_link`가 소유한다. |
| `auth_users` | AuthUser | 로그인 계정, 인증·알림·승인·역할·잠금·접속상태와 계정 감사정보의 SSOT다. | 직원 화면은 `user_employees.user_id = auth_users.id`로 결합한다. 공용 직원 Table Setting 메타는 `user_employees` 25개 다음에 `auth_users` 23개 물리 컬럼을 원본 순서대로 합성하며, 두 테이블의 `id`는 각각 `id`, `auth_user_id` 공개 키로 구분한다. |
| `institution_job_assignments_jobs` | Job | 인사·노무 전체에서 재사용하는 직무 마스터다. | 직무별 자격·교육·프로젝트 적격성은 향후 별도 관계 테이블이 이 ID를 참조한다. 동일 직무 목록을 `system_codes`에 중복하지 않는다. |
| `institution_job_assignments_employment_status_histories` | EmployeeEmploymentStatus | 특정 날짜의 입사예정·재직·휴직·퇴직 상태를 재현하는 기간 이력 SSOT다. | `user_employees.employment_status`는 현재상태이며 인사발령 적용 Service가 동일 트랜잭션에서 이력과 함께 갱신한다. 로그인 활성상태와 독립이다. |
| `institution_job_assignments_department_histories` | EmployeeDepartmentAssignment | 직원 부서의 유효기간 이력 SSOT다. | 종료일 NULL은 현재 유효행이며 직원별 한 건만 허용한다. 시작·종료 인사발령 대상자를 보존하고 직원 마스터 현재 부서와 적용 Service가 원자적으로 동기화한다. |
| `institution_job_assignments_position_histories` | EmployeePositionAssignment | 통합 `user_positions`를 기준으로 직원 직위·직책의 유효기간 이력을 보존한다. | 별도 직책 마스터를 만들지 않는다. 종료일 NULL은 현재 유효행이며 적용 Service가 직원 마스터와 원자적으로 동기화한다. |
| `institution_job_assignments_leave_periods` | EmployeeLeave | 휴가와 분리된 휴직·복직 기간 SSOT다. | 직원별 활성 휴직은 최대 1건이다. 휴직·복직 원본 발령 대상자를 보존한다. |
| `institution_job_assignments_job_histories` | EmployeeJobAssignment | 직원 직무의 기간 이력 SSOT다. | `user_employees.job_id`는 현재 직무 캐시이며 발령 적용 Service만 이력과 원자적으로 동기화한다. 기간 중복은 Service 잠금 검증을 병행한다. |
| `institution_job_assignments_audits` | EmployeeAssignmentAudit | 직무·프로젝트·근무지 배치의 직접 등록·종료·정정 감사 증적이다. | 업무 상태 SSOT가 아니며 현재·기준일·목록 상태 계산에 사용하지 않는다. 도메인별 FK 하나, 물리 작업·출처·사유·Actor, JSON 전후값과 전역 UNIQUE `request_key`를 보존한다. 감사행 수정·삭제 API는 제공하지 않는다. |
| `institution_job_assignments_project_histories` | EmployeeProjectAssignment | 직원의 다중 프로젝트 기간배치 SSOT다. | 동일 프로젝트 재배치를 허용하고 시작일로 차수를 구분한다. `status_code`는 저장 당시 상태와 명시적 `CANCELLED`를 보존하며 기준일 표시는 기간에서 파생한다. 현재 주배치는 직원별 최대 1건이며 전체 기간 중복은 Service 잠금 검증을 병행한다. |
| `institution_job_assignments_workplace_histories` | EmployeeWorkplaceAssignment | 프로젝트 배치와 독립된 직원 근무지 기간이력 SSOT다. | `HEAD_OFFICE`, `PROJECT`, `BUSINESS_TRIP`, `REMOTE`, `OTHER`를 표현하고 특정 날짜의 근무지를 재현한다. 프로젝트 유형만 `project_id`를 필수로 하며 현재 활성 근무지는 직원별 최대 1건이다. |
| `institution_personnel_actions` | PersonnelAction | 인사발령 문서, 업무상태, 결재 포인터, 미래 적용, 정정·취소 원본의 SSOT다. | `issued_date`는 발령일, `action_date`는 효력일이다. 결재상태는 `user_approval_requests`가 소유하며 적용완료 문서는 직접 수정하지 않는다. |
| `institution_attendance_clock_events` | AttendanceClockEvent | 수정·삭제하지 않는 실제 출퇴근 원본 SSOT다. | 내부 `request_key`, 외부 `source_type_code + external_key`로 중복을 차단하고 오류 원본은 무효 표시한다. |
| `institution_attendance_daily_records` | AttendanceDailyRecord | 직원·근무일별 재계산 가능한 근태 Projection이다. 계약 일정, 실제 구간, 승인 휴가, `working_time_standard_id`, `public_holiday_standard_id`와 계산정책 version을 적용한 최신 결과를 보관한다. | `employee_id + work_date`가 유일하다. `contract_excess_seconds`는 계약 예정초과, `calculated_overtime_seconds`는 주간 전체 재평가로 발생 날짜에 귀속한 일·주 법정 연장근로다. 법정기준 FK는 삭제 제한과 Runtime 불변 Guard로 과거 근거를 보존한다. |
| `institution_attendance_work_segments` | AttendanceWorkSegment | 일별 WORK·BREAK·OUTSIDE 실제 시간 구간이다. | 반개구간을 사용하고 Service가 역전·중복을 차단한다. |
| `institution_attendance_daily_exceptions` | AttendanceDailyException | 지각·조퇴·결근·누락·일정충돌·연속 출퇴근 중복 등 복수 예외 SSOT다. | `DUPLICATE_CLOCK_IN/OUT`은 서로 다른 시각의 연속 동일 원본을 보존한 채 표시한다. `daily_record_id + exception_type_code + source_type_code` 단일 행을 재사용한다. |
| `institution_attendance_monthly_closures` | AttendanceMonthlyClosure | 직원·월별 현재 마감상태와 현재 revision 포인터 SSOT다. | 집계 스냅샷은 저장하지 않고 현재 history만 참조한다. |
| `institution_attendance_monthly_closure_histories` | AttendanceMonthlyClosureHistory | 월 마감 revision별 불변 집계 스냅샷 SSOT이며 급여·원가·통계의 확정 근태 소비 경계다. | 귀속월 마지막 법정 주간이 종료·재평가된 후에만 생성한다. `monthly_closure_id + revision` 및 `source_request_key`가 유일하고 과거 revision을 수정하지 않는다. 재오픈 이후에는 새 revision 재마감 전까지 확정 소비를 중단한다. |
| `institution_attendance_audits` | AttendanceAudit | 근태 등록·재계산·정정·마감·재오픈의 변경 전후 감사 증적이다. | 현재 상태나 월 집계를 계산하는 SSOT로 사용하지 않는다. |
| `institution_personnel_actions_targets` | PersonnelActionTarget | 발령 문서의 대상 직원과 대상별 적용결과·적용 Actor를 저장한다. | 문서 내 직원 중복을 금지하며 `application_status`, `application_error`, `applied_at`, `applied_by`가 대상별 적용 감사를 구성한다. |
| `institution_personnel_actions_changes` | PersonnelActionChange | 대상 직원별 타입 안전 변경명령과 변경 전후 스냅샷을 저장한다. | `change_type_code`는 일반 코드가 아니라 `PersonnelActionChangePolicy`가 소유하는 고정 시스템 실행명령이다. 범용 문자열·JSON EAV를 사용하지 않으며 FK·날짜·코드 물리 컬럼과 DB CHECK는 명령별 물리 저장 무결성을 방어한다. |
- `institution_leave_types`: 휴가 종류와 허용 신청단위·유급·잔액·증빙 정책 SSOT.
- `institution_leave_grants`: 직원별 수동 휴가 부여 업무행.
- `institution_leave_requests`: 일반 휴가 및 승인 후 전체 취소 신청 헤더.
- `institution_leave_request_items`: 날짜별 신청구간과 근로계약·예정근로·휴게구간 계산 스냅샷.
- `institution_leave_usages`: 최종 승인된 휴가 사용 SSOT. 근태가 직접 조회한다.
- `institution_leave_ledger_entries`: 분 단위 휴가 잔액 증감 불변 원장. 잔액은 `SUM(amount_minutes)`로 계산한다.
- `institution_leave_audits`: 휴가 업무 변경 전용 감사 증적. 잔액과 현재상태 조회에는 사용하지 않는다.
- `institution_employment_contracts_break_schedules`: 근로계약 주간 일정별 실제 예정 휴게구간 SSOT. 기존 `break_minutes`는 합계 검증값으로 유지한다.
## 2026-08-06 — 자격·교육관리

| Table | Domain | Responsibility | Runtime contract |
|---|---|---|---|
| institution_qualifications_employee_records | Qualification | 직원별 자격·면허 원장 SSOT | 직원관리의 대표 자격증과 자격·교육관리가 동일 행을 조회·수정한다. `user_employees.representative_qualification_id`가 대표 행 하나만 연결하며, 만료·갱신일, 첨부, 검증 상태를 보존한다. |
| institution_qualifications_audits | QualificationAudit | 자격 등록·수정·삭제·검증·갱신 감사 | 상태 조회에 사용하지 않고 request_key 멱등성과 전후 스냅샷만 보존한다. |
| institution_educations_courses | EducationCourse | 교육과정 기준정보 SSOT | 교육 종류는 system_codes를 참조하고 법정·필수·수료증 정책을 관리한다. |
| institution_educations_employee_records | Education | 직원별 교육 이수 원장 SSOT | 교육기관·일시·시간·수료번호·유효기간·첨부를 보존한다. |
| institution_educations_audits | EducationAudit | 교육 이력 변경 감사 | 상태 조회에 사용하지 않고 request_key와 전후 스냅샷을 보존한다. |
### `system_work_teams`

- SSOT: 거래처 기반 작업팀 Master (`work-team`)
- 관계: 팀장은 `system_clients.id`, 정렬은 `sort_no ASC`
- 삭제 정책: soft delete → trash → restore 또는 ID 기반 dependency-guarded purge
- Actor: `created_by`, `updated_by`, `deleted_by` 원본을 `ActorHelper`로 표시명 확장

### `system_work_team_members`

- SSOT: 거래처 기반 작업팀 구성원
- 관계: `team_id` → `system_work_teams.id`, `client_id` → `system_clients.id`
- 작업팀 purge 시 직접 Dependency Consumer로 보호
### 신규 직원 인사 Baseline 기록 정책

- `user_employees`: 신규 직원의 현재 부서·직위·직무·재직상태 Master를 저장한다.
- `institution_job_assignments_department_histories`: 신규 직원의 최초 부서 기간이력을 저장한다.
- `institution_job_assignments_position_histories`: 신규 직원의 최초 직위·직책 기간이력을 저장한다.
- `institution_job_assignments_job_histories`: 직무가 지정된 신규 직원의 최초 직무 기간이력을 저장한다.
- `institution_job_assignments_employment_status_histories`: 신규 직원의 최초 재직상태 기간이력을 저장한다.
- 위 기록은 동일 직원 생성 트랜잭션에서 함께 생성되며 이번 작업에서 테이블·컬럼 변경은 없다.
