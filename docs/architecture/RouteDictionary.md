# Route Dictionary

## 2026-08-19 DataTables server-side 목록 transport

- 공용 `createDataTable({ serverSide: true })`가 호출하는 목록 Route는 POST body 조회로 통일한다. 근로계약, 직무·배치, 인사발령, 근태, 취업규칙, 상용근로소득, 결재함, 개인경비, 법정기준, 증빙원본, 거래, 전표, 거래·전표 증빙검색, 지급예정 목록이 대상이다.
- 같은 도메인의 detail, options, search-picker, metadata, trash, Excel 다운로드 등 짧고 단순한 읽기 Route는 GET을 유지한다. 목록 POST는 상태 변경 Route가 아니다.

## 2026-08-11 코드관리 단순화

- 코드관리 API는 `list`, `detail`, `groups`, `save`, `delete`, `reorder`만 유지한다. `delete`는 참조 무결성 확인 후 즉시 영구삭제하며 휴지통·복구·purge·Excel Route는 제공하지 않는다.

## 2026-08-06 법정기준관리

| Key prefix | URL prefix | Responsibility |
|---|---|---|
| `code.view` | `/dashboard/settings/standard/code` | 기준관리 > 코드관리. 기존 Controller·View·JS와 권한을 그대로 재사용한다. |
| `code.view` | `/api/settings/system/code/list` | 코드 목록 및 활성 코드그룹 선택지 조회 |
| `code.view` | `/api/settings/system/code/detail` | 코드 상세 조회 |
| `code.view` | `/api/settings/system/code/groups` | 코드그룹 조회 |
| `code.view` | `/api/settings/system/code/references` | 공용 Registry 기반 코드 참조내역 조회 |
| `code.save` | `/api/settings/system/code/save` | 코드 등록·수정·비활성화 및 그룹명 동기화 |
| `code.delete` | `/api/settings/system/code/delete` | 참조검사 후 단건 Hard Delete |
| `code.save` | `/api/settings/system/code/reorder` | 코드 전역 순번과 수정 Actor 저장 |
| `web.settings.statutory_standards.manage` | `/dashboard/settings/standard/statutory-standards` | 기준관리 > 법정기준관리 화면 |
| `api.settings.statutory_standards.*` | `/api/settings/statutory-standards/*` | 단일 기준행 CRUD·Resolver·근거파일 API |

- 법정기준 API는 `list`, `detail`, `options`, `save`, `delete`, `resolve`, `source-file`만 제공한다. 관련근거 등록·수정·삭제는 기준 저장 요청에 통합하며 `source-file`은 물리경로를 노출하지 않고 source ID로 파일을 연다.
- 단독 Excel 권한은 제공하지 않으며 기존 `api.settings.statutory_standards.excel`, `api.settings.statutory_standards.reorder` 권한은 비활성화한다.
- 법정기준 목록은 `api.settings.statutory_standards.view`(`/api/settings/statutory-standards/list`)가 단일 조회 경로다.
- `api.settings.statutory_standards.reorder`(`/api/settings/statutory-standards/reorder`)는 공용 행 드래그와 선택행 상·하 이동 결과를 `sort_no`에 저장한다. 별도 reorder 권한을 만들지 않고 `permission_key` 별칭으로 기존 save 권한을 검사한다. `delete`는 단건 `id`와 공용 선택삭제의 `ids[]`를 모두 받아 영구삭제한다.
- 현재 Runtime Route는 `view`, `detail`, `options`, `save`, `delete`, `reorder(save 별칭)`, `resolve`, `source_file`뿐이다. 과거 별도 Revision/Correction/History/Audit 설계의 `activate`, `audits`, `calculate`, `cancel`, `correct`, `expire`, `history`, `review`, `revise`, `revision_overview`, `source`, `source.correct`, `source.delete`, `source.file`, `source.list` 권한은 Runtime Route가 없는 비활성 Legacy이며 운영 DB 정리는 별도 승인 대상으로 분류한다.

## 2026-08-06 취업규칙·인사규정

| Key prefix | URL prefix | Responsibility |
|---|---|---|
| `web.institution.human_resources.employment_rules` | `/institution/human-resources/employment-rules` | 관리 화면 |
| `api.institution.human_resources.employment_rules.*` | `/api/institution/human-resources/employment-rules/*` | 목록·상세·이력·저장·개정·삭제·결재·회수·시행·Excel |

## Route Meta Normalization

- Current compatible keys
  - `key`
  - `name`
  - `description`
  - `category`
  - `auth`
  - `permissions`
  - `skip_permission`
  - `log`
- New compatible keys
  - `page`
  - `page_description`
  - `permission_name`
  - `permission_description`
- Backward compatibility
  - Existing `name`, `description`, `category` are not removed during migration.
  - `Router` and `PermissionRegistry` derive legacy breadcrumb/name values when only the new meta keys are provided.

## Persistence Mapping

- `auth_permissions.permission_key`
  - Runtime permission check key
- `auth_permissions.permission_name`
  - Stored from route `permission_name`
- `auth_permissions.description`
  - Stored from route `permission_description`
- `auth_permissions.category`
  - Stored from route `category` until the legacy column is retired
- `auth_permissions.page_key`
  - Resolved from route breadcrumb metadata and linked to `system_page_registry.page_key`

## Representative Routes

