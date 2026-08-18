# 인사발령관리 1단계 실제 DB·Repository 조사 및 사전승인 설계

## 결론

- 실제 DB는 MariaDB 10.11.11, DB `sukhyang`, 호스트 `SUKHYANGNAS:3307`이다.
- 인사발령의 기존 정식 명칭은 Route·Page 기준 `personnel-actions`이며, DB 도메인명 후보는 프로젝트 명명규칙에 맞춘 `institution_personnel_actions`다.
- 실제 직원 현재정보 SSOT는 `user_employees`의 부서·직위·입퇴사일뿐이다. 재직상태, 직급, 직무, 근무지, 휴직·복직, 직원 변경이력, 직원 프로젝트 기간배치 SSOT는 없다.
- `system_projects.employee_id`는 프로젝트 담당직원 1명이며 직원의 프로젝트 배치가 아니다. `system_work_team_members`는 거래처 작업팀원 구조여서 직원 프로젝트 배치 SSOT로 재사용할 수 없다.
- 따라서 프로젝트배치·해제까지 적용하는 인사발령 테이블만 먼저 만들면 적용 대상 SSOT가 없어 문서와 현재상태가 불일치한다. 신규 DB 구조 사전승인 규칙과 추가 구조 승인 조건에 따라 Migration 작성·실행을 중단한다.

## 1. 실제 업무 문제와 사용자 시나리오

인사담당자가 한 발령 문서에 여러 직원을 넣고 직원마다 부서, 직위, 재직·휴복직·퇴직, 직무, 근무지, 프로젝트 배치의 변경 전후 값을 기안한다. 결재 승인 후 발령일이 도래하면 현재정보에 원자적으로 반영하고, 적용 결과와 원본 발령을 보존해야 한다. 미래 발령은 승인 즉시 적용하지 않으며 적용완료 문서는 직접 수정하지 않고 정정 또는 취소 문서로 연결한다.

## 2. 실제 DB SSOT

| 업무 개념 | 실제 SSOT | 조사 결과 |
|---|---|---|
| 직원 | `user_employees` | `id`, `user_id`, `employee_name`, `department_id`, `position_id`, 문서상·실제 입퇴사일 보유 |
| 로그인·활성 | `auth_users` | `approved`, `is_active`, `deleted_at`; 퇴직일과 별도로 로그인 가능 여부를 결정 |
| 부서 | `user_departments` | 실제 FK 대상. 계층형이라는 COMMENT와 달리 부모 컬럼은 없음 |
| 직위·직책 | `user_positions` | 하나의 테이블·컬럼으로 통합. `level_rank`는 정렬 등급이며 독립 직급 FK가 아님 |
| 직급 | 없음 | 별도 마스터·직원 FK 없음 |
| 직무 | 없음 | `institution_employment_contracts.job_description`은 계약 스냅샷이며 현재 직무 SSOT가 아님 |
| 근무지 | `WORK_LOCATION_TYPE` 코드와 근로계약 스냅샷만 존재 | 직원 현재 근무지 SSOT 없음 |
| 프로젝트 | `system_projects` | 프로젝트 자체 SSOT. `employee_id`는 담당직원 1명 |
| 직원 프로젝트 배치 | 없음 | 다중 프로젝트·기간·이력 SSOT 없음 |
| 작업팀 | `system_work_teams`, `system_work_team_members` | 구성원이 `client_id`인 거래처 작업팀 구조. 직원 배치로 재사용 불가 |
| 휴직·복직 | 없음 | 상태·기간·이력 테이블 없음 |
| 퇴직 | `user_employees.doc_retire_date`, `real_retire_date` | 별도 퇴직 사유·이력 없음 |
| 직원 변경이력 | 없음 | 직원 테이블 자체에도 생성·수정 Actor 컬럼 없음 |
| 근로계약 | `institution_employment_contracts`와 4개 자식·마스터 테이블 | 계약 당시 조건과 결재상태 projection의 SSOT |
| 전자결재 | `user_approval_requests`, `user_approval_request_steps` | 업무문서 연결, 전체 상태와 불변 단계 이력 SSOT |
| 급여·근태 | 인사 운영 SSOT 없음 | `ledger_evidence_payroll`은 회계 증빙 본문이며 급여대장 아님. 근태·휴가 메뉴는 Placeholder |