| Key | URL | Notes |
| --- | --- | --- |
| `web.institution.dashboard` | `/institution` | 대외기관업무 카테고리 진입 화면. |
| `web.institution.human_resources.employment_contracts` | `/institution/human-resources/employment-contracts` | 인사·노무관리의 근로계약 작성·개정·결재·휴지통 화면. |
| `web.institution.human_resources.personnel_actions` | `/institution/human-resources/personnel-actions` | 승진·부서이동·직위·직책변경·배치·휴복직·퇴직의 기안·결재·적용을 관리하는 인사발령 업무 화면. |
| `api.institution.human_resources.personnel_action.list` | `/api/institution/human-resources/personnel-action/list` | 인사발령 서버 검색·정렬·페이징 목록. |
| `api.institution.human_resources.personnel_action.detail` | `/api/institution/human-resources/personnel-action/detail` | 헤더·대상자·변경행·결재단계 상세. |
| `api.institution.human_resources.personnel_action.options` | `/api/institution/human-resources/personnel-action/options` | 인사발령 Modal 최초 개방 시 부서·직위·직무 입력옵션을 지연 조회한다. 직원·프로젝트와 시스템 코드는 공용 Picker·코드 API를 사용한다. |
| `api.institution.human_resources.personnel_action.save` | `/api/institution/human-resources/personnel-action/save` | 작성중 인사발령과 복수 대상자·변경행 원자 저장. |
| `api.institution.human_resources.personnel_action.reorder` | `/api/institution/human-resources/personnel-action/reorder` | 공용 순서변경의 선택 상·하 이동 및 드래그 결과 저장. |
| `api.institution.human_resources.personnel_action.submit` | `/api/institution/human-resources/personnel-action/submit` | 저장값 재검증 후 공용 전자결재 요청. |
| `api.institution.human_resources.personnel_action.withdraw` | `/api/institution/human-resources/personnel-action/withdraw` | 진행 중 결재요청 회수와 작성중 상태 복귀. |
| `api.institution.human_resources.personnel_action.apply` | `/api/institution/human-resources/personnel-action/apply` | 승인·효력일·충돌 조건을 검증한 공식 발령 적용. |
| `api.institution.human_resources.personnel_action.delete` | `/api/institution/human-resources/personnel-action/delete` | 결재 이력이 없는 작성중 발령 소프트삭제. |
| `api.institution.human_resources.personnel_action.trash` | `/api/institution/human-resources/personnel-action/trash` | 인사발령 휴지통 목록. |
| `api.institution.human_resources.personnel_action.restore` | `/api/institution/human-resources/personnel-action/restore` | 결재 이력이 없는 작성중 발령 복원. |
| `api.institution.human_resources.personnel_action.purge` | `/api/institution/human-resources/personnel-action/purge` | 휴지통의 작성중 인사발령 단건 완전삭제. |
| `api.institution.human_resources.personnel_action.purge-bulk` | `/api/institution/human-resources/personnel-action/purge-bulk` | 휴지통의 작성중 인사발령 선택 완전삭제. |
| `api.institution.human_resources.personnel_action.purge-all` | `/api/institution/human-resources/personnel-action/purge-all` | 휴지통의 작성중 인사발령 전체 완전삭제. |
| `web.institution.human_resources.attendance` | `/institution/human-resources/attendance` | 실제 출퇴근·일별 근태·월 마감 관리 화면. |
| `api.institution.human_resources.attendance.daily_list` | `/api/institution/human-resources/attendance/daily-list` | 일별 근태 서버 목록. |
| `api.institution.human_resources.attendance.monthly_list` | `/api/institution/human-resources/attendance/monthly-list` | 월별 근태 목록. |
| `api.institution.human_resources.attendance.exception_list` | `/api/institution/human-resources/attendance/exception-list` | 누락·이상 근태 목록. |
| `api.institution.human_resources.attendance.detail` | `/api/institution/human-resources/attendance/detail` | 직원 일별 근태 상세. |
| `api.institution.human_resources.attendance.recalculate` | `/api/institution/human-resources/attendance/recalculate` | 일별 근태 재계산. |
| `api.institution.human_resources.attendance.correct` | `/api/institution/human-resources/attendance/correct` | 관리자 직접 정정. |
| `api.institution.human_resources.attendance.close` | `/api/institution/human-resources/attendance/close` | 월 마감 revision 생성. |
| `api.institution.human_resources.attendance.reopen` | `/api/institution/human-resources/attendance/reopen` | 월 마감 해제. |
| `api.institution.human_resources.attendance.view_all` | `/api/institution/human-resources/attendance/scope/all` | 전체 직원 근태 조회범위 권한. |
| `api.institution.human_resources.attendance.view_self` | `/api/institution/human-resources/attendance/scope/self` | 로그인 사용자 연결 직원의 본인 조회범위 권한. |
| `api.institution.human_resources.attendance.clock_self` | `/api/institution/human-resources/attendance/clock/self` | 서버가 결정한 본인 직원의 웹 출퇴근 등록. |
| `api.institution.human_resources.attendance.clock_admin` | `/api/institution/human-resources/attendance/clock/admin` | 사유를 포함한 관리자 출퇴근 등록. |
| `api.institution.human_resources.attendance.clock_invalidate` | `/api/institution/human-resources/attendance/clock/invalidate` | 불변 원본의 업무 유효성 무효 처리 및 일별 재계산. |
| `api.institution.human_resources.attendance.closure_histories` | `/api/institution/human-resources/attendance/closure-histories` | 월 마감 과거 revision 조회. |
| `api.institution.human_resources.attendance.options` | `/api/institution/human-resources/attendance/options` | 근태 Form 선택옵션 조회. |
| `web.institution.human_resources.leave` | `/institution/human-resources/leave` | 휴가 신청·부여·잔액·원장 관리. |

### 휴가관리 API

- `/api/institution/human-resources/leave/list`, `/all-list`, `/detail`: 본인·전체 범위 목록과 상세 조회
- `/api/institution/human-resources/leave/save`, `/submit`, `/withdraw`, `/cancel`: 신청·결재·전체 취소
- `/api/institution/human-resources/leave/grant`, `/adjust`, `/type-save`: 관리자 부여·조정·정책
- `/api/institution/human-resources/leave/excel`: 권한 범위 Excel 다운로드
| `web.institution.human_resources.qualification_education` | `/institution/human-resources/qualification-education` | 자격·교육관리 카테고리 Placeholder. 자격증·교육·이력·법정교육 세부 기능은 후속 범위다. |
| `web.institution.human_resources.performance_evaluations` | `/institution/human-resources/performance-evaluations` | 목표와 평가를 통합한 성과평가관리 Placeholder. |
| `web.institution.human_resources.compensation_incentives` | `/institution/human-resources/compensation-incentives` | 보상·인센티브관리 Placeholder. |
| `web.institution.human_resources.employment_rules` | `/institution/human-resources/employment-rules` | 취업규칙·인사규정 Placeholder. |
| `web.institution.income_data.regular_employment` | `/institution/income-data/regular-employment` | 귀속월 Header·직원 Item 작성, 결재 및 회계연결 상태를 조회하는 상용근로소득 화면. |

- `/api/institution/income-data/regular-employment/list`, `/detail`, `/eligible-employees`: 상용근로소득 목록·상세와 귀속월 유효 계약/기간이력 기반 대상직원 조회
- `/api/institution/income-data/regular-employment/save`, `/submit`, `/withdraw`, `/delete`: Header·Item 저장, 공용 결재 상신·회수, 초안계열 삭제
| `web.institution.income_data.daily_employment` | `/institution/income-data/daily-employment` | 일용근로소득 Placeholder. |
| `web.institution.income_data.business_income` | `/institution/income-data/business-income` | 사업소득 Placeholder. |
| `web.institution.national_tax` | `/institution/national-tax` | 국세업무 Placeholder. |
| `web.institution.local_tax` | `/institution/local-tax` | 지방세업무 Placeholder. |
| `web.institution.social_insurance` | `/institution/social-insurance` | 4대보험업무 Placeholder. |
| `web.institution.tax_agent` | `/institution/tax-agent` | 세무사업무 Placeholder. |
| `web.institution.filing_history` | `/institution/filing-history` | 신고이력 Placeholder. |
| `web.ledger.funds.account_balances` | `/ledger/funds` | 자금관리의 canonical 첫 화면. 기존 계좌잔액현황 권한·페이지 키를 재사용해 별도 권한 또는 DB 변경 없이 유형별 보유자금과 계좌 목록을 제공한다. |
| `web.ledger.funds.account_balances_legacy` | `/ledger/funds/account-balances` | 기존 링크와 사용자 즐겨찾기 호환용 주소. 별도 권한을 만들지 않고 canonical `/ledger/funds`로 이동한다. |
| `web.ledger.funds.bank_transactions` | `/ledger/funds/account-transactions` | 계좌별거래내역 canonical route. 계좌 미지정 시 전체 운영계좌의 증빙원본 입출금(은행)을 조회하고, `bank_account_id` 지정 시 해당 계좌로 범위를 좁힌다. Registry·권한·사용자 설정은 `ledger.funds.bank_transactions`를 사용한다. |
| `web.settings.organization.role_permissions` | `/dashboard/settings/organization/permission-assignment` | Canonical permission-assignment page route with legacy permission key compatibility |
| `web.settings.organization.approval` | `/dashboard/settings/organization/approval-template` | Canonical approval-template page route with legacy permission key compatibility |
| `web.settings.organization.employees` | `/dashboard/settings/organization/employee` | Canonical employee page route with legacy route compatibility |
| `web.settings.organization.departments` | `/dashboard/settings/organization/department` | Canonical department page route with legacy route compatibility |
| `api.settings.organization.department.list` | `/api/settings/organization/department/list` | 부서 목록과 공용 Actor 표시 필드를 조회하며 응답 대기 중 세션 잠금을 유지하지 않는다. |
| `api.settings.organization.department.detail` | `/api/settings/organization/department/detail` | 외부·호환 Consumer를 위해 유지하는 부서 상세 조회 API이며 응답 대기 중 세션 잠금을 유지하지 않는다. |
| `api.settings.organization.department.save` | `/api/settings/organization/department/save` | 부서 생성·수정과 Service 저장 검증을 수행한다. |
| `api.settings.organization.department.delete` | `/api/settings/organization/department/delete` | 알려진 모든 부서 참조가 0건인 오등록 부서만 Guarded Hard Delete한다. |
| `api.settings.organization.department.reorder` | `/api/settings/organization/department/reorder` | 공용 순서변경 계약으로 `sort_no`를 저장한다. |
| `web.settings.organization.positions` | `/dashboard/settings/organization/position` | Canonical position page route with legacy route compatibility |
| `api.settings.position.list` | `/api/settings/organization/position/list` | 통합 직위·직책 목록과 공용 Actor 표시 필드를 조회하며 응답 대기 중 세션 잠금을 유지하지 않는다. |
| `api.settings.position.detail` | `/api/settings/organization/position/detail` | 외부·호환 Consumer를 위해 유지하는 통합 직위·직책 상세 조회 API이며 응답 대기 중 세션 잠금을 유지하지 않는다. |
| `api.settings.position.save` | `/api/settings/organization/position/save` | 통합 직위·직책 생성·수정과 Service 저장 검증을 수행한다. |
| `api.settings.position.delete` | `/api/settings/organization/position/delete` | 알려진 모든 직위·직책 ID 참조가 0건인 오등록 행만 Guarded Hard Delete한다. |
| `api.settings.position.reorder` | `/api/settings/organization/position/reorder` | 공용 순서변경 계약으로 `sort_no`를 저장한다. |
| `web.settings.organization.roles` | `/dashboard/settings/organization/role` | Canonical role page route with legacy route compatibility |
| `api.settings.organization.role.list` | `/api/settings/organization/role/list` | Returns the role master list with standard Actor display fields |
| `api.settings.organization.role.detail` | `/api/settings/organization/role/detail` | Returns one role master row by ID |
| `api.settings.organization.role.save` | `/api/settings/organization/role/save` | Creates or updates a role through protected-role and required-field policies |
| `api.settings.organization.role.delete` | `/api/settings/organization/role/delete` | Performs dependency-guarded transactional hard deletion for a general role |
| `api.settings.organization.role.reorder` | `/api/settings/organization/role/reorder` | Saves role display ordering |
| `api.settings.permission.delete` | `/api/settings/organization/permission/delete` | Hard deletes a permission row and clears linked `auth_role_permissions` mappings |
| `api.settings.rolepermission.list` | `/api/settings/organization/role-permission/list` | Returns the reusable active Permission Master tree in `master` mode or only the selected role's mapping rows in `selection` mode, avoiding repeated Master transfer |
| `api.settings.rolepermission.assign` | `/api/settings/organization/role-permission/save` | Saves the complete selected permission set in one JSON batch transaction; the legacy single assign/remove contracts are not exposed |
| `api.settings.rolepermission.reorder` | `/api/settings/organization/role-permission/reorder` | Saves the flattened permission display order into `auth_permissions.sort_no` |
| `api.settings.user_permission.list` | `/api/settings/organization/user-permission/list` | 사용자와 역할·직원 상태, 권한방식 및 개인권한 수를 조회한다. |
| `api.settings.user_permission.detail` | `/api/settings/organization/user-permission/detail` | Permission Master와 역할·개인·최종권한, Mode, 편집정책 및 `state_version`을 반환한다. |
| `api.settings.user_permission.save` | `/api/settings/organization/user-permission/save` | 사용자 한 명의 Mode와 개인 Permission 전체 Set을 Guard·낙관적 동시성 검사 후 Audit과 한 트랜잭션으로 저장한다. |
| `api.account.sub_accounts.list` | `/api/account/sub-accounts` | Shared `SubChartAccountController@apiList` endpoint used by journal, evidence, and data-create screens for account-dependent sub-account lookup |
| `api.ledger.sub_account.list` | `/api/ledger/sub-account/list` | Shared `SubChartAccountController@apiList` endpoint used by the ledger account screen for sub-account management lookup |
| `web.ledger.settings.accounts` | `/ledger/settings/accounts` | Canonical ledger account basic-info page route handled by `ChartAccountController@index` |
| `web.ledger.data.index` | `/ledger/data` | Entry route for evidence source pages; redirects to the first active `IMPORT_TYPE` page in code-order |
| `web.ledger.data.list` | `/ledger/data/list` | Legacy evidence-source entry route; normalizes `import_type` queries into dedicated type-page URLs |
| `web.ledger.data.bank-transactions` | `/ledger/data/bank-transactions` | Dedicated evidence-source page route for `BANK_TRANSACTION`. 모든 Type 페이지는 공통 `web.ledger.data.index` 조회 권한을 검사한다. |
| `web.ledger.data.tax-invoices` | `/ledger/data/tax-invoices` | Dedicated evidence-source page route for `TAX_INVOICE` |
| `web.ledger.data.manual-tax-invoices` | `/ledger/data/manual-tax-invoices` | Dedicated evidence-source page route for `TAX_INVOICE_MANUAL` |
| `web.ledger.data.card-hometax` | `/ledger/data/card-hometax` | Dedicated evidence-source page route for `CARD_HOMETAX` |
| `web.ledger.data.card-approvals` | `/ledger/data/card-approvals` | Dedicated evidence-source page route for `CARD_APPROVAL` |
| `web.ledger.data.card-statements` | `/ledger/data/card-statements` | Dedicated evidence-source page route for `CARD_STATEMENT` |
| `web.ledger.data.cash-receipts` | `/ledger/data/cash-receipts` | Dedicated evidence-source page route for `CASH_RECEIPT` |
| `web.ledger.data.cash-receipt-purchases` | `/ledger/data/cash-receipt-purchases` | Legacy route alias normalized to `CASH_RECEIPT`; purchase/sales are represented by `transaction_direction`, not separate `IMPORT_TYPE` values. |
| `web.ledger.data.cash-receipt-sales` | `/ledger/data/cash-receipt-sales` | Legacy route alias normalized to `CASH_RECEIPT`; purchase/sales are represented by `transaction_direction`, not separate `IMPORT_TYPE` values. |
| `web.ledger.data.import-invoices` | `/ledger/data/import-invoices` | Dedicated evidence-source page route for `IMPORT_INVOICE` |
| `web.ledger.data.shopping-orders` | `/ledger/data/shopping-orders` | Dedicated evidence-source page route for `SHOPPING_ORDER` |
| `web.ledger.data.payroll-withholdings` | `/ledger/data/payroll-withholdings` | Dedicated evidence-source page route for `PAYROLL_WITHHOLDING` |
| `web.ledger.data.business-data` | `/ledger/data/business-data` | Dedicated evidence-source page route for `BUSINESS_DATA` |
| `web.ledger.data.payroll` | `/ledger/data/payroll` | Dedicated evidence-source page route for `PAYROLL` |
| `web.ledger.data.business-income` | `/ledger/data/business-income` | Dedicated evidence-source page route for `BUSINESS_INCOME` |
| `web.ledger.data.employee-expenses` | `/ledger/data/employee-expenses` | Dedicated evidence-source page route for `EMPLOYEE_EXPENSE` |
| `web.ledger.data.employee-personal-expenses` | `/ledger/data/employee-personal-expenses` | Shared evidence page for canonical `EMPLOYEE_EXPENSE_PERSONAL`; 개인경비 최종승인 원본을 조회한다. |
| `web.ledger.data.construction` | `/ledger/data/construction` | Dedicated evidence-source page route for `CONSTRUCTION` |
| `api.ledger.account.list` | `/api/ledger/account/list` | Canonical ledger account list endpoint handled by `ChartAccountController@apiList` |
| `web.ledger.journal_rules` | `/ledger/settings/journal-rules` | Canonical ledger journal-rule basic-info page route handled by `JournalRuleController@index` |
| `api.ledger.journal_rules.list` | `/api/ledger/journal-rules/list` | Canonical ledger journal-rule list endpoint handled by `JournalRuleController@apiList` |
| `web.settings.base-info.brand_logo` | `/dashboard/settings/base-info/brand` | Canonical brand page route with legacy permission key compatibility |
| `api.settings.base-info.brand.list` | `/api/settings/base-info/brand/list` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.detail` | `/api/settings/base-info/brand/detail` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.active-type` | `/api/settings/base-info/brand/active-type` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.save` | `/api/settings/base-info/brand/save` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.delete` | `/api/settings/base-info/brand/purge` | Initial rollout target for new permission metadata |
| `api.settings.base-info.brand.status` | `/api/settings/base-info/brand/updatestatus` | Initial rollout target for new permission metadata |
| `web.settings.base-info.cover` | `/dashboard/settings/base-info/cover` | Canonical cover page route |
| `web.settings.base-info.clients` | `/dashboard/settings/base-info/client` | Canonical client page route with legacy route key compatibility |
| `web.settings.base-info.projects` | `/dashboard/settings/base-info/project` | Canonical project page route with legacy route key compatibility |
| `api.settings.base-info.project.list` | `/api/settings/base-info/project/list` | 프로젝트 목록 조회 |
| `api.settings.base-info.project.detail` | `/api/settings/base-info/project/detail` | 프로젝트 상세 조회 |
| `api.settings.base-info.project.search-picker` | `/api/settings/base-info/project/search-picker` | 프로젝트 Picker 조회 |
| `api.settings.base-info.project.distinct-values` | `/api/settings/base-info/project/distinct-values` | 프로젝트 기존 입력값 조회 |
| `api.settings.base-info.project.save` | `/api/settings/base-info/project/save` | 프로젝트 저장 |
| `api.settings.base-info.project.delete` | `/api/settings/base-info/project/delete` | 프로젝트 Soft Delete |
| `api.settings.base-info.project.trash.list` | `/api/settings/base-info/project/trash` | 프로젝트 휴지통 조회 |
| `api.settings.base-info.project.restore` | `/api/settings/base-info/project/restore` | 프로젝트 복원 |
| `api.settings.base-info.project.restore-bulk` | `/api/settings/base-info/project/restore-bulk` | 프로젝트 선택 복원 |
| `api.settings.base-info.project.restore-all` | `/api/settings/base-info/project/restore-all` | 프로젝트 전체 복원 |
| `api.settings.base-info.project.purge` | `/api/settings/base-info/project/purge` | 프로젝트 참조 검사 후 단건 영구삭제 |
| `api.settings.base-info.project.purge-bulk` | `/api/settings/base-info/project/purge-bulk` | 프로젝트 참조 검사 후 선택 영구삭제 |
| `api.settings.base-info.project.purge-all` | `/api/settings/base-info/project/purge-all` | 프로젝트 참조 검사 후 휴지통 전체 부분성공 영구삭제 |
| `api.settings.base-info.project.reorder` | `/api/settings/base-info/project/reorder` | 프로젝트 정렬 저장 |
| `api.settings.base-info.project.template` | `/api/settings/base-info/project/template` | 프로젝트 Excel 양식 다운로드 |
| `api.settings.base-info.project.excel-upload` | `/api/settings/base-info/project/excel-upload` | 프로젝트 Excel 업로드 |
| `api.settings.base-info.project.excel` | `/api/settings/base-info/project/download` | 프로젝트 목록 Excel 다운로드 |
| `web.settings.base-info.accounts` | `/dashboard/settings/base-info/bank-account` | Canonical bank-account page route with legacy route key compatibility |
| `api.settings.base-info.bank-account.list` | `/api/settings/base-info/bank-account/list` | 계좌 목록 조회 |
| `api.settings.base-info.bank-account.detail` | `/api/settings/base-info/bank-account/detail` | 계좌 상세 조회 |
| `api.settings.base-info.bank-account.search-picker` | `/api/settings/base-info/bank-account/search-picker` | 활성 계좌 검색 선택 |
| `api.settings.base-info.bank-account.save` | `/api/settings/base-info/bank-account/save` | 계좌 저장 및 통장사본 안전 처리 |
| `api.settings.base-info.bank-account.delete` | `/api/settings/base-info/bank-account/delete` | 계좌 Soft Delete |
| `api.settings.base-info.bank-account.trash.list` | `/api/settings/base-info/bank-account/trash` | 계좌 휴지통 조회 |
| `api.settings.base-info.bank-account.restore` | `/api/settings/base-info/bank-account/restore` | 계좌 단건 복원 |
| `api.settings.base-info.bank-account.restore-bulk` | `/api/settings/base-info/bank-account/restore-bulk` | 계좌 선택 복원 |
| `api.settings.base-info.bank-account.restore-all` | `/api/settings/base-info/bank-account/restore-all` | 계좌 전체 복원 |
| `api.settings.base-info.bank-account.purge` | `/api/settings/base-info/bank-account/purge` | 계좌 참조 검사 후 단건 영구삭제 |
| `api.settings.base-info.bank-account.purge-bulk` | `/api/settings/base-info/bank-account/purge-bulk` | 계좌 참조 검사 후 선택 부분성공 영구삭제 |
| `api.settings.base-info.bank-account.purge-all` | `/api/settings/base-info/bank-account/purge-all` | 계좌 휴지통 전체 부분성공 영구삭제 |
| `api.settings.base-info.bank-account.reorder` | `/api/settings/base-info/bank-account/reorder` | 계좌 정렬 저장 |
| `api.settings.base-info.bank-account.template` | `/api/settings/base-info/bank-account/template` | 계좌 Excel 양식 다운로드 |
| `api.settings.base-info.bank-account.excel-upload` | `/api/settings/base-info/bank-account/excel-upload` | 계좌 Excel 업로드 |
| `api.settings.base-info.bank-account.excel` | `/api/settings/base-info/bank-account/download` | 계좌 목록 Excel 다운로드 |
| `web.settings.base-info.cards` | `/dashboard/settings/base-info/card` | Canonical card page route with legacy route key compatibility |
| `api.settings.base-info.card.list` | `/api/settings/base-info/card/list` | 카드 목록 조회 |
| `api.settings.base-info.card.detail` | `/api/settings/base-info/card/detail` | 카드 상세 조회 |
| `api.settings.base-info.card.search-picker` | `/api/settings/base-info/card/search-picker` | 공용 카드 Picker 검색 |
| `api.settings.base-info.card.save` | `/api/settings/base-info/card/save` | 카드 등록·수정 및 이미지 생명주기 처리 |
| `api.settings.base-info.card.delete` | `/api/settings/base-info/card/delete` | 카드 Soft Delete |
| `api.settings.base-info.card.trash.list` | `/api/settings/base-info/card/trash` | 카드 휴지통 조회 |
| `api.settings.base-info.card.restore` | `/api/settings/base-info/card/restore` | 카드 단건 복원 |
| `api.settings.base-info.card.restore-bulk` | `/api/settings/base-info/card/restore-bulk` | 카드 선택 복원 |
| `api.settings.base-info.card.restore-all` | `/api/settings/base-info/card/restore-all` | 카드 전체 복원 |
| `api.settings.base-info.card.purge` | `/api/settings/base-info/card/purge` | 카드 참조 검사 후 단건 영구삭제 |
| `api.settings.base-info.card.purge-bulk` | `/api/settings/base-info/card/purge-bulk` | 카드 참조 검사 후 선택 부분성공 영구삭제 |
| `api.settings.base-info.card.purge-all` | `/api/settings/base-info/card/purge-all` | 카드 휴지통 전체 부분성공 영구삭제 |
| `api.settings.base-info.card.reorder` | `/api/settings/base-info/card/reorder` | 카드 `sort_no` 정렬 저장 |
| `api.settings.base-info.card.template` | `/api/settings/base-info/card/template` | 카드 Excel 양식 다운로드 |
| `api.settings.base-info.card.excel-upload` | `/api/settings/base-info/card/excel-upload` | 카드 Excel 업로드 |
| `api.settings.base-info.card.download` | `/api/settings/base-info/card/download` | 카드 목록 Excel 다운로드 |
| `api.settings.base-info.cover.list` | `/api/settings/base-info/cover/list` | Canonical cover permission metadata |
| `api.settings.base-info.cover.detail` | `/api/settings/base-info/cover/detail` | Canonical cover permission metadata |
| `api.settings.base-info.cover.save` | `/api/settings/base-info/cover/save` | Canonical cover permission metadata |
| `api.settings.base-info.cover.delete` | `/api/settings/base-info/cover/delete` | Canonical cover permission metadata |
| `api.settings.base-info.cover.reorder` | `/api/settings/base-info/cover/reorder` | Canonical cover permission metadata |
| `api.settings.system.database.status` | `/api/settings/system/database/status` | Returns the current Active DB plus Primary/Secondary online status for the NAS-based backup screen |
| `api.settings.system.database.switch-active` | `/api/settings/system/database/switch-active` | Manually switches the Active DB between Primary 3306 and Secondary 3307 by updating `db_replication.php` active_target |
| `api.settings.system.database.sync` | `/api/settings/system/database/sync` | Applies the latest Primary backup to Secondary DB through the PDO-based sync engine |
| `api.settings.system.database.sync-info` | `/api/settings/system/database/sync-info` | Returns the latest PDO-based DB sync result, file, timestamp, and last error for the sync card |
| `api.settings.system.database.restore` | `/api/settings/system/database/restore` | Applies the selected SQL backup file to the current Active DB through the PDO-based restore engine |
| `api.settings.system.database.restore-info` | `/api/settings/system/database/restore-info` | Returns the latest Active DB restore result, file, timestamp, and last error for the restore card |
| `api.settings.system.database.activity-log` | `/api/settings/system/database/activity-log` | Returns the combined backup, sync, and restore log view for the database backup screen |
| `api.settings.system.data_table_columns` | `/api/settings/system/data-table-columns` | Returns canonical DB physical-column metadata for shared DataTable table-settings screens so column order, required flags, and visibility defaults come from DB SSOT instead of page JS columns |
| `api.settings.system.user-settings.detail` | `/api/settings/system/user-settings/detail` | Returns the current user's persisted page setting payload from `system_user_settings` by `page_key` and `setting_type`, including an `exists` flag so common UI can distinguish missing settings from empty JSON |
| `api.settings.system.user-settings.save` | `/api/settings/system/user-settings/save` | Saves the current user's page setting payload into `system_user_settings` so DB-backed UI preferences can replace browser storage for `TABLE`, `VIEW`, `EXCEL_UPLOAD`, and `EXCEL_DOWNLOAD` |
| `api.settings.system.user-settings.delete` | `/api/settings/system/user-settings/delete` | Deletes the current user's persisted page setting row so the next page load regenerates defaults from the current DB schema and common view defaults |
| `api.settings.approval.template.reorder` | `/api/settings/organization/approval/template/reorder` | Persists the complete approval-template order in one locked transaction and reloads the shared DataTable without changing its saved VIEW state |
| `api.settings.approval.step.reorder` | `/api/settings/organization/approval/step/reorder` | Persists one template's complete `template_id + sort_no` step order in one locked transaction |
| `api.settings.approval.template.list` | `/api/settings/organization/approval/template/list` | Returns approval-template headers with standard Actor display fields for the shared DataTable |
| `api.settings.approval.template.save` | `/api/settings/organization/approval/template/save` | Creates an inactive template draft or validates and updates an existing template, including full-flow activation guards |
| `api.settings.approval.template.delete` | `/api/settings/organization/approval/template/delete` | Hard-deletes an unused template and its child steps while blocking templates referenced by approval requests |
| `api.settings.approval.step.list` | `/api/settings/organization/approval/step/list` | Returns all physical approval-template step fields and display references for one template |
| `api.settings.approval.step.save` | `/api/settings/organization/approval/step/save` | Creates or updates a step only while its template is inactive and validates the role/approver contract |
| `api.settings.approval.step.delete` | `/api/settings/organization/approval/step/delete` | Deletes a step only while its template is inactive, preserving request-step snapshots |
| `api.auth.password.change_later` | `/api/auth/password/change-later` | Completes the password-expired guidance flow without changing the password by restoring a normal authenticated session and redirecting to the dashboard |
| `api.ledger.transaction.evidence_search` | `/api/ledger/transaction/evidence-search` | 거래입력 Evidence Selection Module 조회 경로. 활성 `TRANSACTION` 대상 `ledger_evidence_links`가 존재하는 증빙과 현재 거래 모달에 추가한 `exclude_evidences`만 제외한다. `VOUCHER` 링크는 거래 추가목록 제외 조건이 아니며, 선택 결과는 TransactionCrudService의 `linked_evidences[]` 계약으로 전달한다. |
| `web.ledger.transactions.input` | `/ledger/transactions/input` | 거래입력의 유일한 canonical 화면 Route다. `/ledger/transactions`, `/ledger/transactions/create`, `/ledger/transaction`, `/ledger/transaction/create`, `/site/entry`, `/site/entry/create`는 호환 목적의 canonical redirect만 수행한다. |
| `api.ledger.transaction.*` | `/api/ledger/transaction/{list,reorder,detail,evidence-search,file,save,delete,trash,restore,restore-bulk,restore-all,purge,purge-bulk,purge-all}` | 거래입력의 현행 조회·수동 Draft 저장·Evidence 연결·목록 순서저장·파일·휴지통 생명주기 API다. `reorder`는 활성 거래의 `sort_no`만 저장하며 Header·Item·Settlement Excel API는 제공하지 않는다. |
| `api.ledger.voucher.evidence_search` | `/api/ledger/voucher/evidence-search` | 전표입력 증빙 추가 공용 DataTable 서버 조회 경로. 활성 `VOUCHER` 대상 `ledger_evidence_links`가 존재하는 증빙과 현재 전표 모달에 추가한 `exclude_evidences`만 제외한다. `TRANSACTION` 링크는 전표 추가목록 제외 조건이 아니다. `evidence_type`, `import_type`, `exclude_evidences`, `q`, `page`, `per_page`, column-key 기반 `sort_field`/`sort_direction`을 받고 DataTables draw/전체 건수와 활성 자료유형 필터를 반환한다. |
| `api.ledger.voucher.evidence_recommendations` | `/api/ledger/voucher/evidence-recommendations` | 전표 모달에 현재 추가된 `(import_type, evidence_id)` 전체를 분석한다. 건별 `connection_status`, `recommendation_status`, `reason_code`, 사용자 안내문을 반환하여 증빙 연결 가능 여부와 분개추천 성공·없음을 분리하고, 연결 가능한 은행 자금증빙은 추천이 없어도 유지한다. 분개규칙·최근사용·학습패턴·거래처 기준계정에 따른 선택형 추천 후보 2~3개와 표준 `line.refs[]`를 반환할 수 있으나 전표·라인·링크를 저장하거나 추천을 자동 적용하지 않는다. |
| `api.ledger.journal_rules.*` | `/api/ledger/journal-rules/{list,detail,save,status,reorder,delete,trash,restore,restore-bulk,restore-all,purge,purge-bulk,purge-all}` | 분개규칙 조회·수동 보정·상태·정렬·휴지통 생명주기 API다. Excel Route는 운영하지 않으며, 사용된 Rule은 purge하지 않는다. |
| `api.ledger.voucher.request_review` | `/api/ledger/voucher/request-review` | Moves a voucher from `DRAFT` to `REVIEW_REQUESTED` as the explicit review-request workflow step |
| `api.ledger.voucher.cancel_review_request` | `/api/ledger/voucher/cancel-review-request` | Returns a voucher from `REVIEW_REQUESTED` to `DRAFT` when the review request is cancelled |
| `api.ledger.voucher.complete_review` | `/api/ledger/voucher/complete-review` | `api.ledger.voucher.review` 권한으로 전표를 `REVIEW_REQUESTED`에서 `REVIEWED`로 전환 |
| `api.ledger.voucher.post` | `/api/ledger/voucher/post` | `api.ledger.voucher.post` 권한으로 전표를 `REVIEWED`에서 `POSTED`로 전기 |
| `api.ledger.voucher.reject` | `/api/ledger/voucher/reject` | `api.ledger.voucher.review` 권한으로 검토요청 전표를 반려 |
| `api.ledger.voucher.cancel_complete_review` | `/api/ledger/voucher/cancel-complete-review` | `api.ledger.voucher.review_cancel` 권한으로 검토완료 취소 |
| `api.ledger.voucher.reverse` | `/api/ledger/voucher/reverse` | `api.ledger.voucher.reverse` 권한으로 전기완료 전표의 취소전표 작성 |