실제 DB에는 인사발령, 재직상태, 직급, 직무, 근무지 마스터, 직원 프로젝트 배치, 휴직·복직, 직원 변경이력 테이블이 없다.

## 3. Repository 영향 범위

- 직원 직접 수정 경로가 존재한다: `/api/settings/organization/employee/save` → `EmployeeController::apiSave()` → `EmployeeService::save()` → `EmployeeModel`의 `INSERT/UPDATE user_employees`.
- 직접 수정 필드는 `department_id`, `position_id`, `doc_hire_date`, `real_hire_date`, `doc_retire_date`, `real_retire_date`를 포함한다. 인사발령 적용 후에는 발령 대상 필드의 임의 직접수정 제한 또는 정정발령 유도가 필요하다.
- 재직 판정은 `EmployeeModel`, `EmploymentContractModel`, `ApprovalInboxModel`에서 퇴사일을 직접 비교한다. 신규 재직상태 SSOT를 만들 경우 세 영역과 공용 직원 피커, 결재 적격성, 로그인 활성정책을 함께 전환해야 한다.
- 전자결재 연동은 `ApprovalWorkflowService`, 문서 Adapter Registry, `ApprovalInboxModel`, 결재함 View·JS까지 영향받는다.
- 인사발령·직무배치·근태·휴가는 현재 Web Route, 권한 메타, Sidebar, Breadcrumb, Placeholder만 존재한다. 인사발령 API·Controller·Service·Model·Validation·JS·CSS는 없다.
- 관련 공용 사전에는 근로계약, 전자결재, 공용 DataTable·SearchForm·Picker가 등록되어 있으나 인사발령 Service·API·Table은 없다.

## 4. 근로계약관리 UI 비교

### 재사용 가능한 계열

- 페이지 제목 → 공용 버튼 → `ui-search.php` → `ui-table.php` → 공용 Pagination 순서
- 서버 검색·정렬·페이징과 컬럼설정을 지원하는 `createDataTable`
- `SearchForm`, 상태 badge, `actorColumn`, 공용 휴지통, 행 더블클릭 상세 열기
- `ui-form-modal`, `ui-form-card`, `modal-xl`, 날짜·시간 Picker, Select2 기반 `AdminPicker`
- 작성중만 편집, 결재대기 회수, 승인 후 개정이라는 상태별 읽기전용 전환
- 공용 결재 Workflow와 문서 Adapter 구조

### 그대로 복제할 수 없는 부분

- 근로계약은 직원 1명·계약 1건이며 인사발령은 헤더 1건 → 대상자 N명 → 대상자별 변경항목 N건이다.
- 근로계약은 별도 상세 모달이 아니라 동일 등록 모달을 읽기전용으로 사용한다. 인사발령도 같은 패턴은 가능하지만 대상자·변경항목 Grid가 필요하다.
- 현재 근로계약의 직원·프로젝트 선택은 서버 제공 옵션을 Select2로 꾸민 구조이며 원격 공용 피커 API를 사용하지 않는다.
- 근로계약에는 첨부파일 구현이 없다. 인사발령 첨부는 공용 파일 정책과 독립 파일 링크 책임을 별도 설계·승인해야 한다.
- 권한별 버튼은 Route 권한 보호가 중심이고 화면 버튼은 주로 업무상태로 제어된다. 인사발령은 save, submit, withdraw, apply, cancel 권한을 서버에서 각각 검증해야 한다.
- 반려는 결재함에서 처리되고 업무문서는 작성상태로 projection된다. 재상신은 새 결재요청 행을 생성하는 공용 정책을 사용한다.

## 5. 책임 경계

- 인사발령관리: 변경 기안, 결재, 미래 적용 예약, 원자적 적용 결과, 정정·취소 원본 연결을 소유한다.
- 직무·배치관리: 적용된 현재 직무·프로젝트 배치와 기간 이력을 조회·운영한다. 승인 문서나 결재상태를 중복 저장하지 않는다.
- 근로계약: 계약 당시 조건 스냅샷을 보존하며 직원 현재 직무·근무지·배치를 대신하지 않는다.
- 로그인: 퇴직 발령 적용 시 `auth_users.is_active` 변경 여부는 별도 명시 정책이 필요하다. 퇴직일만으로 계정을 자동 비활성화한다고 가정하지 않는다.