## Evidence Metadata Administration

| Key | URL | Purpose |
| --- | --- | --- |
| `api.external.employee.list` | `/api/external/employees/list` | Compatibility employee-list endpoint handled by `ExternalApiController@employees`; `/api/external/employees` remains the existing alias |
| `web.ledger.evidence_metadata` | `/ledger/data/evidence-metadata` | Evidence metadata header and semantic-column mapping administration page. |
| `api.ledger.evidence_metadata.list` | `/api/ledger/evidence-metadata/list` | Lists evidence metadata header policies. |
| `api.ledger.evidence.type_policies` | `/api/ledger/evidence/type-policies` | Returns the active evidence-type UI policies used by the original evidence editor in every host screen. |
| `api.ledger.evidence_metadata.detail` | `/api/ledger/evidence-metadata/detail` | Returns one header policy with child semantic mappings. |
| `api.ledger.evidence_metadata.save` | `/api/ledger/evidence-metadata/save` | Transactionally creates or updates the header and child mappings. |
| `api.ledger.evidence_metadata.delete` | `/api/ledger/evidence-metadata/delete` | Moves active evidence policies to the shared soft-delete trash by setting header `deleted_at` and `deleted_by`; detail rows remain unchanged. |
| `api.ledger.evidence_metadata.trash` | `/api/ledger/evidence-metadata/trash` | Lists soft-deleted evidence-policy headers for the shared trash modal. |
| `api.ledger.evidence_metadata.restore` | `/api/ledger/evidence-metadata/restore` | Restores one evidence-policy header without changing detail mappings. |
| `api.ledger.evidence_metadata.restore_bulk` | `/api/ledger/evidence-metadata/restore-bulk` | Restores selected evidence-policy headers. |
| `api.ledger.evidence_metadata.restore_all` | `/api/ledger/evidence-metadata/restore-all` | Restores all evidence-policy headers in trash. |
| `api.ledger.evidence_metadata.purge` | `/api/ledger/evidence-metadata/purge` | Permanently deletes one trashed header; DB FK CASCADE deletes its detail mappings. |
| `api.ledger.evidence_metadata.purge_bulk` | `/api/ledger/evidence-metadata/purge-bulk` | Permanently deletes selected trashed headers. |
| `api.ledger.evidence_metadata.reorder` | `/api/ledger/evidence-metadata/reorder` | Saves active evidence-policy row order. |
| `api.ledger.evidence_metadata.source_columns` | `/api/ledger/evidence-metadata/source-columns` | Lists actual columns for the selected evidence source table. |
| `api.ledger.evidence_metadata.recommend` | `/api/ledger/evidence-metadata/recommend` | Recommends header policy and semantic mappings from the selected import type and actual DB schema without per-import-type branching. |
| `api.ledger.evidence_metadata.options` | `/api/ledger/evidence-metadata/options` | Returns active import types whose convention-based source tables actually exist in the current DB. |