## 6. 대안 비교와 권고 구조

### 대안 A: 범용 문자열·JSON EAV

변경코드와 문자열 before/after만 저장하는 방식은 빠르지만 FK 무결성, 삭제정책, 타입 검증, 적용 SQL의 안전성을 보장하지 못한다. JSON fallback과 표시값 기반 판정이 생기므로 채택하지 않는다.

### 대안 B: 변경항목별 상세 테이블

무결성은 가장 강하지만 현재 SSOT가 없는 직급·직무·근무지·재직·배치까지 선제 테이블이 늘어나고 조회·적용 흐름이 과도하게 분산된다. 현 단계에서는 채택하지 않는다.

### 대안 C: 발령 헤더·대상자 + 유형이 지정된 물리 컬럼 변경행

기존 FK가 있는 부서·직위·프로젝트는 before/after FK를 두고, 코드관리 항목은 before/after 코드값을 두며 CHECK로 변경유형별 허용 컬럼을 제한한다. 문자열·JSON 범용 저장을 피할 수 있다. 다만 프로젝트 FK만으로는 기간 배치 현재상태를 표현하지 못하므로 직원 프로젝트 배치 SSOT 승인이 선행되어야 한다.

권고는 대안 C이며, 다음 신규 구조 묶음을 한 번에 승인받아야 한다.

1. `institution_personnel_actions`: 발령 문서 헤더
2. `institution_personnel_actions_targets`: 대상 직원과 적용 결과
3. `institution_personnel_actions_changes`: 유형이 지정된 변경 전후 물리값
4. 별도 승인 대상 `institution_job_assignments_project_histories`: 직원·프로젝트·배치기간·역할·현재/종료 이력 SSOT
5. 재직·휴복직·직무·근무지 SSOT는 컬럼 추가와 별도 기간 이력 중 어떤 구조로 갈지 추가 업무정책 확정

## 7. 인사발령 3개 테이블 DDL 초안

### `institution_personnel_actions`

`id`, `sort_no`, `action_no`, `action_name`, `action_type_code`, `action_date`, `action_reason`, `business_status`, `current_approval_request_id`, `original_action_id`, `correction_kind`, `approved_at`, `applied_at`, `cancelled_at`, `cancelled_reason`, `note`, 생성·수정 Actor, soft-delete Actor를 둔다.

- PK: `id`
- UK: `action_no`, `sort_no`
- FK: 결재요청 `ON DELETE SET NULL`, 원본 발령 `ON DELETE RESTRICT`
- INDEX: `(business_status, action_date, deleted_at)`, `current_approval_request_id`, `original_action_id`
- CHECK: 상태 코드, 정정종류, 승인·적용·취소 날짜의 상태별 정합성, 자기 원본 참조 금지

### `institution_personnel_actions_targets`

`id`, `personnel_action_id`, `employee_id`, `sort_no`, `individual_reason`, `application_status`, `applied_at`, `application_error`, 생성·수정 Actor를 둔다.

- PK: `id`
- UK: `(personnel_action_id, employee_id)`, `(personnel_action_id, sort_no)`
- FK: 발령 `ON DELETE CASCADE`, 직원 `ON DELETE RESTRICT`
- INDEX: `(employee_id, application_status)`, `(personnel_action_id, application_status)`
- CHECK: 적용상태와 적용일시·오류내용의 상호 정합성

### `institution_personnel_actions_changes`

`id`, `target_id`, `sort_no`, `change_type_code`, 부서 before/after FK, 직위 before/after FK, 프로젝트 배치 before/after FK, 코드형 before/after 값, 날짜형 before/after 값, 텍스트 스냅샷 before/after, `effective_date`, 생성·수정 Actor를 둔다.

- PK: `id`
- UK: `(target_id, change_type_code)` 및 `(target_id, sort_no)`
- FK: 대상자 `ON DELETE CASCADE`; 부서·직위·프로젝트·배치 참조는 `ON DELETE RESTRICT`
- INDEX: 변경유형, 각 after FK, 적용기준일
- CHECK: before와 after가 같지 않음, 유형별 관련 물리 컬럼만 사용, 적용기준일 필수

표시 스냅샷은 과거 문서 가독성을 위한 값이며 적용 판정에는 사용하지 않는다. Actor 값은 향후 Service에서 `ActorHelper`만 사용한다.