## SSOT Alias Notes

- `brand`
  - standard domain: `brand`
  - legacy aliases isolated: `brand_logo`, `brand-logo`
  - canonical page key: `settings.base_info.brand`
- `cover`
  - standard domain: `cover`
  - legacy aliases isolated: `coverimage`, `cover-image`, `cover_image`
  - canonical page key: `settings.base_info.cover`
- `client`
  - standard domain: `client`
  - legacy aliases isolated: `clients`
  - canonical page key compatibility: `settings.base_info.clients`
- `project`
  - standard domain: `project`
  - legacy aliases isolated: `projects`
  - canonical page key compatibility: `settings.base_info.projects`
- `bank-account`
  - standard domain: `bank-account`
  - legacy aliases isolated: `bank-accounts`, `bank.account`
  - canonical page key compatibility: `settings.base_info.bank_accounts`
  - canonical URL: `/dashboard/settings/base-info/bank-account`; `/dashboard/settings/base-info/bank-accounts` remains redirect-only compatibility
- `card`
  - standard domain: `card`
  - legacy aliases isolated: `cards`
  - canonical page key compatibility: `settings.base_info.cards`
- `work-team`
  - standard domain: `work-team`
  - legacy aliases isolated: `work-teams`, `work_team`
  - canonical page key: `settings.base_info.work_teams`
  - display name: `팀`
  - API contract: list/detail/save/delete/trash/restore/purge/reorder/template/excel/excel-upload under `/api/settings/base-info/work-team/*`