## 8. 상태·코드 정책

- 결재상태는 `user_approval_requests.status`의 `pending`, `in_progress`, `approved`, `rejected`, `withdrawn`, `cancelled`가 SSOT다.
- 발령 업무상태는 별도 공용 코드그룹 `PERSONNEL_ACTION_STATUS`가 필요하다. 권고 코드는 `DRAFT`, `APPROVAL_PENDING`, `APPROVED`, `APPLIED`, `CANCELLED`다. 반려·회수는 결재 이력에 남기고 발령은 `DRAFT`로 projection하여 한 컬럼에 결재 이력과 업무상태를 합치지 않는다.
- 발령유형은 공용 코드그룹 `PERSONNEL_ACTION_TYPE`으로 관리한다. 권고 코드는 `HIRE`, `DEPARTMENT_TRANSFER`, `POSITION_CHANGE`, `PROMOTION`, `JOB_CHANGE`, `PROJECT_ASSIGN`, `PROJECT_RELEASE`, `WORK_LOCATION_CHANGE`, `TRANSFER`, `LEAVE_OF_ABSENCE`, `REINSTATEMENT`, `RETIREMENT`, `OTHER`다.
- 코드관리 FK는 현재 프로젝트가 물리 FK를 사용하지 않고 Service에서 활성 코드를 검증하는 정책을 따른다. ENUM이나 별도 중복 마스터는 만들지 않는다.
- 미래 발령은 `APPROVED`에 머물고 `action_date <= 기준일`일 때 별도 적용 작업이 `APPLIED`로 전환한다.

## 9. 생명주기·감사·삭제

- 작성중만 수정·소프트삭제 가능하다. 결재요청마다 새 `user_approval_requests` 행을 생성한다.
- 승인 후 발령일 도래 시 대상자 단위가 아니라 문서 전체를 트랜잭션으로 적용하는 것을 기본으로 한다. 실패 시 rollback하고 대상 오류를 기록하는 정책은 재시도·부분성공 요구에 따라 확정한다.
- 적용완료 문서는 직접 수정·삭제하지 않는다. 정정·취소 문서가 원본을 `RESTRICT` FK로 참조한다.
- Header purge는 작성중이며 결재 이력이 없을 때만 허용하고 Target·Change를 명시 삭제 후 건수를 검증한다.
- 예상 규모는 발령 문서 수천 건, 대상자·변경행 수만 건 수준으로 보고 직원·상태·발령일 복합 인덱스를 둔다.

## 10. Migration·Rollback·Backfill

- 기존 Migration은 수정하지 않고 신규 up/down SQL을 사용한다.
- 현재 인사발령 데이터가 없으므로 Backfill은 없다. 기존 직원 현재값은 첫 발령 작성 시 before 값으로 서버가 읽어 잠근다.
- down은 인사발령 3개 테이블이 비어 있고 결재요청 참조가 없을 때만 역순 DROP한다.
- 운영 DB 실행 전 사본 DB에서 MariaDB 10.11.11 구문, FK 대상 타입·collation, 제약명·인덱스명 충돌, CHECK 강제 여부를 검증한다.

## 11. 미생성 시 장애와 기술부채

- 현재는 직원정보 API가 부서·직위·퇴사일을 직접 덮어써 승인·미래적용·변경이력·정정 근거가 없다.
- 인사발령 3개 테이블만 만들고 배치 SSOT를 만들지 않으면 프로젝트배치 발령을 적용할 곳이 없어 문서와 운영상태가 분리된다.
- 재직상태를 퇴사일로만 추론하는 코드가 직원 목록, 근로계약 직원 피커, 결재 적격성에 중복되어 있다.
- 직위와 직책이 하나의 SSOT이고 직급·직무 SSOT가 없어 발령유형의 법적·업무적 의미를 완전히 표현하지 못한다.

## 12. 다음 화면 구현 초안