- `permission-assignment`
  - standard domain: `permission-assignment`
  - legacy aliases isolated: `role_permissions`, `role-permission`, `rolepermission`
  - canonical page key compatibility: `settings.organization.role_permissions`
- `approval-template`
  - standard domain: `approval-template`
  - legacy aliases isolated: `approval`, `approval/template`, `approval/step`, `approval.templates`
  - canonical page key compatibility: `settings.organization.approval`
- `employee`
  - standard domain: `employee`
  - legacy aliases isolated: `employees`
  - canonical page key compatibility: `settings.organization.employees`
- `department`
  - standard domain: `department`
  - legacy aliases isolated: `departments`, `dept`
  - canonical page key compatibility: `settings.organization.departments`
- `position`
  - standard domain: `position`
  - legacy aliases isolated: `positions`, `positions_modal`
  - canonical page key compatibility: `settings.organization.positions`
- `role`
  - standard domain: `role`
  - legacy aliases isolated: `roles`
  - canonical page key compatibility: `settings.organization.roles`

## Current Scope

- Route metadata still supports legacy consumers such as:
  - `PermissionRegistry` sync
  - breadcrumb rendering
  - sitemap rendering
  - permission screen grouping fallback
- The June 2026 permission refactor keeps runtime permission checks on `permission_key` and assignment writes on `permission_id`.

## Personal expense approval routes

| Key | URL | Responsibility |
|---|---|---|
| `web.approval.inbox` | `/approval/status` | User-scoped integrated approval inbox; the retained URL replaces the former static approval-status screen |
| `api.approval.inbox.list` | `/api/approval/inbox/list` | 지정결재자 및 현재 적격한 역할 공동결재자를 기준으로 분류한 처리할 문서·진행·완료·반려·상신 목록 |
| `api.approval.inbox.detail` | `/api/approval/inbox/detail` | 발의자, 신청 문서, 단계 스냅샷, 역할 대기 상태, 실제 처리자 및 재상신 이력 상세 |
| `api.approval.inbox.act` | `/api/approval/inbox/act` | 현재 지정결재자 또는 적격 역할 사용자의 원자적 승인·반려 처리 |
| `web.approval.personal-expense` | `/approval/personal-expense` | Owner-scoped personal-expense application page; exposed in the electronic-approval sidebar |
| `api.approval.personal-expense.list` | `/api/approval/personal-expense/list` | Current employee application list |
| `api.approval.personal-expense.detail` | `/api/approval/personal-expense/detail` | Current employee application detail |
| `api.approval.personal-expense.save` | `/api/approval/personal-expense/save` | Draft/rejected/withdrawn application save |
| `api.approval.personal-expense.reorder` | `/api/approval/personal-expense/reorder` | Current employee application row ordering |
| `api.approval.personal-expense.delete` | `/api/approval/personal-expense/delete` | Owner-scoped soft delete limited to draft/rejected/withdrawn documents |
| `api.approval.personal-expense.trash` | `/api/approval/personal-expense/trash` | Current employee's soft-deleted applications with retained item detail |
| `api.approval.personal-expense.restore` | `/api/approval/personal-expense/restore` | Owner-scoped single restore |
| `api.approval.personal-expense.restore-bulk` | `/api/approval/personal-expense/restore-bulk` | Transactional owner-scoped selected restore |
| `api.approval.personal-expense.restore-all` | `/api/approval/personal-expense/restore-all` | Transactional restore of all current-employee trash rows |
| `api.approval.personal-expense.purge` | `/api/approval/personal-expense/purge` | Trash-only permanent deletion of items then header after approval/evidence guards |
| `api.approval.personal-expense.purge-bulk` | `/api/approval/personal-expense/purge-bulk` | Transactional selected permanent deletion |
| `api.approval.personal-expense.purge-all` | `/api/approval/personal-expense/purge-all` | Transactional permanent deletion of all eligible current-employee trash rows |
| `api.approval.personal-expense.save-submit` | `/api/approval/personal-expense/save-submit` | 초안 저장과 발의 자동완료 결재요청을 하나의 트랜잭션으로 처리 |
| `api.approval.personal-expense.withdraw` | `/api/approval/personal-expense/withdraw` | Requester withdrawal |
| `api.approval.personal-expense.template` | `/api/approval/personal-expense/template` | Shared Excel Manager template for the editable personal-expense item grid |
| `api.approval.personal-expense.excel` | `/api/approval/personal-expense/excel` | Shared Excel Manager download of the current personal-expense item grid rows |
| `api.approval.personal-expense.excel-upload` | `/api/approval/personal-expense/excel-upload` | Validates uploaded item rows and returns a full replacement grid without persisting or registering new clients |

## Employment contract routes