- 목록: 발령번호, 발령명, 발령유형, 발령일, 대상자 수, 업무상태, 결재상태, 승인일시, 적용일시, 작성자, 수정일시, 관리.
- 검색: 발령일, 키워드, 발령유형, 업무상태, 결재상태, 직원.
- 모달: 발령 기본정보 카드, 대상 직원 공용 DataTable, 선택 직원의 변경 전·후 항목 Grid, 발령사유, 첨부, 결재·적용 시스템정보.
- 버튼: 신규등록, 임시저장, 결재요청, 회수, 재상신, 정정발령, 취소발령, 적용재시도, 휴지통. 서버 권한과 상태를 모두 검증한다.
- 공용 SearchForm·DataTable·Pagination·직원 Picker·프로젝트 Picker·날짜 Picker·휴지통·Actor 렌더링을 우선 사용한다.

## 13. 승인 후 확정 구조

사전승인 결과 인사발령 3개 테이블과 함께 재직상태, 휴직, 직무, 프로젝트 배치, 근무지의 공통 현재상태·기간이력 기반을 생성한다. 부서·직위는 기존 직원 현재값을 유지하고 발령 변경행이 전후 FK와 표시 스냅샷을 보존한다.

직무별 필수 자격·교육, 자격 유효기간, 교육 이수주기와 프로젝트 배치 적격성은 아직 업무 규칙이 확정되지 않았으므로 선행 컬럼을 만들지 않는다. 향후 자격·교육 도메인의 관계 테이블이 `institution_job_assignments_jobs.id`를 참조한다.

프로젝트 배치의 참여율, 책임자 여부, 평가대상 여부도 현재 Migration에 선행 생성하지 않는다. 배치의 안정적인 UUID, 직원·프로젝트·직무 FK와 기간구조가 있으므로 향후 승인된 Migration으로 확장한다.

## 14. 향후 페이지별 재사용 매핑

| 기능 | 재사용 SSOT | 사용 방식 |
|---|---|---|
| 근로계약관리 | 근로계약 자체 테이블, 직원·직무·근무지·프로젝트 조회 | 계약 체결 당시 값을 스냅샷으로 저장하며 현재 직원 상태를 역갱신하지 않는다. |
| 인사발령관리 | 발령 헤더·대상자·변경행, 전자결재 | 변경 기안, 미래 적용, 정정·취소와 적용 근거를 관리한다. |
| 직무·배치관리 | 직무·프로젝트·근무지 기간배치 | 적용된 현재상태와 기간 이력을 조회·운영한다. |
| 근태관리 | 재직상태 이력, 휴직기간, 근무지·프로젝트 배치 | 근무일 기준 재직·휴직·근무장소와 프로젝트를 판정하고 실제 근무실적은 별도 근태 SSOT에 저장한다. |
| 휴가관리 | 재직상태 이력, 휴직기간 | 특정 날짜가 재직 중이며 휴직기간이 아닌지 판정한다. 휴가는 휴직 테이블에 저장하지 않는다. |
| 자격·교육관리 | `institution_job_assignments_jobs`, 직원 직무·프로젝트 배치 | 향후 직무별 필수 자격·교육 관계와 프로젝트 적격성 판단의 FK 기준으로 사용한다. |
| 성과평가관리 | 재직·직무·근무지·프로젝트 기간 이력, 적용된 부서·직위 발령 변경행 | 직무·근무지·프로젝트는 기간 테이블로, 부서·직위는 적용 발령의 전후 FK로 도입 이후 평가기간 상태를 재현한다. 도입 전 부서·직위 과거이력은 복원하지 않는다. |
| 보상·인센티브관리 | 평가 결과, 직원·직무·프로젝트 기간 기준 | 평가기간 기준 조직 맥락과 보상 결과를 연결하되 근로계약 급여 스냅샷을 현재 보상 SSOT로 사용하지 않는다. |
| 취업규칙·인사규정 | 재직·휴직·직무 코드와 기간 기준 | 규정 적용대상 판정에 공통 상태를 사용하고 규정 문서 자체는 별도 도메인이 소유한다. |

## 15. 다음 페이지 구현 책임

- 인사발령 적용 Service가 직원 현재 재직상태·부서·직위·현재 직무와 관련 기간 이력을 한 트랜잭션에서 갱신한다.
- 직원 직접 수정 API는 이번 단계에서 제거하지 않지만 발령 적용 대상 필드는 페이지 구현 단계에서 직접 수정 제한 또는 정정발령 흐름으로 전환한다.
- 프로젝트·직무·근무지·휴직 기간 중복은 인덱스와 `SELECT ... FOR UPDATE` 기반 Service Validation을 결합한다.
- 퇴직 발령은 재직상태만 변경하며 `auth_users.is_active`는 퇴직정산·자료열람 정책이 확정될 때 별도 처리한다.