| Key | URL | Responsibility |
|---|---|---|
| `api.institution.human_resources.employment_contract.list` | `/api/institution/human-resources/employment-contract/list` | 근로계약 목록 |
| `api.institution.human_resources.employment_contract.detail` | `/api/institution/human-resources/employment-contract/detail` | 계약 헤더·요일별 소정근로 일정·급여조건 상세 |
| `api.institution.human_resources.employment_contract.options` | `/api/institution/human-resources/employment-contract/options` | 신규·상세 Modal 최초 개방 시 계약 입력정책·급여항목 지연 조회 |
| `api.institution.human_resources.employment_contract.save` | `/api/institution/human-resources/employment-contract/save` | 계약 헤더·요일별 소정근로 일정·급여조건 원자적 임시저장 |
| `api.institution.human_resources.employment_contract.reorder` | `/api/institution/human-resources/employment-contract/reorder` | 선택행 상·하 이동과 드래그 결과를 계약 `sort_no`에 저장 |
| `api.institution.human_resources.employment_contract.submit` | `/api/institution/human-resources/employment-contract/submit` | 공용 결재요청 생성 |
| `api.institution.human_resources.employment_contract.withdraw` | `/api/institution/human-resources/employment-contract/withdraw` | 진행 중 기안 회수 |
| `api.institution.human_resources.employment_contract.revise` | `/api/institution/human-resources/employment-contract/revise` | 승인 계약의 신규 개정 초안 생성 |
| `api.institution.human_resources.employment_contract.terminate` | `/api/institution/human-resources/employment-contract/terminate` | 승인 계약 종료·해지 |
| `api.institution.human_resources.employment_contract.delete` | `/api/institution/human-resources/employment-contract/delete` | 허용 상태 계약 소프트삭제 |
| `api.institution.human_resources.employment_contract.trash` | `/api/institution/human-resources/employment-contract/trash` | 근로계약 휴지통 |
| `api.institution.human_resources.employment_contract.restore` | `/api/institution/human-resources/employment-contract/restore` | 삭제 계약 복원 |
| `api.institution.human_resources.employment_contract.purge` | `/api/institution/human-resources/employment-contract/purge` | 결재 보호조건을 통과한 휴지통 계약 완전삭제 |
| `web.institution.human_resources.job_assignments` | `/institution/human-resources/job-assignments` | 기준일별 직원 직무·배치 목록과 상세 기간이력 화면 |
| `api.institution.human_resources.job_assignment.list` | `/api/institution/human-resources/job-assignment/list` | 기준일·직원·조직·직무·프로젝트·근무지·상태 조건 목록 조회 |
| `api.institution.human_resources.job_assignment.detail` | `/api/institution/human-resources/job-assignment/detail` | 직원 현재요약, 부서·직위·직무·프로젝트·근무지·재직상태·인사발령 이력 조회 |
| `api.institution.human_resources.job_assignment.options` | `/api/institution/human-resources/job-assignment/options` | Modal과 선택형 검색에서 최초 사용 시 불러오는 코드·직무·부서·직위 최소 옵션 조회 |
| `api.institution.human_resources.job_assignment.history_save` | `/api/institution/human-resources/job-assignment/history-save` | 인사발령 도입 이전의 종료된 과거 직무 이력 등록 |
| `api.institution.human_resources.job_assignment.project_save` | `/api/institution/human-resources/job-assignment/project-save` | 비주요·단기·겸임 프로젝트 배치 등록 |
| `api.institution.human_resources.job_assignment.end` | `/api/institution/human-resources/job-assignment/end` | 직접 등록된 비주요 프로젝트 배치 종료 |
| `api.institution.human_resources.job_assignment.correct` | `/api/institution/human-resources/job-assignment/correct` | 직접 등록 직무·프로젝트 배치 관리자 정정 |
| `api.integration.biz_status` | `/api/integration/biz-status` | 로그인 사용자가 거래처·전표·개인경비 화면에서 공용으로 사용하는 사업자 상태 조회. 별도 시스템 설정 권한 없이 인증만 검사한다. |
| `web.ledger.funds.daily_report` | `/ledger/funds/daily-report` | 기준일 회사 전체 자금일보 화면. 확정 상태 전표의 증빙·canonical `ACCOUNT` 참조로 판정한 내부 입출금은 외부 입출금과 분리한다. |
| `web.ledger.funds.payment_schedule` | `/ledger/funds/payment-schedule` | 지급의무·지급계획과 실제 은행 출금 배분을 관리하는 지급예정현황 화면. 기존 route/page/permission key를 유지한다. |
| `api.ledger.funds.daily_report.report` | `/api/funds/daily-report/report` | 자금일보 요약·자금수단·당일 외부/내부 입출금 조회 |
| `api.ledger.funds.daily_report.excel` | `/api/funds/daily-report/excel` | 현재 조건 자금일보 엑셀 다운로드 |

## 지급예정현황 API

| Key | URL | Responsibility |
|---|---|---|
| `api.ledger.funds.payment_schedule.list` | `/api/funds/payment-schedule/list` | 조건별 지급예정 목록·계산상태·요약 조회 |
| `api.ledger.funds.payment_schedule.detail` | `/api/funds/payment-schedule/detail` | 지급예정·실제지급 연결·업무이력 상세 조회 |
| `api.ledger.funds.payment_schedule.save` | `/api/funds/payment-schedule/save` | 전표 승인으로 자동 생성된 지급예정의 예정일·귀속·지급계좌·메모 수정. 신규 등록은 차단한다. |
| `api.ledger.funds.payment_schedule.hold` | `/api/funds/payment-schedule/hold` | 지급 보류와 사유 기록 |
| `api.ledger.funds.payment_schedule.release_hold` | `/api/funds/payment-schedule/release-hold` | 지급 보류 해제 |
| `api.ledger.funds.payment_schedule.delete` | `/api/funds/payment-schedule/delete` | 활성 실제지급 연결이 없는 지급예정 소프트삭제 |
| `api.ledger.funds.payment_schedule.trash` | `/api/funds/payment-schedule/trash` | 지급예정 휴지통 조회 |
| `api.ledger.funds.payment_schedule.restore` | `/api/funds/payment-schedule/restore` | 참조 무결성을 재검증한 지급예정 복원 |
| `api.ledger.funds.payment_schedule.excel` | `/api/funds/payment-schedule/excel` | 현재 조회조건 지급예정 엑셀 다운로드 |
| `api.ledger.funds.payment_schedule.bank_withdrawals` | `/api/funds/payment-schedule/bank-withdrawals` | 배분 가능한 입출금(은행) 출금 원본 검색 |
| `api.ledger.funds.payment_schedule.allocate` | `/api/funds/payment-schedule/allocate` | 은행 출금 원본을 지급예정에 금액 배분 |
| `api.ledger.funds.payment_schedule.release_allocation` | `/api/funds/payment-schedule/release-allocation` | 실제지급 배분 연결 해제 |
## 2026-08-06 — 자격·교육관리

| Key prefix | URL prefix | Responsibility |
|---|---|---|
| web.institution.human_resources.qualification_education | /institution/human-resources/qualification-education | 자격·교육관리 화면 |
| api.institution.human_resources.qualification_education.* | /api/institution/human-resources/qualification-education/* | 본인·전체 목록, 상세, 저장, 삭제, 검증, 갱신, 교육과정, Excel |