## 16. Migration 및 실제 DB 검증 결과

- `20260804_01_create_hr_common_ssot`로 9개 테이블, 직원 현재상태 2개 컬럼, 6개 공용 코드그룹 35개 코드를 생성했다.
- 기존 직원 10명은 `ACTIVE` 5명, `RETIRED` 5명으로 Backfill했고 입사·퇴직 사실 15행을 재직상태 이력으로 생성했다.
- Backfill 불확정 직원 0명, 현재상태 불일치 0건, 상태이력 고아 0건, 신규 테이블 빈 컬럼 COMMENT 0건이다.
- `20260804_02_allow_scheduled_hr_assignment_end_dates`로 계획·활성 직무·프로젝트·근무지 배치의 예정 종료일을 허용했다. 현재 주 프로젝트와 현재 근무지 중복키는 종료일이 아니라 활성상태를 기준으로 생성한다.
- 격리 DB에서 01 up/down과 02 up/down을 각각 실행해 롤백 후 잔존 테이블·직원 컬럼 0건을 확인했다.
- 실제 DB에서 01 재실행은 기존 9개 테이블과 직원 컬럼 2개를 감지해 DDL 실행 전에 차단됐다.

## 17. 인사·노무 공통 SSOT Baseline 최종 검토

### 발령 변경행

현재 변경행은 11개 인사 변경유형에 필요한 FK·코드·날짜 컬럼만 허용하고 유형별 CHECK로 관련 없는 컬럼 사용을 차단한다. 임의 키와 문자열·JSON 값을 저장하는 범용 EAV가 아니다. 대상자별 변경명령을 한 Grid와 한 적용 트랜잭션에서 정렬·검증하는 현재 업무에는 단일 테이블이 유형별 상세 테이블보다 조회와 적용 책임이 명확하다.

향후 급여·자격·교육처럼 독립 생명주기나 다중 상세행을 갖는 업무는 이 테이블에 nullable 컬럼을 계속 추가하지 않는다. 해당 도메인 SSOT를 만들고 발령 변경행은 승인된 명령 참조만 보존한다. 현재 구조는 변경 없이 Baseline으로 확정한다.

### 재직상태 전이

| 현재상태 | 다음 상태 | 업무 의미 |
|---|---|---|
| `PENDING_HIRE` | `ACTIVE` | 미래 입사 발령일 도래 후 재직 시작 |
| `ACTIVE` | `ON_LEAVE` | 휴직 발령 적용과 휴직기간 시작 |
| `ON_LEAVE` | `ACTIVE` | 복직 발령 적용과 휴직기간 종료 |
| `ACTIVE`, `ON_LEAVE` | `RETIRED` | 퇴직 발령 적용 |

핵심 재직상태는 `ACTIVE`, `ON_LEAVE`, `RETIRED`다. `PENDING_HIRE`는 승인된 미래 입사 발령의 상태 복제값이 아니라, 직원 UUID가 발급되어 근로계약·발령 대상·사전 준비업무에서 참조되지만 아직 재직을 시작하지 않은 직원 마스터 행의 현재 생명주기 상태다. 미래 입사의 승인과 적용 대기는 인사발령의 `APPROVED`, `action_date`가 SSOT이며, 발령일 전 `PENDING_HIRE` 직원은 재직자·근태·휴가 대상에서 제외한다. 이 책임 분리를 전제로 `PENDING_HIRE`를 유지한다. `SUSPENDED` 등 미확정 후보는 문서에만 남기고 코드나 CHECK에 추가하지 않는다. 퇴직과 로그인 비활성화는 별도 정책이다.

### 프로젝트 역할과 직무

- `job_id`: 회사 전체에서 재사용하는 정형 직무 FK다. 자격·교육 요구조건, 평가와 보상의 기준이 된다.
- `assignment_role`: 특정 프로젝트에서 실제 맡는 역할 문구다. 동일 직무라도 프로젝트마다 역할이 달라질 수 있다.
- 두 값을 합치거나 역할을 직무 코드로 중복 관리하지 않는다. 역할의 반복 표준화 요구가 실제로 확인될 때만 별도 역할 마스터를 검토한다.

### 직무 마스터

`institution_job_assignments_jobs`는 `sort_no`, `job_code`, `job_name`, `description`, 유효기간, `is_active`, 생성·수정·삭제 Actor와 일시를 모두 갖는다. 향후 자격·교육 관계, 평가기준, 보상정책은 별도 관계 테이블에서 `institution_job_assignments_jobs.id`를 참조할 수 있으므로 추가 컬럼이 필요하지 않다.

### 근무지

Baseline 유형은 본사 `HEAD_OFFICE`, 프로젝트 현장 `PROJECT`, 출장 `BUSINESS_TRIP`, 재택 `REMOTE`, 기타 `OTHER`다. 프로젝트 현장만 프로젝트 FK를 필수로 하고 출장·재택·기타는 기존 장소명·주소 스냅샷으로 표현한다. 출장 일정이나 재택 승인처럼 별도 생명주기가 필요해지면 근태·신청 도메인에서 확장한다.

### 미래 발령 적용

`DRAFT → APPROVAL_PENDING → APPROVED → 발령일 도래 → Scheduler → PersonnelActionApplyService → APPLIED` 흐름을 사용한다. Scheduler는 적용 Service만 호출하며 직원 현재상태, 기간이력, 대상별 결과, 발령상태 전환을 직접 갱신하지 않는다.

### 화면 구현 DB 준비도

목록, SearchForm, DataTable, 등록·상세 모달, 대상 직원 DataTable, 변경 전후 Grid, 결재요청, 미래 발령 표시와 적용 상태 표시에 필요한 헤더·대상자·변경행·결재 포인터·날짜·상태·Actor 구조가 모두 존재한다. 인사발령관리 화면 구현을 위해 추가 DB 변경은 필요하지 않다.

## 18. Runtime 최소 Baseline 보강 및 1차 구현

이전 화면 DB 변경 불필요 판단은 실제 화면·적용 계약을 대조한 결과 보강되었다. Migration `20260804_04_extend_personnel_action_runtime_baseline`은 승인된 네 범위만 반영한다.

- 헤더 `issued_date`는 발령일이고 `action_date`는 효력일이다.
- `institution_job_assignments_department_histories`와 `institution_job_assignments_position_histories`는 부서 및 통합 직위·직책 기간 이력 SSOT다.
- `institution_personnel_actions_targets.applied_by`가 대상자별 적용처리자를 보존한다.
- 기존 10명은 재직상태 이력 최초 시작일을 우선하여 현재 확인 가능한 기준시점 이력만 생성했다. 과거 부서·직위 변경은 추정하지 않았다.
- `PersonnelActionService`는 작성중 문서·대상자·변경행과 공용 결재 흐름을, `PersonnelActionApplyService`는 승인·효력일·현재값 충돌 검증 및 직원 마스터·기간 이력의 원자 적용을 담당한다.
- `PENDING_HIRE`는 효력일 전까지 유지되고 입사발령 적용 시 `ACTIVE`와 재직상태 이력으로 전환된다.
- 변경행에 범용 종료일과 사유를 추가하지 않는다. 종료일은 기간 이력, 사유는 헤더와 대상자가 소유한다.

## 19. PERSONNEL_ACTION 결재선 및 1차 완료 정책

- 실제 DB의 활성 템플릿 `7540cbd0-679b-4bd4-919c-afede9622d6c`(인사발령)을 단일 SSOT로 사용한다.
- 확정 결재선은 `발의(SUBMIT, 로그인 사용자) → 최종승인(FINAL_APPROVAL, 지정결재자 이정호)` 2단계다. 관리자 중간승인은 생성하지 않는다.
- 최종단계의 `role_id` 최고관리자는 이정호의 역할 적격성을 검증한다. `approver_id`가 있으므로 결재함 노출·상세 행동·승인 SQL은 이정호 일치만 허용하고 역할 대체를 허용하지 않는다.
- 최종승인과 효력 적용은 분리한다. 오늘·과거 효력일은 승인 직후 공식 Apply Service를 호출하고, 미래 효력일은 `APPROVED`로 대기한 뒤 Scheduler가 같은 Service만 호출한다.
- 결재함 목록·알림·상세에 `PERSONNEL_ACTION` 문서조인과 Adapter를 연결하며 다른 문서유형을 재사용하지 않는다.
